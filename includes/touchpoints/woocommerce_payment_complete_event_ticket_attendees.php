<?php

// No direct access
defined('ABSPATH') || exit;

// ----------------------------------------------------------------------------------------
// Add touchpoints to wicket person records that match event attendees when an event purchase
// completes. Create the person records if they don't already exist, then write touchpoints.
//
// I've noticed that sometimes woocommerce_payment_complete does not fire, so
// woocommerce_order_status_completed is used instead.
//
// Attendees come from the attendee posts on the order, not from the order's
// _tribe_tickets_meta
// ----------------------------------------------------------------------
// This writer used to build its attendee list from the order's _tribe_tickets_meta, which is
// where Event Tickets Plus parks a copy of the registration form answers. That field is not a
// reliable list of who is on the order:
//
//   1. ETP holds in-progress answers in a transient that expires after 24 hours, keyed by a
//      hash in a browser-session cookie. A WooCommerce cart outlives both, indefinitely for a
//      logged-in customer. A cart assembled over more than a day therefore reaches checkout
//      with only the most recently added ticket's answers, and the order field is written from
//      whatever survived. Every other ticket on the order was silently skipped.
//   2. A ticket with no attendee-information fields configured never populates the field at
//      all, so those events never recorded a registration.
//   3. ETP rewrites the field on every order status change, so it can go from complete to
//      partial after the attendees have already been created.
//
// Attendee posts have none of those problems: Event Tickets creates exactly one per ticket
// sold, whether or not any answers were collected. Reading them also matches what the admin
// and CSV-import writer in event_ticket_attendees_added_removed.php already does, so all three
// registration paths now agree on where an attendee comes from.
//
// Purchases deliberately stay on this order-level hook rather than moving to
// event_ticket_woo_attendee_created: ETP generates WooCommerce attendees as early as the
// pending status, so writing from there would record unpaid and abandoned orders.
// ----------------------------------------------------------------------------------------
add_action('woocommerce_order_status_completed', 'woocommerce_payment_complete_event_ticket_attendees');

/**
 * Write registration touchpoints for every attendee on a completed order.
 *
 * @param int $order_id The WooCommerce order ID.
 */
function woocommerce_payment_complete_event_ticket_attendees($order_id)
{
    $order = wc_get_order($order_id);

    if (!$order || $order->has_status('failed')) {
        return;
    }

    // ----------------------------------------------------------------------------------------
    // Only real front-end checkouts belong on this hook.
    //
    // Adding an attendee from the admin Attendees screen, and importing attendees from CSV,
    // both create their own WooCommerce order and set it to completed, so this hook fires for
    // them too. Those two paths are handled per attendee instead, in
    // event_ticket_attendees_added_removed.php, where the acting user is known.
    //
    // created_via is 'checkout' for a front-end purchase, 'admin' for an admin-added attendee
    // and 'import' for the CSV importer. It is empty for orders created programmatically by
    // other means, which are treated as checkouts to preserve existing behaviour.
    // ----------------------------------------------------------------------------------------
    $created_via = (string) $order->get_created_via();

    /**
     * Filter which WooCommerce created_via values this order-level writer handles.
     *
     * @param array    $origins     Allowed created_via values.
     * @param string   $created_via This order's created_via value.
     * @param WC_Order $order       The order.
     */
    $origins = apply_filters('wicket_tec_touchpoint_order_hook_origins', ['checkout', ''], $created_via, $order);

    if (!in_array($created_via, $origins, true)) {
        return;
    }

    foreach (wicket_tec_order_registrations($order) as $registration) {
        wicket_tec_write_purchase_registration_touchpoint(
            $registration['attendee_id'],
            $registration['event_id'],
            $registration['ticket_id'],
            $order
        );
    }

    wicket_tec_maybe_write_ticket_buyer_touchpoint($order);
}

/**
 * List the registrations on an order, one entry per attendee post.
 *
 * tribe_tickets_get_attendees() resolves the provider itself and is the same helper
 * wicket_tec_attendee_identity() reads, so a provider this plugin does not know about still
 * works. Only its flat fields are used: attendee_meta is shaped differently per provider and
 * must not be trusted (see the gotchas in docs/engineering/tec-touchpoints.md).
 *
 * A direct postmeta lookup backs it up, because the Attendee CSV Importer and some older
 * providers create attendee posts without registering them with the data API.
 *
 * @param WC_Order $order The order.
 * @return array<int, array{attendee_id: int, ticket_id: int, event_id: int}>
 */
