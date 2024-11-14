<?php 

add_action( 'woocommerce_order_status_processing', 'wicket_org_search_select_on_order_complete', 10, 1); // Switched from woocommerce_payment_complete and woocommerce_order_status_completed

function wicket_org_search_select_on_order_complete( $order_id ) {
  //$order               = wc_get_order( $order_id );
  $current_user_wp_id  = get_current_user_id();
  $current_person_uuid = wicket_current_person_uuid();

  // TODO: Confirm if a membership was just purchased and only proceed if so;
  // right now this assumes that the user selected an org and their immediate next checkout
  // must be related to their onboarding flow

  $org_uuid_for_roster_man_access = get_user_meta( $current_user_wp_id, 'roster_man_org_to_grant', true );

  // Don't proceed if no UUID is set in the user meta
  if( !isset( $org_uuid_for_roster_man_access ) || empty( $org_uuid_for_roster_man_access ) ) {
    return;
  }

  // Assign roles
  wicket_assign_role($current_person_uuid, 'membership_manager', $org_uuid_for_roster_man_access);
  //wicket_assign_role($current_person_uuid, 'org_editor', $org_uuid_for_roster_man_access);

  // Clean up after ourselves now that we've actioned the meta's value
  delete_user_meta( $current_user_wp_id, 'roster_man_org_to_grant' );

  return true;
}

// ---------------------------------------------------------------------------------------
// Allow admins to view (and use if needed) the 'pay for order' screen for a customer's order
// ---------------------------------------------------------------------------------------
function allow_admin_to_pay_for_order(){
  $administrator = get_role('administrator');
  $administrator->add_cap( 'pay_for_order' );
}
add_action('init', 'allow_admin_to_pay_for_order');

// ---------------------------------------------------------------------------------------
// Allow draft status to be editable
// Ref: https://stackoverflow.com/a/68256196
// ---------------------------------------------------------------------------------------
function wicket_filter_wc_order_is_editable( $editable, $order ) {
  // Compare
  if ( $order->get_status() == 'checkout-draft' ) {
      $editable = true;
  }
  
  return $editable;
}
add_filter( 'wc_order_is_editable', 'wicket_filter_wc_order_is_editable', 10, 2 );

// ---------------------------------------------------------------------------------------
// assign organization ID to order on order create based on certain person-to-org relationships that are set in the base plugin 
// ---------------------------------------------------------------------------------------
add_action('woocommerce_new_order', 'write_org_id_to_order', 9999, 2);
add_action('woocommerce_admin_order_data_after_order_details', 'wicket_display_org_input_on_order', 10, 1 );
add_action('admin_enqueue_scripts', 'wicket_enqueue_wc_org_scripts' );
add_action('wp_ajax_wc_org_search', 'wicket_handle_wc_org_search' );
add_action('save_post', 'wicket_set_wc_org_uuid');

function wicket_set_wc_org_uuid( $order_id ) {
  if(get_post_type($order_id) == 'shop_order' && !empty($_REQUEST['wicket_wc_org_select_uuid'])) {
    $wicket_org = wicket_get_organization($_REQUEST['wicket_wc_org_select_uuid'] );
    $org['name'] = $wicket_org['data']['attributes']['legal_name'];
    $org['uuid'] = $_REQUEST['wicket_wc_org_select_uuid'];
    update_post_meta( $order_id, '_wc_org_uuid', $org);
  }  
}

function write_org_id_to_order($order_id, $order) {
  $organizations = get_organizations_based_on_certain_types();

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
    update_post_meta( $order_id, '_wc_org_uuid', $org);
  }
}

function wicket_display_org_input_on_order( $order ) {
  $org = get_post_meta( $order->get_id(), '_wc_org_uuid', true);
  if(empty($org) || !is_array($org)) {
    $org = [
      'name' => '',
      'uuid' => '',
    ];
    $organizations = get_organizations_based_on_certain_types();
    if ($organizations) {
      // just use the first one...should be all we need based on the sorting above
      $org['uuid'] = key($organizations);
      $org['name'] = reset($organizations);
      update_post_meta( $order->get_id(), '_wc_org_uuid', $org);
    }
  }
  wp_nonce_field('wc_org_nonce', 'wc_org_nonce_field');

  ?>
  <p class="form-field form-field-wide wc-org-uuid"></p>
    <label for="wc-org-search">Organization Name:</label><br />
    <input id="wc-org-search" class="woocommerce-input" name="wc_org_uuid" type="text" value="<?php echo $org['name']; ?>">
      <input type="hidden" id="wc-org-search-id" name="wicket_wc_org_select_uuid" value="<?php echo $org['uuid'];?>">
    <div id="wc-org-results" class="woocommerce-results"></div>
  </p>
  <style>
      /* Container for the search input and results */
      .search-container {
          position: relative; /* For positioning the results */
      }

      #wc-org-search {
        font-size: 11pt;
      }

      /* Style the input field */
      .woocommerce-input {
          width: 100%; /* Full width */
          padding: 12px; /* Padding for comfortable click area */
          border: 1px solid #ccc; /* Border color */
          border-radius: 4px; /* Rounded corners */
          background-color: #fff; /* Background color */
          font-size: 16px; /* Font size */
          color: #333; /* Text color */
          transition: border-color 0.3s ease; /* Smooth border transition */
      }

      /* Input focus style */
      .woocommerce-input:focus {
          border-color: #0071a1; /* Change border color on focus */
          outline: none; /* Remove default outline */
      }

      /* Style for results container */
      .woocommerce-results {
          /*position: absolute; /* Position results below the input */
          top: 100%; /* Align to the bottom of the input */
          left: 0; /* Align to the left */
          right: 0; /* Stretch to the right */
          background-color: #fff; /* Background color */
          border: 1px solid #ccc; /* Border around results */
          border-radius: 4px; /* Rounded corners */
          z-index: 999; /* Ensure it appears above other elements */
          max-height: 200px; /* Limit height for scrolling */
          overflow-y: auto; /* Scroll if too many results */
          display: none; /* Initially hidden */
      }

      /* Individual result item */
      .woocommerce-results .result-item {
          padding: 10px; /* Padding for items */
          cursor: pointer; /* Pointer cursor on hover */
          color: #333; /* Text color */
      }

      /* Hover effect for result items */
      .woocommerce-results .result-item:hover {
          background-color: #f7f7f7; /* Change background on hover */
      }

      /* No results found message */
      .woocommerce-results .no-results {
          padding: 10px; /* Padding for no results */
          color: #999; /* Color for no results text */
          text-align: center; /* Center text */
      }
    </style>
  <?php
}

function wicket_enqueue_wc_org_scripts() {
  wp_enqueue_script('jquery');
  wp_enqueue_script('custom-wc-org', plugins_url('../../assets/js/wicket_wc_org.js', __FILE__), array('jquery'), null, true);
  wp_localize_script('custom-wc-org', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
}

function wicket_handle_wc_org_search() {
  check_ajax_referer('wc_org_nonce', 'nonce');
  $search_term = isset($_POST['term']) ? sanitize_text_field($_POST['term']) : '';
  $search_json = json_encode(['searchTerm' => $search_term, 'autocomplete' => true]);  
  $request = new \WP_REST_Request('POST');
  $request->set_headers(['Content-Type' => 'application/json']);
  $request->set_body($search_json); // Set the body as the JSON string
  $results = wicket_internal_endpoint_search_orgs( $request);
  wp_reset_postdata();
  wp_send_json($results);
}
