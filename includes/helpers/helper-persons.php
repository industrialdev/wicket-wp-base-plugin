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
 * After resolving the UUID, optional profile fields (job_title, phone) are updated
 * when provided in $extras.
 *
 * @param string $first_name Person first name.
 * @param string $last_name  Person last name.
 * @param string $email      Person email address.
 * @param array  $extras     Optional: 'job_title' (string), 'phone' (string).
 * @return string|WP_Error Person UUID on success, WP_Error on failure.
 */
function wicket_create_or_get_person(string $first_name, string $last_name, string $email, array $extras = [])
{
    $first_name = sanitize_text_field($first_name);
    $last_name  = sanitize_text_field($last_name);
    $email      = is_scalar($email) ? filter_var((string) $email, FILTER_SANITIZE_EMAIL) : '';

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
        $person = wicket_create_person($first_name, $last_name, $email);
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

    $phone = isset($extras['phone']) ? preg_replace('/[^0-9+]/', '', (string) $extras['phone']) : '';
    if ('' !== $phone && function_exists('wicket_create_person_phone')) {
        try {
            wicket_create_person_phone($uuid, [
                'data' => [
                    'type'       => 'phones',
                    'attributes' => ['number' => $phone, 'type' => 'work'],
                ],
            ]);
        } catch (Throwable $e) {
            // Non-fatal.
        }
    }

    return $uuid;
}
