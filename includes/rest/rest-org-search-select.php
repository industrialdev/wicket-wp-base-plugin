<?php

// No direct access
defined('ABSPATH') || exit;

/**
 * Register REST API routes for Wicket Base plugin.
 *
 * Registers multiple REST endpoints for organization management, search functionality,
 * relationship management, and user access control.
 *
 * @since 1.0.0
 * @return void
 */
function wicket_base_register_rest_routes()
{
    register_rest_route('wicket-base/v1', 'search-orgs', array(
      'methods'  => 'POST',
      'callback' => 'wicket_internal_endpoint_search_orgs',
      'permission_callback' => function () {
          return is_user_logged_in();
      },
    ));

    register_rest_route('wicket-base/v1', 'search-groups', array(
      'methods'  => 'POST',
      'callback' => 'wicket_internal_endpoint_search_groups',
      'permission_callback' => function () {
          return is_user_logged_in();
      },
    ));

    register_rest_route('wicket-base/v1', 'terminate-relationship', array(
      'methods'  => 'POST',
      'callback' => 'wicket_internal_endpoint_terminate_relationship',
      'permission_callback' => function () {
          return is_user_logged_in();
      },
    ));

    register_rest_route('wicket-base/v1', 'create-relationship', array(
      'methods'  => 'POST',
      'callback' => 'wicket_internal_endpoint_create_relationship',
      'permission_callback' => function () {
          return is_user_logged_in();
      },
    ));

    register_rest_route('wicket-base/v1', 'organization-parent', [
      'methods'  => 'POST',
      'callback' => 'wicket_internal_endpoint_organization_parent',
      'permission_callback' => function () {
          return is_user_logged_in();
      }
    ]);

    register_rest_route('wicket-base/v1', 'create-org', array(
      'methods'  => 'POST',
      'callback' => 'wicket_internal_endpoint_create_org',
      'permission_callback' => function () {
          return is_user_logged_in();
      },
    ));

    register_rest_route('wicket-base/v1', 'flag-for-rm-access', array(
      'methods'  => 'POST',
      'callback' => 'wicket_internal_endpoint_flag_for_rm_access',
      'permission_callback' => function () {
          return is_user_logged_in();
      },
    ));

    register_rest_route('wicket-base/v1', 'flag-for-org-editor-access', array(
      'methods'  => 'POST',
      'callback' => 'wicket_internal_endpoint_flag_for_org_editor_access',
      'permission_callback' => function () {
          return is_user_logged_in();
      },
    ));

    register_rest_route('wicket-base/v1', 'grant-org-editor', array(
      'methods'  => 'POST',
      'callback' => 'wicket_internal_endpoint_grant_org_editor',
      'permission_callback' => function () {
          return is_user_logged_in();
      },
    ));

    register_rest_route('wicket-base/v1', 'wicket-component-do-action', array(
      'methods'  => 'POST',
      'callback' => 'wicket_internal_endpoint_component_do_action',
      'permission_callback' => function () {
          return is_user_logged_in();
      },
    ));

    register_rest_route('wicket-base/v1', 'wicket-component-do-action', array(
      'methods'  => 'POST',
      'callback' => 'wicket_internal_endpoint_component_do_action',
      'permission_callback' => function () {
          return is_user_logged_in();
      },
    ));
}
add_action('rest_api_init', 'wicket_base_register_rest_routes', 8, 1);

/**
 * Calls the Wicket helper functions to search for a given organization name
 * and provide a list of results.
 *
 * @param WP_REST_Request $request that contains JSON params, notably a 'searchTerm' and an optional 'lang'.
 *
 * @return JSON success:false or success:true, along with any related information or notices.
 */
function wicket_internal_endpoint_search_orgs($request)
{
    $params = $request->get_json_params();

    if (!isset($params['searchTerm'])) {
        wp_send_json_error('Search term not provided');
    }

    $lang = $params['lang'] ?? 'en';

    if (isset($params['autocomplete'])) {
        if ($params['autocomplete']) {
            // Use autocomplete API instead
            $return = wicket_search_organizations($params['searchTerm'], 'org_name', $params['orgType'], true, $lang);
            if (gettype($return) == 'boolean' && !$return) {
                wp_send_json_error('There was a problem searching orgs.');
            } else {
                wp_send_json_success($return);
            }
        }
    } else {
        $return = wicket_search_organizations($params['searchTerm'], 'org_name', $params['orgType'], false, $lang);
        if (gettype($return) == 'boolean' && !$return) {
            wp_send_json_error('There was a problem searching orgs.');
        } else {
            wp_send_json_success($return);
        }
    }
}

