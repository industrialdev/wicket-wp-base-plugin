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


/**
 * Accepts a Wicket person object, like from wicket_current_person(),
 * and returns a clean array of the specified repeatable contact method.
 *
 * @param array  $wicket_person_obj Like from wicket_current_person($uuid)
 * @param string $type              E.g. "addresses", "phones", "web_addresses", "emails"
 *
 * @return array | bool             Array of those contact items if successful, false if not.
 */
function wicket_person_obj_get_repeatable_contact_info($wicket_person_obj, $type, $return_full_arrays = false)
{
    $wicket_person_included = $wicket_person_obj->included()->toArray(); // Converting collection to array
    $contact_items = []; // Will be our array of contact options
    foreach ($wicket_person_included as $elem) {
        if ($elem['type'] !== $type) {
            continue;
        }
        $contact_items[] = $elem;
    }

    if (empty($contact_items)) {
        return false;
    }

    $to_return = [];

    foreach ($contact_items as $contact_item) {
        if ($return_full_arrays) {
            $to_return[] = $contact_item;
        } else {
            $to_return[] = $contact_item['attributes'];
        }
    }

    return $to_return;
}

/**
 * Used if a user exists in the MDP but not WP, and you need to sync them
 * down on a one-off basis, for example processing an order or for roster management.
 *
 * @param string $uuid UUID of their MDP person
 * @param string $first_name (optional) First name override, if needed
 * @param string $last_name  (optional) Last name override, if needed
 * @param string $femail     (optional) Email override, if needed
 *
 * @return bool | int        Will return false if there was a problem, and their new
 *                           WP user ID if successful.
 */
function wicket_create_wp_user_if_not_exist($uuid, $first_name = null, $last_name = null, $email = null)
{
    if (empty($uuid)) {
        return false;
    }

    $user = get_user_by('login', $uuid);
    if ($user) {
        return $user->id;
    }

    // Grab MDP info if overrides were not provided
    if (is_null($first_name) && is_null($last_name) && is_null($email)) {
        $mdp_person = wicket_get_person_by_id($uuid);
        $first_name = $mdp_person->given_name;
        $last_name = $mdp_person->family_name;
        $email = $mdp_person->primary_email_address;
    }

    // Final check if their WP user exists by email, since trying to create them again with the same email will error anyway
    $user = get_user_by('email', $email);
    if ($user) {
        return $user->id;
    }

    // Create the WP user
    $username = sanitize_user($uuid);
    $password = wp_generate_password(12, false);
    //$user_id  = wp_create_user($username, $password, $email);
    $user_id = wp_insert_user([
        'user_email'   => $email,
        'user_pass'    => $password,
        'user_login'   => $username,
        'display_name' => $first_name . ' ' . $last_name,
        'first_name'   => $first_name,
        'last_name'    => $last_name,
        'role'         => 'user',
    ]);

    if (is_wp_error($user_id)) {
        return false;
    }

    return $user_id;
}

/**
 * Get all people from the MDP API.
 *
 * @return object Response collection of people.
 */
function wicket_get_all_people()
{
    $client = wicket_api_client();
    $person = $client->people->all();

    return $person;
}

/**
 * Return a person object by email.
 *
 * @param string $email The email address of the person
 *
 * @return object|bool The person object or false if not found
 */
function wicket_get_person_by_email($email = '')
{
    if (!$email) {
        return false;
    }

    $client = wicket_api_client();
    $person = $client->get('/people?filter[emails_primary_eq]=true&filter[emails_address_eq]=' . urlencode($email));

    // Return the first person if found
    if (isset($person['data'][0])) {
        return $person['data'][0];
    }

    return false;
}

/**
 * Get an address resource by ID from the MDP API.
 *
 * @param string $id Address ID.
 * @return object|null Address resource or null if not found.
 */
function wicket_get_address($id)
{
    static $address = null;
    if (is_null($address)) {
        if ($id) {
            $client = wicket_api_client();
            $address = $client->addresses->fetch($id);

            return $address;
        }
    }

    return $address;
}

/**
 * Check if current logged in person has the member role.
 *
 * @return bool True if person has member role, false otherwise.
 */
function wicket_is_member()
{
    static $has_membership = null;
    if (is_null($has_membership)) {
        $person = wicket_current_person();
        $roles = $person->role_names;
        $has_membership = in_array('member', $roles);
    }

    return $has_membership;
}

