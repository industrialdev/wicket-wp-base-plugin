<?php

declare(strict_types=1);

namespace WicketWP;

/**
 * Blocks class
 * Handles initialization and registration of all Wicket blocks
 */
class Blocks
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
     * Initialize the Blocks instance
     */
    public function init()
    {
        // Include and instantiate the block handler to register hooks early
        include_once WICKET_PLUGIN_DIR . 'includes/wicket-blocks.php';
        $blocks = new \Wicket_Blocks();

        // Initialize ACF dependent components after widgets are registered
        add_action('acf/init', [$blocks, 'wicket_load_blocks'], 15);
    }
}
