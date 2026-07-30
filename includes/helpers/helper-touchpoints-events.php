<?php

declare(strict_types=1);

// No direct access
defined('ABSPATH') || exit;

/**
 * Shared helpers for The Events Calendar / Event Tickets touchpoints.
 *
 * Pure functions only, no hooks. Hook wiring lives in includes/touchpoints/.
 *
 * Historically each TEC touchpoint writer carried its own near-identical copy of the
 * event-data builder, and only the check-in copy guarded against events with no venue
 * or no virtual-event type. wicket_tec_event_data() is now the single implementation;
 * the three original functions remain as thin wrappers so themes calling them keep
 * working.
 */

/**
 * Build the canonical event data used by every TEC touchpoint payload.
 *
 * Always defines every key, so callers never hit an undefined index. Guards the
 * venue and virtual-event lookups: an event with no venue yields an empty location
 * rather than the ', , ,  ' string the unguarded copies produced.
 *
 * @param int $event_id The tribe_events post ID.
 * @return array{
 *   start: string, end: string, timezone: string, event_name: string,
 *   event_id: int, url: string, event_type: string, location: string,
 *   format: string, event_additional_fields: array
 * }
 */
function wicket_tec_event_data(int $event_id): array
{
    $is_virtual = (bool) get_post_meta($event_id, '_tribe_events_is_virtual', true);
    $virtual_type = (string) get_post_meta($event_id, '_tribe_virtual_events_type', true);
    $is_virtual_hybrid = $virtual_type === 'hybrid';

    // Format is derived before location so a purely virtual event can skip the venue string.
    if ($is_virtual && !$is_virtual_hybrid) {
        $format = 'Virtual';
    } elseif ($is_virtual_hybrid) {
        $format = 'Hybrid';
    } else {
        $format = 'In person';
    }

    $data = [
        'start' => (string) tribe_get_start_date($event_id, false, 'Y-m-d g:i A T'),
        'end' => (string) tribe_get_end_date($event_id, false, 'Y-m-d g:i A T'),
        'timezone' => wicket_tec_event_timezone($event_id),
        'event_name' => (string) get_the_title($event_id),
        'event_id' => $event_id,
        'url' => (string) get_permalink($event_id),
        'event_type' => wicket_tec_event_category($event_id),
        'location' => $is_virtual && !$is_virtual_hybrid ? 'VIRTUAL' : wicket_tec_event_location($event_id),
        'format' => $format,
        'event_additional_fields' => wicket_tec_event_additional_fields($event_id),
    ];

    /*
     * Filter the canonical TEC event data used to build touchpoint payloads.
     *
     * @param array $data     The event data.
     * @param int   $event_id The event post ID.
     */
    return apply_filters('wicket_tec_event_data', $data, $event_id);
}

/**
 * Build the venue address string for an event.
 *
 * Returns '' when the event has no venue. The unguarded copies of this logic read
 * $venue_object[0]->ID directly, which on a venue-less event produced a null-property
 * warning and a location of ', , ,  '.
 *
 * @param int $event_id The event post ID.
 * @return string The address, or '' when there is no venue.
 */
function wicket_tec_event_location(int $event_id): string
{
    $venues = tribe_get_venues(false, -1, true, ['event' => $event_id]);

    if (empty($venues) || !isset($venues[0]->ID)) {
        return '';
    }

    // Only the first venue is used; TEC allows several but the touchpoint records one.
    $venue_id = (int) $venues[0]->ID;

    $parts = [
        tribe_get_address($venue_id) . ', ',
        tribe_get_city($venue_id) . ', ',
        tribe_get_region($venue_id) . ', ',
        tribe_get_country($venue_id) . ' ',
        tribe_get_zip($venue_id),
    ];

    $location = implode('', array_map('strval', $parts));

    // A venue with no address fields at all should read as empty, not as punctuation.
    return trim($location, ", \t\n\r\0\x0B") === '' ? '' : $location;
}

