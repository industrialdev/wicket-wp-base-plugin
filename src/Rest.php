<?php

declare(strict_types=1);

namespace WicketWP;

// No direct access
defined('ABSPATH') || exit;

/**
 * Class Rest
 * Instance-based encapsulation of REST routes and handlers for the Wicket plugin.
 */
class Rest
{
    /** @var Main|null */
    private $main;

    public function __construct(?Main $main = null)
    {
        $this->main = $main;
    }

    public function init(): void
    {
        add_action('rest_api_init', [$this, 'register_routes'], 8, 1);
    }

    public function register_routes(): void
    {
        $map = [
            ['search-orgs', 'search_orgs'],
            ['search-groups', 'search_groups'],
            ['terminate-relationship', 'terminate_relationship'],
            ['create-relationship', 'create_or_update_relationship'],
            ['organization-parent', 'organization_parent'],
            ['create-org', 'create_org'],
            ['flag-for-rm-access', 'flag_for_rm_access'],
            ['flag-for-org-editor-access', 'flag_for_org_editor_access'],
            ['grant-org-editor', 'grant_org_editor'],
            ['wicket-component-do-action', 'component_do_action'],
        ];

        foreach ($map as [$route, $handler]) {
            register_rest_route('wicket-base/v1', $route, [
                'methods'  => 'POST',
                'callback' => [$this, $handler],
                'permission_callback' => function () {
                    return is_user_logged_in();
                },
            ]);
        }
    }

    // --- Handlers (converted from procedural functions) ---

    public function search_orgs(\WP_REST_Request $request)
    {
        $params = $request->get_json_params();

        if (!isset($params['searchTerm'])) {
            wp_send_json_error('Search term not provided');
        }

        $lang = $params['lang'] ?? 'en';

        if (isset($params['autocomplete']) && $params['autocomplete']) {
            $return = wicket_search_organizations($params['searchTerm'], 'org_name', $params['orgType'], true, $lang);
            if (gettype($return) == 'boolean' && !$return) {
                wp_send_json_error('There was a problem searching orgs.');
            }
            wp_send_json_success($return);
        }

        $return = wicket_search_organizations($params['searchTerm'], 'org_name', $params['orgType'], false, $lang);
        if (gettype($return) == 'boolean' && !$return) {
            wp_send_json_error('There was a problem searching orgs.');
        }

        wp_send_json_success($return);
    }

    public function search_groups(\WP_REST_Request $request)
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
        $lang = $params['lang'];

        $args = [
            'sort' => 'name',
            'page_size' => 100,
            'page_number' => 1,
        ];

        $args['filter']['name_' . $lang . '_cont'] = $search_term;

        $args = preg_replace('/\%5B\d+\%5D/', '%5B%5D', http_build_query($args));

        try {
            $search_groups = $client->get('groups?' . $args);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }

        $results = [];

        if (!empty($search_groups['meta']['page']['total_items'])) {
            foreach ($search_groups['data'] as $result) {
                $results[$result['id']]['id'] = $result['id'];
                if (isset($result['attributes']["name_$lang"])) {
                    $results[$result['id']]['name'] = $result['attributes']["name_$lang"];
                } else {
                    $results[$result['id']]['name'] = $result['attributes']['name'];
                }
            }
        }

