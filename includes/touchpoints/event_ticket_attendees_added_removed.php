<?php

// No direct access
defined('ABSPATH') || exit;

// ----------------------------------------------------------------------------------------
// Touchpoints for attendees added outside a normal purchase, and for attendees removed.
//
// Added by an admin, or imported from CSV
// -------------------------------------
// Both paths fire event_ticket_woo_attendee_created, the same action a front-end purchase
// fires, so the origin is worked out from the order's created_via and the attendee's
// provenance meta (see wicket_tec_attendee_origin()). Front-end purchases are left to the
// order-level writer in woocommerce_payment_complete_event_ticket_attendees.php, which
// stays on woocommerce_order_status_completed: attendees are generated as early as the
// pending status, so writing from here would record unpaid and abandoned orders.
//
// Removed
// -------
// WordPress' own trash and delete hooks are used rather than Event Tickets' delete action,
// whose first argument is frequently null (the admin Attendees table calls
// delete_ticket(null, $id)) and whose timing relative to postmeta removal varies by
// provider. Both hooks are needed: with EMPTY_TRASH_DAYS at 0, wp_trash_post() goes
// straight to wp_delete_post() and the trash hook never fires.
// ----------------------------------------------------------------------------------------

if (wicket_get_option('wicket_admin_settings_tp_event_ticket_attendees_added') === '1') {
    // Priority 20 so Event Tickets Plus and the CSV importer have finished writing the
    // attendee's postmeta, including the provenance keys origin detection relies on.
    add_action('event_ticket_woo_attendee_created', 'wicket_tec_attendee_added_touchpoint', 20, 4);
    add_action('event_ticket_edd_attendee_created', 'wicket_tec_attendee_added_touchpoint', 20, 4);

    // Mark the request while the CSV importer is mid-row. The importer's per-row filter
    // fires before it creates the order and the attendee; the Event Aggregator action
    // fires once the row is finished, and still fires if attendee creation threw.
    foreach (wicket_tec_attendee_post_types() as $attendee_post_type) {
        add_filter("tribe_ext_tickets_attendee_csv_importer_data_{$attendee_post_type}", 'wicket_tec_mark_csv_import_row', 1);
    }
    add_action('tec_events_csv_importer_post_update', 'wicket_tec_clear_csv_import_row', 999);
}

if (wicket_get_option('wicket_admin_settings_tp_event_ticket_attendees_removed') === '1') {
    add_action('wp_trash_post', 'wicket_tec_attendee_trashed_touchpoint', 10, 1);
    add_action('before_delete_post', 'wicket_tec_attendee_deleted_touchpoint', 10, 1);
}

/**
 * Write a registration touchpoint for an attendee added by an admin or by CSV import.
 *
 * @param int             $attendee_id The attendee post ID.
 * @param int             $event_id    The event post ID.
 * @param WC_Order|mixed $order       The order the attendee hangs off.
 * @param int             $product_id  The ticket product post ID.
 */
function wicket_tec_attendee_added_touchpoint($attendee_id, $event_id, $order = null, $product_id = 0)
{
    $attendee_id = (int) $attendee_id;
    $origin = wicket_tec_attendee_origin($attendee_id, $order);

    // Front-end purchases and RSVPs already have their own writers.
    if (!in_array($origin, ['admin', 'import'], true)) {
        return;
    }

    wicket_tec_write_attendee_added_touchpoint(
        $attendee_id,
        (int) $event_id,
        (int) $product_id,
        $origin,
        $order instanceof WC_Order ? $order : null
    );
}

/**
 * Build and write the "added to an event" registration touchpoint.
 *
 * Uses the same action label and MDP service as a purchased registration, so existing
 * reporting keeps working, and records which path it came from in data.registration_source.
 *
 * @param int             $attendee_id The attendee post ID.
 * @param int             $event_id    The event post ID.
 * @param int             $ticket_id   The ticket product post ID.
 * @param string          $origin      'admin' or 'import'.
 * @param WC_Order|null  $order       The order, when there is one.
 * @return bool Whether a touchpoint was written.
 */
