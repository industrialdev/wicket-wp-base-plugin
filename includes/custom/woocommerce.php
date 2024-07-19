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
  wicket_assign_role($current_person_uuid, 'org_editor', $org_uuid_for_roster_man_access);

  // Clean up after ourselves now that we've actioned the meta's value
  delete_user_meta( $current_user_wp_id, 'roster_man_org_to_grant' );

  return true;
}