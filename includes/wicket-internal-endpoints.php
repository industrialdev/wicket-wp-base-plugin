<?php 

add_action('rest_api_init', 'wicket_base_register_rest_routes', 8, 1 );

// Ref: https://developer.wordpress.org/reference/functions/register_rest_route/
function wicket_base_register_rest_routes(){
  register_rest_route( 'wicket-base/v1', 'search-orgs',array(
    'methods'  => 'POST',
    'callback' => 'wicket_internal_endpoint_search_orgs',
    'permission_callback' => function() {
      //return true;
      //return current_user_can('edit_posts'); // Can just return true if it's a public endpoint
      return is_user_logged_in();
    },
  ));

  register_rest_route( 'wicket-base/v1', 'search-groups',array(
    'methods'  => 'POST',
    'callback' => 'wicket_internal_endpoint_search_groups',
    'permission_callback' => function() {
      //return true;
      //return current_user_can('edit_posts'); // Can just return true if it's a public endpoint
      return is_user_logged_in();
    },
  ));

  register_rest_route( 'wicket-base/v1', 'terminate-relationship',array(
    'methods'  => 'POST',
    'callback' => 'wicket_internal_endpoint_terminate_relationship',
    'permission_callback' => function() {
      return is_user_logged_in();
    },
  ));

  register_rest_route( 'wicket-base/v1', 'create-relationship',array(
    'methods'  => 'POST',
    'callback' => 'wicket_internal_endpoint_create_relationship',
    'permission_callback' => function() {
      return is_user_logged_in();
    },
  ));

  register_rest_route( 'wicket-base/v1', 'create-org',array(
    'methods'  => 'POST',
    'callback' => 'wicket_internal_endpoint_create_org',
    'permission_callback' => function() {
      return is_user_logged_in();
    },
  ));

  register_rest_route( 'wicket-base/v1', 'flag-for-rm-access',array(
    'methods'  => 'POST',
    'callback' => 'wicket_internal_endpoint_flag_for_rm_access',
    'permission_callback' => function() {
      return is_user_logged_in();
    },
  ));

  register_rest_route( 'wicket-base/v1', 'grant-org-editor',array(
    'methods'  => 'POST',
    'callback' => 'wicket_internal_endpoint_grant_org_editor',
    'permission_callback' => function() {
      return is_user_logged_in();
    },
  ));
}

/**
 * Calls the Wicket helper functions to search for a given organization name
 * and provide a list of results.
 * 
 * @param WP_REST_Request $request that contains JSON params, notably a 'searchTerm' and an optional 'lang'.
 * 
 * @return JSON success:false or success:true, along with any related information or notices.
 */
function wicket_internal_endpoint_search_orgs( $request ) {
  $params = $request->get_json_params();

  if( !isset( $params['searchTerm'] ) ) {
    wp_send_json_error( 'Search term not provided' );
  }
  // if( !isset( $params['lang'] ) ) {
  //   wp_send_json_error( 'Language code not provided' );
  // }

  try {
    $client = wicket_api_client();
  } catch (\Exception $e) {
    wp_send_json_error( $e->getMessage() );
  }

  $search_term = $params['searchTerm'];
  $lang        = '';
  if( isset( $params['lang'] ) ) {
    $lang = $params['lang'];
  }
  $orgType     = '';
  if( isset( $params['orgType'] ) ) {
    $orgType = $params['orgType'];
  }

  // Search using the autocomplete endpoint
  $keywords = $search_term ;
  $language = !empty($lang) ? $lang : "en";

  // Autocomplete is limited to 100 results total.
  $max_results = 100; // TODO: Handle edge case where there are more than 100 results and 
                      // we need to filter by a specific org type, thus they wouldn't all show
  $autocomplete_results = $client->get('/search/autocomplete', [
    'query' => [
      // Autocomplete lookup query, can filter based on name, membership number, email etc.
      'query' => $keywords,
      // Skip side-loading of people for faster request time.
      // 'include' => '',
      'fields' => [
        'organizations' => 'legal_name_en,legal_name_fr,type'
      ],
      'filter' => [
        // Limit autocomplete results to only organization resources
        'resource_type' => 'organizations',
      ],
      'page' => [
        'size' => $max_results
      ]
    ]
  ]);

  $return = [];
  foreach ($autocomplete_results['included'] as $result) {
    $tmp = [];
    if( isset( $result['attributes']['type'] ) && !empty( $orgType ) ) {
      $result_type = $result['attributes']['type'];
      //wicket_write_log($result_type . ' vs ' . $orgType);
      if( $result_type != $orgType ) {
        //wicket_write_log('Skipped');
        // Skip this record if an org type filter was passed to this endpoint
        // and it doesn't match
        continue;
      }
    }
    $tmp['name'] = $result['attributes']['legal_name_'.$language];
    $tmp['type'] = $result['attributes']['type'];
    $tmp['id'] = $result['id'];
    $return[] = $tmp;
  }

  wp_send_json_success($return);
}

