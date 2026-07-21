<?php
/**
 * Modal trigger component.
 *
 * Opens a get_component('modal') dialog. Pairs the two modes:
 *
 * - 'vanilla'  (default): emits onclick="document.getElementById(id).showModal()".
 * - 'datastar': emits data-on:click="$signal = true".
 *
 * Killing hand-rolled imperative openers is the point of this component: every
 * "how do I get opened" concern lives here, so modal.php stays free of it.
 *
 * The trigger is a thin wrapper over the existing button conventions (mirrors
 * button.php: variant, size, label, icon, atts). It does NOT call
 * get_component('button') to avoid coupling trigger availability to button's
 * full arg surface; it renders the same class contract directly.
 *
 * @param array $args {
 *   Required:
 *     @type string $modal_id    The target dialog id.
 *     @type string $label       Button label.
 *   Mode (must match the target modal's mode):
 *     @type string $mode        'vanilla' (default) | 'datastar'.
 *     @type string $open_signal Required when mode=datastar. Forbidden otherwise.
 *   Optional:
 *     @type string $variant     'primary' | 'secondary' | 'ghost' (default 'secondary').
 *     @type string $size        '' | 'sm' | 'lg' (default '').
 *     @type array  $classes     Extra classes.
 *     @type array  $atts        Extra HTML attributes (rendered like button).
 * }
 */

$defaults = [
    'modal_id'    => '',
    'label'       => '',
    'mode'        => 'vanilla',
    'open_signal' => '',
    'variant'     => 'secondary',
    'size'        => '',
    'classes'     => [],
    'atts'        => [],
];
$args = wp_parse_args($args, $defaults);

$modal_id    = $args['modal_id'];
$label       = $args['label'];
$mode        = $args['mode'];
$open_signal = $args['open_signal'];
$variant     = $args['variant'];
$size        = $args['size'];
$classes     = $args['classes'];
$atts        = $args['atts'];

/////////////////// FAIL-LOUD CONTRACT CHECKS (mirror modal.php) ///////////////////
if ($modal_id === '') {
    throw new InvalidArgumentException('modal-trigger component: "modal_id" is required.');
}
if ($label === '') {
    throw new InvalidArgumentException('modal-trigger component: "label" is required.');
}
if ($mode !== 'vanilla' && $mode !== 'datastar') {
    throw new InvalidArgumentException(sprintf('modal-trigger component: "mode" must be "vanilla" or "datastar", got "%s".', $mode));
}
if ($mode === 'datastar' && $open_signal === '') {
    throw new InvalidArgumentException('modal-trigger component: "open_signal" is required when mode=datastar.');
}
if ($mode === 'vanilla' && $open_signal !== '') {
    throw new InvalidArgumentException('modal-trigger component: "open_signal" must not be set when mode=vanilla.');
}
/////////////////// END FAIL-LOUD CONTRACT CHECKS ///////////////////

/////////////////// OPEN ACTION (the whole point of this component) ///////////////////
if ($mode === 'datastar') {
    $open_action = sprintf('$%s = true', $open_signal);
    $open_attr   = sprintf('data-on:click="%s"', esc_attr($open_action));
} else {
    $open_action = sprintf("document.getElementById('%s').showModal()", esc_js($modal_id));
    $open_attr   = sprintf('onclick="%s"', esc_attr($open_action));
}
/////////////////// END OPEN ACTION ///////////////////

/////////////////// CLASSES (mirror button.php contract) ///////////////////
$classes[] = 'component-button';
$classes[] = 'button';
$classes[] = "button--{$variant}";
if ($size) {
    $classes[] = "button--{$size}";
}
$classes = array_filter(array_map('trim', $classes));
/////////////////// END CLASSES ///////////////////

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

<button
    type="button"
    class="<?php echo esc_attr(implode(' ', $classes)); ?>"
    <?php echo $open_attr; ?>
    <?php echo $extra_atts; ?>
>
    <?php echo esc_html($label); ?>
</button>
