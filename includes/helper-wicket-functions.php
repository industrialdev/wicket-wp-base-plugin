<?php

/**
 * Wicket Helper Functions for Wicket Base Plugin
 *
 */

use Wicket\Client;

/**
 * Get Wicket settings based on the current environment.
 *
 * This function retrieves the Wicket API settings for the specified environment.
 * The environment is determined by the 'wicket_admin_settings_environment' option.
 *
 * @param string|null $environment The environment to get settings for. Default is null.
 * @return array                   The settings for the specified environment, including:
 *                                 - 'api_endpoint' (string): The API endpoint URL.
 *                                 - 'jwt' (string): The secret key for JWT authentication.
 *                                 - 'person_id' (string): The person ID.
 *                                 - 'parent_org' (string): The parent organization ID.
 *                                 - 'wicket_admin' (string): The Wicket admin setting.
 */
function get_wicket_settings($environment = null)
{
  $settings    = [];
  $environment = wicket_get_option('wicket_admin_settings_environment');

  switch ($environment) {
    case 'prod':
      $settings['api_endpoint'] = wicket_get_option('wicket_admin_settings_prod_api_endpoint');
      $settings['jwt'] = wicket_get_option('wicket_admin_settings_prod_secret_key');
      $settings['person_id'] = wicket_get_option('wicket_admin_settings_prod_person_id');
      $settings['parent_org'] = wicket_get_option('wicket_admin_settings_prod_parent_org');
      $settings['wicket_admin'] = wicket_get_option('wicket_admin_settings_prod_wicket_admin');
      break;
    case 'stage':
      $settings['api_endpoint'] = wicket_get_option('wicket_admin_settings_stage_api_endpoint');
      $settings['jwt'] = wicket_get_option('wicket_admin_settings_stage_secret_key');
      $settings['person_id'] = wicket_get_option('wicket_admin_settings_stage_person_id');
      $settings['parent_org'] = wicket_get_option('wicket_admin_settings_stage_parent_org');
      $settings['wicket_admin'] = wicket_get_option('wicket_admin_settings_stage_wicket_admin');
      break;
  }

  return $settings;
}

/**
 * Loads the Wicket API client.
 *
 * This function initializes the Wicket API client using the settings for the current environment.
 * It connects to the Wicket API and authorizes the client with the provided JWT and person ID.
 *
 * @return \Wicket\Client|false The initialized Wicket API client, or false if the client could not be initialized.
 */
function wicket_api_client()
{
  try {
    if (!class_exists('\Wicket\Client')) {
      // No SDK available!
      return FALSE;
    }

    // connect to the wicket api and get the current person
    $wicket_settings = get_wicket_settings();
    $client = new Client($app_key = '', $wicket_settings['jwt'], $wicket_settings['api_endpoint']);
    $client->authorize($wicket_settings['person_id']);
  } catch (Exception $e) {
    // don't return the $client unless the API is up.
    return false;
  }
  return $client;
}

/**
 * Get Wicket client, authorized as the current user.
 *
 * This function initializes the Wicket API client and authorizes it as the current user.
 * This is useful for giving context to person operations and respecting permissions on the Wicket side.
 *
 * @return \Wicket\Client|null The initialized and authorized Wicket API client, or null if authorization fails.
 */
function wicket_api_client_current_user()
{
  $client = wicket_api_client();

  if ($client) {
    $person_id = wicket_current_person_uuid();

    if ($person_id) {
      $client->authorize($person_id);
    } else {
      $client = null;
    }
  }

  return $client;
}

/**------------------------------------------------------------------
 * Get wicket client, authorized as the current user.
 * Taken from the wicket SDK (it's used as a protected method there)
------------------------------------------------------------------*/
function wicket_access_token_for_person($person_id, $expiresIn = 60 * 60 * 8)
{
  $settings = get_wicket_settings();
  $iat = time();

  $token = [
    'sub' => $person_id,
    'iat' => $iat,
    'exp' => $iat + $expiresIn,
  ];

  return Firebase\JWT\JWT::encode($token, $settings['jwt'], 'HS256');
}

/**------------------------------------------------------------------
 * Generate access token for Org widgets
 * This endpoint will return an access token that lets you use the profile + additional info widget on any org.
 * You will need to know the person uuid (the person currently logged into the website) and the organization uuid so you can provide it to the widget_tokens endpoint
------------------------------------------------------------------*/
function wicket_get_access_token($person_id, $org_uuid)
{
  $client = wicket_api_client();

  $payload = [
    'data' => [
      'type' => 'widget_tokens',
      'attributes' => [
        "widget_context" => "organizations",
      ],
      'relationships' => [
        'subject' => [
          'data' => [
            'type' => 'people',
            'id' => $person_id
          ]
        ],
        'resource' => [
          'data' => [
            'type' => "organizations",
            'id' => $org_uuid,
          ]
        ]
      ],
    ]
  ];

  try {
    $token = $client->post("widget_tokens", ['json' => $payload]);

    return $token['token'];
  } catch (Exception $e) {
    $errors = json_decode($e->getResponse()->getBody())->errors;

    error_log($e->getMessage());
  }

  return false;
}

/**
 * Get the current person's Wicket person UUID.
 *
 * This function retrieves the UUID of the current person based on their WordPress user login.
 *
 * @return string|null The UUID of the current person, or null if the function `wicket_api_client` is not available.
 */
function wicket_current_person_uuid()
{
  // Get the SDK client from the wicket module.
  if (function_exists('wicket_api_client')) {
    $person_id = wp_get_current_user()->user_login;

    return $person_id;
  }
}

/**
 * Get the current person from Wicket.
 *
 * This function retrieves the current person's details from Wicket.
 *
 * @return object|null The current person object if found, or null if not found.
 */
function wicket_current_person()
{
  static $person = null;

  if (is_null($person)) {
    $person_id = wicket_current_person_uuid();

    if ($person_id) {
      $client = wicket_api_client_current_user();
      $person = $client->people->fetch($person_id);

      return $person;
    }
  }

  return $person;
}

/**------------------------------------------------------------------
 * Check if user is a Wicket person (compare UUID format)
------------------------------------------------------------------*/
function wicket_person_has_uuid()
{
  $user_id   = get_current_user_id();
  $user_info = get_userdata($user_id);

  if (!$user_info || !is_object($user_info)) {
    return false;
  }

  if (is_string($user_info->user_login) && (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $user_info->user_login) == 1)) {
    return true;
  }

  return false;
}

/**
 * Used if a user exists in the MDP but not WP, and you need to sync them
 * down on a one-off basis, for example processing an order or for roster management.
 *
 * @param string $uuid UUID of their MDP person
 * @param string $first_name (optional) First name override, if needed
 * @param string $last_name  (optional) Last name override, if needed
 * @param string $femail     (optional) Email override, if needed
 *
 * @return bool | int        Will return false if there was a problem, and their new
 *                           WP user ID if successful.
 */
function wicket_create_wp_user_if_not_exist($uuid, $first_name = null, $last_name = null, $email = null) {
  if(empty($uuid)) {
    return false;
  }

  $user = get_user_by('login', $uuid);

  if ($user) {
    return $user->id;
  }

  // Grab MDP info if overrides were not provided
  if(is_null($first_name) && is_null($last_name) && is_null($email)) {
    $mdp_person = wicket_get_person_by_id($uuid);
    $first_name = $mdp_person->given_name;
    $last_name = $mdp_person->family_name;
    $email = $mdp_person->primary_email_address;
  }

  // Create the WP user
  $username = sanitize_user($uuid);
  $password = wp_generate_password(12, false);
  //$user_id  = wp_create_user($username, $password, $email);
  $user_id  = wp_insert_user([
    'user_email'   => $email,
    'user_pass'    => $password,
    'user_login'   => $username,
    'display_name' => $first_name . ' ' . $last_name,
    'first_name'   => $first_name,
    'last_name'    => $last_name,
    'role'         => 'user'
  ]);

  if (is_wp_error($user_id)) {
    return false;
  }

  return $user_id;
}

/**------------------------------------------------------------------
 * Gets all people from wicket
------------------------------------------------------------------*/
function wicket_get_all_people()
{
  $client = wicket_api_client();
  $person = $client->people->all();
  return $person;
}

/**------------------------------------------------------------------
 * Get person by UUID
------------------------------------------------------------------*/
function wicket_get_person_by_id($uuid)
{
  if ($uuid) {
    $client = wicket_api_client();
    $person = $client->people->fetch($uuid);
    return $person;
  }
  return false;
}

/**
 * Return a person object by email
 *
 * @param string $email The email address of the person
 *
 * @return object|bool The person object or false if not found
 */
function wicket_get_person_by_email($email = '')
{
  if (!$email) {
    return false;
  }

  $client = wicket_api_client();
  $person = $client->get('/people?filter[emails_primary_eq]=true&filter[emails_address_eq]=' . urlencode($email));

  // Return the first person if found
  if (isset($person['data'][0])) {
    return $person['data'][0];
  }

  return false;
}

/**------------------------------------------------------------------
 * Get email by id
------------------------------------------------------------------*/
function wicket_get_address($id)
{
  static $address = null;
  if (is_null($address)) {
    if ($id) {
      $client = wicket_api_client();
      $address = $client->addresses->fetch($id);
      return $address;
    }
  }
  return $address;
}

/**------------------------------------------------------------------
 * Get Interval by id
------------------------------------------------------------------*/
function wicket_get_interval($id)
{
  static $interval = null;
  if (is_null($interval)) {
    if ($id) {
      $client = wicket_api_client();
      try {
        $interval = $client->intervals->fetch($id);
      } catch (Exception $e) {
        $interval = false;
      }
      return $interval;
    }
  }
  return $interval;
}