/**
 * Get the first Events Calendar category name for an event.
 *
 * @param int $event_id The event post ID.
 * @return string The category name, or 'Not set' when the event has none.
 */
function wicket_tec_event_category(int $event_id): string
{
    $terms = wp_get_post_terms($event_id, 'tribe_events_cat');

    if (is_wp_error($terms) || empty($terms) || !isset($terms[0]->name)) {
        return 'Not set';
    }

    return (string) $terms[0]->name;
}

/**
 * Get an event's timezone string.
 *
 * Note the class: get_event_timezone_string() lives on Tribe__Events__Timezones (in
 * The Events Calendar), not on the common Tribe__Timezones, which is present but does
 * not define it. Hence method_exists() rather than class_exists().
 *
 * @param int $event_id The event post ID.
 * @return string The timezone string, e.g. 'America/Toronto'.
 */
function wicket_tec_event_timezone(int $event_id): string
{
    if (method_exists('Tribe__Events__Timezones', 'get_event_timezone_string')) {
        $timezone = Tribe__Events__Timezones::get_event_timezone_string($event_id);

        if (is_string($timezone) && $timezone !== '') {
            return $timezone;
        }
    }

    $timezone = get_post_meta($event_id, '_EventTimezone', true);

    if (is_string($timezone) && $timezone !== '') {
        return $timezone;
    }

    return (string) wp_timezone_string();
}

/**
 * Get an event's TEC additional (custom) fields as label/value pairs.
 *
 * The original implementation reused a $temp array across loop iterations without
 * resetting it, so three fields A, B, C produced [[A], [A, B], [A, B, C]] instead of
 * one entry per field. It also left the key undefined when an event had no custom
 * fields, which made the purchase writer read an undefined index.
 *
 * @param int $event_id The event post ID.
 * @return array<int, array<string, mixed>> One single-entry array per field.
 */
function wicket_tec_event_additional_fields(int $event_id): array
{
    if (!function_exists('tribe_get_custom_fields')) {
        return [];
    }

    $fields = tribe_get_custom_fields($event_id);

    if (empty($fields) || !is_array($fields)) {
        return [];
    }

    $additional = [];
    foreach ($fields as $label => $value) {
        $additional[] = [$label => $value];
    }

    return $additional;
}

/**
 * Resolve the event ID a ticket product belongs to.
 *
 * Checks the WooCommerce ticket key first, then the RSVP key. The original used
 * get_post_meta(...)[0] with a ??= fallback that could never fire, because
 * get_post_meta() returns '' rather than null for a missing single value.
 *
 * @param int $ticket_id The ticket product post ID.
 * @return int The event post ID, or 0 when it cannot be resolved.
 */
function wicket_tec_event_id_from_ticket(int $ticket_id): int
{
    foreach (['_tribe_wooticket_for_event', '_tribe_rsvp_for_event', '_tribe_eddticket_for_event'] as $key) {
        $event_id = (int) get_post_meta($ticket_id, $key, true);

        if ($event_id > 0) {
            return $event_id;
        }
    }

    return 0;
}

/**
 * The attendee post types across the ticket providers.
 *
 * @return array<int, string>
 */
function wicket_tec_attendee_post_types(): array
{
    return apply_filters('wicket_tec_attendee_post_types', [
        'tribe_wooticket',        // WooCommerce, via Event Tickets Plus
        'tribe_rsvp_attendees',   // RSVP
        'tribe_eddticket',        // Easy Digital Downloads, via Event Tickets Plus
        'tec_tc_attendee',        // Tickets Commerce
        'tribe_tpp_attendees',    // Tribe Commerce / PayPal, legacy
    ]);
}

/**
 * Flag the current request as being inside a CSV attendee-import row.
 *
 * The Attendee CSV Importer extension bypasses the attendee repository entirely (it does
 * its own wp_insert_post) and re-fires the provider's created action by hand, so none of
 * the usual provenance meta is written. This request-scoped flag is how an imported
 * attendee is told apart from a real purchase. It also covers RSVP imports, which have
 * no WooCommerce order to inspect.
 *
 * Used as a filter callback, so it returns its input untouched.
 *
 * @param mixed $attendee_data Passed through unchanged.
 * @return mixed The unchanged input.
 */
