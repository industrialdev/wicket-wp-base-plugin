<?php

declare(strict_types=1);

// No direct access
defined('ABSPATH') || exit;

/**
 * MDP service-identity helpers: generic CRUD over the polymorphic
 * `identifiable` (people OR organizations), with person-flavored wrappers.
 *
 * A service identity maps an identifiable to an external service and carries
 * that service's external_id (e.g. OBA's bar number on the "Bar Number"
 * service). The external_id is set-once/immutable; a service configured with
 * generation_strategy: 'sequential' auto-mints the next value when external_id
 * is omitted on create. Deleted numbers still count toward the next value, but
 * a deleted value can be re-added by supplying it explicitly on create.
 *
 * Generic functions take an identifiable_type ('people'|'organizations') plus
 * identifiable_id. The wicket_*_person_service_identity() wrappers pass
 * 'people' and stay for the import/batch call sites that predate the generic
 * layer; new code should prefer the generic functions.
 *
 * All calls use the admin-token client (wicket_api_client()); service-identity
 * management is a back-office operation, not current-user-scoped.
 *
 * @see https://developers.wicketcloud.com  /service_identities
 */

/**
 * The identifiable types that can own a service identity.
 *
 * @return string[]
 */
function wicket_service_identity_identifiable_types(): array
{
    return ['people', 'organizations'];
}

/**
 * Create a service identity for an identifiable (mint).
 *
 * POST /service_identities. When $external_id is omitted and the service is
 * configured generation_strategy: 'sequential', the MDP mints the next
 * sequential value and returns it on 201. Supply $external_id to set it
 * explicitly (e.g. re-adding a previously-deleted number). The value is
 * immutable after creation (set-once).
 *
 * Callers that may run more than once for the same identifiable (import
 * retries, re-runs) MUST check for an existing identity first via
 * wicket_find_service_identity_by_external_id() (or the person wrapper) to
 * avoid creating a duplicate; the MDP's per-service uniqueness depends on the
 * service's uniqueness_scope, which can be 'none'.
 *
 * @param string      $identifiable_type 'people' or 'organizations'.
 * @param string      $identifiable_id   UUID of the identifiable.
 * @param string      $service_uuid      UUID of the service.
 * @param string|null $external_id       Explicit value, or null to auto-generate.
 * @return array|WP_Error The created entry ({id, attributes, relationships}), or WP_Error.
 */
function wicket_create_service_identity(string $identifiable_type, string $identifiable_id, string $service_uuid, ?string $external_id = null): array|WP_Error
{
    if ($identifiable_id === '' || $service_uuid === '') {
        return new WP_Error('wicket_service_identity_missing_args', __('identifiable_id and service_uuid are required.', 'wicket'));
    }
    if (! in_array($identifiable_type, wicket_service_identity_identifiable_types(), true)) {
        return new WP_Error(
            'wicket_service_identity_bad_type',
            sprintf(__('identifiable_type must be one of: %s.', 'wicket'), implode(', ', wicket_service_identity_identifiable_types()))
        );
    }

    $client = wicket_api_client();
    if ($client === false) {
        return new WP_Error('wicket_client_unavailable', __('Wicket API client is not available.', 'wicket'));
    }

    $attributes = [];
    if ($external_id !== null && $external_id !== '') {
        $attributes['external_id'] = $external_id;
    }

    $payload = [
        'data' => [
            'type' => 'service_identities',
            'attributes' => $attributes,
            'relationships' => [
                'service' => ['data' => ['type' => 'services', 'id' => $service_uuid]],
                'identifiable' => ['data' => ['type' => $identifiable_type, 'id' => $identifiable_id]],
            ],
        ],
    ];

    try {
        $response = $client->post('service_identities', ['json' => $payload]);
    } catch (Throwable $e) {
        wicket_service_identity_log('error', 'wicket_create_service_identity failed.', [
            'identifiable_type' => $identifiable_type,
            'identifiable_id' => $identifiable_id,
            'service_uuid' => $service_uuid,
            'error' => $e->getMessage(),
        ]);

        return new WP_Error('wicket_service_identity_create_failed', $e->getMessage());
    }

    $entry = wicket_service_identity_unwrap($response);
    if ($entry === null || empty($entry['id'])) {
        return new WP_Error('wicket_service_identity_no_id', __('MDP returned no service identity id.', 'wicket'));
    }

    return $entry;
}

