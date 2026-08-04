<?php

// ----------------------------------------------------------------------------------------
// Add touchpoints to wicket person records that match event attendees when event purchase completes
// Create the person records if they don't already exist, then write touchpoints on new records
// I've noticed that sometimes woocommerce_payment_complete does not fire, so I've used woocommerce_order_status_completed in this case
// ----------------------------------------------------------------------------------------
add_action('woocommerce_order_status_completed', 'woocommerce_payment_complete_event_ticket_attendees');

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
    // them too. Those orders carry no order-level _tribe_tickets_meta, so the attendee loop
    // below finds nothing and only the ticket-buyer fallback would run, attributing the
    // registration to whoever happens to be the order's customer. Those two paths are handled
    // per attendee instead, where the real attendee is known.
    //
    // created_via is 'checkout' for a front-end purchase, 'admin' for an admin-added attendee
    // and 'import' for the CSV importer. It is empty for orders created programmatically by
    // other means, which are treated as checkouts to preserve existing behaviour.
    // ----------------------------------------------------------------------------------------
    $created_via = (string) $order->get_created_via();

    /**
     * Filter which WooCommerce created_via values this order-level writer handles.
     *
     * @param array     $origins     Allowed created_via values.
     * @param string    $created_via This order's created_via value.
     * @param WC_Order $order       The order.
     */
    $origins = apply_filters('wicket_tec_touchpoint_order_hook_origins', ['checkout', ''], $created_via, $order);

    if (!in_array($created_via, $origins, true)) {
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
                // The rest of this array is the attendee's registration form answers,
                // keyed by field slug. Kept whole so they can be added to the touchpoint.
                $temp['raw_answers'] = is_array($attendee) ? $attendee : [];
                $attendees_arr[$product_id][] = $temp;
            }
        }
    }

    // ----------------------------------------------------------------------------------------
    // Load the items from the order and look for ticket products to get the event info
    // ----------------------------------------------------------------------------------------
    $event_info = [];
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();

        if (!$product) {
            continue;
        }

        // if this is a ticket product, we'll get back the event post id
        $event_id = $product->get_meta('_tribe_wooticket_for_event');

        if ($event_id) {
            $event_post = get_post($event_id);

            if ($event_post) {
                $event_info[$item->get_product_id()] = $event_post;
            }
        }
    }

    // ----------------------------------------------------------------------------------------
    // Also add the person buying the ticket (not an attendee technically) to the attendees array
    // so we can write a touchpoint for them as well.
    // Add this to a theme: add_filter( 'wicket_include_tec_touchpoint_for_ticket_buyer', '__return_false' );
    //
    // The product id used here was previously whatever the loop above happened to leave behind,
    // which on an order whose last line item was not a ticket pointed at a product with no entry
    // in $event_info. Use the last ticket product explicitly: on the common single-event order
    // that is the same product, without depending on line-item order.
    // ----------------------------------------------------------------------------------------
    if (apply_filters('wicket_include_tec_touchpoint_for_ticket_buyer', true) && !empty($event_info)) {
        $order_user = get_user_by('id', $order->get_customer_id());

        // Guest orders and deleted customers have no user to attribute the purchase to.
        if ($order_user) {
            $attendees_arr[array_key_last($event_info)][] = [
                'name' => $order_user->first_name ?? '',
                'email' => $order_user->user_email ?? '',
                'last-name' => $order_user->last_name ?? '',
            ];
        }
    }

    // ----------------------------------------------------------------------------------------
    // Write touchpoints to existing users, create if not exist
    // ----------------------------------------------------------------------------------------
    // The find-and-create branches used to be two near-identical copies of the payload
    // build. They are one path now: resolve the person, then write.
    foreach ($attendees_arr as $product_id => $attendees) {
        // The buyer fallback and the order meta are keyed independently, so an entry can
        // point at a product that is not a ticket on this order.
        if (!isset($event_info[$product_id])) {
            continue;
        }

        foreach ($attendees as $attendee) {

            // make sure that for whatever reason, if email is empty, we do not continue. This has happened for some odd reason in the past causing junk touchpoints so let's try and stop it here
            if (!isset($attendee['email']) || $attendee['email'] == '') {
                continue;
            }

            // check to see if a record for this person already exists in wicket, creating one if not.
            // Matches on the primary address first, then across every address on a record, so a
            // returning attendee who used a secondary address is not duplicated.
            $person = wicket_tec_resolve_attendee_person($attendee['email'], [
                'first_name' => (string) ($attendee['name'] ?? ''),
                'last_name' => (string) ($attendee['last-name'] ?? ''),
            ]);

            // Previously a failed wicket_create_person() returned a truthy ['errors' => ...]
            // and the touchpoint went out with person_id => null. Skip instead.
            if ($person['uuid'] === '') {
                wicket_tec_log_error('Skipped event registration touchpoint: could not resolve a Wicket person', [
                    'reason' => $person['code'],
                    'email' => $attendee['email'],
                    'order_id' => $order->get_id(),
                    'product_id' => $product_id,
                ]);

                continue;
            }

            $event_data = wicket_touchpoint_get_event_data_from_event($event_info[$product_id]->ID);
            $ticket_product_name = get_the_title($product_id);

            // Registration form answers, taken from the order meta this writer already
            // reads. Empty unless the setting is on and the ticket collects answers, so
            // payloads without them are unchanged.
            $answers = wicket_get_option('wicket_admin_settings_tp_event_ticket_attendees_answers') === '1'
                ? wicket_tec_registration_answers_from_raw(
                    (array) ($attendee['raw_answers'] ?? []),
                    (int) $product_id,
                    (int) $event_info[$product_id]->ID
                )
                : [];

            $attendee_details = 'Event ID: ' . $event_data['event_id'] . '<br />';
            $attendee_details .= 'Event Name: ' . $event_data['event_name'] . '<br />';
            $attendee_details .= 'Ticket Product Name: ' . $ticket_product_name . '<br />';
            $attendee_details .= 'Start Date: ' . $event_data['start'] . '<br />';
            $attendee_details .= 'End Date: ' . $event_data['end'] . '<br />';
            $attendee_details .= 'Event Format: ' . $event_data['format'] . '<br />';
            $attendee_details .= 'Event Type: ' . $event_data['event_type'] . '<br />';
            $attendee_details .= wicket_tec_registration_answers_details($answers);

            $action = 'Registered for an event';

            $params = [
                'action' => $action,
                'details' => $attendee_details,
                'person_id' => $person['uuid'],
                'data' => [
                    'url' => $event_data['url'],
                    'end_date' => $event_data['end'],
                    'start_date' => $event_data['start'],
                    'event_title' => $event_data['event_name'],
                    'ticket_product_name' => $ticket_product_name,
                    'event_type' => $event_data['event_type'],
                    'order_date' => $order->get_date_created(),
                    'event_id' => $event_data['event_id'],
                    'location' => $event_data['location'],
                    // Stays null when the event has no TEC custom fields, as it always has.
                    'event_additional_fields' => $event_data['event_additional_fields'] ?? null,
                ],
            ];

            // Added last, and only when there are answers, so payloads without them keep
            // exactly the key set and order they have always had.
            if ($answers !== []) {
                $params['data']['registration_answers'] = $answers;
            }

            // Build a predictable, hashable string
            $hashInput = json_encode([
                'data' => $params['data'],
                'person_id' => $params['person_id'], // include per-attendee uniqueness
                'action' => $params['action'],       // optional, for extra context
            ], JSON_UNESCAPED_SLASHES);

            // Compose final unique identifier
            $externalEventIdParts = [
                $order->get_id(),    // order ID
                $order->get_status(), // order status
                hash('sha256', $hashInput), // hash of structured, stable data
            ];

            $params['external_event_id'] = implode('_', $externalEventIdParts);

            $service_id = get_create_touchpoint_service_id('Events Calendar', 'Events from the website');
            write_touchpoint($params, $service_id);
        }
    }
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
