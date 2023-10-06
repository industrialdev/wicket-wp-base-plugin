<?php

/*
 * Wicket WooCommerce Customizations
 * Description: Adjustments to the product archives and singles selected as 'Membership Categories'
 *
 */

/**
 * Redirect Membership Categories To Shop
 */
add_action( 'wp', 'wicket_redirect_membership_cats' );
function wicket_redirect_membership_cats() {

    if ( wicket_get_option('wicket_admin_settings_woo_remove_membership_categories') === '1' ) {
        $membership_categories = wicket_get_option('wicket_admin_settings_membership_categories');

        if ( is_product_category($membership_categories) ) {
          wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
          exit;
        }
    }

}

/**
 * Redirect single products from Membership categories to shop page
 */
add_action( 'wp', 'wicket_redirect_membership_cat_product_pages', 99 );
function wicket_redirect_membership_cat_product_pages() {

    if ( wicket_get_option('wicket_admin_settings_woo_remove_membership_product_single') === '1' ) {          $membership_categories = wicket_get_option('wicket_admin_settings_membership_categories');  
        if ( is_product() && has_term( $membership_categories, 'product_cat' ) ) {
            wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
            exit;
        } 
    }
}