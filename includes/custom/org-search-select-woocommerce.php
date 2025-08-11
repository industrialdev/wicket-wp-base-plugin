<?php

// No direct access
defined('ABSPATH') || exit;

/**
 * Adding persistent role to WC API for mdp user sync
 * @param mixed $allowed_roles
 */
function wicket_wc_api_allowed_roles($allowed_roles)
{
  $allowed_roles[] = 'user';
  return $allowed_roles;
}
add_filter('woocommerce_rest_customer_allowed_roles', 'wicket_wc_api_allowed_roles', 10, 1);

/**
 * Handle organization role assignment when an order is completed.
 *
 * Processes user meta data to assign roster management and organization editor roles
 * based on previously stored organization UUIDs. Cleans up temporary meta data after
 * role assignment.
 *
 * @since 1.0.0
 * @param int $order_id The WooCommerce order ID
 * @return bool|void Returns true on success, void if no roles to assign
 */
function wicket_org_search_select_on_order_complete($order_id)
{
  $order = wc_get_order($order_id);
  $order_user_id = $order ? (int) $order->get_user_id() : 0;

  // If order has no customer user, try resolve by billing email
  if ($order_user_id === 0 && $order) {
    $billing_email = $order->get_billing_email();
    if (!empty($billing_email)) {
      $maybe_user = get_user_by('email', $billing_email);
      if ($maybe_user && !is_wp_error($maybe_user)) {
        $order_user_id = (int) $maybe_user->ID;
      }
    }
  }

  // Bail if we still cannot resolve a customer
  if ($order_user_id === 0) {
    return;
  }

  // Resolve Wicket person UUID from user login or helper
  $current_person_uuid = wicket_current_person_uuid();

  // Only proceed if order contains a membership product.
  if ($order instanceof WC_Order) {
    $has_membership_like_item = false;

    foreach ($order->get_items() as $item) {
      $product = $item->get_product();
      if (!$product) { continue; }

      // Prefer native product-type checks for Woo Subscriptions products
      // Ref: https://stackoverflow.com/questions/66950465/woocommerce-subscriptions-check-if-product-type-is-a-simple-subscription
      // Only check for simple subscription here; other types not present in this codebase
      if ($product->is_type('subscription')) {
        $has_membership_like_item = true;
        break;
      }

      // Fallback via Subscriptions class helper if available
      if (class_exists('WC_Subscriptions_Product') && \WC_Subscriptions_Product::is_subscription($product)) {
        $has_membership_like_item = true;
        break;
      }

      // Fallback via meta check if needed
      $maybe_membership_flag = $product->get_meta('_is_membership_product', true);
      if (!empty($maybe_membership_flag)) {
        $has_membership_like_item = true;
        break;
      }
    }

    // If you want to enforce the check strictly, uncomment the next 2 lines
    // if (!$has_membership_like_item) {
    //   return; // No qualifying items; skip role grants
    // }
  }

  $org_uuid_for_roster_man_access = get_user_meta($order_user_id, 'wicket_roster_man_org_to_grant', true);
  $org_uuid_for_org_editor_access = get_user_meta($order_user_id, 'wicket_org_editor_org_to_grant', true);

  // Don't proceed if no UUID is set in the user meta
  if ((!isset($org_uuid_for_roster_man_access) || empty($org_uuid_for_roster_man_access)) && (!isset($org_uuid_for_org_editor_access) || empty($org_uuid_for_org_editor_access))) { // Check both meta
    return;
  }

  // Assign Roster Manager role if needed
  if (isset($org_uuid_for_roster_man_access) && !empty($org_uuid_for_roster_man_access)) {
    // Assign roles
    wicket_assign_role($current_person_uuid, 'membership_manager', $org_uuid_for_roster_man_access);

    // Clean up after ourselves now that we've actioned the meta's value
    delete_user_meta($order_user_id, 'wicket_roster_man_org_to_grant');
  }

  // Assign Org Editor role if needed
  if (isset($org_uuid_for_org_editor_access) && !empty($org_uuid_for_org_editor_access)) {
    // Assign role
    wicket_assign_role($current_person_uuid, 'org_editor', $org_uuid_for_org_editor_access);

    // Clean up after ourselves now that we've actioned the meta's value
    delete_user_meta($order_user_id, 'wicket_org_editor_org_to_grant'); // Delete new meta
  }

  return true;
}
add_action('woocommerce_order_status_processing', 'wicket_org_search_select_on_order_complete', 10, 1); // Switched from woocommerce_payment_complete
add_action('woocommerce_order_status_completed', 'wicket_org_search_select_on_order_complete', 10, 1);

/**
 * Add 'pay_for_order' capability to administrator role.
 *
 * Allows administrators to view and use the 'pay for order' screen
 * for customer orders in WooCommerce.
 *
 * @since 1.0.0
 * @return void
 */
