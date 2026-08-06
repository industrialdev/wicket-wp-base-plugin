<?php
/**
 * Experimental Datastar variant of the org-search-select component.
 *
 * Loaded by get_component() when the 'wicket_orgss_variant' filter returns
 * 'datastar'. Default stays 'alpine', so this file is not reached in production
 * unless a child theme opts in.
 *
 * Slice 0 status: transport proof only. This shell loads the Datastar client,
 * binds a couple of signals, and round-trips one SSE patch through the
 * orgss-ds/ping endpoint. The real orgss UI is built in later slices.
 *
 * The $args contract matches the Alpine component. Only the keys this shell
 * references are defaulted here; extra caller args are preserved by wp_parse_args.
 *
 * @see docs/org-search-select-datastar-migration-spec.md (slice 0)
 */

// No direct access
defined('ABSPATH') || exit;

$defaults = [
    'classes'                       => [],
    'selected_uuid_hidden_field_name' => 'orgss-selected-uuid',
    'key'                           => rand(1, PHP_INT_MAX),
    'title'                         => '',
];
$args = wp_parse_args($args, $defaults);

$classes                  = $args['classes'];
$selectedUuidHiddenFieldName = $args['selected_uuid_hidden_field_name'];
$key                      = $args['key'];
$title                    = $args['title'];

// Ping endpoint with WP REST nonce on the query string (GET auth).
$ping_url = add_query_arg(
    '_wpnonce',
    wp_create_nonce('wp_rest'),
    rest_url('wicket-base/v1/orgss-ds/ping')
);

$signals = [
    'orgssPingAt'          => '',
    'orgssSearchQuery'     => '',
    'orgssSelectedOrgUuid' => '',
];
?>
<div class="container component-org-search-select component-org-search-select--datastar <?php echo implode(' ', array_map('esc_attr', $classes)); ?>"
     data-signals="<?php echo esc_attr(wp_json_encode($signals)); ?>">

    <div class="component-org-search-select__variant-banner"
         style="border:1px dashed #999; padding:.75rem 1rem; margin-bottom:1rem; background:#fafafa;">
        <strong>ORGSS &middot; Datastar experiment</strong> (key <?php echo (int) $key; ?>)
        &middot; slice 0: transport proof.<?php if ($title !== '') : ?> &middot; <?php echo esc_html($title); ?><?php endif; ?>
    </div>

    <div class="component-org-search-select__controls" style="display:flex; gap:.5rem; align-items:center; margin-bottom:1rem;">
        <button type="button"
                data-on:click="@get('<?php echo esc_js($ping_url); ?>')">
            <?php esc_html_e('Ping endpoint', 'wicket'); ?>
        </button>

        <label>
            <span class="screen-reader-text"><?php esc_html_e('Search (no endpoint yet)', 'wicket'); ?></span>
            <input type="text"
                   data-bind="orgssSearchQuery"
                   placeholder="<?php esc_attr_e('Type to test two-way binding', 'wicket'); ?>" />
        </label>

        <span data-text="$orgssSearchQuery ? 'echo: ' + $orgssSearchQuery : ''"></span>
    </div>

    <div id="orgss-ds-ping-target"
         data-text="$orgssPingAt ? 'last ping: ' + $orgssPingAt : '<?php echo esc_js(__('(no ping yet)', 'wicket')); ?>'">
        <?php esc_html_e('(no ping yet)', 'wicket'); ?>
    </div>

    <input type="hidden"
           name="<?php echo esc_attr($selectedUuidHiddenFieldName); ?>"
           data-attr:value="$orgssSelectedOrgUuid"
           value="" />
</div>