/**
 * Calls the Wicket helper functions to search for a given group name
 * and provide a list of results.
 * 
 * @param WP_REST_Request $request that contains JSON params, notably a 'searchTerm' and a 'lang'.
 * 
 * @return JSON success:false or success:true, along with any related information or notices.
 */
function wicket_internal_endpoint_search_groups( $request ) {
  $params = $request->get_json_params();

  if( !isset( $params['searchTerm'] ) ) {
    wp_send_json_error( 'Search term not provided' );
  }
  if( !isset( $params['lang'] ) ) {
    wp_send_json_error( 'Language code not provided' );
  }

  try {
    $client = wicket_api_client();
  } catch (\Exception $e) {
    wp_send_json_error( $e->getMessage() );
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
    wp_send_json_error( $e->getMessage() );
  }

  // wp_send_json_success( $search_groups );
  // return;



  $results = [];
  //wicket_write_log($search_groups);
  if ($search_groups['meta']['page']['total_items'] > 0) {
    foreach ($search_groups['data'] as $result) {
      $results[$result['id']]['id'] = $result['id'];
      if( isset( $result['attributes']["name_$lang"] ) ) {
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
 * 
 * @return JSON success:false or success:true, along with any related information or notices.
 */
function wicket_internal_endpoint_terminate_relationship( $request ) {
  $params = $request->get_json_params();

  if( !isset( $params['connectionId'] ) ) {
    wp_send_json_error( 'Connection ID not provided' );
  }

  $connectionId = $params['connectionId'];

  if( wicket_remove_connection( $connectionId ) ) {
    wp_send_json_success();
  } else {
    wp_send_json_error( 'Something went wrong removing the connection' );
  }
}

/**
 * Calls the Wicket helper functions to create a relationship.
 * 
 * @param WP_REST_Request $request that contains JSON params, notably the following:
 *  - fromUuid
 *  - toUuid
 *  - relationshipType
 *  - userRoleInRelationship
 * 
 * @return JSON success:false or success:true, along with any related information or notices.
 */
function wicket_internal_endpoint_create_relationship( $request ) {
  $params = $request->get_json_params();

  if( !isset( $params['fromUuid'] ) ) {
    wp_send_json_error( 'fromUuid not provided' );
  }
  if( !isset( $params['toUuid'] ) ) {
    wp_send_json_error( 'toUuid not provided' );
  }
  if( !isset( $params['relationshipType'] ) ) {
    wp_send_json_error( 'relationshipType not provided' );
  }
  if( !isset( $params['userRoleInRelationship'] ) ) {
    wp_send_json_error( 'userRoleInRelationship not provided' );
  }

  $fromUuid                 = $params['fromUuid'];
  $toUuid                   = $params['toUuid'];
  $relationshipType         = $params['relationshipType'];
  $userRoleInRelationship   = $params['userRoleInRelationship'];

  try {
    $client = wicket_api_client();
  } catch (\Exception $e) {
    wp_send_json_error( $e->getMessage() );
  }

  $payload = [
    'data' => [
      'type' => 'connections',
      'attributes' => [
        'connection_type'   => $relationshipType,
        'type'              => $userRoleInRelationship,
        'starts_at'         => null,
        'ends_at'           => null,
        'description'       => null,
        'tags'              => [],
      ],
      'relationships' => [
        'from' => [
          'data' => [
            'type' => 'people',
            'id'   => $fromUuid ,
            'meta' => [
              'can_manage' => false,
              'can_update' => false,
            ],
          ],
        ],
        'to' => [
          'data' => [
            'type' => 'organizations',
            'id'   => $toUuid ,
          ],
        ],
      ],
    ]
    ];

  try {
    $new_connection = wicket_create_connection( $payload );
    //wicket_write_log('creating connection:');
    //wicket_write_log($new_connection);
  } catch (\Exception $e) {
    wp_send_json_error( $e->getMessage() );
  }

  $new_connection_id = '';
  if( isset( $new_connection['data'] ) ) {
    if( isset( $new_connection['data']['id'] ) ) {
      $new_connection_id = $new_connection['data']['id'];
    }
  }

  // Grab information about the new org connection to send back
  $org_info = wicket_get_organization_basic_info( $toUuid );

  $org_memberships = wicket_get_org_memberships( $toUuid );
  $has_active_membership = false;
  if( !empty( $org_memberships ) ) {
    foreach( $org_memberships as $membership ) {
      if( isset( $membership['membership'] ) ) {
        if( isset( $membership['membership']['attributes'] ) ) {
          if( isset( $membership['membership']['attributes']['active'] ) ) {
            if( $membership['membership']['attributes']['active'] ) {
              $has_active_membership = true;
            }
          }
        }
      }
    } 
  }  

  $return =  [
    'connection_id'   => $new_connection['data']['id'] ?? '',
    'connection_type' => $relationshipType,
    'starts_at'       => $new_connection['data']['attributes']['starts_at'] ?? '',
    'ends_at'         => $new_connection['data']['attributes']['ends_at'] ?? '',
    'tags'            => $new_connection['data']['attributes']['tags'] ?? '',
    'active'          => $has_active_membership,
    'org_id'          => $toUuid,
    'org_name'        => $org_info['org_name'],
    'org_description' => $org_info['org_description'],
    'org_type'        => $org_info['org_type_pretty'],
    'org_status'      => $org_info['org_status'],
    'org_parent_id'   => $org_info['org_parent_id'],
    'org_parent_name' => $org_info['org_parent_name'],
    'person_id'       => $fromUuid,
  ];

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
function wicket_internal_endpoint_create_org( $request ) {
  $params = $request->get_json_params();

  if( !isset( $params['orgName'] ) ) {
    wp_send_json_error( 'Organization name not provided' );
  }
  if( !isset( $params['orgType'] ) ) {
    wp_send_json_error( 'Organization type not provided' );
  }

  $org_name = $params['orgName'];
  $org_type = $params['orgType'];

  $create_org_call = wicket_create_organization($org_name, $org_type);

  if( isset( $create_org_call ) && !empty( $create_org_call ) ) {
    wp_send_json_success($create_org_call);
  } else {
    wp_send_json_error( 'Something went wrong creating the organization' );
  }
}

/**
 * Sets a temporary piece of user meta so that the user will get Roster Mangement
 * access for the given org UUID on the next order_complete containing a membership product.
 * 
 * @param WP_REST_Request $request that contains JSON params, notably the following:
 *  - orgUuid
 * 
 * @return JSON success:false or success:true, along with any related information or notices.
 */
function wicket_internal_endpoint_flag_for_rm_access( $request ) {
  $params = $request->get_json_params();

  if( !isset( $params['orgUuid'] ) ) {
    wp_send_json_error( 'Organization uuid not provided' );
  }

  $org_uuid = $params['orgUuid'];

  update_user_meta( get_current_user_id(), 'roster_man_org_to_grant', $org_uuid );

  wp_send_json_success();
}

function wicket_internal_endpoint_grant_org_editor( $request ) {
  $params = $request->get_json_params();

  if( !isset( $params['orgUuid'] ) ) {
    wp_send_json_error( 'Organization uuid not provided' );
  }
  if( !isset( $params['personUuid'] ) ) {
    wp_send_json_error( 'Person uuid not provided' );
  }

  $org_uuid = $params['orgUuid'];
  $person_uuid = $params['personUuid'];

  $result = wicket_assign_role($person_uuid, 'org_editor', $org_uuid);

  if( $result ) {
    wp_send_json_success($result);
  } else {
    wp_send_json_error('There was a problem assigning the org_editor role.');
  }
}