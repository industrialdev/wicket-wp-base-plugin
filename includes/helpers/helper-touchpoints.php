<?php

// No direct access
defined('ABSPATH') || exit;

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
    $client = wicket_api_client();
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
    $client = wicket_api_client();
    $payload = build_touchpoint_payload($params, $wicket_service_id);

    if ($payload) {
        try {
            $res = $client->post('touchpoints', ['json' => $payload]);
        } catch (Exception $e) {
            Wicket()->log()->error($e->getMessage(), ['source' => 'wicket-base']);
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
                        'type' => 'people',
                    ],
                ],
                'service' => [
                    'data' => [
                        'id' => $wicket_service_id, //service id in wicket
                        'type' => 'services',
                    ],
                ],
            ],
        ],
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
 * Resolved service IDs are cached in two layers to keep touchpoint writes from
 * issuing a services lookup every time: a static in-request cache, and a
 * transient for subsequent requests. Services change infrequently, so this
 * cuts a large number of API calls (helping with rate limits) at the cost of
 * picking up service changes only after the cache expires. Only successful
 * lookups are cached, so a failed request is retried on the next call.
 *
 * Filter `wicket_touchpoint_service_id_cache_ttl` to change the cache lifetime,
 * or return 0/false to disable the transient layer.
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
function get_create_touchpoint_service_id($service_name, $service_description = 'Custom from WP', $integration_type = 'custom')
{
    // In-request cache, so repeated writes (for example one touchpoint per
    // attendee) resolve the service only once per request.
    static $resolved = [];

    $cache_id = (string) $service_name;

    if (isset($resolved[$cache_id])) {
        return $resolved[$cache_id];
    }

    $cache_key = 'wicket_tp_service_id_' . md5($cache_id);
    $cached_service_id = get_transient($cache_key);

    if (!empty($cached_service_id)) {
        $resolved[$cache_id] = $cached_service_id;

        return $cached_service_id;
    }

    /**
     * Filters how long a resolved touchpoint service ID is cached.
     *
     * @param int    $ttl          Cache lifetime in seconds. 0 or false disables the transient cache.
     * @param string $service_name The name of the service being resolved.
     */
    $cache_ttl = apply_filters('wicket_touchpoint_service_id_cache_ttl', DAY_IN_SECONDS, $service_name);

    $client = wicket_api_client();

    // check for existing service, return service ID
    $existing_services = $client->get("services?filter[name_eq]=$service_name");
    $existing_service = isset($existing_services['data']) && !empty($existing_services['data']) ? $existing_services['data'][0]['id'] : '';

    if ($existing_service) {
        $resolved[$cache_id] = $existing_service;

        if ($cache_ttl) {
            set_transient($cache_key, $existing_service, (int) $cache_ttl);
        }

        return $existing_service;
    }

    // if no existing service, create one and return service ID
    $payload['data']['attributes'] = [
        'name' => $service_name,
        'description' => $service_description,
        'status' => 'active',
        'integration_type' => $integration_type,
        'integration_settings' => [
            'base_url' => get_home_url(),
        ],
    ];

    try {
        $service = $client->post('/services', ['json' => $payload]);
        $new_service_id = $service['data']['id'];

        $resolved[$cache_id] = $new_service_id;

        if ($cache_ttl) {
            set_transient($cache_key, $new_service_id, (int) $cache_ttl);
        }

        return $new_service_id;
    } catch (Exception $e) {
        $errors = json_decode($e->getResponse()->getBody())->errors;
    }

    return false;
}
