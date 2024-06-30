<?php

// ----------------------------------------------------------------------------------------
// Add touchpoints to wicket person records that match event attendees when event purchase completes
// Create the person records if they don't already exist, then write touchpoints on new records
// I've noticed that sometimes woocommerce_payment_complete does not fire, so I've used woocommerce_order_status_completed in this case
// ----------------------------------------------------------------------------------------
add_action('woocommerce_order_status_completed', 'woocommerce_payment_complete_event_ticket_attendees');

function woocommerce_payment_complete_event_ticket_attendees($order_id) {
  $log_file = '/srv/wicket-woocommerce.log';
  $order = wc_get_order($order_id);

  if ($order->has_status('failed')) {
    return;
  }

  // see these files in order to understand where this all came from (mostly in the admin backend for viewing woo order item):
  // web\app\plugins\woocommerce\includes\admin\meta-boxes\views\html-order-items.php
  // web\app\plugins\woocommerce\includes\admin\meta-boxes\views\html-order-item.php
  // web\app\plugins\event-tickets-plus\src\Tribe\Commerce\WooCommerce\Enhanced_Templates\Service_Provider.php
  // web\app\plugins\event-tickets-plus\src\Tribe\Commerce\WooCommerce\Enhanced_Templates\Hooks.php

  // ----------------------------------------------------------------------------------------
  // Get the attendees right off the order. The ticket meta is keyed by the woo product id
  // This is important to know since the user might checkout with multiple events
  // We'll use this product_id lower down to make sure we show the right event info for each attendee
  // Make sure your event attendee fields contain a last name field. We usually rename the one that's there to just name (using another hook elsewhere), then add this as well
  // ----------------------------------------------------------------------------------------
  $attendees_per_event = $order->get_meta('_tribe_tickets_meta');

  $attendees_arr = [];
  if (!empty($attendees_per_event)) {
    foreach ($attendees_per_event as $product_id => $event) {
      // look at each event's attendees
      foreach ($event as $attendee) {
        $temp = [];
        $temp['name'] = $attendee['tribe-tickets-plus-iac-name'] ?? '';
        $temp['email'] = $attendee['tribe-tickets-plus-iac-email'] ?? '';
        $temp['last-name'] = $attendee['last-name'] ?? '';
        $attendees_arr[$product_id][] = $temp;
      }
    }
  }

  // ----------------------------------------------------------------------------------------
  // Load the items from the order and look for ticket products to get the event info
  // ----------------------------------------------------------------------------------------
  $event_info = [];
  foreach ($order->get_items() as $item) {
    // if this is a ticket product, we'll get back the event post id
    $event_id = $item->get_product()->get_meta('_tribe_wooticket_for_event');
    $product_id = $item->get_product_id();
    if ($event_id) {
      $event_post = !empty($event_id) ? get_post($event_id) : '' ;
      $event_info[$product_id] = $event_post;
    }
  }

  // ----------------------------------------------------------------------------------------
  // Also add the person buying the ticket (not an attendee technically) to the attendees array
  // so we can write a touchpoint for them as well
  // ----------------------------------------------------------------------------------------
  $order_has_event = $order->get_meta('_tribe_has_tickets');
  if ($order_has_event) {
    $order_user = get_user_by('id', $order->get_customer_id());
    $temp = [];
    $temp['name'] = $order_user->first_name ?? '';
    $temp['email'] = $order_user->user_email ?? '';
    $temp['last-name'] = $order_user->last_name ?? '';
    $attendees_arr[$product_id][] = $temp;
  }

  // ----------------------------------------------------------------------------------------
  // Write touchpoints to existing users, create if not exist
  // ----------------------------------------------------------------------------------------
  $client = wicket_api_client();

  foreach ($attendees_arr as $product_id => $attendees) {
    foreach ($attendees as $attendee) {
      // check to see if a record for this person already exists in wicket
      $search_emails_result = $client->get('/people?filter[emails_address_eq]=' . urlencode($attendee['email']) . '&filter[emails_primary_eq]=true');

      if ($search_emails_result['meta']['page']['total_items'] != 0) {
        // we have someone, there will only be one result since primary emails are unique in wicket
        $person_uuid = $search_emails_result['data'][0]['attributes']['uuid'];

        $event_title = $event_info[$product_id]->post_title;
        
        $ticket_id = $attendee['product_id'];
        $event_data = wicket_touchpoint_get_event_data_from_event($event_info[$product_id]->ID);
      
        $attendee_details = 'Event ID: ' . $event_data['event_id'] . '<br />';
        $attendee_details .= 'Event Name: ' . $event_data['event_name'] . '<br />';
        $attendee_details .= 'Start Date: ' . $event_data['start'] . '<br />';
        $attendee_details .= 'End Date: ' . $event_data['end'] . '<br />';
        $attendee_details .= 'Event Format: ' . $event_data['format'] . '<br />';
        $attendee_details .= 'Event Type: ' . $event_data['event_type'] . '<br />';

        $action = 'Registered for an event';
      
        $params = [
          'action' => $action,
          'details' => $attendee_details,
          'person_id' => $person_uuid,
          'data' => [
            'url' => $event_data['url'],
            'end_date' => $event_data['end'],
            'start_date' => $event_data['start'],
            'event_title' => $event_data['event_name'],
            'event_type' => $event_data['event_type'],
            'order_date' => $order->get_date_created(),
            'event_id' => $event_data['event_id'],
            'location' => $event_data['location'],
            'event_additional_fields' => $event_data['event_additional_fields'],
          ]
        ];
        $service_id = get_create_touchpoint_service_id('Events Calendar', 'Events from the website');
        write_touchpoint($params, $service_id);

      }else {
        // person does not exists, so create a new person
        $new_person = wicket_create_person(
          $attendee['name'],
          $attendee['last-name'],
          $attendee['email']
        );

        if ($new_person) {
          $new_uuid = $new_person['data']['attributes']['uuid'];
          $event_title = $event_info[$product_id]->post_title;
    
          $ticket_id = $attendee['product_id'];
          $event_data = wicket_touchpoint_get_event_data_from_event($event_info[$product_id]->ID);
      
          $attendee_details = 'Event ID: ' . $event_data['event_id'] . '<br />';
          $attendee_details .= 'Event Name: ' . $event_data['event_name'] . '<br />';
          $attendee_details .= 'Start Date: ' . $event_data['start'] . '<br />';
          $attendee_details .= 'End Date: ' . $event_data['end'] . '<br />';
          $attendee_details .= 'Event Format: ' . $event_data['format'] . '<br />';
          $attendee_details .= 'Event Type: ' . $event_data['event_type'] . '<br />';

          $action = 'Registered for an event';
        
          $params = [
            'action' => $action,
            'details' => $attendee_details,
            'person_id' => $new_uuid,
            'data' => [
              'url' => $event_data['url'],
              'end_date' => $event_data['end'],
              'start_date' => $event_data['start'],
              'event_title' => $event_data['event_name'],
              'event_type' => $event_data['event_type'],
              'order_date' => $order->get_date_created(),
              'event_id' => $event_data['event_id'],
              'location' => $event_data['location'],
              'event_additional_fields' => $event_data['event_additional_fields'],
            ]
          ];
          $service_id = get_create_touchpoint_service_id('Events Calendar', 'Events from the website');
          write_touchpoint($params, $service_id);
        }
      }
    }
  }
}

