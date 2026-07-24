<?php

declare(strict_types=1);

// No direct access
defined('ABSPATH') || exit;

/**
 * Mint a service identity for a person, letting the MDP generate the external_id.
 *
 * Generic MDP primitive for services configured with
 * generation_strategy: 'sequential' (e.g. OBA's "Bar ID" service). When
 * $external_id is omitted (the default), the MDP mints the next sequential
 * numeric value and returns it on the 201. When $external_id is supplied,
 * that value is set explicitly (used to re-add a previously-deleted number).
 *
 * The value is immutable after creation (set-once). Callers that may run more
 * than once for the same person (import retries, re-runs) MUST check the
 * person's existing service identities first via wicket_get_person_service_identity()
 * to avoid minting a duplicate. See wicket_person_has_service_identity().
 *
 * Uses the admin-token client (wicket_api_client()) since service-identity
 * creation is an elevated/back-office operation (imports, admin tooling), not
 * a current-user-scoped action.
 *
 * @see https://developers.wicketcloud.com  POST /service_identities
 *
 * @param string $person_uuid  UUID of the person the identity belongs to.
 * @param string $service_uuid UUID of the service (e.g. the "Bar ID" service).
 * @param string|null $external_id Explicit value to set. Omit (null) to let the
 *                                 MDP auto-generate the next sequential value.
 * @return array|\WP_Error The created service identity entry
 *                         ({id, attributes:{external_id,...}}) on success, or
 *                         WP_Error on failure.
 */
function wicket_mint_service_identity(string $person_uuid, string $service_uuid, ?string $external_id = null)
{
    if ($person_uuid === '' || $service_uuid === '') {
        return new \WP_Error('wicket_service_identity_missing_args', 'person_uuid and service_uuid are required.');
    }

    $client = wicket_api_client();
    if ($client === false) {
        return new \WP_Error('wicket_client_unavailable', 'Wicket API client is not available.');
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
                'service' => [
                    'data' => ['type' => 'services', 'id' => $service_uuid],
                ],
                'identifiable' => [
                    'data' => ['type' => 'people', 'id' => $person_uuid],
                ],
            ],
        ],
    ];

    try {
        $response = $client->post('service_identities', ['json' => $payload]);
    } catch (\Throwable $e) {
        if (function_exists('Wicket') && Wicket()->log()) {
            Wicket()->log()->error('wicket_mint_service_identity failed.', [
                'person_uuid' => $person_uuid,
                'service_uuid' => $service_uuid,
                'error' => $e->getMessage(),
            ]);
        }

        return new \WP_Error('wicket_service_identity_create_failed', $e->getMessage());
    }

    // Unwrap JSON:API envelope to the entry.
    $entry = is_array($response) && isset($response['data']) && is_array($response['data'])
        ? $response['data']
        : (is_array($response) ? $response : []);

    if (empty($entry['id'])) {
        return new \WP_Error('wicket_service_identity_no_id', 'MDP returned no service identity id.');
    }

    return $entry;
}

/**
 * Get a person's service identity for a specific service, if one exists.
 *
 * Lists the person's service identities via GET /people/:id/service_identities
 * and returns the first whose service relationship matches $service_uuid.
 * Used as the idempotency check before wicket_mint_service_identity() so an
 * import retry or re-run does not mint a second identity for the same service.
 *
 * @param string $person_uuid  UUID of the person.
 * @param string $service_uuid UUID of the service to match.
 * @return array|null The matching service identity entry, or null when none / on lookup failure.
 */
function wicket_get_person_service_identity(string $person_uuid, string $service_uuid): ?array
{
    if ($person_uuid === '' || $service_uuid === '') {
        return null;
    }

    $client = wicket_api_client();
    if ($client === false) {
        return null;
    }

    try {
        $response = $client->get("people/{$person_uuid}/service_identities", ['query' => 'page[size]=25']);
    } catch (\Throwable $e) {
        // Fail safe: a lookup failure surfaces as "no existing identity", so the
        // caller proceeds to mint. The MDP's own per-service uniqueness guard is
        // the backstop. Logged for visibility.
        if (function_exists('Wicket') && Wicket()->log()) {
            Wicket()->log()->warning('wicket_get_person_service_identity lookup failed.', [
                'person_uuid' => $person_uuid,
                'service_uuid' => $service_uuid,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    $identities = is_array($response) && isset($response['data']) && is_array($response['data'])
        ? $response['data']
        : (is_array($response) ? $response : []);

    foreach ($identities as $identity) {
        $match_service_id = $identity['relationships']['service']['data']['id'] ?? '';
        if ($match_service_id === $service_uuid) {
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
