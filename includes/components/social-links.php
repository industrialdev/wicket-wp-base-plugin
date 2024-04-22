<?php
$defaults = array(
	'classes'  => [],
	'reversed' => false,
	'button-variant' => 'primary',
);

$args           = wp_parse_args( $args, $defaults );
$classes        = $args['classes'];
$reversed       = $args['reversed'];
$button_variant = $args['button-variant'];
$classes[]      = 'flex gap-2 list-none p-0 m-0 items-center';
$field          = get_field_object( 'social_media_links', 'option' );
$layouts        = $field['layouts'];

// Loop through the layouts and find layout that has name 'facebook'
foreach ( $layouts as $layout ) {
	if ( $layout['name'] === 'facebook' ) {
		$facebook_layout = $layout;
	}
}

if ( have_rows( 'social_media_links', 'option' ) ) : ?>
	<ul class="<?php echo implode( ' ', $classes ) ?>">
		<?php while ( have_rows( 'social_media_links', 'option' ) ) :
			the_row(); ?>
			<li>
				<?php get_component( 'button', [ 
					'size'               => 'sm',
					'variant'            => $button_variant,
					'label'              => '',
					'prefix_icon'        => 'fab fa-' . get_row_layout() . ' fa-fw',
					'reversed'           => $reversed,
					'rounded'            => true,
					'a_tag'              => true,
					'link'               => get_sub_field( 'link' ),
					'link_target'        => '_blank',
					'screen_reader_text' => 'Follow us on ' . get_social_link_label( $layouts, get_row_layout() ),
				] ) ?>
			</li>
		<?php endwhile; ?>
	</ul>
<?php endif; ?>