function wicket_touchpoint_get_event_data_from_event($event_id) {
  $start_date = tribe_get_start_date($event_id, false, 'Y-m-d g:i A T');
  $end_date = tribe_get_end_date($event_id, false, 'Y-m-d g:i A T');
  $is_virtual = get_post_meta($event_id, '_tribe_events_is_virtual');
  $is_virtual_hybrid = get_post_meta($event_id, '_tribe_virtual_events_type')[0] == 'hybrid';
  // build location string
  $event_location = '';
  $args = [
    'event' => $event_id,
  ];
  $venue_object = tribe_get_venues(false, -1, true, $args);
  $venue_id = $venue_object[0]->ID;
  $event_location .= tribe_get_address($venue_id) . ', ';
  $event_location .= tribe_get_city($venue_id) . ', ';
  $event_location .= tribe_get_region($venue_id) . ', ';
  $event_location .= tribe_get_country($venue_id) . ' ';
  $event_location .= tribe_get_zip($venue_id);
  $event_additional_fields = tribe_get_custom_fields($event_id);
  // if event is purely virtual, not a hybrid the location = Virtual, else calculate physical location
  $event_location = $is_virtual && !$is_virtual_hybrid ? 'VIRTUAL' : $event_location;
  // build event types string
  $event_type = wp_get_post_terms($event_id, 'tribe_events_cat') ? wp_get_post_terms($event_id, 'tribe_events_cat')[0]->name : 'Not set';

  $data['start'] = $start_date;
  $data['end'] = $end_date;
  $data['event_name'] = get_the_title($event_id);
  $data['event_id'] = $event_id;
  $data['url'] = get_permalink($event_id);
  $data['event_type'] = $event_type;
  // new fields
  $data['location'] = $event_location;
  if ($is_virtual && !$is_virtual_hybrid) {
    $data['format'] = 'Virtual';
  } elseif ($is_virtual_hybrid) {
    $data['format'] = 'Hybrid';
  } else {
    $data['format'] = 'In person';
  }
  if ($event_additional_fields){
    foreach ($event_additional_fields as $label => $value){
      $temp[$label] = $value;
      $data['event_additional_fields'][] = $temp;
    }
  }
  
  return $data;
}





