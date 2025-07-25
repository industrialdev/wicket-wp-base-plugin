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
 * Check if the current user has a valid UUID
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
 * Alias for wicket_get_person_by_uuid
 *
 * @param  string $uuid The ID of the person to fetch.
 * @return object|false The person's details object on success, or false if not found.
 */
function wicket_get_person_by_id($uuid)
{
    return wicket_get_person_by_uuid($uuid);
}

/**
 * Retrieve a person's profile from Wicket by their UUID.
 *
 * If no UUID is provided, it attempts to use the UUID of the current logged-in WordPress user.
 *
 * @param string|null $person_uuid The UUID of the person. Defaults to null.
 *
 * @return object|null The person's profile object on success, or null on failure or if not found.
 */
function wicket_get_person_profile_by_uuid(?string $person_uuid = null): ?object
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
        // The Wicket SDK's fetch method typically returns the resource object or throws an exception if not found/error.
        $profile = $client->people->fetch($person_uuid);
        return $profile;
    } catch (\Exception $e) {
        // Optionally log the exception message for debugging purposes
        // error_log("Error fetching Wicket person profile for UUID {$person_uuid}: " . $e->getMessage());
        return null;
    }
}

