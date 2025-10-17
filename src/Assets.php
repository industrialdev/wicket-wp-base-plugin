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
class Assets
{

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
    public function __construct($plugin)
    {
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
    public function enqueue_plugin_styles()
    {
        // Check if it's a Wicket theme
        $is_wicket_theme = is_wicket_theme_active();

        $base_styles_url      = WICKET_URL . 'assets/css/min/wicket.min.css';
        $base_styles_path     = WICKET_PLUGIN_DIR . 'assets/css/min/wicket.min.css';

        $base_styles_wrapped_url      = WICKET_URL . 'assets/css/min/wicket-wrapped.min.css';
        $base_styles_wrapped_path     = WICKET_PLUGIN_DIR . 'assets/css/min/wicket-wrapped.min.css';

        $tailwind_styles_url  = WICKET_URL . 'assets/css/min/wicket-tailwind.min.css';
        $tailwind_styles_path = WICKET_PLUGIN_DIR . 'assets/css/min/wicket-tailwind.min.css';

        $tailwind_styles_wrapped_url  = WICKET_URL . 'assets/css/min/wicket-tailwind-wrapped.min.css';
        $tailwind_styles_wrapped_path = WICKET_PLUGIN_DIR . 'assets/css/min/wicket-tailwind-wrapped.min.css';

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

        // Only on non-Wicket themes
        if (!$is_wicket_theme) {
            // Wicket theme not in use, so enqueue the compiled component styles unless disabled
            $disable_default_styling = wicket_get_option('wicket_admin_settings_disable_default_styling', false) === '1';

            if (!$disable_default_styling) {
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
    }

    /**
     * Enqueue the plugin's scripts.
     *
     * @return void
     */
    public function enqueue_plugin_scripts()
    {
        // Check if it's a Wicket theme
        $is_wicket_theme = is_wicket_theme_active();

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

        // Only on non-Wicket themes
        if (!$is_wicket_theme) {
            // Wicket theme not in use, we need to enqueue the Alpine script
            wp_enqueue_script(
                'wicket-plugin-alpine-script',
                $alpine_scripts_url,
                [],
                filemtime($alpine_scripts_path),
                [
                    'strategy' => 'defer'
                ]
            );
        }
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook The current admin page
     * @return void
     */
    public function enqueue_admin_assets($hook)
    {
        // This method can be used to enqueue admin-specific assets
        // based on the current admin page
    }

    /**
     * Enqueue block editor assets
     *
     * @return void
     */
    public function enqueue_block_editor_assets()
    {
        // This method can be used to enqueue assets specifically for
        // the Gutenberg block editor
    }
}
