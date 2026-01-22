<?php

declare(strict_types=1);

// No direct access
defined('ABSPATH') || exit;

/**
 * Update a connection's attributes (start/end, description, tags, etc.).
 *
 * @param string $connection_id Connection ID.
 * @param array  $attributes    Keys: starts_at, ends_at, description, tags, custom_data_field. Dates may be ISO 8601 or YYYY-MM-DD.
 *
 * @return mixed Updated connection on success, false on failure.
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
 * Create a connection via API.
 *
 * @param array $payload JSON:API payload for the connection.
 *
 * @return array|false API response on success, false on failure.
 */
function wicket_create_connection($payload)
{
    $client = wicket_api_client();

    try {
        $apiCall = $client->post('connections', ['json' => $payload]);

        return $apiCall;
    } catch (Exception $e) {
        // Log and return safely instead of echo/die
        $msg = '[wicket-base-helper] wicket_create_connection exception: ' . $e->getMessage();
        error_log($msg);
        // Try to log API error details if available
        try {
            $responseBody = $e->getResponse() ? (string) $e->getResponse()->getBody() : '';
            if (!empty($responseBody)) {
                error_log('[wicket-base-helper] wicket_create_connection response body: ' . $responseBody);
            }
        } catch (Throwable $t) {
            // Swallow logging failures
        }

        return false;
    }

    return false;
}

/**
 * Create a person→organization connection.
 *
 * @param string $person_uuid        Person UUID.
 * @param string $org_uuid           Organization UUID.
 * @param string $relationship_type  Relationship type slug.
 * @param bool   $skip_if_exists     When true, return existing matching connection if found.
 * @param array  $atts               Additional attributes for the connection.
 *
 * @return array|false Connection data or false on failure.
 */
function wicket_create_person_to_org_connection($person_uuid, $org_uuid, $relationship_type, $skip_if_exists = false, $atts = [])
{
    $existing_connection = null;
    if ($skip_if_exists) {
        // Get current connections/relationships
        $current_connections = wicket_get_person_connections();
        if (isset($current_connections['data'])) {
            foreach ($current_connections['data'] as $connection) {
                if (
                    $connection['attributes']['type'] == $relationship_type
                    && $connection['relationships']['organization']['data']['id'] == $org_uuid
                ) {
                    $existing_connection = $connection;
                }
            }
        }

        if (!is_null($existing_connection)) {
            // Same relationship was found to already exist, so returning that relationship data instead of making a new one
            return $existing_connection;
        }
    }

    // Defensive: ensure we have valid IDs
    if (empty($person_uuid) || empty($org_uuid)) {
        error_log('[wicket-base-helper] wicket_create_person_to_org_connection missing IDs: person_uuid=' . ($person_uuid ?: 'EMPTY') . ' org_uuid=' . ($org_uuid ?: 'EMPTY') . ' type=' . $relationship_type);

        return false;
    }

    $attributes = [
        'connection_type'   => 'person_to_organization',
        'type'              => $relationship_type,
        'starts_at'         => null,
        'ends_at'           => null,
        'description'       => null,
        'tags'              => [],
    ];

    $attributes = array_merge($attributes, $atts);

    $payload = [
        'data' => [
            'type' => 'connections',
            'attributes' => $attributes,
            'relationships' => [
                // Explicit relationships for API validation
                'organization' => [
                    'data' => [
                        'type' => 'organizations',
                        'id'   => $org_uuid,
                    ],
                ],
                'person' => [
                    'data' => [
                        'type' => 'people',
                        'id'   => $person_uuid,
                    ],
                ],
                'from' => [
                    'data' => [
                        'type' => 'people',
                        'id'   => $person_uuid,
                        'meta' => [
                            'can_manage' => false,
                            'can_update' => false,
                        ],
                    ],
                ],
                'to' => [
                    'data' => [
                        'type' => 'organizations',
                        'id'   => $org_uuid,
                    ],
                ],
            ],
        ],
    ];

    // Brief debug log for diagnostics (IDs only)
    error_log('[wicket-base-helper] Creating person->org connection: person_uuid=' . $person_uuid . ' org_uuid=' . $org_uuid . ' type=' . $relationship_type);

    try {
        $new_connection = wicket_create_connection($payload);
    } catch (Exception $e) {

    }

    $new_connection_id = '';
    if (isset($new_connection['data'])) {
        if (isset($new_connection['data']['id'])) {
            $new_connection_id = $new_connection['data']['id'];
        }
    }

    if (empty($new_connection_id)) {
        return false;
    }

    return [
        'connection_id'     => $new_connection['data']['id'] ?? '',
        'connection_type'   => $relationship_type,
        'starts_at'         => $new_connection['data']['attributes']['starts_at'] ?? '',
        'ends_at'           => $new_connection['data']['attributes']['ends_at'] ?? '',
        'tags'              => $new_connection['data']['attributes']['tags'] ?? '',
        'active_connection' => $new_connection['data']['attributes']['active'],
        'org_id'            => $org_uuid,
        'person_id'         => $person_uuid,
    ];
}

