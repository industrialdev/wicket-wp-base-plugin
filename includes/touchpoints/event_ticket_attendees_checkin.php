<?php

/**
 * Get event data for a ticket product.
 *
 * Kept for backward compatibility: themes may call this directly. Delegates to
 * wicket_tec_event_data() and returns the same key set this has always returned.
 *
 * @param int|string $ticket_id The ticket product post ID.
 * @return array The legacy event-data key set.
 */
function wicket_touchpoint_get_event_data_from_ticket($ticket_id)
{
    $event_id = wicket_tec_event_id_from_ticket((int) $ticket_id);
    $data = wicket_tec_event_data_legacy_shape(wicket_tec_event_data($event_id), 'ticket');

    // This builder read the event ID out of postmeta, so it has always been a string
    // here while the purchase writer's builder returned an int. Cast back rather than
    // silently changing the JSON type the MDP receives on this path.
    $data['event_id'] = (string) $data['event_id'];

    return $data;
}

function wicket_touchpoint_write_attendee($attendee_id, $action)
{
    $attendee = tribe_tickets_get_attendees($attendee_id)[0] ?? null;

    if (!$attendee) {
        return;
    }

    // check if they exist in Wicket, if they do use that as $person_id, if they do not exist in Wicket, create account and use that as $person_id
    // holder_name is a full name, so pass it through the shared identity resolver rather
    // than using it as a given name (which is what created "Jane Doe Doe" style records).
    $identity = wicket_tec_attendee_identity((int) $attendee_id, (int) ($attendee['product_id'] ?? 0));

    $person = wicket_tec_resolve_attendee_person(
        $identity['email'] !== '' ? $identity['email'] : (string) ($attendee['holder_email'] ?? ''),
        [
            'first_name' => $identity['first_name'],
            'last_name' => $identity['last_name'],
        ]
    );

    // Previously a failed wicket_create_person() left $person_uuid undefined and the
    // touchpoint was written with person_id => null. Skip instead.
    if ($person['uuid'] === '') {
        wicket_tec_log_error('Skipped check-in touchpoint: could not resolve a Wicket person', [
            'reason' => $person['code'],
            'attendee_id' => $attendee_id,
        ]);

        return;
    }

    $person_uuid = $person['uuid'];

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
        ],
    ];

    $service_id = get_create_touchpoint_service_id('Events Calendar', 'Events from the website');
    write_touchpoint($params, $service_id);
}

add_action('rsvp_checkin', 'wicket_tec_checkin_touchpoint', 10, 2);
add_action('event_tickets_checkin', 'wicket_tec_checkin_touchpoint', 10, 2);
add_action('eddtickets_checkin', 'wicket_tec_checkin_touchpoint', 10, 2);
add_action('wootickets_checkin', 'wicket_tec_checkin_touchpoint', 10, 2);
function wicket_tec_checkin_touchpoint($attendee_id, $qr)
{
    wicket_touchpoint_write_attendee($attendee_id, 'Attended an event');
}
