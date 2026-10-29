<?php

// ---------------------------------------------------------------------------------------
// setup order hooks. We need both since status changed only runs for existing orders
// ---------------------------------------------------------------------------------------
add_action('woocommerce_order_status_changed', 'woocommerce_order_touchpoint', 9999);
add_action('woocommerce_new_order', 'woocommerce_order_touchpoint', 9999, 2);

/**
 * Writes a touchpoint to the customer's MDP person when an order is created or changes status.
 *
 * The details list each product in its own block, built by wicket_order_touchpoint_products().
 * Sites can reshape the touchpoint through the alter_woocommerce_order_touchpoint filter,
 * which receives the params and a context array with the order under the 'order' key.
 * Subscriptions and orders with no MDP person are skipped.
 *
 * @param int           $order_id The order ID.
 * @param WC_Order|null $order    The order, passed by woocommerce_new_order. Loaded from $order_id when absent.
 *
 * @return void
 *
 * @see wicket_order_touchpoint_products()
 * @see atlas://packages/wicket-wp-base-plugin/touchpoints-subsystem.md
 */
function woocommerce_order_touchpoint($order_id, $order = null)
{

    // ---------------------------------------------------------------------------------------
    // Load the order if we need to. It comes with woocommerce_new_order but not woocommerce_order_status_changed.
    // This is important because if we try to just load the order using wc_get_order for new orders, the line items aren't yet available for pending status.
    // Also get the user attached to the order so we can write the touchpoint against them
    // ---------------------------------------------------------------------------------------
    $order ??= wc_get_order($order_id);

    if (!$order) {
        return;
    }

    // The person this order belongs to. Usually the customer's user_login, which on a
    // Wicket site is their UUID, but not for users created outside SSO: see
    // wicket_person_uuid_for_order(), which falls back to the billing email.
    $order_user_uuid = wicket_person_uuid_for_order($order);
    // _wc_org_uuid may be absent (customer-less orders get no org) or legacy
    // string-shaped; guard before indexing (WWID-2605).
    $order_org_meta = $order->get_meta('_wc_org_uuid');
    $org_name = is_array($order_org_meta) ? ($order_org_meta['name'] ?? '') : '';

    // ---------------------------------------------------------------------------------------
    // Do not run for subscriptions, which are also considered orders kinda.
    // We were getting duplicate touchpoints and it seemed that the hooks above were also running
    // for subscriptions, likely the woocommerce_new_order one?
    // ---------------------------------------------------------------------------------------
    if (get_class($order) == 'WC_Subscription') {
        return;
    }

    // ---------------------------------------------------------------------------------------
    // No person, no touchpoint. Posting anyway just earns a 404 "Person not found" from
    // the MDP, which is what used to happen for guest orders and for customers whose
    // WordPress username is not a UUID. Log it so the order can be reconciled by hand.
    // ---------------------------------------------------------------------------------------
    if ($order_user_uuid === '') {
        Wicket()->log()->error(
            sprintf(
                'Skipped order touchpoint for order %d: no MDP person for customer %d (%s).',
                (int) $order->get_id(),
                (int) $order->get_user_id(),
                $order->get_billing_email() ?: 'no billing email'
            ),
            ['source' => 'wicket-base']
        );

        return;
    }

    // ---------------------------------------------------------------------------------------
    // Load needed order info
    // ---------------------------------------------------------------------------------------
    $order_id = $order->id;
    $order_status = $order->status;
    $order_edit_url = $order->get_edit_order_url();
    $order_payment_method = $order->payment_method;
    $order_total = $order->get_total();
    $currency_code = $order->get_currency();
    $currency_symbol = get_woocommerce_currency_symbol($currency_code);

    // ---------------------------------------------------------------------------------------
    // Build action of touchpoint
    // ---------------------------------------------------------------------------------------
    $action_map = [
        'completed'  => 'Order Completed',
        'on-hold'    => 'Order On-Hold',
        'refunded'   => 'Order Refunded',
        'cancelled'  => 'Order Cancelled',
        'processing' => 'Order Processing',
        'pending'    => 'Order Pending',
        'deleted'    => 'Order Deleted',
        'failed'     => 'Order Failed',
    ];
    $action = $action_map[$order_status];

    // ---------------------------------------------------------------------------------------
    // Build details of touchpoint
    // ---------------------------------------------------------------------------------------
    $products = wicket_order_touchpoint_products($order, $currency_symbol);

    $details = "Order ID: [$order_id]($order_edit_url) <br>"; // needs to be markdown for the MDP
    $details .= "Order Total: $currency_symbol$order_total $currency_code <br>";
    $details .= 'Org Name: ' . $org_name . '<br>';
    $details .= '<br>';
    $details .= 'Product(s): <br>';
    $details .= $products['details'];

    // ---------------------------------------------------------------------------------------
    // Build data of touchpoint
    // ---------------------------------------------------------------------------------------
    $data = [
        'order_id'              => $order_id,
        'order_total'           => $order_total,
        'organization_id'       => $order_org_meta['uuid'] ?? '',
        'organization_name'     => $order_org_meta['name'] ?? '',
        'products'              => $products['meta'],
        'order_status'          => ucwords($order_status),
        'order_edit_link'       => $order_edit_url,
        'order_currency'        => $currency_code,
        'order_currency_symbol' => $currency_symbol,
    ];

    // ---------------------------------------------------------------------------------------
    // Build touchpoint params
    // ---------------------------------------------------------------------------------------
    $params = [
        'person_id' => $order_user_uuid,
        'details'   => $details,
        'action'    => $action,
        'data'      => $data,
    ];

    // ---------------------------------------------------------------------------------------
    // Need to account for new orders created with changed statuses and without
    // By default, orders start as status = pending payment. Admins could change the status before clicking create, or not.
    // If they do, it would result in a status change hook fired in addition to woocommerce_new_order
    // This logic below is used on the MDP side to filter out duplicates
    // We are also looking to ignore statuses going back and forth. This is very important to understand! Any repeats of external_event_id values will be ignored
    // So for example, if an order goes back and forth between statuses, THIS WILL ONLY RECORD THE FIRST INSTANCE OF THAT STATUS!
    // ---------------------------------------------------------------------------------------
    $externalEventIdParts = [$order_id, $order_status];
    if ($order_status === 'completed') {
        // we're using $data instead of date_completed so that it's more unique. People were flipping it back and for the complete and we were getting duplicate touchpoints
        $externalEventIdParts[] = hash('sha256', implode($data));
    }
    $params['external_event_id'] = implode('_', $externalEventIdParts);

    // ---------------------------------------------------------------------------------------
    // Configure a toolbox of values that a dev could use to override.
    // Use this instead of passing individual values as per the docs because this could be added to in the future
    // ex:
    // add_filter('alter_woocommerce_order_touchpoint', 'my_callback', 9999, 2);
    // function my_callback($params, $values_arr){
    //   $params['details'] = 'new details in here';
    //   return $params;
    // }
    // ---------------------------------------------------------------------------------------
    $values_arr = ['order' => $order];
    $params = apply_filters('alter_woocommerce_order_touchpoint', $params, $values_arr);
    write_touchpoint($params, get_create_touchpoint_service_id('WooCommerce', 'WooCommerce is an open-source e-commerce plugin for WordPress.', 'woo_commerce'));
}