function wicket_tec_write_attendee_added_touchpoint(int $attendee_id, int $event_id, int $ticket_id, string $origin, $order = null): bool
{
    // Idempotency: one attendee post is one registration, so never write twice for it.
    // Guards against a provider firing the created action more than once, and against a
    // re-import creating a second touchpoint for an attendee already recorded.
    if (get_post_meta($attendee_id, '_wicket_touchpoint_registered', true)) {
        return false;
    }

    if ($event_id <= 0) {
        $event_id = wicket_tec_event_id_from_ticket($ticket_id);
    }

    if ($event_id <= 0) {
        wicket_tec_log_error('Skipped added-attendee touchpoint: could not resolve the event', [
            'reason' => 'no_event',
            'attendee_id' => $attendee_id,
            'ticket_id' => $ticket_id,
            'origin' => $origin,
        ]);

        return false;
    }

    $attendee = wicket_tec_attendee_identity($attendee_id, $ticket_id, $order);

    if ($attendee['email'] === '') {
        wicket_tec_log_error('Skipped added-attendee touchpoint: no email address on the attendee', [
            'reason' => 'no_email',
            'attendee_id' => $attendee_id,
            'event_id' => $event_id,
            'origin' => $origin,
        ]);

        return false;
    }

    $person = wicket_tec_resolve_attendee_person($attendee['email'], [
        'first_name' => $attendee['first_name'],
        'last_name' => $attendee['last_name'],
    ]);

    if ($person['uuid'] === '') {
        wicket_tec_log_person_resolution_failure($person, [
            'attendee_id' => $attendee_id,
            'event_id' => $event_id,
            'origin' => $origin,
            'action' => 'Registered for an event',
        ]);

        return false;
    }

    $event_data = wicket_tec_event_data($event_id);
    $ticket_product_name = $ticket_id > 0 ? (string) get_the_title($ticket_id) : '';

    // The CSV importer stores no registration answers at all, so imported attendees carry
    // the event details but no form data. Documented in the setting description.
    $answers = wicket_get_option('wicket_admin_settings_tp_event_ticket_attendees_answers') === '1'
        ? wicket_tec_registration_answers($ticket_id, $attendee_id, $event_id)
        : [];

    $details = 'Event ID: ' . $event_data['event_id'] . '<br />';
    $details .= 'Event Name: ' . $event_data['event_name'] . '<br />';
    $details .= 'Ticket Product Name: ' . $ticket_product_name . '<br />';
    $details .= 'Start Date: ' . $event_data['start'] . '<br />';
    $details .= 'End Date: ' . $event_data['end'] . '<br />';
    $details .= 'Event Format: ' . $event_data['format'] . '<br />';
    $details .= 'Event Type: ' . $event_data['event_type'] . '<br />';
    // The MDP timeline renders the details string but not the data payload, so without
    // this line an admin-added or imported attendee is indistinguishable from a normal
    // registration when someone is reading the list. Only added on these two paths, so a
    // purchased registration's details stay exactly as they were.
    $added_by = wicket_tec_attendee_added_by($attendee_id);
    $details .= 'Added by: ' . wicket_tec_added_by_label($origin, $added_by) . '<br />';
    $details .= wicket_tec_registration_answers_details($answers);

    $params = [
        'action' => 'Registered for an event',
        'details' => $details,
        'person_id' => $person['uuid'],
        'data' => [
            'url' => $event_data['url'],
            'end_date' => $event_data['end'],
            'timezone' => $event_data['timezone'],
            'start_date' => $event_data['start'],
            'event_title' => $event_data['event_name'],
            'ticket_product_name' => $ticket_product_name,
            'event_type' => $event_data['event_type'],
            'event_id' => $event_data['event_id'],
            'location' => $event_data['location'],
            'event_additional_fields' => $event_data['event_additional_fields'],
            'registration_source' => $origin,
            'added_by' => $added_by,
            'added_by_name' => wicket_tec_user_display_name($added_by),
        ],
        // One attendee post is one registration, so the attendee ID is the stable key.
        'external_event_id' => wicket_tec_external_event_id('reg', $attendee_id),
    ];

    if ($answers !== []) {
        $params['data']['registration_answers'] = $answers;
    }

    if ($order instanceof WC_Order) {
        $params['data']['order_date'] = $order->get_date_created();
    }

    $written = write_touchpoint($params, get_create_touchpoint_service_id('Events Calendar', 'Events from the website'));

    // Only mark on success, so a transient MDP failure can be retried.
    if ($written) {
        update_post_meta($attendee_id, '_wicket_touchpoint_registered', time());
        update_post_meta($attendee_id, '_wicket_touchpoint_registered_origin', $origin);
    }

    return (bool) $written;
}

/**
 * Handle an attendee being moved to the trash.
 *
 * @param int $post_id The post being trashed.
 */
function wicket_tec_attendee_trashed_touchpoint($post_id)
{
    wicket_tec_maybe_write_removal_touchpoint((int) $post_id, 'trash');
}

/**
 * Handle an attendee being permanently deleted.
 *
 * @param int $post_id The post being deleted.
 */
function wicket_tec_attendee_deleted_touchpoint($post_id)
{
    wicket_tec_maybe_write_removal_touchpoint((int) $post_id, 'delete');
}

