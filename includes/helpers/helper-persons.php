<?php

declare(strict_types=1);

// No direct access
defined('ABSPATH') || exit;

/**
 * Get the current person's Wicket person UUID.
 *
 * This function retrieves the UUID of the current person based on their WordPress user login.
 *
 * @return string|null The UUID of the current person, or null if the function `wicket_api_client` is not available.
 */
function wicket_current_person_uuid()
{
    // Get the SDK client from the wicket module.
    if (function_exists('wicket_api_client')) {
        $person_id = wp_get_current_user()->user_login;

        return $person_id;
    }
}

/**
 * Get the current person from Wicket.
 *
 * This function retrieves the current person's details from Wicket.
 *
 * @return object|null The current person object if found, or null if not found.
 */
function wicket_current_person()
{
    static $person = null;

    if (is_null($person)) {
        $person_id = wicket_current_person_uuid();

        if ($person_id) {
            $client = wicket_api_client_current_user();
            $person = $client->people->fetch($person_id);

            return $person;
        }
    }

    return $person;
}

/**
 * Check if the current user has a valid UUID.
 *
 * @return bool True if the current user has a valid UUID as their user_login, false otherwise.
 */
function wicket_person_has_uuid()
{
    $current_user = wp_get_current_user();

    if (!$current_user || !isset($current_user->user_login)) {
        return false;
    }

    // Check if user_login is a valid UUID string
    if (isset($current_user->user_login) && is_string($current_user->user_login) && isValidUuid($current_user->user_login)) {
        return true;
    }

    return false;
}

/**
 * Retrieve a person's details from Wicket by their UUID.
 *
 * @param string $uuid The UUID of the person to fetch.
 *
 * @return object|false The person's details object on success, or false if the UUID is empty or not found.
 */
function wicket_get_person_by_uuid($uuid)
{
    if ($uuid) {
        $client = wicket_api_client();
        $person = $client->people->fetch($uuid);

        return $person;
    }

    return false;
}

/**
 * Alias for wicket_get_person_by_uuid.
 *
 * @param  string $uuid The ID of the person to fetch.
 * @return object|false The person's details object on success, or false if not found.
 */
function wicket_get_person_by_id($uuid)
{
    return wicket_get_person_by_uuid($uuid);
}

/**
 * Retrieve a person's profile from Wicket by their UUID as a plain array.
 *
 * If no UUID is provided, it attempts to use the UUID of the current logged-in WordPress user.
 * Uses wicket_convert_obj_to_array() to provide a simple array payload for developers.
 *
 * @param string|null $person_uuid The UUID of the person. Defaults to null.
 *
 * @return array|null The person's profile array on success, or null on failure or if not found.
 */
function wicket_get_person_profile(?string $person_uuid = null): ?array
{
    if (empty($person_uuid)) {
        // Attempt to get the current person's UUID if not provided
        if (!function_exists('wicket_current_person_uuid')) {
            // Optionally log this error: error_log('Wicket helper function wicket_current_person_uuid() not found.');
            return null;
        }

        $person_uuid = wicket_current_person_uuid();
    }

    // If no UUID could be determined (either not provided or current user has no UUID), cannot proceed
    if (empty($person_uuid)) {
        return null;
    }

    // Ensure the Wicket API client function exists
    if (!function_exists('wicket_api_client')) {
        // Optionally log this error: error_log('Wicket API client function wicket_api_client() not found.');
        return null;
    }

    try {
        $client = wicket_api_client();
        // Fetch SDK resource object then convert to a plain array for easier consumption.
        $profile = $client->people->fetch($person_uuid);

        if (function_exists('wicket_convert_obj_to_array')) {
            return wicket_convert_obj_to_array($profile);
        }

        // Fallback: basic cast if legacy helper unavailable (keys may be less clean)
        return (array) $profile;
    } catch (Exception $e) {
        // Optionally log the exception message for debugging purposes
        // error_log("Error fetching Wicket person profile for UUID {$person_uuid}: " . $e->getMessage());
        return null;
    }
}

/**
 * Add one or more tags to a Wicket person.
 *
 * This function adds tags to a person identified by their UUID. It merges new tags with existing ones.
 *
 * @param string       $person_uuid The UUID of the person to whom tags will be added.
 * @param string|array $tags        A single tag or an array of tags to add.
 *
 * @return object|false The response from the Wicket API on success, or false on failure.
 */
