<?php
$defaults = array(
	'classes'  => [],
	'label'    => __( 'Members Only', 'wicket' ),
	'icon'     => '',
	'reversed' => false,
	'link'     => '',
);
$args     = wp_parse_args( $args, $defaults );
$classes  = $args['classes'];
$label    = $args['label'];
$icon     = $args['icon'];
$reversed = $args['reversed'];
$link     = $args['link'];

if ( defined( 'WICKET_WP_THEME_V2' ) ) {
	$classes[] = 'component-tag';
} else {
	$classes[] = 'component-tag p-2 inline-flex gap-2 rounded-050';
	$classes[] = $reversed ? 'bg-dark-080 text-white' : 'bg-light-020 text-dark-080';
	$classes[] = $link ? 'font-normal border-solid border border-light-020 text-sm leading-normal group-hover:underline group-focus:shadow-focus group-focus:underline' : 'font-bold leading-none';
	
	if ( $link ) {
		$classes[] = '!bg-light-010';
	}
}
?>

<?php if ( $link ) : ?>
	<a href="<?php echo esc_url( $link ); ?>" class="group">
	<?php endif; ?>

	<span class="<?php echo implode( ' ', $classes ) ?>">
		<?php if ( $label ) {
			echo esc_html( $label );
		} ?>
		<?php if ( $icon ) {
			get_component( 'icon', [ 
				'icon' => $icon,
			] );
		} ?>
	</span>

	<?php if ( $link ) : ?>
	</a>
<?php endif; ?>