/**------------------------------------------------------------------
 * Check if current logged in person has the 'member' role
------------------------------------------------------------------*/
function wicket_is_member()
{
  static $has_membership = null;
  if (is_null($has_membership)) {
    $person = wicket_current_person();
    $roles = $person->role_names;
    $has_membership = in_array('member', $roles);
  }
  return $has_membership;
}

/**------------------------------------------------------------------
 * Build firstname/lastname from person object of current user
------------------------------------------------------------------*/
function wicket_person_name()
{
  $person = wicket_current_person();
  return $person->given_name . ' ' . $person->family_name;
}

/**------------------------------------------------------------------
 * Get Wicket orders for person by person UUID
------------------------------------------------------------------*/
function wicket_get_order($uuid)
{
  $client = wicket_api_client();
  $order = $client->orders->fetch($uuid); // uuid of the order
  return $order;
}

/**------------------------------------------------------------------
 * Get all organizations from Wicket
------------------------------------------------------------------*/
function wicket_get_organizations()
{
  $client = wicket_api_client();
  static $organizations = null;
  // prepare and memoize all organizations from Wicket
  if (is_null($organizations)) {
    $organizations = $client->get('organizations');
  }
  if ($organizations) {
    return $organizations;
  }
}

/**------------------------------------------------------------------
 * Get organization by UUID from Wicket
------------------------------------------------------------------*/
function wicket_get_organization($uuid, $include = null)
{
  $query_string = '';
  $client = wicket_api_client();
  if (!empty($include)) {
    $query_string = '/?include=' . $include;
  }
  $organization = $client->get('organizations/' . $uuid . $query_string);
  if ($organization) {
    return $organization;
  }
}

/**------------------------------------------------------------------
 * Get commontly-needed organization info by UUID from Wicket
 *
 * Grabs info like the correctly-localized legal name and description, as well
 * as the parent org ID (if applicable) and its name. More info can be added
 * to the return payload as it's useful in more scenarios.
------------------------------------------------------------------*/
function wicket_get_organization_basic_info($uuid, $lang = 'en')
{
  $org_info = wicket_get_organization($uuid);

  $org_parent_id = $org_info['data']['relationships']['parent_organization']['data']['id'] ?? '';
  $org_parent_name = '';
  if (!empty($org_parent_id)) {
    $org_parent_info = wicket_get_organization($org_parent_id);
  }

  // Get language-specific meta
  $org_name = $org_info['data']['attributes']["legal_name_$lang"] ?? $org_info['data']['attributes']['legal_name'];
  $org_description = $org_info['data']['attributes']["description_$lang"] ?? $org_info['data']['attributes']['description'];

  if (isset($org_parent_info)) {
    $org_parent_name = $org_parent_info['data']['attributes']["legal_name_$lang"] ?? $org_info['data']['attributes']['legal_name'];
  }

  // Org type (also tidying up the slug for presentation if we like)
  $org_type = '';
  $org_type_pretty = '';
  if (!empty($org_info['data']['attributes']['type'])) {
    // TODO: Dig the proper UI name for the enum out of the schema, if needed in other cases
    $org_type = $org_info['data']['attributes']['type'];
    $org_type_pretty = $org_type;
    $org_type_pretty = str_replace('_', ' ', $org_type_pretty);
    $org_type_pretty = ucfirst($org_type_pretty);
  }

  return [
    'org_id'          => $uuid,
    'org_name'        => $org_name,
    'org_description' => $org_description,
    'org_type'        => $org_type,
    'org_type_pretty' => $org_type_pretty,
    'org_status'      => $org_info['data']['attributes']['status'] ?? '',
    'org_parent_id'   => $org_parent_id ?? '',
    'org_parent_name' => $org_parent_name ?? '',
  ];
}

/**
 * For searching organizations by a term when you don't have a specific UUID, likely to display
 * search results on the front end.
 *
 * @param String $search_term The query term, e.g. 'My company'
 * @param String $search_by   Currently not used, but can be expanded in the future if we want to
 *                            differentiate between searching by org name verses some other attribute
 * @param String $org_type    The org type slug you want to filter results down to. Note that autocomplete will
 *                            filter post-search and full will filter pre-search, as it has that option available.
 * @param Bool $autocomplete  Whether or not to use the autocomplete API or the search API.
 * @param String $lang        Language code to utilize, defaults to 'en'. Not fully implemented, especially in full search.
 *
 * @return Bool | Array       False if there was a problem, or an array of the results. The fewer terms suppplied by the autocomplete
 *                            endpoint should also be available in the response from the full search, for consistency in usage of the
 *                            function (e.g. both have id, name, and type parameters returned).
 */
