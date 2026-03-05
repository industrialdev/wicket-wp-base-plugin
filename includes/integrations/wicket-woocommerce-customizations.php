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

/*
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
add_filter('pre_as_schedule_recurring_action', function ($return, $timestamp, $interval, $hook, $args) {
    if ('woocommerce_cleanup_draft_orders' === $hook) {
        $prevent = apply_filters('wicket_prevent_draft_order_cleanup', true, $hook, $args);
        if ($prevent) {
            return false;
        }
    }

    return $return;
}, 10, 5);

add_filter('pre_as_schedule_cron_action', function ($return, $timestamp, $hook, $args) {
    if ('woocommerce_cleanup_draft_orders' === $hook) {
        $prevent = apply_filters('wicket_prevent_draft_order_cleanup', true, $hook, $args);
        if ($prevent) {
            return false;
        }
    }

    return $return;
}, 10, 4);

add_filter('pre_as_schedule_single_action', function ($return, $timestamp, $hook, $args) {
    if ('woocommerce_cleanup_draft_orders' === $hook) {
        $prevent = apply_filters('wicket_prevent_draft_order_cleanup', true, $hook, $args);
        if ($prevent) {
            return false;
        }
    }

    return $return;
}, 10, 4);

add_filter('pre_as_enqueue_async_action', function ($return, $hook, $args) {
    if ('woocommerce_cleanup_draft_orders' === $hook) {
        $prevent = apply_filters('wicket_prevent_draft_order_cleanup', true, $hook, $args);
        if ($prevent) {
            return false;
        }
    }

    return $return;
}, 10, 3);

/**
 * Add a sortable customer column to Woo orders and subscriptions list tables.
 *
 * The column is managed through the native Screen Options panel by default
 * because it is registered as a standard list-table column.
 */
add_filter('manage_edit-shop_order_columns', 'wicket_wc_add_customer_column');
add_filter('manage_edit-shop_subscription_columns', 'wicket_wc_add_customer_column');
add_filter('woocommerce_shop_order_list_table_columns', 'wicket_wc_add_customer_column');
add_filter('woocommerce_shop_subscription_list_table_columns', 'wicket_wc_add_customer_column');

add_filter('manage_edit-shop_order_sortable_columns', 'wicket_wc_add_customer_sortable_column');
add_filter('manage_edit-shop_subscription_sortable_columns', 'wicket_wc_add_customer_sortable_column');
add_filter('woocommerce_shop_order_list_table_sortable_columns', 'wicket_wc_add_customer_sortable_column');
add_filter('woocommerce_shop_subscription_list_table_sortable_columns', 'wicket_wc_add_customer_sortable_column');

add_action('manage_shop_order_posts_custom_column', 'wicket_wc_render_customer_column_for_post_row', 10, 2);
add_action('manage_shop_subscription_posts_custom_column', 'wicket_wc_render_customer_column_for_post_row', 10, 2);
add_action('woocommerce_shop_order_list_table_custom_column', 'wicket_wc_render_customer_column_for_order_row', 10, 2);
add_action('woocommerce_shop_subscription_list_table_custom_column', 'wicket_wc_render_customer_column_for_order_row', 10, 2);

add_filter('request', 'wicket_wc_customer_column_request_query', 20);
add_filter('woocommerce_shop_order_list_table_prepare_items_query_args', 'wicket_wc_customer_column_prepare_items_query_args', 20);
add_filter('woocommerce_shop_subscription_list_table_prepare_items_query_args', 'wicket_wc_customer_column_prepare_items_query_args', 20);
add_filter('posts_clauses', 'wicket_wc_customer_column_posts_clauses', 20, 2);
add_filter('woocommerce_orders_table_query_clauses', 'wicket_wc_customer_column_orders_table_clauses', 20, 3);

/**
 * Add the customer column to WooCommerce list-table columns.
 *
 * @param array<string, string> $columns Existing columns.
 * @return array<string, string>
 */
function wicket_wc_add_customer_column($columns)
{
    if (isset($columns['wicket_customer'])) {
        return $columns;
    }

    $new_columns = [];
    $inserted = false;

    foreach ($columns as $key => $label) {
        $new_columns[$key] = $label;

        if (in_array($key, ['order_status', 'status'], true)) {
            $new_columns['wicket_customer'] = __('Customer', 'wicket');
            $inserted = true;
        }
    }

    if (!$inserted) {
        $new_columns['wicket_customer'] = __('Customer', 'wicket');
    }

    return $new_columns;
}