function wicket_tec_mark_csv_import_row($attendee_data = null)
{
    wicket_tec_csv_import_row_flag(true);

    return $attendee_data;
}

/**
 * Clear the CSV import-row flag once the row has been processed.
 *
 * Used as an action callback on a hook that passes arguments we do not need.
 */
function wicket_tec_clear_csv_import_row(): void
{
    wicket_tec_csv_import_row_flag(false);
}

/**
 * Read or write the request-scoped CSV import-row flag.
 *
 * @param bool|null $set True or false to set the flag, null to read it.
 * @return bool The current flag value.
 */
function wicket_tec_csv_import_row_flag(?bool $set = null): bool
{
    static $in_import_row = false;

    if ($set !== null) {
        $in_import_row = $set;
    }

    return $in_import_row;
}

/**
 * Whether the current request is inside a CSV attendee-import row.
 */
function wicket_tec_is_csv_import_row(): bool
{
    return wicket_tec_csv_import_row_flag();
}

/**
 * Work out how an attendee came to exist.
 *
 * Every WooCommerce attendee path fires the same created action, so the origin has to be
 * inferred. Two independent signals back each other up:
 *
 *  - CSV import: the request-scoped flag above, plus the order's created_via of 'import'.
 *  - Admin add:  the _tribe_attendee_source postmeta of 'admin', written inside the same
 *                wp_insert_post() that creates the attendee (so it is readable by the
 *                time the created action fires), plus a created_via of 'admin'.
 *
 * Note that adding an attendee from the admin screen and importing from CSV each create
 * their own WooCommerce order, whereas a front-end checkout creates one order covering
 * every attendee in the basket.
 *
 * @param int             $attendee_id The attendee post ID.
 * @param WC_Order|mixed $order       The attendee's order, when there is one.
 * @param string          $hint        Explicit origin, which wins over detection.
 * @return string 'purchase', 'admin', 'import' or 'rsvp'.
 */
function wicket_tec_attendee_origin(int $attendee_id, $order = null, string $hint = ''): string
{
    $origin = wicket_tec_detect_attendee_origin($attendee_id, $order, $hint);

    /*
     * Filter the detected origin of an attendee.
     *
     * @param string          $origin      'purchase', 'admin', 'import' or 'rsvp'.
     * @param int             $attendee_id The attendee post ID.
     * @param \WC_Order|mixed $order       The attendee's order, when there is one.
     */
    return (string) apply_filters('wicket_tec_attendee_origin', $origin, $attendee_id, $order);
}

/**
 * Origin detection, split out so the filter in wicket_tec_attendee_origin() wraps it.
 *
 * @param int             $attendee_id The attendee post ID.
 * @param WC_Order|mixed $order       The attendee's order, when there is one.
 * @param string          $hint        Explicit origin, which wins over detection.
 * @return string 'purchase', 'admin', 'import' or 'rsvp'.
 */
function wicket_tec_detect_attendee_origin(int $attendee_id, $order = null, string $hint = ''): string
{
    if ($hint !== '') {
        return $hint;
    }

    if (wicket_tec_is_csv_import_row()) {
        return 'import';
    }

    if (get_post_meta($attendee_id, '_tribe_attendee_source', true) === 'admin') {
        return 'admin';
    }

    if ($order instanceof WC_Order) {
        switch ($order->get_created_via()) {
            case 'admin':
                return 'admin';
            case 'import':
                return 'import';
        }

        return 'purchase';
    }

    return get_post_type($attendee_id) === 'tribe_rsvp_attendees' ? 'rsvp' : 'purchase';
}

/**
 * Resolve an attendee's email address to an MDP person, for a touchpoint.
 *
 * Matches on the primary address first, then across every address on a record, so a
 * returning attendee who signed up with a secondary address is recognised instead of being
 * duplicated. When several people share an address and none holds it as their primary
 * there is no safe choice, so the touchpoint is skipped rather than guessed.
 *
 * @param string $email The attendee's email address.
 * @param array  $args  Passed through to wicket_resolve_person_by_email().
 * @return array The resolver result.
 */