function wicket_search_organizations($search_term, $search_by = 'org_name', $org_type = null, $autocomplete = false, $lang = 'en') {
  try {
    $client = wicket_api_client();
  } catch (\Exception $e) {
    return false;
  }

  if($autocomplete) {
    // --------------------------------------
    // Search using the autocomplete endpoint
    // --------------------------------------

    // Autocomplete is limited to 100 results total.
    $max_results = 100; // TODO: Handle edge case where there are more than 100 results and
                        // we need to filter by a specific org type, thus they wouldn't all show

    $autocomplete_results = $client->get('/search/autocomplete', [
      'query' => [
        // Autocomplete lookup query, can filter based on name, membership number, email etc.
        'query' => $search_term,
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
      if( isset( $result['attributes']['type'] ) && !is_null($org_type) ) { 
        $result_type = $result['attributes']['type'];
        if( $result_type != $org_type ) {
          //wicket_write_log('Skipped');
          // Skip this record if an org type filter was passed to this endpoint
          // and it doesn't match
          continue;
        }
      }
      $tmp['name'] = $result['attributes']['legal_name_'.$lang];
      $tmp['type'] = $result['attributes']['type'];
      $tmp['id'] = $result['id'];
      $return[] = $tmp;
    }

    return $return;
  } else {
    // -----------------------------
    // Full search, non-autocomplete
    // -----------------------------
    $args = [
      'sort' => 'legal_name',
      'page' => [
        'size' => 10,
      ],
    ];

    $args['filter']['keywords']['term'] = $search_term;
    if(!is_null($org_type)) {
      $args['filter']['type'] = $org_type;
    }
    if( !empty( $lang ) ) {
      $args['filter']['keywords']['fields'] = "legal_name_$lang";
    } else {
      $args['filter']['keywords']['fields'] = 'legal_name';
    }

    // replace query string page[0] and page[1] etc. with page[] since ruby doesn't like it
    $args = preg_replace('/\%5B\d+\%5D/', '%5B%5D', http_build_query($args));

    try {
      $search_organizations = $client->get('search/organizations?' . $args);
    } catch (\Exception $e) {
      //wp_send_json_error( $e->getMessage() );
      return false;
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

        $results[$result['id']]['id'] = $result['id'];
        $results[$result['id']]['name'] = $result['attributes']['organization']['legal_name'];
        $results[$result['id']]['type'] = $result['attributes']['organization']['type'];
        $results[$result['id']]['address1'] = $address1;
        $results[$result['id']]['city'] = $city;
        $results[$result['id']]['zip_code'] = $zip_code;
        $results[$result['id']]['state_name'] = $state_name;
        $results[$result['id']]['country_code'] = $country_code;
        $results[$result['id']]['web_address'] = $web_address;
        $results[$result['id']]['org_memberships'] = $org_memberships;
        $results[$result['id']]['phone'] = $tel;
        $results[$result['id']]['active_membership'] = $has_active_membership;
      }
    }

    return $results;
  }
}

/**------------------------------------------------------------------
 * Get all groups from Wicket
------------------------------------------------------------------*/
function wicket_get_groups()
{
  $client = wicket_api_client();

  $groups = $client->get('groups');

  if ($groups) {
    return $groups;
  }

  return false;
}

/**------------------------------------------------------------------
 * Get all "connections" (relationships) of a Wicket person
------------------------------------------------------------------*/
function wicket_get_person_connections()
{
  $client = wicket_api_client();
  $person_id = wicket_current_person_uuid();
  if ($person_id) {
    $client = wicket_api_client();
    $person = $client->people->fetch($person_id);
  }
  static $connections = null;
  // prepare and memoize all connections from Wicket
  if (is_null($connections)) {
    $connections = $client->get('people/' . $person->id . '/connections?filter%5Bconnection_type_eq%5D=all&sort=-created_at');
  }
  if ($connections) {
    return $connections;
  }
}

/**------------------------------------------------------------------
 * Get all "connections" (relationships) of a Wicket person by UUID
------------------------------------------------------------------*/
function wicket_get_person_connections_by_id($uuid)
{
  $client = wicket_api_client();
  static $connections = null;
  // prepare and memoize all connections from Wicket
  if (is_null($connections)) {
    $connections = $client->get('people/' . $uuid . '/connections?filter%5Bconnection_type_eq%5D=all&sort=-created_at');
  }
  if ($connections) {
    return $connections;
  }
}

/**------------------------------------------------------------------
 * Get all "connections" (relationships) of a Wicket org by UUID
------------------------------------------------------------------*/
function wicket_get_org_connections_by_id($uuid)
{
  $client = wicket_api_client();
  static $connections = null;
  // prepare and memoize all connections from Wicket
  if (is_null($connections)) {
    $connections = $client->get('organizations/' . $uuid . '/connections?filter%5Bconnection_type_eq%5D=all&sort=-created_at');
  }
  if ($connections) {
    return $connections;
  }
}

/**------------------------------------------------------------------
 * Get all JSON Schemas from Wicket
------------------------------------------------------------------*/
function wicket_get_schemas()
{
  $client = wicket_api_client();
  static $schemas = null;
  // prepare and memoize all schemas from Wicket
  if (is_null($schemas)) {
    $schemas = $client->get('json_schemas');
  }
  if ($schemas) {
    return $schemas;
  }
}

/**------------------------------------------------------------------
 * Load options from a schema based
 * on a schema entry found using wicket_get_schemas()
------------------------------------------------------------------*/
function wicket_get_schemas_options($schema, $field, $sub_field)
{
  $language = strtok(get_bloginfo("language"), '-');
  $return = [];

  // -----------------------------
  // GET VALUES
  // -----------------------------

  // single value
  if (isset($schema['attributes']['schema']['properties'][$field]['enum'])) {
    $counter = 0;
    foreach ($schema['attributes']['schema']['properties'][$field]['enum'] as $key => $value) {
      $return[$counter]['key'] = $value;
      $counter++;
    }
  }
  // multi-value
  if (isset($schema['attributes']['schema']['properties'][$field]['items']['enum'])) {
    $counter = 0;
    foreach ($schema['attributes']['schema']['properties'][$field]['items']['enum'] as $key => $value) {
      $return[$counter]['key'] = $value;
      $counter++;
    }
  }
  // if field is using ui_schema, get keys
  if (isset($schema['attributes']['schema']['oneOf'][0]['properties'][$field]['items']['enum'])) {
    $counter = 0;
    foreach ($schema['attributes']['schema']['oneOf'][0]['properties'][$field]['items']['enum'] as $key => $value) {
      $return[$counter]['key'] = $value;
      $counter++;
    }
  }
  // if field is using a repeater type field with 'move up/down and remove rows', get keys
  if (isset($schema['attributes']['schema']['properties'][$field]['items']['properties'][$sub_field]['enum'])) {
    $counter = 0;
    foreach ($schema['attributes']['schema']['properties'][$field]['items']['properties'][$sub_field]['enum'] as $key => $value) {
      $return[$counter]['key'] = $value;
      $counter++;
    }
  }
  // if field is using a repeater type field with repeater field inside, get keys
  if (isset($schema['attributes']['schema']['properties'][$field]['items']['properties'][$sub_field]['items']['enum'])) {
    $counter = 0;
    foreach ($schema['attributes']['schema']['properties'][$field]['items']['properties'][$sub_field]['items']['enum'] as $key => $value) {
      $return[$counter]['key'] = $value;
      $counter++;
    }
  }
  // if field is using an object type field, get keys
  if (isset($schema['attributes']['schema']['properties'][$field]['oneOf'][0]['properties'][$sub_field]['enum'])) {
    $counter = 0;
    foreach ($schema['attributes']['schema']['properties'][$field]['oneOf'] as $key => $value) {
      $return[$counter]['key'] = $value['properties'][$sub_field]['enum'][0];
      $counter++;
    }
  }
  // if field is using an object type field with values depending on another, get keys (these are buried deeper)
  if (isset($schema['attributes']['schema']['properties'][$field]['items']['oneOf'][0])) {
    $counter = 0;
    foreach ($schema['attributes']['schema']['properties'][$field]['items']['oneOf'] as $key => $value) {
      if (array_key_exists($sub_field, $value['properties'])) {
        foreach ($value['properties'][$sub_field]['items']['enum'] as $sub_value) {
          $return[$counter]['key'] = $sub_value;
          $counter++;
        }
      }
    }
  }

  // -----------------------------
  // GET LABELS
  // -----------------------------

  // get label values from ui_schema
  if (isset($schema['attributes']['ui_schema'][$field]['ui:i18n']['enumNames'][$language])) {
    $counter = 0;
    foreach ($schema['attributes']['ui_schema'][$field]['ui:i18n']['enumNames'][$language] as $key => $value) {
      $return[$counter]['value'] = $value;
      $counter++;
    }
  }
  // get label values from ui_schema
  if (isset($schema['attributes']['ui_schema'][$field]['items'][$sub_field]['ui:i18n']['enumNames'][$language])) {
    $counter = 0;
    foreach ($schema['attributes']['ui_schema'][$field]['items'][$sub_field]['ui:i18n']['enumNames'][$language] as $key => $value) {
      $return[$counter]['value'] = $value;
      $counter++;
    }
  }
  // if field is using a repeater type field with 'move up/down and remove rows', get labels
  if (isset($schema['attributes']['schema']['properties'][$field]['items']['properties'][$sub_field]['enumNames'])) {
    $counter = 0;
    foreach ($schema['attributes']['schema']['properties'][$field]['items']['properties'][$sub_field]['enumNames'] as $key => $value) {
      $return[$counter]['value'] = $value;
      $counter++;
    }
  }
  return $return;
}

/**------------------------------------------------------------------
 * Gets all the options for a field within a json schema
 * Parent field is the accordion in wicket in additional info
 * Field is a field within the accordion
 * Sub Field is optional. Would be needed if using repeater fields with objects as values
 ------------------------------------------------------------------*/
function wicket_get_schema_field_values($parent_field, $field, $sub_field = '')
{
  $schemas = wicket_get_schemas();
  if ($schemas) {
    foreach ($schemas['data'] as $key => $schema) {
      if ($schema['attributes']['key'] == $parent_field) {
        $schema = $schemas['data'][$key];
        break;
      }
    }
    $options = wicket_get_schemas_options($schema, $field, $sub_field);
    if ($options) {
      return $options;
    }
  }
}

/**------------------------------------------------------------------
 * Used to build data_fields array during form submission (common for additional_info)
 * Uses passed in $data_fields as reference to build on to
 * $data_fields = the array we build to pass to the api
 * $field = The field within each schema (under an accordion in wicket)
 * $schema = The ID for the accordion field (group of fields)
 * $type = string, array, int, boolean, object or readonly
 * $entity = usually the preloaded org or person object from the API
 ------------------------------------------------------------------*/
function wicket_add_data_field(&$data_fields, $field, $schema, $type, $entity = '')
{
  if (isset($_POST[$field])) {
    $value = $_POST[$field];

    // remove empty arrays (likely select fields with the "choose option" set)
    if ($type == 'array' && empty(array_filter($value))) {
      return false;
    }

    // remove empty strings (likely select fields with the "choose option" set)
    if ($type == 'string' && $value == '') {
      return false;
    }

    // add conversion for booleans
    if ($type == 'boolean' && $_POST[$field] == '1') {
      $value = true;
    }
    if ($type == 'boolean' && $_POST[$field] == '0') {
      $value = false;
    }
    // if boolean is posted but no value, ignore it
    if ($type == 'boolean' && $_POST[$field] == '') {
      return false;
    }
    // cast ints for the API (like year values)
    if ($type == 'int' && $value) {
      $value = (int)$value;
    } elseif ($type == 'int' && !$value) {
      // dont include int fields if we want to blank them out
      return false;
    }

    // convert object to arrays, replacing passed-in values looping over by reference
    if ($type == 'object' && $value) {
      foreach ($value as $key => &$index) {
        $index = (array)json_decode(stripslashes($index));
      }
    }

    // keep the fields for each schema together by keying the data_fields array by the schema id
    // It still seems to work through the API this way, even though the wicket admin uses zero based array indexes
    $data_fields[$schema]['value'][$field] = $value;
    $data_fields[$schema]['$schema'] = $schema;
  } else {
    // pass empty array for multi-value fields to clear them out if no options are present
    if ($type == 'array' || $type == 'object') {
      $value = [];
    }
    // unset empty string if no value set. Sometimes happens to radio buttons with no value
    if ($type == 'string') {
      return false;
    }

    // unset empty boolean if no value set. Sometimes happens to radio buttons with no value
    if ($type == 'boolean') {
      return false;
    }

    // don't return a field if array is being used using "oneOf" to clear them out if no options are present
    // these are typically used in Wicket for initial yes/no radios followed by a field if choose "yes"
    if ($type == 'array_oneof') {
      return false;
    }

    // if this field is being used as a "readonly" value on the edit form page,
    // pass on the original value(s) within the schema otherwise they'll be emptied if not passed on PATCH
    if ($type == 'readonly') {
      // make sure, usually on new accounts, that there is even AI fields to read from
      // data_fields will likely be completely empty on new accounts
      if (!empty((array)$entity->data_fields) && array_search($schema, array_column((array)$entity->data_fields, '$schema'))) {
        foreach ($entity->data_fields as $df) {
          if ($df['$schema'] == $schema) {
            // look for existing value, if there is one, else ignore this field
            if (isset($df['value'][$field])) {
              $value = $df['value'][$field];
            } else {
              return false;
            }
          }
        }
      } else {
        return false;
      }
    }

    $data_fields[$schema]['value'][$field] = $value ?? '';
    $data_fields[$schema]['$schema'] = $schema;
  }
}

/**------------------------------------------------------------------
 * Assign a person to a membership on an org
------------------------------------------------------------------*/
function wicket_assign_person_to_org_membership($person_id, $membership_id, $org_membership_id, $org_membership)
{
  $client = wicket_api_client();
  // build payload to assign person to the membership on the org

  $payload = [
    'data' => [
      'type' => 'person_memberships',
      'attributes' => [
        'starts_at' => $org_membership['data']['attributes']['starts_at'],
        "ends_at" => $org_membership['data']['attributes']['ends_at'],
        "status" => 'Active'
      ],
      'relationships' => [
        'person' => [
          'data' => [
            'id' => $person_id,
            'type' => 'people'
          ]
        ],
        'membership' => [
          'data' => [
            'id' => $membership_id,
            'type' => 'memberships'
          ]
        ],
        'organization_membership' => [
          'data' => [
            'id' => $org_membership_id,
            'type' => 'organization_memberships'
          ]
        ]
      ]
    ]
  ];

  try {
    $client->post('person_memberships', ['json' => $payload]);
    return true;
  } catch (Exception $e) {
    $errors = json_decode($e->getResponse()->getBody())->errors;
    // echo "<pre>";
    // print_r($errors);
    // echo "</pre>";
    // die;
  }
}

/**------------------------------------------------------------------
 * Unassign a person from a membership on an org
------------------------------------------------------------------*/
function wicket_unassign_person_from_org_membership($person_membership_id)
{
  $client = wicket_api_client();
  try {
    $client->delete("person_memberships/$person_membership_id");
    return true;
  } catch (Exception $e) {
    $errors = json_decode($e->getResponse()->getBody())->errors;
    // echo "<pre>";
    // print_r($errors);
    // echo "</pre>";
  }
}

/**------------------------------------------------------------------
 * Send email to user letting them know of a team assignment
 * for their account by an organization manager
 ------------------------------------------------------------------*/
function send_person_to_team_assignment_email($user, $org_id)
{
  $org = wicket_get_organization($org_id);
  $lang = defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : 'en';
  $person = wicket_get_person_by_id($user->data->user_login);

  if ($org) {
    $organization_name = $org['data']['attributes']['legal_name_' . $lang];
  }

  $to = $person->primary_email_address;
  $first_name = $person->given_name;
  $last_name = $person->family_name;
  $subject = "Welcome to NJBIA!";
  $body = "Hi $first_name, <br><br>
	You have been assigned a membership as part of $organization_name.
	<br>
	<br>
	Visit njbia.org and login to complete your profile and explore your member benefits.
	<br>
	<br>
	Thank you,
	<br>
	<br>
	New Jersey Business & Industry Association";
  $headers = array('Content-Type: text/html; charset=UTF-8');
  $headers[] = 'From: New Jersey Business & Industry Association <info@njbia.org>';
  wp_mail($to, $subject, $body, $headers);
}

/**------------------------------------------------------------------
 * Send email to NEW user letting them know of a team assignment
 * for their account by an organization manager
 ------------------------------------------------------------------*/
function send_new_person_to_team_assignment_email($first_name, $last_name, $email, $org_id)
{
  $org = wicket_get_organization($org_id);
  $lang = defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : 'en';

  if ($org) {
    $organization_name = $org['data']['attributes']['legal_name_' . $lang];
  }

  $to = $email;
  $subject = "Welcome to NJBIA!";
  $body = "Hi $first_name, <br><br>
	You have been assigned a membership as part of $organization_name.
	<br>
	<br>
	You will soon receive an Account Confirmation email with instructions on how to finalize your login account.
	Once you have confirmed your account, visit njbia.org and login to complete your profile and explore your member benefits.
	<br>
	<br>
	Thank you,
	<br>
	<br>
	New Jersey Business & Industry Association";
  $headers = array('Content-Type: text/html; charset=UTF-8');
  $headers[] = 'From: New Jersey Business & Industry Association <info@njbia.org>';
  wp_mail($to, $subject, $body, $headers);
}

/**------------------------------------------------------------------
 * Send email to Tier Contact Address for new membership pending approval
 ------------------------------------------------------------------*/
function send_approval_required_email($email, $membership_link)
{
  $lang = defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : 'en';
  $to = $email;
  $subject = "Membership Pending Approval";
  $body = "You have a membership pending approval.
	<br>
  Please login with the following link to process the membership request.
  <br>
  $membership_link";
  $headers = array('Content-Type: text/html; charset=UTF-8');
  $headers[] = 'From:' . get_bloginfo('admin_email') . '<' . get_bloginfo('admin_email') . '>';
  wp_mail($to, $subject, $body, $headers);
}


/**------------------------------------------------------------------
 * Create basic person record, no password
 ------------------------------------------------------------------*/
function wicket_create_person($given_name, $family_name, $address = '', $password = '', $password_confirmation = '', $job_title = '', $gender = '', $additional_info = [])
{
  $client = wicket_api_client();

  $wicket_settings = get_wicket_settings();
  $parent_org = $wicket_settings['parent_org'];
  $args = [
    'query' => [
      'filter' => [
        'alternate_name_en_eq' => $parent_org
      ],
      'page' => [
        'number' => 1,
        'size' => 1,
      ]
    ]
  ];
  $parent_org = $client->get('organizations', $args);
  if ($parent_org) {
    $parent_org = $parent_org['data'][0]['id'];
  }

  // build person payload
  $payload = [
    'data' => [
      'type' => 'people',
      'attributes' => [
        'given_name' => $given_name,
        'family_name' => $family_name
      ]
    ]
  ];

  // add optional email ('address')
  if (isset($address)) {
    $payload['data']['relationships']['emails']['data'][] = [
      'type' => 'emails',
      'attributes' => ['address' => $address]
    ];
  }
  // add optional password
  if (isset($password) && isset($password_confirmation) && $password != '' && $password_confirmation != '') {
    $payload['data']['attributes']['user']['password'] = $password;
    $payload['data']['attributes']['user']['password_confirmation'] = $password_confirmation;
  }
  // add optional job title
  if (isset($job_title)) {
    $payload['data']['attributes']['job_title'] = $job_title;
  }
  // add optional gender
  if (isset($job_title)) {
    $payload['data']['attributes']['gender'] = $gender;
  }
  // add optional additional info
  if (!empty($additional_info)) {
    $payload['data']['attributes']['data_fields'] = $additional_info;
  }

  try {
    $person = $client->post("organizations/$parent_org/people", ['json' => $payload]);
    return $person;
  } catch (Exception $e) {
    $errors = json_decode($e->getResponse()->getBody())->errors;
  }
  return ['errors' => $errors];
}

/**------------------------------------------------------------------
 * Assign role to person
 * $role_name is the text name of the role
 * The lookup is case sensitive so "prospective AO" and "prospective ao" would be considered different roles
 * Will create the role with matching name if it doesnt exist yet.
 * $org_uuid is for adding a relationship to this role
 ------------------------------------------------------------------*/
function wicket_assign_role($person_uuid, $role_name, $org_uuid = '')
{
  $client = wicket_api_client();

  // build role payload
  $payload = [
    'data' => [
      'type' => 'roles',
      'attributes' => [
        'name' => $role_name,
      ]
    ]
  ];

  if ($org_uuid != '') {
    $payload['data']['relationships']['resource']['data']['id'] = $org_uuid;
    $payload['data']['relationships']['resource']['data']['type'] = 'organizations';
  }

  try {
    $client->post("people/$person_uuid/roles", ['json' => $payload]);
    return true;
  } catch (Exception $e) {
    $errors = json_decode($e->getResponse()->getBody())->errors;
    // echo "<pre>";
    // print_r($e->getMessage());
    // echo "</pre>";
    //
    // echo "<pre>";
    // print_r($errors);
    // echo "</pre>";
    // die;
  }
  return false;
}

/**------------------------------------------------------------------
 * Removes role from person
 * $role_name is the text name of the role
 * The lookup is case sensitive so "prospective AO" and "prospective ao" would be considered different roles
 ------------------------------------------------------------------*/
function wicket_remove_role($person_uuid, $role_name)
{
  $client = wicket_api_client();
  $person = wicket_get_person_by_id($person_uuid);

  $role_id = '';
  if ($person) {
    foreach ($person->included() as $included) {
      if ($included['type'] == 'roles' && $included['attributes']['name'] == $role_name) {
        // get the role id for use in the payload below
        $role_id = $included['id'];
        break;
      }
    }
  }

  if ($role_id) {
    // build role payload
    $payload = [
      'data' => [
        [
          'type' => 'roles',
          'id' => $role_id
        ]
      ]
    ];

    try {
      $client->delete("people/$person_uuid/relationships/roles", ['json' => $payload]);
      return true;
    } catch (Exception $e) {
      $errors = json_decode($e->getResponse()->getBody())->errors;
      // echo "<pre>";
      // print_r($e->getMessage());
      // echo "</pre>";
      //
      // echo "<pre>";
      // print_r($errors);
      // echo "</pre>";
      // die;
    }
  }

  return false;
}

/**------------------------------------------------------------------
 * Assign organization membership to person
 ------------------------------------------------------------------*/
function wicket_assign_organization_membership($person_uuid, $org_id, $membership_id, $starts_at = '', $ends_at = '', $max_seats = 0, $grace_period_days = 0)
{
  $client = wicket_api_client();

  if (empty($starts_at)) {
    $starts_at = date('c', time());
  }
  if (empty($ends_at)) {
    $ends_at = date('c', strtotime('+1 year'));
  }

  // build membership payload
  $payload = [
    'data' => [
      'type' => 'organization_memberships',
      'attributes' => [
        'starts_at' => $starts_at,
        "ends_at" => $ends_at,
        "max_assignments" => $max_seats,
        "grace_period_days" => $grace_period_days,
      ],
      'relationships' => [
        'owner' => [
          'data' => [
            'id' => $person_uuid,
            'type' => 'people'
          ]
        ],
        'membership' => [
          'data' => [
            'id' => $membership_id,
            'type' => 'memberships'
          ]
        ],
        'organization' => [
          'data' => [
            'id' => $org_id,
            'type' => 'organizations'
          ]
        ]
      ]
    ]
  ];

  try {
    $response = $client->post("organization_memberships", ['json' => $payload]);
  } catch (Exception $e) {
    $response = new \WP_Error('wicket_api_error', $e->getMessage());
  }
  return $response;
}

/**
 * Check for matching membership for person
 * Option: filter by date
 */
function wicket_get_person_membership_exists($person_uuid, $membership_uuid, $starts_at = '', $ends_at = '')
{
  $client = wicket_api_client();
  try {
    $response = $client->get("people/$person_uuid/membership_entries?include=membership&filter[starts_at_eq]=$starts_at&filter[ends_at_eq]=$ends_at&page[size]=2000");
    foreach ($response['data'] as $record) {
      if ($record['relationships']['membership']['data']['id'] == $membership_uuid) {
        return $record['id'];
      }
    }
  } catch (Exception $e) {
    $response = new \WP_Error('wicket_api_error', $e->getMessage());
  }
}

/**------------------------------------------------------------------
 * Assign individual membership to person
 ------------------------------------------------------------------*/
function wicket_assign_individual_membership($person_uuid, $membership_uuid, $starts_at = '', $ends_at = '', $grace_period_days = 0)
{
  $client = wicket_api_client();

  if (empty($starts_at)) {
    $starts_at = date('c', time());
  }
  if (empty($ends_at)) {
    $ends_at = date('c', strtotime('+1 year'));
  }

  // build membership payload
  $payload = [
    'data' => [
      'type' => 'person_memberships',
      'attributes' => [
        'starts_at' => $starts_at,
        'ends_at' => $ends_at,
        "grace_period_days" => $grace_period_days,
      ],
      'relationships' => [
        'person' => [
          'data' => [
            'id' => $person_uuid,
            'type' => 'people'
          ]
        ],
        'membership' => [
          'data' => [
            'id' => $membership_uuid,
            'type' => 'memberships'
          ]
        ]
      ]
    ]
  ];

  try {
    $response = $client->post("person_memberships", ['json' => $payload]);
  } catch (Exception $e) {
    $response = new \WP_Error('wicket_api_error', $e->getMessage());
  }
  return $response;
}

/**------------------------------------------------------------------
 * Update individual membership dates
 ------------------------------------------------------------------*/
function wicket_update_individual_membership_dates($membership_uuid, $starts_at = '', $ends_at = '', $grace_period_days = false)
{
  $client = wicket_api_client();

  if (empty($starts_at)) {
    $starts_at = date('c', time());
  }
  if (empty($ends_at)) {
    $ends_at = date('c', strtotime('+1 year'));
  }

  // build membership payload
  $payload = [
    'data' => [
      'type' => 'person_memberships',
      'attributes' => [
        'starts_at' => $starts_at,
        'ends_at' => $ends_at
      ],
    ]
  ];

  if ($grace_period_days !== false) {
    $payload['data']['attributes']['grace_period_days'] = $grace_period_days;
  }

  try {
    $response = $client->patch("/person_memberships/$membership_uuid", ['json' => $payload]);
  } catch (Exception $e) {
    $response = new \WP_Error('wicket_api_error', $e->getMessage());
  }
  return $response;
}


/**------------------------------------------------------------------
 * Update organization membership dates
 ------------------------------------------------------------------*/
function wicket_update_organization_membership_dates($membership_uuid, $starts_at = '', $ends_at = '', $max_seats = false, $grace_period_days = false)
{
  $client = wicket_api_client();

  if (empty($starts_at)) {
    $starts_at = date('c', time());
  }
  if (empty($ends_at)) {
    $ends_at = date('c', strtotime('+1 year'));
  }

  // build membership payload
  $payload = [
    'data' => [
      'type' => 'organization_memberships',
      'attributes' => [
        'starts_at' => $starts_at,
        "ends_at" => $ends_at
      ],
    ]
  ];

  if ($max_seats !== false) {
    $payload['data']['attributes']['max_assignments'] = $max_seats;
  }

  if ($grace_period_days !== false) {
    $payload['data']['attributes']['grace_period_days'] = $grace_period_days;
  }

  try {
    $response = $client->patch("organization_memberships/$membership_uuid", ['json' => $payload]);
  } catch (Exception $e) {
    $response = new \WP_Error('wicket_api_error', $e->getMessage());
  }
  return $response;
}

/**
 * Set the external ID on the membership record
 *
 * @param string $membership_uuid wicket mdp membership id
 * @param string $membership_type organization|individual
 * @param int $external_id post_id
 * @return object | \WP_Error
 */
function wicket_update_membership_external_id($membership_uuid, $membership_type, $external_id)
{
  $client = wicket_api_client();

  if (!in_array($membership_type, ['organization_memberships', 'person_memberships'])) {
    new \WP_Error('wicket_api_error', 'Unknown membership_type ( organization_memberships, person_memberships )');
  }

  // build membership payload
  $payload = [
    'data' => [
      'type' => $membership_type,
      'attributes' => [
        'external_id' => $external_id
      ],
    ]
  ];

  try {
    $response = $client->patch("$membership_type/$membership_uuid", ['json' => $payload]);
  } catch (Exception $e) {
    $response = new \WP_Error('wicket_api_error', $e->getMessage());
  }
  return $response;
}

/**------------------------------------------------------------------
 * Gets the current person memberships
 * using the person membership entries endpoint
 ------------------------------------------------------------------*/
function wicket_get_current_person_memberships()
{
  $client = wicket_api_client();
  $uuid = wicket_current_person_uuid();
  static $memberships = null;
  // prepare and memoize all connections from Wicket
  if (is_null($memberships)) {
    try {
      $memberships = $client->get('people/' . $uuid . '/membership_entries?include=membership,organization_membership.organization,fusebill_subscription');
    } catch (Exception $e) {
    }
  }
  if ($memberships) {
    return $memberships;
  }
}

/**------------------------------------------------------------------
 * Create organization
 * $additional_info is data_fields. An array of arrays to get the number based indexing needed
 * $org_type will be the machine name of the different org types available for the wicket instance
 ------------------------------------------------------------------*/
function wicket_create_organization($org_name, $org_type, $additional_info = [])
{
  $client = wicket_api_client();

  // build org payload
  $payload = [
    'data' => [
      'type' => 'organizations',
      'attributes' => [
        'type' => $org_type,
        'legal_name' => $org_name,
      ]
    ]
  ];

  if (!empty($additional_info)) {
    $payload['data']['attributes']['data_fields'] = $additional_info;
  }

  try {
    $org = $client->post("organizations", ['json' => $payload]);
    return $org;
  } catch (Exception $e) {
    $errors = json_decode($e->getResponse()->getBody())->errors;
    // echo "<pre>";
    // print_r($e->getMessage());
    // echo "</pre>";
    //
    // echo "<pre>";
    // print_r($errors);
    // echo "</pre>";
    // die;
  }
  return false;
}

/**------------------------------------------------------------------
 * Create organization address
 * $payload is an array of attributes. See how wicket does this via the API/network tab in chrome
 * an example might be the following:
 $payload = [
   'data' => [
     'type' => 'addresses',
     'attributes' => [
       'type' => 'work',
       'address1' => '123 fake st',
       'city' => 'ottawa',
       'country_code' => 'CA',
       'state_name' => 'ON',
       'zip_code' => 'k1z6x6'
     ]
   ]
 ];
 ------------------------------------------------------------------*/
function wicket_create_organization_address($org_id, $payload)
{
  $client = wicket_api_client();

  try {
    $org = $client->post("organizations/$org_id/addresses", ['json' => $payload]);
    return true;
  } catch (Exception $e) {
    $errors = json_decode($e->getResponse()->getBody())->errors;
    // echo "<pre>";
    // print_r($e->getMessage());
    // echo "</pre>";
    //
    // echo "<pre>";
    // print_r($errors);
    // echo "</pre>";
    // die;
  }
  return false;
}

/**------------------------------------------------------------------
 * Create person address
 * $payload is an array of attributes. See how wicket does this via the API/network tab in chrome
 * an example might be the following:
 $payload = [
   'data' => [
     'type' => 'addresses',
     'attributes' => [
       'type' => 'work',
       'address1' => '123 fake st',
       'city' => 'ottawa',
       'country_code' => 'CA',
       'state_name' => 'ON',
       'zip_code' => 'k1z6x6'
     ]
   ]
 ];
 ------------------------------------------------------------------*/
function wicket_create_person_address($person_uuid, $payload)
{
  $client = wicket_api_client();

  try {
    $org = $client->post("people/$person_uuid/addresses", ['json' => $payload]);
    return true;
  } catch (Exception $e) {
    $errors = json_decode($e->getResponse()->getBody())->errors;
    // echo "<pre>";
    // print_r($e->getMessage());
    // echo "</pre>";
    //
    // echo "<pre>";
    // print_r($errors);
    // echo "</pre>";
    // die;
  }
  return false;
}

/**------------------------------------------------------------------
 * Create organization email
 * $payload is an array of attributes. See how wicket does this via the API/network tab in chrome
 ------------------------------------------------------------------*/
function wicket_create_organization_email($org_id, $payload)
{
  $client = wicket_api_client();

  try {
    $org = $client->post("organizations/$org_id/emails", ['json' => $payload]);
    return true;
  } catch (Exception $e) {
    $errors = json_decode($e->getResponse()->getBody())->errors;
    // echo "<pre>";
    // print_r($e->getMessage());
    // echo "</pre>";
    //
    // echo "<pre>";
    // print_r($errors);
    // echo "</pre>";
    // die;
  }
  return false;
}

/**------------------------------------------------------------------
 * Create organization phone
 * $payload is an array of attributes. See how wicket does this via the API/network tab in chrome
 ------------------------------------------------------------------*/
function wicket_create_organization_phone($org_id, $payload)
{
  $client = wicket_api_client();

  try {
    $org = $client->post("organizations/$org_id/phones", ['json' => $payload]);
    return true;
  } catch (Exception $e) {
    $errors = json_decode($e->getResponse()->getBody())->errors;
    // echo "<pre>";
    // print_r($e->getMessage());
    // echo "</pre>";
    //
    // echo "<pre>";
    // print_r($errors);
    // echo "</pre>";
    // die;
  }
  return false;
}

/**------------------------------------------------------------------
 * Create person phone
 * $payload is an array of attributes. See how wicket does this via the API/network tab in chrome
 ------------------------------------------------------------------*/
function wicket_create_person_phone($person_uuid, $payload)
{
  $client = wicket_api_client();

  try {
    $org = $client->post("people/$person_uuid/phones", ['json' => $payload]);
    return true;
  } catch (Exception $e) {
    $errors = json_decode($e->getResponse()->getBody())->errors;
    // echo "<pre>";
    // print_r($e->getMessage());
    // echo "</pre>";
    //
    // echo "<pre>";
    // print_r($errors);
    // echo "</pre>";
    // die;
  }
  return false;
}

/**------------------------------------------------------------------
 * Create organization website
 * $payload is an array of attributes. See how wicket does this via the API/network tab in chrome
 ------------------------------------------------------------------*/
function wicket_create_organization_web_address($org_id, $payload)
{
  $client = wicket_api_client();

  try {
    $org = $client->post("organizations/$org_id/web_addresses", ['json' => $payload]);
    return true;
  } catch (Exception $e) {
    $errors = json_decode($e->getResponse()->getBody())->errors;
    // echo "<pre>";
    // print_r($e->getMessage());
    // echo "</pre>";
    //
    // echo "<pre>";
    // print_r($errors);
    // echo "</pre>";
    // die;
  }
  return false;
}

/**------------------------------------------------------------------
 * Add a user to a Wicket group.
 * $group_role_slug could be obtained with a function call like wicket_get_entity_types()
 * and then wicket_get_resource_types() using the entity's uuid.
------------------------------------------------------------------*/
function wicket_add_group_member($person_id, $group_id, $group_role_slug, $start_date = null, $end_date = null)
{
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
            // 'meta' => [
            //   'can_manage' => true,
            //   'can_update' => true,
            // ],
            'type' => 'groups',
          ],
        ],
      ],
      'type'          => 'group_members',
    ]
  ];

  try {
    $apiCall = $client->post('group_members', ['json' => $payload]);
    return $apiCall;
  } catch (\Exception $e) {
    $errors = json_decode($e->getResponse()->getBody())->errors;
    echo "<pre>";
    print_r($e->getMessage());
    echo "</pre>";

    echo "<pre>";
    print_r($errors);
    echo "</pre>";
    die;
  }
  return false;
}

