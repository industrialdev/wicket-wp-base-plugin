<?php

/**
 * Plugin Name: Wicket Base
 * Plugin URI: http://wicket.io
 * Description: This official Wicket plugin includes core functionality, standard features and developer tools for integrating the Wicket member data platform into a WordPress installation.
 * Version: 2.1.45
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

// Define global constants
define('WICKET_URL', plugin_dir_url(__FILE__));
define('WICKET_BASENAME', plugin_basename(__FILE__));
define('WICKET_PLUGIN_DIR', plugin_dir_path(__FILE__));

/**
 * The main WicketWP class
 */
class WicketWP
{
    /**
     * Plugin instance.
     *
     * @see get_instance()
     * @type object
     */
    protected static $instance = NULL;

    /**
     * Main class instance.
     *
     * @var WicketWP\Main
     */
    protected $main;

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

        // Initialize the new Main class
        $this->main = WicketWP\Main::get_instance();
        $this->main->init($this);

        // Set plugin path and url properties
        $this->plugin_url    = WICKET_URL;
        $this->plugin_path   = WICKET_PLUGIN_DIR;

    }

} // end Class WicketWP.

add_action(
    'plugins_loaded',
    [WicketWP::get_instance(), 'plugin_setup'],
    99
);
