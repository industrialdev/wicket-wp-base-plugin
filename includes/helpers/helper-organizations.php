<?php

// No direct access
defined('ABSPATH') || exit;

/**
 * Get organization addresses.
 *
 * @param string $org_id Organization ID.
 * @return mixed|false Organization addresses or false on failure.
 */
function wicket_get_organization_addresses($org_id)
{
    $client = wicket_api_client();

    try {
        $org = $client->get("organizations/$org_id/addresses");

        return $org;
    } catch (Exception $e) {
        $errors = json_decode($e->getResponse()->getBody())->errors;
    }

    return false;
}

/**
 * Search organizations and include active membership + seat summary details.
 *
 * Mirrors wicket_search_organizations() while augmenting each result with an
 * `active_membership_seat_summary` array derived from helper-memberships.php.
 *
 * @param string       $search_term Search term, e.g. "My company".
 * @param string       $search_by   Currently unused; kept for parity with the original helper.
 * @param string|array $org_type    Org type slug(s) to filter by.
 * @param bool         $autocomplete Whether to hit the autocomplete endpoint.
 * @param string       $lang         Language code (defaults to 'en').
 *
 * @return bool|array False on failure, or array of results with seat summaries.
 */
function wicket_search_organizations_with_membership_details($search_term, $search_by = 'org_name', $org_type = null, $autocomplete = false, $lang = 'en')
{
    // Leverage the legacy helper for backwards compatibility.
    $base_results = wicket_search_organizations($search_term, $search_by, $org_type, $autocomplete, $lang);

    if ($base_results === false) {
        return false;
    }

    // Autocomplete responses are simple indexed arrays; enhance each record by org id when available.
    foreach ($base_results as $index => $result) {
        $org_id = $result['id'] ?? null;
        if (empty($org_id)) {
            $base_results[$index]['active_membership_seat_summary'] = null;
            continue;
        }

        $org_memberships = $result['org_memberships'] ?? null;
        if (!is_array($org_memberships)) {
            $org_memberships = wicket_get_org_memberships($org_id);
        }
        $seat_summary = wicket_get_active_membership_seat_summary($org_memberships);

        $base_results[$index]['active_membership_seat_summary'] = $seat_summary;
        $base_results[$index]['active_membership'] = $seat_summary['has_active_membership'] ?? ($result['active_membership'] ?? false);
    }

    return $base_results;
}

/**
 * Reduce a list of organization UUIDs to the ones that still exist in the MDP.
 *
 * The MDP search index can hold on to documents for organizations that have since been
 * deleted or merged away, so a search hit is not proof that the record still exists. Those
 * stale hits are selectable in the front end but every follow-up call against them 404s.
 *
 * Existence is confirmed in bulk (one request per chunk of UUIDs) rather than fetching each
 * organization individually. Every failure path fails open and keeps the UUIDs it could not
 * verify, so a hiccup here degrades to the previous behaviour instead of emptying search
 * results.
 *
 * @param array $org_ids Organization UUIDs to verify.
 *
 * @return array The subset of $org_ids that still resolve to an organization, original order kept.
 */
function wicket_filter_existing_organization_ids(array $org_ids): array
{
    $org_ids = array_values(array_unique(array_filter($org_ids)));

    if (empty($org_ids)) {
        return [];
    }

    $client = wicket_api_client();

    if (!$client) {
        // Fail open: cannot verify, keep all hits.
        return $org_ids;
    }

    $existing = [];

    foreach (array_chunk($org_ids, 50) as $chunk) {
        $query = http_build_query([
            'fields' => ['organizations' => 'legal_name'],
            'page'   => ['size' => count($chunk)],
            'filter' => ['uuid_in' => $chunk],
        ]);
        // Ruby doesn't like the numeric keys PHP adds to array params, e.g. filter[uuid_in][0].
        $query = preg_replace('/\%5B\d+\%5D/', '%5B%5D', $query);

        try {
            $response = $client->get('organizations?' . $query);
        } catch (Exception $e) {
            Wicket()->log()->warning(
                'wicket_filter_existing_organization_ids lookup failed, keeping unverified results: ' . $e->getMessage(),
                ['source' => 'wicket-base']
            );

            // Fail open for this chunk.
            $existing = array_merge($existing, $chunk);
            continue;
        }

        foreach ($response['data'] ?? [] as $organization) {
            if (!empty($organization['id'])) {
                $existing[$organization['id']] = $organization['id'];
            }
        }
    }

    $existing = array_flip(array_values($existing));

    return array_values(array_filter($org_ids, function ($org_id) use ($existing) {
        return isset($existing[$org_id]);
    }));
}