/**
 * Create an organization→organization connection.
 *
 * @param string $from_org_uuid      Source organization UUID.
 * @param string $to_org_uuid        Target organization UUID.
 * @param string $relationship_type  Relationship type slug.
 * @param bool   $skip_if_exists     When true, return existing matching connection if found.
 * @param array  $atts               Additional attributes for the connection.
 *
 * @return array|false Connection data or false on failure.
 */
function wicket_create_org_to_org_connection($from_org_uuid, $to_org_uuid, $relationship_type, $skip_if_exists = false, $atts = [])
{
    $existing_connection = null;
    if ($skip_if_exists) {
        // Get current connections/relationships
        $current_connections = wicket_get_org_connections_by_id($from_org_uuid);
        if (isset($current_connections['data'])) {
            foreach ($current_connections['data'] as $connection) {
                if (
                    $connection['attributes']['type'] == $relationship_type
                    && $connection['relationships']['to']['data']['id'] == $to_org_uuid
                ) {
                    $existing_connection = $connection;
                }
            }
        }

        if (!is_null($existing_connection)) {
            // Same relationship was found to already exist, so returning that relationship data instead of making a new one
            return $existing_connection;
        }
    }

    $attributes = [
        'connection_type'   => 'organization_to_organization',
        'type'              => $relationship_type,
        'starts_at'         => null,
        'ends_at'           => null,
        'description'       => null,
        'tags'              => [],
    ];

    $attributes = array_merge($attributes, $atts);

    $payload = [
        'data' => [
            'type' => 'connections',
            'attributes' => $attributes,
            'relationships' => [
                'from' => [
                    'data' => [
                        'type' => 'organizations',
                        'id'   => $from_org_uuid,
                    ],
                ],
                'to' => [
                    'data' => [
                        'type' => 'organizations',
                        'id'   => $to_org_uuid,
                    ],
                ],
            ],
        ],
    ];

    try {
        $new_connection = wicket_create_connection($payload);
    } catch (Exception $e) {

    }

    $new_connection_id = '';
    if (isset($new_connection['data'])) {
        if (isset($new_connection['data']['id'])) {
            $new_connection_id = $new_connection['data']['id'];
        }
    }

    if (empty($new_connection_id)) {
        return false;
    }

    return [
        'connection_id'     => $new_connection['data']['id'] ?? '',
        'connection_type'   => $relationship_type,
        'starts_at'         => $new_connection['data']['attributes']['starts_at'] ?? '',
        'ends_at'           => $new_connection['data']['attributes']['ends_at'] ?? '',
        'tags'              => $new_connection['data']['attributes']['tags'] ?? '',
        'active_connection' => $new_connection['data']['attributes']['active'],
        'from_org_id'       => $from_org_uuid,
        'to_org_id'         => $to_org_uuid,
    ];
}

/**
 * Remove a connection by ID.
 *
 * @param string $connection_id Connection ID.
 *
 * @return bool True on success, false on failure.
 */
function wicket_remove_connection($connection_id)
{
    try {
        $client = wicket_api_client();
    } catch (Exception $e) {
        error_log($e->getMessage());

        return false;
    }

    try {
        $removed_connection = $client->delete('connections/' . $connection_id);
    } catch (Exception $e) {
        error_log($e->getMessage());

        return false;
    }

    return true;
}

/**
 * Fetch a connection by ID.
 *
 * @param string $connection_id Connection ID.
 *
 * @return array|false Connection data on success, false on failure.
 */
function wicket_get_connection_by_id($connection_id)
{
    try {
        $client = wicket_api_client();
    } catch (Exception $e) {
        error_log($e->getMessage());

        return false;
    }

    try {
        $connection = $client->get('connections/' . $connection_id);

        return $connection;
    } catch (Exception $e) {
        error_log($e->getMessage());

        return false;
    }
}

/**
 * End a connection by setting its end timestamp using the site timezone.
 *
 * @param string                  $connection_id Connection ID.
 * @param \DateTimeInterface|null $end_time      Optional explicit end time; defaults to now in site TZ.
 *
 * @return mixed Updated connection on success, false on failure.
 */
function wicket_end_connection(string $connection_id, ?\DateTimeInterface $end_time = null): mixed
{
    if ($connection_id === '') {
        return false;
    }

    $timestamp = $end_time ?? new \DateTime('now', wp_timezone());
    $formatted_end = $timestamp->format('Y-m-d\TH:i:sP');

    return wicket_update_connection_attributes($connection_id, ['ends_at' => $formatted_end]);
}

