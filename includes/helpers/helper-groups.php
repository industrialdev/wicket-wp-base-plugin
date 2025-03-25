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
 * Get a person's groups
 *
 * @param string $person_uuid The person uuid (optional, uses current person if not provided)
 *
 * @return array|false
 */
function wicket_get_person_groups($person_uuid = null)
{
  $client = wicket_api_client();

  if (is_null($person_uuid)) {
    $person_uuid = wicket_current_person_uuid();
  }

  $groups = $client->get("group_members/?page%5Bnumber%5D=1&page%5Bsize%5D=9999&filter%5Bperson_uuid_eq%5D=$person_uuid&include=group");

  if ($groups) {
    return $groups;
  }

  return false;
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
