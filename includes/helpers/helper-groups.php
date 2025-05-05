<?php

// No direct access
defined('ABSPATH') || exit;

/**
 * Get all groups
 *
 * @return array|false
 */
function wicket_get_groups()
{
  $client = wicket_api_client();

  $groups = $client->get('groups');

  if ($groups) {
    return $groups;
  }

  return false;
}

/**
 * Get all groups that a person UUID is part of
 *
 * @param string $person_uuid (Optional) The person UUID to search for. If missing, uses current person.
 * @param array $args (Optional) Array of arguments to pass to the API
 *              org_id (Optional) The organization UUID to search for. If missing, search in all groups.
 *              search_query (Optional) The search query to find groups by their names, case insensitive.
 *              per_page (Optional) The number of groups to return per page (size). Default: 50.
 *              page (Optional) The page number to return. Default: 1.
 *
 * @return array|false Array of groups on ['data'] or false on failure
 */
function wicket_get_person_groups($person_uuid = null, $args = [])
{
  // Default args
  $defaults = [
    'org_id'       => null,
    'search_query' => null,
    'per_page'     => 50,
    'page'         => 1,
  ];
  $args = wp_parse_args($args, $defaults);

  if (is_null($person_uuid)) {
    $person_uuid = wicket_current_person_uuid();
  }

  if (empty($person_uuid)) {
    return false;
  }

  $client = wicket_api_client();

  try {
    // Payload
    $query_params = [
      'page' => [
        'number' => $args['page'],
        'size'   => $args['per_page']
      ],
      'filter' => [
        'person_uuid_eq' => $person_uuid
      ],
      'include' => 'group'
    ];

    // Arg: org_id
    if (!empty($args['org_id'])) {
      $query_params['filter']['group_organization_uuid_eq'] = $args['org_id'];
    }

    // Arg: search_query
    if (!empty($args['search_query'])) {
      $query_params['filter']['group_name_en_i_cont'] = $args['search_query'];
    }

    // Query the MDP
    $response = $client->get('/group_members', [
      'query' => $query_params
    ]);

    if (!isset($response['data']) || empty($response['data'])) {
      return false;
    }

    return $response;
  } catch (Exception $e) {
    return false;
  }
}

/**
 * Add a member to a group with the specified role
 *
 * @param int|string $person_id ID of the person to add
 * @param int|string $group_uuid ID of the group to add the member to
 * @param string $group_role_slug The type of group role to assign to the person
 * @param array $args {
 *     Optional. Arguments to pass to the function.
 *     @type string $start_date     [optional] The date to start the group membership. Default null.
 *     @type string $end_date       [optional] The date to end the group membership. Default null.
 *     @type bool   $skip_if_exists [optional] Whether to skip adding if the user is already a member with the same role. Default true.
 * }
 *
 * @return object|array|WP_Error The response object from the Wicket API, the existing group membership array if skipped, or WP_Error on failure.
 */
function wicket_add_group_member($person_id, $group_uuid, $group_role_slug, $args = [])
{
  // Default args
  $defaults = [
    'start_date'     => null,
    'end_date'       => null,
    'skip_if_exists' => true,
  ];
  $args = wp_parse_args($args, $defaults);

  // Extract args
  $start_date     = $args['start_date'];
  $end_date       = $args['end_date'];
  $skip_if_exists = $args['skip_if_exists'];

  if ($skip_if_exists) {
    // Check if the user is already a member of that group with the same role
    $current_user_groups = wicket_get_person_groups($person_id);
    if (isset($current_user_groups['data'])) {
      foreach ($current_user_groups['data'] as $group) {
        if (
          $group['relationships']['group']['data']['id'] == $group_uuid
          && $group['attributes']['type'] == $group_role_slug
        ) {
          // Matching group found - returning that group connection instead of adding them to the group again
          return $group;
        }
      }
    }
  }

  $client = wicket_api_client();

  $payload = [
    'data' => [
      'attributes'   => [
        'custom_data_field' => null,
        'end_date'          => $end_date,
        'person_id'         => $person_id, // Redundant in payload? Check API docs. Keeping for now.
        'start_date'        => $start_date,
        'type'              => $group_role_slug,
      ],
      'id'            => null,
      'relationships' => [
        'group' => [
          'data' => [
            'id'   => $group_uuid,
            'type' => 'groups',
          ],
        ],
      ],
      'type'          => 'group_members',
    ]
  ];

  try {
    $response = $client->post('group_members', ['json' => $payload]);
  } catch (\Exception $e) {
    $wicket_api_error = json_decode($e->getResponse()->getBody())->errors;
    $response = new \WP_Error('wicket_api_error', $wicket_api_error);
  }

  return $response;
}