/**
 * Get all organizations from the MDP API.
 *
 * @return array|null The organizations response array or null on failure.
 */
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

/**
 * Get an organization by UUID from the MDP API.
 *
 * @param string $uuid The organization UUID.
 * @param string|null $include Optional. Related resources to include.
 * @return array|false|null The organization response array, false if missing/error, or null.
 */
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
    try {
        $organization = $client->get('organizations/' . $uuid . $query_string);
    } catch (GuzzleHttp\Exception\ClientException $e) {
        // Gracefully handle missing organizations (e.g., stale UUIDs)
        if (apply_filters('wicket_log_missing_organization_lookup', true, $uuid, $e)) {
            Wicket()->log()->warning('wicket_get_organization 404 for UUID ' . $uuid . ': ' . $e->getMessage(), ['source' => 'wicket-base']);
        }

        return false;
    }

    if ($organization) {
        return $organization;
    }
}

/**
 * Get organization by slug from the MDP API.
 *
 * @param string $slug The slug of the organization.
 * @param bool $return_uuid_only Optional. Return only the UUID when true. Default false.
 * @return array|string|false The organization data array, UUID string, or false if not found.
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

/**
 * Get commonly-needed organization info by UUID from the MDP API.
 *
 * Retrieves localized legal name, alternate name, description, and parent organization info.
 *
 * @param string $uuid The organization UUID.
 * @param string $lang Optional. Language code. Defaults to current language.
 * @return array Array of formatted organization details.
 */
function wicket_get_organization_basic_info($uuid, $lang = '')
{
    $org_info = wicket_get_organization($uuid);

    if (empty($lang)) {
        $lang = wicket_get_current_language();
    }

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
        $org_type = $org_info['data']['attributes']['type'];
        $org_type_name = wicket_get_resource_type_name_by_slug($org_type_slug);
    }

    $return = [
        'org_id'            => $uuid,
        'org_name'          => $org_name,
        'org_name_alt'      => $org_name_alt,
        'org_description'   => $org_description,
        'org_type'          => $org_type, // Some solutions like orgs select still need this to be defined as 'org_type'
        'org_type_pretty'   => $org_type_pretty,
        'org_type_slug'     => $org_type_slug,
        'org_type_name'     => $org_type_name,
        'org_status'        => $org_info['data']['attributes']['status'] ?? '',
        'org_parent_id'     => $org_parent_id ?? '',
        'org_parent_name'   => $org_parent_name ?? '',
    ];

    return $return;
}

/**
 * For searching organizations by a term when you don't have a specific UUID, likely to display
 * search results on the front end.
 *
 * @param string $search_term The query term, e.g. 'My company'
 * @param string $search_by   Currently not used, but can be expanded in the future if we want to
 *                            differentiate between searching by org name verses some other attribute
 * @param string|array $org_type    The org type slug you want to filter results down to. Note that autocomplete will
 *                            filter post-search and full will filter pre-search, as it has that option available.
 * @param bool $autocomplete  Whether or not to use the autocomplete API or the search API.
 * @param string $lang        Language code to utilize, defaults to 'en'. Not fully implemented, especially in full search.
 * @param bool   $include_memberships Whether to include membership data and active membership status in results.
 *
 * @return bool | array       False if there was a problem, or an array of the results. The fewer terms suppplied by the autocomplete
 *                            endpoint should also be available in the response from the full search, for consistency in usage of the
 *                            function (e.g. both have id, name, and type parameters returned).
 */
