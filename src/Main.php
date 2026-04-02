<?php

declare(strict_types=1);

namespace WicketWP;

// No direct access
defined('ABSPATH') || exit;

/**
 * Class Main.
 *
 * Entry point for all other classes in the src/ folder
 */
class Main
{
    /**
     * Instance of the Main class.
     *
     * @var Main
     */
    private static $instance;

    /**
     * Instance of the Assets class.
     *
     * @var Assets
     */
    public $assets;

    /**
     * Instance of the Blocks class.
     *
     * @var Blocks|null
     */
    public $blocks;

    /**
     * Instance of the Widgets class.
     *
     * @var Widgets|null
     */
    public $widgets;

    /**
     * Instance of the Includes class.
     *
     * @var Includes
     */
    public $includes;

    /**
     * Instance of the Rest class.
     *
     * @var Rest|null
     */
    public $rest;

    /**
     * Instance of the WooCommerce email blocker.
     *
     * @var WooCommerce\EmailBlocker|null
     */
    public $woo_email_blocker;

    /**
     * Instance of the Log class.
     *
     * @var Log
     */
    private Log $_log;

    /**
     * Get the instance of the Main class.
     *
     * @return Main
     */
    public static function get_instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct()
    {
        // Initialize classes here
    }

    /**
     * Access the logger or write a log entry directly.
     *
     * Two calling conventions are supported:
     *
     *   // Getter — returns the Log instance for chained level calls:
     *   Wicket()->log()->error('message', $context);
     *
     *   // Direct — write a log entry in one call:
     *   Wicket()->log('error', 'message', $context);
     *
     * @param string|null $level   Log level (LOG_LEVEL_* constant or string). Omit to get the Log instance.
     * @param string      $message Log message (required when $level is provided).
     * @param array       $context Optional context array.
     * @return Log|bool   Log instance when called with no args; bool write result when called with args.
     */
    public function log(?string $level = null, string $message = '', array $context = []): Log|bool
    {
        if (!isset($this->_log)) {
            $this->_log = new Log();
        }

        if ($level === null) {
            return $this->_log;
        }

        return $this->_log->log($level, $message, $context);
    }

    /**
     * Initialize the plugin components.
     *
     * @param object $plugin The main plugin instance
     * @return void
     */
    public function init($plugin)
    {
        // Initialize Log first so all subsequent classes can use it
        $this->_log = new Log();

        // Initialize Assets class
        $this->assets = new Assets($this);

        // Initialize Includes class
        $this->includes = new Includes($this);

        // Initialize REST routes
        $this->rest = new Rest($this);
        $this->rest->init();

        // Initialize blocks
        $this->blocks = new Blocks($this);
        $this->blocks->init();

        // Initialize widgets
        $this->widgets = new Widgets($this);
        $this->widgets->init();

        // Initialize WooCommerce email blocker
        $this->woo_email_blocker = new WooCommerce\EmailBlocker();
        $this->woo_email_blocker->init();
    }
}
