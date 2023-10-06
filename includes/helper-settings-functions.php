<?php
/**
 * WPSettings Helper Functions for Wicket Base Plugin
 *
 */
/*

 * Simplify get_option for Wicket Settings using WPSettings
 * example usage: wicket_get_option('my_field_id');
 */
function wicket_get_option($key, $fallback = null) {

    $options = get_option('wicket_settings', []);

    return $options[$key] ?? $fallback;

}