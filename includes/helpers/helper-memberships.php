<?php

// No direct access
defined('ABSPATH') || exit;

/**
 * WPML-aware helper to detect if a product belongs to the 'membership' product category.
 * Accepts product ID or WC_Product instance. Falls back to checking translations when WPML is present.
 *
 * @param int|\WC_Product $product
 * @return bool
 */
function wicket_is_membership_product($product)
{
    // Normalize to product ID
    $product_id = is_object($product) && method_exists($product, 'get_id') ? (int) $product->get_id() : (int) $product;

    if (!$product_id) {
        return false;
    }

    // If WPML is active, run WPML-aware checks using the 'wpml_object_id' filter.
    // Otherwise just return has_term() directly.
    // Use the multilanguage boolean helper to determine if any multilanguage provider is active.
    $wpml_active = wicket_is_multilang_active();
    if (!$wpml_active) {
        return function_exists('has_term') ? has_term('membership', 'product_cat', $product_id) : false;
    }

    // WPML is active: try checking the translated product (or term) fallback using the filter only.
    // Try to get the product ID for the site's default language and check there
    $default_lang = apply_filters('wpml_default_language', null);
    if ($default_lang) {
        // Get translation of product into default language using the wpml_object_id filter
        $translated_product_id = apply_filters('wpml_object_id', $product_id, 'product', false, $default_lang);
        if ($translated_product_id && $translated_product_id !== $product_id) {
            if (has_term('membership', 'product_cat', $translated_product_id)) {
                return true;
            }
        }
    }

    // Additionally try checking the term translation: lookup the 'membership' term ID in current taxonomy
    $term = get_term_by('slug', 'membership', 'product_cat');
    if ($term && !is_wp_error($term)) {
        $term_id = $term->term_id;
        // Map term id via the wpml_object_id filter in the current language
        $current_lang = apply_filters('wpml_current_language', null);
        $translated_term_id = apply_filters('wpml_object_id', $term_id, 'product_cat', false, $current_lang);

        if ($translated_term_id) {
            $terms = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
            if (!empty($terms) && in_array($translated_term_id, $terms, true)) {
                return true;
            }
        }
    }

    // As a last-resort, check the product's categories for translated slugs that might equal 'membership' in other languages
    if (function_exists('wp_get_post_terms')) {
        $product_terms = wp_get_post_terms($product_id, 'product_cat');
        if (!empty($product_terms) && is_array($product_terms)) {
            foreach ($product_terms as $t) {
                if (!empty($t->slug) && strtolower($t->slug) === 'membership') {
                    return true;
                }
            }
        }
    }

    return false;
}
