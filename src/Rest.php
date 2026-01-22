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
            ['orgss-notify-owner', 'orgss_notify_owner'],
            ['orgss-notify-owner-roster-added', 'orgss_notify_owner_roster_added'],
            ['orgss-seat-summary', 'orgss_seat_summary'],
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
        $include_membership_summary = !isset($params['includeMembershipSummary']) || (bool) $params['includeMembershipSummary'];
        $include_location = !isset($params['includeLocation']) || (bool) $params['includeLocation'];

        if (isset($params['autocomplete']) && $params['autocomplete']) {
            if ($include_membership_summary) {
                $return = wicket_search_organizations_with_membership_details($params['searchTerm'], 'org_name', $params['orgType'], true, $lang);
            } else {
                $return = wicket_search_organizations($params['searchTerm'], 'org_name', $params['orgType'], true, $lang, false);
            }
            if (gettype($return) == 'boolean' && !$return) {
                wp_send_json_error('There was a problem searching orgs.');
            }
            wp_send_json_success($return);
        }

        if ($include_membership_summary) {
            $return = wicket_search_organizations_with_membership_details($params['searchTerm'], 'org_name', $params['orgType'], false, $lang);
        } else {
            $return = wicket_search_organizations($params['searchTerm'], 'org_name', $params['orgType'], false, $lang, false);
            $minimal = [];
            foreach ($return as $id => $result) {
                $minimal[$id] = [
                    'id' => $result['id'] ?? $id,
                    'name' => $result['name'] ?? '',
                    'type' => $result['type'] ?? '',
                    'type_name' => $result['type_name'] ?? '',
                ];
                if ($include_location) {
                    $minimal[$id]['address1'] = $result['address1'] ?? '';
                    $minimal[$id]['city'] = $result['city'] ?? '';
                    $minimal[$id]['zip_code'] = $result['zip_code'] ?? '';
                    $minimal[$id]['state_name'] = $result['state_name'] ?? '';
                    $minimal[$id]['country_code'] = $result['country_code'] ?? '';
                }
            }
            $return = $minimal;
        }
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

        $end_connection = wicket_end_connection($connectionId);
        if ($end_connection) {
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

    public function orgss_seat_summary(\WP_REST_Request $request)
    {
        $params = $request->get_json_params();

        if (!isset($params['orgUuid'])) {
            wp_send_json_error('Organization uuid not provided');
        }

        $org_uuid = sanitize_text_field($params['orgUuid']);

        $org_memberships = wicket_get_org_memberships($org_uuid);
        $seat_summary = function_exists('wicket_get_active_membership_seat_summary')
            ? wicket_get_active_membership_seat_summary($org_memberships)
            : [
                'has_active_membership' => false,
                'assigned'              => null,
                'max'                   => null,
                'unlimited'             => false,
                'has_available_seats'   => null,
            ];

        $active_membership = $seat_summary['has_active_membership'] ?? false;

        wp_send_json_success([
            'seat_summary' => $seat_summary,
            'active_membership' => $active_membership,
        ]);
    }

    public function orgss_notify_owner(\WP_REST_Request $request)
    {
        $logger = wc_get_logger();
        $params = $request->get_json_params();
        $org_uuid = isset($params['orgUuid']) ? sanitize_text_field($params['orgUuid']) : '';
        $email_subject = isset($params['emailSubject']) ? sanitize_text_field($params['emailSubject']) : '';
        $email_body = isset($params['emailBody']) ? wp_kses_post($params['emailBody']) : '';

        if (empty($org_uuid)) {
            $logger->error('ORGSS notify owner missing org UUID.', ['source' => 'wicket-orgss']);
            wp_send_json_error(['message' => __('Organization not provided.', 'wicket')]);
        }

        $current_person_uuid = function_exists('wicket_current_person_uuid') ? wicket_current_person_uuid() : '';
        $current_person_profile = function_exists('wicket_get_person_profile')
            ? wicket_get_person_profile($current_person_uuid ?: null)
            : null;
        $current_person_name = '';
        if (is_array($current_person_profile)) {
            $attributes = $current_person_profile['attributes'] ?? ($current_person_profile['data']['attributes'] ?? []);
            $given = $attributes['given_name'] ?? '';
            $family = $attributes['family_name'] ?? '';
            $current_person_name = trim($given . ' ' . $family);
        }

        if ($current_person_name === '') {
            $current_person_name = __('A member', 'wicket');
        }

        $org_memberships = function_exists('wicket_get_org_memberships')
            ? wicket_get_org_memberships($org_uuid)
            : [];
        if (empty($org_memberships)) {
            $logger->error('ORGSS notify owner memberships not found.', ['source' => 'wicket-orgss', 'org_uuid' => $org_uuid]);
            wp_send_json_error(['message' => __('Organization membership not found.', 'wicket')]);
        }

        $owner_uuid = '';
        foreach ($org_memberships as $membership) {
            $org_membership = $membership['membership'] ?? [];
            $org_attributes = $org_membership['attributes'] ?? [];
            $active = $org_attributes['active'] ?? false;
            if (!$active) {
                continue;
            }
            if (!empty($org_attributes['owner_uuid'])) {
                $owner_uuid = $org_attributes['owner_uuid'];
                break;
            }
            $relationships = $org_membership['relationships'] ?? [];
            if (isset($relationships['owner']['data']['id'])) {
                $owner_uuid = $relationships['owner']['data']['id'];
                break;
            }
        }

        if (empty($owner_uuid)) {
            $logger->error('ORGSS notify owner org owner not found.', ['source' => 'wicket-orgss', 'org_uuid' => $org_uuid]);
            wp_send_json_error(['message' => __('Organization owner not found.', 'wicket')]);
        }

        $transient_key = 'orgss_notify_owner_' . md5($org_uuid);
        if (get_transient($transient_key)) {
            $logger->info('ORGSS notify owner throttled.', ['source' => 'wicket-orgss', 'org_uuid' => $org_uuid]);
            wp_send_json_error([
                'message' => __('The organization owner was already notified recently. Please wait before trying again.', 'wicket'),
            ]);
        }

        $owner_profile = function_exists('wicket_get_person_profile')
            ? wicket_get_person_profile($owner_uuid)
            : null;
        $owner_email = '';
        if (is_array($owner_profile)) {
            $attributes = $owner_profile['attributes'] ?? ($owner_profile['data']['attributes'] ?? []);
            $owner_email = $attributes['primary_email_address'] ?? ($owner_profile['primary_email_address'] ?? '');
        }

        if (empty($owner_email) && function_exists('wicket_get_person_by_id')) {
            $owner_person = wicket_get_person_by_id($owner_uuid);
            if (is_object($owner_person) && isset($owner_person->primary_email_address)) {
                $owner_email = $owner_person->primary_email_address;
            }
        }

        $owner_email = sanitize_email($owner_email);
        if (empty($owner_email) || !is_email($owner_email)) {
            $logger->error('ORGSS notify owner email invalid.', ['source' => 'wicket-orgss', 'org_uuid' => $org_uuid, 'owner_uuid' => $owner_uuid, 'owner_email' => $owner_email]);
            wp_send_json_error(['message' => __('Organization owner email not found.', 'wicket')]);
        }

        $subject = $email_subject !== '' ? $email_subject : __('Roster update requested', 'wicket');
        $body = $email_body !== ''
            ? $email_body
            : __('%s would like to be added to the organization roster. Please update the roster to make room or contact support for more seats.', 'wicket');

        $body = wpautop(sprintf($body, esc_html($current_person_name)));
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $sent = wp_mail($owner_email, $subject, $body, $headers);
        if (!$sent) {
            $logger->error('ORGSS notify owner email failed to send.', ['source' => 'wicket-orgss', 'org_uuid' => $org_uuid, 'owner_email' => $owner_email]);
            wp_send_json_error(['message' => __('Email delivery is not configured on this environment. Please try again later.', 'wicket')]);
        }

        set_transient($transient_key, time(), HOUR_IN_SECONDS);
        $logger->info('ORGSS notify owner email sent.', ['source' => 'wicket-orgss', 'org_uuid' => $org_uuid, 'owner_email' => $owner_email]);

        wp_send_json_success([
            'message' => __('Thanks, the organization owner has been notified.', 'wicket'),
        ]);
    }

    public function orgss_notify_owner_roster_added(\WP_REST_Request $request)
    {
        $logger = wc_get_logger();
        $params = $request->get_json_params();
        $org_uuid = isset($params['orgUuid']) ? sanitize_text_field($params['orgUuid']) : '';
        $email_subject = isset($params['emailSubject']) ? sanitize_text_field($params['emailSubject']) : '';
        $email_body = isset($params['emailBody']) ? wp_kses_post($params['emailBody']) : '';

        if (empty($org_uuid)) {
            $logger->error('ORGSS roster added missing org UUID.', ['source' => 'wicket-orgss']);
            wp_send_json_error(['message' => __('Organization not provided.', 'wicket')]);
        }

        $current_person_uuid = function_exists('wicket_current_person_uuid') ? wicket_current_person_uuid() : '';
        $current_person_profile = function_exists('wicket_get_person_profile')
            ? wicket_get_person_profile($current_person_uuid ?: null)
            : null;
        $current_person_name = '';
        if (is_array($current_person_profile)) {
            $attributes = $current_person_profile['attributes'] ?? ($current_person_profile['data']['attributes'] ?? []);
            $given = $attributes['given_name'] ?? '';
            $family = $attributes['family_name'] ?? '';
            $current_person_name = trim($given . ' ' . $family);
        }

        if ($current_person_name === '') {
            $current_person_name = __('A member', 'wicket');
        }

        $org_memberships = function_exists('wicket_get_org_memberships')
            ? wicket_get_org_memberships($org_uuid)
            : [];
        if (empty($org_memberships)) {
            $logger->error('ORGSS roster added memberships not found.', ['source' => 'wicket-orgss', 'org_uuid' => $org_uuid]);
            wp_send_json_error(['message' => __('Organization membership not found.', 'wicket')]);
        }

        $owner_uuid = '';
        foreach ($org_memberships as $membership) {
            $org_membership = $membership['membership'] ?? [];
            $org_attributes = $org_membership['attributes'] ?? [];
            $active = $org_attributes['active'] ?? false;
            if (!$active) {
                continue;
            }
            if (!empty($org_attributes['owner_uuid'])) {
                $owner_uuid = $org_attributes['owner_uuid'];
                break;
            }
            $relationships = $org_membership['relationships'] ?? [];
            if (isset($relationships['owner']['data']['id'])) {
                $owner_uuid = $relationships['owner']['data']['id'];
                break;
            }
        }

        if (empty($owner_uuid)) {
            $logger->error('ORGSS roster added org owner not found.', ['source' => 'wicket-orgss', 'org_uuid' => $org_uuid]);
            wp_send_json_error(['message' => __('Organization owner not found.', 'wicket')]);
        }

        $owner_profile = function_exists('wicket_get_person_profile')
            ? wicket_get_person_profile($owner_uuid)
            : null;
        $owner_email = '';
        if (is_array($owner_profile)) {
            $attributes = $owner_profile['attributes'] ?? ($owner_profile['data']['attributes'] ?? []);
            $owner_email = $attributes['primary_email_address'] ?? ($owner_profile['primary_email_address'] ?? '');
        }

        if (empty($owner_email) && function_exists('wicket_get_person_by_id')) {
            $owner_person = wicket_get_person_by_id($owner_uuid);
            if (is_object($owner_person) && isset($owner_person->primary_email_address)) {
                $owner_email = $owner_person->primary_email_address;
            }
        }

        $owner_email = sanitize_email($owner_email);
        if (empty($owner_email) || !is_email($owner_email)) {
            $logger->error('ORGSS roster added email invalid.', ['source' => 'wicket-orgss', 'org_uuid' => $org_uuid, 'owner_uuid' => $owner_uuid, 'owner_email' => $owner_email]);
            wp_send_json_error(['message' => __('Organization owner email not found.', 'wicket')]);
        }

        $subject = $email_subject !== '' ? $email_subject : __('Roster update notification', 'wicket');
        $body = $email_body !== ''
            ? $email_body
            : __('%1$s has been added to your organization roster. No action is needed unless %1$s is not an employee. In that case, you can access the roster management tool and remove them. If %1$s is an employee, they can now access member benefits.', 'wicket');

        $body = wpautop(sprintf($body, esc_html($current_person_name)));
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $sent = wp_mail($owner_email, $subject, $body, $headers);
        if (!$sent) {
            $logger->error('ORGSS roster added email failed to send.', ['source' => 'wicket-orgss', 'org_uuid' => $org_uuid, 'owner_email' => $owner_email]);
            wp_send_json_error(['message' => __('Email delivery is not configured on this environment. Please try again later.', 'wicket')]);
        }

        $logger->info('ORGSS roster added email sent.', ['source' => 'wicket-orgss', 'org_uuid' => $org_uuid, 'owner_email' => $owner_email]);
        wp_send_json_success([
            'message' => __('Thanks, the organization owner has been notified.', 'wicket'),
        ]);
    }
}