/**
 * Calls the Wicket helper functions to search for a given group name
 * and provide a list of results.
 *
 * @param WP_REST_Request $request that contains JSON params, notably a 'searchTerm' and a 'lang'.
 *
 * @return JSON success:false or success:true, along with any related information or notices.
 */
function wicket_internal_endpoint_search_groups($request)
{
    $params = $request->get_json_params();

    if (!isset($params['searchTerm'])) {
        wp_send_json_error('Search term not provided');
    }
    if (!isset($params['lang'])) {
        wp_send_json_error('Language code not provided');
    }

    try {
        $client = wicket_api_client();
    } catch (\Exception $e) {
        wp_send_json_error($e->getMessage());
    }

    $search_term = $params['searchTerm'];
    $lang        = $params['lang'];

    $args = [
      'sort' => 'name',
      'page_size' => 100,
      'page_number' => 1,
    ];

    $args['filter']["name_" . $lang . "_cont"] = $search_term;

    // replace query string page[0] and page[1] etc. with page[] since ruby doesn't like it
    $args = preg_replace('/\%5B\d+\%5D/', '%5B%5D', http_build_query($args));

    try {
        $search_groups = $client->get('groups?' . $args);
    } catch (\Exception $e) {
        wp_send_json_error($e->getMessage());
    }

    $results = [];

    if ($search_groups['meta']['page']['total_items'] > 0) {
        foreach ($search_groups['data'] as $result) {
            $results[$result['id']]['id'] = $result['id'];
            if (isset($result['attributes']["name_$lang"])) {
                $results[$result['id']]['name'] = $result['attributes']["name_$lang"];
            } else {
                $results[$result['id']]['name'] = $result['attributes']["name"];
            }
        }
    }

    wp_send_json_success($results);
}

/**
 * Calls the Wicket helper functions to terminate a relationship.
 *
 * @param WP_REST_Request $request that contains JSON params, notably a 'connectionId'.
 * Optionally 'removeRelationship' can be provided, which will delete the relationship altogether
 * instead of set the end date to today's date.
 *
 * @return JSON success:false or success:true, along with any related information or notices.
 */
function wicket_internal_endpoint_terminate_relationship($request)
{
    $params = $request->get_json_params();

    if (!isset($params['connectionId'])) {
        wp_send_json_error('Connection ID not provided');
    }

    $connectionId = $params['connectionId'];
    $removeRelationship = $params['removeRelationship'] ?? false;
    $limitToRelationshipType = $params['relationshipType'] ?? false;

    if ($limitToRelationshipType) {
        $current_connection_info = wicket_get_connection_by_id($connectionId);

        if (empty($current_connection_info)) {
            wp_send_json_error('Connection not found. During relationship type limiting.');
        }

        $attributes = $current_connection_info['data']['attributes'];
        $relationshipType = $attributes['type'];

        if ($relationshipType !== $limitToRelationshipType) {
            wp_send_json_error('Relationships do not match; cannot remove.');
        }
    }

    if ($removeRelationship) {
        if (wicket_remove_connection($connectionId)) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Something went wrong removing the connection.');
        }
    } else {
        // Set the relationship end date to today's date
        $set_end_date = wicket_set_connection_start_end_dates($connectionId, date('Y-m-d'));
        if ($set_end_date) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Something wrong setting the end date of the connection.');
        }
    }
}

/**
 * Calls the Wicket helper functions to create a relationship.
 *
 * @param WP_REST_Request $request that contains JSON params, notably the following:
 *  - fromUuid
 *  - toUuid
 *  - relationshipType
 *  - userRoleInRelationship (can be single role, or comma-separated list)
 *
 * @return JSON success:false or success:true, along with any related information or notices.
 */