/**
 * Builds the product part of an order touchpoint: a data row and a details block per line item.
 *
 * Each block links the product's edit screen rather than its page, since the frontend can
 * redirect away from some products, such as memberships. The block then lists the product's
 * categories, its line amount and its short description, trimmed to 25 words. The
 * Description line is left out when the product has no short description.
 *
 * Line items whose product no longer exists are skipped.
 *
 * @param WC_Order $order           The order whose line items are listed.
 * @param string   $currency_symbol Symbol shown before each line amount, as returned by get_woocommerce_currency_symbol().
 *
 * @return array{meta: array<int, array<string, mixed>>, details: string} The rows for the touchpoint data's products key, and the markdown blocks for its details.
 *
 * @see atlas://packages/wicket-wp-base-plugin/touchpoints-subsystem.md
 */
function wicket_order_touchpoint_products($order, $currency_symbol): array
{
    $coupons = implode(', ', $order->get_used_coupons());
    $line_item_meta = [];
    $products_list = '';

    foreach ($order->get_items() as $item_id => $line_item) {
        $product = $line_item->get_product();

        if (!$product) {
            continue;
        }

        // A variation has no categories or short description of its own, so both come from its parent.
        $product_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
        $parent_product = wc_get_product($product_id);
        $product_name = $product->get_name();
        $product_desc = wp_trim_words($parent_product->get_short_description(), 25);
        $product_edit_url = admin_url("post.php?post=$product_id&action=edit");
        $product_category_ids = $parent_product->get_category_ids();
        $product_category_names = [];

        foreach ($product_category_ids as $cat_id) {
            $cat_term = get_term_by('id', (int) $cat_id, 'product_cat');
            if ($cat_term) {
                $product_category_names[] = $cat_term->name;
            }
        }

        $item_quantity = $line_item->get_quantity();
        $item_total = $line_item->get_total();
        $product_amount = number_format($item_total, 2);
        $line_item_meta[] = [
            'product_id'       => $product_id,
            'product_name'     => $product_name,
            'product_desc'     => $product_desc,
            'product_category' => $product_category_names,
            'quantity'         => $item_quantity,
            'product_amount'   => $product_amount,
            'coupon_code'      => $coupons,
        ];

        $products_list .= " &#8226; [$product_name]($product_edit_url)<br>";
        $products_list .= ' &nbsp;&nbsp; - Product Category: ' . implode(', ', $product_category_names) . '<br>';
        $products_list .= " &nbsp;&nbsp; - Product Amount: $currency_symbol$product_amount<br>";

        if ($product_desc !== '') {
            $products_list .= " &nbsp;&nbsp; - Description: $product_desc<br>";
        }
    }

    return [
        'meta'    => $line_item_meta,
        'details' => $products_list,
    ];
}