/**
 * Register customer column as sortable.
 *
 * @param array<string, string> $columns Existing sortable columns.
 * @return array<string, string>
 */
function wicket_wc_add_customer_sortable_column($columns)
{
    $columns['wicket_customer'] = 'wicket_customer_last_name';

    return $columns;
}

/**
 * Render customer column content for CPT-backed rows.
 *
 * @param string $column  Column key.
 * @param int    $post_id Order/subscription post ID.
 * @return void
 */
function wicket_wc_render_customer_column_for_post_row($column, $post_id)
{
    if ('wicket_customer' !== $column) {
        return;
    }

    $order = wc_get_order($post_id);
    if (!$order instanceof WC_Order) {
        echo '&mdash;';
        return;
    }

    echo wp_kses_post(wicket_wc_get_customer_column_html($order));
}

/**
 * Render customer column content for HPOS-backed rows.
 *
 * @param string   $column Column key.
 * @param WC_Order $order  Order/subscription object.
 * @return void
 */
function wicket_wc_render_customer_column_for_order_row($column, $order)
{
    if ('wicket_customer' !== $column || !$order instanceof WC_Order) {
        return;
    }

    echo wp_kses_post(wicket_wc_get_customer_column_html($order));
}

/**
 * Build customer column display.
 *
 * @param WC_Order $order Order/subscription object.
 * @return string
 */
function wicket_wc_get_customer_column_html($order)
{
    $customer_id = (int) $order->get_customer_id();
    $customer_name = '';

    if ($customer_id > 0) {
        $customer_name = wicket_wc_get_customer_account_display_name($customer_id);
    }

    if ('' === $customer_name) {
        $first_name = trim((string) $order->get_billing_first_name());
        $last_name = trim((string) $order->get_billing_last_name());

        if ('' !== $last_name && '' !== $first_name) {
            $customer_name = $last_name . ', ' . $first_name;
        } elseif ('' !== $last_name) {
            $customer_name = $last_name;
        } elseif ('' !== $first_name) {
            $customer_name = $first_name;
        }
    }

    if ('' === $customer_name) {
        $customer_name = trim((string) $order->get_formatted_billing_full_name());
    }

    if ('' === $customer_name) {
        $customer_name = __('Guest', 'wicket');
    }

    if ($customer_id > 0) {
        $edit_user_link = get_edit_user_link($customer_id);
        if (!empty($edit_user_link)) {
            return sprintf(
                '<a href="%1$s">%2$s</a>',
                esc_url($edit_user_link),
                esc_html($customer_name)
            );
        }
    }

    return esc_html($customer_name);
}

/**
 * Build a customer display name from WP account profile fields.
 *
 * @param int $customer_id WP user ID.
 * @return string
 */
function wicket_wc_get_customer_account_display_name($customer_id)
{
    $user = get_user_by('id', $customer_id);
    if (!$user instanceof WP_User) {
        return '';
    }

    $first_name = trim((string) get_user_meta($customer_id, 'first_name', true));
    $last_name = trim((string) get_user_meta($customer_id, 'last_name', true));

    if ('' !== $last_name && '' !== $first_name) {
        return $last_name . ', ' . $first_name;
    }

    if ('' !== $last_name) {
        return $last_name;
    }

    if ('' !== $first_name) {
        return $first_name;
    }

    return trim((string) $user->display_name);
}

/**
 * Apply customer-last-name sorting marker to CPT-backed order/subscription tables.
 *
 * @param array<string, mixed> $vars List table request vars.
 * @return array<string, mixed>
 */