function wicket_internal_endpoint_create_relationship($request)
{
    $params = $request->get_json_params();

    if (!isset($params['fromUuid'])) {
        wp_send_json_error('fromUuid not provided');
    }
    if (!isset($params['toUuid'])) {
        wp_send_json_error('toUuid not provided');
    }
    if (!isset($params['relationshipType'])) {
        wp_send_json_error('relationshipType not provided');
    }
    if (!isset($params['userRoleInRelationship'])) {
        wp_send_json_error('userRoleInRelationship not provided');
    }

    $fromUuid                    = $params['fromUuid'];
    $toUuid                      = $params['toUuid'];
    $relationshipType            = $params['relationshipType'];
    $userRoleInRelationship      = $params['userRoleInRelationship'];
    $description                 = isset($params['description']) ? $params['description'] : null;
    $userRoleInRelationshipArray = explode(',', $userRoleInRelationship);

    $return = [];

    foreach ($userRoleInRelationshipArray as $userRoleInRelationship) {
        $payload = [
          'data' => [
            'type' => 'connections',
            'attributes' => [
              'connection_type'   => $relationshipType,
              'type'              => trim($userRoleInRelationship),
              'starts_at'         => date('Y-m-d'),
              'ends_at'           => null,
              'description'       => $description,
              'tags'              => [],
            ],
            'relationships' => [
              'from' => [
                'data' => [
                  'type' => 'people',
                  'id'   => $fromUuid,
                  'meta' => [
                    'can_manage' => false,
                    'can_update' => false,
                  ],
                ],
              ],
              'to' => [
                'data' => [
                  'type' => 'organizations',
                  'id'   => $toUuid,
                ],
              ],
            ],
          ]
        ];

        try {
            $new_connection = wicket_create_connection($payload);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }

        $new_connection_id = '';
        if (isset($new_connection['data'])) {
            if (isset($new_connection['data']['id'])) {
                $new_connection_id = $new_connection['data']['id'];
            }
        }

        // Grab information about the new org connection to send back
        $org_info = wicket_get_organization_basic_info($toUuid);

        $org_memberships = wicket_get_org_memberships($toUuid);
        $has_active_membership = false;
        if (!empty($org_memberships)) {
            foreach ($org_memberships as $membership) {
                if (isset($membership['membership'])) {
                    if (isset($membership['membership']['attributes'])) {
                        if (isset($membership['membership']['attributes']['active'])) {
                            if ($membership['membership']['attributes']['active']) {
                                $has_active_membership = true;
                            }
                        }
                    }
                }
            }
        }

        $return[] =  [
          'connection_id'     => $new_connection['data']['id'] ?? '',
          'connection_type'   => $relationshipType,
          'starts_at'         => $new_connection['data']['attributes']['starts_at'] ?? '',
          'ends_at'           => $new_connection['data']['attributes']['ends_at'] ?? '',
          'tags'              => $new_connection['data']['attributes']['tags'] ?? '',
          'active_membership' => $has_active_membership,
          'active_connection' => $new_connection['data']['attributes']['active'],
          'org_id'            => $toUuid,
          'org_name'          => $org_info['org_name'],
          'org_description'   => $org_info['org_description'],
          'org_type'          => $org_info['org_type_pretty'],
          'org_status'        => $org_info['org_status'],
          'org_parent_id'     => $org_info['org_parent_id'],
          'org_parent_name'   => $org_info['org_parent_name'],
          'person_id'         => $fromUuid,
        ];
    }

    wp_send_json_success($return);
}

/**
 * Calls the Wicket helper functions to create an organization.
 *
 * @param WP_REST_Request $request that contains JSON params, notably the following:
 *  - orgName
 *  - orgType
 *
 * @return JSON success:false or success:true, along with any related information or notices.
 */
function wicket_internal_endpoint_create_org($request)
{
    $params = $request->get_json_params();

    if (!isset($params['orgName'])) {
        wp_send_json_error('Organization name not provided');
    }
    if (!isset($params['orgType'])) {
        wp_send_json_error('Organization type not provided');
    }

    $org_name = $params['orgName'];
    $org_type = $params['orgType'];
    $no_duplicate = $params['noDuplicate'] ?? false;

    if ($no_duplicate) {
        // Check if the org of that name and type already exists
        $org_name_lowercase = trim(strtolower($org_name));

        // Search by org name, filtering to the org type, and use the autocomplete API for better speed
        $search = wicket_search_organizations($org_name_lowercase, 'org_name', $org_type, true);

        $found = false;
        foreach ($search as $result) {
            if (!isset($result['name']) || !isset($result['type'])) {
                break;
            }

            $result_name = trim(strtolower($result['name']));
            $result_type = trim(strtolower($result['type']));

            if ($result_name === $org_name_lowercase && $result_type == $org_type) {
                $found = true;
                break;
            }
        }

        if ($found) {
            wp_send_json_error('Duplicate org is not allowed.');
        }
    } // End no_duplicate feature

    $create_org_call = wicket_create_organization($org_name, $org_type);

    if (isset($create_org_call) && !empty($create_org_call)) {
        wp_send_json_success($create_org_call);
    } else {
        wp_send_json_error('Something went wrong creating the organization');
    }
}

