<?php

// This function replicates the one provided by WordPress and drupal plugins
function wicket_api_client() {
  $settings = require('settings.php');

  $client = new \Wicket\Client('not-used', $settings['jwt_secret_key'], $settings['wicket_api_url']);
  $client->authorize($settings['admin_user_uuid']);

  return $client;
}