/**------------------------------------------------------------------
 * Create connection
 * $payload is an array of attributes. See how wicket does this via the API/network tab in chrome
 * an example might be the following:
$relationship_payload = [
    'data' => [
      'type' => 'connections',
      'attributes' => [
        'type' => 'employee',
        'starts_at' => date("Y-m-d"),
        'description' => 'my description goes here'
      ],
      'relationships' => [
        'organization' => [
          'data' => [
            'id' => $org_id, //org id
            'type' => 'organizations'
          ]
        ],
        'person' => [
          'data' => [
            'id' => $person['data']['id'],
            'type' => 'people'
          ]
        ],
        'from' => [
          'data' => [
            'id' => $person['data']['id'],
            'type' => 'people'
          ]
        ],
        'to' => [
          'data' => [
            'id' => $org_id, //org id
            'type' => 'organizations'
          ]
        ],
      ],
    ]
  ];
 ------------------------------------------------------------------*/
function wicket_create_connection($payload)
{
  $client = wicket_api_client();

  try {
    $apiCall = $client->post('connections', ['json' => $payload]);
    return $apiCall;
  } catch (\Exception $e) {
    $errors = json_decode($e->getResponse()->getBody())->errors;
    echo "<pre>";
    print_r($e->getMessage());
    echo "</pre>";

    echo "<pre>";
    print_r($errors);
    echo "</pre>";
    die;
  }
  return false;
}

