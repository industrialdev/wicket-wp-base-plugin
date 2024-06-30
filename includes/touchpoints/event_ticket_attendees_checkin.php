<?php

function wicket_touchpoint_get_event_data_from_ticket($ticket_id) {
  $event_id = get_post_meta($ticket_id, '_tribe_wooticket_for_event')[0];
  // set this incase the above isn't set in certain circumstances (should be the same though)
  $alternate_event = get_post_meta($ticket_id, '_tribe_rsvp_for_event');
  $alternate_event_id = 0;
  if( isset( $alternate_event[0] ) ) {
    $alternate_event_id = $alternate_event[0];
  }

  $event_id = $event_id ?? $alternate_event_id;
  $start_date = tribe_get_start_date($event_id, false, 'Y-m-d g:i A T');
  $end_date = tribe_get_end_date($event_id, false, 'Y-m-d g:i A T');
  $is_virtual = get_post_meta($event_id, '_tribe_events_is_virtual');
  $is_virtual_hybrid = false;
  $virtual_event_type = get_post_meta($event_id, '_tribe_virtual_events_type');
  if( isset( $virtual_event_type[0] ) ) {
    $is_virtual_hybrid = $virtual_event_type[0] == 'hybrid';
  }
  // build location string
  $event_location = '';
  $args = [
    'event' => $event_id,
  ];
  $venue_object = tribe_get_venues(false, -1, true, $args);
  $venue_id = 0;
  if( isset( $venue_object[0] ) ) {
    if( isset( $venue_object[0]->ID ) ) {
      $venue_id = $venue_object[0]->ID;
    }
  }
  if( $venue_id != 0 ) {
    $event_location .= tribe_get_address($venue_id) . ', ';
    $event_location .= tribe_get_city($venue_id) . ', ';
    $event_location .= tribe_get_region($venue_id) . ', ';
    $event_location .= tribe_get_country($venue_id) . ' ';
    $event_location .= tribe_get_zip($venue_id);
  }

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
  
  return $data;
}

function wicket_touchpoint_write_attendee($attendee_id, $action) {
  $client = wicket_api_client();
  $attendee = tribe_tickets_get_attendees($attendee_id)[0];
  $order = wc_get_order($attendee['order_id']);

  // check if they exist in Wicket, if they do use that as $person_id, if they do not exist in Wicket, create account and use that as $person_id
  $search_emails_result = $client->get('/people?filter[emails_address_eq]=' . urlencode($attendee['holder_email']) . '&filter[emails_primary_eq]=true');

  if ($search_emails_result['meta']['page']['total_items'] != 0) {
    // we have someone, there will only be one result since primary emails are unique in wicket
    $person_uuid = $search_emails_result['data'][0]['attributes']['uuid'];
  } else {
    // person does not exists, so create a new person
    $new_person = wicket_create_person(
      $attendee['holder_name'],
      $attendee['attendee_meta']['last-name']['value'],
      $attendee['holder_email']
    );
  
    if ($new_person) {
      $person_uuid  = $new_person['data']['attributes']['uuid'];
    }
  }  
  
  $ticket_id = $attendee['product_id'];
  $event_data = wicket_touchpoint_get_event_data_from_ticket($ticket_id);

  $attendee_details = 'Event ID: ' . $event_data['event_id'] . '<br />';
  $attendee_details .= 'Event Name: ' . $event_data['event_name'] . '<br />';
  $attendee_details .= 'Start Date: ' . $event_data['start'] . '<br />';
  $attendee_details .= 'End Date: ' . $event_data['end'] . '<br />';
  $attendee_details .= 'Event Format: ' . $event_data['format'] . '<br />';
  $attendee_details .= 'Event Type: ' . $event_data['event_type'] . '<br />';

  $params = [
    'action' => $action,
    'details' => $attendee_details,
    'person_id' => $person_uuid,
    'data' => [
      'url' => $event_data['url'],
      'end_date' => $event_data['end'],
      'timezone' => $event_data['timezone'] ?? '+00:00',
      'start_date' => $event_data['start'],
      'event_title' => $event_data['event_name'],
      'event_id' => $event_data['event_id'],
    ]
  ];

  $service_id = get_create_touchpoint_service_id('Events Calendar', 'Events from the website');
  write_touchpoint($params, $service_id);
}

add_action('rsvp_checkin', 'wicket_tec_checkin_touchpoint', 10, 2);
add_action('event_tickets_checkin', 'wicket_tec_checkin_touchpoint', 10, 2);
add_action('eddtickets_checkin', 'wicket_tec_checkin_touchpoint', 10, 2);
add_action('wootickets_checkin', 'wicket_tec_checkin_touchpoint', 10, 2);
function wicket_tec_checkin_touchpoint($attendee_id, $qr) {
  wicket_touchpoint_write_attendee($attendee_id, 'Attended an event');
}
