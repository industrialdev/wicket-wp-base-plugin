<?php

/**
 * Plugin Name: Wicket Base
 * Plugin URI: http://wicket.io
 * Description: This official Wicket plugin includes core functionality, standard features and developer tools for integrating the Wicket member data platform into a WordPress installation.
 * Version: 1.0.149
 * Author: Wicket Inc.
 * Author URI: https://wicket.io/
 * Text Domain: wicket
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 *
 * @package Wicket
 */

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}



if (! class_exists('Wicket_Main')) {

	// Add vendor plugins with composer autoloader
	if (is_file(plugin_dir_path(__FILE__) . 'vendor/autoload.php')) {
		require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';
	}

	// Add root autoload if it is present (helpful for vanilla WP sites)
	if (is_file(ABSPATH . 'vendor/autoload.php')) {
		require_once ABSPATH . 'vendor/autoload.php';
	}

	/**
	 * The main Wicket class
	 */
	class Wicket_Main
	{
		/**
		 * Constructor
		 */
		public function __construct()
		{

			// Define global constants
			$this->wicket_global_constants_vars();

			// load text domain
			add_action('plugins_loaded', array($this, 'wicket_init'));



			// Registration hook setting
			register_activation_hook(__FILE__, array($this, 'wicket_install_settings'));

			// Include admin files
			if (is_admin()) {
				// include admin class
				include_once WICKET_PLUGIN_DIR . 'includes/admin/class-wicket-admin.php';
			}

			// Include to allow other functions to check if plugins are active on front end
			include_once(ABSPATH . 'wp-admin/includes/plugin.php');

			// Include helper functions
			include_once WICKET_PLUGIN_DIR . 'includes/helper-wicket-functions.php';
			include_once WICKET_PLUGIN_DIR . 'includes/helper-settings-functions.php';
			// include acf blocks
			include_once WICKET_PLUGIN_DIR . 'includes/wicket-blocks.php';
			// Include wicket widgets
			include_once WICKET_PLUGIN_DIR . 'includes/widgets/wicket-create-account.php';
			include_once WICKET_PLUGIN_DIR . 'includes/widgets/wicket-update-password.php';
			include_once WICKET_PLUGIN_DIR . 'includes/widgets/wicket-manage-preferences.php';

			// Include wicket shortcodes
			include_once WICKET_PLUGIN_DIR . 'includes/wicket-shortcodes.php';

			// Include internal API endpoints
			include_once WICKET_PLUGIN_DIR . 'includes/wicket-internal-endpoints.php';

			// Include wicket components
			include_once WICKET_PLUGIN_DIR . 'includes/wicket-components.php';

			// Include custom code snippets, such as those that additional functionality to complex components
			include_once WICKET_PLUGIN_DIR . 'includes/custom/woocommerce.php';

			// Include Mailtrap settings for stage
			include_once WICKET_PLUGIN_DIR . 'includes/integrations/wicket-mailtrap.php';

			// Include woo order touchpoints
			if (wicket_get_option('wicket_admin_settings_tp_woo_order') === '1') {
				include_once WICKET_PLUGIN_DIR . 'includes/touchpoints/woocommerce-order.php';
			}

			// Include event tickets attendee registered touchpoints
			if (wicket_get_option('wicket_admin_settings_tp_event_ticket_attendees') === '1') {
				include_once WICKET_PLUGIN_DIR . 'includes/touchpoints/woocommerce_payment_complete_event_ticket_attendees.php';
			}

			// Include event tickets attendee registered touchpoints
			if (wicket_get_option('wicket_admin_settings_tp_event_ticket_attendees_checkin') === '1') {
				include_once WICKET_PLUGIN_DIR . 'includes/touchpoints/event_ticket_attendees_checkin.php';
			}

			// Include event tickets attendee registered touchpoints
			if (wicket_get_option('wicket_admin_settings_tp_event_ticket_attendees_rsvp') === '1') {
				include_once WICKET_PLUGIN_DIR . 'includes/touchpoints/event_ticket_attendees_rsvp.php';
			}

			// Include event tickets attendee field hooks to provide last name field by default, rename 'name' field to first name and re-sort fields
			// Not sure if this applies to rsvp fields as well or just attendee registration, but will include it if any of the above options are enabled
			if (
				wicket_get_option('wicket_admin_settings_tp_event_ticket_attendees') === '1' ||
				wicket_get_option('wicket_admin_settings_tp_event_ticket_attendees_checkin') === '1' ||
				wicket_get_option('wicket_admin_settings_tp_event_ticket_attendees_rsvp') === '1'
			) {
				include_once WICKET_PLUGIN_DIR . 'includes/touchpoints/event_ticket_attendees_field_hooks.php';
			}

			// Include wp-cassify sync
			if (is_plugin_active('wp-cassify/wp-cassify.php') && (wicket_get_option('wicket_admin_settings_wpcassify_sync_roles') === '1')) {
				include_once WICKET_PLUGIN_DIR . 'includes/integrations/wicket-cas-role-sync.php';
			}
			// Include woocommerce functions
			if (is_plugin_active('woocommerce/woocommerce.php') && (wicket_get_option('wicket_admin_settings_woo_sync_addresses') === '1')) {
				include_once WICKET_PLUGIN_DIR . 'includes/integrations/wicket-woocommerce-checkout-addresses.php';
				include_once WICKET_PLUGIN_DIR . 'includes/integrations/wicket-woocommerce-customizations.php';
			}
			// Include woocommerce memberships team functions
			if (is_plugin_active('woocommerce-memberships-for-teams/woocommerce-memberships-for-teams.php')) {
				// include_once WICKET_PLUGIN_DIR . 'includes/integrations/wicket-woocommerce-membership-team-metabox.php';
				include_once WICKET_PLUGIN_DIR . 'includes/integrations/wicket-woocommerce-membership-team.php';
			}
			// Include user switching functions
			if (is_plugin_active('user-switching/user-switching.php')) {
				include_once WICKET_PLUGIN_DIR . 'includes/integrations/wicket-user-switching-sync.php';
			}

			// Enqueue styles and scripts
			add_action('wp_enqueue_scripts', array('Wicket_Main', 'enqueue_plugin_styles'), 15); // Using 15 so these will enqueue after the parent theme but before the child theme, so child theme can override
		}

		/**
		 * Define Global variables function
		 */
		public function wicket_global_constants_vars()
		{

			if (! defined('WICKET_URL')) {
				define('WICKET_URL', plugin_dir_url(__FILE__));
			}

			if (! defined('WICKET_BASENAME')) {
				define('WICKET_BASENAME', plugin_basename(__FILE__));
			}

			if (! defined('WICKET_PLUGIN_DIR')) {
				define('WICKET_PLUGIN_DIR', plugin_dir_path(__FILE__));
			}
		}

		public static function enqueue_plugin_styles()
		{
			$theme = wp_get_theme(); // gets the current theme
			$theme_name = $theme->name;

			$base_styles_url      = WICKET_URL . 'assets/css/min/wicket.min.css';
			$base_styles_path     = WICKET_PLUGIN_DIR . 'assets/css/min/wicket.min.css';
			$tailwind_styles_url  = WICKET_URL . 'assets/css/min/wicket-tailwind.min.css';
			$tailwind_styles_path = WICKET_PLUGIN_DIR . 'assets/css/min/wicket-tailwind.min.css';
			$alpine_scripts_url   = WICKET_URL . 'assets/js/min/wicket-alpine.min.js';
			$alpine_scripts_path  = WICKET_PLUGIN_DIR . 'assets/js/min/wicket-alpine.min.js';

			$base_always_script_url  = WICKET_URL . 'assets/js/wicket_base.js';
			$base_always_script_path = WICKET_PLUGIN_DIR . 'assets/js/wicket_base.js';

			if (str_contains(strtolower($theme_name), 'wicket')) {
				// Wicket theme is active, so just enqueue the compiled component styles
				wp_enqueue_style(
					'wicket-plugin-base-styles',
					$base_styles_url,
					FALSE,
					filemtime($base_styles_path),
					'all'
				);
			} else {
				// Wicket theme not in use, so enqueue the compiled component styles and
				// the backup component Tailwind styles and Alpine

				wp_enqueue_style(
					'wicket-plugin-base-styles',
					$base_styles_url,
					FALSE,
					filemtime($base_styles_path),
					'all'
				);
				wp_enqueue_style(
					'wicket-plugin-tailwind-styles',
					$tailwind_styles_url,
					FALSE,
					filemtime($tailwind_styles_path),
					'all'
				);
				wp_enqueue_script(
					'wicket-plugin-alpine-script',
					$alpine_scripts_url,
					array(),
					filemtime($alpine_scripts_path),
					array()
				);
			}

			// Scripts and styles that always get enqueued
			wp_enqueue_script(
				'wicket-plugin-always-script',
				$base_always_script_url,
				array(),
				filemtime($base_always_script_path),
				array()
			);
			wp_enqueue_style('material-icons', 'https://fonts.googleapis.com/icon?family=Material+Icons');
		}

		/**
		 * Plugin settings
		 */
		public function wicket_install_settings()
		{
			// Default settings for plugin.
		}

		/**
		 * Load text domain
		 */
		public function wicket_init()
		{

			if (function_exists('load_plugin_textdomain')) {
				load_plugin_textdomain('wicket', false, dirname(plugin_basename(__FILE__)) . '/languages/');
			}
		}
	} // end Class Wicket_Main.
	new Wicket_Main();
}
