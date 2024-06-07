<?php 

add_action('rest_api_init', 'wicket_base_register_rest_routes' );

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
}

/**
 * Calls the Wicket helper functions to search for a given organization name
 * and provide a list of results.
 * 
 * @param WP_REST_Request $request that contains JSON params, notably a 'searchTerm'.
 * 
 * @return JSON success:false or success:true, along with any related information or notices.
 */
function wicket_internal_endpoint_search_orgs( $request ) {
  $params = $request->get_json_params();

  if( !isset( $params['searchTerm'] ) ) {
    wp_send_json_error( 'Search term not provided' );
  }

  try {
    $client = wicket_api_client();
  } catch (\Exception $e) {
    wp_send_json_error( $e->getMessage() );
  }

  $args = [
    'sort' => 'legal_name',
    'page' => [
      'size' => 10,
    ],
  ];

  $args['filter']['keywords']['term'] = $params['searchTerm'];
  $args['filter']['keywords']['fields'] = 'legal_name';

  // replace query string page[0] and page[1] etc. with page[] since ruby doesn't like it
  $args = preg_replace('/\%5B\d+\%5D/', '%5B%5D', http_build_query($args));

  try {
    $search_organizations = $client->get('search/organizations?' . $args);
  } catch (\Exception $e) {
    wp_send_json_error( $e->getMessage() );
  }



  $results = [];

  if ($search_organizations['meta']['page']['total_items'] > 0) {
    foreach ($search_organizations['data'] as $result) {
      $address1 = '';
      $city = '';
      $zip_code = '';
      $state_name = '';
      $country_code = '';
      $web_address = '';
      $org_memberships = '';
      $tel = '';

      /**------------------------------------------------------------------
       * Get Primary Address
      ------------------------------------------------------------------*/
      foreach ($result['attributes']['organization']['addresses'] as $addresses) {
        if ($addresses['primary'] == 1) {
          $address1 = (isset($addresses["address1"])) ? $addresses["address1"] : '';
          $city = (isset($addresses["city"])) ? $addresses["city"] : '';
          $zip_code = (isset($addresses["zip_code"])) ? $addresses["zip_code"] : '';
          $state_name = (isset($addresses["state_name"])) ? $addresses["state_name"] : '';
          $country_code = (isset($addresses["country_code"])) ? $addresses["country_code"] : '';
        }
      }

      /**------------------------------------------------------------------
       * Get Primary Phone Number
      ------------------------------------------------------------------*/
      foreach ($result['attributes']['organization']['phones'] as $phone) {
        if ($phone['primary'] == 1) {
          $tel = $phone['number'];
        }
      }

      /**------------------------------------------------------------------
       * Get org website
      ------------------------------------------------------------------*/
      foreach ($result['attributes']['organization']['web_addresses'] as $web_addresses) {
        if ($web_addresses['type'] == 'website') {
          $web_address = $web_addresses['address'];
        }
      }

      /**------------------------------------------------------------------
       * Get org memberships
      ------------------------------------------------------------------*/
      $org_memberships = wicket_get_org_memberships($result['id']);

      $results[$result['id']]['org_id'] = $result['id'];
      $results[$result['id']]['org_name'] = $result['attributes']['organization']['legal_name'];
      $results[$result['id']]['address1'] = $address1;
      $results[$result['id']]['city'] = $city;
      $results[$result['id']]['zip_code'] = $zip_code;
      $results[$result['id']]['state_name'] = $state_name;
      $results[$result['id']]['country_code'] = $country_code;
      $results[$result['id']]['web_address'] = $web_address;
      $results[$result['id']]['org_memberships'] = $org_memberships;
      $results[$result['id']]['phone'] = $tel;
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
    wicket_write_log('creating connection:');
    wicket_write_log($new_connection);
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

  $return =  [
    'connection_id'   => $new_connection['data']['id'] ?? '',
    'connection_type' => $relationshipType,
    'starts_at'       => $new_connection['data']['attributes']['starts_at'] ?? '',
    'ends_at'         => $new_connection['data']['attributes']['ends_at'] ?? '',
    'tags'            => $new_connection['data']['attributes']['tags'] ?? '',
    'active'          => $new_connection['data']['attributes']['active'] ?? true,
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