/**
 * Build full name from given name and family name of current person.
 *
 * @return string Full name of current person.
 */
function wicket_person_name()
{
    $person = wicket_current_person();

    return $person->given_name . ' ' . $person->family_name;
}

/**
 * For searching person by a term when you don't have a specific UUID, likely to display
 * search results on the front end.
 *
 * @param string $search_term The query term, e.g. 'Rob Ferguson'
 *
 * @return bool | array       False if there was a problem, or an array of the results.
 */
function wicket_search_person($search_term)
{
    try {
        $client = wicket_api_client();
    } catch (Exception $e) {
        return false;
    }

    // --------------------------------------
    // Search using the autocomplete endpoint
    // --------------------------------------

    // Autocomplete is limited to 100 results total.
    $max_results = 100;

    $autocomplete_results = $client->get('/search/autocomplete', [
        'query' => [
            // Autocomplete lookup query, can filter based on name, membership number, email etc.
            'query' => $search_term,
            // Skip side-loading of people for faster request time.
            // 'include' => '',
            'fields' => [
                'people' => 'full_name, primary_email_address',
            ],
            'filter' => [
                // Limit autocomplete results to only people
                'resource_type' => 'people',
            ],
            'page' => [
                'size' => $max_results,
            ],
        ],
    ]);

    $return = [];
    foreach ($autocomplete_results['included'] as $result) {
        $tmp['full_name'] = !empty($result['attributes']['full_name']) ? $result['attributes']['full_name'] : '';
        $tmp['primary_email_address'] = !empty($result['attributes']['primary_email_address']) ? $result['attributes']['primary_email_address'] : '';
        $tmp['id'] = $result['id'];
        $return[] = $tmp;
    }

    return $return;
}

/**
 * Create a basic person record in the MDP API.
 *
 * @param string $given_name Given name.
 * @param string $family_name Family name.
 * @param string $address Optional. Email address.
 * @param string $password Optional. User password.
 * @param string $password_confirmation Optional. Password confirmation.
 * @param string $job_title Optional. Job title.
 * @param string $gender Optional. Gender.
 * @param array $additional_info Optional. Additional data fields array.
 * @param string $email_type Optional. Email type, e.g. 'work' or 'personal'.
 * @return object|array Created person resource object or array with errors.
 */
function wicket_create_person($given_name, $family_name, $address = '', $password = '', $password_confirmation = '', $job_title = '', $gender = '', $additional_info = [], $email_type = '')
{
    $client = wicket_api_client();

    // build person payload
    $payload = [
        'data' => [
            'type' => 'people',
            'attributes' => [
                'given_name' => $given_name,
                'family_name' => $family_name,
            ],
        ],
    ];

    // add optional email ('address'), with optional type (e.g. 'work', 'personal')
    if (isset($address)) {
        $email_attributes = ['address' => $address];
        if (isset($email_type) && '' !== $email_type) {
            $email_attributes['type'] = $email_type;
        }
        $payload['data']['relationships']['emails']['data'][] = [
            'type' => 'emails',
            'attributes' => $email_attributes,
        ];
    }
    // add optional password
    if (isset($password) && isset($password_confirmation) && $password != '' && $password_confirmation != '') {
        $payload['data']['attributes']['user']['password'] = $password;
        $payload['data']['attributes']['user']['password_confirmation'] = $password_confirmation;
    }
    // add optional job title
    if (isset($job_title)) {
        $payload['data']['attributes']['job_title'] = $job_title;
    }
    // add optional gender
    if (isset($gender)) {
        $payload['data']['attributes']['gender'] = $gender;
    }
    // add optional additional info
    if (!empty($additional_info)) {
        $payload['data']['attributes']['data_fields'] = $additional_info;
    }

    try {
        $person = $client->post('people', ['json' => $payload]);

        return $person;
    } catch (Exception $e) {
        $errors = json_decode($e->getResponse()->getBody())->errors;
    }

    return ['errors' => $errors];
}