/**
 * Read one service identity by its UUID.
 *
 * GET /service_identities/{id}.
 *
 * @param string $identity_uuid UUID of the service identity.
 * @return array|null The entry, or null when not found / on lookup failure.
 */
function wicket_get_service_identity(string $identity_uuid): ?array
{
    if ($identity_uuid === '') {
        return null;
    }

    $client = wicket_api_client();
    if ($client === false) {
        return null;
    }

    try {
        $response = $client->get('service_identities/' . rawurlencode($identity_uuid));
    } catch (Throwable $e) {
        wicket_service_identity_log('warning', 'wicket_get_service_identity failed.', [
            'identity_uuid' => $identity_uuid,
            'error' => $e->getMessage(),
        ]);

        return null;
    }

    $entry = wicket_service_identity_unwrap($response);

    // Guard against an error envelope / non-resource body being treated as found.
    return (is_array($entry) && ! empty($entry['id'])) ? $entry : null;
}

/**
 * Update a service identity's mutable attributes.
 *
 * PATCH /service_identities/{id}. external_id is immutable after creation and
 * is stripped if present; set namespace / external_url / data here.
 *
 * @param string $identity_uuid UUID of the service identity.
 * @param array  $attributes    Mutable attributes (namespace, external_url, data).
 * @return array|WP_Error The updated entry, or WP_Error.
 */
function wicket_update_service_identity(string $identity_uuid, array $attributes): array|WP_Error
{
    if ($identity_uuid === '') {
        return new WP_Error('wicket_service_identity_missing_args', __('identity_uuid is required.', 'wicket'));
    }

    // external_id is set-once/immutable; never send it on update.
    unset($attributes['external_id']);
    if ($attributes === []) {
        return new WP_Error('wicket_service_identity_no_attrs', __('No mutable attributes to update (external_id is immutable).', 'wicket'));
    }

    $client = wicket_api_client();
    if ($client === false) {
        return new WP_Error('wicket_client_unavailable', __('Wicket API client is not available.', 'wicket'));
    }

    $payload = [
        'data' => [
            'type' => 'service_identities',
            'id' => $identity_uuid,
            'attributes' => $attributes,
        ],
    ];

    try {
        $response = $client->patch('service_identities/' . rawurlencode($identity_uuid), ['json' => $payload]);
    } catch (Throwable $e) {
        wicket_service_identity_log('error', 'wicket_update_service_identity failed.', [
            'identity_uuid' => $identity_uuid,
            'error' => $e->getMessage(),
        ]);

        return new WP_Error('wicket_service_identity_update_failed', $e->getMessage());
    }

    $entry = wicket_service_identity_unwrap($response);
    if ($entry === null || empty($entry['id'])) {
        return new WP_Error('wicket_service_identity_no_entry', __('MDP returned no service identity.', 'wicket'));
    }

    return $entry;
}

/**
 * Delete a service identity.
 *
 * DELETE /service_identities/{id}. A deleted sequential number still counts
 * toward the service's next value; re-add a deleted value by supplying it
 * explicitly on a later create.
 *
 * @param string $identity_uuid UUID of the service identity.
 * @return bool|WP_Error True on success, or WP_Error.
 */
function wicket_delete_service_identity(string $identity_uuid): bool|WP_Error
{
    if ($identity_uuid === '') {
        return new WP_Error('wicket_service_identity_missing_args', __('identity_uuid is required.', 'wicket'));
    }

    $client = wicket_api_client();
    if ($client === false) {
        return new WP_Error('wicket_client_unavailable', __('Wicket API client is not available.', 'wicket'));
    }

    try {
        $client->delete('service_identities/' . rawurlencode($identity_uuid));
    } catch (Throwable $e) {
        wicket_service_identity_log('error', 'wicket_delete_service_identity failed.', [
            'identity_uuid' => $identity_uuid,
            'error' => $e->getMessage(),
        ]);

        return new WP_Error('wicket_service_identity_delete_failed', $e->getMessage());
    }

    return true;
}

