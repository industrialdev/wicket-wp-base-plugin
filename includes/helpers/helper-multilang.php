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

/**
 * Handle WooCommerce product IDs in multilanguage context.
 * Returns the appropriate product ID based on current language and use case.
 *
 * @param int $product_id The original product ID
 * @param string $target_language The target language code (default: 'en' for English)
 * @return array Array with 'original' and 'translated' product IDs, plus current language info
 */
function wicket_get_multilang_product_id( $product_id, $target_language = 'en' )
{
  if ( ! is_numeric( $product_id ) || $product_id <= 0 ) {
    return [
      'original' => $product_id,
      'translated' => $product_id,
      'current_lang' => wicket_get_current_language(),
      'is_multilang' => false,
      'was_translated' => false
    ];
  }

  $current_lang = wicket_get_current_language();
  $is_multilang = wicket_is_multilang_active();

  // If not in multilanguage context or already in target language, return original
  if ( ! $is_multilang || $current_lang === $target_language ) {
    return [
      'original' => $product_id,
      'translated' => $product_id,
      'current_lang' => $current_lang,
      'is_multilang' => $is_multilang,
      'was_translated' => false
    ];
  }

  // Check if WPML is active and translate the product ID
  if ( defined( 'ICL_SITEPRESS_VERSION' ) && function_exists( 'wpml_object_id_filter' ) ) {
    $translated_id = apply_filters( 'wpml_object_id', $product_id, 'product', false, $target_language );

    // If translation failed or returned same ID, use original
    if ( empty( $translated_id ) || $translated_id === $product_id ) {
      $translated_id = $product_id;
      $was_translated = false;
    } else {
      $was_translated = true;
    }

    return [
      'original' => $product_id,
      'translated' => $translated_id,
      'current_lang' => $current_lang,
      'target_lang' => $target_language,
      'is_multilang' => $is_multilang,
      'was_translated' => $was_translated
    ];
  }

  // Fallback for other multilanguage plugins or if WPML translation fails
  return [
    'original' => $product_id,
    'translated' => $product_id,
    'current_lang' => $current_lang,
    'target_lang' => $target_language,
    'is_multilang' => $is_multilang,
    'was_translated' => false
  ];
}

/**
 * Get the correct product ID for WooCommerce operations based on context.
 * Use 'original' for user-facing operations (like role assignment, cart display).
 * Use 'translated' for internal operations (like membership tier lookups).
 *
 * @param int $product_id The original product ID
 * @param string $context 'original' or 'translated' - which version to return
 * @param string $target_language The target language for translation (default: 'en')
 * @return int The appropriate product ID for the context
 */
function wicket_get_product_id_for_context( $product_id, $context = 'original', $target_language = 'en' )
{
  $product_data = wicket_get_multilang_product_id( $product_id, $target_language );

  return $context === 'translated' ? $product_data['translated'] : $product_data['original'];
}