/**
 * Swiss army knife function for updating many profile attributes of a Wicket user.
 * The $fields_to_update array can include as many or as few high-level profile data types
 * as you need to update, for example, attributes and/or addresses, etc.
 *
 *
 * Example of a $fields_to_update array that updates all available Profile aspects:
 *
 * [
 *  'attributes' => [
 *    'family_name' => '',
 *    'given_name'  => '',
 *    'job_function' => '',
 *    'job_level' => '',
 *    'job_title' => '',
 *    'etc attributes ...'
 *  ],
 *  'addresses' => [
 *    [
 *       'uuid' => '',
 *       'type' => '',
 *       'primary' => true,
 *       'mailing' => false,
 *       'city' => '',
 *       'zip_code' => '',
 *       'address1' => '',
 *       'address2' => '',
 *       'state_name' => '',
 *       'country_code' => '',
 *    ],
 *    [
 *      ... other addresses ...
 *    ]
 *  ],
 *  'phones' => [
 *    [
 *       'uuid' => '', // existing phone # uuid
 *       'primary' => true,
 *       'type' => 'business',
 *       'number' => '+15555555555',
 *    ],
 *    [
 *      ... other phones ...
 *    ]
 *  ],
 *  'emails' => [
 *    [
 *       'uuid' => '', // existing email uuid
 *       'primary' => true,
 *       'type' => 'business',
 *       'address' => 'yo@example.com',
 *       'unique' => true // defaults to true
 *    ],
 *    [
 *      ... other emails ...
 *    ]
 *  ],
 *  'web_addresses' => [
 *    [
 *       'uuid' => '', // existing web_address uuid
 *       'type' => 'website',
 *       'address' => 'https://wicket.io',
 *    ],
 *    [
 *      ... other web addresses ...
 *    ]
 *  ],
 * ]
 *
 * @param string $person_uuid
 * @param array  $fields_to_update
 *
 * @return array Array with 'success' param that will be true if successful, false if not. If false, 'errors'
 *               param will include a list of errors encountered.
 */
