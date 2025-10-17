<?php
$defaults           = array(
    'classes'            => [],
    'variant'            => 'primary',
    'size'               => '',
    'label'              => 'Button',
    'prefix_icon'        => '',
    'suffix_icon'        => '',
    'reversed'           => false,
    'rounded'            => false,
    'a_tag'              => false,
    'link'               => '',
    'link_target'        => '_self',
    'type'               => 'button',
    'disabled'           => false,
    'screen_reader_text' => '',
    'atts'               => [],
);
$args               = wp_parse_args($args, $defaults);
$classes            = $args['classes'];
$variant            = $args['variant']; // primary, secondary, ghost
$size               = $args['size']; // sm, lg
$label              = $args['label'];
$prefix             = $args['prefix_icon'];
$suffix             = $args['suffix_icon'];
$reversed           = $args['reversed'];
$rounded            = $args['rounded'];
$a_tag              = $args['a_tag'];
$link               = $args['link'];
$link_target        = $args['link_target'];
$type               = $args['type'];
$disabled           = $args['disabled'];
$screen_reader_text = $args['screen_reader_text'];
$classes[]          = 'component-button';
$classes[]          = 'inline-flex';
$classes[]          = 'items-center';
$atts               = $args['atts'];


$is_wicket_theme    = defined('WICKET_THEME');

/////////////////// VARIANTS ///////////////////
$classes[] = 'button';
$classes[] = "button--{$variant}";

if ($reversed) {
    $classes[] = "button--reversed";
}
/////////////////// END VARIANTS ///////////////////

/////////////////// SIZE ///////////////////
if ($size) {
    $classes[] = "button--{$size}";
}
/////////////////// END SIZE ///////////////////

// Rounded option
if (! defined('WICKET_WP_THEME_V2')) {
    if ($rounded) {
        $classes[] = 'button--rounded';
    }
}

$tag_type    = 'button';
$href_markup = '';
if ($a_tag) {
    $tag_type    = 'a';
    $href_markup = "href='$link' target='$link_target'" . ($link_target === '_blank' ? " aria-label='". esc_attr($label) ." (opens in new tab)'" : '');
}

/////////////////// DISABLED MODE ///////////////////
if ($disabled) {
    $classes[] = 'button--disabled';
    $classes[] = 'pointer-events-none';
    $atts[]    = 'disabled';
}
/////////////////// END DISABLED MODE ///////////////////
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
?>

<<?php echo $tag_type; ?>
    <?php echo $href_markup; ?>
    <?php echo implode(' ', $formatted_atts); ?>
    class="
    <?php echo implode(' ', $classes) ?>"

    <?php if (! $a_tag) {
        echo "type='" . $type . "'";
    } ?>
    >
    <?php
    if ($prefix) {
        $icon_classes = [ 'custom-icon-class' ];

        if (defined('WICKET_WP_THEME_V2') && ! empty($label)) {
            $icon_classes[] = 'me-2';
        }

        get_component('icon', [
            'classes' => $icon_classes,
            'icon'    => $prefix,
            'text'    => $screen_reader_text,
        ]);
    }

echo $label;

if ($suffix) {
    $icon_classes = [ 'custom-icon-class' ];

    if (defined('WICKET_WP_THEME_V2') && ! empty($label)) {
        $icon_classes[] = 'ms-2';
    }

    get_component('icon', [
        'classes' => $icon_classes,
        'icon'    => $suffix,
        'text'    => $screen_reader_text,
    ]);
}
?>
</<?php echo $tag_type; ?>>
