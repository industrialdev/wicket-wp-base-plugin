<?php
/**
 * Experimental Datastar endpoint: transport proof (ping).
 *
 * HyperPress template, resolves as 'wicket:orgss-ping'. Patches a signal and an
 * element to confirm the HyperPress router + SSE transport end to end.
 */

// No direct access.
defined('ABSPATH') || exit;

if (!is_user_logged_in()) {
    hp_die(__('Not allowed.', 'wicket'));
}

$stamp = gmdate('Y-m-d H:i:s');

hp_ds_patch_signals(['orgssPingAt' => $stamp]);
hp_ds_patch_elements('<div id="orgss-ds-ping-target">pong @ ' . esc_html($stamp) . '</div>');
