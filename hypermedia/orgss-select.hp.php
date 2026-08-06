<?php
/**
 * Experimental Datastar endpoint: select org + create relationship.
 *
 * HyperPress template, resolves as 'wicket:orgss-select'. POST (nonce-protected
 * by HyperPress). Triggered by the search result Select button.
 *
 * Security note: this variant is secure by design relative to the shipped JSON
 * create-relationship handler. The person UUID comes from the session
 * (wicket_current_person_uuid), never from the request body, so a caller cannot
 * forge or reopen connections for another person. HyperPress enforces the nonce
 * on POST and hp_ds_is_rate_limited caps abuse.
 *
 * Config (connectionType, connectionRole, formId) is read from the Datastar
 * signals sent in the POST body; org_uuid and orgss_key come from the URL.
 *
 * On success: sets selectedOrgUuid, morphs a selected-org card into
 * #orgss-results-<key>, and dispatches the 'orgss-selection-made' window event
 * so Gravity Forms and WooCommerce consumers react without changes.
 *
 * @see docs/org-search-select-datastar-migration-spec.md (slice 2)
 */

// No direct access.
defined('ABSPATH') || exit;

if (!is_user_logged_in()) {
    hp_die(__('Not allowed.', 'wicket'));
}

$org_uuid = isset($hp_vals['org_uuid']) ? (string) $hp_vals['org_uuid'] : '';
$key      = isset($hp_vals['orgss_key']) ? (string) $hp_vals['orgss_key'] : '';
$ns       = $key !== '' ? 'orgss_' . $key : 'orgss';
$target   = $key !== '' ? '#orgss-results-' . $key : '#orgss-results';

$message = static function (string $text) use ($target): void {
    hp_ds_patch_elements(
        '<div class="component-org-search-select__search-message">' . esc_html($text) . '</div>',
        ['selector' => $target, 'mode' => 'inner']
    );
};

if (hp_ds_is_rate_limited([
    'requests_per_window' => 20,
    'time_window_seconds' => 60,
    'identifier'          => 'orgss_select_' . get_current_user_id(),
    'error_selector'      => $target,
])) {
    return;
}

if ($org_uuid === '') {
    $message(__('No organization selected.', 'wicket'));
    return;
}

// Config travels in the POST body (Datastar signals).
$signals         = hp_ds_read_signals();
$cfg             = $signals[$ns] ?? [];
$connection_type = isset($cfg['connectionType']) ? (string) $cfg['connectionType'] : 'person_to_organization';
$connection_role = isset($cfg['connectionRole']) ? (string) $cfg['connectionRole'] : 'employee';
$form_id         = isset($cfg['formId']) ? (int) $cfg['formId'] : 0;

// Session person. Never the request body.
$person_uuid = function_exists('wicket_current_person_uuid') ? wicket_current_person_uuid() : '';
if ($person_uuid === '') {
    $message(__('You must be signed in to select an organization.', 'wicket'));
    return;
}

// The subsidiaries (organization_parent) flow is a different endpoint and path;
// out of scope for this slice.
if ($connection_type === 'organization_parent') {
    $message(__('Parent-organization linking is not supported in this experiment yet.', 'wicket'));
    return;
}

// Create or reopen the connection (session-scoped). Shared helper, also used
// by orgss-create-org.
$created_ok = \WicketWP\OrgssDatastar::createOrReopenConnection($person_uuid, $org_uuid, $connection_type, $connection_role);

if (!$created_ok) {
    $message(__('There was an error creating the connection. Please try again.', 'wicket'));
    return;
}

// Success: record the selection in a signal (drives the hidden field).
hp_ds_patch_signals([$ns => ['selectedOrgUuid' => $org_uuid]]);

$org_info = function_exists('wicket_get_organization_basic_info')
    ? wicket_get_organization_basic_info($org_uuid)
    : [];
$org_name = $org_info['org_name'] ?? '';

ob_start();
?>
<div class="component-org-search-select__selected-card flex items-center justify-between px-1 py-3 border-b border-dark-100 border-opacity-5">
    <div>
        <div class="component-org-search-select__selected-label mb-1"><?php esc_html_e('Selected', 'wicket'); ?></div>
        <div class="component-org-search-select__selected-name font-bold"><?php echo esc_html($org_name); ?></div>
    </div>
    <button type="button"
            class="component-org-search-select__clear-selection-button"
            data-on:click="$<?php echo esc_attr($ns); ?>.selectedOrgUuid = ''">
        <?php esc_html_e('Change', 'wicket'); ?>
    </button>
</div>
<?php
hp_ds_patch_elements((string) ob_get_clean(), ['selector' => $target, 'mode' => 'inner']);

// External contract: tell Gravity Forms / WooCommerce consumers a selection was made.
$event_detail = [
    'uuid'               => $org_uuid,
    'searchType'         => 'org',
    'orgDetails'         => ['org_id' => $org_uuid, 'org_name' => $org_name],
    'formId'             => $form_id,
    'orgSearchSelectKey' => (string) $key,
];
hp_ds_execute_script(
    'window.dispatchEvent(new CustomEvent("orgss-selection-made", { detail: ' . wp_json_encode($event_detail) . ' }));'
);
