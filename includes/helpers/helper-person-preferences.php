<?php

declare(strict_types=1);

// No direct access
defined('ABSPATH') || exit;

/**
 * Update a person's communication preferences in the MDP.
 *
 * This function updates the communication preferences for a person identified by their UUID.
 * It can update the main email preference and/or multiple sublist preferences in a single API call.
 *
 * @param array $preferences An associative array of communication preferences:
 *                           - 'email' (bool|null): Main email communication preference
 *                           - 'sublists' (array|null): Associative array of sublist preferences
 *                             e.g., ['one' => true, 'two' => false, 'three' => true]
 * @param array $options Optional. An associative array of options:
 *                       - 'person_uuid' (string): The UUID of the person to update. Defaults to current user.
 *
 * @return object|false The API response on success, or false on failure.
 */
function wicket_person_update_communication_preferences($preferences, $options = [])
{
    // Parse options with defaults
    $defaults = [
        'person_uuid' => null,
    ];
    $options = wp_parse_args($options, $defaults);

    // Get person UUID from options or fallback to current user
    $person_uuid = $options['person_uuid'];
    if (empty($person_uuid)) {
        if (function_exists('wicket_current_person_uuid')) {
            $person_uuid = wicket_current_person_uuid();
        }

        if (empty($person_uuid)) {
            error_log('wicket_person_update_communication_preferences: Unable to determine person UUID');
            return false;
        }
    }

    if (!is_array($preferences) || empty($preferences)) {
        error_log('wicket_person_update_communication_preferences: Invalid preferences array');
        return false;
    }

    try {
        $client = wicket_api_client();
        if (!$client) {
            error_log('wicket_person_update_communication_preferences: Failed to get Wicket API client');
            return false;
        }

        // Build the communications data structure
        $communications_data = [];

        // Add email preference if provided
        if (array_key_exists('email', $preferences) && $preferences['email'] !== null) {
            $communications_data['email'] = (bool) $preferences['email'];
        }

        // Add sublist preferences if provided
        if (array_key_exists('sublists', $preferences) && is_array($preferences['sublists']) && !empty($preferences['sublists'])) {
            $communications_data['sublists'] = $preferences['sublists'];
        }

        // If no valid preferences were provided, return false
        if (empty($communications_data)) {
            error_log('wicket_person_update_communication_preferences: No valid preferences provided');
            return false;
        }

        // Build the payload for the PATCH request
        $payload = [
            'data' => [
                'type' => 'people',
                'id' => $person_uuid,
                'attributes' => [
                    'data' => [
                        'communications' => $communications_data
                    ]
                ]
            ]
        ];

        // Make the PATCH request to the MDP API
        $response = $client->patch("people/$person_uuid", ['json' => $payload]);

        // Log successful update
        error_log(sprintf(
            'wicket_person_update_communication_preferences: Successfully updated preferences for person %s: %s',
            $person_uuid,
            json_encode($communications_data)
        ));

        return $response;
    } catch (Exception $e) {
        error_log(sprintf(
            'wicket_person_update_communication_preferences: Error updating preferences for person %s: %s',
            $person_uuid,
            $e->getMessage()
        ));
        return false;
    }
}

/**
 * Update only the main email communication preference for a person.
 *
 * @param bool  $email_enabled Whether email communications should be enabled.
 * @param array $options Optional. An associative array of options:
 *                       - 'person_uuid' (string): The UUID of the person to update. Defaults to current user.
 *
 * @return object|false The API response on success, or false on failure.
 */
function wicket_person_update_email_preference($email_enabled, $options = [])
{
    return wicket_person_update_communication_preferences(['email' => $email_enabled], $options);
}

/**
 * Update only sublist communication preferences for a person.
 *
 * @param array $sublists Associative array of sublist preferences.
 *                        e.g., ['one' => true, 'two' => false, 'three' => true]
 * @param array $options Optional. An associative array of options:
 *                       - 'person_uuid' (string): The UUID of the person to update. Defaults to current user.
 *
 * @return object|false The API response on success, or false on failure.
 */
function wicket_person_update_sublist_preferences($sublists, $options = [])
{
    if (!is_array($sublists) || empty($sublists)) {
        error_log('wicket_person_update_sublist_preferences: Invalid sublists array provided');
        return false;
    }

    return wicket_person_update_communication_preferences(['sublists' => $sublists], $options);
}

/**
 * Enable all communication preferences for a person (email and all sublists).
 *
 * @param array $available_sublists Array of available sublist keys to enable.
 *                                  Defaults to ['one', 'two', 'three', 'four', 'five']
 * @param array $options Optional. An associative array of options:
 *                       - 'person_uuid' (string): The UUID of the person to update. Defaults to current user.
 *
 * @return object|false The API response on success, or false on failure.
 */
function wicket_person_enable_all_communications($available_sublists = ['one', 'two', 'three', 'four', 'five'], $options = [])
{
    $sublists = array_fill_keys($available_sublists, true);

    return wicket_person_update_communication_preferences([
        'email' => true,
        'sublists' => $sublists
    ], $options);
}

/**
 * Disable all communication preferences for a person (email and all sublists).
 *
 * @param array $available_sublists Array of available sublist keys to disable.
 *                                  Defaults to ['one', 'two', 'three', 'four', 'five']
 * @param array $options Optional. An associative array of options:
 *                       - 'person_uuid' (string): The UUID of the person to update. Defaults to current user.
 *
 * @return object|false The API response on success, or false on failure.
 */
function wicket_person_disable_all_communications($available_sublists = ['one', 'two', 'three', 'four', 'five'], $options = [])
{
    $sublists = array_fill_keys($available_sublists, false);

    return wicket_person_update_communication_preferences([
        'email' => false,
        'sublists' => $sublists
    ], $options);
}

/**
 * Get current communication preferences for a person.
 *
 * @param array $options Optional. An associative array of options:
 *                       - 'person_uuid' (string): The UUID of the person. Defaults to current user.
 *
 * @return array|null The person's communication preferences as an associative array,
 *                    or null if not found or on error.
 */
function wicket_person_get_communication_preferences($options = [])
{
    // Parse options with defaults
    $defaults = [
        'person_uuid' => null,
    ];
    $options = wp_parse_args($options, $defaults);

    // Get person UUID from options or fallback to current user
    $person_uuid = $options['person_uuid'];
    if (empty($person_uuid)) {
        if (function_exists('wicket_current_person_uuid')) {
            $person_uuid = wicket_current_person_uuid();
        }

        if (empty($person_uuid)) {
            error_log('wicket_person_get_communication_preferences: Unable to determine person UUID');
            return null;
        }
    }

    try {
        $client = wicket_api_client();
        if (!$client) {
            error_log('wicket_person_get_communication_preferences: Failed to get Wicket API client');
            return null;
        }

        $person = $client->people->fetch($person_uuid);

        if (function_exists('wicket_convert_obj_to_array')) {
            $person_array = wicket_convert_obj_to_array($person);
        } else {
            $person_array = (array) $person;
        }

        // Extract communication preferences from the data structure
        $communications = $person_array['data']['communications'] ?? [];

        return [
            'email' => $communications['email'] ?? null,
            'sublists' => $communications['sublists'] ?? []
        ];
    } catch (Exception $e) {
        error_log(sprintf(
            'wicket_person_get_communication_preferences: Error getting preferences for person %s: %s',
            $person_uuid,
            $e->getMessage()
        ));
        return null;
    }
}
