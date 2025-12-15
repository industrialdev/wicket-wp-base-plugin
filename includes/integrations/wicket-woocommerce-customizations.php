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