function wicket_person_add_tag($person_uuid, $tags)
{
    try {
        $client = wicket_api_client();
    } catch (Exception $e) {
        Wicket()->log()->error($e->getMessage(), ['source' => 'wicket-base']);

        return false;
    }

    if (!is_array($tags)) {
        $tags = [$tags];
    }

    // Grab current tags, if any
    $wicket_person = wicket_get_person_by_id($person_uuid);
    $existing_tags = $wicket_person->tags ?? [];

    $tags = array_merge($existing_tags, $tags);

    // Add new tags to current tags
    $payload = [
        'data' => [
            'type' => 'people',
            'id' => $person_uuid,
            'attributes' => [
                'tags' => $tags,
            ],
        ],
    ];

    try {
        return $client->patch("people/$person_uuid", ['json' => $payload]);
    } catch (Exception $e) {
        Wicket()->log()->error($e->getMessage(), ['source' => 'wicket-base']);

        return false;
    }
}

/**
 * Find or create a person in Wicket and optionally update their profile.
 *
 * Lookup order:
 *   1. wicket_get_person_by_email() — primary/legacy helper.
 *   2. Direct API filter (/people?filter[emails_address_eq]=) — handles secondary/alias emails.
 *   3. wicket_create_person() — only called when no match is found.
 *
 * After resolving the UUID, optional profile fields (job_title, phone, phone_type)
 * are updated when provided in $extras, regardless of whether the person was just
 * created or already existed. 'email_type' is the exception: it is only applied
 * when a new person is created (passed straight into wicket_create_person()). An
 * existing person's email type is left untouched — this is a "get", not a place
 * to silently overwrite data that may have been set deliberately elsewhere.
 *
 * @param string $first_name Person first name.
 * @param string $last_name  Person last name.
 * @param string $email      Person email address.
 * @param array  $extras     Optional: 'job_title' (string), 'phone' (string),
 *                           'email_type' (string, e.g. 'work'/'personal' — applied
 *                           only when creating a new person), 'phone_type' (string,
 *                           e.g. 'work'/'mobile' — applied to the phone created
 *                           from 'phone'; defaults to 'work').
 * @return string|WP_Error Person UUID on success, WP_Error on failure.
 */
function wicket_create_or_get_person(string $first_name, string $last_name, string $email, array $extras = [])
{
    $first_name = sanitize_text_field($first_name);
    $last_name = sanitize_text_field($last_name);
    $email = is_scalar($email) ? filter_var((string) $email, FILTER_SANITIZE_EMAIL) : '';

    if ('' === $first_name || '' === $last_name || '' === $email) {
        return new WP_Error('invalid_person_data', 'First name, last name, and a valid email are required.');
    }

    if (!function_exists('wicket_create_person')) {
        return new WP_Error('missing_dependency', 'wicket_create_person() is unavailable.');
    }

    // 1. Primary lookup via legacy helper
    $person = null;
    if (function_exists('wicket_get_person_by_email')) {
        $found = wicket_get_person_by_email($email);
        if (!empty($found)) {
            $person = $found;
        }
    }

    // 2. Fallback: direct API filter (catches secondary / alias email addresses)
    if (!$person && function_exists('wicket_api_client')) {
        try {
            $client = wicket_api_client();
            $response = $client->get('/people?filter[emails_address_eq]=' . rawurlencode($email));
            if (!empty($response['data'][0])) {
                $person = $response['data'][0];
            }
        } catch (Throwable $e) {
            // Non-fatal — will attempt create below.
        }
    }

    // 3. Create if still not found
    if (!$person) {
        $emailType = isset($extras['email_type']) ? sanitize_text_field((string) $extras['email_type']) : '';
        $person = wicket_create_person($first_name, $last_name, $email, '', '', '', '', [], $emailType);
        if (!$person || (is_array($person) && isset($person['errors']))) {
            return new WP_Error('person_creation_failed', 'Failed to create person in Wicket.');
        }
    }

    // 4. Extract UUID from whatever shape the API returned
    $uuid = null;
    if (is_array($person)) {
        $uuid = $person['id'] ?? $person['data']['id'] ?? null;
    } elseif (is_object($person)) {
        $uuid = $person->id ?? null;
    }

    if (!$uuid) {
        return new WP_Error('person_resolution_failed', 'Unable to resolve person UUID from Wicket response.');
    }

    // 5. Update optional profile fields (non-fatal on individual failure)
    $job_title = isset($extras['job_title']) ? sanitize_text_field((string) $extras['job_title']) : '';
    if ('' !== $job_title && function_exists('wicket_update_person')) {
        wicket_update_person($uuid, ['attributes' => ['job_title' => $job_title]]);
    }

    $phoneType = isset($extras['phone_type']) ? sanitize_text_field((string) $extras['phone_type']) : 'work';
    $phone = isset($extras['phone']) ? preg_replace('/[^0-9+]/', '', (string) $extras['phone']) : '';
    if ('' !== $phone && function_exists('wicket_create_person_phone')) {
        try {
            wicket_create_person_phone($uuid, [
                'data' => [
                    'type'       => 'phones',
                    'attributes' => ['number' => $phone, 'type' => $phoneType],
                ],
            ]);
        } catch (Throwable $e) {
            // Non-fatal.
        }
    }

    return $uuid;
}

