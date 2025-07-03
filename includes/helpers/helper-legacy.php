<?php

// No direct access
defined('ABSPATH') || exit;

/*
 * MARK: PLEASE READ...
 * BEFORE EDITING THIS FILE
 *
 * DO NOT ADD ANYTHING BELOW THIS COMMENT
 * DO NOT ADD ANYTHING BELOW THIS COMMENT
 * DO NOT ADD ANYTHING BELOW THIS COMMENT
 *
 * Use the rest of the files (in this same directory) meant to contain helpers, based on their purpose.
 *
 * Be thoughtful and considerate of your fellow developers, please.
 * This will make it easier for everyone, including you, to maintain this work in the future.
 *
 * Thanks!
 */

/**
 * Accepts a Wicket person object, like from wicket_current_person(),
 * and returns a clean array of the specified repeatable contact method.
 *
 * @param Array  $wicket_person_obj Like from wicket_current_person($uuid)
 * @param String $type              E.g. "addresses", "phones", "web_addresses", "emails"
 *
 * @return Array | bool             Array of those contact items if successful, false if not.
 */
function wicket_person_obj_get_repeatable_contact_info($wicket_person_obj, $type, $return_full_arrays = false)
{
    $wicket_person_included = $wicket_person_obj->included()->toArray(); // Converting collection to array
    $contact_items = []; // Will be our array of contact options
    foreach ($wicket_person_included as $elem) {
        if ($elem['type'] !== $type) {
            continue;
        }
        $contact_items[] = $elem;
    }

    if (empty($contact_items)) {
        return false;
    }

    $to_return = [];

    foreach ($contact_items as $contact_item) {
        if ($return_full_arrays) {
            $to_return[] = $contact_item;
        } else {
            $to_return[] = $contact_item['attributes'];
        }
    }

    return $to_return;
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
function wicket_create_wp_user_if_not_exist($uuid, $first_name = null, $last_name = null, $email = null)
{
    if (empty($uuid)) {
        return false;
    }

    $user = get_user_by('login', $uuid);
    if ($user) {
        return $user->id;
    }

    // Grab MDP info if overrides were not provided
    if (is_null($first_name) && is_null($last_name) && is_null($email)) {
        $mdp_person = wicket_get_person_by_id($uuid);
        $first_name = $mdp_person->given_name;
        $last_name = $mdp_person->family_name;
        $email = $mdp_person->primary_email_address;
    }

    // Final check if their WP user exists by email, since trying to create them again with the same email will error anyway
    $user = get_user_by('email', $email);
    if ($user) {
        return $user->id;
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
    // make sure nothing is calling this function with no uuid. This will try to return all mdp orgs, which is no bueno
    if ($uuid == '') {
        return false;
    }

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

/**
 * Get organization by slug from Wicket. Can return the UUID only if needed.
 *
 * @param string $slug The slug of the organization
 * @param bool $return_uuid_only Whether to return the UUID only
 *
 * @return array|string|bool The organization data or UUID if found, or false if not found
 */
function wicket_get_organization_by_slug($slug, $return_uuid_only = false)
{
    $client = wicket_api_client();

    if ($return_uuid_only) {
        $organizations = $client->get("organizations?filter[slug_eq]=$slug&fields[organizations]=id&page[size]=1");
    } else {
        $organizations = $client->get("organizations?filter[slug_eq]=$slug&page[size]=1");
    }
    if ($organizations) {
        if ($return_uuid_only) {
            return $organizations['data'][0]['id'];
        } else {
            return $organizations['data'][0];
        }
    }

    return false;
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
    $org_name_alt = $org_info['data']['attributes']["alternate_name_$lang"] ?? $org_info['data']['attributes']['alternate_name'];
    $org_description = $org_info['data']['attributes']["description_$lang"] ?? $org_info['data']['attributes']['description'];

    if (isset($org_parent_info)) {
        $org_parent_name = $org_parent_info['data']['attributes']["legal_name_$lang"] ?? $org_info['data']['attributes']['legal_name'];
    }

    // Org type (also tidying up the slug for presentation if we like)
    $org_type = '';
    $org_type_pretty = '';
    $org_type_slug = '';
    $org_type_name = '';
    if (!empty($org_info['data']['attributes']['type'])) {
        $org_type_slug = $org_info['data']['attributes']['type'];
        $org_type_name = wicket_get_resource_type_name_by_slug($org_type_slug);
    }

    return [
      'org_id'          => $uuid,
      'org_name'        => $org_name,
      'org_name_alt'    => $org_name_alt,
      'org_description' => $org_description,
      'org_type'        => $org_type,
      'org_type_pretty' => $org_type_pretty,
      'org_type_slug'   => $org_type_slug,
      'org_type_name'   => $org_type_name,
      'org_status'      => $org_info['data']['attributes']['status'] ?? '',
      'org_parent_id'   => $org_parent_id ?? '',
      'org_parent_name' => $org_parent_name ?? '',
    ];
}

/**
 * Gets the name of a resource type by slug
 *
 * @param string $slug The slug of the resource type
 *
 * @return string|false The name of the resource type, or false if not found
 */
function wicket_get_resource_type_name_by_slug(string $slug): string|false
{
    $client = wicket_api_client();
    $resource_types = $client->get('/resource_types');

    if (!isset($resource_types['data']) || !is_array($resource_types['data'])) {
        return false;
    }

    foreach ($resource_types['data'] as $resource_type) {
        if (
            isset($resource_type['attributes']['slug'])
            && $resource_type['attributes']['slug'] === $slug
            && isset($resource_type['attributes']['name'])
        ) {
            return $resource_type['attributes']['name'];
        }
    }

    return false;
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
function wicket_search_organizations($search_term, $search_by = 'org_name', $org_type = null, $autocomplete = false, $lang = 'en')
{
    try {
        $client = wicket_api_client();
    } catch (\Exception $e) {
        return false;
    }

    if ($autocomplete) {
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
            if (isset($result['attributes']['type']) && !is_null($org_type)) {
                $result_type = $result['attributes']['type'];
                if ($result_type != $org_type) {
                    //wicket_write_log('Skipped');
                    // Skip this record if an org type filter was passed to this endpoint
                    // and it doesn't match
                    continue;
                }
            }
            $tmp['name'] = $result['attributes']['legal_name_' . $lang];
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
            'size' => 50,
          ],
        ];

        $args['filter']['keywords']['term'] = $search_term;
        if (!is_null($org_type)) {
            $args['filter']['type'] = $org_type;
        }
        if (!empty($lang)) {
            $args['filter']['keywords']['fields'] = "legal_name_{$lang},alternate_name_{$lang}";
        } else {
            $args['filter']['keywords']['fields'] = 'legal_name,alternate_name';
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


/**
 * For searching person by a term when you don't have a specific UUID, likely to display
 * search results on the front end.
 *
 * @param String $search_term The query term, e.g. 'Rob Ferguson'
 *
 * @return Bool | Array       False if there was a problem, or an array of the results.
 */

function wicket_search_person($search_term)
{
    try {
        $client = wicket_api_client();
    } catch (\Exception $e) {
        return false;
    }

    // --------------------------------------
    // Search using the autocomplete endpoint
    // --------------------------------------

    // Autocomplete is limited to 100 results total.
    $max_results = 100;

    $autocomplete_results = $client->get('/search/autocomplete', [
      'query' => [
        // Autocomplete lookup query, can filter based on name, membership number, email etc.
        'query' => $search_term,
        // Skip side-loading of people for faster request time.
        // 'include' => '',
        'fields' => [
          'people' => 'full_name, primary_email_address'
        ],
        'filter' => [
          // Limit autocomplete results to only people
          'resource_type' => 'people',
        ],
        'page' => [
          'size' => $max_results
        ]
      ]
    ]);

    $return = [];
    foreach ($autocomplete_results['included'] as $result) {
        $tmp['full_name'] = !empty($result['attributes']['full_name']) ? $result['attributes']['full_name'] : '';
        $tmp['primary_email_address'] = !empty($result['attributes']['primary_email_address']) ? $result['attributes']['primary_email_address'] : '';
        $tmp['id'] = $result['id'];
        $return[] = $tmp;
    }
    return $return;
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

    $outgoing_email = apply_filters('wicket_approval_email_from', get_bloginfo('admin_email'));

    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $headers[] = 'From:' . $outgoing_email . '<' . $outgoing_email . '>';

    wp_mail($to, $subject, $body, $headers);
}


/**------------------------------------------------------------------
 * Create basic person record, no password
 ------------------------------------------------------------------*/
function wicket_create_person($given_name, $family_name, $address = '', $password = '', $password_confirmation = '', $job_title = '', $gender = '', $additional_info = [])
{
    $client = wicket_api_client();

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
    if (isset($gender)) {
        $payload['data']['attributes']['gender'] = $gender;
    }
    // add optional additional info
    if (!empty($additional_info)) {
        $payload['data']['attributes']['data_fields'] = $additional_info;
    }

    try {
        $person = $client->post("people", ['json' => $payload]);
        return $person;
    } catch (Exception $e) {
        $errors = json_decode($e->getResponse()->getBody())->errors;
    }
    return ['errors' => $errors];
}

/**
 * Swiss army knife function for updating many profile attributes of a Wicket user.
 * The $fields_to_update array can include as many or as few high-level profile data types
 * as you need to update, for example, attributes and/or addresses, etc.
 *
 *
 * Example of a $fields_to_update array that updates all available Profile aspects:
 *
 * [
 *  'attributes' => [
 *    'family_name' => '',
 *    'given_name'  => '',
 *    'job_function' => '',
 *    'job_level' => '',
 *    'job_title' => '',
 *    'etc attributes ...'
 *  ],
 *  'addresses' => [
 *    [
 *       'uuid' => '',
 *       'type' => '',
 *       'primary' => true,
 *       'mailing' => false,
 *       'city' => '',
 *       'zip_code' => '',
 *       'address1' => '',
 *       'address2' => '',
 *       'state_name' => '',
 *       'country_code' => '',
 *    ],
 *    [
 *      ... other addresses ...
 *    ]
 *  ],
 *  'phones' => [
 *    [
 *       'uuid' => '', // existing phone # uuid
 *       'primary' => true,
 *       'type' => 'business',
 *       'number' => '+15555555555',
 *    ],
 *    [
 *      ... other phones ...
 *    ]
 *  ],
 *  'emails' => [
 *    [
 *       'uuid' => '', // existing email uuid
 *       'primary' => true,
 *       'type' => 'business',
 *       'address' => 'yo@example.com',
 *       'unique' => true // defaults to true
 *    ],
 *    [
 *      ... other emails ...
 *    ]
 *  ],
 *  'web_addresses' => [
 *    [
 *       'uuid' => '', // existing web_address uuid
 *       'type' => 'website',
 *       'address' => 'https://wicket.io',
 *    ],
 *    [
 *      ... other web addresses ...
 *    ]
 *  ],
 * ]
 *
 * @param String $person_uuid
 * @param Array  $fields_to_update
 *
 * @return Array Array with 'success' param that will be true if successful, false if not. If false, 'errors'
 *               param will include a list of errors encountered.
 */
function wicket_update_person($person_uuid, $fields_to_update)
{
    $client = wicket_api_client();
    $wicket_person = wicket_get_person_by_id($person_uuid);

    if (empty($wicket_person)) {
        return [
          'success' => false,
          'error'   => 'Wicket person not found'
        ];
    }
    $wicket_person_array = wicket_convert_obj_to_array($wicket_person);

    $attributes = [];
    if (isset($fields_to_update['attributes'])) {
        // Target specific attributes as the /people/uuid patch endpoint only accepts these
        $attributes = [
          'additional_name' => $wicket_person_array['attributes']['additional_name'],
          'family_name' => $wicket_person_array['attributes']['additional_name'],
          'given_name' => $wicket_person_array['attributes']['additional_name'],
          'honorific_prefix' => $wicket_person_array['attributes']['additional_name'],
          'honorific_suffix' => $wicket_person_array['attributes']['additional_name'],
          'job_function' => $wicket_person_array['attributes']['additional_name'],
          'job_level' => $wicket_person_array['attributes']['additional_name'],
          'job_title' => $wicket_person_array['attributes']['additional_name'],
          'nickname' => $wicket_person_array['attributes']['additional_name'],
          'status' => $wicket_person_array['attributes']['additional_name'],
          'suffix' => $wicket_person_array['attributes']['additional_name'],
        ];
        $attributes = array_merge($attributes, $fields_to_update['attributes']); // Later array will overwrite first one
        $attributes = wicket_filter_null_and_blank($attributes); // sanitize for MDP call
    }

    // -------------
    // Send updates
    // -------------
    $errors = [];
    $person = null;

    // Attributes
    if (!empty($attributes)) {
        $payload = [
          'data' => [
            'id' => $person_uuid,
            'type' => 'people',
            'attributes' => $attributes
          ]
        ];

        try {
            $person = $client->patch("people/$person_uuid", ['json' => $payload]);
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }
    }

    // Repeatable contact types
    if (isset($fields_to_update['addresses'])) {
        $addresses_update = wicket_add_update_person_addresses($person_uuid, $fields_to_update['addresses']);
        if (!$addresses_update['success']) {
            $errors[] = $addresses_update['error'];
        }
    }
    if (isset($fields_to_update['phones'])) {
        $phones_update = wicket_add_update_person_phones($person_uuid, $fields_to_update['phones']);
        if (!$phones_update['success']) {
            $errors[] = $phones_update['error'];
        }
    }
    if (isset($fields_to_update['emails'])) {
        $emails_update = wicket_add_update_person_emails($person_uuid, $fields_to_update['emails']);
        if (!$emails_update['success']) {
            $errors[] = $emails_update['error'];
        }
    }
    if (isset($fields_to_update['web_addresses'])) {
        $web_addresses_update = wicket_add_update_person_web_addresses($person_uuid, $fields_to_update['web_addresses']);
        if (!$web_addresses_update['success']) {
            $errors[] = $web_addresses_update['error'];
        }
    }

    if (empty($errors)) {
        return [
          'success' => true
        ];
    } else {
        return [
          'success' => false,
          'error'   => $errors
        ];
    }
}

/**
 * Function for updating or creating new addresses for a user.
 *
 * Example $addresses array:
 *
 * [
 *    [
 *       'uuid' => '',
 *       'type' => '',
 *       'primary' => true,
 *       'mailing' => false,
 *       'city' => '',
 *       'zip_code' => '',
 *       'address1' => '',
 *       'address2' => '',
 *       'state_name' => '',
 *       'country_code' => '',
 *    ],
 *    [
 *      ... other addresses ...
 *    ]
 *  ]
 *
 */
function wicket_add_update_person_addresses($person_uuid, $addresses)
{
    $client = wicket_api_client();
    $wicket_person = wicket_get_person_by_id($person_uuid);

    $addresses_to_update = [];
    $addresses_to_create = [];
    $errors = [];

    // Get user current address
    $current_addresses = wicket_person_obj_get_repeatable_contact_info($wicket_person, 'addresses', true); // Return full address arrays for writing back to the MDP, instead of the simple address list

    $addresses_update    = wicket_update_addresses($addresses, $current_addresses);
    $addresses_to_update = $addresses_update['updated_addresses'];
    $addresses_to_create = $addresses_update['addresses_not_found'];
    $errors              = $errors;


    // Addresses to create
    if (!empty($addresses_to_create)) {
        foreach ($addresses_to_create as $address) {
            $payload = [
              'data' => [
                'type' => 'addresses',
                'attributes' => [
                  'address1' => $address['address1'] ?? '',
                  'address2' => $address['address2'] ?? '',
                  'city' => $address['city'] ?? '',
                  'company_name' => $address['company_name'] ?? '',
                  'country_code' => $address['country_code'] ?? '',
                  'department' => $address['department'] ?? '',
                  'division' => $address['division'] ?? '',
                  'mailing' => $address['mailing'] ?? false,
                  'primary' => $address['primary'] ?? false,
                  'state_name' => $address['state_name'] ?? '',
                  'type' => $address['type'] ?? '',
                  'zip_code' => $address['zip_code'] ?? '',
                ]
              ]
            ];

            try {
                $address_creation = $client->post("people/$person_uuid/addresses", ['json' => $payload]);
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
    }

    if (empty($errors)) {
        return [
          'success' => true
        ];
    } else {
        return [
          'success' => false,
          'error'   => $errors
        ];
    }
}

function wicket_update_addresses($updated_addresses, $current_addresses)
{
    $client = wicket_api_client();
    $addresses_to_update = [];
    $addresses_not_found = [];
    $errors = [];

    // Loop both sets of addresses to determine if they should be updated or added anew
    foreach ($updated_addresses as $address_to_add_update) {
        $address_exists = false;
        foreach ($current_addresses as $current_address) {
            if (isset($address_to_add_update['uuid'])) {
                if ($current_address['attributes']['uuid'] === $address_to_add_update['uuid']) {
                    $address_exists = true;
                    $updated_address = $current_address;
                    $updated_address['attributes'] = array_merge($updated_address['attributes'], $address_to_add_update); // Later array will overwrite first one
                    $addresses_to_update[] = $updated_address;
                }
            }
        }
        if (!$address_exists) {
            $addresses_not_found[] = $address_to_add_update;
        }
    }

    /**
     * Send updates
     */

    // Addresses to update
    if (!empty($addresses_to_update)) {
        foreach ($addresses_to_update as $address) {
            $payload = $address;
            $address_uuid = $payload['attributes']['uuid'];

            // Unset params that the MDP provides but doesn't want sent back to it
            unset($payload['attributes']['uuid']);
            unset($payload['attributes']['type_external_id']);
            unset($payload['attributes']['formatted_address_label']);
            unset($payload['attributes']['latitude']);
            unset($payload['attributes']['longitude']);
            unset($payload['attributes']['created_at']);
            unset($payload['attributes']['updated_at']);
            unset($payload['attributes']['deleted_at']);
            unset($payload['attributes']['active']);
            unset($payload['attributes']['consent']);
            unset($payload['attributes']['consent_third_party']);
            unset($payload['attributes']['consent_directory']);

            $payload = [
              'data' => $payload
            ];

            try {
                $address_update = $client->patch("addresses/$address_uuid", ['json' => $payload]);
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
    }

    return [
      'updated_addresses' => $addresses_to_update,
      'addresses_not_found' => $addresses_not_found,
      'errors' => $errors
    ];
}

/**
 * Function for updating or creating new phones for a user.
 *
 * Example $phones array:
 *
 * [
 *    [
 *       'uuid' => '', // existing phone # uuid
 *       'primary' => true,
 *       'type' => 'business',
 *       'number' => '+15555555555',
 *    ],
 *    [
 *      ... other phones ...
 *    ]
 *  ]
 *
 */
function wicket_add_update_person_phones($person_uuid, $phones)
{
    $client = wicket_api_client();
    $wicket_person = wicket_get_person_by_id($person_uuid);

    $phones_to_update = [];
    $phones_to_create = [];
    $errors = [];

    // Get user current phone
    $current_phones = wicket_person_obj_get_repeatable_contact_info($wicket_person, 'phones', true); // Return full phone arrays for writing back to the MDP, instead of the simple phone list

    // Loop both sets of phones to determine if they should be updated or added anew
    foreach ($phones as $phone_to_update) {
        $phone_exists = false;
        foreach ($current_phones as $current_phone) {
            if (isset($phone_to_update['uuid'])) {
                if ($current_phone['attributes']['uuid'] === $phone_to_update['uuid']) {
                    $phone_exists = true;
                    $updated_phone = $current_phone;
                    $updated_phone['attributes'] = array_merge($updated_phone['attributes'], $phone_to_update); // Later array will overwrite first one
                    $phones_to_update[] = $updated_phone;
                }
            }
        }
        if (!$phone_exists) {
            $phones_to_create[] = $phone_to_update;
        }
    }

    /**
     * Send updates
     */

    // phones to update
    if (!empty($phones_to_update)) {
        foreach ($phones_to_update as $phone) {
            $payload = $phone;
            $phone_uuid = $payload['attributes']['uuid'];

            // Unset params that the MDP provides but doesn't want sent back to it
            unset($payload['attributes']['uuid']);
            unset($payload['attributes']['type_external_id']);
            unset($payload['attributes']['number_national_format']);
            unset($payload['attributes']['number_international_format']);
            unset($payload['attributes']['extension']);
            unset($payload['attributes']['country_code_number']);
            unset($payload['attributes']['created_at']);
            unset($payload['attributes']['updated_at']);
            unset($payload['attributes']['deleted_at']);
            unset($payload['attributes']['primary_sms']);
            unset($payload['attributes']['consent']);
            unset($payload['attributes']['consent_third_party']);
            unset($payload['attributes']['consent_directory']);

            $payload = [
              'data' => $payload
            ];

            try {
                $phone_update = $client->patch("phones/$phone_uuid", ['json' => $payload]);
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
    }

    // phones to create
    if (!empty($phones_to_create)) {
        foreach ($phones_to_create as $phone) {
            $payload = [
              'data' => [
                'type' => 'phones',
                'attributes' => [
                  'number' => $phone['number'] ?? '',
                  'primary' => $phone['primary'] ?? false,
                  'type' => $phone['type'] ?? '',
                ]
              ]
            ];

            try {
                $phone_creation = $client->post("people/$person_uuid/phones", ['json' => $payload]);
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
    }

    if (empty($errors)) {
        return [
          'success' => true
        ];
    } else {
        return [
          'success' => false,
          'error'   => $errors
        ];
    }
}

/**
 * Function for updating or creating new emails for a user.
 *
 * Example $emails array:
 *
 * [
 *    [
 *       'uuid' => '', // existing email uuid
 *       'primary' => true,
 *       'type' => 'business',
 *       'address' => 'yo@example.com',
 *       'unique' => true // defaults to true
 *    ],
 *    [
 *      ... other emails ...
 *    ]
 *  ]
 *
 */
function wicket_add_update_person_emails($person_uuid, $emails)
{
    $client = wicket_api_client();
    $wicket_person = wicket_get_person_by_id($person_uuid);

    $emails_to_update = [];
    $emails_to_create = [];
    $errors = [];

    // Get user current email
    $current_emails = wicket_person_obj_get_repeatable_contact_info($wicket_person, 'emails', true); // Return full email arrays for writing back to the MDP, instead of the simple email list

    // Loop both sets of emails to determine if they should be updated or added anew
    foreach ($emails as $email_to_update) {
        $email_exists = false;
        foreach ($current_emails as $current_email) {
            if (isset($email_to_update['uuid'])) {
                if ($current_email['attributes']['uuid'] === $email_to_update['uuid']) {
                    $email_exists = true;
                    $updated_email = $current_email;
                    $updated_email['attributes'] = array_merge($updated_email['attributes'], $email_to_update); // Later array will overwrite first one
                    $emails_to_update[] = $updated_email;
                }
            }
        }
        if (!$email_exists) {
            $emails_to_create[] = $email_to_update;
        }
    }

    /**
     * Send updates
     */

    // emails to update
    if (!empty($emails_to_update)) {
        foreach ($emails_to_update as $email) {
            $payload = $email;
            $email_uuid = $payload['attributes']['uuid'];

            // Unset params that the MDP provides but doesn't want sent back to it
            unset($payload['attributes']['uuid']);
            unset($payload['attributes']['type_external_id']);
            unset($payload['attributes']['localpart']);
            unset($payload['attributes']['domain']);
            unset($payload['attributes']['email']);
            unset($payload['attributes']['unique']);
            unset($payload['attributes']['created_at']);
            unset($payload['attributes']['updated_at']);
            unset($payload['attributes']['deleted_at']);
            unset($payload['attributes']['consent']);
            unset($payload['attributes']['consent_third_party']);
            unset($payload['attributes']['consent_directory']);

            $payload = [
              'data' => $payload
            ];

            try {
                $email_update = $client->patch("emails/$email_uuid", ['json' => $payload]);
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
    }

    // emails to create
    if (!empty($emails_to_create)) {
        foreach ($emails_to_create as $email) {
            $payload = [
              'data' => [
                'type' => 'emails',
                'attributes' => [
                  'address' => $email['address'] ?? '',
                  'primary' => $email['primary'] ?? false,
                  'type' => $email['type'] ?? '',
                  'unique' => $email['unique'] ?? true,
                ]
              ]
            ];

            try {
                $email_creation = $client->post("people/$person_uuid/emails", ['json' => $payload]);
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
    }

    if (empty($errors)) {
        return [
          'success' => true
        ];
    } else {
        return [
          'success' => false,
          'error'   => $errors
        ];
    }
}

/**
 * Function for updating or creating new web address for a user.
 *
 * Example $web_addresses array:
 *
 * [
 *    [
 *       'uuid' => '', // existing web_address uuid
 *       'type' => 'website',
 *       'address' => 'https://wicket.io',
 *    ],
 *    [
 *      ... other web addresses ...
 *    ]
 *  ]
 *
 */
function wicket_add_update_person_web_addresses($person_uuid, $web_addresses)
{
    $client = wicket_api_client();
    $wicket_person = wicket_get_person_by_id($person_uuid);

    $web_addresses_to_update = [];
    $web_addresses_to_create = [];
    $errors = [];

    // Get user current web_address
    $current_web_addresses = wicket_person_obj_get_repeatable_contact_info($wicket_person, 'web_addresses', true); // Return full web_address arrays for writing back to the MDP, instead of the simple web_address list

    // Loop both sets of web_addresses to determine if they should be updated or added anew
    foreach ($web_addresses as $web_address_to_update) {
        $web_address_exists = false;
        foreach ($current_web_addresses as $current_web_address) {
            if (isset($web_address_to_update['uuid'])) {
                if ($current_web_address['attributes']['uuid'] === $web_address_to_update['uuid']) {
                    $web_address_exists = true;
                    $updated_web_address = $current_web_address;
                    $updated_web_address['attributes'] = array_merge($updated_web_address['attributes'], $web_address_to_update); // Later array will overwrite first one
                    $web_addresses_to_update[] = $updated_web_address;
                }
            }
        }
        if (!$web_address_exists) {
            $web_addresses_to_create[] = $web_address_to_update;
        }
    }

    /**
     * Send updates
     */

    // web_addresses to update
    if (!empty($web_addresses_to_update)) {
        foreach ($web_addresses_to_update as $web_address) {
            $payload = $web_address;
            $web_address_uuid = $payload['attributes']['uuid'];

            // Unset params that the MDP provides but doesn't want sent back to it
            unset($payload['attributes']['uuid']);
            unset($payload['attributes']['type_external_id']);
            unset($payload['attributes']['data']);
            unset($payload['attributes']['created_at']);
            unset($payload['attributes']['updated_at']);
            unset($payload['attributes']['deleted_at']);
            unset($payload['attributes']['consent']);
            unset($payload['attributes']['consent_third_party']);
            unset($payload['attributes']['consent_directory']);

            $payload = [
              'data' => $payload
            ];

            try {
                $web_address_update = $client->patch("web_addresses/$web_address_uuid", ['json' => $payload]);
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
    }

    // web_addresses to create
    if (!empty($web_addresses_to_create)) {
        foreach ($web_addresses_to_create as $web_address) {
            $payload = [
              'data' => [
                'type' => 'web_addresses',
                'attributes' => [
                  'address' => $web_address['address'] ?? '',
                  'type' => $web_address['type'] ?? '',
                ]
              ]
            ];

            try {
                $web_address_creation = $client->post("people/$person_uuid/web_addresses", ['json' => $payload]);
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
    }

    if (empty($errors)) {
        return [
          'success' => true
        ];
    } else {
        return [
          'success' => false,
          'error'   => $errors
        ];
    }
}

/**
 * Converts and object to a clean array and sanitizes previously protected property keys
 * so they can be normally accessible with no special characters. Helpful for converting
 * Wicket objects from the SDK like the Person object that comes from wicket_get_person_by_id()
 *
 * @param Object $object
 *
 * @return Array Cleaned arrayification of the $object
 */
function wicket_convert_obj_to_array($object)
{
    // Serialize and unserialize to access all properties
    $array = (array) unserialize(serialize($object), ['allowed_classes' => false]);

    $cleanArray = [];
    foreach ($array as $key => $value) {
        // Remove special characters from keys
        $cleanKey = preg_replace('/^\x00(?:\*|[^\x00]+)\x00/', '', $key);
        $cleanArray[$cleanKey] = $value;
    }

    return $cleanArray;
}

/**
 * Little helper function that acts as a version of array_filter()
 * that *doesn't strip out 0 values, which we might actually want for purposes of sending
 * data back to the MDP, for example.
 *
 * @param Array $array
 *
 * @return Array that has had it's null and blank string values removed.
 */
if (!function_exists('wicket_filter_null_and_blank')) {
    function wicket_filter_null_and_blank($array)
    {
        return array_filter($array, static function ($var) {
            return $var !== null && $var !== "";
        });
    }
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
function wicket_assign_organization_membership(
    $person_uuid,
    $org_id,
    $membership_id,
    $starts_at = '',
    $ends_at = '',
    $max_seats = 0,
    $grace_period_days = 0,
    $previous_membership_uuid = '',
    $grant_owner_assignment = false
) {
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

    if (!empty($grant_owner_assignment)) {
        $payload['data']['attributes']['grant_owner_assignment'] = true;
    }

    if (!empty($previous_membership_uuid)) {
        $payload['data']['attributes']['copy_previous_assignments'] = true;
        $payload['data']['relationships']['previous_membership_entry']['data'] = [
          'type' => 'organization_memberships',
          'id' => $previous_membership_uuid
        ];
    }

    try {
        $response = $client->post("organization_memberships", ['json' => $payload]);
    } catch (Exception $e) {
        $response = new \WP_Error('wicket_api_error', $e->getMessage());
    }
    return $response;
}

function change_organization_membership_owner($org_membership_uuid, $person_uuid)
{
    $client = wicket_api_client();

    $payload = [
      'data' => [
        'type' => 'organization_memberships',
        'id' => $org_membership_uuid,
        'relationships' => [
          'owner' => [
            'data' => [
              'type' => 'people',
              'id' => $person_uuid
            ]
          ]
        ]
      ]
    ];

    try {
        $response = $client->patch("/organization_memberships/$org_membership_uuid", ['json' => $payload]);
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
function wicket_assign_individual_membership(
    $person_uuid,
    $membership_uuid,
    $starts_at = '',
    $ends_at = '',
    $grace_period_days = 0,
    $previous_membership_uuid = ''
) {
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

    if (!empty($previous_membership_uuid)) {
        $payload['data']['relationships']['previous_membership_entry']['data'] = [
          'type' => 'person_memberships',
          'id' => $previous_membership_uuid
        ];
    }

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
 * Summary of wicket_delete_person_membership
 * @param string $membership_uuid
 * @return mixed
 */
function wicket_delete_person_membership($membership_uuid)
{
    $client = wicket_api_client();
    try {
        $response = $client->delete("person_memberships/$membership_uuid");
    } catch (Exception $e) {
        $response = new \WP_Error('wicket_api_error', $e->getMessage());
    }
    return $response;
}

/**
 * Summary of wicket_delete_organization_membership
 * NOTE: force_destroy=true will clear all membership assignments
 * @param string $membership_uuid
 * @return mixed
 */
function wicket_delete_organization_membership($membership_uuid)
{
    $client = wicket_api_client();
    try {
        $response = $client->delete("organization_memberships/$membership_uuid?force_detroy=true");
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
 * Gets the person memberships for a specified UUID
 * using the person membership entries endpoint
 ------------------------------------------------------------------*/
function wicket_get_person_memberships($uuid)
{
    $client = wicket_api_client();
    static $memberships = null;
    // prepare and memoize all connections from Wicket
    if (is_null($memberships)) {
        try {
            $memberships = $client->get('people/' . $uuid . '/membership_entries?include=membership,organization_membership.organization,fusebill_subscription');
        } catch (Exception $e) {
            wicket_write_log($e->getMessage());
        }
    }
    if ($memberships) {
        return $memberships;
    }
    return false;
}

/**------------------------------------------------------------------
 * Gets the person memberships for a specified UUID
 * using the person membership entries endpoint
 ------------------------------------------------------------------*/
function wicket_get_person_active_memberships($uuid)
{
    $client = wicket_api_client();
    static $memberships = null;
    // prepare and memoize all connections from Wicket
    if (is_null($memberships)) {
        try {
            $memberships = $client->get('people/' . $uuid . '/membership_entries?include=membership,organization_membership.organization,fusebill_subscription&filter[active_at]=now');
        } catch (Exception $e) {
            wicket_write_log($e->getMessage());
        }
    }
    if ($memberships) {
        return $memberships;
    }
    return false;
}

/**
 * Gets the person memberships for a specified UUID
 * using the person membership entries endpoint
 *
 * @param array $args (Optional) Array of arguments to pass to the API
 *              person_uuid (Optional) The person UUID to search for. If missing, uses current person.
 *              include (Optional) The include parameter to pass to the API. Default: 'membership,organization_membership.organization,fusebill_subscription'.
 *              filter (Optional) The filter parameter to pass to the API. Default: ['active_at' => 'now'].
 *
 * @return array|false Array of memberships on ['data'] or false on failure
 */
function wicket_get_current_person_memberships($args = [])
{
    $defaults = [
        'person_uuid' => wicket_current_person_uuid(),
        'include' => 'membership,organization_membership.organization,fusebill_subscription',
        'filter' => [
            'active_at' => 'now',
        ],
    ];

    $args = wp_parse_args($args, $defaults);

    $client = wicket_api_client();
    $uuid   = $args['person_uuid'];

    static $memberships = null;

    // prepare and memoize all connections from Wicket
    if (is_null($memberships)) {
        try {
            $memberships = $client->get('people/' . $uuid . '/membership_entries?' . http_build_query($args));
        } catch (Exception $e) {
            return false;
        }
    }

    if ($memberships) {
        return $memberships;
    }

    return false;
}

/**
 * Gets the current person's active memberships
 * using the person membership entries endpoint
 *
 * @return array|false Array of active memberships on ['data'] or false on failure
 */
function wicket_get_current_person_active_memberships()
{
    $response = wicket_get_current_person_memberships([
        'filter' => [
            'active_at' => 'now',
        ],
    ]);

    if (is_wp_error($response) || empty($response['data'])) {
        return false;
    }

    return $response;
}

/**
 * Create an organization in Wicket
 *
 * @param string $org_name Organization name
 * @param string $org_type Organization type, see Wicket schema
 * @param array $additional_info (optional) Additional org info, see Wicket schema.
 * @param string $org_parent_id (optional) Parent org id, if applicable
 *
 * @return object | false \Wicket\Api\Response or false on error
 */
function wicket_create_organization($org_name, $org_type, $additional_info = [], $org_parent_id = '')
{
    $client = wicket_api_client();

    // Build org payload
    $payload = [
      'data' => [
        'type' => 'organizations',
        'attributes' => [
          'type'       => $org_type,
          'legal_name' => $org_name,
        ]
      ]
    ];

    if (!empty($additional_info)) {
        $payload['data']['attributes']['data_fields'] = $additional_info;
    }

    if (!empty($additional_info['description'])) {
        unset($payload['data']['attributes']['data_fields']['description']);
        $payload['data']['attributes']['description'] = $additional_info['description'];
    }

    if (!empty($org_parent_id)) {
        $payload['data']['relationships']['parent_organization'] = [
          'data' => [
            'type' => 'organizations',
            'id'   => $org_parent_id
          ]
        ];
    }

    try {
        $org = $client->post("organizations", ['json' => $payload]);

        return $org;
    } catch (Exception $e) {
        return false;
    }

    return false;
}


/**
 * Create organization address
 *
 * $payload is an array of attributes. See how wicket does this via the API/network tab in chrome
 * an example might be the following.
 *
 * Payload example:
 *
 * $payload = [
 *   'data' => [
 *     'type' => 'addresses',
 *     'attributes' => [
 *       'type' => 'work',
 *       'address1' => '123 fake st',
 *       'city' => 'ottawa',
 *       'country_code' => 'CA',
 *       'state_name' => 'ON',
 *       'zip_code' => 'k1z6x6'
 *     ]
 *   ]
 * ];
 *
 * @return bool
 */
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


/**
 * Create organization email
 *
 * $payload is an array of attributes. See how wicket does this via the API/network tab in chrome
 * an example might be the following.
 *
 * Payload example:
 *
 * {"data":{"type":"emails","attributes":{"address":"ernest@test.wicket.io","type":"general"}}}
 */
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

/**
 * Create organization phone
 *
 * $payload is an array of attributes. See how wicket does this via the API/network tab in chrome
 * an example might be the following.
 *
 * Payload example:
 *
 * {"data":{"type":"phones","attributes":{"number":"+12345678901","type":"general"}}}
 */
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


/**
 * Create organization web address
 *
 * $payload is an array of attributes. See how wicket does this via the API/network tab in chrome
 * an example might be the following.
 *
 * Payload example:
 *
 * {"data":{"type":"web_addresses","attributes":{"address":"https://www.google.com","type":"website"}}}
 */
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

/**
 * Creates a connection between a person and an organization.
 *
 * This function establishes a relationship of a specified type between a person and an organization.
 * If the $skip_if_exists parameter is true, it will first check if such a relationship already exists
 * and return the existing connection if found.
 *
 * @param string $person_uuid The UUID of the person.
 * @param string $org_uuid The UUID of the organization.
 * @param string $relationship_type The type of relationship to create.
 * @param bool $skip_if_exists Optional. Whether to skip creating a new connection if one already exists. Default false.
 * @param array $atts Optional. Additional attributes for the connection. Default empty array.
 *
 * @return array|false The connection data if created or found, or false on failure.
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
        $new_connection = wicket_create_connection($payload);
    } catch (\Exception $e) {
        wicket_write_log($e->getMessage());
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
 * Creates a connection between two organizations.
 *
 * This function establishes a relationship of a specified type between two organizations.
 * If the $skip_if_exists parameter is true, it will first check if such a relationship already exists
 * and return the existing connection if found.
 *
 * @param string $from_org_uuid The UUID of the source organization.
 * @param string $to_org_uuid The UUID of the target organization.
 * @param string $relationship_type The type of relationship to create.
 * @param bool $skip_if_exists Optional. Whether to skip creating a new connection if one already exists. Default false.
 * @param array $atts Optional. Additional attributes for the connection. Default empty array.
 *
 * @return array|false The connection data if created or found, or false on failure.
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
      ]
    ];

    try {
        $new_connection = wicket_create_connection($payload);
    } catch (\Exception $e) {
        wicket_write_log($e->getMessage());
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
function wicket_set_connection_start_end_dates($connection_id, $end_date = '', $start_date = '')
{

    if (empty($end_date)) {
        //wicket_write_log('End date is empty');
        return false;
    }

    try {
        $client = wicket_api_client();
    } catch (\Exception $e) {
        error_log($e->getMessage());
        return false;
    }

    try {
        $current_connection_info = wicket_get_connection_by_id($connection_id);

        if (empty($current_connection_info)) {
            //wicket_write_log('Current connection info is empty');
            return false;
        }

        $attributes = $current_connection_info['data']['attributes'];

        // Only if we received a start date, set it
        if (!empty($start_date)) {
            $attributes['starts_at'] = strval($start_date);
        }

        $attributes['ends_at']   = !empty($end_date) ? strval($end_date) : null;

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
          ]
        ];
        // wicket_write_log('payload before send:');
        // wicket_write_log($payload);

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
        wicket_write_log($error_message);
        return false;
    }

    //wicket_write_log('wicket_set_connection_start_end_dates() reached the end of the function without success');
    return false;
}

/**------------------------------------------------------------------
 * Gets array of information from the MDP API by querying a GET
 * on the provided $connection_id
 * ------------------------------------------------------------------*/
function wicket_get_connection_by_id($connection_id)
{
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
function wicket_add_tag_organization($org_uuid, $tags)
{
    try {
        $client = wicket_api_client();
    } catch (\Exception $e) {
        error_log($e->getMessage());
        return false;
    }

    if (!is_array($tags)) {
        $tags = [$tags];
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
function wicket_set_tag_organization($org_uuid, $tags)
{
    try {
        $client = wicket_api_client();
    } catch (\Exception $e) {
        error_log($e->getMessage());
        return false;
    }

    if (!is_array($tags)) {
        $tags = [$tags];
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
function wicket_remove_tag_organization($org_uuid, $tags)
{
    try {
        $client = wicket_api_client();
    } catch (\Exception $e) {
        error_log($e->getMessage());
        return false;
    }

    if (!is_array($tags)) {
        $tags = [$tags];
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
        try {
            $organization_memberships = $client->get("/organizations/$org_id/membership_entries?sort=-ends_at&include=membership");
        } catch (\Exception $e) {
            //wicket_write_log('wicket_get_org_memberships() error:');
            //wicket_write_log($e->getMessage());
            return [];
        }
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

/**
 * Gets organizations resource list
 *
 * @return Collection
 */
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

/**
 * Gets organizations resource list
 *
 * @return Collection
 */
function wicket_get_org_types_list()
{
    $client         = wicket_api_client();
    $resource_types = $client->get('/resource_types');

    // Create an array of every item with resource_type == 'organizations'
    $resource_types_list = [];

    if (isset($resource_types['data']) && is_array($resource_types['data'])) {
        foreach ($resource_types['data'] as $resource_type) {
            if (isset($resource_type['attributes']['resource_type']) && $resource_type['attributes']['resource_type'] === 'organizations') {
                $resource_types_list[] = $resource_type;
            }
        }
    }

    return $resource_types_list;
}

/**
 * Gets organizations based on certain person to org types selected in the base plugin settings
 *
 * @param array $id_array | [user_id => #]  OR [order_id => #] OR if empty array [] will use currently authenticated user
 * @return array|bool
 */
function get_organizations_based_on_certain_types($id_array = [])
{
    if (!empty($person_to_org_types = wicket_get_option('wicket_admin_settings_woo_person_to_org_types'))) {
        // Get the current user's organization relationships of only the types defined in the global setting for person-to-organization relationships
        // Certain applications of this helper may want to boil this down to one ideally, so hence the additional sorting on the query to prefer relationships in this order:
        // - Greatest Relationship End Date
        // - Then, Greatest Relationship Start Date
        // - If neither of those exist, then it just has to go by entry date of the relationships, with the newest relationship being loaded first

        // remove empty "N/A" value from settings if present
        $person_to_org_types = array_filter($person_to_org_types);

        $client = wicket_api_client();
        $current_person_uuid = wicket_current_person_uuid();

        if (!empty($id_array)) {
            if (!empty($id_array['user_id'])) {
                $user = get_user_by('id', $id_array['user_id']);
            } elseif (!empty($id_array['order_id'])) {
                $order = wc_get_order($id_array['order_id']);
                $user = $order->get_user();
            }
            if (!empty($user->user_login)) {
                $current_person_uuid = $user->user_login;
            }
        }

        $types_filter = 'filter[resource_type_slug_in][]=' . implode('&filter[resource_type_slug_in][]=', $person_to_org_types);
        $url = "people/$current_person_uuid/connections?filter[to_type_eq]=Organization&$types_filter&filter[active_true]=true&sort=-ends_at,-starts_at,-created_at";

        try {
            $connections = $client->get($url);
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }

        // boil down list of connections to just an array of id => legal_name of orgs
        if ($connections) {
            foreach ($connections['data'] as $connection) {
                foreach ($connections['included'] as $included) {
                    if ($connection['relationships']['organization']['data']['id'] == $included['id']) {
                        $orgs[$included['id']] = $included['attributes']['legal_name'];
                    }
                }
            }
            return $orgs;
        }
        return false;
    }
    return false;
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

/**
 * Gets all individual memberships
 *
 * @return array
 *
 * @see https://wicketapi.docs.apiary.io/#reference/supplemental-resources/membership-tiers/fetch-membership-tiers
 */
function get_individual_memberships($id = '')
{
    $client = wicket_api_client();
    $path = 'memberships';
    if (!empty($id)) {
        $path = $path . "/$id";
    }
    try {
        $search_organizations = $client->get($path);
    } catch (\Exception $e) {
        // echo "<pre>";
        // print_r($e->getMessage());
        // echo "</pre>";
        // die;
    }

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

        $client->patch("people/$person_uuid", ['json' => $payload]);
        return array(true);
    } catch (Exception $e) {
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
function wicket_update_schema_by_slug($schema_slug, $key, $value, $pass_raw_value = false, $target_uuid = '', $type = 'person')
{
    $client = wicket_api_client();
    if (empty($target_uuid) && $type == 'person') {
        $wicket_person = wicket_current_person();
        $target_uuid = $wicket_person->id;
    } elseif ($type == 'person') {
        $wicket_person = wicket_get_person_by_id($target_uuid);
    } elseif ($type == 'org') {
        $wicket_org = wicket_get_organization($target_uuid);
    } else {
        return array(false, 'Please provide all parameters.');
    }

    if (empty($client)) {
        return array(false, 'Could not obtain client.');
    }

    // Set schema values depending on the type of entity we're working with
    $schema_values;
    if ($type == 'person') {
        $schema_values = wicket_get_field_from_data_fields($wicket_person->data_fields, $schema_slug)['value'];
    } elseif ($type == 'org') {
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

/**
 * Helper function for wicket_update_schema_single_value.
 *
 * @param array $data_fields
 * @param string $key
 *
 * @return array
 */
function wicket_get_field_from_data_fields($data_fields, $key)
{
    // get matches
    $matches = array_filter($data_fields, function ($field) use ($key) {
        return isset($field['key']) && $field['key'] == $key;
    });

    // return first match
    return reset($matches);
}


/**
 * Gets a schema by slug.
 *
 * @param string $schema_slug The schema slug to search for.
 *
 * @return array The schema if found, otherwise an empty array.
 */
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

/**
 * Update organization info.
 *
 * @param string $organization_uuid The UUID of the organization to update.
 * @param array $payload The info to update.
 *
 * {"data":{"type":"organizations","id":"1b86fee3-5ad0-4b81-a891-7dc7084b22bc","meta":{"ancestry_depth":1,"can_manage":true,"can_update":true},"attributes":{"legal_name":"Ernest Corp","type":"subsidiary","description":"Desc Lorem","legal_name_en":"Ernest Corp","description_en":"Desc Lorem"},"relationships":{"parent_organization":{"data":{"type":"organizations","id":"51f22eea-c473-4400-aa51-685ea957a983"}}}}}
 *
 * @return array A tuple where the first element is a boolean indicating success or failure, and the second element is a string with the error message on failure or the response on success.
 */
function wicket_set_organization_info($organization_uuid = '', $payload = [])
{
    if (empty($organization_uuid) || empty($payload)) {
        return array(false, 'Please provide all parameters.');
    }

    $client = wicket_api_client();
    if (empty($client)) {
        return array(false, 'Could not obtain client.');
    }

    try {
        $output = $client->patch("organizations/$organization_uuid", ['json' => $payload]);
        return $output;
    } catch (\Exception $e) {
        return array(false, $e->getMessage());
    }
}

/**
 * Update an org's basic attributes by passing only the delta new attributes.
 *
 * Example of attributes array:
 *
 * [
 *   'description' => 'New',
 *   'description_en' => 'New',
 *   'alternate_name' => 'Alt',
 *   'alternate_name_en' => 'Alt',
 *   'status' => 'some-status',
 *   'type' => 'type',
 * ]
 *
 * @param String $org_uuid
 * @param String $attributes
 *
 * @return Array of boolean 'success' and either 'error' or 'data', depending on success.
 */
function wicket_update_organization_attributes($org_uuid, $attributes)
{
    $client = wicket_api_client();
    if (empty($client)) {
        return array(false, 'Could not obtain client.');
    }

    $attributes = wicket_filter_null_and_blank($attributes); // sanitize for MDP call

    $current_org_info = wicket_get_organization($org_uuid);

    // Unset the data the MDP doesn't want to receive back in attributes
    // NOTE: These may need to be adjusted to accomodate other kinds of updates the MDP likes
    unset($current_org_info['data']['attributes']['uuid']);
    unset($current_org_info['data']['attributes']['slug']);
    unset($current_org_info['data']['attributes']['ancestry']);
    unset($current_org_info['data']['attributes']['duns']);
    unset($current_org_info['data']['attributes']['people_count']);
    unset($current_org_info['data']['attributes']['created_at']);
    unset($current_org_info['data']['attributes']['updated_at']);
    unset($current_org_info['data']['attributes']['deleted_at']);
    unset($current_org_info['data']['attributes']['membership_began_on']);
    unset($current_org_info['data']['attributes']['inheritable_from_parent']);
    unset($current_org_info['data']['attributes']['inherits_from_parent']);
    unset($current_org_info['data']['attributes']['identifying_number']);
    unset($current_org_info['data']['attributes']['data']);
    unset($current_org_info['data']['attributes']['is_primary_organization']);
    unset($current_org_info['data']['attributes']['assignable_role_names']);
    unset($current_org_info['data']['attributes']['type_external_id']);
    unset($current_org_info['data']['attributes']['tags']);
    unset($current_org_info['data']['attributes']['data_fields']);
    unset($current_org_info['data']['relationships']);
    unset($current_org_info['data']['meta']);

    $current_attributes = $current_org_info['data']['attributes'];
    $new_attributes = array_merge($current_attributes, $attributes);

    $new_attributes = wicket_filter_null_and_blank($new_attributes); // sanitize for MDP call

    $payload = [
      'data' => $current_org_info['data']
    ];
    $payload['data']['attributes'] = $new_attributes;

    $org_update = wicket_set_organization_info($org_uuid, $payload);

    if (isset($org_update[0])) {
        if (!$org_update[0]) {
            return [
              'success' => false,
              'error' => $org_update[1]
            ];
        }
    }

    return [
      'success' => true,
      'data' => $org_update
    ];
}

/**
 * Delete address record in MDP
 *
 * @param string $address_uuid The address UUID to delete
 * @return bool True if successful, false if not
 */
function wicket_delete_address_record($address_uuid)
{
    if (empty($address_uuid)) {
        return false;
    }

    $client = wicket_api_client();

    if (empty($client)) {
        return false;
    }

    try {
        $client->delete("/addresses/{$address_uuid}");
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Delete email record in MDP
 *
 * @param string $email_uuid The address UUID to delete
 * @return bool True if successful, false if not
 */
function wicket_delete_email_record($email_uuid)
{
    if (empty($email_uuid)) {
        return false;
    }

    $client = wicket_api_client();

    if (empty($client)) {
        return false;
    }

    try {
        $client->delete("/emails/{$email_uuid}");
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Delete phone record in MDP
 *
 * @param string $phone_uuid The address UUID to delete
 * @return bool True if successful, false if not
 */
function wicket_delete_phones_record($phone_uuid)
{
    if (empty($phone_uuid)) {
        return false;
    }

    $client = wicket_api_client();

    if (empty($client)) {
        return false;
    }

    try {
        $client->delete("/phones/{$phone_uuid}");
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/*
 * MARK: PLEASE READ...
 * BEFORE EDITING THIS FILE
 *
 * DO NOT ADD ANYTHING ABOVE THIS COMMENT
 * DO NOT ADD ANYTHING ABOVE THIS COMMENT
 * DO NOT ADD ANYTHING ABOVE THIS COMMENT
 *
 * Use the rest of the files (in this same directory) meant to contain helpers, based on their purpose.
 *
 * Be thoughtful and considerate of your fellow developers, please.
 * This will make it easier for everyone, including you, to maintain this work in the future.
 *
 * Thanks!
 */
