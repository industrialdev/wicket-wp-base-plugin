<?php
/**
 * PHPUnit Bootstrap File.
 *
 * Loads Composer autoloader and defines essential WordPress constants for isolated unit testing.
 */

declare(strict_types=1);

// Define essential WordPress constants
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

if (!defined('WEEK_IN_SECONDS')) {
    define('WEEK_IN_SECONDS', 604800);
}

if (!defined('MONTH_IN_SECONDS')) {
    define('MONTH_IN_SECONDS', 2592000);
}

if (!defined('YEAR_IN_SECONDS')) {
    define('YEAR_IN_SECONDS', 31536000);
}

// Define Wicket plugin constants
if (!defined('WICKET_URL')) {
    define('WICKET_URL', 'http://example.com/wp-content/plugins/wicket-wp-base-plugin/');
}

if (!defined('WICKET_BASENAME')) {
    define('WICKET_BASENAME', 'wicket-wp-base-plugin/wicket.php');
}

if (!defined('WICKET_PLUGIN_DIR')) {
    define('WICKET_PLUGIN_DIR', dirname(__DIR__) . '/');
}

// Mock WordPress classes
if (!class_exists('WP_Widget')) {
    class WP_Widget {
        public function __construct($id_base = '', $name = '', $widget_options = [], $control_options = []) {}
    }
}

require_once dirname(__DIR__) . '/vendor/autoload.php';