/**
 * Get specific group by UUID
 *
 * @return array|false
 */
function wicket_get_group($uuid)
{
  if (!$uuid) {
    return false;
  }

  $client = wicket_api_client();

  $group = $client->get("groups/{$uuid}");

  if ($group) {
    return $group;
  }

  return false;
}

/**
 * Get all members of a group
 *
 * @param string $group_uuid The UUID of the group to get members from
 * @param array $args (Optional) Array of arguments to pass to the API
 *              per_page (Optional) The number of members to return per page (size). Default: 50.
 *              page (Optional) The page number to return. Default: 1.
 *              active (Optional) Boolean to filter by active status. Default: true.
 *              role (Optional) String to filter by group role slug (e.g., 'member', 'administrator') or comma-separated string for multiple roles (e.g., 'member,observer').
 *
 * @return array|WP_Error Array of group members on success, WP_Error on failure
 */
function wicket_get_group_members($group_uuid, $args = [])
{
  if (empty($group_uuid)) {
    return new \WP_Error('missing_param', 'Group UUID is required.');
  }

  // Default args
  $defaults = [
    'per_page' => 50,
    'page'     => 1,
    'active'   => true,
    'role'     => null, // Default to no specific role filter
  ];
  $args = wp_parse_args($args, $defaults);

  $client = wicket_api_client();
  $endpoint = "/groups/{$group_uuid}/people";
  $role_query_string = ''; // Initialize role query string part

  // Base Payload
  $query_params = [
    'page' => [
      'number' => $args['page'],
      'size' => $args['per_page']
    ],
    'filter' => [
      'active_eq' => $args['active'],
    ],
    'include' => 'person'
  ];

  // Handle role filtering
  if (!empty($args['role'])) { // Use parsed args
    $trimmed_role = trim($args['role']);
    if (str_contains($trimmed_role, ',')) {
      // Multiple roles: Manually build query string part
      $roles_array = explode(',', $trimmed_role);
      $roles_array = array_map('trim', $roles_array);
      $role_query_parts = [];
      foreach ($roles_array as $role) {
        $role_query_parts[] = 'filter[resource_type_slug_in][]=' . urlencode($role);
      }
      $role_query_string = implode('&', $role_query_parts);
    } else {
      // Single role: Let Guzzle handle it
      $query_params['filter']['resource_type_slug_eq'] = $trimmed_role;
    }
  }

  // Append manual role query string if needed
  if (!empty($role_query_string)) {
    $endpoint .= '?' . $role_query_string;
  }

  // Query the MDP using the potentially modified endpoint
  try {
    $response = $client->get($endpoint, [
      'query' => $query_params // Pass the remaining params for Guzzle to handle
    ]);
  } catch (Exception $e) {
    // Log the error for debugging
    // error_log('Wicket API Error in wicket_get_group_members: ' . $e->getMessage());
    $api_error_message = 'Unknown API error.';
    if ($e->hasResponse()) {
      try {
        $error_body = json_decode((string) $e->getResponse()->getBody(), true);
        // Attempt to extract a meaningful message
        if (isset($error_body['errors'][0]['detail'])) {
          $api_error_message = $error_body['errors'][0]['detail'];
        } elseif (isset($error_body['errors'][0]['title'])) {
          $api_error_message = $error_body['errors'][0]['title'];
        }
      } catch (\JsonException $jsonEx) {
        $api_error_message = 'Could not decode API error response.';
      }
    }
    return new \WP_Error('wicket_api_error', $api_error_message, ['status' => $e->getCode(), 'exception' => $e]);
  }

  return $response; // Return the full response
}

/**
 * Search for group members
 *
 * @param string $group_uuid The UUID of the group to search in
 * @param string $search_query The search query to use: person's first name, last name and/or email
 * @param array $args (Optional) Array of arguments to pass to the API
 *             per_page The number of members to return per page (size). Default: 20.
 *            page The page number to return. Default: 1.
 *            active Boolean to filter by active status. Default: true.
 *            role (Optional) String to filter by group role slug (e.g., 'member', 'administrator') or comma-separated string for multiple roles (e.g., 'member,observer').
 *
 * @return array|WP_Error The response array from the Wicket API or WP_Error on failure
 */