/**
 * List service identities with optional server-side filters.
 *
 * GET /service_identities?filter[...]. Supported $filters keys (all optional):
 *   - service_uuid       -> filter[service_uuid_eq]
 *   - external_id        -> filter[external_id_eq]
 *   - identifiable_type  -> filter[identifiable_type_eq]   ('people'|'organizations')
 *   - page_size          -> page[size] (default 100)
 *
 * There is no filter by identifiable UUID on this endpoint:
 * service_identities.identifiable_id is an internal integer FK, not the UUID.
 * Scope a query to one identifiable via the nested /people/{id}/service_identities
 * endpoint (see wicket_get_person_service_identity()).
 *
 * Returns the first page only (up to page_size entries). Callers that need
 * every identity on a service must page through links.next themselves.
 *
 * @param array $filters Optional filter map.
 * @return array<int,array> Matching entries (empty array on failure / none).
 */
function wicket_list_service_identities(array $filters = []): array
{
    $client = wicket_api_client();
    if ($client === false) {
        return [];
    }

    $query = ['page[size]=' . max(1, (int) ($filters['page_size'] ?? 100))];
    $map = [
        'service_uuid' => 'service_uuid_eq',
        'external_id' => 'external_id_eq',
        'identifiable_type' => 'identifiable_type_eq',
    ];
    foreach ($map as $key => $filterKey) {
        $val = $filters[$key] ?? null;
        if (is_string($val) && $val !== '') {
            $query[] = 'filter[' . $filterKey . ']=' . rawurlencode($val);
        }
    }

    try {
        $response = $client->get('service_identities?' . implode('&', $query));
    } catch (Throwable $e) {
        wicket_service_identity_log('warning', 'wicket_list_service_identities failed.', [
            'filters' => $filters,
            'error' => $e->getMessage(),
        ]);

        return [];
    }

    return wicket_service_identity_unwrap_list($response);
}

/**
 * Find a service identity by its external_id within a service (reverse lookup).
 *
 * Convenience over wicket_list_service_identities() that returns the first
 * match's full entry (so the caller can read the identifiable type + id), or
 * null. Service identities are unique per (service, identifiable_type) when the
 * service's uniqueness_scope is 'identifiable_type' (the OBA Bar Number
 * default), so the first match is the only match in that case.
 *
 * @param string $external_id  The value to match (e.g. bar number).
 * @param string $service_uuid UUID of the service to search within.
 * @return array|null The entry, or null when no match / on lookup failure.
 */
function wicket_find_service_identity_by_external_id(string $external_id, string $service_uuid): ?array
{
    if ($external_id === '' || $service_uuid === '') {
        return null;
    }

    $matches = wicket_list_service_identities([
        'external_id' => $external_id,
        'service_uuid' => $service_uuid,
        'page_size' => 1,
    ]);

    return $matches[0] ?? null;
}

/**
 * Resolve an MDP service by its slug.
 *
 * GET /services?filter[slug_eq]={slug}. The MDP recommends resolving
 * services by slug (stable) rather than name. Returns the full service entry
 * ({id, attributes:{name, slug, ...}}) so callers can read the UUID, or null.
 *
 * Used to resolve the service UUID the service-identity helpers need (e.g. an
 * OBA minter resolving its 'bar-number' service before minting).
 *
 * @param string $slug The service slug (e.g. 'bar-number').
 * @return array|null The service entry, or null when not found / on lookup failure.
 */
