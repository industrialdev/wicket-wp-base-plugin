<?php

declare(strict_types=1);

/**
 * Wicket Modal block.
 *
 * Editor-facing wrapper over the shared modal component
 * (includes/components/modal.php + modal-trigger.php). Renders in
 * 'vanilla' mode: native <dialog>, inline onclick open/close, no
 * Datastar dependency on content pages.
 *
 * The block body is an editable InnerBlocks area. ACF replaces the
 * literal <InnerBlocks /> tag in the final rendered output (it buffers
 * the whole render call, then runs one regex pass), so buffering the
 * tag into the component's 'body' arg is safe.
 *
 * Fields (group_wicket_modal): trigger_label, trigger_variant,
 * dialog_title, dialog_width, close_label. Editors get graceful
 * defaults for every empty value; the component's fail-loud contract
 * is never allowed to throw from editor input.
 */

namespace Wicket\Blocks\Wicket_Modal;

/**
 * Inner blocks editors may place inside the modal body.
 */
const ALLOWED_INNER_BLOCKS = [
    'core/paragraph',
    'core/heading',
    'core/image',
    'core/list',
    'core/quote',
    'core/buttons',
    'wicket/banner',
];

/**
 * Register the block's ACF field group. Called once at include time;
 * Wicket_Blocks includes this file during acf/init (priority 15), so
 * ACF is fully loaded and a direct registration call is safe.
 */
function register_fields(): void
{
    if (!function_exists('acf_add_local_field_group')) {
        return;
    }

    acf_add_local_field_group([
        'key'                   => 'group_wicket_modal',
        'title'                 => __('Modal', 'wicket'),
        'location'              => [
            [
                [
                    'param'    => 'block',
                    'operator' => '==',
                    'value'    => 'wicket/modal',
                ],
            ],
        ],
        'menu_order'            => 0,
        'position'              => 'normal',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
        'active'                => true,
        'fields'                => [
            [
                'key'           => 'field_wicket_modal_trigger_label',
                'label'         => __('Button label', 'wicket'),
                'name'          => 'trigger_label',
                'type'          => 'text',
                'required'      => 1,
                'default_value' => __('Learn more', 'wicket'),
                'instructions'  => __('Text shown on the button that opens the modal.', 'wicket'),
            ],
            [
                'key'           => 'field_wicket_modal_trigger_variant',
                'label'         => __('Button style', 'wicket'),
                'name'          => 'trigger_variant',
                'type'          => 'select',
                'required'      => 0,
                'choices'       => [
                    'primary'   => __('Primary', 'wicket'),
                    'secondary' => __('Secondary', 'wicket'),
                    'ghost'     => __('Ghost', 'wicket'),
                ],
                'default_value' => 'secondary',
            ],
            [
                'key'           => 'field_wicket_modal_dialog_title',
                'label'         => __('Modal title', 'wicket'),
                'name'          => 'dialog_title',
                'type'          => 'text',
                'required'      => 1,
                'default_value' => '',
                'instructions'  => __('Heading shown at the top of the modal window.', 'wicket'),
            ],
            [
                'key'           => 'field_wicket_modal_dialog_width',
                'label'         => __('Modal width', 'wicket'),
                'name'          => 'dialog_width',
                'type'          => 'select',
                'required'      => 0,
                'choices'       => [
                    'md' => __('Medium', 'wicket'),
                    'lg' => __('Large', 'wicket'),
                ],
                'default_value' => 'lg',
            ],
            [
                'key'           => 'field_wicket_modal_close_label',
                'label'         => __('Close button label', 'wicket'),
                'name'          => 'close_label',
                'type'          => 'text',
                'required'      => 0,
                'default_value' => __('Close', 'wicket'),
                'instructions'  => __('Hidden text read by screen readers for the × button.', 'wicket'),
            ],
        ],
    ]);
}

/**
 * Render the block. $block is the ACF block context array.
 */
function render(array $block = []): void
{
    if (!function_exists('get_modal_pair')) {
        return;
    }

    $fields = function_exists('get_fields') ? (get_fields() ?: []) : [];

    // Dialog id: block anchor when the editor set one, else a stable
    // per-render unique id so several modals coexist on one page.
    // A repeated or malformed anchor falls back to a suffixed unique
    // id; getElementById must never resolve to the wrong dialog.
    static $used_ids = [];
    $anchor = is_array($block) ? ($block['anchor'] ?? '') : '';
    if ($anchor !== '' && function_exists('sanitize_html_class')) {
        $anchor = sanitize_html_class($anchor);
    }
    if ($anchor !== '' && !in_array($anchor, $used_ids, true)) {
        $id = $anchor;
    } else {
        $id = function_exists('wp_unique_id') ? wp_unique_id($anchor !== '' ? $anchor . '-' : 'wicket-modal-') : uniqid('wicket-modal-');
    }
    $used_ids[] = $id;

    $width = in_array($fields['dialog_width'] ?? '', ['md', 'lg'], true) ? $fields['dialog_width'] : 'lg';
    $title = trim((string) ($fields['dialog_title'] ?? ''));
    $close_label = trim((string) ($fields['close_label'] ?? ''));
    $title = $title !== '' ? $title : __('Modal', 'wicket');
    $close_label = $close_label !== '' ? $close_label : __('Close', 'wicket');

    // The literal <InnerBlocks /> tag must survive into the final page
    // output; ACF replaces it there (see file header). Buffered so the
    // component receives it as the body HTML string.
    ob_start();
    // Raw tag on purpose: ACF parses this marker out of the final output.
    echo '<InnerBlocks allowedBlocks="' . esc_attr((string) wp_json_encode(ALLOWED_INNER_BLOCKS)) . '" template="' . esc_attr((string) wp_json_encode([['core/paragraph']])) . '" />';
    $body = (string) ob_get_clean();

    get_modal_pair([
        'id'          => $id,
        'title'       => $title,
        'body'        => $body,
        'mode'        => 'vanilla',
        'width'       => $width,
        'close_label' => $close_label,
        'classes'     => ['wicket-modal-block'],
        'trigger'     => [
            'label'   => (string) ($fields['trigger_label'] ?? '') ?: __('Learn more', 'wicket'),
            'variant' => in_array($fields['trigger_variant'] ?? '', ['primary', 'secondary', 'ghost'], true) ? $fields['trigger_variant'] : 'secondary',
            'classes' => ['wicket-modal-block__trigger'],
        ],
    ]);
}

register_fields();