function wicket_tec_order_registrations(WC_Order $order): array
{
    $registrations = [];
    $seen = [];

    $attendees = function_exists('tribe_tickets_get_attendees')
        ? tribe_tickets_get_attendees($order->get_id())
        : [];

    foreach ((array) $attendees as $attendee) {
        if (!is_array($attendee)) {
            continue;
        }

        $attendee_id = (int) ($attendee['attendee_id'] ?? 0);

        if ($attendee_id <= 0 || isset($seen[$attendee_id])) {
            continue;
        }

        $seen[$attendee_id] = true;
        $registrations[] = wicket_tec_registration_from_attendee(
            $attendee_id,
            (int) ($attendee['product_id'] ?? 0),
            (int) ($attendee['event_id'] ?? 0)
        );
    }

    // Fallback: attendee posts that point at this order but were not returned above.
    foreach (wicket_tec_attendee_ids_by_order($order->get_id()) as $attendee_id) {
        if (isset($seen[$attendee_id])) {
            continue;
        }

        $seen[$attendee_id] = true;
        $registrations[] = wicket_tec_registration_from_attendee($attendee_id, 0, 0);
    }

    // A ticket sold with no attendee post behind it means someone gets no touchpoint. That
    // used to happen silently, which is how the _tribe_tickets_meta gap went unnoticed for so
    // long, so say something rather than quietly writing fewer touchpoints than tickets sold.
    $expected = wicket_tec_order_ticket_quantity($order);

    if ($expected > count($registrations)) {
        wicket_tec_log_error('Event registration touchpoints: fewer attendees found than tickets sold on the order', [
            'reason' => 'attendee_count_mismatch',
            'order_id' => $order->get_id(),
            'tickets_sold' => $expected,
            'attendees_found' => count($registrations),
        ]);
    }

    return $registrations;
}

/**
 * Normalise one attendee into a registration, filling in whatever the caller did not supply.
 *
 * @param int $attendee_id The attendee post ID.
 * @param int $ticket_id   The ticket product post ID, or 0 to look it up.
 * @param int $event_id    The event post ID, or 0 to look it up.
 * @return array{attendee_id: int, ticket_id: int, event_id: int}
 */
function wicket_tec_registration_from_attendee(int $attendee_id, int $ticket_id = 0, int $event_id = 0): array
{
    if ($ticket_id <= 0) {
        $ticket_id = wicket_tec_attendee_ticket_id($attendee_id);
    }

    if ($event_id <= 0) {
        $event_id = wicket_tec_attendee_event_id($attendee_id);
    }

    // Last resort: the ticket product records the event it belongs to.
    if ($event_id <= 0) {
        $event_id = wicket_tec_event_id_from_ticket($ticket_id);
    }

    return [
        'attendee_id' => $attendee_id,
        'ticket_id' => $ticket_id,
        'event_id' => $event_id,
    ];
}

/**
 * Find attendee posts belonging to a WooCommerce order, straight from postmeta.
 *
 * @param int $order_id The WooCommerce order ID.
 * @return int[] Attendee post IDs.
 */
function wicket_tec_attendee_ids_by_order(int $order_id): array
{
    $attendee_ids = get_posts([
        'post_type' => wicket_tec_attendee_post_types(),
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'meta_query' => [
            [
                'key' => '_tribe_wooticket_order',
                'value' => $order_id,
            ],
        ],
    ]);

    return array_map('intval', (array) $attendee_ids);
}

/**
 * Count how many tickets an order sold, so the attendee count can be sanity checked.
 *
 * @param WC_Order $order The order.
 * @return int The total ticket quantity on the order.
 */
function wicket_tec_order_ticket_quantity(WC_Order $order): int
{
    $quantity = 0;

    foreach ($order->get_items() as $item) {
        $product = $item->get_product();

        if (!$product || !$product->get_meta('_tribe_wooticket_for_event')) {
            continue;
        }

        $quantity += max(1, (int) $item->get_quantity());
    }

    return $quantity;
}

/**
 * Write the "registered for an event" touchpoint for one purchased attendee.
 *
 * @param int      $attendee_id The attendee post ID.
 * @param int      $event_id    The event post ID.
 * @param int      $ticket_id   The ticket product post ID.
 * @param WC_Order $order       The order the attendee hangs off.
 * @return bool Whether a touchpoint was written.
 */