function wicket_get_service_by_slug(string $slug): ?array
{
    if ($slug === '') {
        return null;
    }

    $client = wicket_api_client();
    if ($client === false) {
        return null;
    }

    try {
        $response = $client->get('services?filter[slug_eq]=' . rawurlencode($slug) . '&page[size]=1');
    } catch (Throwable $e) {
        wicket_service_identity_log('warning', 'wicket_get_service_by_slug lookup failed.', [
            'slug' => $slug,
            'error' => $e->getMessage(),
        ]);

        return null;
    }

    $entry = is_array($response) && isset($response['data'][0]) && is_array($response['data'][0])
        ? $response['data'][0]
        : null;

    return (is_array($entry) && ! empty($entry['id'])) ? $entry : null;
}

// ---------------------------------------------------------------------------
// Person wrappers (back-office + import call sites; pass identifiable 'people')
// ---------------------------------------------------------------------------

/**
 * Mint a service identity for a person, letting the MDP generate external_id.
 *
 * Thin wrapper over wicket_create_service_identity() for the people type.
 * Kept for the import/batch call sites that predate the generic layer; new code
 * should call wicket_create_service_identity('people', ...) directly.
 *
 * @param string      $person_uuid  UUID of the person.
 * @param string      $service_uuid UUID of the service.
 * @param string|null $external_id  Explicit value, or null to auto-generate.
 * @return array|WP_Error The created entry, or WP_Error.
 */
function wicket_mint_service_identity(string $person_uuid, string $service_uuid, ?string $external_id = null): array|WP_Error
{
    return wicket_create_service_identity('people', $person_uuid, $service_uuid, $external_id);
}

/**
 * Get a person's service identity for a specific service, if one exists.
 *
 * Lists the person's service identities via the per-identifiable endpoint
 * (GET /people/:id/service_identities?filter[service_uuid_eq]=…) and returns
 * the first match. Used as the idempotency check before wicket_mint_service_identity().
 *
 * @param string $person_uuid  UUID of the person.
 * @param string $service_uuid UUID of the service to match.
 * @return array|null The matching entry, or null when none / on lookup failure.
 */
function wicket_get_person_service_identity(string $person_uuid, string $service_uuid): ?array
{
    if ($person_uuid === '' || $service_uuid === '') {
        return null;
    }

    // Memoize found identities only, keyed per (person, service) for batch/import
    // use. Absence and failures are deliberately NOT cached: an absent result is
    // re-fetched so a same-request mint is immediately visible on re-check, and a
    // transient API error must not be remembered as "no identity" or a retry would
    // skip the idempotency check and mint a dupe.
    static $cache = [];
    $cache_key = $person_uuid . '|' . $service_uuid;
    if (array_key_exists($cache_key, $cache)) {
        return $cache[$cache_key];
    }

    $client = wicket_api_client();
    if ($client === false) {
        return null;
    }

    try {
        $response = $client->get('people/' . rawurlencode($person_uuid) . '/service_identities?filter[service_uuid_eq]=' . rawurlencode($service_uuid) . '&page[size]=100');
    } catch (Throwable $e) {
        // Fail safe: a lookup failure surfaces as "no existing identity", so the
        // caller proceeds to mint. NOTE the MDP does NOT unconditionally enforce
        // per-service uniqueness - it depends on the service's uniqueness_scope,
        // which can be 'none' (no DB uniqueness, no backstop). So this precheck
        // is load-bearing for dupe prevention, not belt-and-suspenders. Logged.
        wicket_service_identity_log('warning', 'wicket_get_person_service_identity lookup failed.', [
            'person_uuid' => $person_uuid,
            'service_uuid' => $service_uuid,
            'error' => $e->getMessage(),
        ]);

        return null;
    }

    foreach (wicket_service_identity_unwrap_list($response) as $identity) {
        $match_service_id = $identity['relationships']['service']['data']['id'] ?? '';
        if ($match_service_id === $service_uuid) {
            $cache[$cache_key] = $identity;

            return $identity;
        }
    }

    return null;
}

/**
 * The external_id of a person's service identity for a given service.
 *
 * Convenience wrapper around wicket_get_person_service_identity() that returns
 * just the external_id (e.g. the OBA bar number), or null when the person has
 * no identity on that service or the lookup failed.
 *
 * @param string $person_uuid  UUID of the person.
 * @param string $service_uuid UUID of the service.
 * @return string|null The external_id, trimmed, or null.
 */
