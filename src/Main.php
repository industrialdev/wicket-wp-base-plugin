<?php

declare(strict_types=1);

namespace WicketWP;

// No direct access
defined('ABSPATH') || exit;

/**
 * Class Main
 *
 * Entry point for all other classes in the src/ folder
 *
 * @package WicketWP
 */
class Main {

    /**
     * Instance of the Main class
     *
     * @var Main
     */
    private static $instance;

    /**
     * Instance of the Assets class
     *
     * @var Assets
     */
    public $assets;

    /**
     * Instance of the Blocks class
     *
     * @var Blocks|null
     */
    public $blocks;

    /**
     * Instance of the Widgets class
     *
     * @var Widgets|null
     */
    public $widgets;

    /**
     * Instance of the Includes class
     *
     * @var Includes
     */
    public $includes;

    /**
     * Instance of the Rest class
     *
     * @var Rest|null
     */
    public $rest;

    /**
     * Get the instance of the Main class
     *
     * @return Main
     */
    public static function get_instance(): Main {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Initialize classes here
    }

    /**
     * Initialize the plugin components
     *
     * @param object $plugin The main plugin instance
     * @return void
     */
    public function init($plugin) {
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
    }
}