        wp_send_json_success($results);
    }

    public function terminate_relationship(\WP_REST_Request $request)
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
            }

            wp_send_json_error('Something went wrong removing the connection.');
        }

        $set_end_date = wicket_set_connection_start_end_dates($connectionId, date('Y-m-d'));
        if ($set_end_date) {
            wp_send_json_success();
        }

        wp_send_json_error('Something wrong setting the end date of the connection.');
    }

    public function create_or_update_relationship(\WP_REST_Request $request)
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

        $fromUuid = $params['fromUuid'];
        $toUuid = $params['toUuid'];
        $relationshipType = $params['relationshipType'];
        $userRoleInRelationship = $params['userRoleInRelationship'];
        $description = $params['description'] ?? null;
        $userRoleInRelationshipArray = explode(',', $userRoleInRelationship);

        $personProfile = wicket_current_person();

        $jobTitle = null;
        if (is_object($personProfile)) {
            $jobTitle = $personProfile->job_title ?? null;
        } elseif (is_array($personProfile)) {
            $jobTitle = $personProfile['job_title']
                ?? ($personProfile['data']['attributes']['job_title'] ?? ($personProfile['attributes']['job_title'] ?? null));
        }

        if (empty($jobTitle) && !empty($fromUuid)) {
            $fallbackProfile = wicket_get_person_profile($fromUuid);

            if (is_object($fallbackProfile)) {
                $jobTitle = $fallbackProfile->job_title ?? null;
            } elseif (is_array($fallbackProfile)) {
                $jobTitle = $fallbackProfile['job_title']
                    ?? ($fallbackProfile['data']['attributes']['job_title'] ?? ($fallbackProfile['attributes']['job_title'] ?? null));
            }
        }

        if ($description === '' || $description === null) {
            $description = !empty($jobTitle) ? (string) $jobTitle : null;
        }

        if ($description === '') {
            $description = null;
        }

        $return = [];

        foreach ($userRoleInRelationshipArray as $userRoleInRelationship) {
            $roleSlug = trim($userRoleInRelationship);

            $existing_match = wicket_find_person_org_connection($fromUuid, $toUuid, $relationshipType, $roleSlug, true);

            $payload = [
                'data' => [
                    'type' => 'connections',
                    'attributes' => [
                        'connection_type'   => $relationshipType,
                        'type'              => $roleSlug,
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
                ],
            ];

            if ($existing_match) {
                $connection_id = $existing_match['id'] ?? '';
                if ($connection_id === '') {
                    wp_send_json_error('Unable to determine existing connection ID for update.');
                }

                $updated = wicket_update_connection_attributes($connection_id, [
                    'description' => $description,
                    'ends_at' => null,
                ]);

                if ($updated === false) {
                    wp_send_json_error('Something went wrong updating the existing connection.');
                }

                $new_connection = $updated;
            } else {
                try {
                    $new_connection = wicket_create_connection($payload);
                } catch (\Exception $e) {
                    wp_send_json_error($e->getMessage());
                }
            }

            $new_connection_id = '';
            if (isset($new_connection['data'])) {
                if (isset($new_connection['data']['id'])) {
                    $new_connection_id = $new_connection['data']['id'];
                }
            }

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

            $return[] = [
                'connection_id'     => $new_connection['data']['id'] ?? '',
                'connection_type'   => $relationshipType,
                'relationship_type' => $roleSlug,
                'starts_at'         => $new_connection['data']['attributes']['starts_at'] ?? '',
                'ends_at'           => $new_connection['data']['attributes']['ends_at'] ?? '',
                'tags'              => $new_connection['data']['attributes']['tags'] ?? '',
                'active_membership' => $has_active_membership,
                'active_connection' => $new_connection['data']['attributes']['active'],
                'org_id'            => $toUuid,
                'org_name'          => $org_info['org_name'],
                'org_description'   => $org_info['org_description'],
                'org_type'          => $org_info['org_type'],
                'org_status'        => $org_info['org_status'],
                'org_parent_id'     => $org_info['org_parent_id'],
                'org_parent_name'   => $org_info['org_parent_name'],
                'person_id'         => $fromUuid,
            ];
        }

        wp_send_json_success($return);
    }

    public function create_org(\WP_REST_Request $request)
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
            $org_name_lowercase = trim(strtolower($org_name));

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
        }

        $create_org_call = wicket_create_organization($org_name, $org_type);

        if (isset($create_org_call) && !empty($create_org_call)) {
            wp_send_json_success($create_org_call);
        } else {
            wp_send_json_error('Something went wrong creating the organization');
        }
    }

    public function flag_for_rm_access(\WP_REST_Request $request)
    {
        $params = $request->get_json_params();

        if (!isset($params['orgUuid'])) {
            wp_send_json_error('Organization uuid not provided');
        }

        $org_uuid = $params['orgUuid'];

        update_user_meta(get_current_user_id(), 'wicket_roster_man_org_to_grant', $org_uuid);

        wp_send_json_success();
    }

    public function flag_for_org_editor_access(\WP_REST_Request $request)
    {
        $params = $request->get_json_params();

        if (!isset($params['orgUuid'])) {
            wp_send_json_error('Organization uuid not provided');
        }

        $org_uuid = $params['orgUuid'];

        update_user_meta(get_current_user_id(), 'wicket_org_editor_org_to_grant', $org_uuid);

        wp_send_json_success();
    }

    public function grant_org_editor(\WP_REST_Request $request)
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

    public function organization_parent(\WP_REST_Request $request)
    {
        $params = $request->get_json_params();

        if (!isset($params['fromUuid'])) {
            wp_send_json_error('fromUuid not provided');
        }
        if (!isset($params['toUuid'])) {
            wp_send_json_error('toUuid not provided');
        }

        $fromUuid = $params['fromUuid'];
        $toUuid = $params['toUuid'];

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
            ],
        ];

        try {
            $response = $client->patch('organizations/' . $toUuid, [
                'json' => $payload,
            ]);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }

        wp_send_json_success($response);
    }

    public function component_do_action(\WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        $action_name = $params['action_name'] ?? 'generic';
        $action_data = $params['action_data'] ?? [];

        do_action('wicket_component_' . $action_name, $action_data);

        wp_send_json_success();
    }
}
