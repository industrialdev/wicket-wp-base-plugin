<?php

/**
 * ACF Blocks file for Wicket Base Plugins.
 *
 * @version  1.0.0
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Wicket Blocks class.
 */
class Wicket_Blocks
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        if (!class_exists('ACF')) {
            return;
        }

        // Add Wicket block categories
        add_filter('block_categories_all', [$this, 'wicket_block_category']);

        // Add ACF blocks and field groups
        add_action('enqueue_block_assets', [$this, 'wicket_enqueue_block_styles']);
        add_filter('acf/settings/load_json', [$this, 'wicket_load_acf_field_group']);
    }

    /**
     * Add Wicket block categories.
     */
    public function wicket_block_category($categories)
    {
        $categories[] = [
            'slug'  => 'wicket',
            'title' => 'Wicket',
        ];

        return $categories;
    }

    /**
     * Load and register ACF Blocks on init.
     */
    public function wicket_load_blocks()
    {
        $blocks = $this->wicket_get_blocks();
        foreach ($blocks as $block) {
            if (file_exists(WICKET_PLUGIN_DIR . 'includes/blocks/' . $block . '/block.json')) {
                register_block_type(WICKET_PLUGIN_DIR . 'includes/blocks/' . $block . '/block.json');
                if (file_exists(WICKET_PLUGIN_DIR . 'includes/blocks/' . $block . '/init.php')) {
                    include_once WICKET_PLUGIN_DIR . 'includes/blocks/' . $block . '/init.php';
                }
            }
        }
    }

    /**
     * Enqueue block styles.
     */
    public function wicket_enqueue_block_styles()
    {
        $blocks = $this->wicket_get_blocks();
        foreach ($blocks as $block) {
            if (file_exists(WICKET_PLUGIN_DIR . 'includes/blocks/' . $block . '/style.css')) {
                $block_json = json_decode(file_get_contents(WICKET_PLUGIN_DIR . 'includes/blocks/' . $block . '/block.json'), true);
                if (!empty($block_json['style'])) {
                    wp_enqueue_style(
                        $block_json['style'],
                        WICKET_URL . 'includes/blocks/' . $block . '/style.css',
                        [],
                        filemtime(WICKET_PLUGIN_DIR . 'includes/blocks/' . $block . '/style.css')
                    );
                }
            }
        }
    }

    /**
     * Load ACF field groups for blocks.
     */
    public function wicket_load_acf_field_group($paths)
    {
        $blocks = $this->wicket_get_blocks();
        foreach ($blocks as $block) {
            $paths[] = WICKET_PLUGIN_DIR . 'includes/blocks/' . $block;
        }

        return $paths;
    }

    /**
     * Get ACF Blocks from all folders included in the blocks folder.
     */
    public function wicket_get_blocks()
    {
        $path = WICKET_PLUGIN_DIR . 'includes/blocks/';
        if (!is_dir($path)) {
            return [];
        }
        $blocks = scandir($path);
        if (!is_array($blocks)) {
            return [];
        }

        return array_values(array_diff($blocks, ['..', '.', '.DS_Store', '_base-block']));
    }
} // end Class Wicket_Blocks.
