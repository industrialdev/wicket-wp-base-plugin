<?php
$defaults = [
    'classes'    => [],
    'url'        => '',
    'text'       => 'Link',
    'target'     => '_self',
    'reversed'   => false,
    'default_link_style' => false,
    'icon_start' => [
        'classes' => [],
        'icon'    => '', // Font Awesome classes
        'text'    => '', // This will be for screenreaders only
    ],
    'icon_end'   => [
        'classes' => [],
        'icon'    => '', // Font Awesome classes
        'text'    => '', // This will be for screenreaders only
    ],
    'atts'       => [],
    'size'       => 'md', // 'sm', 'md', 'lg', available in theme v2 only
];

$args = wp_parse_args($args, $defaults);
$classes = $args['classes'];
$url = $args['url'];
$text = $args['text'];
$target = $args['target'];
$reversed = $args['reversed'];
$default_link_style = $args['default_link_style'];
$icon_start = (!empty($args['icon_start']['icon']) ? get_component('icon', $args['icon_start'], false) : '');
$icon_end = (!empty($args['icon_end']['icon']) ? get_component('icon', $args['icon_end'], false) : '');
$atts = $args['atts'];
$size = $args['size'];

if (defined('WICKET_WP_THEME_V2')) {
    $classes[] = 'component-link';

    $classes[] = "component-link--{$size}";

    if ($reversed) {
        $classes[] = 'component-link--reversed';
    }

    if ($default_link_style) {
        $classes[] = 'component-link--default';
    }
} else {
    $classes[] = 'component-link';

    if (!$icon_start && !$icon_end) {
        $classes[] = 'underline hover:no-underline focus:shadow-focus';
    }

    if ($icon_start || $icon_end) {
        if ($default_link_style) {
            $classes[] = 'inline-flex items-center gap-2 underline hover:no-underline focus:shadow-focus';
        } else {
            $classes[] = 'inline-flex items-center gap-2 hover:underline focus:shadow-focus';
        }
    }

    if ($reversed) {
        $classes[] = 'link--reversed';
    }
}

?>

<a <?php echo implode(' ', $atts); ?>
	class="<?php echo implode(' ', $classes) ?>"
	href="<?php echo $url ?>"
	target="<?php echo $target ?>"
	<?php echo $target === '_blank' ? 'aria-label="' . esc_attr($text) . ' (opens in new tab)"' : ''; ?>>
	<?php
    echo $icon_start;
echo $text;
echo $icon_end;
?>
</a>
