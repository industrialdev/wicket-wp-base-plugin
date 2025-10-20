<?php

// No direct access
defined('ABSPATH') || exit;

/**
 * Determine if the active theme or its parent originates from the Wicket suite.
 *
 * @return bool True when the current theme or parent theme name starts with "wicket-".
 */
function is_wicket_theme_active(): bool
{
    if (defined('WICKET_THEME') && true === WICKET_THEME) {
        return true;
    }

    $theme = wp_get_theme();

    if (!$theme) {
        return false;
    }

    $themeName = strtolower($theme->name ?? '');
    $parentTheme = $theme->parent();
    $parentThemeName = $parentTheme ? strtolower($parentTheme->name ?? '') : '';

    if (str_starts_with($themeName, 'wicket-')) {
        return true;
    }

    if ($parentThemeName && str_starts_with($parentThemeName, 'wicket-')) {
        return true;
    }

    return false;
}

/**
 * Determine if the active theme or its parent does not originate from the Wicket suite.
 *
 * @return bool True when the current theme or parent theme name does not start with "wicket-".
 */
function is_non_wicket_theme_active(): bool
{
    return ! is_wicket_theme_active();
}