/**
 * Sets a temporary piece of user meta so that the user will get Roster Mangement access for the given org UUID on the next order_complete containing a membership product.
 *
 * @param WP_REST_Request $request that contains JSON params, notably the following:
 *  - orgUuid
 *
 * @return JSON success:false or success:true, along with any related information or notices.
 */
function wicket_internal_endpoint_flag_for_rm_access($request)
{
    $params = $request->get_json_params();

    if (!isset($params['orgUuid'])) {
        wp_send_json_error('Organization uuid not provided');
    }

    $org_uuid = $params['orgUuid'];

    update_user_meta(get_current_user_id(), 'wicket_roster_man_org_to_grant', $org_uuid);

    wp_send_json_success();
}

/**
 * Sets a temporary piece of user meta so that the user will get Org Editor
 * access for the given org UUID on the next order_complete containing a membership product.
 *
 * @param WP_REST_Request $request that contains JSON params, notably the following:
 *  - orgUuid
 *
 * @return JSON success:false or success:true, along with any related information or notices.
 */
function wicket_internal_endpoint_flag_for_org_editor_access($request)
{
    $params = $request->get_json_params();

    if (!isset($params['orgUuid'])) {
        wp_send_json_error('Organization uuid not provided');
    }

    $org_uuid = $params['orgUuid'];

    update_user_meta(get_current_user_id(), 'wicket_org_editor_org_to_grant', $org_uuid);

    wp_send_json_success();
}

function wicket_internal_endpoint_grant_org_editor($request)
{
    $params = $request->get_json_params();

    if (!isset($params['orgUuid'])) {
        wp_send_json_error('Organization uuid not provided');
    }
    if (!isset($params['personUuid'])) {
        wp_send_json_error('Person uuid not provided');
    }

    $org_uuid = $params['orgUuid'];
    $person_uuid = $params['personUuid'];

    $result = wicket_assign_role($person_uuid, 'org_editor', $org_uuid);

    if ($result) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error('There was a problem assigning the org_editor role.');
    }
}

/**
 * Calls the Wicket helper functions to assign an organization a parent relationship.
 *
 * @param WP_REST_Request $request that contains JSON params, notably the following:
 *  - fromUuid
 *  - toUuid
 *
 * @return JSON success:false or success:true, along with any related information or notices.
 */
function wicket_internal_endpoint_organization_parent($request)
{
    $params = $request->get_json_params();

    if (!isset($params['fromUuid'])) {
        wp_send_json_error('fromUuid not provided');
    }
    if (!isset($params['toUuid'])) {
        wp_send_json_error('toUuid not provided');
    }

    $fromUuid = $params['fromUuid'];
    $toUuid   = $params['toUuid'];

    try {
        $client = wicket_api_client();
    } catch (\Exception $e) {
        wp_send_json_error($e->getMessage());
    }

    $payload = [
      'data' => [
        'type' => 'organizations',
        'id'   => $toUuid,
        'relationships' => [
          'parent_organization' => [
            'data' => [
              'type' => 'organizations',
              'id'   => $fromUuid,
            ],
          ],
        ],
      ]
    ];

    try {
        $response = $client->patch('organizations/' . $toUuid, [
          'json' => $payload
        ]);
    } catch (\Exception $e) {
        wp_send_json_error($e->getMessage());
    }

    wp_send_json_success($response);
}

function wicket_internal_endpoint_component_do_action($request)
{
    $params = $request->get_json_params();
    $action_name = $params['action_name'] ?? 'generic';
    $action_data = $params['action_data'] ?? []; // TODO: maybe JSON parse this if needed

    do_action('wicket_component_' . $action_name, $action_data);

    wp_send_json_success();
}
