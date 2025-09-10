<?php

// No direct access
defined('ABSPATH') || exit;

/**
 * Helpers for multilanguage workflows
 */

/**
 * Get current language ISO code (two letter code)
 * Compatible with: WPML, Polylang, WP user locale, default WP locale
 *
 * @return string Two letter ISO code. Default is 'en'.
 */
function wicket_get_current_language()
{
  // 1. Check WPML
  if (defined('ICL_SITEPRESS_VERSION')) {
    // Attempt 1.1: via filter
    $lang_candidate_wpml = apply_filters('wpml_current_language', null);
    if (is_string($lang_candidate_wpml) && strlen($lang_candidate_wpml) === 2) {
      return $lang_candidate_wpml;
    }

    // Attempt 1.2: via global $sitepress object
    global $sitepress;
    if (isset($sitepress) && is_object($sitepress) && method_exists($sitepress, 'get_current_language')) {
      $lang_candidate_wpml = $sitepress->get_current_language();
      if (is_string($lang_candidate_wpml) && strlen($lang_candidate_wpml) === 2) {
        return $lang_candidate_wpml;
      }
    }

    // Attempt 1.3: via ICL_LANGUAGE_CODE constant
    if (defined('ICL_LANGUAGE_CODE')) {
      // Cast to string as constants can be other types, though ICL_LANGUAGE_CODE should be string.
      $lang_candidate_wpml = (string) ICL_LANGUAGE_CODE;
      if (strlen($lang_candidate_wpml) === 2) {
        return $lang_candidate_wpml;
      }
    }
  }

  // 2. Check Polylang
  if (function_exists('pll_current_language')) {
    // The original had phpcs:ignore for pll_current_language call.
    $lang_candidate_polylang = pll_current_language('slug'); // phpcs:ignore
    if (is_string($lang_candidate_polylang)) {
      // If the language is not two letters, we need to get the two letter code, first two letters of the string
      if (strlen($lang_candidate_polylang) > 2) {
        $lang_candidate_polylang = substr($lang_candidate_polylang, 0, 2);
      }

      return $lang_candidate_polylang;
    }
  }

  // 3. WP user locale or default WP locale
  $current_language = get_user_locale();

  if (empty($current_language)) {
    $current_language = get_locale();
  }

  if (is_string($current_language)) {
    // If the language is not two letters, we need to get the two letter code, first two letters of the string
    if (strlen($current_language) > 2) {
      $current_language = substr($current_language, 0, 2);
    }

    return $current_language;
  }

  // 4. Default fallback
  return 'en';
}

/**
 * Return an array of active multilanguage providers detected on this site.
 * Common providers detected: 'wpml', 'polylang', 'translatepress', 'weglot', 'qtranslate'
 *
 * @return array<string>
 */
function wicket_get_active_multilang_provider()
{
  $providers = [];

  // WPML
  if (defined('ICL_SITEPRESS_VERSION')) {
    $providers[] = 'wpml';
  }

  // Polylang
  if (function_exists('pll_current_language')) {
    $providers[] = 'polylang';
  }

  // TranslatePress
  if (function_exists('trp_get_language') || function_exists('trp_locale')) {
    $providers[] = 'translatepress';
  }

  // Weglot
  if (function_exists('weglot_get_current_language') || function_exists('weglot_current_language')) {
    $providers[] = 'weglot';
  }

  // qTranslate / qTranslate-X
  if (function_exists('qtranxf_getLanguage') || function_exists('qtrans_getLanguage')) {
    $providers[] = 'qtranslate';
  }

  return $providers;
}

/**
 * Shortcut boolean helper: true when any multilanguage provider is detected.
 *
 * @return bool
 */
function wicket_is_multilang_active()
{
  return (bool) wicket_get_active_multilang_provider();
}


