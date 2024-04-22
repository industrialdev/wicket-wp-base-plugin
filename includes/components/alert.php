<?php
$defaults = array(
	'classes' => [],
	'variant' => 'default',
	'content' => '',
);
$args     = wp_parse_args( $args, $defaults );
$classes  = $args['classes'];
$variant  = $args['variant'];
$content  = $args['content'];

$classes[] = 'p-4 border-l-4';

if ( $variant === 'default' ) {
	$classes[] = 'bg-info-b-010 border-info-b-100';
}

$classes = implode( ' ', $classes );
?>

<div class="<?php echo esc_attr( $classes ); ?>" role="alert">
	<?php echo $content; ?>
</div>