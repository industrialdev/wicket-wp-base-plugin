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

// ─────────────────────────────────────────────────────────────────────────────
// DUPLICATE RENEWAL ORDER CLEANUP
//
// PROBLEM
// -------
// When a subscription's automatic payment fails, WooCommerce Subscriptions puts
// the subscription on-hold and creates a renewal order.  AutomateWoo workflows
// that call wicket_generate_renewal_order() can fire more than once while the
// subscription remains on-hold (scheduled daily runs, retry events, etc.).
// The deduplication guard in wicket_generate_renewal_order() (helper-automatewoo.php)
// prevents NEW duplicates from being created going forward.  This hook handles
// the complementary clean-up: as soon as ONE renewal order for a subscription is
// paid, every other pending/failed/on-hold renewal order for that same
// subscription is cancelled automatically so none of them can be paid a second
// time or left as clutter.
//
// HOW IT WORKS
// ------------
// Hooked to woocommerce_order_status_changed at priority 20 — deliberately after
// WCS's own renewal-payment recording (priority 10) so the subscription is already
// reactivated before we touch sibling orders.
//
// 1. Ignore any transition that does not land on a paid status (processing/completed).
// 2. Ignore orders that are not renewal orders.  wcs_order_contains_renewal() covers
//    both regular and early-renewal orders because WCS stores both under the
//    'renewal' relation type.
// 3. For every subscription linked to the paid order, collect all of its related
//    renewal-type orders (get_related_orders('ids', 'renewal')) and cancel any
//    sibling that is still pending, failed, or on-hold — leaving an audit note so
//    admins can see why it was cancelled.
//
// Originated from rahb-website-wordpress/src/web/app/themes/wicket-child/custom/woocommerce.php
// — rahb_cancel_duplicate_renewal_orders_on_payment() — and ported here as the
// canonical base-plugin version under the wicket_ prefix.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Cancel duplicate renewal orders when one renewal order for a subscription is paid.
 *
 * @param int      $order_id   The order that changed status.
 * @param string   $old_status Previous order status (without 'wc-' prefix).
 * @param string   $new_status New order status (without 'wc-' prefix).
 * @param WC_Order $order      The order object (available in WC 3.0+).
 * @return void
 */
function wicket_cancel_duplicate_renewal_orders_on_payment($order_id, $old_status, $new_status, $order = null)
{
    // Only act when an order moves to a paid status.
    if (!in_array($new_status, ['processing', 'completed'], true)) {
        return;
    }

    // Ensure we have the order object (4th arg available since WC 3.0).
    if (!($order instanceof WC_Order)) {
        $order = wc_get_order($order_id);
    }
    if (!$order) {
        return;
    }

    // Only act on renewal orders. wcs_order_contains_renewal() returns true for
    // both regular renewal orders and early-renewal orders because WCS stores
    // both under the 'renewal' relation type.
    if (!function_exists('wcs_order_contains_renewal') || !wcs_order_contains_renewal($order)) {
        return;
    }

    // Get every subscription this renewal order belongs to.
    $subscriptions = wcs_get_subscriptions_for_renewal_order($order);
    if (empty($subscriptions)) {
        return;
    }

    foreach ($subscriptions as $subscription) {
        // get_related_orders('ids', 'renewal') returns all renewal-type order IDs
        // for the subscription, including early-renewal orders.
        $sibling_ids = $subscription->get_related_orders('ids', 'renewal');

        foreach ($sibling_ids as $sibling_id) {
            if ((int) $sibling_id === (int) $order_id) {
                continue; // Skip the order that just got paid.
            }

            $sibling = wc_get_order($sibling_id);
            if (!$sibling) {
                continue;
            }

            if (in_array($sibling->get_status(), ['pending', 'failed', 'on-hold'], true)) {
                $sibling->update_status(
                    'cancelled',
                    sprintf(
                        /* translators: %s: order ID of the renewal order that was successfully paid */
                        __('Cancelled automatically: renewal order #%s was paid for this subscription.', 'wicket'),
                        $order_id
                    )
                );
            }
        }
    }
}
add_action('woocommerce_order_status_changed', 'wicket_cancel_duplicate_renewal_orders_on_payment', 20, 4);