function wicket_allow_admin_to_pay_for_order()
{
  $administrator = get_role('administrator');
  $administrator->add_cap('pay_for_order');
}
add_action('init', 'wicket_allow_admin_to_pay_for_order');

/**
 * Filter WooCommerce order editability for checkout-draft status.
 *
 * Makes orders with 'checkout-draft' status editable in WooCommerce admin.
 * Based on solution from Stack Overflow.
 * Ref: https://stackoverflow.com/a/68256196
 *
 * @since 1.0.0
 * @param bool     $editable Whether the order is editable
 * @param WC_Order $order    The WooCommerce order object
 * @return bool True if order is checkout-draft, otherwise original editable value
 */
function wicket_filter_wc_order_is_editable($editable, $order)
{
  // Compare
  if ($order->get_status() == 'checkout-draft') {
    $editable = true;
  }

  return $editable;
}
add_filter('wc_order_is_editable', 'wicket_filter_wc_order_is_editable', 10, 2);

// Assign organization ID to order on order create based on certain person-to-org relationships that are set in the base plugin
add_action('woocommerce_order_status_changed', 'wicket_write_org_id_to_order', 9999, 2);
add_action('woocommerce_new_order', 'wicket_write_org_id_to_order', 9999, 2);
if (!empty($person_to_org_types = wicket_get_option('wicket_admin_settings_woo_person_to_org_types'))) {
  add_action('woocommerce_admin_order_data_after_order_details', 'wicket_display_org_input_on_order', 10, 1);
  add_action('admin_enqueue_scripts', 'wicket_enqueue_wc_org_scripts');
  add_action('wp_ajax_wc_org_search', 'wicket_handle_wc_org_search');
  add_action('woocommerce_update_order', 'wicket_set_wc_org_uuid');
}

/**
 * Set organization UUID for a WooCommerce order from admin request.
 *
 * Updates the order meta with organization information when an admin
 * selects an organization from the order edit screen.
 *
 * @since 1.0.0
 * @param int $order_id The WooCommerce order ID
 * @return void
 */
function wicket_set_wc_org_uuid($order_id)
{
  if (isset($_REQUEST['wicket_wc_org_select_uuid']) && $_REQUEST['wicket_wc_org_select_uuid'] != '') {
    $wicket_org = wicket_get_organization($_REQUEST['wicket_wc_org_select_uuid']);
    $org['name'] = $wicket_org['data']['attributes']['legal_name'];
    $org['uuid'] = $_REQUEST['wicket_wc_org_select_uuid'];
    update_post_meta($order_id, '_wc_org_uuid', $org);
    $order = wc_get_order($order_id);
    if (!empty($order)) {
      $order->update_meta_data('_wc_org_uuid', $org);
    }
  }
}

/**
 * Write organization ID to order based on person-to-org relationships.
 *
 * Automatically associates an organization with an order based on
 * the customer's organizational relationships. Only writes if no
 * organization is already associated.
 *
 * @since 1.0.0
 * @param int $order_id The WooCommerce order ID
 * @return void
 */
function wicket_write_org_id_to_order($order_id)
{
  $order = wc_get_order($order_id);
  $org_meta_exists = get_post_meta($order_id, '_wc_org_uuid', true);
  if (!empty($org_meta_exists['uuid']) && !empty($org_meta_exists['name'])) {
    return;
  }

  //here we specify to get the user for relation from the order owner
  $id_array = ['order_id' => $order_id];
  $organizations = get_organizations_based_on_certain_types($id_array);

  if ($organizations) {
    // just use the first one...should be all we need based on the sorting above
    $org_uuid = key($organizations);
    $org_name = reset($organizations);

    //file_put_contents('php://stdout', '----------------------------------------------------------------');
    //file_put_contents('php://stdout', print_r($org_uuid, true));
    //file_put_contents('php://stdout', '----------------------------------------------------------------');
    //file_put_contents('php://stdout', print_r($org_name, true));

    $org['uuid'] = $org_uuid;
    $org['name'] = $org_name;
    update_post_meta($order_id, '_wc_org_uuid', $org);
    if (!empty($order)) {
      $order->update_meta_data('_wc_org_uuid', $org);
    }
  }
}

/**
 * Display organization input field on WooCommerce order edit screen.
 *
 * Renders an organization search input field in the admin order edit
 * screen, allowing administrators to search and select organizations
 * to associate with orders.
 *
 * @since 1.0.0
 * @param WC_Order $order The WooCommerce order object
 * @return void
 */
