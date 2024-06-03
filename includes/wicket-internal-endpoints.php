<?php 

add_action('rest_api_init', 'wicket_base_register_rest_routes' );

// Ref: https://developer.wordpress.org/reference/functions/register_rest_route/
function wicket_base_register_rest_routes(){
  register_rest_route( 'wicket-base/v1', 'search-orgs',array(
    'methods'  => 'POST',
    'callback' => 'wicket_internal_endpoint_search_orgs',
    'permission_callback' => function() {
      //return true;
      return current_user_can('edit_posts'); // Can just return true if it's a public endpoint
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