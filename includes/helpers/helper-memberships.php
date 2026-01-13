<?php

// No direct access
defined('ABSPATH') || exit;

/**
 * WPML-aware helper to detect if a product belongs to the 'membership' product category.
 * Accepts product ID or WC_Product instance. Falls back to checking translations when WPML is present.
 *
 * @param int|WC_Product $product
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
            $terms = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
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

/**
 * Build a normalized summary of the active membership seat allocation for an org.
 *
 * @param array $org_memberships Result of wicket_get_org_memberships().
 * @return array{
 *     has_active_membership:bool,
 *     assigned:?int,
 *     max:?int,
 *     unlimited:bool,
 *     has_available_seats:?bool
 * }
 */
function wicket_get_active_membership_seat_summary(array $org_memberships)
{
    $summary = [
        'has_active_membership' => false,
        'assigned'              => null,
        'max'                   => null,
        'unlimited'             => false,
        'has_available_seats'   => null,
    ];

    $fallback_active_summary = null;

    foreach ($org_memberships as $membership) {
        $org_membership = $membership['membership'] ?? [];
        $org_attributes = $org_membership['attributes'] ?? [];
        $included = $membership['included'] ?? [];
        $included_attributes = $included['attributes'] ?? [];

        $active = $org_attributes['active'] ?? ($included_attributes['active'] ?? false);
        if (empty($active)) {
            continue;
        }

        $summary['has_active_membership'] = true;

        $meta = [];
        if (isset($included_attributes['meta']) && is_array($included_attributes['meta'])) {
            $meta = $included_attributes['meta'];
        } elseif (isset($org_attributes['meta']) && is_array($org_attributes['meta'])) {
            $meta = $org_attributes['meta'];
        } elseif (isset($included['meta']) && is_array($included['meta'])) {
            $meta = $included['meta'];
        } elseif (isset($org_membership['meta']) && is_array($org_membership['meta'])) {
            $meta = $org_membership['meta'];
        }

        $meta_unlimited = false;
        if (isset($meta['unlimited_assignments'])) {
            $meta_unlimited = (bool) $meta['unlimited_assignments'];
        } elseif (isset($meta['unlimited_seats'])) {
            $meta_unlimited = (bool) $meta['unlimited_seats'];
        }

        $assigned = null;
        if (isset($included_attributes['active_assignments_count'])) {
            $assigned = (int) $included_attributes['active_assignments_count'];
        } elseif (isset($org_attributes['active_assignments_count'])) {
            $assigned = (int) $org_attributes['active_assignments_count'];
        } elseif (isset($included_attributes['assignments_count'])) {
            $assigned = (int) $included_attributes['assignments_count'];
        } elseif (isset($org_attributes['assignments_count'])) {
            $assigned = (int) $org_attributes['assignments_count'];
        } elseif (isset($meta['active_assignments_count'])) {
            $assigned = (int) $meta['active_assignments_count'];
        } elseif (isset($meta['assignments_count'])) {
            $assigned = (int) $meta['assignments_count'];
        }

        $meta_org_seats = null;
        if (isset($meta['org_seats'])) {
            $meta_org_seats = (int) $meta['org_seats'];
        } elseif (isset($meta['membership_seats'])) {
            $meta_org_seats = (int) $meta['membership_seats'];
        }

        $max = null;
        if (isset($included_attributes['max_assignments'])) {
            $max = (int) $included_attributes['max_assignments'];
        } elseif (isset($org_attributes['max_assignments'])) {
            $max = (int) $org_attributes['max_assignments'];
        }

        $unlimited = !empty($included_attributes['unlimited_assignments']) || !empty($org_attributes['unlimited_assignments']) || $meta_unlimited;
        if ($meta_org_seats !== null && $meta_org_seats > 0) {
            $max = $meta_org_seats;
            $unlimited = false;
        }

        if ($max !== null && $max > 0) {
            $unlimited = false;
        }

        $candidate = [
            'has_active_membership' => true,
            'assigned'              => $assigned,
            'unlimited'             => $unlimited,
            'max'                   => $max,
            'has_available_seats'   => null,
        ];

        if ($unlimited) {
            $candidate['has_available_seats'] = true;
        } elseif (is_null($assigned) || is_null($max)) {
            $candidate['has_available_seats'] = null;
        } else {
            $candidate['has_available_seats'] = $assigned < $max;
        }

        if ($fallback_active_summary === null) {
            $fallback_active_summary = $candidate;
        }

        if ($candidate['max'] !== null || $meta_org_seats !== null) {
            return $candidate;
        }
    }

    return $fallback_active_summary ?? $summary;
}