function wicket_update_person($person_uuid, $fields_to_update)
{
    $client = wicket_api_client();
    $wicket_person = wicket_get_person_by_id($person_uuid);

    if (empty($wicket_person)) {
        return [
            'success' => false,
            'error'   => 'Wicket person not found',
        ];
    }
    $wicket_person_array = wicket_convert_obj_to_array($wicket_person);

    $attributes = [];
    if (isset($fields_to_update['attributes'])) {
        // Target specific attributes as the /people/uuid patch endpoint only accepts these
        $attributes = [
            'additional_name' => $wicket_person_array['attributes']['additional_name'],
            'family_name' => $wicket_person_array['attributes']['family_name'],
            'given_name' => $wicket_person_array['attributes']['given_name'],
            'honorific_prefix' => $wicket_person_array['attributes']['honorific_prefix'],
            'honorific_suffix' => $wicket_person_array['attributes']['honorific_suffix'],
            'job_function' => $wicket_person_array['attributes']['job_function'],
            'job_level' => $wicket_person_array['attributes']['job_level'],
            'job_title' => $wicket_person_array['attributes']['job_title'],
            'nickname' => $wicket_person_array['attributes']['nickname'],
            'status' => $wicket_person_array['attributes']['status'],
            'suffix' => $wicket_person_array['attributes']['suffix'],
        ];
        foreach ($fields_to_update['attributes'] as $key => $value) {
            if (is_string($value) && trim($value) === '') {
                $fields_to_update['attributes'][$key] = null;
            }
        }
        $attributes = array_merge($attributes, $fields_to_update['attributes']); // Later array will overwrite first one
    }

    // -------------
    // Send updates
    // -------------
    $errors = [];
    $person = null;

    // Attributes
    if (!empty($attributes)) {
        $payload = [
            'data' => [
                'id' => $person_uuid,
                'type' => 'people',
                'attributes' => $attributes,
            ],
        ];

        try {
            $person = $client->patch("people/$person_uuid", ['json' => $payload]);
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }

    // Repeatable contact types
    if (isset($fields_to_update['addresses'])) {
        $addresses_update = wicket_add_update_person_addresses($person_uuid, $fields_to_update['addresses']);
        if (!$addresses_update['success']) {
            $errors[] = $addresses_update['error'];
        }
    }
    if (isset($fields_to_update['phones'])) {
        $phones_update = wicket_add_update_person_phones($person_uuid, $fields_to_update['phones']);
        if (!$phones_update['success']) {
            $errors[] = $phones_update['error'];
        }
    }
    if (isset($fields_to_update['emails'])) {
        $emails_update = wicket_add_update_person_emails($person_uuid, $fields_to_update['emails']);
        if (!$emails_update['success']) {
            $errors[] = $emails_update['error'];
        }
    }
    if (isset($fields_to_update['web_addresses'])) {
        $web_addresses_update = wicket_add_update_person_web_addresses($person_uuid, $fields_to_update['web_addresses']);
        if (!$web_addresses_update['success']) {
            $errors[] = $web_addresses_update['error'];
        }
    }

    if (empty($errors)) {
        return [
            'success' => true,
        ];
    } else {
        return [
            'success' => false,
            'error'   => $errors,
        ];
    }
}

/**
 * Function for updating or creating new addresses for a user.
 *
 * Example $addresses array:
 *
 * [
 *    [
 *       'uuid' => '',
 *       'type' => '',
 *       'primary' => true,
 *       'mailing' => false,
 *       'city' => '',
 *       'zip_code' => '',
 *       'address1' => '',
 *       'address2' => '',
 *       'state_name' => '',
 *       'country_code' => '',
 *    ],
 *    [
 *      ... other addresses ...
 *    ]
 *  ]
 */
function wicket_add_update_person_addresses($person_uuid, $addresses)
{
    $client = wicket_api_client();
    $wicket_person = wicket_get_person_by_id($person_uuid);

    $addresses_to_update = [];
    $addresses_to_create = [];
    $errors = [];

    // Get user current address
    $current_addresses = wicket_person_obj_get_repeatable_contact_info($wicket_person, 'addresses', true); // Return full address arrays for writing back to the MDP, instead of the simple address list

    $addresses_update = wicket_update_addresses($addresses, $current_addresses);
    $addresses_to_update = $addresses_update['updated_addresses'];
    $addresses_to_create = $addresses_update['addresses_not_found'];
    $errors = $errors;

    // Addresses to create
    if (!empty($addresses_to_create)) {
        foreach ($addresses_to_create as $address) {
            $payload = [
                'data' => [
                    'type' => 'addresses',
                    'attributes' => [
                        'address1' => $address['address1'] ?? '',
                        'address2' => $address['address2'] ?? '',
                        'city' => $address['city'] ?? '',
                        'company_name' => $address['company_name'] ?? '',
                        'country_code' => $address['country_code'] ?? '',
                        'department' => $address['department'] ?? '',
                        'division' => $address['division'] ?? '',
                        'mailing' => $address['mailing'] ?? false,
                        'primary' => $address['primary'] ?? false,
                        'state_name' => $address['state_name'] ?? '',
                        'type' => $address['type'] ?? '',
                        'zip_code' => $address['zip_code'] ?? '',
                    ],
                ],
            ];

            try {
                $address_creation = $client->post("people/$person_uuid/addresses", ['json' => $payload]);
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
    }

    if (empty($errors)) {
        return [
            'success' => true,
        ];
    } else {
        return [
            'success' => false,
            'error'   => $errors,
        ];
    }
}

function wicket_update_addresses($updated_addresses, $current_addresses)
{
    $client = wicket_api_client();
    $addresses_to_update = [];
    $addresses_not_found = [];
    $errors = [];

    // Loop both sets of addresses to determine if they should be updated or added anew
    foreach ($updated_addresses as $address_to_add_update) {
        $address_exists = false;
        foreach ($current_addresses as $current_address) {
            if (isset($address_to_add_update['uuid'])) {
                if ($current_address['attributes']['uuid'] === $address_to_add_update['uuid']) {
                    $address_exists = true;
                    $updated_address = $current_address;
                    $updated_address['attributes'] = array_merge($updated_address['attributes'], $address_to_add_update); // Later array will overwrite first one
                    $addresses_to_update[] = $updated_address;
                }
            }
        }
        if (!$address_exists) {
            $addresses_not_found[] = $address_to_add_update;
        }
    }

    /*
     * Send updates
     */

    // Addresses to update
    if (!empty($addresses_to_update)) {
        foreach ($addresses_to_update as $address) {
            $payload = $address;
            $address_uuid = $payload['attributes']['uuid'];

            // Unset params that the MDP provides but doesn't want sent back to it
            unset($payload['attributes']['uuid']);
            unset($payload['attributes']['type_external_id']);
            unset($payload['attributes']['formatted_address_label']);
            unset($payload['attributes']['latitude']);
            unset($payload['attributes']['longitude']);
            unset($payload['attributes']['created_at']);
            unset($payload['attributes']['updated_at']);
            unset($payload['attributes']['deleted_at']);
            unset($payload['attributes']['active']);
            unset($payload['attributes']['consent']);
            unset($payload['attributes']['consent_third_party']);
            unset($payload['attributes']['consent_directory']);

            $payload = [
                'data' => $payload,
            ];

            try {
                $address_update = $client->patch("addresses/$address_uuid", ['json' => $payload]);
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
    }

    return [
        'updated_addresses' => $addresses_to_update,
        'addresses_not_found' => $addresses_not_found,
        'errors' => $errors,
    ];
}

/**
 * Function for updating or creating new phones for a user.
 *
 * Example $phones array:
 *
 * [
 *    [
 *       'uuid' => '', // existing phone # uuid
 *       'primary' => true,
 *       'type' => 'business',
 *       'number' => '+15555555555',
 *    ],
 *    [
 *      ... other phones ...
 *    ]
 *  ]
 */
function wicket_add_update_person_phones($person_uuid, $phones)
{
    $client = wicket_api_client();
    $wicket_person = wicket_get_person_by_id($person_uuid);

    $phones_to_update = [];
    $phones_to_create = [];
    $errors = [];

    // Get user current phone
    $current_phones = wicket_person_obj_get_repeatable_contact_info($wicket_person, 'phones', true); // Return full phone arrays for writing back to the MDP, instead of the simple phone list

    // Loop both sets of phones to determine if they should be updated or added anew
    foreach ($phones as $phone_to_update) {
        $phone_exists = false;
        foreach ($current_phones as $current_phone) {
            if (isset($phone_to_update['uuid'])) {
                if ($current_phone['attributes']['uuid'] === $phone_to_update['uuid']) {
                    $phone_exists = true;
                    $updated_phone = $current_phone;
                    $updated_phone['attributes'] = array_merge($updated_phone['attributes'], $phone_to_update); // Later array will overwrite first one
                    $phones_to_update[] = $updated_phone;
                }
            }
        }
        if (!$phone_exists) {
            $phones_to_create[] = $phone_to_update;
        }
    }

    /*
     * Send updates
     */

    // phones to update
    if (!empty($phones_to_update)) {
        foreach ($phones_to_update as $phone) {
            $payload = $phone;
            $phone_uuid = $payload['attributes']['uuid'];

            // Unset params that the MDP provides but doesn't want sent back to it
            unset($payload['attributes']['uuid']);
            unset($payload['attributes']['type_external_id']);
            unset($payload['attributes']['number_national_format']);
            unset($payload['attributes']['number_international_format']);
            unset($payload['attributes']['extension']);
            unset($payload['attributes']['country_code_number']);
            unset($payload['attributes']['created_at']);
            unset($payload['attributes']['updated_at']);
            unset($payload['attributes']['deleted_at']);
            unset($payload['attributes']['primary_sms']);
            unset($payload['attributes']['consent']);
            unset($payload['attributes']['consent_third_party']);
            unset($payload['attributes']['consent_directory']);

            $payload = [
                'data' => $payload,
            ];

            try {
                $phone_update = $client->patch("phones/$phone_uuid", ['json' => $payload]);
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
    }

    // phones to create
    if (!empty($phones_to_create)) {
        foreach ($phones_to_create as $phone) {
            $payload = [
                'data' => [
                    'type' => 'phones',
                    'attributes' => [
                        'number' => $phone['number'] ?? '',
                        'primary' => $phone['primary'] ?? false,
                        'type' => $phone['type'] ?? '',
                    ],
                ],
            ];

            try {
                $phone_creation = $client->post("people/$person_uuid/phones", ['json' => $payload]);
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
    }

    if (empty($errors)) {
        return [
            'success' => true,
        ];
    } else {
        return [
            'success' => false,
            'error'   => $errors,
        ];
    }
}

/**
 * Function for updating or creating new emails for a user.
 *
 * Example $emails array:
 *
 * [
 *    [
 *       'uuid' => '', // existing email uuid
 *       'primary' => true,
 *       'type' => 'business',
 *       'address' => 'yo@example.com',
 *       'unique' => true // defaults to true
 *    ],
 *    [
 *      ... other emails ...
 *    ]
 *  ]
 */
function wicket_add_update_person_emails($person_uuid, $emails)
{
    $client = wicket_api_client();
    $wicket_person = wicket_get_person_by_id($person_uuid);

    $emails_to_update = [];
    $emails_to_create = [];
    $errors = [];

    // Get user current email
    $current_emails = wicket_person_obj_get_repeatable_contact_info($wicket_person, 'emails', true); // Return full email arrays for writing back to the MDP, instead of the simple email list

    // Loop both sets of emails to determine if they should be updated or added anew
    foreach ($emails as $email_to_update) {
        $email_exists = false;
        foreach ($current_emails as $current_email) {
            if (isset($email_to_update['uuid'])) {
                if ($current_email['attributes']['uuid'] === $email_to_update['uuid']) {
                    $email_exists = true;
                    $updated_email = $current_email;
                    $updated_email['attributes'] = array_merge($updated_email['attributes'], $email_to_update); // Later array will overwrite first one
                    $emails_to_update[] = $updated_email;
                }
            }
        }
        if (!$email_exists) {
            $emails_to_create[] = $email_to_update;
        }
    }

    /*
     * Send updates
     */

    // emails to update
    if (!empty($emails_to_update)) {
        foreach ($emails_to_update as $email) {
            $payload = $email;
            $email_uuid = $payload['attributes']['uuid'];

            // Unset params that the MDP provides but doesn't want sent back to it
            unset($payload['attributes']['uuid']);
            unset($payload['attributes']['type_external_id']);
            unset($payload['attributes']['localpart']);
            unset($payload['attributes']['domain']);
            unset($payload['attributes']['email']);
            unset($payload['attributes']['unique']);
            unset($payload['attributes']['created_at']);
            unset($payload['attributes']['updated_at']);
            unset($payload['attributes']['deleted_at']);
            unset($payload['attributes']['consent']);
            unset($payload['attributes']['consent_third_party']);
            unset($payload['attributes']['consent_directory']);

            $payload = [
                'data' => $payload,
            ];

            try {
                $email_update = $client->patch("emails/$email_uuid", ['json' => $payload]);
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
    }

    // emails to create
    if (!empty($emails_to_create)) {
        foreach ($emails_to_create as $email) {
            $payload = [
                'data' => [
                    'type' => 'emails',
                    'attributes' => [
                        'address' => $email['address'] ?? '',
                        'primary' => $email['primary'] ?? false,
                        'type' => $email['type'] ?? '',
                        'unique' => $email['unique'] ?? true,
                    ],
                ],
            ];

            try {
                $email_creation = $client->post("people/$person_uuid/emails", ['json' => $payload]);
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
    }

    if (empty($errors)) {
        return [
            'success' => true,
        ];
    } else {
        return [
            'success' => false,
            'error'   => $errors,
        ];
    }
}

/**
 * Function for updating or creating new web address for a user.
 *
 * Example $web_addresses array:
 *
 * [
 *    [
 *       'uuid' => '', // existing web_address uuid
 *       'type' => 'website',
 *       'address' => 'https://wicket.io',
 *    ],
 *    [
 *      ... other web addresses ...
 *    ]
 *  ]
 */
function wicket_add_update_person_web_addresses($person_uuid, $web_addresses)
{
    $client = wicket_api_client();
    $wicket_person = wicket_get_person_by_id($person_uuid);

    $web_addresses_to_update = [];
    $web_addresses_to_create = [];
    $errors = [];

    // Get user current web_address
    $current_web_addresses = wicket_person_obj_get_repeatable_contact_info($wicket_person, 'web_addresses', true); // Return full web_address arrays for writing back to the MDP, instead of the simple web_address list

    // Loop both sets of web_addresses to determine if they should be updated or added anew
    foreach ($web_addresses as $web_address_to_update) {
        $web_address_exists = false;
        foreach ($current_web_addresses as $current_web_address) {
            if (isset($web_address_to_update['uuid'])) {
                if ($current_web_address['attributes']['uuid'] === $web_address_to_update['uuid']) {
                    $web_address_exists = true;
                    $updated_web_address = $current_web_address;
                    $updated_web_address['attributes'] = array_merge($updated_web_address['attributes'], $web_address_to_update); // Later array will overwrite first one
                    $web_addresses_to_update[] = $updated_web_address;
                }
            }
        }
        if (!$web_address_exists) {
            $web_addresses_to_create[] = $web_address_to_update;
        }
    }

    /*
     * Send updates
     */

    // web_addresses to update
    if (!empty($web_addresses_to_update)) {
        foreach ($web_addresses_to_update as $web_address) {
            $payload = $web_address;
            $web_address_uuid = $payload['attributes']['uuid'];

            // Unset params that the MDP provides but doesn't want sent back to it
            unset($payload['attributes']['uuid']);
            unset($payload['attributes']['type_external_id']);
            unset($payload['attributes']['data']);
            unset($payload['attributes']['created_at']);
            unset($payload['attributes']['updated_at']);
            unset($payload['attributes']['deleted_at']);
            unset($payload['attributes']['consent']);
            unset($payload['attributes']['consent_third_party']);
            unset($payload['attributes']['consent_directory']);

            $payload = [
                'data' => $payload,
            ];

            try {
                $web_address_update = $client->patch("web_addresses/$web_address_uuid", ['json' => $payload]);
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
    }

    // web_addresses to create
    if (!empty($web_addresses_to_create)) {
        foreach ($web_addresses_to_create as $web_address) {
            $payload = [
                'data' => [
                    'type' => 'web_addresses',
                    'attributes' => [
                        'address' => $web_address['address'] ?? '',
                        'type' => $web_address['type'] ?? '',
                    ],
                ],
            ];

            try {
                $web_address_creation = $client->post("people/$person_uuid/web_addresses", ['json' => $payload]);
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
    }

    if (empty($errors)) {
        return [
            'success' => true,
        ];
    } else {
        return [
            'success' => false,
            'error'   => $errors,
        ];
    }
}

/**
 * Assign a role to a person in the MDP API.
 *
 * Lookup is case-sensitive. Creates the role if it does not exist yet.
 *
 * @param string $person_uuid Person UUID.
 * @param string $role_name Name of the role to assign.
 * @param string $org_uuid Optional. Organization UUID to scope the role relationship.
 * @return bool True on success, false on error.
 */
function wicket_assign_role($person_uuid, $role_name, $org_uuid = '')
{
    $client = wicket_api_client();

    // build role payload
    $payload = [
        'data' => [
            'type' => 'roles',
            'attributes' => [
                'name' => $role_name,
            ],
        ],
    ];

    if ($org_uuid != '') {
        $payload['data']['relationships']['resource']['data']['id'] = $org_uuid;
        $payload['data']['relationships']['resource']['data']['type'] = 'organizations';
    }

    try {
        $client->post("people/$person_uuid/roles", ['json' => $payload]);

        return true;
    } catch (Exception $e) {
        $errors = json_decode($e->getResponse()->getBody())->errors;
    }

    return false;
}

/**
 * Remove a role from a person in the MDP API.
 *
 * Lookup is case-sensitive. When org_id is provided, scopes match to that organization.
 * Idempotent: returns true if the role is already absent.
 *
 * @param string $person_uuid Person UUID.
 * @param string $role_name Name of the role to remove.
 * @param string $org_id Optional. Organization UUID to scope the role relationship.
 * @return bool True on success, false on error.
 */
function wicket_remove_role($person_uuid, $role_name, $org_id = '')
{
    $client = wicket_api_client();
    $person = wicket_get_person_by_id($person_uuid);

    // Never mask a fetch failure: if the person could not be loaded, the role
    // absence is untrustworthy. Return false so callers can surface the error.
    if (!$person) {
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->error('wicket_remove_role: person fetch returned empty', [
                'source' => 'wicket-orgman',
                'person_uuid' => $person_uuid,
                'role_name' => $role_name,
                'org_id' => $org_id,
            ]);
        }

        return false;
    }

    // Normalize included() to a plain iterable array. The SDK returns a
    // Illuminate\Support\Collection (an object) or null.
    $included_raw = is_object($person) && method_exists($person, 'included')
        ? $person->included()
        : null;
    $included_items = [];
    if (is_array($included_raw)) {
        $included_items = $included_raw;
    } elseif ($included_raw instanceof \Traversable) {
        foreach ($included_raw as $inc_item) {
            $included_items[] = $inc_item;
        }
    }

    $role_id = '';
    foreach ($included_items as $included) {
        if (($included['type'] ?? '') !== 'roles') {
            continue;
        }
        if (($included['attributes']['name'] ?? '') !== $role_name) {
            continue;
        }
        // When org_id is provided, only match roles scoped to that org.
        if ($org_id !== '') {
            $resource_id = (string) (
                $included['relationships']['resource']['data']['id']
                ?? $included['relationships']['organization']['data']['id']
                ?? ''
            );
            if ($resource_id !== $org_id) {
                continue;
            }
        }
        $role_id = $included['id'];
        break;
    }

    // Role not found despite a successful person fetch: it is already gone
    // (e.g. membership-derived role auto-revoked server-side). Treat as success.
    if ('' === $role_id) {
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->info('wicket_remove_role: role already absent, treating as success', [
                'source' => 'wicket-orgman',
                'person_uuid' => $person_uuid,
                'role_name' => $role_name,
                'org_id' => $org_id,
            ]);
        }

        return true;
    }

    // build role payload
    $payload = [
        'data' => [
            [
                'type' => 'roles',
                'id' => $role_id,
            ],
        ],
    ];

    try {
        $client->delete("people/$person_uuid/relationships/roles", ['json' => $payload]);

        return true;
    } catch (Exception $e) {
        // Safe error extraction: getResponse() may be null for non-HTTP failures.
        $error_detail = $e->getMessage();
        if (method_exists($e, 'getResponse') && $e->getResponse()) {
            $body = (string) $e->getResponse()->getBody();
            $decoded = json_decode($body, true);
            if (isset($decoded['errors'])) {
                $error_detail .= ' | ' . wp_json_encode($decoded['errors']);
            }
        }
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->error('wicket_remove_role: DELETE request failed', [
                'source' => 'wicket-orgman',
                'person_uuid' => $person_uuid,
                'role_name' => $role_name,
                'org_id' => $org_id,
                'role_id' => $role_id,
                'error' => $error_detail,
            ]);
        }

        return false;
    }
}

/**
 * Create person address in the MDP API.
 *
 * @param string $person_uuid Person UUID.
 * @param array $payload Address payload array.
 * @return bool True on success, false on error.
 */
function wicket_create_person_address($person_uuid, $payload)
{
    $client = wicket_api_client();

    try {
        $org = $client->post("people/$person_uuid/addresses", ['json' => $payload]);

        return true;
    } catch (Exception $e) {
        $errors = json_decode($e->getResponse()->getBody())->errors;
    }

    return false;
}

/**
 * Create person phone in the MDP API.
 *
 * @param string $person_uuid Person UUID.
 * @param array $payload Phone payload array.
 * @return bool True on success, false on error.
 */
function wicket_create_person_phone($person_uuid, $payload)
{
    $client = wicket_api_client();

    try {
        $org = $client->post("people/$person_uuid/phones", ['json' => $payload]);

        return true;
    } catch (Exception $e) {
        $errors = json_decode($e->getResponse()->getBody())->errors;
    }

    return false;
}

/**
 * Delete address record in the MDP API.
 *
 * @param string $address_uuid The address UUID to delete.
 * @return bool True if successful, false if not.
 */
function wicket_delete_address_record($address_uuid)
{
    if (empty($address_uuid)) {
        return false;
    }

    $client = wicket_api_client();

    if (empty($client)) {
        return false;
    }

    try {
        $client->delete("/addresses/{$address_uuid}");

        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Delete email record in the MDP API.
 *
 * @param string $email_uuid The email UUID to delete.
 * @return bool True if successful, false if not.
 */
function wicket_delete_email_record($email_uuid)
{
    if (empty($email_uuid)) {
        return false;
    }

    $client = wicket_api_client();

    if (empty($client)) {
        return false;
    }

    try {
        $client->delete("/emails/{$email_uuid}");

        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Delete phone record in the MDP API.
 *
 * @param string $phone_uuid The phone UUID to delete.
 * @return bool True if successful, false if not.
 */
function wicket_delete_phones_record($phone_uuid)
{
    if (empty($phone_uuid)) {
        return false;
    }

    $client = wicket_api_client();

    if (empty($client)) {
        return false;
    }

    try {
        $client->delete("/phones/{$phone_uuid}");

        return true;
    } catch (Exception $e) {
        return false;
    }
}
