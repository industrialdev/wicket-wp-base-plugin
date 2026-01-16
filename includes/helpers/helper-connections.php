<?php

declare(strict_types=1);

// No direct access
defined('ABSPATH') || exit;

/**
 * Update connection attributes including start/end dates and other fields.
 *
 * @param string $connection_id The connection ID to update.
 * @param array $attributes Associative array of attributes to update.
 *                          Supported keys: starts_at, ends_at, description, tags, custom_data_field
 *                          Dates should be formatted as YYYY-MM-DD or ISO 8601 format.
 * @return mixed Response from the API call on success, false otherwise.
 */
function wicket_update_connection_attributes(string $connection_id, array $attributes = []): mixed
{
    if (empty($connection_id)) {
        return false;
    }

    if (empty($attributes)) {
        return false;
    }

    try {
        $client = wicket_api_client();
        if (!$client) {
            return false;
        }
    } catch (Exception $e) {
        return false;
    }

    try {
        // Get current connection info
        $current_connection_info = wicket_get_connection_by_id($connection_id);

        if (empty($current_connection_info)) {
            return false;
        }

        // Merge current attributes with new ones
        $merged_attributes = $current_connection_info['data']['attributes'];

        // Process each attribute
        foreach ($attributes as $key => $value) {
            switch ($key) {
                case 'starts_at':
                case 'ends_at':
                    // Handle date formatting
                    if (!empty($value)) {
                        // If date is in YYYY-MM-DD format, convert to ISO 8601
                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                            $merged_attributes[$key] = $value . 'T00:00:00Z';
                        } else {
                            $merged_attributes[$key] = strval($value);
                        }
                    } else {
                        $merged_attributes[$key] = null;
                    }
                    break;

                case 'description':
                case 'custom_data_field':
                    // Ensure empty fields stay null
                    $merged_attributes[$key] = !empty($value) ? strval($value) : null;
                    break;

                case 'tags':
                    // Ensure tags is an array or null
                    $merged_attributes[$key] = !empty($value) && is_array($value) ? $value : null;
                    break;

                default:
                    // For any other attributes, just set the value
                    $merged_attributes[$key] = $value;
                    break;
            }
        }

        // Build the payload
        $payload = [
            'data' => [
                'attributes' => $merged_attributes,
                'id' => $connection_id,
                'relationships' => [
                    'from' => $current_connection_info['data']['relationships']['from'],
                    'to' => $current_connection_info['data']['relationships']['to'],
                ],
                'type' => $current_connection_info['data']['type'],
            ],
        ];

        // Update the connection
        $updated_connection = $client->patch('connections/' . $connection_id, ['json' => $payload]);

        return $updated_connection;
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        if (strpos($error_message, 'must be before') !== false) {
            // This is a special case where the end date is being set to the same day as the start date
            // So we need to simply remove the connection and return true
            wicket_remove_connection($connection_id);

            return true;
        }

        return false;
    }

    return false;
}

/**
 * Set the description of a connection.
 *
 * @param string $connection_id The connection ID to update.
 * @param string $description The description to set.
 *
 * @return mixed Response from the API call on success, false otherwise.
 */
function wicket_set_connection_description(string $connection_id, string $description = ''): mixed
{
    return wicket_update_connection_attributes($connection_id, ['description' => $description]);
}

/**
 * Patch ONLY the description of a connection, leaving all other attributes and relationships untouched.
 *
 * This sends a minimal payload compliant with Wicket API expectations.
 *
 * @param string $connection_id The connection ID to update.
 * @param string|null $description The description to set (null clears it).
 *
 * @return mixed Response from the API call on success, false otherwise.
 */
function wicket_patch_connection_description(string $connection_id, ?string $description = null): mixed
{
    if ($connection_id === '') {
        return false;
    }

    try {
        $client = wicket_api_client();
    } catch (Exception $e) {
        return false;
    }

    // Normalize empty string to null as per API conventions
    $desc = (isset($description) && $description !== '') ? strval($description) : null;

    $payload = [
        'data' => [
            'type' => 'connections',
            'id' => $connection_id,
            'attributes' => [
                'description' => $desc,
            ],
        ],
    ];

    try {
        $res = $client->patch('connections/' . $connection_id, ['json' => $payload]);

        return $res;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Find an existing person->organization connection matching org, connection type and role.
 *
 * This helper queries the person's connections from Wicket directly (no memoized helper)
 * and returns the first ACTIVE matching connection. If none active is found and
 * $includeEnded is true, it will return an ended matching connection instead.
 *
 * @param string $person_uuid       Person UUID (from)
 * @param string $org_uuid          Organization UUID (to)
 * @param string $connection_type   Connection type (e.g., person_to_organization)
 * @param string $role_slug         Role/type slug (e.g., employee)
 * @param bool   $includeEnded      Whether to allow returning an ended matching connection if no active one exists
 *
 * @return array|null The matching connection resource array, or null if none found
 */
function wicket_find_person_org_connection(
    string $person_uuid,
    string $org_uuid,
    string $connection_type,
    string $role_slug,
    bool $includeEnded = false
): ?array {
    $normalized_connection_type = strtolower(trim($connection_type));
    $normalized_role_slug = strtolower(trim($role_slug));

    if ($person_uuid === '' || $org_uuid === '' || $normalized_connection_type === '' || $normalized_role_slug === '') {
        return null;
    }

    try {
        $client = wicket_api_client();
    } catch (Exception $e) {
        return null;
    }

    try {
        // Query all connections for the person, newest first
        $connections = $client->get('people/' . $person_uuid . '/connections?filter%5Bconnection_type_eq%5D=all&sort=-created_at');
    } catch (Exception $e) {
        return null;
    }

    if (!is_array($connections) || !isset($connections['data']) || !is_array($connections['data'])) {
        return null;
    }

    $ended_match = null;
    foreach ($connections['data'] as $conn) {
        $to_id = $conn['relationships']['to']['data']['id'] ?? '';
        $to_type = $conn['relationships']['to']['data']['type'] ?? '';
        $conn_type = strtolower(trim((string) ($conn['attributes']['connection_type'] ?? '')));
        $role_type = strtolower(trim((string) ($conn['attributes']['type'] ?? '')));

        if (
            $to_id === $org_uuid
            && $to_type === 'organizations'
            && $conn_type === $normalized_connection_type
            && $role_type === $normalized_role_slug
        ) {
            $is_active = (bool) ($conn['attributes']['active'] ?? false);
            $is_ended = !empty($conn['attributes']['ends_at']);
            if ($is_active && !$is_ended) {
                return $conn;
            }
            if ($includeEnded && $ended_match === null) {
                $ended_match = $conn;
            }
        }
    }

    return $ended_match;
}

/**
 * Check if a person already has a matching person->organization connection.
 *
 * @param string $person_uuid
 * @param string $org_uuid
 * @param string $connection_type
 * @param string $role_slug
 * @param bool   $includeEnded Whether to consider ended connections as existing
 *
 * @return bool True if a matching connection exists, false otherwise
 */
function wicket_person_has_org_connection(
    string $person_uuid,
    string $org_uuid,
    string $connection_type,
    string $role_slug,
    bool $includeEnded = false
): bool {
    return wicket_find_person_org_connection($person_uuid, $org_uuid, $connection_type, $role_slug, $includeEnded) !== null;
}