function wicket_tec_write_purchase_registration_touchpoint(int $attendee_id, int $event_id, int $ticket_id, WC_Order $order): bool
{
    // Idempotency: one attendee post is one registration, so never write twice for it. This
    // also makes the writer safe to call again by hand to backfill an order that was missed.
    if (get_post_meta($attendee_id, '_wicket_touchpoint_registered', true)) {
        return false;
    }

    if ($event_id <= 0) {
        wicket_tec_log_error('Skipped event registration touchpoint: could not resolve the event', [
            'reason' => 'no_event',
            'attendee_id' => $attendee_id,
            'ticket_id' => $ticket_id,
            'order_id' => $order->get_id(),
        ]);

        return false;
    }

    // Reads the attendee's own name and email, falling back to the order's billing details.
    // Event Tickets stamps the purchaser's billing name and email onto an attendee whose
    // registration answers were never collected, so for those the buyer is all there is.
    $attendee = wicket_tec_attendee_identity($attendee_id, $ticket_id, $order);

    // Make sure that if the email is empty we do not continue. This has happened for some odd
    // reason in the past causing junk touchpoints, so stop it here.
    if ($attendee['email'] === '') {
        wicket_tec_log_error('Skipped event registration touchpoint: no email address on the attendee', [
            'reason' => 'no_email',
            'attendee_id' => $attendee_id,
            'event_id' => $event_id,
            'order_id' => $order->get_id(),
        ]);

        return false;
    }

    // Check to see if a record for this person already exists in wicket, creating one if not.
    // Matches on the primary address first, then across every address on a record, so a
    // returning attendee who used a secondary address is not duplicated.
    $person = wicket_tec_resolve_attendee_person($attendee['email'], [
        'first_name' => $attendee['first_name'],
        'last_name' => $attendee['last_name'],
    ]);

    if ($person['uuid'] === '') {
        wicket_tec_log_person_resolution_failure($person, [
            'attendee_id' => $attendee_id,
            'event_id' => $event_id,
            'order_id' => $order->get_id(),
            'action' => 'Registered for an event',
        ]);

        return false;
    }

    $event_data = wicket_touchpoint_get_event_data_from_event($event_id);
    $ticket_product_name = $ticket_id > 0 ? (string) get_the_title($ticket_id) : '';

    // Registration form answers, read from the attendee rather than from the order, so a
    // ticket whose answers never reached the order still reports what was collected. Empty
    // unless the setting is on and the ticket collects answers, so payloads without them are
    // unchanged.
    $answers = wicket_get_option('wicket_admin_settings_tp_event_ticket_attendees_answers') === '1'
        ? wicket_tec_registration_answers($ticket_id, $attendee_id, $event_id)
        : [];

    $params = wicket_tec_purchase_touchpoint_params($person['uuid'], $event_data, $ticket_product_name, $order, $answers);

    // One attendee post is one registration, so the attendee ID is the stable key. The old
    // scheme hashed the payload, which meant any change to the event data produced a new
    // identifier and the MDP could no longer recognise a repeat write.
    $params['external_event_id'] = wicket_tec_external_event_id('reg', $attendee_id);

    $written = write_touchpoint($params, get_create_touchpoint_service_id('Events Calendar', 'Events from the website'));

    // Only mark on success, so a transient MDP failure can be retried.
    if ($written) {
        update_post_meta($attendee_id, '_wicket_touchpoint_registered', time());
        update_post_meta($attendee_id, '_wicket_touchpoint_registered_origin', 'purchase');
    }

    return (bool) $written;
}

/**
 * Also write a touchpoint for the person who bought the tickets.
 *
 * The buyer is not an attendee, so there is no attendee post and no answers to report. Kept
 * exactly as it behaved before, including attributing the buyer to the last ticket on the
 * order: on the common single-event order that is the only ticket, and changing it would
 * silently start writing several touchpoints for sites that have this switched on.
 *
 * Turn it off with:
 *   add_filter( 'wicket_include_tec_touchpoint_for_ticket_buyer', '__return_false' );
 *
 * @param WC_Order $order The order.
 * @return bool Whether a touchpoint was written.
 */
