<?php

declare(strict_types=1);

// No direct access
defined('ABSPATH') || exit;

/**
 * Update connection attributes including start/end dates and other fields
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
  } catch (\Exception $e) {
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
      ]
    ];

    // Update the connection
    $updated_connection = $client->patch('connections/' . $connection_id, ['json' => $payload]);

    return $updated_connection;
  } catch (\Exception $e) {
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
 * Set the description of a connection
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
  } catch (\Exception $e) {
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
  } catch (\Exception $e) {
    return false;
  }
}