/**
 * Extract a person UUID from any of the shapes the Wicket API and helpers return.
 *
 * A person record may arrive as the JSON:API resource itself (with 'id' and
 * 'attributes.uuid'), wrapped in a 'data' envelope, as a collection, or as an object.
 *
 * @param mixed $person A person resource, envelope, collection, or object.
 * @return string The UUID, or '' when one cannot be found.
 */
function wicket_person_uuid_from_result($person): string
{
    if (is_object($person)) {
        $person = json_decode((string) wp_json_encode($person), true);
    }

    if (!is_array($person)) {
        return '';
    }

    // Unwrap a { data: ... } envelope.
    if (isset($person['data']) && is_array($person['data'])) {
        $person = $person['data'];
    }

    // A collection: take the first resource.
    if (isset($person[0]) && is_array($person[0])) {
        $person = $person[0];
    }

    foreach ([$person['attributes']['uuid'] ?? null, $person['uuid'] ?? null, $person['id'] ?? null] as $candidate) {
        if (is_string($candidate) && $candidate !== '') {
            return $candidate;
        }
    }

    return '';
}

/**
 * Log a Wicket touchpoint error.
 *
 * Uses error level deliberately: WicketWP\Log suppresses anything below error unless
 * WP_DEBUG is on, and these need to be visible in production. The 'wicket-base' source
 * keeps them in the log file support already reads.
 *
 * @param string $message The message to log.
 * @param array  $context Extra context merged into the log entry.
 */
function wicket_tec_log_error(string $message, array $context = []): void
{
    if (!function_exists('Wicket')) {
        return;
    }

    Wicket()->log()->error($message, array_merge([
        'source' => 'wicket-base',
        'context' => 'tec-touchpoint',
    ], $context));
}

/**
 * Decide whether a set of matched person rows identifies exactly one person.
 *
 * Split out from wicket_resolve_person_by_email() so the decision can be tested
 * without an API client, and because the interesting cases are hard to reproduce
 * against live data.
 *
 * meta.page.total_items counts matched rows, not distinct people: one person can match
 * on several of their own addresses. So rows are deduplicated by UUID, and uniqueness is
 * only claimed when the reported total fits inside the page actually read. If the total
 * overruns the page, an unseen match could exist further on, which is ambiguous.
 *
 * @param array $rows      The 'data' rows from a people lookup.
 * @param int   $total     The reported total matched rows (meta.page.total_items).
 * @param int   $page_size The page size that was requested.
 * @return array{status: string, uuids: array<int, string>} status is 'found',
 *               'ambiguous' or 'none'.
 */
function wicket_person_match_verdict(array $rows, int $total, int $page_size): array
{
    $unique = [];
    foreach ($rows as $row) {
        $uuid = wicket_person_uuid_from_result($row);
        if ($uuid !== '') {
            $unique[$uuid] = true;
        }
    }
    $uuids = array_keys($unique);

    if ($uuids === []) {
        return ['status' => 'none', 'uuids' => []];
    }

    if (count($uuids) === 1 && $total <= $page_size) {
        return ['status' => 'found', 'uuids' => $uuids];
    }

    return ['status' => 'ambiguous', 'uuids' => $uuids];
}

