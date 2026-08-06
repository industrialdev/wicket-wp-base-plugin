<?php
/**
 * Experimental Datastar endpoint: terminate a person-to-org connection.
 *
 * HyperPress template, resolves as 'wicket:orgss-terminate'. POST
 * (nonce-protected by HyperPress). Triggered by the Remove button on a current
 * connection card.
 *
 * Security note: this variant is secure by design relative to the shipped JSON
 * terminate-relationship handler. Before ending the connection, it verifies the
 * connection_id belongs to the session user's own connections
 * (wicket_get_person_connections), so a caller cannot sever another person's
 * relationships (the destructive IDOR the shipped endpoint has). HyperPress
 * enforces the nonce on POST; hp_ds_is_rate_limited caps abuse.
 *
 * On success the connection is ended and the card is removed from the DOM.
 *
 * @see docs/org-search-select-datastar-migration-spec.md (slice 4)
 */

// No direct access.
defined('ABSPATH') || exit;

if (!is_user_logged_in()) {
    hp_die(__('Not allowed.', 'wicket'));
}

$connection_id = isset($hp_vals['connection_id']) ? (string) $hp_vals['connection_id'] : '';
$key           = isset($hp_vals['orgss_key']) ? (string) $hp_vals['orgss_key'] : '';
$card_selector = $connection_id !== '' ? '#orgss-conn-' . $connection_id : '';

if (hp_ds_is_rate_limited([
    'requests_per_window' => 20,
    'time_window_seconds' => 60,
    'identifier'          => 'orgss_terminate_' . get_current_user_id(),
])) {
    return;
}

if ($connection_id === '') {
    hp_ds_patch_signals(['orgssTerminateError' => __('No connection selected.', 'wicket')]);
    return;
}

// Session-scoped ownership: the connection must be in the user's own list.
$owns = false;
if (function_exists('wicket_get_person_connections')) {
    $mine = wicket_get_person_connections(['dedupe' => 'org_id']);
    foreach (($mine['data'] ?? []) as $c) {
        if (($c['id'] ?? '') === $connection_id) {
            $owns = true;
            break;
        }
    }
}

if (!$owns) {
    hp_ds_patch_signals(['orgssTerminateError' => __('You can only remove your own connections.', 'wicket')]);
    return;
}

// End the connection (sets end date; mirrors the shipped handler's default path).
$ended = function_exists('wicket_end_connection') ? wicket_end_connection($connection_id) : false;

if (!$ended) {
    hp_ds_patch_signals(['orgssTerminateError' => __('We could not remove this connection. Please try again.', 'wicket')]);
    return;
}

hp_ds_remove_elements($card_selector);
