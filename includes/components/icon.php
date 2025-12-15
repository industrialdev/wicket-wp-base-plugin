<?php
$defaults = [
    'classes' => [],
    'icon'    => 'fa fa-default-icon', // Replace with your Font Awesome icon classes
    'text'    => '',
    'atts'    => [],
];
$args = wp_parse_args($args, $defaults);
$classes = $args['classes'];
$icon = $args['icon'];
$text = $args['text'];
$classes[] = 'component-icon icon';
$atts = $args['atts'];
?>

<span 
	class="<?php echo implode(' ', $classes) ?>"
	<?php echo implode(' ', $atts); ?>
>
	<i class="<?php echo $icon; ?>" aria-hidden="true"></i>
	<span class="sr-only">
		<?php echo $text; ?>
	</span>
</span>