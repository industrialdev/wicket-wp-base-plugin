<?php
/**
 * Experimental Datastar variant of the org-search-select component.
 *
 * Loaded by get_component() when the 'wicket_orgss_variant' filter returns
 * 'datastar'. Default stays 'alpine', so this file is not reached in production
 * unless a child theme opts in.
 *
 * Slice 1: live org search. The input drives an @get to the orgss-ds/search
 * adapter, which runs the MDP search, renders the results server-side, and
 * morphs #orgss-results-<key> over SSE. Selecting a result sets the
 * selectedOrgUuid signal only; relationship creation lands in a later slice.
 *
 * The $args contract matches the Alpine component. Only the keys this slice
 * references are defaulted here; extra caller args are preserved by wp_parse_args.
 *
 * @see docs/org-search-select-datastar-migration-spec.md (slice 1)
 */

// No direct access
defined('ABSPATH') || exit;

$defaults = [
    'classes'                         => [],
    'selected_uuid_hidden_field_name' => 'orgss-selected-uuid',
    'key'                             => rand(1, PHP_INT_MAX),
    'title'                           => '',
    'search_mode'                     => 'org',
    'search_org_type'                 => '',
    'display_org_fields'              => 'name',
    'display_org_type'                => false,
    'org_term_singular'              => '',
];
$args = wp_parse_args($args, $defaults);

$classes          = $args['classes'];
$hiddenFieldName  = $args['selected_uuid_hidden_field_name'];
$key              = $args['key'];
$title            = $args['title'];
$searchOrgType    = $args['search_org_type'];
$displayOrgFields = $args['display_org_fields'];
$displayOrgType   = (bool) $args['display_org_type'];

$ns   = 'orgss_' . $key;
$lang = function_exists('wicket_get_current_language') ? wicket_get_current_language() : 'en';

// Adapter URL with the fixed params and nonce. searchTerm is appended per request.
$search_url = add_query_arg(
    [
        '_wpnonce'       => wp_create_nonce('wp_rest'),
        'orgssKey'       => $key,
        'orgType'        => $searchOrgType,
        'lang'           => $lang,
        'display'        => $displayOrgFields,
        'displayOrgType' => $displayOrgType ? '1' : '0',
    ],
    rest_url('wicket-base/v1/orgss-ds/search')
);

// Reused @get expression (button + Enter key). Real single quotes; esc_attr at
// the attribute sink handles HTML escaping, browser decodes for Datastar.
$search_get = "@get('" . $search_url . "&searchTerm=' + encodeURIComponent($" . $ns . ".searchQuery))";

$signals = [
    $ns => [
        'searchQuery'     => '',
        'selectedOrgUuid' => '',
        'loading'         => false,
    ],
];

$search_placeholder = $args['org_term_singular'] !== ''
    ? sprintf(__('Search by %s name', 'wicket'), strtolower($args['org_term_singular']))
    : __('Search by organization name', 'wicket');
?>
<div class="container component-org-search-select component-org-search-select--datastar <?php echo implode(' ', array_map('esc_attr', $classes)); ?>"
     data-signals="<?php echo esc_attr(wp_json_encode($signals)); ?>">

    <div class="component-org-search-select__variant-banner"
         style="border:1px dashed #999; padding:.5rem .75rem; margin-bottom:.75rem; background:#fafafa; font-size:.85rem;">
        <strong>ORGSS &middot; Datastar experiment</strong> (slice 1: search)<?php if ($title !== '') : ?> &middot; <?php echo esc_html($title); ?><?php endif; ?>
    </div>

    <div class="component-org-search-select__search-form flex flex-col bg-dark-100 bg-opacity-5 rounded-100 p-3">
        <div class="component-org-search-select__search-controls flex sm:items-center gap-2">
            <div class="flex-grow w-full">
                <input type="text"
                       class="component-org-search-select__search-input w-full"
                       data-bind:<?php echo esc_attr($ns); ?>.searchQuery
                       data-indicator="<?php echo esc_attr($ns); ?>.loading"
                       data-on:keydown="evt.key === 'Enter' && (<?php echo esc_attr($search_get); ?>)"
                       placeholder="<?php echo esc_attr($search_placeholder); ?>" />
            </div>
            <div class="sm:flex-shrink-0">
                <button type="button"
                        class="component-org-search-select__search-button"
                        data-indicator="<?php echo esc_attr($ns); ?>.loading"
                        data-on:click="<?php echo esc_attr($search_get); ?>">
                    <?php esc_html_e('Search', 'wicket'); ?>
                </button>
            </div>
        </div>
    </div>

    <div class="component-org-search-select__loading-overlay"
         data-show="$<?php echo esc_attr($ns); ?>.loading"
         style="padding:.5rem 0; font-style:italic; color:#666;">
        <?php esc_html_e('Searching...', 'wicket'); ?>
    </div>

    <div id="orgss-results-<?php echo (int) $key; ?>"
         class="component-org-search-select__results">
        <!-- morphed by the orgss-ds/search SSE adapter -->
    </div>

    <input type="hidden"
           name="<?php echo esc_attr($hiddenFieldName); ?>"
           data-attr:value="$<?php echo esc_attr($ns); ?>.selectedOrgUuid"
           value="" />
</div>
