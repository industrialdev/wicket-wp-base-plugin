<?php

declare(strict_types=1);

// No direct access
defined('ABSPATH') || exit;

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
