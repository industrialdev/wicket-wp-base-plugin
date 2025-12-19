<?php

/*
 * Wicket WooCommerce Customizations
 * Description: Adjustments to the product archives and singles selected as 'Membership Categories'
 *
 */

/*
 * Redirect Membership Categories To Shop
 */
add_action('wp', 'wicket_redirect_membership_cats');
function wicket_redirect_membership_cats()
{

    if (!is_admin() && wicket_get_option('wicket_admin_settings_woo_remove_membership_categories') === '1') {
        $membership_categories = wicket_get_option('wicket_admin_settings_membership_categories');

        if (is_product_category($membership_categories)) {
            wp_safe_redirect(wc_get_page_permalink('shop'));
            exit;
        }
    }

}

/*
 * Redirect single products from Membership categories to shop page
 * Membership Categories that are used to redirect here can be configured under Wicket -> Memberships
 */
add_action('wp', 'wicket_redirect_membership_cat_product_pages', 99);
function wicket_redirect_membership_cat_product_pages()
{

    if (!is_admin() && wicket_get_option('wicket_admin_settings_woo_remove_membership_product_single') === '1') {
        $membership_categories = wicket_get_option('wicket_admin_settings_membership_categories');
        if ($membership_categories && is_product() && has_term($membership_categories, 'product_cat')) {
            wp_safe_redirect(wc_get_page_permalink('shop'));
            exit;
        }
    }
}

/*
 * When redirected to a checkout disable the "X added to cart, continue shopping?" message
 */
add_action('wp', 'wicket_disable_cart_links');
function wicket_disable_cart_links()
{

    /*
     * Remove Product Permalink on Order Table in cart and after checkout
     */
    if (wicket_get_option('wicket_admin_settings_woo_remove_cart_links') === '1') {
        // Remove Product Permalink on Order Table after checkout
        add_filter('woocommerce_order_item_permalink', '__return_false');
        // Remove product link in cart item list
        add_filter('woocommerce_cart_item_permalink', '__return_false');
    }
}

/*
 * When redirected to a checkout disable the "X added to cart, continue shopping?" message
 * THIS DID NOT WORK WHEN ADDED INTO A FUNCTION, BUT IT SHOULD BE. THIS SHOULD ALSO ONLY BE APPLIED TO THE CART PAGE, CURRENTLY IT IS EVERYWHERE.
 */
if (wicket_get_option('wicket_admin_settings_woo_remove_added_to_cart_message') === '1') {
    add_filter('wc_add_to_cart_message_html', '__return_false');
}


/**
 * Prevent WooCommerce from deleting draft orders
 * 
 * This prevents WooCommerce (v10+) from scheduling the automatic cleanup of draft orders
 * via Action Scheduler. By default, this prevents ALL draft order deletion.
 * 
 * Developers can disable this prevention by using the filter:
 * 
 * Example to allow draft order cleanup:
 * add_filter( 'wicket_prevent_draft_order_cleanup', '__return_false' );
 * 
 * Example to conditionally allow cleanup (e.g., only for specific draft orders):
 * add_filter( 'wicket_prevent_draft_order_cleanup', function( $prevent, $hook, $args ) {
 *     // Your custom logic here
 *     return $prevent;
 * }, 10, 3 );
 * 
 * @since 2.1.50
 */
add_filter( 'pre_as_schedule_recurring_action', function( $return, $timestamp, $interval, $hook, $args ) {
    if ( 'woocommerce_cleanup_draft_orders' === $hook ) {
        $prevent = apply_filters( 'wicket_prevent_draft_order_cleanup', true, $hook, $args );
        if ( $prevent ) {
            return false;
        }
    }
    return $return;
}, 10, 5 );

add_filter( 'pre_as_schedule_cron_action', function( $return, $timestamp, $hook, $args ) {
    if ( 'woocommerce_cleanup_draft_orders' === $hook ) {
        $prevent = apply_filters( 'wicket_prevent_draft_order_cleanup', true, $hook, $args );
        if ( $prevent ) {
            return false;
        }
    }
    return $return;
}, 10, 4 );

add_filter( 'pre_as_schedule_single_action', function( $return, $timestamp, $hook, $args ) {
    if ( 'woocommerce_cleanup_draft_orders' === $hook ) {
        $prevent = apply_filters( 'wicket_prevent_draft_order_cleanup', true, $hook, $args );
        if ( $prevent ) {
            return false;
        }
    }
    return $return;
}, 10, 4 );

add_filter( 'pre_as_enqueue_async_action', function( $return, $hook, $args ) {
    if ( 'woocommerce_cleanup_draft_orders' === $hook ) {
        $prevent = apply_filters( 'wicket_prevent_draft_order_cleanup', true, $hook, $args );
        if ( $prevent ) {
            return false;
        }
    }
    return $return;
}, 10, 3 );
