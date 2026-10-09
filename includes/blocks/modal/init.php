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
 * the whole render call, then runs one regex pass), so passing the
 * tag into the component's 'body' arg is safe.
 *
 * Editor preview: the canvas (iframed since WP 7.1) cannot show a closed
 * <dialog>, so the preview renders the body in flow below the trigger.
 * Only the frontend render emits the real <dialog>. The branch gates on
 * ACF's $is_preview (true inside the editor's admin-ajax preview fetch),
 * not is_admin(): other admin-ajax handlers may render post content and
 * must keep receiving frontend markup.
 *
 * Fields (group_wicket_modal): trigger_label, trigger_variant,
 * dialog_title, dialog_width, close_label, editor_hint. Editors get graceful
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
            [
                'key'     => 'field_wicket_modal_editor_hint',
                'label'   => __('Modal content', 'wicket'),
                'name'    => 'editor_hint',
                'type'    => 'message',
                'message' => sprintf(
                    '%1$s <a href="%2$s" target="_blank" rel="noopener noreferrer">%3$s</a>',
                    __('Everything below the button is the content visitors see inside the modal window. Click that area to edit it. To reach nested blocks, use List View (Document Overview).', 'wicket'),
                    esc_url('https://wordpress.org/documentation/article/list-view/'),
                    __('How List View works', 'wicket')
                ),
            ],
        ],
    ]);
}

/**
 * Render the block. $block is the ACF block context array.
 */
function render(array $block = [], bool $is_preview = false): void
{
    if (!function_exists('get_modal_pair')) {
        return;
    }

    $fields = function_exists('get_fields') ? (get_fields() ?: []) : [];

    $width = in_array($fields['dialog_width'] ?? '', ['md', 'lg'], true) ? $fields['dialog_width'] : 'lg';
    $title = trim((string) ($fields['dialog_title'] ?? ''));
    $close_label = trim((string) ($fields['close_label'] ?? ''));
    $title = $title !== '' ? $title : __('Modal', 'wicket');
    $close_label = $close_label !== '' ? $close_label : __('Close', 'wicket');
    $trigger_label = (string) ($fields['trigger_label'] ?? '') ?: __('Learn more', 'wicket');
    $trigger_variant = in_array($fields['trigger_variant'] ?? '', ['primary', 'secondary', 'ghost'], true) ? $fields['trigger_variant'] : 'secondary';

    // The literal <InnerBlocks /> tag must survive into the final page
    // output; ACF replaces it there (see file header).
    // Raw tag on purpose: ACF parses this marker out of the final output.
    $inner_blocks_tag = '<InnerBlocks allowedBlocks="' . esc_attr((string) wp_json_encode(ALLOWED_INNER_BLOCKS)) . '" template="' . esc_attr((string) wp_json_encode([['core/paragraph']])) . '" />';

    // WP 7.1 renders the editor canvas inside an iframe. A closed <dialog>
    // is display:none, so the InnerBlocks area inside it was invisible in
    // the canvas and reachable only through List View. The editor preview
    // shows the modal body in flow below the trigger, before the dialog-id
    // registry is touched: the preview emits no ids and never consumes an
    // anchor. Canvas markup stays JS-free: the iframed document is not the
    // document editor scripts run in.
    if ($is_preview) {
        // Mirror of the width token map in includes/components/modal.php.
        $width_classes = ['md' => 'max_wt_md', 'lg' => 'max_wt_3xl'];

        echo '<div class="wicket-modal-block wicket-modal-block--editor">';
        echo '<p class="wicket-modal-block__editor-note">' . esc_html__('Modal content: what visitors see inside the dialog after clicking the button.', 'wicket') . '</p>';
        echo '<button type="button" class="component-button button button--' . esc_attr($trigger_variant) . ' wicket-modal-block__trigger" tabindex="-1" aria-hidden="true">' . esc_html($trigger_label) . '</button>';
        echo '<div class="wicket-modal-block__editor-card ' . esc_attr($width_classes[$width]) . '">';
        echo '<span class="modal__close" aria-hidden="true">&times;</span>';
        echo '<h2 class="wp-block-heading has-heading-sm-font-size">' . esc_html($title) . '</h2>';
        echo '<div class="modal__body">' . $inner_blocks_tag . '</div>';
        echo '</div>';
        echo '</div>';

        return;
    }

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

    // Standard block wrapper: every Wicket block renders inside
    // get_block_wrapper_attributes() so the content column and any
    // width/alignment containers constrain it. A bare trigger button
    // escapes the column and lands at the viewport edge.
    echo '<div ' . get_block_wrapper_attributes() . '>';
    get_modal_pair([
        'id'          => $id,
        'title'       => $title,
        'body'        => $inner_blocks_tag,
        'mode'        => 'vanilla',
        'width'       => $width,
        'close_label' => $close_label,
        'classes'     => ['wicket-modal-block'],
        'trigger'     => [
            'label'   => $trigger_label,
            'variant' => $trigger_variant,
            'classes' => ['wicket-modal-block__trigger'],
        ],
    ]);
    echo '</div>';
}

register_fields();
