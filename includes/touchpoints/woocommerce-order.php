<?php

// ---------------------------------------------------------------------------------------
// setup order hooks. We need both since status changed only runs for existing orders
// ---------------------------------------------------------------------------------------
add_action('woocommerce_order_status_changed', 'woocommerce_order_touchpoint', 9999);
add_action('woocommerce_new_order', 'woocommerce_order_touchpoint', 9999, 2);

function woocommerce_order_touchpoint($order_id, $order = null) {

  // ---------------------------------------------------------------------------------------
  // Load the order if we need to. It comes with woocommerce_new_order but not woocommerce_order_status_changed.
  // This is important because if we try to just load the order using wc_get_order for new orders, the line items aren't yet available for pending status.
  // Also get the user attached to the order so we can write the touchpoint against them
  // ---------------------------------------------------------------------------------------
  $order           = $order ?? wc_get_order($order_id);
  $order_user      = get_user_by( 'id', $order->get_user_id());
  $order_user_uuid = $order_user->user_login;
  $order_org_meta  = get_post_meta($order->id, '_wc_org_uuid', true);
  $org_name        = $order_org_meta['name'] ?? '';

  // ---------------------------------------------------------------------------------------
  // Do not run for subscriptions, which are also considered orders kinda. 
  // We were getting duplicate touchpoints and it seemed that the hooks above were also running
  // for subscriptions, likely the woocommerce_new_order one?
  // ---------------------------------------------------------------------------------------
  if (get_class($order) == 'WC_Subscription') {
    return;
  }

  // ---------------------------------------------------------------------------------------
  // Load needed order info
  // ---------------------------------------------------------------------------------------
  $order_id             = $order->id;
  $order_status         = $order->status;
  $order_edit_url       = $order->get_edit_order_url();
  $order_payment_method = $order->payment_method;
  $order_total          = $order->get_total();
  $currency_code        = $order->get_currency();  
  $currency_symbol      = get_woocommerce_currency_symbol( $currency_code );
  $coupons              = implode(', ', $order->get_used_coupons());  

  // ---------------------------------------------------------------------------------------
  // Build action of touchpoint
  // ---------------------------------------------------------------------------------------
  $action_map = [
    'completed'  => "Order Completed",
    'on-hold'    => "Order On-Hold",
    'refunded'   => "Order Refunded",
    'cancelled'  => "Order Cancelled",
    'processing' => "Order Processing",
    'pending'    => "Order Pending",
    'deleted'    => "Order Deleted",
    'failed'     => "Order Failed"
  ];
  $action = $action_map[$order_status];
  
  // ---------------------------------------------------------------------------------------
  // Build details of touchpoint
  // ---------------------------------------------------------------------------------------
  $line_item_meta = [];
  $products_list  = [];
  foreach($order->get_items() as $item_id => $line_item){
    $product            = $line_item->get_product();

    if(!$product){
      continue;
    }

    $product_id         = $product->get_id();
    $product_name       = $product->get_name();
    $product_categories = strip_tags($product->get_categories());
    $item_quantity      = $line_item->get_quantity();
    $item_total         = $line_item->get_total();
    $line_item_meta[]   = [
      'product_id'       => $product_id, 
      'product_name'     => $product_name, 
      'product_category' => $product_categories, 
      'quantity'         => $item_quantity, 
      'product_amount'   => number_format( $item_total, 2 ),
      'coupon_code'      => $coupons
    ];
    $products_list[] = $product_name;
  }

  $products_imploded = implode('<br>',$products_list);
  $products_link     = "[$products_imploded]($order_edit_url)"; // needs to be markdown for the MDP
  $details           = "Order ID: [$order_id]($order_edit_url) <br>";
  $details          .= "Order Total: $currency_symbol $order_total $currency_code <br>";
  $details          .= "Org Name: ".$org_name."<br>";
  $details          .= "Products: $products_link";

  // ---------------------------------------------------------------------------------------
  // Build data of touchpoint
  // ---------------------------------------------------------------------------------------
  $data = [
    'order_id'              => $order_id,
    'order_total'           => $order_total,
    'organization_id'       => $order_org_meta['uuid'] ?? '',
    'organization_name'     => $order_org_meta['name'] ?? '',
    'products'              => $line_item_meta,
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
    $externalEventIdParts[] = $order->get_date_completed()->format('c');
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

// ---------------------------------------------------------------------------------------
// Setup an action for partial refunds. We already capture full refunds above since an order
// changes status to "Refunded" when the full amount is applied. Otherwise we need this below.
// A partial refund is just any refund amount that is not the full amount. The order otherwise stays in the current status
// ---------------------------------------------------------------------------------------
add_action('woocommerce_order_partially_refunded', 'woocommerce_order_partially_refunded_touchpoint', 9999, 2);

function woocommerce_order_partially_refunded_touchpoint($order_id, $refund_id){
  $order                 = wc_get_order( $order_id );
  $order_user            = get_user_by( 'id', $order->get_user_id());
  $order_user_uuid       = $order_user->user_login;
  $order_org_meta        = $order->get_meta('_wc_org_uuid');
  $org_name              = $order_org_meta['name'] ?? '';
  $net_payment_remaining = $order->get_remaining_refund_amount();
  $refund                = wc_get_order( $refund_id );
  $amount_refunded       = $refund->get_amount();

  // ---------------------------------------------------------------------------------------
  // Load needed order info
  // ---------------------------------------------------------------------------------------
  $order_id             = $order->id;
  $order_status         = $order->status;
  $order_edit_url       = $order->get_edit_order_url();
  $order_payment_method = $order->payment_method;
  $order_total          = $order->get_total();
  $currency_code        = $order->get_currency();  
  $currency_symbol      = get_woocommerce_currency_symbol( $currency_code );

  $action = 'Order Partially Refunded';
  
  // ---------------------------------------------------------------------------------------
  // Build details of touchpoint
  // ---------------------------------------------------------------------------------------
  $details = "Order ID: [$order_id]($order_edit_url) <br>"; // needs to be markdown for the MDP
  $details .= "Refund Total: $amount_refunded $currency_code <br>";
  $details .= "Order Total After Refund: ".$net_payment_remaining." <br>";

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
    'refund' => $refund
  ];
  $params = apply_filters('alter_woocommerce_order_partially_refunded_touchpoint', $params, $values_arr);
  write_touchpoint($params, get_create_touchpoint_service_id('WooCommerce', 'WooCommerce is an open-source e-commerce plugin for WordPress.', 'woo_commerce'));
}


