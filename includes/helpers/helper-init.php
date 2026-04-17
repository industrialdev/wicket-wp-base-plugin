<?php

use Wicket\Client;

// No direct access
defined('ABSPATH') || exit;

// These files will be included at the end of the current file
$wicket_helpers = [
    'helper-general.php',
    'helper-time.php',
    'helper-unsorted.php',
    'helper-persons.php',
    'helper-person-preferences.php',
    'helper-organizations.php',
    'helper-groups.php',
    'helper-touchpoints.php',
    'helper-segments.php',
    'helper-memberships.php',
    'helper-multilang.php',
    'helper-connections.php',
    'helper-theme.php',
    'helper-woocommerce.php',
    'helper-automatewoo.php',
];

/**
 * Simplify get_option for Wicket Settings using WPSettings
 * example usage: wicket_get_option('my_field_id');.
 */
function wicket_get_option($key, $fallback = null)
{
    $options = get_option('wicket_settings', []);

    return $options[$key] ?? $fallback;
}

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
    $settings = [];
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
 * Use this admin-token client only when elevated access is required, such as
 * reading or writing another person's data, performing background tasks, or
 * running cross-user aggregation queries. For requests on behalf of the
 * currently logged-in user, prefer wicket_api_client_current_user() so the
 * request stays scoped to that user's permissions and audit trail.
 *
 * @return Client|false The initialized Wicket API client, or false if the client could not be initialized.
 */
function wicket_api_client()
{
    try {
        if (!class_exists('\Wicket\Client')) {
            // No SDK available!
            return false;
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
 * Prefer this helper by default when the operation is being performed on
 * behalf of the currently logged-in user. It keeps permissions scoped to that
 * user, distributes rate limiting across per-user tokens, and improves audit
 * traceability in MDP logs.
 *
 * @return Client|null The initialized and authorized Wicket API client, or null if authorization fails.
 */
function wicket_api_client_current_user()
{
    try {
        $client = wicket_api_client();

        if ($client) {
            $person_id = wicket_current_person_uuid();

            if ($person_id) {
                $client->authorize($person_id);
            } else {
                $client = null;
            }
        }
    } catch (Exception $e) {
        return null;
    }

    return $client;
}

/**
 * Generate an access token for a Wicket person.
 *
 * Taken from the Wicket SDK, where it is used as a protected method.
 *
 * @param string $person_id The Wicket person UUID.
 * @param int $expiresIn The token lifetime in seconds. Default is 8 hours.
 * @return string The encoded JWT access token.
 */
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

/**
 * Generate an access token for organization widgets.
 *
 * This returns a token that can be used for the profile and additional info
 * widget on a specific organization.
 *
 * @param string $person_id The Wicket person UUID for the current user.
 * @param string $org_uuid The organization UUID.
 * @return string|false The token string on success, or false on failure.
 */
function wicket_get_access_token($person_id, $org_uuid)
{
    $client = wicket_api_client();

    $payload = [
        'data' => [
            'type' => 'widget_tokens',
            'attributes' => [
                'widget_context' => 'organizations',
            ],
            'relationships' => [
                'subject' => [
                    'data' => [
                        'type' => 'people',
                        'id' => $person_id,
                    ],
                ],
                'resource' => [
                    'data' => [
                        'type' => 'organizations',
                        'id' => $org_uuid,
                    ],
                ],
            ],
        ],
    ];

    try {
        $token = $client->post('widget_tokens', ['json' => $payload]);

        return $token['token'];
    } catch (Exception $e) {
        Wicket()->log()->error($e->getMessage(), ['source' => 'wicket-base']);
    }

    return false;
}

/*
 * Load all helpers
 */
if (isset($wicket_helpers) && !empty($wicket_helpers) && is_array($wicket_helpers)) {
    foreach ($wicket_helpers as $helper) {
        if (file_exists(WICKET_PLUGIN_DIR . 'includes/helpers/' . $helper)) {
            include_once WICKET_PLUGIN_DIR . 'includes/helpers/' . $helper;
        }
    }
}