/**
 * Set the description of a connection.
 *
 * @param string $connection_id Connection ID.
 * @param string $description   Description text.
 *
 * @return mixed Updated connection on success, false on failure.
 */
function wicket_set_connection_description(string $connection_id, string $description = ''): mixed
{
    return wicket_update_connection_attributes($connection_id, ['description' => $description]);
}

/**
 * Patch only the description of a connection (minimal payload).
 *
 * @param string      $connection_id Connection ID.
 * @param string|null $description   Description to set (null clears it).
 *
 * @return mixed Updated connection on success, false on failure.
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
 * Find a person→organization connection matching org, connection type, and role.
 * Returns active match; if none and $includeEnded, returns an ended match.
 *
 * @param string $person_uuid     Person UUID (from).
 * @param string $org_uuid        Organization UUID (to).
 * @param string $connection_type Connection type slug.
 * @param string $role_slug       Role/type slug.
 * @param bool   $includeEnded    If true, allow returning ended match when no active found.
 *
 * @return array|null Matching connection or null.
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
 * Check if a person already has a matching person→organization connection.
 *
 * @param string $person_uuid     Person UUID.
 * @param string $org_uuid        Organization UUID.
 * @param string $connection_type Connection type slug.
 * @param string $role_slug       Role/type slug.
 * @param bool   $includeEnded    Consider ended connections as existing.
 *
 * @return bool True if a matching connection exists, otherwise false.
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

/**
 * Set start/end dates on a connection (deprecated; use wicket_end_connection or wicket_update_connection_attributes).
 *
 * @param string $connection_id Connection ID.
 * @param string $end_date      End date/time (YYYY-MM-DD).
 * @param string $start_date    Optional start date/time (YYYY-MM-DD).
 *
 * @deprecated 2026-01-22 Use wicket_end_connection() or wicket_update_connection_attributes() with timezone-aware timestamps.
 * @return mixed Updated connection on success, false on failure.
 */
function wicket_set_connection_start_end_dates($connection_id, $end_date = '', $start_date = '')
{

    // Mark deprecated and emit a WordPress deprecation notice
    if (function_exists('_deprecated_function')) {
        _deprecated_function(__FUNCTION__, '2026-01-22', 'wicket_end_connection()');
    }

    if (empty($end_date)) {
        return false;
    }

    try {
        $client = wicket_api_client();
    } catch (Exception $e) {
        error_log($e->getMessage());

        return false;
    }

    try {
        $current_connection_info = wicket_get_connection_by_id($connection_id);

        if (empty($current_connection_info)) {
            return false;
        }

        $attributes = $current_connection_info['data']['attributes'];

        $tz = wp_timezone();
        $now = new DateTime('now', $tz);

        // Only if we received a start date, set it (timezone-aware, preserve given date with current time if time missing)
        if (!empty($start_date)) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) === 1) {
                $dt_start = DateTime::createFromFormat('Y-m-d H:i:s', $start_date . ' ' . $now->format('H:i:s'), $tz);
            } else {
                $dt_start = new DateTime($start_date, $tz);
            }
            $attributes['starts_at'] = $dt_start ? $dt_start->format('Y-m-d\TH:i:sP') : null;
        }

        // End date always timezone-aware; if date-only, use current time of day in site TZ
        if (!empty($end_date)) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date) === 1) {
                $dt_end = DateTime::createFromFormat('Y-m-d H:i:s', $end_date . ' ' . $now->format('H:i:s'), $tz);
            } else {
                $dt_end = new DateTime($end_date, $tz);
            }
            $attributes['ends_at'] = $dt_end ? $dt_end->format('Y-m-d\TH:i:sP') : null;
        } else {
            $attributes['ends_at'] = null;
        }

        // Ensure empty fields stay null, which the MDP likes
        $attributes['description'] = !empty($attributes['description']) ? $attributes['description'] : null;
        $attributes['custom_data_field'] = !empty($attributes['custom_data_field']) ? $attributes['custom_data_field'] : null;
        $attributes['tags'] = !empty($attributes['tags']) ? $attributes['tags'] : null;

        $payload = [
            'data' => [
                'attributes'    => $attributes,
                'id'            => $connection_id,
                'relationships' => [
                    'from' => $current_connection_info['data']['relationships']['from'],
                    'to'   => $current_connection_info['data']['relationships']['to'],
                ],
                'type'          => $current_connection_info['data']['type'],
            ],
        ];

        $updated_connection = $client->patch('connections/' . $connection_id, ['json' => $payload]);

        return $updated_connection;
    } catch (Exception $e) {
        return false;
    }

    return false;
}
