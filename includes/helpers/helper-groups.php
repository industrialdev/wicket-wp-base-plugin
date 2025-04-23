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
        'number' => 1,
        'size' => 50 // We shouldn't be querying 9999 or more groups here or anywhere. Remember: paginate or live search, with limits.
      ],
      'filter' => [
        'person_uuid_eq' => $person_uuid
      ],
      'include' => 'group'
    ];

    // Arg: org_id
    if (isset($args['org_id']) && !empty($args['org_id'])) {
      $query_params['filter']['group_organization_uuid_eq'] = $args['org_id'];
    }

    // Arg: search_query
    if (isset($args['search_query']) && !empty($args['search_query'])) {
      $query_params['filter']['group_name_en_i_cont'] = $args['search_query'];
    }

    // Arg: per_page
    if (isset($args['per_page']) && !empty($args['per_page'])) {
      $query_params['page']['size'] = $args['per_page'];
    }

    // Arg: page
    if (isset($args['page']) && !empty($args['page'])) {
      $query_params['page']['number'] = $args['page'];
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
 * @param int|string $group_id ID of the group to add the member to
 * @param string $group_role_slug The type of group role to assign to the person
 * @param string $start_date [optional] The date to start the group membership
 * @param string $end_date [optional] The date to end the group membership
 *
 * @return object The response object from the Wicket API
 */
function wicket_add_group_member($person_id, $group_id, $group_role_slug, $start_date = null, $end_date = null, $skip_if_exists = false)
{
  if ($skip_if_exists) {
    // Check if the user is already a member of that group with the same role
    $current_user_groups = wicket_get_person_groups($person_id);
    if (isset($current_user_groups['data'])) {
        foreach ($current_user_groups['data'] as $group) {
            if (
                $group['relationships']['group']['data']['id'] == $group_id
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
        'person_id'         => $person_id,
        'start_date'        => $start_date,
        'type'              => $group_role_slug,
      ],
      'id'            => null,
      'relationships' => [
        'group' => [
          'data' => [
            'id'   => $group_id,
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
 * Search for group members
 *
 * @param string $group_uuid The UUID of the group to search in
 * @param string $search_query The search query to use: person's first name, last name and/or email
 *
 * @return object The response object from the Wicket API
 */
function wicket_search_group_members($group_uuid, $search_query)
{
  $client = wicket_api_client();

  $response = $client->get("groups/$group_uuid/members?search=$search_query");

  return $response;
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

    if (!$group) continue;

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
