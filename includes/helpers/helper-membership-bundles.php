<?php

// No direct access
defined('ABSPATH') || exit;

/**
 * Create a membership bundle record in the MDP.
 *
 * @param string $person_uuid       Owner person UUID.
 * @param string $org_uuid          Organization UUID.
 * @param string $name_en           Bundle name (English).
 * @param string $starts_at         ISO 8601 start date. Defaults to now.
 * @param string $ends_at           ISO 8601 end date. Optional.
 * @param int    $grace_period_days Grace period in days. Default 0.
 * @param string $name_fr           Bundle name (French). Optional.
 * @param string $external_id       External ID (e.g. WP post ID). Optional.
 *
 * @return array|WP_Error MDP response array containing bundle UUID, or WP_Error on failure.
 */
function wicket_create_bundle_membership(
    string $person_uuid,
    string $org_uuid,
    string $name_en,
    string $starts_at = '',
    string $ends_at = '',
    int $grace_period_days = 0,
    string $name_fr = '',
    string $external_id = ''
) {
    $client = wicket_api_client();

    if (empty($starts_at)) {
        $starts_at = wicket_time_format_iso8601_utc(wicket_time_get_utc_datetime());
    }

    $attributes = [
        'name_en'           => $name_en,
        'starts_at'         => $starts_at,
        'grace_period_days' => $grace_period_days,
    ];

    if (!empty($ends_at)) {
        $attributes['ends_at'] = $ends_at;
    }

    if (!empty($name_fr)) {
        $attributes['name_fr'] = $name_fr;
    }

    if (!empty($external_id)) {
        $attributes['external_id'] = $external_id;
    }

    $payload = [
        'data' => [
            'type'          => 'membership_bundles',
            'attributes'    => $attributes,
            'relationships' => [
                'owner' => [
                    'data' => [
                        'id'   => $person_uuid,
                        'type' => 'people',
                    ],
                ],
                'organization' => [
                    'data' => [
                        'id'   => $org_uuid,
                        'type' => 'organizations',
                    ],
                ],
            ],
        ],
    ];

    try {
        $response = $client->post('membership_bundles', ['json' => $payload]);
    } catch (Exception $e) {
        $response = new WP_Error('wicket_api_error', $e->getMessage());
    }

    return $response;
}

/**
 * Get a single membership bundle record from the MDP.
 *
 * @param string $bundle_uuid Bundle UUID.
 *
 * @return array|WP_Error MDP response array or WP_Error on failure.
 */
function wicket_get_bundle_membership(string $bundle_uuid)
{
    $client = wicket_api_client();

    try {
        $response = $client->get("membership_bundles/$bundle_uuid");
    } catch (Exception $e) {
        $response = new WP_Error('wicket_api_error', $e->getMessage());
    }

    return $response;
}

/**
 * Update a membership bundle record in the MDP.
 *
 * MDP derives status from dates — do not pass a status field.
 * Only include params you want to change; omitted optional params are not sent.
 *
 * @param string    $bundle_uuid       Bundle UUID.
 * @param string    $starts_at         ISO 8601 start date. Optional.
 * @param string    $ends_at           ISO 8601 end date. Optional.
 * @param int|false $grace_period_days Grace period in days. Pass false to omit.
 * @param string    $name_en           Bundle name (English). Optional.
 * @param string    $name_fr           Bundle name (French). Optional.
 * @param string    $external_id       External ID. Optional.
 *
 * @return array|WP_Error MDP response array or WP_Error on failure.
 */
function wicket_update_bundle_membership(
    string $bundle_uuid,
    string $starts_at = '',
    string $ends_at = '',
    $grace_period_days = false,
    string $name_en = '',
    string $name_fr = '',
    string $external_id = ''
) {
    $client = wicket_api_client();

    $attributes = [];

    if (!empty($starts_at)) {
        $attributes['starts_at'] = $starts_at;
    }

    if (!empty($ends_at)) {
        $attributes['ends_at'] = $ends_at;
    }

    if ($grace_period_days !== false) {
        $attributes['grace_period_days'] = (int) $grace_period_days;
    }

    if (!empty($name_en)) {
        $attributes['name_en'] = $name_en;
    }

    if (!empty($name_fr)) {
        $attributes['name_fr'] = $name_fr;
    }

    if (!empty($external_id)) {
        $attributes['external_id'] = $external_id;
    }

    $payload = [
        'data' => [
            'type'       => 'membership_bundles',
            'id'         => $bundle_uuid,
            'attributes' => $attributes,
        ],
    ];

    try {
        $response = $client->patch("membership_bundles/$bundle_uuid", ['json' => $payload]);
    } catch (Exception $e) {
        $response = new WP_Error('wicket_api_error', $e->getMessage());
    }

    return $response;
}

/**
 * Delete a membership bundle record from the MDP.
 *
 * MDP implicitly destroys all child person_memberships on bundle delete.
 *
 * @param string $bundle_uuid Bundle UUID.
 *
 * @return array|WP_Error MDP response or WP_Error on failure.
 */
function wicket_delete_bundle_membership(string $bundle_uuid)
{
    $client = wicket_api_client();

    try {
        $response = $client->delete("membership_bundles/$bundle_uuid");
    } catch (Exception $e) {
        $response = new WP_Error('wicket_api_error', $e->getMessage());
    }

    return $response;
}

/**
 * Get all membership bundles for an organization.
 *
 * @param string $org_uuid    Organization UUID.
 * @param array  $filters     Optional query filters (e.g. ['with_deleted' => 'true']).
 * @param int    $page_size   Number of results per page. Default 25.
 * @param int    $page_number Page number. Default 1.
 *
 * @return array|WP_Error MDP response array or WP_Error on failure.
 */
