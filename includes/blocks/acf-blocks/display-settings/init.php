<?php
/**
 * Wicket Settings block
 *
 **/

namespace Wicket\Blocks\Wicket_Settings;

/**
 * Admin Welcome
 */
function site( $block = [] ) {

	echo 'Create Account Page '.wicket_get_option('wicket_admin_settings_create_account_page') .'<br>';
	echo 'New Account Redirect '.wicket_get_option('wicket_admin_settings_person_creation_redirect') .'<br>';
	echo 'Captcha Enable? '.wicket_get_option('wicket_admin_settings_google_captcha_enable') .'<br>';
	echo 'Captcha Key '.wicket_get_option('wicket_admin_settings_google_captcha_secret_key') .'<br>';
	echo 'Captcha Secret '.wicket_get_option('wicket_admin_settings_google_captcha_secret_key') .'<br>';
	echo 'Environment '.wicket_get_option('wicket_admin_settings_environment') .'<br>';
	echo 'Prod API '.wicket_get_option('wicket_admin_settings_prod_api_endpoint') .'<br>';
	echo 'Prod Secret '.wicket_get_option('wicket_admin_settings_prod_secret_key') .'<br>';
	echo 'Prod Person ID '.wicket_get_option('wicket_admin_settings_prod_person_id') .'<br>';
	echo 'Prod Parent Org '.wicket_get_option('wicket_admin_settings_prod_parent_org') .'<br>';
	echo 'Prod Wicket Admin '.wicket_get_option('wicket_admin_settings_prod_wicket_admin') .'<br>';
	echo 'Stage API '.wicket_get_option('wicket_admin_settings_stage_api_endpoint') .'<br>';
	echo 'Stage Secret '.wicket_get_option('wicket_admin_settings_stage_secret_key') .'<br>';
	echo 'Stage Person ID '.wicket_get_option('wicket_admin_settings_stage_person_id') .'<br>';
	echo 'Stage Parent Org '.wicket_get_option('wicket_admin_settings_stage_parent_org') .'<br>';
	echo 'Stage Wicket Admin '.wicket_get_option('wicket_admin_settings_stage_wicket_admin') .'<br>';
	echo 'Membership Categories '.print_r(wicket_get_option('wicket_admin_settings_membership_categories')) .'<br>';

}
