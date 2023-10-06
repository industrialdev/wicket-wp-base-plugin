<?php
/**
 * Plugin Name: Wicket - Base
 * Plugin URI: http://wicket.io
 * Description: This official Wicket plugin includes core functionality, standard features and developer tools for integrating the Wicket member data platform into a WordPress installation.
 * Version: 0.0.1
 * Author: Wicket Inc.
 * Author URI: https://wicket.io/
 * Text Domain: wicket
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 *
 * @package Wicket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}



if ( ! class_exists( 'Wicket_Main' ) ) {

	// Add vendor plugins with composer autoloader
	if (is_file(plugin_dir_path( __FILE__ ) . 'vendor/autoload.php')) {
		require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
	}

	/**
	 * The main Wicket class
	 */
	class Wicket_Main {
		/**
		 * Constructor
		 */
		public function __construct() {

			// Define global constants
			$this->wicket_global_constants_vars();

			// load text domain
			add_action( 'plugins_loaded', array( $this, 'wicket_init' ) );



			// Registration hook setting
			register_activation_hook( __FILE__, array( $this, 'wicket_install_settings' ) );

			// Include admin files
			if ( is_admin() ) {
				// include admin class
				include_once WICKET_PLUGIN_DIR . 'includes/admin/class-wicket-admin.php';
			}

			// Include to allow other functions to check if plugins are active on front end
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			
			// Include helper functions
			include_once WICKET_PLUGIN_DIR . 'includes/helper-wicket-functions.php';
			include_once WICKET_PLUGIN_DIR . 'includes/helper-settings-functions.php';
			// include acf blocks
			include_once WICKET_PLUGIN_DIR . 'includes/wicket-blocks.php';
			// Include wicket widgets
			include_once WICKET_PLUGIN_DIR . 'includes/widgets/wicket-create-account.php';
			include_once WICKET_PLUGIN_DIR . 'includes/widgets/wicket-contact-information.php';
			include_once WICKET_PLUGIN_DIR . 'includes/widgets/wicket-update-password.php';
			include_once WICKET_PLUGIN_DIR . 'includes/widgets/wicket-manage-preferences.php';
			include_once WICKET_PLUGIN_DIR . 'includes/widgets/wicket-additional-information.php';

			// Include wicket shortcodes
			include_once WICKET_PLUGIN_DIR . 'includes/wicket-shortcodes.php';

			// Include wp-cassify sync
			if ( is_plugin_active('wp-cassify/wp-cassify.php') && (wicket_get_option('wicket_admin_settings_wpcassify_sync_roles') === '1') ) {
				include_once WICKET_PLUGIN_DIR . 'includes/integrations/wicket-cas-role-sync.php';
			} 
			// Include woocommerce functions
			if ( is_plugin_active('woocommerce/woocommerce.php') && (wicket_get_option('wicket_admin_settings_woo_sync_addresses') === '1') ) {
				include_once WICKET_PLUGIN_DIR . 'includes/integrations/wicket-woocommerce-checkout-addresses.php';
				include_once WICKET_PLUGIN_DIR . 'includes/integrations/wicket-woocommerce-customizations.php';
			} 
			// Include woocommerce memberships team functions
			if ( is_plugin_active('woocommerce-memberships-for-teams/woocommerce-memberships-for-teams.php') ) {
				include_once WICKET_PLUGIN_DIR . 'includes/integrations/wicket-woocommerce-membership-team.php';
			}
			// Include user switching functions
			if ( is_plugin_active('user-switching/user-switching.php') ) {
				include_once WICKET_PLUGIN_DIR . 'includes/integrations/wicket-user-switching-sync.php';
			}
			

			
		}

		/**
		 * Define Global variables function
		 */
		public function wicket_global_constants_vars() {

			if ( ! defined( 'WICKET_URL' ) ) {
				define( 'WICKET_URL', plugin_dir_url( __FILE__ ) );
			}

			if ( ! defined( 'WICKET_BASENAME' ) ) {
				define( 'WICKET_BASENAME', plugin_basename( __FILE__ ) );
			}

			if ( ! defined( 'WICKET_PLUGIN_DIR' ) ) {
				define( 'WICKET_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
			}
		}

		/**
		 * Plugin settings
		 */
		public function wicket_install_settings() {
			// Default settings for plugin.
		}

		/**
		 * Load text domain
		 */
		public function wicket_init() {

			if ( function_exists( 'load_plugin_textdomain' ) ) {
				load_plugin_textdomain( 'wicket', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
			}

		}

	} // end Class Wicket_Main.
	new Wicket_Main();
}