<?php

/**
 * Plugin Name: Wicket Base
 * Plugin URI: http://wicket.io
 * Description: This official Wicket plugin includes core functionality, standard features and developer tools for integrating the Wicket member data platform into a WordPress installation.
 * Version: 2.0.165
 * Author: Wicket Inc.
 * Author URI: https://wicket.io
 * Text Domain: wicket
 * Domain Path: /languages
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Requires Plugins: advanced-custom-fields-pro
 *
 * @package Wicket
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}



// Add vendor plugins with composer autoloader
if (is_file(plugin_dir_path(__FILE__) . 'vendor/autoload.php')) {
    require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';
}

// Add root autoload if it is present (helpful for vanilla WP sites)
if (is_file(ABSPATH . 'vendor/autoload.php')) {
    require_once ABSPATH . 'vendor/autoload.php';
}

add_action(
    'plugins_loaded',
    array(Wicket_Main::get_instance(), 'plugin_setup'),
    99
);

/**
 * The main Wicket class
 */
class Wicket_Main
{
    /**
     * Plugin instance.
     *
     * @see get_instance()
     * @type object
     */
    protected static $instance = NULL;

    /**
     * The instance of the Wicket_Blocks class.
     *
     * @var Wicket_Blocks
     */
    public $blocks;

    /**
     * URL to this plugin's directory.
     *
     * @type string
     */
    public $plugin_url = '';

    /**
     * Path to this plugin's directory.
     *
     * @type string
     */
    public $plugin_path = '';

    /**
     * Access this plugin’s working instance
     *
     * @wp-hook plugins_loaded
     * @return  object of this class
     */
    public static function get_instance()
    {
        NULL === self::$instance and self::$instance = new self;

        return self::$instance;
    }

    /**
     * Constructor. Intentionally left empty and public.
     *
     * @see plugin_setup()
     */
    public function __construct() {}

    /**
     * Used for regular plugin work.
     *
     * @wp-hook plugins_loaded
     * @return  void
     */
    public function plugin_setup()
    {
        // Define global constants
        $this->wicket_global_constants_vars();

        // Set plugin path and url properties
        $this->plugin_url    = WICKET_URL;
        $this->plugin_path   = WICKET_PLUGIN_DIR;

        // File includes
        $this->wicket_includes();

        // load text domain and includes
        add_action('init', array($this, 'wicket_init'), 0);

        // Include and instantiate the block handler to register hooks early
        include_once WICKET_PLUGIN_DIR . 'includes/wicket-blocks.php';
        $this->blocks = new Wicket_Blocks();

        // Enqueue styles and scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueue_plugin_styles'], 15); // Using 15 so these will enqueue after the parent theme but before the child theme, so child theme can override

        // Initialize ACF dependent components
        add_action('acf/init', array($this, 'initialize_block_handler'));

        // Register widgets
        add_action('widgets_init', array($this, 'register_widgets'));
    }

    /**
     * Initialize the block handler on acf/init
     */
    public function register_widgets()
    {
        // Register wicket widgets
        register_widget('wicket_create_account');
        register_widget('wicket_update_password');
        register_widget('wicket_preferences');
    }