function wicket_tec_maybe_write_ticket_buyer_touchpoint(WC_Order $order): bool
{
    /**
     * Filter whether the ticket buyer also gets a registration touchpoint.
     *
     * @param bool $include Whether to include the buyer.
     */
    if (!apply_filters('wicket_include_tec_touchpoint_for_ticket_buyer', true)) {
        return false;
    }

    $ticket_id = 0;
    $event_id = 0;

    // The last ticket line item whose event still exists, matching the previous behaviour.
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();

        if (!$product) {
            continue;
        }

        $item_event_id = (int) $product->get_meta('_tribe_wooticket_for_event');

        if ($item_event_id <= 0 || !get_post($item_event_id)) {
            continue;
        }

        $ticket_id = (int) $item->get_product_id();
        $event_id = $item_event_id;
    }

    if ($event_id <= 0) {
        return false;
    }

    // Guest orders and deleted customers have no user to attribute the purchase to.
    $buyer = get_user_by('id', $order->get_customer_id());

    if (!$buyer || empty($buyer->user_email)) {
        return false;
    }

    $person = wicket_tec_resolve_attendee_person((string) $buyer->user_email, [
        'first_name' => (string) ($buyer->first_name ?? ''),
        'last_name' => (string) ($buyer->last_name ?? ''),
    ]);

    if ($person['uuid'] === '') {
        wicket_tec_log_person_resolution_failure($person, [
            'order_id' => $order->get_id(),
            'event_id' => $event_id,
            'action' => 'Registered for an event',
            'role' => 'ticket_buyer',
        ]);

        return false;
    }

    $event_data = wicket_touchpoint_get_event_data_from_event($event_id);
    $ticket_product_name = $ticket_id > 0 ? (string) get_the_title($ticket_id) : '';

    $params = wicket_tec_purchase_touchpoint_params($person['uuid'], $event_data, $ticket_product_name, $order, []);

    // The buyer has no attendee post to key on, so this path keeps the original payload-hash
    // scheme: the same order in the same status rebuilds the same identifier.
    $hash_input = json_encode([
        'data' => $params['data'],
        'person_id' => $params['person_id'],
        'action' => $params['action'],
    ], JSON_UNESCAPED_SLASHES);

    $params['external_event_id'] = implode('_', [
        $order->get_id(),
        $order->get_status(),
        hash('sha256', (string) $hash_input),
    ]);

    return (bool) write_touchpoint($params, get_create_touchpoint_service_id('Events Calendar', 'Events from the website'));
}

/**
 * Build the touchpoint payload shared by the attendee and ticket-buyer paths.
 *
 * The key set and ordering are deliberately unchanged from the original writer, so existing
 * MDP reporting and segments keep working.
 *
 * @param string   $person_uuid         The MDP person UUID.
 * @param array    $event_data          Event data from wicket_touchpoint_get_event_data_from_event().
 * @param string   $ticket_product_name The ticket product title.
 * @param WC_Order $order               The order.
 * @param array    $answers             Registration answers, label to value. May be empty.
 * @return array The touchpoint parameters, without external_event_id.
 */
function wicket_tec_purchase_touchpoint_params(string $person_uuid, array $event_data, string $ticket_product_name, WC_Order $order, array $answers): array
{
    // wicket_tec_event_data() defines every key, but it passes through the
    // wicket_tec_event_data filter and then wicket_tec_event_data_legacy_shape(), which copies
    // only the keys that exist. A site filtering that data, or a theme calling this helper
    // directly, can therefore hand over a partial array. Default explicitly rather than
    // relying on an implicit null and an undefined-index warning.
    $event_id = $event_data['event_id'] ?? '';
    $event_name = $event_data['event_name'] ?? '';
    $start = $event_data['start'] ?? '';
    $end = $event_data['end'] ?? '';
    $format = $event_data['format'] ?? '';
    $event_type = $event_data['event_type'] ?? '';
    $url = $event_data['url'] ?? '';
    $location = $event_data['location'] ?? '';

    $details = 'Event ID: ' . $event_id . '<br />';
    $details .= 'Event Name: ' . $event_name . '<br />';
    $details .= 'Ticket Product Name: ' . $ticket_product_name . '<br />';
    $details .= 'Start Date: ' . $start . '<br />';
    $details .= 'End Date: ' . $end . '<br />';
    $details .= 'Event Format: ' . $format . '<br />';
    $details .= 'Event Type: ' . $event_type . '<br />';
    $details .= wicket_tec_registration_answers_details($answers);

    $params = [
        'action' => 'Registered for an event',
        'details' => $details,
        'person_id' => $person_uuid,
        'data' => [
            'url' => $url,
            'end_date' => $end,
            'start_date' => $start,
            'event_title' => $event_name,
            'ticket_product_name' => $ticket_product_name,
            'event_type' => $event_type,
            'order_date' => $order->get_date_created(),
            'event_id' => $event_id,
            'location' => $location,
            // Stays null when the event has no TEC custom fields, as it always has.
            'event_additional_fields' => $event_data['event_additional_fields'] ?? null,
        ],
    ];

    // Added last, and only when there are answers, so payloads without them keep exactly the
    // key set and order they have always had.
    if ($answers !== []) {
        $params['data']['registration_answers'] = $answers;
    }

    return $params;
}

/**
 * Get event data for an event.
 *
 * Kept for backward compatibility: themes may call this directly. Delegates to
 * wicket_tec_event_data() and returns the same key set this has always returned,
 * including event_additional_fields only when the event actually has custom fields.
 *
 * @param int|string $event_id The event post ID.
 * @return array The legacy event-data key set.
 */
function wicket_touchpoint_get_event_data_from_event($event_id)
{
    return wicket_tec_event_data_legacy_shape(wicket_tec_event_data((int) $event_id), 'event');
}