function wicket_search_organizations($search_term, $search_by = 'org_name', $org_type = null, $autocomplete = false, $lang = 'en', $include_memberships = true)
{
    try {
        $client = wicket_api_client();
    } catch (Exception $e) {
        return false;
    }

    if ($autocomplete) {
        // --------------------------------------
        // Search using the autocomplete endpoint
        // --------------------------------------

        // Autocomplete is limited to 100 results total.
        $max_results = 100; // TODO: Handle edge case where there are more than 100 results and
        // we need to filter by a specific org type, thus they wouldn't all show

        $cache_key = 'wicket_search_orgs_ac_' . md5(
            $search_term . '|'
            . (is_array($org_type) ? implode(',', $org_type) : (string) $org_type) . '|'
            . $lang
        );
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        try {
            $autocomplete_results = $client->get('/search/autocomplete', [
                'query' => [
                    // Autocomplete lookup query, can filter based on name, membership number, email etc.
                    'query' => $search_term,
                    // Skip side-loading of people for faster request time.
                    // 'include' => '',
                    'fields' => [
                        'organizations' => 'legal_name_en,legal_name_fr,type',
                    ],
                    'filter' => [
                        // Limit autocomplete results to only organization resources
                        'resource_type' => 'organizations',
                    ],
                    'page' => [
                        'size' => $max_results,
                    ],
                ],
            ]);
        } catch (Exception $e) {
            return false;
        }

        $return = [];
        $temp_org_type = is_array($org_type) ? $org_type : [$org_type]; // make sure it's an array for easier checking
        foreach ($autocomplete_results['included'] as $result) {
            $tmp = [];
            if (isset($result['attributes']['type']) && !is_null($org_type)) {
                $result_type = $result['attributes']['type'];
                if (!in_array($result_type, $temp_org_type)) {
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

        // Drop hits for organizations the search index still knows about but that have since
        // been deleted or merged away in the MDP.
        $existing_ids = array_flip(wicket_filter_existing_organization_ids(array_column($return, 'id')));
        $return = array_values(array_filter($return, function ($result) use ($existing_ids) {
            return isset($existing_ids[$result['id']]);
        }));

        set_transient($cache_key, $return, 10 * MINUTE_IN_SECONDS);

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
        } catch (Exception $e) {
            //wp_send_json_error( $e->getMessage() );
            return false;
        }

        // Map Org types with labels nicely.
        $org_types = wicket_get_resource_types('organizations');
        $org_types_mapped = [];

        if ($org_types !== false) {
            foreach ($org_types['data'] as $item) {
                $slug = $item['attributes']['slug'] ?? null;
                if (!$slug) {
                    continue;
                }

                $org_types_mapped[$slug] = [
                    'name'    => $item['attributes']['name'] ?? null,
                    'name_en' => $item['attributes']['name_en'] ?? null,
                    'name_fr' => $item['attributes']['name_fr'] ?? null,
                    'name_es' => $item['attributes']['name_es'] ?? null,
                ];
            }
        }

        $results = [];

        if ($search_organizations['meta']['page']['total_items'] > 0) {
            // Drop hits for organizations the search index still knows about but that have since
            // been deleted or merged away in the MDP. Done before the loop below so we don't spend
            // a membership lookup on records that are about to be discarded.
            $result_ids = array_column($search_organizations['data'], 'id');
            $existing_ids = array_flip(wicket_filter_existing_organization_ids($result_ids));

            foreach ($search_organizations['data'] as $result) {
                if (!isset($existing_ids[$result['id']])) {
                    continue;
                }

                $address1 = '';
                $city = '';
                $zip_code = '';
                $state_name = '';
                $country_code = '';
                $web_address = '';
                $org_memberships = '';
                $tel = '';

                // Get Primary Address
                foreach ($result['attributes']['organization']['addresses'] as $addresses) {
                    if ($addresses['primary'] == 1) {
                        $address1 = (isset($addresses['address1'])) ? $addresses['address1'] : '';
                        $city = (isset($addresses['city'])) ? $addresses['city'] : '';
                        $zip_code = (isset($addresses['zip_code'])) ? $addresses['zip_code'] : '';
                        $state_name = (isset($addresses['state_name'])) ? $addresses['state_name'] : '';
                        $country_code = (isset($addresses['country_code'])) ? $addresses['country_code'] : '';
                    }
                }

                // Get Primary Phone Number
                foreach ($result['attributes']['organization']['phones'] as $phone) {
                    if ($phone['primary'] == 1) {
                        $tel = $phone['number'];
                    }
                }

                // Get org website
                foreach ($result['attributes']['organization']['web_addresses'] as $web_addresses) {
                    if ($web_addresses['type'] == 'website') {
                        $web_address = $web_addresses['address'];
                    }
                }

                $org_memberships = '';
                $has_active_membership = false;
                if ($include_memberships) {
                    // Get org memberships
                    $org_memberships = wicket_get_org_memberships($result['id']);

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
                }

                $results[$result['id']]['id'] = $result['id'];
                $results[$result['id']]['name'] = $result['attributes']['organization']['legal_name'];
                $results[$result['id']]['type'] = $result['attributes']['organization']['type'];
                $results[$result['id']]['type_name'] = $org_types_mapped[$result['attributes']['organization']['type']]['name'] ?? '';
                $results[$result['id']]['address1'] = $address1;
                $results[$result['id']]['city'] = $city;
                $results[$result['id']]['zip_code'] = $zip_code;
                $results[$result['id']]['state_name'] = $state_name;
                $results[$result['id']]['country_code'] = $country_code;
                $results[$result['id']]['web_address'] = $web_address;
                $results[$result['id']]['phone'] = $tel;
                if ($include_memberships) {
                    $results[$result['id']]['org_memberships'] = $org_memberships;
                    $results[$result['id']]['active_membership'] = $has_active_membership;
                }
            }
        }

        return $results;
    }
}

/**
 * Create an organization in Wicket.
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
            ],
        ],
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
                'id'   => $org_parent_id,
            ],
        ];
    }

    try {
        $org = $client->post('organizations', ['json' => $payload]);

        return $org;
    } catch (Exception $e) {
        return false;
    }

    return false;
}

/**
 * Create organization address.
 *
 * @param string $org_id Organization UUID.
 * @param array $payload Address payload array.
 * @return bool True on success, false on error.
 */
function wicket_create_organization_address($org_id, $payload)
{
    $client = wicket_api_client();

    try {
        $org = $client->post("organizations/$org_id/addresses", ['json' => $payload]);

        return true;
    } catch (Exception $e) {
        $errors = json_decode($e->getResponse()->getBody())->errors;
    }

    return false;
}

/**
 * Create organization email.
 *
 * @param string $org_id Organization UUID.
 * @param array $payload Email payload array.
 * @return bool True on success, false on error.
 */
function wicket_create_organization_email($org_id, $payload)
{
    $client = wicket_api_client();

    try {
        $org = $client->post("organizations/$org_id/emails", ['json' => $payload]);

        return true;
    } catch (Exception $e) {
        $errors = json_decode($e->getResponse()->getBody())->errors;
    }

    return false;
}

/**
 * Create organization phone.
 *
 * @param string $org_id Organization UUID.
 * @param array $payload Phone payload array.
 * @return bool True on success, false on error.
 */
function wicket_create_organization_phone($org_id, $payload)
{
    $client = wicket_api_client();

    try {
        $org = $client->post("organizations/$org_id/phones", ['json' => $payload]);

        return true;
    } catch (Exception $e) {
        $errors = json_decode($e->getResponse()->getBody())->errors;
    }

    return false;
}

/**
 * Create organization web address.
 *
 * @param string $org_id Organization UUID.
 * @param array $payload Web address payload array.
 * @return bool True on success, false on error.
 */
function wicket_create_organization_web_address($org_id, $payload)
{
    $client = wicket_api_client();

    try {
        $org = $client->post("organizations/$org_id/web_addresses", ['json' => $payload]);

        return true;
    } catch (Exception $e) {
        $errors = json_decode($e->getResponse()->getBody())->errors;
    }

    return false;
}

/**
 * Add one or more tags to an organization.
 *
 * @param string $org_uuid The organization UUID.
 * @param string|array $tags Single tag or array of tag strings.
 * @return array|false API response payload or false on failure.
 */
function wicket_add_tag_organization($org_uuid, $tags)
{
    try {
        $client = wicket_api_client();
    } catch (Exception $e) {
        Wicket()->log()->error($e->getMessage(), ['source' => 'wicket-base']);

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
                'tags' => $tags,
            ],
        ],
    ];

    try {
        return $client->patch("organizations/$org_uuid", ['json' => $payload]);
    } catch (Exception $e) {
        Wicket()->log()->error($e->getMessage(), ['source' => 'wicket-base']);

        return false;
    }
}

