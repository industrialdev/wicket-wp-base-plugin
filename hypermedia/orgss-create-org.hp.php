<?php
/**
 * Experimental Datastar endpoint: create a new organization, then select it.
 *
 * HyperPress template, resolves as 'wicket:orgss-create-org'. POST
 * (nonce-protected by HyperPress). Triggered by the variant's create-org form.
 *
 * Duplicate guard runs server-side (exact name + type match) and renders the
 * warning directly, so there is no client-side English-string matching to break
 * (the brittle pattern the Alpine client used).
 *
 * On success the new org is selected: the person-to-org connection is created
 * using the SESSION person, the selected-org card is morphed into the results
 * region, and the 'orgss-selection-made' event is dispatched. The connection
 * logic mirrors orgss-select.hp.php (kept inline so templates stay independent).
 *
 * @see docs/org-search-select-datastar-migration-spec.md (slice 3)
 */

// No direct access.
defined('ABSPATH') || exit;

if (!is_user_logged_in()) {
    hp_die(__('Not allowed.', 'wicket'));
}

$key    = isset($hp_vals['orgss_key']) ? (string) $hp_vals['orgss_key'] : '';
$ns     = $key !== '' ? 'orgss_' . $key : 'orgss';
$target = $key !== '' ? '#orgss-results-' . $key : '#orgss-results';
$dup_target = $key !== '' ? '#orgss-duplicate-' . $key : '#orgss-duplicate';

$message = static function (string $text) use ($target): void {
    hp_ds_patch_elements(
        '<div class="component-org-search-select__search-message">' . esc_html($text) . '</div>',
        ['selector' => $target, 'mode' => 'inner']
    );
};

if (hp_ds_is_rate_limited([
    'requests_per_window' => 10,
    'time_window_seconds' => 60,
    'identifier'          => 'orgss_create_org_' . get_current_user_id(),
    'error_selector'      => $target,
])) {
    return;
}

$org_name = isset($hp_vals['org_name']) ? (string) $hp_vals['org_name'] : '';
$org_type = isset($hp_vals['org_type']) ? (string) $hp_vals['org_type'] : '';

if (trim($org_name) === '' || $org_type === '') {
    $message(__('Please provide an organization name and type.', 'wicket'));
    return;
}

// Config + session person.
$signals         = hp_ds_read_signals();
$cfg             = $signals[$ns] ?? [];
$connection_type = isset($cfg['connectionType']) ? (string) $cfg['connectionType'] : 'person_to_organization';
$connection_role = isset($cfg['connectionRole']) ? (string) $cfg['connectionRole'] : 'employee';
$form_id         = isset($cfg['formId']) ? (int) $cfg['formId'] : 0;
$person_uuid     = function_exists('wicket_current_person_uuid') ? wicket_current_person_uuid() : '';

if ($person_uuid === '') {
    $message(__('You must be signed in to create an organization.', 'wicket'));
    return;
}

// Duplicate guard: exact name + type match (mirrors the JSON create-org handler).
$name_lower = trim(strtolower($org_name));
$type_lower = trim(strtolower($org_type));
$duplicate  = false;

if (function_exists('wicket_search_organizations')) {
    $search = wicket_search_organizations($name_lower, 'org_name', $org_type, true);
    if (is_array($search)) {
        foreach ($search as $r) {
            $r_name = isset($r['name']) ? trim(strtolower((string) $r['name'])) : '';
            $r_type = isset($r['type']) ? trim(strtolower((string) $r['type'])) : '';
            if ($r_name === $name_lower && $r_type === $type_lower) {
                $duplicate = true;
                break;
            }
        }
    }
}

if ($duplicate) {
    ob_start();
    ?>
    <div class="component-org-search-select__duplicate-warning" style="margin-top:.5rem; padding:.5rem .75rem; border-left:4px solid #dc3545; background:#f8d7da; color:#721c24;">
        <strong><?php echo esc_html(sprintf(__('%s you are trying to add already exists', 'wicket'), __('Organization', 'wicket'))); ?></strong><br>
        <?php esc_html_e('Search for it above to select the existing record.', 'wicket'); ?>
    </div>
    <?php
    hp_ds_patch_elements((string) ob_get_clean(), ['selector' => $dup_target, 'mode' => 'inner']);
    return;
}

// Clear any stale duplicate warning.
hp_ds_remove_elements($dup_target);

// Create the organization.
$created = function_exists('wicket_create_organization') ? wicket_create_organization($org_name, $org_type) : null;
$new_uuid = is_array($created) ? (($created['data']['id'] ?? null) ?: ($created['data']['data']['id'] ?? '')) : '';

if ($new_uuid === '') {
    $message(sprintf(__('There was an error creating the %s, please try again.', 'wicket'), strtolower(__('Organization', 'wicket'))));
    return;
}

// Create or reopen the person-to-org connection (session-scoped). Shared helper.
$created_ok = \WicketWP\OrgssDatastar::createOrReopenConnection($person_uuid, $new_uuid, $connection_type, $connection_role);

if (!$created_ok) {
    $message(__('The organization was created but the connection failed. Please select it from the list.', 'wicket'));
    return;
}

// Select the new org.
hp_ds_patch_signals([
    $ns => [
        'selectedOrgUuid' => $new_uuid,
        'newOrgName'      => '',
        'newOrgType'      => '',
        'justCreatedOrgUuid' => $new_uuid,
    ],
]);

ob_start();
?>
<div class="component-org-search-select__selected-card flex items-center justify-between px-1 py-3 border-b border-dark-100 border-opacity-5">
    <div>
        <div class="component-org-search-select__selected-label mb-1"><?php esc_html_e('Selected (new)', 'wicket'); ?></div>
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

$event_detail = [
    'uuid'               => $new_uuid,
    'searchType'         => 'org',
    'orgDetails'         => ['org_id' => $new_uuid, 'org_name' => $org_name, 'created' => true],
    'formId'             => $form_id,
    'orgSearchSelectKey' => (string) $key,
];
hp_ds_execute_script(
    'window.dispatchEvent(new CustomEvent("orgss-selection-made", { detail: ' . wp_json_encode($event_detail) . ' }));'
);
