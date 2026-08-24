<?php

declare(strict_types=1);

// No direct access
defined('ABSPATH') || exit;

/**
 * Works around a duplicate-import bug in The Events Calendar's CSV importer.
 *
 * Event Aggregator imports a CSV in batches of 5 rows. Which row it is on is kept in the
 * `tribe_events_importer_offset` option, written only once a batch has finished
 * (Tribe__Events__Aggregator__Record__CSV::do_import()) and read back with get_option()
 * at the start of the next batch (::continue_import()).
 *
 * Batches are driven from two places at once: the Event Aggregator ajax loop, and the
 * WordPress heartbeat of every open admin screen showing the "import in progress" notice
 * (Tribe__Events__Aggregator__Record__Queue_Realtime hooks both). The guard meant to stop
 * two processors working the same record, Queue::is_in_progress(), never returns its
 * value, so it is always falsy.
 *
 * Because the option is autoloaded, WordPress serves it from the cache it filled when the
 * request booted. A processor that booted while an earlier batch was still running
 * therefore reads a row cursor from before that batch's write, and imports the same rows
 * again: a second WooCommerce order, a second attendee, a second ticket email and a
 * second set of MDP touchpoints for everyone in the batch. The same stale read makes
 * `tribe_events_import_log` under-report, because the second processor writes its own
 * count over the first's.
 *
 * Reading both options straight from the table closes it. TEC already takes a per-record
 * database lock around the read-import-write in continue_import(), so an uncached read
 * turns that into a correct critical section: a processor either fails to take the lock
 * and does nothing, or reads the cursor the previous batch just wrote.
 *
 * Remove this file once TEC ships a fix. Both defects are reported upstream; see the
 * ticket referenced in the changelog entry for this change.
 */

/**
 * Read an option straight from the options table, bypassing the request's cache.
 *
 * @param  string     $option The option name.
 * @return mixed|null The stored value, or null when the option does not exist.
 */
function wicket_tec_read_option_uncached(string $option)
{
    global $wpdb;

    $raw = $wpdb->get_var(
        $wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", $option)
    );

    return $raw === null ? null : maybe_unserialize($raw);
}

/**
 * Serve the CSV importer's row cursor and running tally uncached.
 *
 * Returning anything other than the value passed in short-circuits get_option(), so an
 * option that is not set has to fall through untouched.
 *
 * @param  mixed  $pre    The short-circuit value, false unless another filter set one.
 * @param  string $option The option being read.
 * @return mixed  The value from the table, or $pre to let WordPress carry on.
 */
function wicket_tec_csv_import_uncached_option($pre, string $option)
{
    // Another filter already answered: leave it alone.
    if ($pre !== false) {
        return $pre;
    }

    $value = wicket_tec_read_option_uncached($option);

    return $value === null ? $pre : $value;
}

add_filter('pre_option_tribe_events_importer_offset', 'wicket_tec_csv_import_uncached_option', 10, 2);
add_filter('pre_option_tribe_events_import_log', 'wicket_tec_csv_import_uncached_option', 10, 2);