function wicket_search_group_members($group_uuid, $search_query, $args = [])
{
  if (empty($group_uuid)) {
    return new \WP_Error('missing_param', 'Group UUID is required.');
  }
  if (empty($search_query)) {
    return new \WP_Error('missing_param', 'Search query is required.');
  }

  // Default args
  $defaults = [
    'per_page' => 20,
    'page'     => 1,
    'active'   => true,
    'role'     => null,
  ];
  $args = wp_parse_args($args, $defaults);

  $client = wicket_api_client();
  $endpoint = "/groups/{$group_uuid}/people";
  $role_query_string = ''; // Initialize role query string part

  // Base Payload
  $query_params = [
    'page' => [
      'number' => $args['page'],
      'size' => $args['per_page']
    ],
    'filter' => [
      'active_eq' => $args['active'],
      'person_search_query' => [
        'keywords' => [
          'term' => $search_query,
          'fields' => 'full_name,given_name,family_name,primary_email'
        ]
      ]
    ],
    'include' => 'person'
  ];

  // Handle role filtering
  if (!empty($args['role'])) { // Use parsed args
    $trimmed_role = trim($args['role']);
    if (str_contains($trimmed_role, ',')) {
      // Multiple roles: Manually build query string part
      $roles_array = explode(',', $trimmed_role);
      $roles_array = array_map('trim', $roles_array);
      $role_query_parts = [];
      foreach ($roles_array as $role) {
        $role_query_parts[] = 'filter[resource_type_slug_in][]=' . urlencode($role);
      }
      $role_query_string = implode('&', $role_query_parts);
    } else {
      // Single role: Let Guzzle handle it
      $query_params['filter']['resource_type_slug_eq'] = $trimmed_role;
    }
  }

  // Append manual role query string if needed
  if (!empty($role_query_string)) {
    $endpoint .= '?' . $role_query_string;
  }

  // Query the MDP
  try {
    $response = $client->get($endpoint, [
      'query' => $query_params // Pass the remaining params for Guzzle to handle
    ]);
  } catch (Exception $e) {
    // Log the error for debugging
    // error_log('Wicket API Error in wicket_search_group_members: ' . $e->getMessage());
    $api_error_message = 'Unknown API error during search.';
    if ($e->hasResponse()) {
      try {
        $error_body = json_decode((string) $e->getResponse()->getBody(), true);
        // Attempt to extract a meaningful message
        if (isset($error_body['errors'][0]['detail'])) {
          $api_error_message = $error_body['errors'][0]['detail'];
        } elseif (isset($error_body['errors'][0]['title'])) {
          $api_error_message = $error_body['errors'][0]['title'];
        }
      } catch (\JsonException $jsonEx) {
        $api_error_message = 'Could not decode API error response during search.';
      }
    }
    return new \WP_Error('wicket_api_error', $api_error_message, ['status' => $e->getCode(), 'exception' => $e]);
  }

  return $response; // Return the full response
}

/**
 * Get formatted group data for display
 *
 * @param array $groups The groups data from the API
 * @return array|false Array of formatted group data or false if empty
 */
function wicket_get_person_groups_selector_data($groups = [])
{
  if (empty($groups)) {
    return false;
  }

  $formatted_groups = [];
  $lang = defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : 'en';

  foreach ($groups['data'] as $group_member) {
    // Find the group in included data
    $group = null;
    foreach ($groups['included'] as $included) {
      if ($included['type'] === 'groups' && $included['id'] === $group_member['relationships']['group']['data']['id']) {
        $group = $included;
        break;
      }
    }

    if (!$group) {
      continue;
    }

    $formatted_groups[] = [
      'id' => $group['id'],
      'name' => $group['attributes']["name_{$lang}"] ?? $group['attributes']['name'],
      'type' => ucwords(str_replace('_', ' ', $group['attributes']['type'])),
      'description' => $group['attributes']["description_{$lang}"] ?? $group['attributes']['description'],
      'is_active' => $group_member['attributes']['active'],
      'member_role' => ucwords(str_replace('_', ' ', $group_member['attributes']['type'])),
      'is_admin' => $group_member['attributes']['type'] === 'administrator',
      'start_date' => $group_member['attributes']['start_date'],
      'end_date' => $group_member['attributes']['end_date'],
      'slug' => $group['attributes']['slug']
    ];
  }

  return $formatted_groups;
}