function wicket_wc_customer_column_request_query($vars)
{
    if (($vars['orderby'] ?? '') !== 'wicket_customer_last_name') {
        return $vars;
    }

    $post_type = $vars['post_type'] ?? '';
    if ('' === $post_type && isset($_GET['post_type'])) {
        $post_type = sanitize_key((string) wp_unslash($_GET['post_type'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }

    if (!in_array($post_type, ['shop_order', 'shop_subscription'], true)) {
        return $vars;
    }

    $vars['wicket_sort_customer_last_name'] = 1;
    $vars['orderby'] = 'ID';

    return $vars;
}

/**
 * Apply customer-last-name sorting marker to HPOS-backed order/subscription tables.
 *
 * @param array<string, mixed> $query_args Query args used by wc_get_orders().
 * @return array<string, mixed>
 */
function wicket_wc_customer_column_prepare_items_query_args($query_args)
{
    if (($query_args['orderby'] ?? '') !== 'wicket_customer_last_name') {
        return $query_args;
    }

    $query_args['wicket_sort_customer_last_name'] = true;
    $query_args['orderby'] = 'id';

    return $query_args;
}

/**
 * Sort CPT list tables by registered customer's last name.
 *
 * @param array<string, string> $clauses Query clauses.
 * @param WP_Query              $query   Current query object.
 * @return array<string, string>
 */
function wicket_wc_customer_column_posts_clauses($clauses, $query)
{
    if (!is_admin() || (int) $query->get('wicket_sort_customer_last_name') !== 1) {
        return $clauses;
    }

    global $wpdb;

    $posts_table = $wpdb->posts;
    $postmeta_table = $wpdb->postmeta;
    $users_table = $wpdb->users;
    $usermeta_table = $wpdb->usermeta;

    $order = strtoupper((string) $query->get('order'));
    $order = in_array($order, ['ASC', 'DESC'], true) ? $order : 'ASC';

    $clauses['join'] .= " LEFT JOIN {$postmeta_table} AS wicket_customer_user_pm ON ({$posts_table}.ID = wicket_customer_user_pm.post_id AND wicket_customer_user_pm.meta_key = '_customer_user')";
    $clauses['join'] .= " LEFT JOIN {$users_table} AS wicket_customer_user ON wicket_customer_user.ID = CAST(wicket_customer_user_pm.meta_value AS UNSIGNED)";
    $clauses['join'] .= " LEFT JOIN (SELECT user_id, MAX(meta_value) AS last_name FROM {$usermeta_table} WHERE meta_key = 'last_name' GROUP BY user_id) AS wicket_customer_last_name ON wicket_customer_last_name.user_id = wicket_customer_user.ID";
    $clauses['join'] .= " LEFT JOIN (SELECT user_id, MAX(meta_value) AS first_name FROM {$usermeta_table} WHERE meta_key = 'first_name' GROUP BY user_id) AS wicket_customer_first_name ON wicket_customer_first_name.user_id = wicket_customer_user.ID";

    $clauses['orderby'] = "COALESCE(NULLIF(wicket_customer_last_name.last_name, ''), wicket_customer_user.display_name, '') {$order}, COALESCE(NULLIF(wicket_customer_first_name.first_name, ''), '') {$order}, {$posts_table}.ID DESC";

    return $clauses;
}

/**
 * Sort HPOS list tables by registered customer's last name.
 *
 * @param array<string, string> $clauses     Query clauses.
 * @param mixed                 $order_query OrdersTableQuery object.
 * @param array<string, mixed>  $args        Query args.
 * @return array<string, string>
 */
function wicket_wc_customer_column_orders_table_clauses($clauses, $order_query, $args)
{
    if (!is_admin() || empty($args['wicket_sort_customer_last_name'])) {
        return $clauses;
    }

    global $wpdb;

    $orders_table = $order_query->get_table_name('orders');
    $users_table = $wpdb->users;
    $usermeta_table = $wpdb->usermeta;

    $order = strtoupper((string) ($args['order'] ?? 'ASC'));
    $order = in_array($order, ['ASC', 'DESC'], true) ? $order : 'ASC';

    $clauses['join'] = (empty($clauses['join']) ? '' : $clauses['join'] . ' ');
    $clauses['join'] .= "LEFT JOIN {$users_table} AS wicket_customer_user ON wicket_customer_user.ID = {$orders_table}.customer_id ";
    $clauses['join'] .= "LEFT JOIN (SELECT user_id, MAX(meta_value) AS last_name FROM {$usermeta_table} WHERE meta_key = 'last_name' GROUP BY user_id) AS wicket_customer_last_name ON wicket_customer_last_name.user_id = wicket_customer_user.ID ";
    $clauses['join'] .= "LEFT JOIN (SELECT user_id, MAX(meta_value) AS first_name FROM {$usermeta_table} WHERE meta_key = 'first_name' GROUP BY user_id) AS wicket_customer_first_name ON wicket_customer_first_name.user_id = wicket_customer_user.ID";

    $clauses['orderby'] = "COALESCE(NULLIF(wicket_customer_last_name.last_name, ''), wicket_customer_user.display_name, '') {$order}, COALESCE(NULLIF(wicket_customer_first_name.first_name, ''), '') {$order}, {$orders_table}.id DESC";

    return $clauses;
}