function wicket_get_person_service_external_id(string $person_uuid, string $service_uuid): ?string
{
    $identity = wicket_get_person_service_identity($person_uuid, $service_uuid);
    if ($identity === null) {
        return null;
    }

    $raw = $identity['attributes']['external_id'] ?? null;
    if ($raw === null || $raw === '') {
        return null;
    }

    $value = trim((string) $raw);

    return $value !== '' ? $value : null;
}

/**
 * Find the person that owns a given service identity value (reverse lookup).
 *
 * Wraps wicket_find_service_identity_by_external_id() and returns the owning
 * person UUID, or null when no match / the match is not a person / on failure.
 *
 * @param string $external_id  The service identity value to match (e.g. bar number).
 * @param string $service_uuid UUID of the service to search within.
 * @return string|null Person UUID, or null.
 */
function wicket_find_person_by_service_external_id(string $external_id, string $service_uuid): ?string
{
    $entry = wicket_find_service_identity_by_external_id($external_id, $service_uuid);
    if ($entry === null) {
        return null;
    }

    $identifiable = $entry['relationships']['identifiable']['data'] ?? null;
    if (! is_array($identifiable) || ($identifiable['type'] ?? '') !== 'people') {
        return null;
    }

    $id = $identifiable['id'] ?? '';

    return is_string($id) && $id !== '' ? $id : null;
}

/**
 * Find the WP user that owns a given service identity value.
 *
 * Resolves the person UUID via wicket_find_person_by_service_external_id(),
 * then matches it to a WP user by user_login. In the Wicket stack the MDP
 * person UUID IS the WP user_login, so the match is exact and needs no extra
 * meta. Use this for "get a user by their bar ID" flows.
 *
 * @param string $external_id  The service identity value (e.g. bar number).
 * @param string $service_uuid UUID of the service.
 * @return WP_User|null
 */
function wicket_find_wp_user_by_service_external_id(string $external_id, string $service_uuid): ?WP_User
{
    $person_uuid = wicket_find_person_by_service_external_id($external_id, $service_uuid);
    if ($person_uuid === null) {
        return null;
    }

    $user = get_user_by('login', $person_uuid);

    return $user instanceof WP_User ? $user : null;
}

// ---------------------------------------------------------------------------
// Internal helpers
// ---------------------------------------------------------------------------

/**
 * Unwrap a JSON:API single-entry response to the entry, or null.
 *
 * Accepts either an enveloped `{data: {...}}` response or a bare resource
 * object. A bare response is only treated as an entry when it is a real
 * resource object (has `id` + `type`) - never an `{errors:[...]}` envelope,
 * which would otherwise be mistaken for a found identity.
 *
 * @param mixed $response Raw client response.
 * @return array|null The entry, or null.
 */
function wicket_service_identity_unwrap($response): ?array
{
    if (is_array($response) && isset($response['data']) && is_array($response['data'])) {
        return $response['data'];
    }

    if (is_array($response) && isset($response['id'], $response['type'])) {
        return $response;
    }

    return null;
}

/**
 * Unwrap a JSON:API list response to the entries array.
 *
 * @param mixed $response Raw client response.
 * @return array<int,array> The `{data}` list, or [] when absent / wrong shape.
 */
function wicket_service_identity_unwrap_list($response): array
{
    if (is_array($response) && isset($response['data']) && is_array($response['data'])) {
        return $response['data'];
    }

    return [];
}

/**
 * Log a service-identity helper message when the Wicket logger is present.
 *
 * @param string $level   'error' | 'warning' | 'info'.
 * @param string $message Log message.
 * @param array  $context Log context.
 */
function wicket_service_identity_log(string $level, string $message, array $context = []): void
{
    $logger = function_exists('Wicket') ? Wicket()->log() : null;
    if ($logger !== null) {
        $logger->{$level}($message, $context);
    }
}