function wicket_create_person_to_org_connection($person_uuid, $org_uuid, $relationship_type)
{
  $payload = [
    'data' => [
      'type' => 'connections',
      'attributes' => [
        'connection_type'   => 'person_to_organization',
        'type'              => $relationship_type,
        'starts_at'         => null,
        'ends_at'           => null,
        'description'       => null,
        'tags'              => [],
      ],
      'relationships' => [
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
    ]
  ];

  try {
    $new_connection = wicket_create_connection( $payload );
  } catch (\Exception $e) {
    wicket_write_log($e->getMessage());
  }

  $new_connection_id = '';
  if( isset( $new_connection['data'] ) ) {
    if( isset( $new_connection['data']['id'] ) ) {
      $new_connection_id = $new_connection['data']['id'];
    }
  }

  if(empty($new_connection_id)) {
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

/**------------------------------------------------------------------
 * Remove connection
 * $connection_id can be retrieved by using  wicket_get_person_connections_by_id($uuid)
 * or wicket_get_person_connections().
 * ------------------------------------------------------------------*/
function wicket_remove_connection($connection_id)
{
  try {
    $client = wicket_api_client();
  } catch (\Exception $e) {
    error_log($e->getMessage());
    return false;
  }

  try {
    $removed_connection = $client->delete('connections/' . $connection_id);
  } catch (\Exception $e) {
    error_log($e->getMessage());
    return false;
  }

  return true;
}

/**
 * Set start and/or end date of a connection
 * It's expected that the dates are formatted YYYY-MM-DD
 *
 * @param string $connection_id The connection ID to update.
 * @param string $end_date The end date to set. Format: YYYY-MM-DD.
 * @param string $start_date Optional. The start date to set. Format: YYYY-MM-DD. Leave empty to keep the current start date.
 *
 * @return mixed Response from the API call on success, false otherwise.
 */
function wicket_set_connection_start_end_dates( $connection_id, $end_date = '', $start_date = '' ) {

  if( empty( $end_date ) ) {
    return false;
  }

  try {
    $client = wicket_api_client();
  } catch (\Exception $e) {
    error_log($e->getMessage());
    return false;
  }

  try {
    $current_connection_info = wicket_get_connection_by_id( $connection_id );

    if( empty( $current_connection_info ) ) {
      return false;
    }

    $attributes = $current_connection_info['data']['attributes'];

    // Only if we received a start date, set it
    if( !empty( $start_date ) ) {
      $attributes['starts_at'] = strval($start_date);
    }

    $attributes['ends_at']   = !empty( $end_date ) ? strval($end_date) : null;

    // Ensure empty fields stay null, which the MDP likes
    $attributes['description'] = !empty( $attributes['description'] ) ? $attributes['description'] : null;
    $attributes['custom_data_field'] = !empty( $attributes['custom_data_field'] ) ? $attributes['custom_data_field'] : null;
    $attributes['tags'] = !empty( $attributes['tags'] ) ? $attributes['tags'] : null;

    $payload = [
      'data' => [
        'attributes'    => $attributes,
        'id'            => $connection_id,
        'relationships' => [
          'from' => $current_connection_info['data']['relationships']['from'],
          'to'   => $current_connection_info['data']['relationships']['to'],
        ],
        'type'          => $current_connection_info['data']['type'],
      ]
    ];
    wicket_write_log('payload before send:');
    wicket_write_log($payload);

    $updated_connection = $client->patch('connections/' . $connection_id, ['json' => $payload]);

    return $updated_connection;
  } catch (\Exception $e) {
    error_log($e->getMessage());

    return false;
  }

  return false;
}

/**------------------------------------------------------------------
 * Gets array of information from the MDP API by querying a GET
 * on the provided $connection_id
 * ------------------------------------------------------------------*/
function wicket_get_connection_by_id( $connection_id ) {
  try {
    $client = wicket_api_client();
  } catch (\Exception $e) {
    error_log($e->getMessage());
    return false;
  }

  try {
    $connection = $client->get('connections/' . $connection_id);
    return $connection;
  } catch (\Exception $e) {
    error_log($e->getMessage());
    return false;
  }
}

/**
 * Adds a tag(s) to an organization.
 *
 * @param String $org_uuid
 * @param Mixed $tags could be a single tag as a String, or an
 * array of Strings.
 *
 * @return Array payload response from API.
 */
function wicket_add_tag_organization($org_uuid, $tags) {
  try {
    $client = wicket_api_client();
  } catch (\Exception $e) {
    error_log($e->getMessage());
    return false;
  }

  if(!is_array($tags)) {
    $tags = [ $tags ];
  }

  // Grab current tags, if any
  $org_data = wicket_get_organization($org_uuid);
  $existing_tags = $org_data['data']['attributes']['tags'] ?? [];

  $tags = array_merge($existing_tags, $tags);

  // Add new tags to current tags

  $payload = [
    'data' => [
      'type' => 'organizations',
      'id' => "$org_uuid",
      'attributes' => [
        'tags' => $tags
      ]
    ]
  ];

  try {
    return $client->patch("organizations/$org_uuid", ['json' => $payload]);
  } catch (\Exception $e) {
    error_log($e->getMessage());
    return false;
  }
}

/**
 * Overwrites the tags for an organization.
 *
 * @param String $org_uuid
 * @param Mixed $tags could be a single tag as a String, or an
 * array of Strings.
 *
 * @return Array payload response from API.
 */
function wicket_set_tag_organization($org_uuid, $tags) {
  try {
    $client = wicket_api_client();
  } catch (\Exception $e) {
    error_log($e->getMessage());
    return false;
  }

  if(!is_array($tags)) {
    $tags = [ $tags ];
  }

  // Add new tags to current tags

  $payload = [
    'data' => [
      'type' => 'organizations',
      'id' => "$org_uuid",
      'attributes' => [
        'tags' => $tags
      ]
    ]
  ];

  try {
    return $client->patch("organizations/$org_uuid", ['json' => $payload]);
  } catch (\Exception $e) {
    error_log($e->getMessage());
    return false;
  }
}

/**
 * Removes a tag(s) from an organization.
 *
 * @param String $org_uuid
 * @param Mixed $tags could be a single tag as a String, or an
 * array of Strings.
 *
 * @return Array payload response from API.
 */
function wicket_remove_tag_organization($org_uuid, $tags) {
  try {
    $client = wicket_api_client();
  } catch (\Exception $e) {
    error_log($e->getMessage());
    return false;
  }

  if(!is_array($tags)) {
    $tags = [ $tags ];
  }

  // Grab current tags, if any
  $org_data = wicket_get_organization($org_uuid);
  $existing_tags = $org_data['data']['attributes']['tags'] ?? [];

  // Remove elements from $tags found in $existing_tags
  $tags = array_diff($existing_tags, $tags);
  $tags = array_values($tags);

  $payload = [
    'data' => [
      'type' => 'organizations',
      'id' => "$org_uuid",
      'attributes' => [
        'tags' => $tags
      ]
    ]
  ];

  try {
    $result = $client->patch("organizations/$org_uuid", ['json' => $payload]);
    return $result;
  } catch (\Exception $e) {
    wicket_write_log($e->getMessage());
    return false;
  }
}

/**
 * Get Touchpoints for the Current User.
 *
 * This function retrieves touchpoints for the current user based on the provided service ID.
 *
 * @param string $service_id The ID of the service to filter touchpoints by.
 * @return array|false       The touchpoints if successful, or false on failure.
 */
function wicket_get_current_user_touchpoints($service_id)
{
  $client    = wicket_api_client();
  $person_id = wicket_current_person_uuid();

  try {
    $touchpoints = $client->get("people/$person_id/touchpoints?page[size]=100&filter[service_id]=$service_id", ['json']);

    return $touchpoints;
  } catch (Exception $e) {
    $errors = json_decode($e->getResponse()->getBody())->errors;
  }
  return false;
}

/**
 * Write a Touchpoint.
 *
 * This function sends a touchpoint to the Wicket API based on the provided parameters and service ID.
 *
 * USAGE:
 * ```php
 * $params = [
 *   'person_id' => '[uuid from wicket]',
 *   'action' => 'test action',
 *   'details' => 'these are some details',
 *   'data' => ['test' => 'thing'],
 *   'external_event_id' => 'some unique value used when you dont want duplicate touchpoints but cant control how they are triggered'
 * ];
 * write_touchpoint($params, get_create_touchpoint_service_id('[service name]', '[service description]'));
 * ```
 *
 * @param array  $params            The parameters for the touchpoint, including:
 *                                  - 'person_id' (string): The UUID of the person from Wicket.
 *                                  - 'action' (string): The action of the touchpoint.
 *                                  - 'details' (string): Details about the touchpoint.
 *                                  - 'data' (array): Additional data for the touchpoint.
 *                                  - 'external_event_id' (string): A unique value to prevent duplicate touchpoints.
 * @param string $wicket_service_id The ID of the Wicket service.
 * @return bool                     True if the touchpoint was successfully written, false otherwise.
 */
function write_touchpoint($params, $wicket_service_id)
{
  $client  = wicket_api_client();
  $payload = build_touchpoint_payload($params, $wicket_service_id);

  if ($payload) {
    try {
      $res = $client->post('touchpoints', ['json' => $payload]);
    } catch (\Exception $e) {
      error_log($e->getMessage());
    }
    if (isset($res)) {
      return true;
    }
  }
}

/**
 * Build Touchpoint Payload.
 *
 * This function constructs the payload for a touchpoint based on the provided parameters and service ID.
 *
 * @param array  $params            The parameters for the touchpoint, including:
 *                                  - 'person_id' (string): The UUID of the person from Wicket.
 *                                  - 'action' (string): The action of the touchpoint.
 *                                  - 'details' (string): Details about the touchpoint.
 *                                  - 'data' (array): Additional data for the touchpoint (optional).
 *                                  - 'external_event_id' (string): A unique value to prevent duplicate touchpoints (optional).
 * @param string $wicket_service_id The ID of the Wicket service.
 * @return array                    The constructed payload for the touchpoint.
 */
function build_touchpoint_payload($params, $wicket_service_id)
{
  $payload = [
    'data' => [
      'type' => 'touchpoints',
      'attributes' => [
        'action' => $params['action'],
        'details' => html_entity_decode($params['details']),
        'code' => str_replace(' ', '_', strtolower($params['action'])),
      ],
      'relationships' => [
        'person' => [
          'data' => [
            'id' => $params['person_id'],
            'type' => 'people'
          ]
        ],
        'service' => [
          'data' => [
            'id' => $wicket_service_id, //service id in wicket
            'type' => 'services'
          ]
        ]
      ],
    ]
  ];

  if (isset($params['data'])) {
    $payload['data']['attributes']['data'] = $params['data'];
  }

  if (isset($params['external_event_id'])) {
    $payload['data']['attributes']['external_event_id'] = $params['external_event_id'];
  }

  return $payload;
}

/**
 * Get or create a touchpoint service ID.
 *
 * This function retrieves an existing service ID by the given service name.
 * If the service does not exist, it creates a new service with the specified
 * name and description and returns the newly created service ID.
 *
 * Example usage:
 * ```php
 * $service_id = get_create_touchpoint_service_id('Events Calendar', 'Events from the website');
 * write_touchpoint($params, $service_id);
 * ```
 *
 * @param string $service_name        The name of the service.
 * @param string $service_description The description of the service. Default is 'Custom from WP'.
 * @return string|false               The service ID if successful, or false on failure.
 */
function get_create_touchpoint_service_id($service_name, $service_description = 'Custom from WP')
{
  $client = wicket_api_client();

  // check for existing service, return service ID
  $existing_services = $client->get("services?filter[name_eq]=$service_name");
  $existing_service = isset($existing_services['data']) && !empty($existing_services['data']) ? $existing_services['data'][0]['id'] : '';

  if ($existing_service) {
    return $existing_service;
  }

  // if no existing service, create one and return service ID
  $payload['data']['attributes'] = [
    'name' => $service_name,
    'description' => $service_description,
    'status' => 'active',
    'integration_type' => "custom",
  ];

  try {
    $service = $client->post("/services", ['json' => $payload]);

    return $service['data']['id'];
  } catch (Exception $e) {
    $errors = json_decode($e->getResponse()->getBody())->errors;
  }

  return false;
}

/**------------------------------------------------------------------
 * Get active org memberships current user owns
 ------------------------------------------------------------------*/
function wicket_get_active_org_memberships()
{
  $client = wicket_api_client();
  $person_id = wicket_current_person_uuid();
  if ($person_id) {
    $organization_memberships = $client->get("/organization_memberships?filter[owner_uuid_eq]=$person_id&filter[m]=or");
    $active_memberships = [];
    if (isset($organization_memberships['data'][0])) {
      foreach ($organization_memberships['data'] as $org_membership) {
        if ($org_membership['attributes']['active'] == 1) {
          $active_memberships[] = $org_membership;
        }
      }
    }
    return $active_memberships;
  } else {
    return [];
  }
}

/**------------------------------------------------------------------
 * Get org memberships
 ------------------------------------------------------------------*/
function wicket_get_org_memberships($org_id)
{
  $client = wicket_api_client();
  if ($org_id) {
    $organization_memberships = $client->get("/organizations/$org_id/membership_entries?sort=-ends_at&include=membership");
    $memberships = [];
    if (isset($organization_memberships['data'][0])) {
      foreach ($organization_memberships['data'] as $org_membership) {
        $memberships[$org_membership['id']]['membership'] = $org_membership;
        // add included attributes as well
        foreach ($organization_memberships['included'] as $included) {
          if ($included['id'] == $org_membership['relationships']['membership']['data']['id']) {
            $memberships[$org_membership['id']]['included'] = $included;
          }
        }
      }
    }
    return $memberships;
  } else {
    return [];
  }
}

/**------------------------------------------------------------------
 * Gets spoken languages resource list (used in account center comm. prefs)
------------------------------------------------------------------*/
function get_spoken_languages_list()
{
  $client = wicket_api_client();
  $resource_types = $client->resource_types->all()->toArray();
  $resource_types = collect($resource_types);
  $found = $resource_types->filter(function ($item) {
    return $item->resource_type == 'shared_written_spoken_languages';
  });

  return $found;
}

/**------------------------------------------------------------------
 * Gets org types resource list
------------------------------------------------------------------*/
function get_org_types_list()
{
  $client = wicket_api_client();
  $resource_types = $client->resource_types->all()->toArray();
  $resource_types = collect($resource_types);
  $found = $resource_types->filter(function ($item) {
    return $item->resource_type == 'organizations';
  });

  return $found;
}

/**------------------------------------------------------------------
 * Gets all entity types and returns their data array from the API call
------------------------------------------------------------------*/
function wicket_get_entity_types()
{
  try {
    $client = wicket_api_client();
  } catch (\Exception $e) {
    error_log($e->getMessage());
    return false;
  }

  try {
    $entity_types = $client->get('entity_types?page%5Bnumber%5D=1&page%5Bsize%5D=9999999');
    if (isset($entity_types['data'])) {
      return $entity_types;
    } else {
      return false;
    }
  } catch (\Exception $e) {
    error_log($e->getMessage());
    return false;
  }
}

/**------------------------------------------------------------------
 * Gets the available resource types for the provided $entity_type_slug, or if
 * no slug is provided (or the UUID for it cannot be found), all resource types and
 * their data are returned
------------------------------------------------------------------*/
function wicket_get_resource_types($entity_type_slug = '')
{
  try {
    $client = wicket_api_client();
  } catch (\Exception $e) {
    error_log($e->getMessage());
    return false;
  }

  $entity_types = wicket_get_entity_types();

  $entity_type_uuid = '';
  if (isset($entity_types['data'])) {
    foreach ($entity_types['data'] as $entity) {
      if (isset($entity['attributes'])) {
        if (isset($entity['attributes']['code'])) {
          if ($entity['attributes']['code'] == $entity_type_slug) {
            $entity_type_uuid = $entity['attributes']['uuid'];
          }
        }
      }
    }
  }
  // If no $entity_type_slug is provided or the $entity_type_uuid is not found, all recource_types will be returned

  try {
    $resource_types = $client->get("resource_types?filter%5Bentity_type_uuid_eq%5D=$entity_type_uuid");
    return $resource_types;
  } catch (\Exception $e) {
    error_log($e->getMessage());
    return false;
  }

  return false;
}

/**------------------------------------------------------------------
 * Gets org connection types resource list
------------------------------------------------------------------*/
function get_person_to_organizations_connection_types_list()
{
  $client = wicket_api_client();
  $resource_types = $client->resource_types->all()->toArray();
  $resource_types = collect($resource_types);
  $found = $resource_types->filter(function ($item) {
    return $item->resource_type == 'connection_person_to_organizations';
  });

  return $found;
}


/**------------------------------------------------------------------
 * Gets all individual memberships
 * Documentation: https://wicketapi.docs.apiary.io/#reference/supplemental-resources/membership-tiers/fetch-membership-tiers
------------------------------------------------------------------*/
function get_individual_memberships()
{
  $client = wicket_api_client();
  try {
    $search_organizations = $client->get('memberships');
  } catch (\Exception $e) {
    // echo "<pre>";
    // print_r($e->getMessage());
    // echo "</pre>";
    // die;
  }
  //var_dump($search_organizations);exit;
  return $search_organizations;
}

/**
 * -!-!-!-!-!-!-
 * DEPRECATED - Use wicket_update_schema_by_slug() instead as it supports MDP slugs and more than updating persons.
 * -!-!-!-!-!-!-
 *
 * Enables writing a single AI value for a person based on a single key/value pair.
 *
 * This function updates a person's data field with a specified key/value pair within a schema.
 * To pass a custom payload for multiple value updates, pass null for $key and True for $pass_raw_value,
 * then you can pass your custom payload to $value.
 *
 * Example usage:
 * ```php
 * wicket_update_schema_single_value(wicket_current_person_uuid(), 'membership_mgmt', 'application_status', 'not_submitted');
 * ```
 *
 * @param string  $schema_slug     The schema slug to identify the schema.
 * @param string  $key             The key to update within the schema. Pass null to update multiple values.
 * @param mixed   $value           The value to set for the specified key, or the custom payload if $pass_raw_value is true.
 * @param bool    $pass_raw_value  Set to true to pass a custom payload in $value. Default is false.
 * @param string  $person_uuid     The UUID of the person to update. Default is 0, which means the current user.
 * @return array                   Returns an array with a boolean indicating success, and an error message if failed.
 */
function wicket_update_schema_single_value($schema_slug, $key, $value, $pass_raw_value = false, $person_uuid = 0)
{
  $client = wicket_api_client();
  $schema = wicket_get_schema($schema_slug);
  if ($person_uuid == 0) {
    $wicket_person = wicket_current_person();
    $person_uuid = $wicket_person->id;
  } else {
    $wicket_person = wicket_get_person_by_id($person_uuid);
  }

  if (empty($client) || empty($schema) || empty($wicket_person)) {
    return false;
  }

  $schema_uuid = $schema['id'];
  $schema_values = wicket_get_field_from_data_fields($wicket_person->data_fields, $schema_slug)['value'];
  $sub_payload = array();
  if (!$pass_raw_value) {
    $schema_values[$key] = $value;
    $sub_payload = $schema_values;
  } else {
    $sub_payload = $value;
  }

  // Cleaning up values
  // TODO: Potentially include more cleanup conditions found in wicket_add_data_field(),
  //  or reference it directly
  foreach ($sub_payload as $key => $value) {
    // remove empty arrays (likely select fields with the "choose option" set)
    if (is_array($value) && empty($value)) {
      unset($sub_payload[$key]);
    }
  }

  try {
    $payload = [
      'data' => [
        'type' => 'people',
        'id' => "$person_uuid",
        'attributes' => [
          'data_fields' => [[
            '$schema' => "urn:uuid:$schema_uuid",
            'value' => $sub_payload,
          ]]
        ]
      ]
    ];

    // error_log("people_ai_write_single_value payload");
    // error_log( json_encode($payload, JSON_PRETTY_PRINT) );

    $client->patch("people/$person_uuid", ['json' => $payload]);
    return array(true);
  } catch (Exception $e) {
    // error_log("Error in people_ai_write_single_value - see details:");
    // error_log( print_r( $e->getMessage(), true ) );
    return array(false, $e->getMessage());
  }
}

/**
 * Enables writing a single AI value for a person based on a single key/value pair.
 *
 * Is essentially a v2 of wicket_update_schema_single_value() but uses *actual MDP-supported slugs and not
 * the old fake ones that would get converted into IDs via an API call, and makes some improvements based on that.
 * This updated version also allows updating both person and organization record types.
 *
 * Example usage:
 * ```php
 * wicket_update_schema_by_slug('orgadvocacy', 'fedRiding', "2");
 * wicket_update_schema_by_slug('orginterests', 'interests', ['advocacy'], false, '4b4e4594-70d3-4402-9b33-a528bca82e26', 'org');
 * ```
 *
 * @param string $schema_slug      The MDP slug for that schema.
 * @param string $key              The key to update within the schema's value array. Pass null to update multiple values. using $pass_raw_value.
 * @param mixed  $value            The value to set for the specified key, or the custom payload if $pass_raw_value is true.
 * @param bool   $pass_raw_value   (Optional) Set to true to pass a custom payload in $value. Default is false.
 * @param string $target_uuid      (Optional) UUID of the person or org to update. If not passed, will default to current user.
 * @param string $type             (Optional) Type of record to update. Can be set to 'person' or 'org'.
 *
 * @return array                   Returns an array with a boolean indicating success, and an error message if failed.
 */
function wicket_update_schema_by_slug($schema_slug, $key, $value, $pass_raw_value = false, $target_uuid = '', $type = 'person') {
  $client = wicket_api_client();
  if (empty($target_uuid) && $type == 'person') {
    $wicket_person = wicket_current_person();
    $target_uuid = $wicket_person->id;
  } else if ($type == 'person'){
    $wicket_person = wicket_get_person_by_id($target_uuid);
  } else if ($type == 'org') {
    $wicket_org = wicket_get_organization($target_uuid);
  } else {
    return array(false, 'Please provide all parameters.');
  }

  if (empty($client)) {
    return array(false, 'Could not obtain client.');
  }

  // Set schema values depending on the type of entity we're working with
  $schema_values;
  if($type == 'person') {
    $schema_values = wicket_get_field_from_data_fields($wicket_person->data_fields, $schema_slug)['value'];
  } else if ($type == 'org'){
    $data_fields = $wicket_org['data']['attributes']['data_fields'];
    $schema_values = wicket_get_field_from_data_fields($data_fields, $schema_slug)['value'];
  }

  // --------------------------------------------------------------------
  // Do the setting, cleanup, and API calls, which apply to both entities:
  // --------------------------------------------------------------------

  // Set new value
  $sub_payload = array();
  if (!$pass_raw_value) {
    $schema_values[$key] = $value;
    $sub_payload = $schema_values;
  } else {
    $sub_payload = $value;
  }

  // Cleaning up values
  // TODO: Potentially include more cleanup conditions found in wicket_add_data_field(),
  //  or reference it directly
  foreach ($sub_payload as $key => $value) {
    // remove empty arrays (likely select fields with the "choose option" set)
    if (is_array($value) && empty($value)) {
      unset($sub_payload[$key]);
    }
  }

  // wicket_write_log($schema_values);
  // wicket_write_log($sub_payload);

  // Make the API call
  $api_path = $type == 'org' ? 'organizations' : 'people';
  try {
    $payload = [
      'data' => [
        'type' => "$api_path",
        'id' => "$target_uuid",
        'attributes' => [
          'data_fields' => [[
            'schema_slug' => "$schema_slug",
            'value' => $sub_payload,
          ]]
        ]
      ]
    ];

    // wicket_write_log('wicket_update_schema_by_slug before send');
    // wicket_write_log($payload);

    $output = $client->patch("$api_path/$target_uuid", ['json' => $payload]);
    return array(true, $output);
  } catch (Exception $e) {
    // wicket_write_log("Error in wicket_update_schema_by_slug - see details:");
    // wicket_write_log($e->getMessage());
    return array(false, $e->getMessage());
  }
  return array(false, 'Something went wrong.');
}


// Helper for wicket_update_schema_single_value
function wicket_get_field_from_data_fields($data_fields, $key)
{
  // get matches
  $matches = array_filter($data_fields, function ($field) use ($key) {
    return isset($field['key']) && $field['key'] == $key;
  });

  // return first match
  return reset($matches);
}

// Finds the schema ID using a provided schema slug.
function wicket_get_schema($schema_slug)
{
  $schemas = wicket_get_schemas();
  if ($schemas) {
    $result = array_filter($schemas['data'], function ($schema) use ($schema_slug) {
      return $schema['attributes']['key'] == $schema_slug;
    });
    return reset($result);
  }
}
