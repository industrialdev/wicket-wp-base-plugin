<?php

use Wicket\Client;

// No direct access
defined('ABSPATH') || exit;

// These files will be included at the end of the current file
$wicket_helpers = [
    'helper-general.php',
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
 * This function initializes the Wicket API client using the settings for the current environment.
 * It connects to the Wicket API and authorizes the client with the provided JWT and person ID.
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
 * This function initializes the Wicket API client and authorizes it as the current user.
 * This is useful for giving context to person operations and respecting permissions on the Wicket side.
 *
 * @return Client|null The initialized and authorized Wicket API client, or null if authorization fails.
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
        $errors = json_decode($e->getResponse()->getBody())->errors;

        error_log($e->getMessage());
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
