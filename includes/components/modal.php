<?php
/**
 * Modal component.
 *
 * Dual-mode `<dialog>` shell that standardizes modal creation across the stack.
 * Mode is explicit and fail-loud:
 *
 * - 'vanilla'  (default): plain native <dialog>. Caller opens via JS
 *              document.getElementById(id).showModal() / .close(), or pairs with
 *              get_component('modal-trigger').
 * - 'datastar': reactive. Caller declares the open signal in its own page-level
 *              data-signals block and passes its name via 'open_signal'. The
 *              component wires data-show / data-effect / data-on:close.
 *
 * The component owns the is_hypermedia_request guard: during a Datastar SSE
 * fragment it renders nothing, preventing duplicate <dialog> nodes / ids on
 * morph re-render. This fixes a latent bug that today is only inconsistently
 * guarded at call sites.
 *
 * Body and footer are passed as buffered HTML strings (ob_start / ob_get_clean),
 * matching the buffering idiom get_component() already uses ($output=false).
 *
 * @param array $args {
 *   Required:
 *     @type string   $id           Unique dialog id.
 *     @type string   $title        Dialog heading text (escaped here).
 *     @type string   $body         Buffered HTML markup for the body.
 *   Mode:
 *     @type string   $mode         'vanilla' (default) | 'datastar'.
 *     @type string   $open_signal  Required when mode=datastar. Forbidden when
 *                                  mode=vanilla. Caller must declare this signal.
 *     @type string   $reset_actions Datastar expression(s) injected into
 *                                  data-on:close after "$signal = false".
 *                                  Developer-authored expressions only; the
 *                                  component escapes the value for an attribute.
 *   Optional:
 *     @type string   $width        'md' | 'lg' (default 'lg'). No 'sm' shipped.
 *     @type string   $close_label  Accessible label for the close button.
 *                                  REQUIRED, no silent empty default.
 *     @type string   $footer       Buffered HTML markup for a footer slot.
 *     @type array    $classes      Extra dialog classes (appended).
 *     @type array    $atts         Extra HTML attributes (rendered like button).
 * }
 */

// Guard: never render a <dialog> shell during a Datastar SSE fragment.
// Datastar sends X-Datastar-Request: true on every backend fetch. Rendering the
// shell into a morph fragment would duplicate the dialog node and its id.
if (isset($_SERVER['HTTP_X_DATASTAR_REQUEST'])
    && filter_var(wp_unslash($_SERVER['HTTP_X_DATASTAR_REQUEST']), FILTER_VALIDATE_BOOLEAN)
) {
    return;
}

$defaults = [
    'id'             => '',
    'title'          => '',
    'body'           => '',
    'mode'           => 'vanilla',
    'open_signal'    => '',
    'reset_actions'  => '',
    'width'          => 'lg',
    'close_label'    => '',
    'footer'         => '',
    'classes'        => [],
    'atts'           => [],
];
$args = wp_parse_args($args, $defaults);

$id            = $args['id'];
$title         = $args['title'];
$body          = $args['body'];
$mode          = $args['mode'];
$open_signal   = $args['open_signal'];
$reset_actions = $args['reset_actions'];
$width         = $args['width'];
$close_label   = $args['close_label'];
$footer        = $args['footer'];
$classes       = $args['classes'];
$atts          = $args['atts'];

/////////////////// FAIL-LOUD CONTRACT CHECKS ///////////////////
if ($id === '') {
    throw new InvalidArgumentException('modal component: "id" is required.');
}
if ($title === '') {
    throw new InvalidArgumentException('modal component: "title" is required.');
}
if ($close_label === '') {
    throw new InvalidArgumentException('modal component: "close_label" is required for accessibility.');
}
if ($mode !== 'vanilla' && $mode !== 'datastar') {
    throw new InvalidArgumentException(sprintf('modal component: "mode" must be "vanilla" or "datastar", got "%s".', $mode));
}
if ($mode === 'datastar' && $open_signal === '') {
    throw new InvalidArgumentException('modal component: "open_signal" is required when mode=datastar.');
}
if ($mode === 'vanilla' && $open_signal !== '') {
    throw new InvalidArgumentException('modal component: "open_signal" must not be set when mode=vanilla.');
}
/////////////////// END FAIL-LOUD CONTRACT CHECKS ///////////////////