function wicket_get_org_bundle_memberships(
    string $org_uuid,
    array $filters = [],
    int $page_size = 25,
    int $page_number = 1
) {
    $client = wicket_api_client();

    $query = http_build_query(array_filter([
        'filter'       => $filters ?: null,
        'page[size]'   => $page_size,
        'page[number]' => $page_number,
    ]));

    $endpoint = "organizations/$org_uuid/membership_bundles" . ($query ? "?$query" : '');

    try {
        $response = $client->get($endpoint);
    } catch (Exception $e) {
        $response = new WP_Error('wicket_api_error', $e->getMessage());
    }

    return $response;
}

/**
 * Get all person_membership assignments under a membership bundle.
 *
 * @param string $bundle_uuid Bundle UUID.
 * @param int    $page_size   Number of results per page. Default 25.
 * @param int    $page_number Page number. Default 1.
 *
 * @return array|WP_Error MDP response array or WP_Error on failure.
 */
function wicket_get_bundle_person_memberships(
    string $bundle_uuid,
    int $page_size = 25,
    int $page_number = 1
) {
    $client = wicket_api_client();

    $query = http_build_query([
        'page[size]'   => $page_size,
        'page[number]' => $page_number,
    ]);

    try {
        $response = $client->get("membership_bundles/$bundle_uuid/person_memberships?$query");
    } catch (Exception $e) {
        $response = new WP_Error('wicket_api_error', $e->getMessage());
    }

    return $response;
}

/**
 * Assign a person_membership to a membership bundle.
 *
 * Uses POST /person_memberships with a membership_bundle relationship instead
 * of organization_membership. Tier UUID is required on the person_membership —
 * it does not live on the bundle itself.
 *
 * MDP cascades bundle starts_at/ends_at/grace_period_days to the assignment
 * automatically, so date params are optional.
 *
 * @param string $person_uuid          Person UUID.
 * @param string $membership_tier_uuid MDP membership (tier) UUID.
 * @param string $bundle_uuid          Membership bundle UUID.
 * @param string $starts_at            ISO 8601 start date. Optional.
 * @param string $ends_at              ISO 8601 end date. Optional.
 * @param int    $grace_period_days    Grace period in days. Default 0.
 *
 * @return array|WP_Error MDP response array (includes person_membership UUID) or WP_Error.
 */
function wicket_assign_person_to_bundle_membership(
    string $person_uuid,
    string $membership_tier_uuid,
    string $bundle_uuid,
    string $starts_at = '',
    string $ends_at = '',
    int $grace_period_days = 0
) {
    $client = wicket_api_client();

    $attributes = [
        'grace_period_days' => $grace_period_days,
    ];

    if (!empty($starts_at)) {
        $attributes['starts_at'] = $starts_at;
    }

    if (!empty($ends_at)) {
        $attributes['ends_at'] = $ends_at;
    }

    $payload = [
        'data' => [
            'type'          => 'person_memberships',
            'attributes'    => $attributes,
            'relationships' => [
                'person' => [
                    'data' => [
                        'id'   => $person_uuid,
                        'type' => 'people',
                    ],
                ],
                'membership' => [
                    'data' => [
                        'id'   => $membership_tier_uuid,
                        'type' => 'memberships',
                    ],
                ],
                'membership_bundle' => [
                    'data' => [
                        'id'   => $bundle_uuid,
                        'type' => 'membership_bundles',
                    ],
                ],
            ],
        ],
    ];

    try {
        $response = $client->post('person_memberships', ['json' => $payload]);
    } catch (Exception $e) {
        $response = new WP_Error('wicket_api_error', $e->getMessage());
    }

    return $response;
}

/**
 * Update the owner of a membership bundle.
 *
 * @param string $bundle_uuid Bundle UUID.
 * @param string $person_uuid New owner person UUID.
 *
 * @return array|WP_Error MDP response or WP_Error on failure.
 */
function wicket_update_bundle_membership_owner(string $bundle_uuid, string $person_uuid)
{
    $client = wicket_api_client();

    $payload = [
        'data' => [
            'type'          => 'membership_bundles',
            'id'            => $bundle_uuid,
            'relationships' => [
                'owner' => [
                    'data' => [
                        'id'   => $person_uuid,
                        'type' => 'people',
                    ],
                ],
            ],
        ],
    ];

    try {
        $response = $client->patch("membership_bundles/$bundle_uuid", ['json' => $payload]);
    } catch (Exception $e) {
        $response = new WP_Error('wicket_api_error', $e->getMessage());
    }

    return $response;
}

/**
 * Set the external_id on a membership bundle record.
 *
 * Used to store the WP post ID on the MDP bundle record for cross-reference.
 *
 * @param string     $bundle_uuid Bundle UUID.
 * @param int|string $external_id External ID (typically WP post ID).
 *
 * @return array|WP_Error MDP response or WP_Error on failure.
 */
function wicket_update_bundle_membership_external_id(string $bundle_uuid, $external_id)
{
    $client = wicket_api_client();

    $payload = [
        'data' => [
            'type'       => 'membership_bundles',
            'id'         => $bundle_uuid,
            'attributes' => [
                'external_id' => $external_id,
            ],
        ],
    ];

    try {
        $response = $client->patch("membership_bundles/$bundle_uuid", ['json' => $payload]);
    } catch (Exception $e) {
        $response = new WP_Error('wicket_api_error', $e->getMessage());
    }

    return $response;
}
