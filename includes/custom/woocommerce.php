<?php 

add_action( 'woocommerce_payment_complete', 'wicket_org_search_select_on_order_complete', 10, 1); // Switched from woocommerce_order_status_completed

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
function write_org_id_to_order($order_id, $order) {
  $organizations = get_organizations_based_on_certain_relationship_types();

  if ($organizations) {
    // just use the first one...should be all we need based on the sorting above
    $org_uuid = key($organizations);
    $org_name = reset($organizations);

    file_put_contents('php://stdout', '----------------------------------------------------------------');
    file_put_contents('php://stdout', print_r($org_uuid, true));
    file_put_contents('php://stdout', '----------------------------------------------------------------');
    file_put_contents('php://stdout', print_r($org_name, true));
  }
}