// ---------------------------------------------------------------------------------------
// Setup an action for partial refunds. We already capture full refunds above since an order
// changes status to "Refunded" when the full amount is applied. Otherwise we need this below.
// A partial refund is just any refund amount that is not the full amount. The order otherwise stays in the current status
// ---------------------------------------------------------------------------------------
add_action('woocommerce_order_partially_refunded', 'woocommerce_order_partially_refunded_touchpoint', 9999, 2);

function woocommerce_order_partially_refunded_touchpoint($order_id, $refund_id)
{
    $order = wc_get_order($order_id);

    if (!$order) {
        return;
    }

    // Same person lookup as the order touchpoint above: user_login is the UUID on an
    // SSO-provisioned account, and the billing email is the fallback for anything else.
    $order_user_uuid = wicket_person_uuid_for_order($order);

    if ($order_user_uuid === '') {
        Wicket()->log()->error(
            sprintf(
                'Skipped partial-refund touchpoint for order %d: no MDP person for customer %d (%s).',
                (int) $order->get_id(),
                (int) $order->get_user_id(),
                $order->get_billing_email() ?: 'no billing email'
            ),
            ['source' => 'wicket-base']
        );

        return;
    }

    $order_org_meta = $order->get_meta('_wc_org_uuid');
    $org_name = is_array($order_org_meta) ? ($order_org_meta['name'] ?? '') : '';
    $net_payment_remaining = $order->get_remaining_refund_amount();
    $refund = wc_get_order($refund_id);
    $amount_refunded = $refund->get_amount();

    // ---------------------------------------------------------------------------------------
    // Load needed order info
    // ---------------------------------------------------------------------------------------
    $order_id = $order->id;
    $order_status = $order->status;
    $order_edit_url = $order->get_edit_order_url();
    $order_payment_method = $order->payment_method;
    $order_total = $order->get_total();
    $currency_code = $order->get_currency();
    $currency_symbol = get_woocommerce_currency_symbol($currency_code);

    $action = 'Order Partially Refunded';

    // ---------------------------------------------------------------------------------------
    // Build details of touchpoint
    // ---------------------------------------------------------------------------------------
    $details = "Order ID: [$order_id]($order_edit_url) <br>"; // needs to be markdown for the MDP
    $details .= "Refund Total: $amount_refunded $currency_code <br>";
    $details .= 'Order Total After Refund: ' . $net_payment_remaining . ' <br>';

    // ---------------------------------------------------------------------------------------
    // Build data of touchpoint
    // ---------------------------------------------------------------------------------------
    $data = [
        'order_id'              => $order_id,
        'refund_total'          => $amount_refunded,
        'total_after_refunds'   => $net_payment_remaining,
        'order_currency'        => $currency_code,
        'order_currency_symbol' => $currency_symbol,
    ];

    // ---------------------------------------------------------------------------------------
    // Build touchpoint params
    // ---------------------------------------------------------------------------------------
    $params = [
        'person_id' => $order_user_uuid,
        'details'   => $details,
        'action'    => $action,
        'data'      => $data,
    ];

    // ---------------------------------------------------------------------------------------
    // Configure a toolbox of values that a dev could use to override.
    // Use this instead of passing individual values as per the docs because this could be added to in the future
    // ex:
    // add_filter('alter_woocommerce_order_partially_refunded_touchpoint', 'my_callback', 9999, 2);
    // function my_callback($params, $values_arr){
    //   $params['details'] = 'new details in here';
    //   return $params;
    // }
    // ---------------------------------------------------------------------------------------
    $values_arr = [
        'order'  => $order,
        'refund' => $refund,
    ];
    $params = apply_filters('alter_woocommerce_order_partially_refunded_touchpoint', $params, $values_arr);
    write_touchpoint($params, get_create_touchpoint_service_id('WooCommerce', 'WooCommerce is an open-source e-commerce plugin for WordPress.', 'woo_commerce'));
}
