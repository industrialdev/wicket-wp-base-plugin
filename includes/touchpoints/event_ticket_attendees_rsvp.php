<?php

/**
 * Get event data for an RSVP event.
 *
 * Kept for backward compatibility: themes may call this directly. Delegates to
 * wicket_tec_event_data() and returns the same key set this has always returned.
 *
 * @param int|string $event_id The event post ID.
 * @return array The legacy event-data key set.
 */
function wicket_rsvp_touchpoint_get_event_data_from_event($event_id)
{
    return wicket_tec_event_data_legacy_shape(wicket_tec_event_data((int) $event_id), 'ticket');
}

function wicket_touchpoint_write_attendee_rsvp($attendee_id, $event_id, $action)
{
    $attendee = tribe_tickets_get_attendees($attendee_id)[0] ?? null;

    if (!$attendee) {
        return;
    }

    // NOTE! The attendee meta fields must be setup in order for this to work with multiple rsvp's at once.
    // It only provides the 'holder email' and 'holder name' for the first one. The other guests only are shown the meta fields, therefore first name, last name, and email must be configured
    // see here https://www.loom.com/share/1a080095f9f047668b05e39af04d8ae3

    // check if they exist in Wicket, if they do use that as $person_id, if they do not exist in Wicket, create account and use that as $person_id
    $person = wicket_tec_resolve_attendee_person(
        (string) ($attendee['attendee_meta']['email']['value'] ?? ''),
        [
            'first_name' => (string) ($attendee['attendee_meta']['first-name']['value'] ?? ''),
            'last_name' => (string) ($attendee['attendee_meta']['last-name']['value'] ?? ''),
        ]
    );

    // Previously a failed wicket_create_person() left $person_uuid undefined and the
    // touchpoint was written with person_id => null. Skip instead.
    if ($person['uuid'] === '') {
        wicket_tec_log_error('Skipped RSVP touchpoint: could not resolve a Wicket person', [
            'reason' => $person['code'],
            'attendee_id' => $attendee_id,
            'event_id' => $event_id,
        ]);

        return;
    }

    $person_uuid = $person['uuid'];

    $event_data = wicket_rsvp_touchpoint_get_event_data_from_event($event_id);
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
            'timezone' => $event_data['timezone'],
            'start_date' => $event_data['start'],
            'event_title' => $event_data['event_name'],
            'event_id' => $event_data['event_id'],
        ],
    ];
    write_touchpoint($params, get_create_touchpoint_service_id('Events Calendar', 'WP Plugin TEC'));
}

// https://docs.theeventscalendar.com/reference/files/src/tribe/repositories/attendee/rsvp.php
// "event_tickets_rsvp_attendee_created" doesn't contain the attendee meta in time, hence we use "event_tickets_rsvp_ticket_created" instead
add_action('event_tickets_rsvp_ticket_created', 'wicket_tec_rsvp_attendee_touchpoint', 100, 4);

function wicket_tec_rsvp_attendee_touchpoint($attendee_id, $event_id, $order_id, $product_id)
{
    wicket_touchpoint_write_attendee_rsvp($attendee_id, $event_id, 'RSVP to event');
}