/////////////////// WIDTH TOKENS (YAGNI: md|lg only) ///////////////////
$width_map = [
    'md' => 'max_wt_md',
    'lg' => 'max_wt_3xl',
];
$width_class = $width_map[$width] ?? $width_map['lg'];
/////////////////// END WIDTH TOKENS ///////////////////

/////////////////// CLASSES ///////////////////
// Base utility classes applied to every modal dialog, matching the current
// Pattern-B shape (members-list.php et al). No stylesheet depends on these
// tokens today; they are structural/visual utilities only.
$classes[] = 'modal';
$classes[] = 'wt_m-auto';
$classes[] = $width_class;
$classes[] = 'wt_rounded-md';
$classes[] = 'wt_shadow-md';
$classes[] = 'backdrop_wt_bg-black-50';
$classes   = array_filter(array_map('trim', $classes));
/////////////////// END CLASSES ///////////////////

/////////////////// DATASTAR WIRING ///////////////////
$datastar_atts = '';
$on_close_expr = '';
if ($mode === 'datastar') {
    $datastar_atts = sprintf(
        ' data-show="$%1$s" data-effect="if ($%1$s) el.showModal(); else el.close();"',
        $open_signal
    );
    // Reset expression: signal flips false first, then caller's reset_actions.
    // reset_actions is escaped for attribute context here, not at the call site.
    $on_close = '$' . $open_signal . ' = false';
    if ($reset_actions !== '') {
        $on_close .= '; ' . $reset_actions;
    }
    $on_close_expr = sprintf(' data-on:close="%s"', esc_attr($on_close));
}
/////////////////// END DATASTAR WIRING ///////////////////

/////////////////// CLOSE BUTTON ///////////////////
// Neutral class name (orgman-modal__close was a leaked org-roster abstraction;
// no CSS depends on it, renamed here to the shared, stack-neutral form).
$close_button_class = 'modal__close wt_absolute wt_right-4 wt_top-4 wt_text-lg wt_font-semibold';
$close_action = $mode === 'datastar'
    ? '$' . $open_signal . ' = false'
    : sprintf("document.getElementById('%s').close()", esc_js($id));
/////////////////// END CLOSE BUTTON ///////////////////

/////////////////// EXTRA ATTS (mirror button.php rendering) ///////////////////
$formatted_atts = [];
foreach ($atts as $key => $value) {
    if (is_int($key)) {
        $formatted_atts[] = $value;
    } else {
        if ($value === true) {
            $formatted_atts[] = $key;
        } elseif ($value !== false && $value !== null) {
            $formatted_atts[] = $key . '="' . esc_attr($value) . '"';
        }
    }
}
$extra_atts = implode(' ', $formatted_atts);
/////////////////// END EXTRA ATTS ///////////////////
?>

<dialog
    id="<?php echo esc_attr($id); ?>"
    class="<?php echo esc_attr(implode(' ', $classes)); ?>"
    <?php echo $datastar_atts; ?>
    <?php echo $on_close_expr; ?>
    <?php echo $extra_atts; ?>
>
    <div class="wt_bg-white wt_p-6 wt_relative">
        <button
            type="button"
            class="<?php echo esc_attr($close_button_class); ?>"
            <?php echo $mode === 'datastar' ? sprintf('data-on:click="%s"', esc_attr($close_action)) : sprintf('onclick="%s"', esc_attr($close_action)); ?>
            aria-label="<?php echo esc_attr($close_label); ?>"
        >&times;</button>

        <h2 class="wp-block-heading has-heading-sm-font-size wt_text-2xl wt_font-semibold wt_mb-4">
            <?php echo esc_html($title); ?>
        </h2>

        <div class="modal__body">
            <?php
            // Body is a pre-built HTML string owned by the caller. It may
            // contain trusted markup (forms, inputs, data-* attributes).
            // Callers are responsible for escaping any dynamic data within.
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller-owned HTML
            echo $body;
            ?>
        </div>

        <?php if ($footer !== ''): ?>
            <div class="modal__footer wt_flex wt_justify-end wt_gap-3 wt_pt-4">
                <?php
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller-owned HTML
                echo $footer;
                ?>
            </div>
        <?php endif; ?>
    </div>
</dialog>