/**
 * Resolve an email address to a Wicket person UUID, creating the person if needed.
 *
 * Lookup order:
 *   1. Primary email only. Primary addresses are unique in Wicket, so a hit here is
 *      unambiguous. This reuses the exact query the TEC touchpoint writers have always
 *      used, so behaviour on the common path is unchanged.
 *   2. All email addresses, which also matches secondary and alias addresses. A single
 *      distinct person is used. Several distinct people is reported as 'ambiguous',
 *      because guessing would attach activity to the wrong person.
 *   3. Create a new person.
 *
 * Counting deserves a note. meta.page.total_items counts matched rows, not distinct
 * people, and one person can match on more than one of their own addresses. So rows are
 * deduplicated by UUID, and uniqueness is only claimed when the reported total fits
 * inside the page actually read. Otherwise an unseen match could exist on a later page,
 * which is 'ambiguous', not 'found'.
 *
 * @param string $email The email address to resolve.
 * @param array  $args  Optional:
 *                      'first_name' (string) used only when creating.
 *                      'last_name' (string) used only when creating.
 *                      'email_type' (string) e.g. 'work', used only when creating.
 *                      'create' (bool) create when nothing matched. Default true.
 *                      'on_ambiguous' (string) 'error' to report ambiguity, or 'first'
 *                      to take the first match. Default 'error'.
 *                      'page_size' (int) results per page for the all-emails lookup.
 *                      Default 25.
 * @return array{status: string, uuid: string, source: string, matches: array<int, string>, total: int, code: string}
 *               status is 'found', 'created', 'ambiguous' or 'error'.
 *               source is 'primary', 'any', 'created' or ''.
 */
function wicket_resolve_person_by_email(string $email, array $args = []): array
{
    $args = array_merge([
        'first_name' => '',
        'last_name' => '',
        'email_type' => '',
        'create' => true,
        'match_all_emails' => true,
        'on_ambiguous' => 'error',
        'page_size' => 25,
    ], $args);

    $result = [
        'status' => 'error',
        'uuid' => '',
        'source' => '',
        'matches' => [],
        'total' => 0,
        'code' => '',
    ];

    $email = sanitize_email($email);

    if ($email === '' || !is_email($email)) {
        $result['code'] = 'invalid_email';

        return $result;
    }

    if (!function_exists('wicket_api_client')) {
        $result['code'] = 'client_unavailable';

        return $result;
    }

    // 1. Primary-email lookup. Unique in Wicket, so any hit wins outright.
    try {
        $client = wicket_api_client();
        $primary = $client->get('/people?filter[emails_primary_eq]=true&filter[emails_address_eq]=' . urlencode($email));
    } catch (Throwable $e) {
        // Never let an MDP outage take down a checkout or an admin save.
        $result['code'] = 'api_error';
        wicket_tec_log_error('Wicket person lookup failed for ' . $email . ': ' . $e->getMessage(), ['reason' => 'api_error']);

        return $result;
    }

    $uuid = wicket_person_uuid_from_result($primary['data'] ?? []);

    if ($uuid !== '') {
        return [
            'status' => 'found',
            'uuid' => $uuid,
            'source' => 'primary',
            'matches' => [$uuid],
            'total' => 1,
            'code' => '',
        ];
    }

    // 2. All-email lookup, which picks up secondary and alias addresses. Optional so a
    // caller can keep the historical primary-only behaviour.
    if (!$args['match_all_emails']) {
        return wicket_resolve_person_create($email, $args);
    }

    $page_size = max(1, (int) $args['page_size']);

    try {
        $any = $client->get('/people?filter[emails_address_eq]=' . urlencode($email) . '&page[size]=' . $page_size);
    } catch (Throwable $e) {
        $result['code'] = 'api_error';
        wicket_tec_log_error('Wicket person lookup failed for ' . $email . ': ' . $e->getMessage(), ['reason' => 'api_error']);

        return $result;
    }

    $rows = is_array($any['data'] ?? null) ? $any['data'] : [];
    $total = (int) ($any['meta']['page']['total_items'] ?? count($rows));

    $verdict = wicket_person_match_verdict($rows, $total, $page_size);

    $result['matches'] = $verdict['uuids'];
    $result['total'] = $total;

    if ($verdict['status'] === 'found') {
        return [
            'status' => 'found',
            'uuid' => $verdict['uuids'][0],
            'source' => 'any',
            'matches' => $verdict['uuids'],
            'total' => $total,
            'code' => '',
        ];
    }

    if ($verdict['status'] === 'ambiguous') {
        if ($args['on_ambiguous'] === 'first') {
            return [
                'status' => 'found',
                'uuid' => $verdict['uuids'][0],
                'source' => 'any',
                'matches' => $verdict['uuids'],
                'total' => $total,
                'code' => 'ambiguous_used_first',
            ];
        }

        $result['status'] = 'ambiguous';
        $result['code'] = 'person_ambiguous';

        return $result;
    }

    // 3. Nothing matched.
    return wicket_resolve_person_create($email, $args);
}

