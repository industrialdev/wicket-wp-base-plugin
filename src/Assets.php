<?php

declare(strict_types=1);

namespace WicketWP;

// No direct access
defined('ABSPATH') || exit;

/**
 * Class Assets
 *
 * Centralized asset loading for the Wicket plugin
 *
 * @package WicketWP
 */
class Assets {

    /**
     * Instance of the main plugin class
     *
     * @var WicketWP
     */
    protected $plugin;

    /**
     * Constructor
     *
     * @param WicketWP $plugin Instance of the main plugin class
     */
    public function __construct($plugin) {
        $this->plugin = $plugin;

        // Hook into WordPress
        add_action('wp_enqueue_scripts', [$this, 'enqueue_plugin_styles'], 15);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_plugin_scripts'], 15);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_block_editor_assets']);
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
    public function enqueue_plugin_styles() {
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
            }

            // Scripts and styles that always get enqueued when not using a wicket theme
            if (!wp_style_is('material-icons', 'enqueued')) {
                wp_enqueue_style('material-icons', 'https://fonts.googleapis.com/icon?family=Material+Icons');
            }

            if (!wp_style_is('font-awesome', 'enqueued')) {
                wp_enqueue_style('font-awesome', WICKET_URL . 'assets/fonts/FontAwesome/web-fonts-with-css/css/fontawesome.css', false, '5.15.4', 'all');
                wp_enqueue_style('font-awesome-brands', WICKET_URL . 'assets/fonts/FontAwesome/web-fonts-with-css/css/brands.css', false, '5.15.4', 'all');
                wp_enqueue_style('font-awesome-solid', WICKET_URL . 'assets/fonts/FontAwesome/web-fonts-with-css/css/solid.css', false, '5.15.4', 'all');
                wp_enqueue_style('font-awesome-regular', WICKET_URL . 'assets/fonts/FontAwesome/web-fonts-with-css/css/regular.css', false, '5.15.4', 'all');
            }
        }
    }

    /**
     * Enqueue the plugin's scripts.
     *
     * @return void
     */
    public function enqueue_plugin_scripts() {
        $theme = wp_get_theme(); // gets the current theme
        $theme_name = $theme->name;

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

        // Only on non-Wicket themes, and only if a wicket block is present
        if (!$is_wicket_theme) {
            // Wicket theme not in use, so enqueue the compiled component styles
            $use_legacy_styles = wicket_get_option('wicket_admin_settings_legacy_styles_enable', false);

            if ($use_legacy_styles) {
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
        }
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook The current admin page
     * @return void
     */
    public function enqueue_admin_assets($hook) {
        // This method can be used to enqueue admin-specific assets
        // based on the current admin page
    }

    /**
     * Enqueue block editor assets
     *
     * @return void
     */
    public function enqueue_block_editor_assets() {
        // This method can be used to enqueue assets specifically for
        // the Gutenberg block editor
    }
}
