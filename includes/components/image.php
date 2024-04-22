<?php
$defaults     = array(
	'classes'      => [],
	'id'           => '',
	'alt'          => '',
	'size'         => 'full',
	'loading'      => 'lazy',
	'aspect_ratio' => '',
);
$args         = wp_parse_args( $args, $defaults );
$classes      = $args['classes'];
$id           = $args['id'];
$alt          = $args['alt'];
$size         = $args['size'];
$sizes        = wp_calculate_image_sizes( $size, null, null, $id );
$loading      = $args['loading'];
$aspect_ratio = $args['aspect_ratio'];
$src          = wp_get_attachment_image_src( $id, $size );
$srcset       = wp_get_attachment_image_srcset( $id, $size );

$classes[] = 'component-image';

if ( $aspect_ratio ) {
	$classes[] = 'aspect-[' . $aspect_ratio . '] object-cover';
}
?>

<img loading="<?= $loading ?>" class="<?php echo implode( ' ', $classes ) ?>" alt="<?php echo $alt ?>"
	src="<?php echo $src[0] ?>" <?php
		 if ( $srcset ) : ?> srcset="<?php echo $srcset ?>" <?php
		 endif;
		 if ( $sizes && is_string( $sizes ) ) : ?> sizes="<?php echo $sizes ?>" <?php
		 endif; ?> />