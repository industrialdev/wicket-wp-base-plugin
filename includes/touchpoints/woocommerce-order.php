<?php

// ---------------------------------------------------------------------------------------
// setup order hooks. We need both since status changed only runs for existing orders
// ---------------------------------------------------------------------------------------
add_action('woocommerce_order_status_changed', 'woocommerce_order_touchpoint');
add_action('woocommerce_new_order', 'woocommerce_order_touchpoint');

function woocommerce_order_touchpoint($order_id) {

  // ---------------------------------------------------------------------------------------
  // load order and get the user attached to it so we can write the touchpoint against them
  // ---------------------------------------------------------------------------------------
  $order           = wc_get_order($order_id);
  $order_user      = get_user_by( 'id', $order->get_user_id());
  $order_user_uuid = $order_user->user_login;

  // ---------------------------------------------------------------------------------------
  // load needed order info
  // ---------------------------------------------------------------------------------------
  $order_id             = $order->id;
  $order_status         = $order->status;
  $order_edit_url       = $order->get_edit_order_url();
  $order_payment_method = $order->payment_method;
  $order_total          = $order->get_total();
  $currency_code        = $order->get_currency();  
  $currency_symbol      = get_woocommerce_currency_symbol( $currency_code );

  // ---------------------------------------------------------------------------------------
  // build action of touchpoint
  // ---------------------------------------------------------------------------------------
  $action_map = [
    'completed'  => "Order Marked Completed",
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
  // build details of touchpoint
  // ---------------------------------------------------------------------------------------
  $line_item_meta = [];
  $products_list  = [];
  foreach($order->get_items() as $item_id => $line_item){
    $product = $line_item->get_product();
    $product_name = $product->get_name();
    $item_quantity = $line_item->get_quantity();
    $item_total = $line_item->get_total();
    $line_item_meta[] = [
      'product_name' => $product_name, 
      'quantity' => $item_quantity, 
      'item_total' => number_format( $item_total, 2 ) 
    ];
    $products_list[] = $product_name;
  }

  $products_imploded = implode('<br>',$products_list);
  $products_link = "[$products_imploded]($order_edit_url)"; // needs to be markdown for the MDP
  $details  = "Order Total: $currency_symbol $order_total $currency_code <br>";
  $details .= "Order Status: ".ucwords($order_status)." <br>";
  // we do not have the products available when the order is pending for some reason, so don't try and write them if it's pending
  if ($order_status != 'pending') {
    $details .= "Product(s) Ordered: $products_link";
  }

  // ---------------------------------------------------------------------------------------
  // build data of touchpoint
  // ---------------------------------------------------------------------------------------
  $data = [
    'OrderID'             => $order_id,
    'Products'            => $products_list,
    'LineItems'           => $line_item_meta,
    'OrderTotal'          => $order_total,
    'OrderStatus'         => ucwords($order_status),
    'OrderEditLink'       => $order_edit_url,
    'OrderCurrency'       => $currency_code,
    'OrderCurrencySymbol' => $currency_symbol,
  ];

  // ---------------------------------------------------------------------------------------
  // build touchpoint params
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

  write_touchpoint($params, get_create_touchpoint_service_id('WooCommerce', 'WooCommerce is an open-source e-commerce plugin for WordPress.'));
}