    /**
     * Initialize the block handler on acf/init
     */
    public function initialize_block_handler()
    {
        if (isset($this->blocks)) {
            $this->blocks->wicket_load_blocks();
        }
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

    /**
     * Includes all the necessary files for the plugin.
     *
     * @return void
     */
    public function wicket_includes()
    {
        // Debug
        include_once WICKET_PLUGIN_DIR . 'includes/wicket-debug.php';

        // Include admin files
        if (is_admin()) {
            // include admin class
            include_once WICKET_PLUGIN_DIR . 'includes/admin/class-wicket-admin.php';
        }

        // Include to allow other functions to check if plugins are active on front end
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');

        // Include MDP Helpers
        include_once WICKET_PLUGIN_DIR . 'includes/helpers/helper-init.php';

        // Include wicket shortcodes
        include_once WICKET_PLUGIN_DIR . 'includes/wicket-shortcodes.php';

        // Include REST API endpoints
        include_once WICKET_PLUGIN_DIR . 'includes/rest/rest-org-search-select.php';

        // Widgets
        include_once WICKET_PLUGIN_DIR . 'includes/widgets/wicket-create-account.php';
        include_once WICKET_PLUGIN_DIR . 'includes/widgets/wicket-update-password.php';
        include_once WICKET_PLUGIN_DIR . 'includes/widgets/wicket-manage-preferences.php';

        // Include wicket components
        include_once WICKET_PLUGIN_DIR . 'includes/wicket-components.php';

        // Include custom code snippets, such as those that additional functionality to complex components
        include_once WICKET_PLUGIN_DIR . 'includes/custom/woocommerce.php';

        // Include Mailtrap settings for stage
        include_once WICKET_PLUGIN_DIR . 'includes/integrations/wicket-mailtrap.php';

        // Include Wicket MDP Schema Merge Tag Generator
        include_once WICKET_PLUGIN_DIR . 'includes/class-wicket-mdp-schema-merge-tag-generator.php';

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
        if (is_plugin_active('woocommerce/woocommerce.php')) {
            if (wicket_get_option('wicket_admin_settings_woo_sync_addresses') === '1') {
                include_once WICKET_PLUGIN_DIR . 'includes/integrations/wicket-woocommerce-checkout-addresses.php';
            }
            include_once WICKET_PLUGIN_DIR . 'includes/integrations/wicket-woocommerce-customizations.php';
        }

        // Include WooCommerce memberships team functions
        if (is_plugin_active('woocommerce-memberships-for-teams/woocommerce-memberships-for-teams.php')) {
            // include_once WICKET_PLUGIN_DIR . 'includes/integrations/wicket-woocommerce-membership-team-metabox.php';
            include_once WICKET_PLUGIN_DIR . 'includes/integrations/wicket-woocommerce-membership-team.php';
        }

        // Include user switching functions
        if (is_plugin_active('user-switching/user-switching.php')) {
            include_once WICKET_PLUGIN_DIR . 'includes/integrations/wicket-user-switching-sync.php';
        }
    }

    /**
     * Enqueue the plugin's styles.
     *
     * If the Wicket theme is active, this method will only enqueue the compiled component styles.
     * If the Wicket theme is not active, this method will enqueue both the compiled component styles
     * and the backup component Tailwind styles and Alpine only if a Wicket block is present on the page.
     *
     * The user can also choose to enable the legacy styles via the Wicket settings page.
     * If the legacy styles are enabled, this method will enqueue the wrapped compiled component styles,
     * the wrapped backup component Tailwind styles and Alpine.
     *
     * @return void
     */
    public function enqueue_plugin_styles()
    {
        $theme = wp_get_theme(); // gets the current theme
        $theme_name = $theme->name;

        $base_styles_url      = WICKET_URL . 'assets/css/min/wicket.min.css';
        $base_styles_path     = WICKET_PLUGIN_DIR . 'assets/css/min/wicket.min.css';

        $base_styles_wrapped_url      = WICKET_URL . 'assets/css/min/wicket-wrapped.min.css';
        $base_styles_wrapped_path     = WICKET_PLUGIN_DIR . 'assets/css/min/wicket-wrapped.min.css';

        $tailwind_styles_url  = WICKET_URL . 'assets/css/min/wicket-tailwind.min.css';
        $tailwind_styles_path = WICKET_PLUGIN_DIR . 'assets/css/min/wicket-tailwind.min.css';

        $tailwind_styles_wrapped_url  = WICKET_URL . 'assets/css/min/wicket-tailwind-wrapped.min.css';
        $tailwind_styles_wrapped_path = WICKET_PLUGIN_DIR . 'assets/css/min/wicket-tailwind-wrapped.min.css';

        $alpine_scripts_url   = WICKET_URL . 'assets/js/min/wicket-alpine.min.js';
        $alpine_scripts_path  = WICKET_PLUGIN_DIR . 'assets/js/min/wicket-alpine.min.js';

        $base_always_script_url  = WICKET_URL . 'assets/js/wicket_base.js';
        $base_always_script_path = WICKET_PLUGIN_DIR . 'assets/js/wicket_base.js';

        // Always enqueue the base script
        wp_enqueue_script(
            'wicket-plugin-base-always-script',
            $base_always_script_url,
            [],
            filemtime($base_always_script_path)
        );

        // Check if it's a Wicket theme
        $is_wicket_theme = str_contains(strtolower($theme_name), 'wicket');

        // Only on Wicket's theme v1
        if ($is_wicket_theme) {
            if (!defined('WICKET_WP_THEME_V2')) {
                // Wicket deprecated v1 theme is active, so just enqueue the compiled component styles
                wp_enqueue_style(
                    'wicket-plugin-base-styles',
                    $base_styles_url,
                    false,
                    filemtime($base_styles_path),
                    'all'
                );
            }
        }

        // Only on non-Wicket themes, and only if a wicket block is present
        if (!$is_wicket_theme) {
            // Wicket theme not in use, so enqueue the compiled component styles
            $use_legacy_styles = wicket_get_option('wicket_admin_settings_legacy_styles_enable', false);

            if ($use_legacy_styles) {
                wp_enqueue_style(
                    'wicket-plugin-base-styles-wrapped',
                    $base_styles_wrapped_url,
                    false,
                    filemtime($base_styles_wrapped_path),
                    'all'
                );
                wp_enqueue_style(
                    'wicket-plugin-tailwind-styles-wrapped',
                    $tailwind_styles_wrapped_url,
                    false,
                    filemtime($tailwind_styles_wrapped_path),
                    'all'
                );
                wp_enqueue_script(
                    'wicket-plugin-alpine-script',
                    $alpine_scripts_url,
                    array(),
                    filemtime($alpine_scripts_path),
                    array(
                        'strategy' => 'defer'
                    )
                );
            }

            // Scripts and styles that always get enqueued when not using a wicket theme
            wp_enqueue_style('material-icons', 'https://fonts.googleapis.com/icon?family=Material+Icons');
            wp_enqueue_style('font-awesome', WICKET_URL . 'assets/fonts/FontAwesome/web-fonts-with-css/css/fontawesome.css', false, '5.15.4', 'all');
            wp_enqueue_style('font-awesome-brands', WICKET_URL . 'assets/fonts/FontAwesome/web-fonts-with-css/css/brands.css', false, '5.15.4', 'all');
            wp_enqueue_style('font-awesome-solid', WICKET_URL . 'assets/fonts/FontAwesome/web-fonts-with-css/css/solid.css', false, '5.15.4', 'all');
            wp_enqueue_style('font-awesome-regular', WICKET_URL . 'assets/fonts/FontAwesome/web-fonts-with-css/css/regular.css', false, '5.15.4', 'all');
        }
    }

    /**
     * Load text domain
     */
    public function wicket_init()
    {
        if (function_exists('load_plugin_textdomain')) {
            load_plugin_textdomain('wicket', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        }

        // Initialize widget form processors
        if (class_exists('wicket_create_account')) {
            wicket_create_account::init();
        }
        if (class_exists('wicket_update_password')) {
            wicket_update_password::init();
        }
        if (class_exists('wicket_preferences')) {
            wicket_preferences::init();
        }
    }
} // end Class Wicket_Main.