/**
 * Write a "removed from an event" touchpoint, if this post is an attendee.
 *
 * Both trash and permanent delete reach here. The marker written on success is what stops
 * a trash followed by a permanent delete producing two touchpoints: WordPress keeps
 * postmeta until after before_delete_post, so it is still readable on the second pass.
 *
 * Deliberately not conditional on the attendee having a registration touchpoint. Every
 * attendee created before this feature shipped lacks that marker, and requiring it would
 * silently ignore removals across the whole existing back catalogue.
 *
 * @param int    $post_id The post being removed.
 * @param string $trigger 'trash' or 'delete'.
 * @return bool Whether a touchpoint was written.
 */
function wicket_tec_maybe_write_removal_touchpoint(int $post_id, string $trigger): bool
{
    $post = get_post($post_id);

    if (!$post || !in_array($post->post_type, wicket_tec_attendee_post_types(), true)) {
        return false;
    }

    // Already recorded, by the trash pass or a previous attempt.
    if (get_post_meta($post_id, '_wicket_touchpoint_removed', true)) {
        return false;
    }

    // A person we could not resolve on the trash pass will not resolve on the delete pass
    // either, so do not log the same failure twice.
    if (get_post_meta($post_id, '_wicket_touchpoint_removal_skipped', true)) {
        return false;
    }

    $ticket_id = wicket_tec_attendee_ticket_id($post_id);
    $event_id = wicket_tec_attendee_event_id($post_id);

    if ($event_id <= 0) {
        $event_id = wicket_tec_event_id_from_ticket($ticket_id);
    }

    if ($event_id <= 0) {
        // Nothing useful to record against, and not worth a log line: this is reached for
        // orphaned attendees whose event has already gone.
        return false;
    }

    $attendee = wicket_tec_attendee_identity($post_id, $ticket_id, null);

    if ($attendee['email'] === '') {
        update_post_meta($post_id, '_wicket_touchpoint_removal_skipped', 'no_email');

        return false;
    }

    // Never create a person just to record that they were removed from an event.
    $person = wicket_tec_resolve_attendee_person($attendee['email'], ['create' => false]);

    if ($person['uuid'] === '') {
        wicket_tec_log_person_resolution_failure($person, [
            'attendee_id' => $post_id,
            'event_id' => $event_id,
            'action' => 'Removed from an event',
        ]);
        update_post_meta($post_id, '_wicket_touchpoint_removal_skipped', $person['code']);

        return false;
    }

    $event_data = wicket_tec_event_data($event_id);
    $ticket_product_name = $ticket_id > 0 ? (string) get_the_title($ticket_id) : '';
    $removed_at = gmdate('c');

    $details = 'Event ID: ' . $event_data['event_id'] . '<br />';
    $details .= 'Event Name: ' . $event_data['event_name'] . '<br />';
    $details .= 'Ticket Product Name: ' . $ticket_product_name . '<br />';
    $details .= 'Start Date: ' . $event_data['start'] . '<br />';
    $details .= 'End Date: ' . $event_data['end'] . '<br />';
    $details .= 'Event Format: ' . $event_data['format'] . '<br />';
    $details .= 'Event Type: ' . $event_data['event_type'] . '<br />';
    $details .= 'Removed: ' . $removed_at . ' (' . $trigger . ')<br />';

    // Same reasoning as the added-by line: the name is what makes this useful to whoever
    // reads the profile later. Omitted for cron or WP-CLI removals with no acting user.
    $removed_by = get_current_user_id();
    $removed_by_name = wicket_tec_user_display_name($removed_by);

    if ($removed_by_name !== '') {
        $details .= 'Removed by: ' . $removed_by_name . '<br />';
    }

    $params = [
        'action' => 'Removed from an event',
        'details' => $details,
        'person_id' => $person['uuid'],
        'data' => [
            'url' => $event_data['url'],
            'end_date' => $event_data['end'],
            'timezone' => $event_data['timezone'],
            'start_date' => $event_data['start'],
            'event_title' => $event_data['event_name'],
            'ticket_product_name' => $ticket_product_name,
            'event_type' => $event_data['event_type'],
            'event_id' => $event_data['event_id'],
            'location' => $event_data['location'],
            'attendee_id' => $post_id,
            'removed_at' => $removed_at,
            'removal_trigger' => $trigger,
            'removed_by' => $removed_by,
            'removed_by_name' => $removed_by_name,
        ],
        'external_event_id' => wicket_tec_external_event_id('removal', $post_id),
    ];

    // Carry the original registration path through when it was recorded.
    $registration_source = get_post_meta($post_id, '_wicket_touchpoint_registered_origin', true);
    if (is_string($registration_source) && $registration_source !== '') {
        $params['data']['registration_source'] = $registration_source;
    }

    $written = write_touchpoint($params, get_create_touchpoint_service_id('Events Calendar', 'Events from the website'));

    if ($written) {
        update_post_meta($post_id, '_wicket_touchpoint_removed', time());
    }

    return (bool) $written;
}
