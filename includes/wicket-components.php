<?php

// No direct access
defined('ABSPATH') || exit;

/**
 * Wicket Components.
 *
 * @author  Wicket Inc.
 * @link    https://wicket.io
 */

/**
 * Get a component from Wicket's library.
 *
 * @param  string  $slug available components {
 *   'accordion',
 *   'alert',
 *   'author',
 *   'banner',
 *   'breadcrumbs',
 *   'button',
 *   'card-call-out',
 *   'card-contact',
 *   'card-event',
 *   'card-featured',
 *   'card-listing',
 *   'card',
 *   'card-product',
 *   'card-related',
 *   'featured-posts',
 *   'filter-form',
 *   'icon-grid',
 *   'icon',
 *   'image',
 *   'link',
 *   'org-search-select',
 *   'related-events',
 *   'related-posts',
 *   'search-form',
 *   'sidebar-contextual-nav',
 *   'social-links',
 *   'social-sharing',
 *   'tabs',
 *   'tag',
 *   'tooltip',
 *   'widget-additional-info',
 *   'widget-prefs-person',
 *   'widget-profile-individual',
 *   'widget-profile-org'
 * }
 * @param  array   $args
 * @param  bool $output
 *
 * @return void
 */
function get_component($slug, array $args = [], $output = true)
{
    /* $args will be available in the component file */
    if (!$output) {
        ob_start();
    }

    // Wrap with the wrapper class to the component classes
    $uses_wicket_theme = is_wicket_theme_active();
    $disable_default_styling = wicket_get_option('wicket_admin_settings_disable_default_styling', false) === '1';

    // Example (add to theme's functions.php) to disable wrapping:
    // add_filter('wicket_base_plugin_should_wrap_component', function ($wrap) {
    // 	return false;
    // });

    $should_we_wrap = apply_filters(
        'wicket_base_plugin_should_wrap_component',
        !$disable_default_styling && !$uses_wicket_theme
    );

    if ($should_we_wrap): ?>
	<div class="wicket-base-plugin">
	<?php
    endif;

    // Try themes first in case an override or custom component was added to the child theme,
    // otherwise use the component file in the plugin if present
    $theme_component_file = locate_template("components/{$slug}.php", false, false);
    $plugin_component_file = __DIR__ . "/components/{$slug}.php";

    if (file_exists($theme_component_file)) {
        require $theme_component_file;
    } elseif (file_exists($plugin_component_file)) {
        require $plugin_component_file;
    } else {
        throw new RuntimeException("Could not find component $slug");
    }

    if ($should_we_wrap): ?>
	</div>
	<?php
    endif;

    if (!$output) {
        return ob_get_clean();
    }
}

/**
 * Check if a component exists.
 *
 * @param  string $slug
 *
 * @return bool
 */
function component_exists($slug)
{
    $theme_component_file = locate_template("components/{$slug}.php", false, false);
    $plugin_component_file = __DIR__ . "/components/{$slug}.php";

    if (file_exists($theme_component_file)) {
        return true;
    } elseif (file_exists($plugin_component_file)) {
        return true;
    }

    return false;
}

/**
 * Get the components directory.
 *
 * @return string
 */
function get_components_dir()
{
    return __DIR__ . '/components/';
}

// Remove WP content filter that sometimes breaks component rendering
remove_filter('the_content', 'wptexturize');
