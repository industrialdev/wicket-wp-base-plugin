<?php
/**
 * Experimental Datastar endpoint: org search.
 *
 * HyperPress template. Resolves as 'wicket:orgss-search' via HyperPress's
 * router at /wp-html/v1/wicket:orgss-search. Loaded by HyperPress\Render when
 * the variant UI fires @get against hp_get_endpoint_url('wicket:orgss-search').
 *
 * $hp_vals is the sanitized $_REQUEST (HyperPress sanitizes keys with
 * sanitize_key and values with sanitize_text_field), so param names must be
 * snake_case to survive key sanitization.
 *
 * Runs the same MDP search as the JSON search_orgs handler, renders the
 * results server-side, and morphs #orgss-results-<key> over SSE via hp_ds_*.
 * Selecting a result sets the selectedOrgUuid signal only; relationship
 * creation lands in a later slice.
 *
 * @see docs/org-search-select-datastar-migration-spec.md (slice 1)
 */

// No direct access.
defined('ABSPATH') || exit;

// Capability: search requires a logged-in user. HyperPress enforces the nonce
// on POST only; this GET endpoint relies on the authenticated session plus the
// auto-attached nonce from HyperPress's fetch wrapper.
if (!is_user_logged_in()) {
    hp_die(__('Not allowed.', 'wicket'));
}

$key       = isset($hp_vals['orgss_key']) ? (string) $hp_vals['orgss_key'] : '';
$target    = $key !== '' ? '#orgss-results-' . $key : '#orgss-results';
$ns        = $key !== '' ? 'orgss_' . $key : 'orgss';

// SSE rate limit (sends an SSE error into the results region when blocked).
if (hp_ds_is_rate_limited([
    'requests_per_window' => 30,
    'time_window_seconds' => 60,
    'identifier'          => 'orgss_search_' . get_current_user_id(),
    'error_selector'      => $target,
])) {
    return;
}

$term      = isset($hp_vals['search_term']) ? (string) $hp_vals['search_term'] : '';
$org_type  = isset($hp_vals['org_type']) ? (string) $hp_vals['org_type'] : '';
$lang      = isset($hp_vals['lang']) ? (string) $hp_vals['lang'] : 'en';
$display   = isset($hp_vals['display']) ? (string) $hp_vals['display'] : 'name';
$show_type = isset($hp_vals['display_org_type']) && $hp_vals['display_org_type'] === '1';

if (trim($term) === '') {
    hp_ds_patch_elements(
        '<div class="component-org-search-select__search-message">' . esc_html(__('Please provide a search term', 'wicket')) . '</div>',
        ['selector' => $target, 'mode' => 'inner']
    );

    return;
}

// Same call the JSON handler makes on the non-membership-summary path.
$results = wicket_search_organizations($term, 'org_name', $org_type, false, $lang, false);

if ($results === false) {
    hp_ds_patch_elements(
        '<div class="component-org-search-select__search-message">' . esc_html(__('There was a problem searching organizations.', 'wicket')) . '</div>',
        ['selector' => $target, 'mode' => 'inner']
    );

    return;
}

$build_subtitle = static function (array $r, string $d): string {
    if ($d === 'name') {
        return '';
    }

    $city    = (string) ($r['city'] ?? '');
    $state   = (string) ($r['state_name'] ?? '');
    $country = (string) ($r['country_code'] ?? '');

    if ($d === 'name_location') {
        return implode(', ', array_filter([$city, $state, $country], fn ($v) => $v !== ''));
    }

    // name_address.
    $address1  = (string) ($r['address1'] ?? '');
    $zip       = (string) ($r['zip_code'] ?? '');
    $state_zip = trim(($state !== '' ? $state : '') . ($zip !== '' ? ($state !== '' ? ' ' : '') . $zip : ''));

    return implode(', ', array_filter([$address1, $city, $state_zip, $country], fn ($v) => $v !== ''));
};

ob_start();

if (empty($results)) {
    echo '<div class="component-org-search-select__search-message">'
        . esc_html(__('Sorry, no organizations match your search. Please try again.', 'wicket'))
        . '</div>';
} else {
    echo '<div class="component-org-search-select__results-list flex flex-col">';

    foreach ($results as $result) {
        $id = isset($result['id']) ? (string) $result['id'] : '';
        if ($id === '') {
            continue;
        }

        $name          = isset($result['name']) ? (string) $result['name'] : '';
        $type_name     = isset($result['type_name']) ? (string) $result['type_name'] : '';
        $subtitle      = $build_subtitle($result, $display);
        $select_url    = hp_get_endpoint_url('wicket:orgss-select') . '?org_uuid=' . rawurlencode($id) . '&orgss_key=' . rawurlencode($key);
        $select_expr   = "@post('" . $select_url . "')";
        $is_sel_expr   = $ns . ".selectedOrgUuid === '" . esc_js($id) . "'";
        $text_expr     = $is_sel_expr . " ? '\u2713 " . esc_js(__('Selected', 'wicket')) . "' : '" . esc_js(__('Select', 'wicket')) . "'";
        ?>
        <div class="component-org-search-select__matching-org-item flex justify-between items-center px-1 py-3 border-b border-dark-100 border-opacity-5">
            <div>
                <div class="component-org-search-select__matching-org-title mb-1 font-bold"><?php echo esc_html($name); ?></div>
                <?php if ($subtitle !== '') : ?>
                    <div class="component-org-search-select__matching-org-subtitle"><?php echo esc_html($subtitle); ?></div>
                <?php endif; ?>
            </div>
            <?php if ($show_type && $type_name !== '') : ?>
                <div class="component-org-search-select__matching-org-type"><?php echo esc_html($type_name); ?></div>
            <?php endif; ?>
            <div class="component-org-search-select__matching-org-action">
                <button type="button"
                        class="component-org-search-select__select-result-button"
                        data-indicator="<?php echo esc_attr($ns); ?>.loading"
                        data-on:click="<?php echo esc_attr($select_expr); ?>"
                        data-text="<?php echo esc_attr($text_expr); ?>"
                        data-class:orgss_disabled_button="<?php echo esc_attr($is_sel_expr); ?>">
                    <?php esc_html_e('Select', 'wicket'); ?>
                </button>
            </div>
        </div>
        <?php
    }

    echo '</div>';
}

hp_ds_patch_elements((string) ob_get_clean(), ['selector' => $target, 'mode' => 'inner']);