function wicket_display_org_input_on_order($order)
{
  //delete_post_meta( $order->get_id(), '_wc_org_uuid' );exit;

  $org = get_post_meta($order->get_id(), '_wc_org_uuid', true);
  if (empty($org) || !is_array($org)) {
    $org = [
      'name' => '',
      'uuid' => '',
    ];
  }
  //A fallback was or can be used for setting directly from MDP  when order page loaded
  /*
    $organizations = get_organizations_based_on_certain_types();
    if ($organizations) {
      // just use the first one...should be all we need based on the sorting above
      $org['uuid'] = key($organizations);
      $org['name'] = reset($organizations);
      update_post_meta( $order->get_id(), '_wc_org_uuid', $org);
    }
  }
  */
  wp_nonce_field('wc_org_nonce', 'wc_org_nonce_field');
?>
  <p class="form-field form-field-wide wc-org-uuid"></p>
  <label for="wc-org-search">Organization Name:</label><br />
  <input id="wc-org-search" class="woocommerce-input" name="wc_org_uuid" type="text" value="<?php echo $org['name']; ?>">
  <input type="hidden" id="wc-org-search-id" name="wicket_wc_org_select_uuid" value="<?php echo $org['uuid']; ?>">
  <div id="wc-org-results" class="woocommerce-results"></div>
  </p>
  <style>
    /* Container for the search input and results */
    .search-container {
      position: relative;
      /* For positioning the results */
    }

    #wc-org-search {
      font-size: 11pt;
    }

    /* Style the input field */
    .woocommerce-input {
      width: 100%;
      /* Full width */
      padding: 12px;
      /* Padding for comfortable click area */
      border: 1px solid #ccc;
      /* Border color */
      border-radius: 4px;
      /* Rounded corners */
      background-color: #fff;
      /* Background color */
      font-size: 16px;
      /* Font size */
      color: #333;
      /* Text color */
      transition: border-color 0.3s ease;
      /* Smooth border transition */
    }

    /* Input focus style */
    .woocommerce-input:focus {
      border-color: #0071a1;
      /* Change border color on focus */
      outline: none;
      /* Remove default outline */
    }

    /* Style for results container */
    .woocommerce-results {
      /*position: absolute; /* Position results below the input */
      top: 100%;
      /* Align to the bottom of the input */
      left: 0;
      /* Align to the left */
      right: 0;
      /* Stretch to the right */
      background-color: #fff;
      /* Background color */
      border: 1px solid #ccc;
      /* Border around results */
      border-radius: 4px;
      /* Rounded corners */
      z-index: 999;
      /* Ensure it appears above other elements */
      max-height: 200px;
      /* Limit height for scrolling */
      overflow-y: auto;
      /* Scroll if too many results */
      display: none;
      /* Initially hidden */
    }

    /* Individual result item */
    .woocommerce-results .result-item {
      padding: 10px;
      /* Padding for items */
      cursor: pointer;
      /* Pointer cursor on hover */
      color: #333;
      /* Text color */
    }

    /* Hover effect for result items */
    .woocommerce-results .result-item:hover {
      background-color: #f7f7f7;
      /* Change background on hover */
    }

    /* No results found message */
    .woocommerce-results .no-results {
      padding: 10px;
      /* Padding for no results */
      color: #999;
      /* Color for no results text */
      text-align: center;
      /* Center text */
    }
  </style>
<?php
}

/**
 * Enqueue JavaScript files for WooCommerce organization functionality.
 *
 * Loads jQuery and custom JavaScript for organization search and
 * selection in WooCommerce admin order screens.
 *
 * @since 1.0.0
 * @return void
 */
function wicket_enqueue_wc_org_scripts()
{
  wp_enqueue_script('jquery');
  wp_enqueue_script('custom-wc-org', plugins_url('../../assets/js/wicket_wc_org.js', __FILE__), array('jquery'), null, true);
  wp_localize_script('custom-wc-org', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
}

/**
 * Handle AJAX request for WooCommerce organization search.
 *
 * Processes AJAX search requests for organizations and returns
 * results in JSON format for autocomplete functionality.
 *
 * @since 1.0.0
 * @return void Sends JSON response via wp_send_json()
 */
function wicket_handle_wc_org_search()
{
  check_ajax_referer('wc_org_nonce', 'nonce');
  $search_term = isset($_POST['term']) ? sanitize_text_field($_POST['term']) : '';
  $search_json = json_encode(['searchTerm' => $search_term, 'autocomplete' => true]);
  $request = new \WP_REST_Request('POST');
  $request->set_headers(['Content-Type' => 'application/json']);
  $request->set_body($search_json); // Set the body as the JSON string
  $results = wicket_internal_endpoint_search_orgs($request);
  wp_reset_postdata();
  wp_send_json($results);
}

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
 * Example usage:   $new_order = wicket_create_wc_order($customer_uuid, $product_ids, ['item_meta' => ['_org_uuid' => $org_uuid]]);
 */
function wicket_create_wc_order($customer_uuid, $product_ids, $args)
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
    $subscription = wcs_create_subscription(array(
      'order_id' => $order->get_id(),
      'customer_id' => $customer_wp_id,
      'billing_period' => $args['subscription_billing_period'],
      'billing_interval' => $args['subscription_billing_interval'],
      'start_date' => $args['subscription_start_date'],
    ));
    if (is_wp_error($subscription)) {

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