/**
 * Create a Wicket person, in the result shape wicket_resolve_person_by_email() returns.
 *
 * @param string $email The email address for the new person.
 * @param array  $args  Resolved args from wicket_resolve_person_by_email().
 * @return array{status: string, uuid: string, source: string, matches: array<int, string>, total: int, code: string}
 */
function wicket_resolve_person_create(string $email, array $args): array
{
    $result = [
        'status' => 'error',
        'uuid' => '',
        'source' => '',
        'matches' => [],
        'total' => 0,
        'code' => '',
    ];

    if (empty($args['create'])) {
        $result['code'] = 'not_found';

        return $result;
    }

    if (!function_exists('wicket_create_person')) {
        $result['code'] = 'create_unavailable';

        return $result;
    }

    $created = wicket_create_person(
        (string) ($args['first_name'] ?? ''),
        (string) ($args['last_name'] ?? ''),
        $email,
        '',
        '',
        '',
        '',
        [],
        (string) ($args['email_type'] ?? '')
    );

    // wicket_create_person() returns ['errors' => ...] on failure, which is truthy.
    // Callers that only tested truthiness fell through with an unset UUID and posted
    // person_id => null to the MDP.
    if (!is_array($created) || isset($created['errors'])) {
        $result['code'] = 'create_failed';
        wicket_tec_log_error('Failed to create Wicket person for ' . $email, ['reason' => 'create_failed']);

        return $result;
    }

    $uuid = wicket_person_uuid_from_result($created);

    if ($uuid === '') {
        $result['code'] = 'create_no_uuid';
        wicket_tec_log_error('Created a Wicket person for ' . $email . ' but read no UUID back', ['reason' => 'create_no_uuid']);

        return $result;
    }

    return [
        'status' => 'created',
        'uuid' => $uuid,
        'source' => 'created',
        'matches' => [$uuid],
        'total' => 0,
        'code' => '',
    ];
}

/**
 * Work out which MDP person a WooCommerce order belongs to.
 *
 * On a Wicket site the WordPress username is the person's UUID, so the customer's
 * user_login is normally the answer and costs nothing to read. It is not always the
 * answer though: anything that creates WordPress users outside of SSO can leave a
 * username that is not a UUID. The Events Calendar's CSV attendee importer is one,
 * it creates missing users with the email address as the username, so orders it
 * generates would otherwise be written against a person id the MDP has never seen
 * and every touchpoint on them fails with "Person not found".
 *
 * Falls back to resolving the person by the order's billing email, and deliberately
 * does not create one: an order touchpoint should record a person, never invent one.
 * The registration writers are the ones allowed to create.
 *
 * @param  WC_Order|WC_Abstract_Order $order The order.
 * @return string                     The person UUID, or an empty string when there isn't one.
 */
function wicket_person_uuid_for_order($order): string
{
    if (!is_object($order) || !method_exists($order, 'get_user_id')) {
        return '';
    }

    $user = get_user_by('id', $order->get_user_id());

    if ($user instanceof WP_User && isValidUuid((string) $user->user_login)) {
        return (string) $user->user_login;
    }

    $email = method_exists($order, 'get_billing_email') ? (string) $order->get_billing_email() : '';

    if ($email === '' || !function_exists('wicket_resolve_person_by_email')) {
        return '';
    }

    $person = wicket_resolve_person_by_email($email, ['create' => false]);

    return (string) ($person['uuid'] ?? '');
}
