<?php

/**
 * Admin file for Wicket Base Plugins
 *
 * @package  Wicket\Admin
 * @version  1.0.0
 */
if (!defined('ABSPATH')) {
	exit;  // Exit if accessed directly
}

if (!class_exists('Wicket_Admin')) {
	/**
	 * Wicket Admin class
	 */
	class Wicket_Admin
	{
		/**
		 * Constructor of class
		 */
		public function __construct()
		{
			// Enqueue Admin CSS JS
			add_action('admin_enqueue_scripts', array($this, 'wicket_admin_enqueue_scripts'));

			// Add Menus
			add_action('admin_menu', array($this, 'wicket_admin_menu'));

			// Add Body Class
			add_filter('admin_body_class', array($this, 'wicket_admin_body_class'));

			// Add Settings Page
			include_once WICKET_PLUGIN_DIR . 'includes/admin/settings/class-wicket-settings.php';
		}

		/**
		 * Enqueue scripts for admin
		 */
		public function wicket_admin_enqueue_scripts()
		{
			$screen = get_current_screen();

			// Wicket Settings page assets
			if ($screen->id == 'toplevel_page_wicket-settings') {
				wp_enqueue_script('wicket_admin_js', WICKET_URL . 'assets/js/wicket_admin.js', array('jquery'), '1.0', false);
				wp_enqueue_style('wicket_admin_css', WICKET_URL . 'assets/css/wicket_admin.css', array(), '1.0');
				wp_enqueue_style('select2', WICKET_URL . 'assets/css/select2.css', array(), '1.0');
				wp_enqueue_script('select2', WICKET_URL . 'assets/js/select2.js', false, '1.0', array('jquery'), '1.0', false);
			}

			// WooCommerce product edit screens: load admin JS so deferral date validation runs
			if ((isset($screen->post_type) && $screen->post_type === 'product') || $screen->id === 'product') {
				// Ensure datepicker is available
				wp_enqueue_script('jquery-ui-datepicker');
				// Reuse admin JS
				wp_enqueue_script('wicket_admin_js', WICKET_URL . 'assets/js/wicket_admin.js', array('jquery', 'jquery-ui-datepicker'), '1.0', false);
			}
		}

		/**
		 * Create admin menu and submenu pages
		 */
		public function wicket_admin_menu()
		{
			add_menu_page(
				esc_html__('Wicket', 'wicket'),  /* Page title */
				esc_html__('Wicket', 'wicket'),  /* Menu title */
				'manage_options',  /* Capability */
				'wicket-settings',  /* Unique Menu slug */
				'',  /* Callback */
				WICKET_URL . '/assets/images/wicket-icon.png',  /* Icon */
				80  /* Position */
			);
		}

		/**
		 * Add a body class to the Wicket Settings page.
		 */
		public function wicket_admin_body_class($classes)
		{
			$screen = get_current_screen();
			if ($screen->id == 'toplevel_page_wicket-settings') {
				return $classes . ' wicket-admin-settings ';
			} else {
				return $classes;
			}
		}
	}

	new Wicket_Admin();
}
