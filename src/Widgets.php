<?php

declare(strict_types=1);

namespace WicketWP;

/**
 * Widgets class
 * Handles registration and initialization of all Wicket widgets
 */
class Widgets
{
    /**
     * Reference to Main
     *
     * @var Main
     */
    protected $main;

    public function __construct(Main $main)
    {
        $this->main = $main;
    }

    /**
     * Initialize widgets (register hooks)
     */
    public function init()
    {
        // Register widgets on widgets_init hook
        add_action('widgets_init', [$this, 'register_widgets']);

        // Also register widgets early for block usage
        add_action('acf/init', [$this, 'register_widgets'], 5);

        // Initialize widget form processors
        add_action('init', [$this, 'initialize_widgets']);
    }

    /**
     * Register widgets
     */
    public function register_widgets()
    {
        add_action('widgets_init', [$this, 'initialize_widgets']);
        add_action('acf/init', [$this, 'initialize_widgets']);

        // Register wicket widgets
        register_widget('WicketWP\\Widgets\\CreateAccount');
        register_widget('WicketWP\\Widgets\\CreateAccountNoPassword');
        register_widget('WicketWP\\Widgets\\UpdatePassword');
        register_widget('WicketWP\\Widgets\\ManagePreferences');
    }

    /**
     * Initialize widget form processors
     */
    public function initialize_widgets()
    {
        // Check if widget classes exist, if not try to load them
        if (! class_exists('WicketWP\\Widgets\\CreateAccount')) {
            $file = WICKET_PLUGIN_DIR . 'src/Widgets/CreateAccount.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }

        if (! class_exists('WicketWP\\Widgets\\CreateAccountNoPassword')) {
            $file = WICKET_PLUGIN_DIR . 'src/Widgets/CreateAccountNoPassword.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }

        if (! class_exists('WicketWP\\Widgets\\UpdatePassword')) {
            $file = WICKET_PLUGIN_DIR . 'src/Widgets/UpdatePassword.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }

        if (! class_exists('WicketWP\\Widgets\\ManagePreferences')) {
            $file = WICKET_PLUGIN_DIR . 'src/Widgets/ManagePreferences.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }

        // Initialize widget form processors
        \WicketWP\Widgets\CreateAccount::init();
        \WicketWP\Widgets\CreateAccountNoPassword::init();
        \WicketWP\Widgets\UpdatePassword::init();
        \WicketWP\Widgets\ManagePreferences::init();
    }
}
