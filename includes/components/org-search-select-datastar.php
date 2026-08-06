<?php
/**
 * Experimental Datastar variant of the org-search-select component.
 *
 * Loaded by get_component() when the 'wicket_orgss_variant' filter returns
 * 'datastar'. Default stays 'alpine', so this file is not reached in production
 * unless a child theme opts in.
 *
 * Slices 1-3: live org search + create relationship on select + create-new-org,
 * all over HyperPress. HyperPress owns the Datastar client enqueue and
 * auto-attaches the WP nonce to Datastar fetches, so this template does not
 * enqueue scripts or handle nonces.
 *
 * The $args contract matches the Alpine component. Only the keys these slices
 * reference are defaulted here; extra caller args are preserved by wp_parse_args.
 *
 * @see docs/org-search-select-datastar-migration-spec.md (slices 1-3)
 */

// No direct access
defined('ABSPATH') || exit;

$defaults = [
    'classes'                             => [],
    'selected_uuid_hidden_field_name'     => 'orgss-selected-uuid',
    'key'                                 => rand(1, PHP_INT_MAX),
    'title'                               => '',
    'search_mode'                         => 'org',
    'search_org_type'                     => '',
    'display_org_fields'                  => 'name',
    'display_org_type'                    => false,
    'org_term_singular'                   => '',
    'relationship_mode'                   => 'person_to_organization',
    'relationship_type_upon_org_creation' => 'employee',
    'form_id'                             => 0,
    'disable_create_org_ui'               => false,
    'new_org_type_override'               => '',
];
$args = wp_parse_args($args, $defaults);

$classes          = $args['classes'];
$hiddenFieldName  = $args['selected_uuid_hidden_field_name'];
$key              = $args['key'];
$title            = $args['title'];
$searchOrgType    = $args['search_org_type'];
$displayOrgFields = $args['display_org_fields'];
$displayOrgType   = (bool) $args['display_org_type'];
$relationshipMode = $args['relationship_mode'];
$connectionRole   = $args['relationship_type_upon_org_creation'];
$formId           = (int) $args['form_id'];
$disableCreateUi  = (bool) $args['disable_create_org_ui'];

$ns   = 'orgss_' . $key;
$lang = function_exists('wicket_get_current_language') ? wicket_get_current_language() : 'en';

// Endpoint URLs. Fixed params baked in; dynamic values appended per request.
$search_url = add_query_arg(
    [
        'orgss_key'        => $key,
        'org_type'         => $searchOrgType,
        'lang'             => $lang,
        'display'          => $displayOrgFields,
        'display_org_type' => $displayOrgType ? '1' : '0',
    ],
    hp_get_endpoint_url('wicket:orgss-search')
);
$create_url = add_query_arg(['orgss_key' => $key], hp_get_endpoint_url('wicket:orgss-create-org'));

$search_get = "@get('" . $search_url . "&search_term=' + encodeURIComponent($" . $ns . ".searchQuery))";
$create_post = "@post('" . $create_url . "')";

// Available org types for the create form, restricted by override.
$available_org_types = function_exists('wicket_get_resource_types')
    ? wicket_get_resource_types('organizations')
    : ['data' => []];
if (!empty($args['new_org_type_override'])) {
    $allowed = array_map('trim', explode(',', (string) $args['new_org_type_override']));
    $available_org_types['data'] = array_values(array_filter(
        $available_org_types['data'] ?? [],
        static fn ($t) => in_array($t['attributes']['slug'] ?? '', $allowed, true)
    ));
}

$signals = [
    $ns => [
        'searchQuery'        => '',
        'selectedOrgUuid'    => '',
        'loading'            => false,
        'newOrgName'         => '',
        'newOrgType'         => '',
        'justCreatedOrgUuid' => '',
        // Config travels with every @get/@post so endpoint templates can read it.
        'connectionType'     => $relationshipMode,
        'connectionRole'     => $connectionRole,
        'formId'             => $formId,
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
        <strong>ORGSS &middot; Datastar experiment</strong> (search + select + create, via HyperPress)<?php if ($title !== '') : ?> &middot; <?php echo esc_html($title); ?><?php endif; ?>
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
        <?php esc_html_e('Working...', 'wicket'); ?>
    </div>

    <div id="orgss-results-<?php echo (int) $key; ?>"
         class="component-org-search-select__results">
        <!-- morphed by the wicket:orgss-search / wicket:orgss-select HyperPress SSE templates -->
    </div>

    <?php if (!$disableCreateUi) : ?>
        <div class="component-org-search-select__create-org-form mt-4"
             data-show="!$<?php echo esc_attr($ns); ?>.selectedOrgUuid">
            <div class="font-bold mb-2"><?php esc_html_e("Can't find your organization? Create a new one:", 'wicket'); ?></div>
            <div class="flex gap-2 items-end">
                <div class="flex flex-col w-1/2">
                    <label class="text-sm"><?php esc_html_e('Name', 'wicket'); ?></label>
                    <input type="text"
                           class="component-org-search-select__create-org-name w-full"
                           data-bind:<?php echo esc_attr($ns); ?>.newOrgName />
                </div>
                <div class="flex flex-col w-1/3">
                    <label class="text-sm"><?php esc_html_e('Type', 'wicket'); ?></label>
                    <select class="component-org-search-select__create-org-type"
                            data-bind:<?php echo esc_attr($ns); ?>.newOrgType>
                        <option value=""><?php esc_html_e('Select one', 'wicket'); ?></option>
                        <?php foreach (($available_org_types['data'] ?? []) as $org_type_option) :
                            $slug = $org_type_option['attributes']['slug'] ?? '';
                            $label = $org_type_option['attributes']['name_' . $lang]
                                ?? ($org_type_option['attributes']['name'] ?? $slug);
                            if ($slug === '') {
                                continue;
                            }
                        ?>
                            <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex flex-col">
                    <button type="button"
                            class="component-org-search-select__create-org-button"
                            data-indicator="<?php echo esc_attr($ns); ?>.loading"
                            data-on:click="<?php echo esc_attr($create_post); ?>">
                        <?php esc_html_e('Add', 'wicket'); ?>
                    </button>
                </div>
            </div>
            <div id="orgss-duplicate-<?php echo (int) $key; ?>"
                 class="component-org-search-select__duplicate-warning-region"></div>
        </div>
    <?php endif; ?>

    <input type="hidden"
           name="<?php echo esc_attr($hiddenFieldName); ?>"
           data-attr:value="$<?php echo esc_attr($ns); ?>.selectedOrgUuid"
           value="" />
</div>
