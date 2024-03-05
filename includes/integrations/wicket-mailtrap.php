<?php

// MAILTRAP - used for staging/local dev
// Change the info within the plugin settings depending on your inbox. It can be found within the mailtrap inbox settings under "Show Credentials"
// You typically also have to disable the WP SMTP mail plugin as well when needing to use this on stage/local

function mailtrap($phpmailer) {
  $phpmailer->isSMTP();
  $phpmailer->Host = wicket_get_option('wicket_admin_settings_mailtrap_host');
  $phpmailer->SMTPAuth = true;
  $phpmailer->Port = wicket_get_option('wicket_admin_settings_mailtrap_port');
  $phpmailer->Username = wicket_get_option('wicket_admin_settings_mailtrap_username');
  $phpmailer->Password = wicket_get_option('wicket_admin_settings_mailtrap_password');
}

$wicket_settings = get_option('wicket_settings');
$environment = '';
if( !empty( $wicket_settings ) ) {
  $environment = $wicket_settings['wicket_admin_settings_environment'] ?? $environment;
}

if ($environment != 'prod') {
  add_action('phpmailer_init', 'mailtrap');
}
