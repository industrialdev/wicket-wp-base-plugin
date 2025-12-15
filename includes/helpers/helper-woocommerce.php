<?php

// No direct access
defined('ABSPATH') or die;

/**
 * Creates a WooCommerce order (draft by default) and optionally a subscription for a customer.
 *
 * @param string $customer_uuid The UUID of the customer.
 * @param array $product_ids An associative array of product IDs and their quantities.
 * @param array $args {
 *     Optional. An array of additional arguments.
 *
 *     @type bool $redirect_to_order_on_creation Whether to redirect to the order on creation. Default false.
 *     @type string $order_status The status of the order. Default 'checkout-draft'.
 *     @type string $created_via The source of the order creation. Default 'admin'.
 *     @type string $org_uuid The UUID of the organization to associate with the order. Default is an empty string.
 *     @type bool $include_subscription Whether to include a subscription in the order. Default true.
 *     @type array $item_meta An associative array of meta keys and values to add to the order items. Default is an array with '_org_uuid' key.
 *     @type string $subscription_billing_period The billing period for the subscription. Default 'year'.
 *     @type int $subscription_billing_interval The billing interval for the subscription. Default 1.
 *     @type string $subscription_start_date The start date for the subscription. Default is the current time in 'mysql' format.
 * }
 * @return WC_Order The created WooCommerce order.
 *
 * Example usage:   $new_order = wicket_create_order($customer_uuid, $product_ids, ['item_meta' => ['_org_uuid' => $org_uuid]]);
 */
function wicket_create_order($customer_uuid, $product_ids, $args)
{
    $default = [
        'redirect_to_order_on_creation' => false,
        'order_status' => 'checkout-draft',
        'created_via' => 'admin',
        'include_subscription' => true,
        'item_meta' => [
            '_org_uuid' => '',
        ],
        'subscription_billing_period' => 'year',
        'subscription_billing_interval' => 1,
        'subscription_start_date' => current_time('mysql'),
    ];
    $args = array_merge($default, $args);

    // Ensure they exist in WP, and if not yet create them
    $customer_wp_id = wicket_create_wp_user_if_not_exist($customer_uuid);

    $order = new WC_Order();
    $order->set_created_via($args['created_via']);
    $order->set_customer_id($customer_wp_id);

    foreach ($product_ids as $product_id => $qty) {
        $wc_product = wc_get_product($product_id);
        $order->add_product($wc_product, $qty);
    }

    $order->calculate_totals(); // Without this order total will be zero
    $order->set_status($args['order_status']);
    $order->save();

    if ($args['item_meta']) {
        // Associate the org as needed
        $order_items = $order->get_items();
        foreach ($order_items as $item) {
            foreach ($args['item_meta'] as $meta_key => $meta_value) {
                wc_update_order_item_meta($item->get_id(), $meta_key, $meta_value);
            }
        }
    }

    // Add subscription
    if ($args['include_subscription']) {
        $subscription = wcs_create_subscription([
            'order_id' => $order->get_id(),
            'customer_id' => $customer_wp_id,
            'billing_period' => $args['subscription_billing_period'],
            'billing_interval' => $args['subscription_billing_interval'],
            'start_date' => $args['subscription_start_date'],
        ]);
        if (is_wp_error($subscription)) {
            wicket_write_log('Error creating subscription:');
            wicket_write_log($subscription);
        } else {
            foreach ($product_ids as $product_id => $qty) {
                $wc_product = wc_get_product($product_id);
                $subscription->add_product($wc_product, $qty);
            }
            $subscription->calculate_totals();
            $subscription->save();

            // Associate the org as needed
            $subscription_items = $subscription->get_items();
            if ($args['item_meta']) {
                foreach ($subscription_items as $item) {
                    foreach ($args['item_meta'] as $meta_key => $meta_value) {
                        wc_update_order_item_meta($item->get_id(), $meta_key, $meta_value);
                    }
                }
            }

            // Add the subscription to the order
            $order->add_order_note('Subscription created successfully.');
            $order->update_meta_data('_subscription_id', $subscription->get_id());
            $order->save();
        } // End if no subscription error
    } // End if include subscription

    if ($args['redirect_to_order_on_creation']) {
        $redirect_to = admin_url('admin.php?page=wc-orders&action=edit&id=' . $order->get_id(), 'https');
        header('Location: ' . $redirect_to);
        die();
    }

    return $order;
}
