<?php
$defaults = array(
	'classes'    => [],
	'url'        => '',
	'text'       => 'Link',
	'target'     => '_self',
	'reversed'   => false,
	'icon_start' => [
		'classes' => [],
		'icon'    => '', // Font Awesome classes
		'text'    => '' // This will be for screenreaders only
	],
	'icon_end'   => [ 
		'classes' => [],
		'icon'    => '', // Font Awesome classes
		'text'    => '' // This will be for screenreaders only
	],
	'atts'       => [],
);

$args       = wp_parse_args( $args, $defaults );
$classes    = $args['classes'];
$url        = $args['url'];
$text       = $args['text'];
$target     = $args['target'];
$reversed     = $args['reversed'];
$icon_start = ( $args['icon_start']['icon'] ? get_component( 'icon', $args['icon_start'], false ) : '' );
$icon_end   = ( $args['icon_end']['icon'] ? get_component( 'icon', $args['icon_end'], false ) : '' );
$atts       = $args['atts'];
$classes[]  = 'component-link';

if ( ! $icon_start && ! $icon_end ) {
	$classes[] = 'underline hover:no-underline focus:shadow-focus';
}

if ( $icon_start || $icon_end ) {
	$classes[] = 'inline-flex items-center gap-2 font-bold hover:underline focus:shadow-focus';
}

if ( $reversed ) {
	$classes[] = 'link--reversed';
}
?>

<a <?php echo implode( ' ', $atts ); ?> class="<?php echo implode( ' ', $classes ) ?>" href="<?php echo $url ?>"
	target="<?php echo $target ?>">
	<?php
	echo $icon_start;
	echo $text;
	echo $icon_end;
	?>
</a>