function wicket_tec_resolve_attendee_person(string $email, array $args = []): array
{
    /*
     * Filter what to do when an email matches several MDP people.
     *
     * 'error' skips the touchpoint and logs it for review, which is the default. 'first'
     * restores the older behaviour of taking whichever record came back first.
     *
     * @param string $strategy 'error' or 'first'.
     * @param string $email    The email being resolved.
     * @param array  $args     The resolver arguments.
     */
    $args['on_ambiguous'] = (string) apply_filters('wicket_tec_ambiguous_person_strategy', 'error', $email, $args);

    return wicket_resolve_person_by_email($email, $args);
}

/**
 * Get an attendee's registration form answers as label/value pairs.
 *
 * Event Tickets Plus already returns these sanitised, with checkbox multi-values joined
 * and unanswered fields skipped, so there is nothing to build here. Only fields in the
 * ticket's configured fieldset are returned: anything injected at render time (this
 * plugin adds a last-name field that way) will not appear.
 *
 * @param int $ticket_id   The ticket product post ID.
 * @param int $attendee_id The attendee post ID.
 * @param int $event_id    The event post ID, passed to the filter for context.
 * @return array<string, string> Label to value, empty when there are no answers.
 */
function wicket_tec_registration_answers(int $ticket_id, int $attendee_id, int $event_id = 0): array
{
    $answers = [];

    if ($ticket_id > 0 && $attendee_id > 0 && function_exists('tribe')) {
        try {
            $meta = tribe('tickets-plus.meta');

            if (is_object($meta) && method_exists($meta, 'get_attendee_meta_values')) {
                $values = $meta->get_attendee_meta_values($ticket_id, $attendee_id);

                if (is_array($values)) {
                    foreach ($values as $label => $value) {
                        // Field labels are author-entered and often carry trailing spaces.
                        $label = trim((string) $label);

                        if ($label !== '') {
                            $answers[$label] = is_scalar($value) ? (string) $value : wp_json_encode($value);
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            // Event Tickets Plus absent or the container failed: no answers, no fuss.
            $answers = [];
        }
    }

    /**
     * Filter the registration form answers included in a touchpoint.
     *
     * Every field is sent by default. Unset individual fields, or return an empty array,
     * to keep answers out of the MDP for a given site or event.
     *
     * @param array<string, string> $answers     Label to value.
     * @param int                   $attendee_id The attendee post ID.
     * @param int                   $ticket_id   The ticket product post ID.
     * @param int                   $event_id    The event post ID.
     */
    $answers = apply_filters('wicket_tec_registration_answers', $answers, $attendee_id, $ticket_id, $event_id);

    return is_array($answers) ? $answers : [];
}

/**
 * Convert raw slug/value registration answers into label/value pairs.
 *
 * Used by the purchase writer, which reads its answers straight off the order's
 * _tribe_tickets_meta rather than from an attendee post. Only slugs present in the
 * ticket's configured fieldset are kept, which is what excludes the individual-attendee
 * name and email fields and any field injected at render time, matching what
 * wicket_tec_registration_answers() returns for a saved attendee.
 *
 * @param array $raw       Raw answers, keyed by field slug.
 * @param int   $ticket_id The ticket product post ID.
 * @param int   $event_id  The event post ID, passed to the filter for context.
 * @return array<string, string> Label to value.
 */
function wicket_tec_registration_answers_from_raw(array $raw, int $ticket_id, int $event_id = 0): array
{
    $answers = [];

    if ($raw !== [] && $ticket_id > 0 && function_exists('tribe')) {
        try {
            $meta = tribe('tickets-plus.meta');

            // Note: get_meta_fields_by_ticket() is an instance method, unlike the static
            // get_attendee_meta_fields(), so it has to come off the container.
            if (is_object($meta) && method_exists($meta, 'get_meta_fields_by_ticket')) {
                foreach ((array) $meta->get_meta_fields_by_ticket($ticket_id) as $field) {
                    $slug = isset($field->slug) ? (string) $field->slug : '';
                    $label = isset($field->label) ? trim((string) $field->label) : '';

                    if ($slug === '' || $label === '' || !isset($raw[$slug])) {
                        continue;
                    }

                    $value = $raw[$slug];

                    if (is_array($value)) {
                        // Checkbox answers arrive as an array, joined the way Event Tickets
                        // Plus joins them for a saved attendee.
                        $value = implode(', ', array_map('strval', $value));
                    }

                    $value = (string) $value;

                    if ($value !== '') {
                        $answers[$label] = $value;
                    }
                }
            }
        } catch (Throwable $e) {
            $answers = [];
        }
    }

    /** This filter is documented in wicket_tec_registration_answers(). */
    $answers = apply_filters('wicket_tec_registration_answers', $answers, 0, $ticket_id, $event_id);

    return is_array($answers) ? $answers : [];
}

/**
 * Get a WordPress user's display name, for touchpoint details.
 *
 * @param int $user_id The WordPress user ID.
 * @return string The display name, or '' when the user cannot be resolved.
 */
function wicket_tec_user_display_name(int $user_id): string
{
    if ($user_id <= 0) {
        return '';
    }

    $user = get_userdata($user_id);

    if (!$user) {
        return '';
    }

    $name = trim((string) $user->display_name);

    return $name !== '' ? $name : (string) $user->user_login;
}

/**
 * Work out which WordPress user added an attendee.
 *
 * Event Tickets Plus records the acting user on a manual add. The CSV importer records
 * nothing, so the user running the import is the best available answer.
 *
 * @param int $attendee_id The attendee post ID.
 * @return int The user ID, or 0 when there is none.
 */
function wicket_tec_attendee_added_by(int $attendee_id): int
{
    $added_by = (int) get_post_meta($attendee_id, '_tribe_attendee_added_by', true);

    return $added_by > 0 ? $added_by : get_current_user_id();
}

/**
 * Describe who added an attendee, for the touchpoint details string.
 *
 * Prefers the person's name, since that is what makes the entry useful to whoever is
 * reading the profile later, and notes the mechanism after it. Falls back to the
 * mechanism alone when no user can be resolved, which happens for imports run over
 * WP-CLI or cron with no logged-in user.
 *
 * @param string $origin  'admin' or 'import'.
 * @param int    $user_id The acting user ID, or 0.
 * @return string A readable description, e.g. 'Jane Smith' or 'Jane Smith (CSV import)'.
 */
function wicket_tec_added_by_label(string $origin, int $user_id): string
{
    $name = wicket_tec_user_display_name($user_id);

    if ($origin === 'import') {
        return $name !== ''
            ? sprintf(__('%s (CSV import)', 'wicket'), $name)
            : __('CSV import', 'wicket');
    }

    return $name !== '' ? $name : __('An administrator', 'wicket');
}

/**
 * Render registration answers as an appendable block for a touchpoint's details string.
 *
 * @param array<string, string> $answers Label to value.
 * @return string The block, or '' when there are no answers.
 */
function wicket_tec_registration_answers_details(array $answers): string
{
    if ($answers === []) {
        return '';
    }

    $details = 'Registration Answers:<br />';
    foreach ($answers as $label => $value) {
        $details .= '&nbsp;&nbsp;' . $label . ': ' . $value . '<br />';
    }

    return $details;
}

/**
 * Resolve an attendee's name and email, whichever provider created them.
 *
 * tribe_tickets_get_attendees() is preferred, but its attendee_meta is shaped differently
 * per provider, so only its flat fields are read. Postmeta and the order's billing details
 * are used as fallbacks, which matters for CSV-imported WooCommerce attendees: the
 * importer writes no name or email postmeta at all, leaving the order as the only source.
 *
 * @param int            $attendee_id The attendee post ID.
 * @param int            $ticket_id   The ticket product post ID.
 * @param WC_Order|null $order       The order, when there is one.
 * @return array{email: string, first_name: string, last_name: string, full_name: string}
 */
function wicket_tec_attendee_identity(int $attendee_id, int $ticket_id = 0, $order = null): array
{
    $email = '';
    $full_name = '';

    if (function_exists('tribe_tickets_get_attendees')) {
        $attendees = tribe_tickets_get_attendees($attendee_id);
        $attendee = is_array($attendees) ? ($attendees[0] ?? null) : null;

        if (is_array($attendee)) {
            $email = (string) ($attendee['holder_email'] ?? '');
            $full_name = (string) ($attendee['holder_name'] ?? '');
        }
    }

    // Provider-specific postmeta, then the generic Event Tickets Plus keys.
    if ($email === '') {
        foreach (['_tribe_rsvp_email', '_tec_tickets_commerce_email', '_tribe_tickets_email'] as $key) {
            $email = (string) get_post_meta($attendee_id, $key, true);
            if ($email !== '') {
                break;
            }
        }
    }

    if ($full_name === '') {
        foreach (['_tribe_rsvp_full_name', '_tec_tickets_commerce_full_name', '_tribe_tickets_full_name'] as $key) {
            $full_name = (string) get_post_meta($attendee_id, $key, true);
            if ($full_name !== '') {
                break;
            }
        }
    }

    // Last resort: the order's billing details. This is the only source for a
    // CSV-imported WooCommerce attendee.
    if (($email === '' || $full_name === '') && $order instanceof WC_Order) {
        if ($email === '') {
            $email = (string) $order->get_billing_email();
        }

        if ($full_name === '') {
            $full_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        }
    }

    // A last-name field is commonly added to the registration form, including by this
    // plugin's own field hooks, so prefer the answers when they carry one.
    $raw_answers = [];
    if ($ticket_id > 0 && class_exists('Tribe__Tickets_Plus__Meta')) {
        $raw_answers = Tribe__Tickets_Plus__Meta::get_attendee_meta_fields($ticket_id, $attendee_id);
        $raw_answers = is_array($raw_answers) ? $raw_answers : [];
    }

    $first_name = '';
    $last_name = '';

    foreach (['first-name', 'name', 'tribe-tickets-plus-iac-name'] as $key) {
        if (!empty($raw_answers[$key]) && is_scalar($raw_answers[$key])) {
            $first_name = trim((string) $raw_answers[$key]);
            break;
        }
    }

    if (!empty($raw_answers['last-name']) && is_scalar($raw_answers['last-name'])) {
        $last_name = trim((string) $raw_answers['last-name']);
    }

    // Fall back to splitting the holder name the way Event Tickets Plus does: first token
    // is the given name, the remainder is the family name.
    if ($first_name === '' || $last_name === '') {
        $parts = preg_split('/\s+/', trim($full_name), 2) ?: [];

        if ($first_name === '') {
            $first_name = (string) ($parts[0] ?? '');
        }

        if ($last_name === '') {
            $last_name = (string) ($parts[1] ?? '');
        }
    }

    return [
        'email' => sanitize_email($email),
        'first_name' => $first_name,
        'last_name' => $last_name,
        'full_name' => $full_name,
    ];
}

/**
 * Get the ticket product ID for an attendee, whichever provider created them.
 *
 * @param int $attendee_id The attendee post ID.
 * @return int The ticket product post ID, or 0.
 */
function wicket_tec_attendee_ticket_id(int $attendee_id): int
{
    $keys = [
        '_tribe_wooticket_product',
        '_tribe_rsvp_product',
        '_tribe_eddticket_product',
        '_tec_tickets_commerce_ticket',
        '_tribe_tpp_product',
    ];

    foreach ($keys as $key) {
        $ticket_id = (int) get_post_meta($attendee_id, $key, true);

        if ($ticket_id > 0) {
            return $ticket_id;
        }
    }

    return 0;
}

/**
 * Get the event ID for an attendee, whichever provider created them.
 *
 * @param int $attendee_id The attendee post ID.
 * @return int The event post ID, or 0.
 */
function wicket_tec_attendee_event_id(int $attendee_id): int
{
    $keys = [
        '_tribe_wooticket_event',
        '_tribe_rsvp_event',
        '_tribe_eddticket_event',
        '_tec_tickets_commerce_event',
        '_tribe_tpp_event',
    ];

    foreach ($keys as $key) {
        $event_id = (int) get_post_meta($attendee_id, $key, true);

        if ($event_id > 0) {
            return $event_id;
        }
    }

    return 0;
}

/**
 * Build a stable external event ID for MDP-side deduplication.
 *
 * Deliberately derived only from the kind and the attendee ID, so re-running the same
 * write produces the same identifier. The site component keeps two sites that share a
 * Wicket tenant from colliding, since touchpoint services are looked up by name.
 *
 * @param string $kind        A short label, e.g. 'reg' or 'removal'.
 * @param int    $attendee_id The attendee post ID.
 * @return string The external event ID.
 */
function wicket_tec_external_event_id(string $kind, int $attendee_id): string
{
    return implode('_', ['tec', $kind, substr(md5((string) get_home_url()), 0, 8), (string) $attendee_id]);
}

/**
 * Log a failure to resolve an MDP person for a touchpoint.
 *
 * An ambiguous address is the interesting case: several people share it and none holds it
 * as their primary, so there is no safe way to choose. The touchpoint is skipped rather
 * than guessed, and logged with the candidate UUIDs so it can be sorted out by hand.
 *
 * @param array $person  The result from wicket_resolve_person_by_email().
 * @param array $context Extra context for the log entry.
 */
function wicket_tec_log_person_resolution_failure(array $person, array $context = []): void
{
    $email = (string) ($context['email'] ?? '');

    if (($person['status'] ?? '') === 'ambiguous') {
        $message = sprintf(
            'Skipped event touchpoint: %d Wicket people share this email address and none holds it as their primary, so manual review is needed.',
            (int) ($person['total'] ?? 0)
        );

        wicket_tec_log_error($message, array_merge($context, [
            'reason' => 'person_ambiguous',
            'match_uuids' => $person['matches'] ?? [],
            'total_matches' => $person['total'] ?? 0,
        ]));

        /*
         * Fires when an attendee's email cannot be resolved to a single MDP person.
         *
         * Lets a site route these to email, Slack or a review queue.
         *
         * @param string $email   The ambiguous email address.
         * @param array  $uuids   The candidate person UUIDs.
         * @param array  $context Attendee, event and action context.
         */
        do_action('wicket_tec_touchpoint_person_ambiguous', $email, $person['matches'] ?? [], $context);

        return;
    }

    wicket_tec_log_error('Skipped event touchpoint: could not resolve a Wicket person', array_merge($context, [
        'reason' => $person['code'] ?? 'unknown',
    ]));
}

/**
 * Reduce the canonical event data to one of the legacy key sets.
 *
 * The three original builders returned different subsets in a specific order, and
 * site themes may iterate them. Wrappers use this so their output is unchanged.
 *
 * @param array  $data  Canonical data from wicket_tec_event_data().
 * @param string $shape 'event' (purchase writer) or 'ticket' (RSVP and check-in writers).
 * @return array The data reduced to the legacy key set, in the legacy order.
 */
function wicket_tec_event_data_legacy_shape(array $data, string $shape): array
{
    $keys = ['start', 'end', 'event_name', 'event_id', 'url', 'event_type', 'location', 'format'];

    $legacy = [];
    foreach ($keys as $key) {
        if (array_key_exists($key, $data)) {
            $legacy[$key] = $data[$key];
        }
    }

    // Only the purchase writer's builder ever exposed additional fields, and only when
    // the event actually had some. Preserve that: absent rather than an empty array.
    if ($shape === 'event' && !empty($data['event_additional_fields'])) {
        $legacy['event_additional_fields'] = $data['event_additional_fields'];
    }

    return $legacy;
}