/**
 * Overwrite tags for an organization.
 *
 * @param string $org_uuid The organization UUID.
 * @param string|array $tags Single tag string or array of tag strings.
 * @return array|false API response payload or false on failure.
 */
function wicket_set_tag_organization($org_uuid, $tags)
{
    try {
        $client = wicket_api_client();
    } catch (Exception $e) {
        Wicket()->log()->error($e->getMessage(), ['source' => 'wicket-base']);

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
                'tags' => $tags,
            ],
        ],
    ];

    try {
        return $client->patch("organizations/$org_uuid", ['json' => $payload]);
    } catch (Exception $e) {
        Wicket()->log()->error($e->getMessage(), ['source' => 'wicket-base']);

        return false;
    }
}

/**
 * Remove one or more tags from an organization.
 *
 * @param string $org_uuid The organization UUID.
 * @param string|array $tags Single tag string or array of tag strings to remove.
 * @return array|false API response payload or false on failure.
 */
function wicket_remove_tag_organization($org_uuid, $tags)
{
    try {
        $client = wicket_api_client();
    } catch (Exception $e) {
        Wicket()->log()->error($e->getMessage(), ['source' => 'wicket-base']);

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
                'tags' => $tags,
            ],
        ],
    ];

    try {
        $result = $client->patch("organizations/$org_uuid", ['json' => $payload]);

        return $result;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get organizations resource list from the MDP API.
 *
 * @return \Illuminate\Support\Collection Collection of organization resource types.
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
 * Get organizations resource list array from the MDP API.
 *
 * @return array Array of organization resource type objects.
 */
function wicket_get_org_types_list()
{
    $client = wicket_api_client();
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
 * Get organizations based on person-to-organization types selected in settings.
 *
 * @param array $id_array Optional array: ['user_id' => int] or ['order_id' => int]. Defaults to current user.
 * @return array|false Associative array of [org_id => legal_name], or false if none found.
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
        } catch (Exception $e) {
            Wicket()->log()->error($e->getMessage(), ['source' => 'wicket-base']);
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

/**
 * Update organization info in the MDP API.
 *
 * @param string $organization_uuid The UUID of the organization to update.
 * @param array $payload The JSON:API payload to update.
 * @return array A tuple where first element is boolean indicating success, and second is error message or response array.
 */
function wicket_set_organization_info($organization_uuid = '', $payload = [])
{
    if (empty($organization_uuid) || empty($payload)) {
        return [false, 'Please provide all parameters.'];
    }

    $client = wicket_api_client();
    if (empty($client)) {
        return [false, 'Could not obtain client.'];
    }

    try {
        $output = $client->patch("organizations/$organization_uuid", ['json' => $payload]);

        return $output;
    } catch (Exception $e) {
        return [false, $e->getMessage()];
    }
}

/**
 * Update organization basic attributes by merging new delta attributes.
 *
 * @param string $org_uuid The organization UUID.
 * @param array $attributes Associative array of attributes to merge and update.
 * @return array Array with boolean 'success' and either 'data' or 'error'.
 */
function wicket_update_organization_attributes($org_uuid, $attributes)
{
    $client = wicket_api_client();
    if (empty($client)) {
        return [false, 'Could not obtain client.'];
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
        'data' => $current_org_info['data'],
    ];
    $payload['data']['attributes'] = $new_attributes;

    $org_update = wicket_set_organization_info($org_uuid, $payload);

    if (isset($org_update[0])) {
        if (!$org_update[0]) {
            return [
                'success' => false,
                'error' => $org_update[1],
            ];
        }
    }

    return [
        'success' => true,
        'data' => $org_update,
    ];
}
