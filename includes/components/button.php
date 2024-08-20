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
$args               = wp_parse_args( $args, $defaults );
$classes            = $args['classes'];
$variant            = $args['variant'];
$size               = $args['size'];
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
$classes[]          = 'button';
$classes[]          = 'inline-flex';
$classes[]          = 'button--' . $variant;
$atts               = $args['atts'];

if ( $size ) {
	$classes[] = 'button--' . $size;
}

if ( $reversed ) {
	$classes[] = 'button--reversed';
}

if ( $rounded ) {
	$classes[] = 'button--rounded';
}

$tag_type    = 'button';
$href_markup = '';
if ( $a_tag ) {
	$tag_type    = 'a';
	$href_markup = "href='$link' target='$link_target'";
}

if ( $disabled ) {
	$classes[] = 'button--disabled';
	$atts[]    = 'disabled';
}
?>

<<?php echo $tag_type; ?>
	<?php echo implode( ' ', $atts ); ?>
	class="
	<?php echo implode( ' ', $classes ) ?>"
	<?php echo $href_markup; ?>
	<?php if ( ! $a_tag ) {
		echo "type='" . $type . "'";
	} ?>
	>
	<?php
	if ( $prefix ) {
		get_component( 'icon', [ 
			'classes' => [ 'custom-icon-class' ],
			'icon'    => $prefix,
			'text'    => $screen_reader_text,
		] );
	}

	echo $label;

	if ( $suffix ) {
		get_component( 'icon', [ 
			'classes' => [ 'custom-icon-class' ],
			'icon'    => $suffix,
			'text'    => $screen_reader_text,
		] );
	}
	?>
</<?php echo $tag_type; ?>>