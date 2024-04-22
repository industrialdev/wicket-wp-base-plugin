<?php
$defaults    = array(
	'classes'     => [],
	'title '      => '',
	'description' => '',
	'links'       => '',
	'style'       => 'primary',
);
$args        = wp_parse_args( $args, $defaults );
$classes     = $args['classes'];
$title       = $args['title'];
$description = $args['description'];
$links       = $args['links'];
$style       = $args['style'];

$wrapper_classes = [ 'component-card-call-out @container p-5 rounded-100' ];

if ( $style === 'primary' ) {
	$wrapper_classes[] = 'bg-info-a-010';
}

if ( $style === 'secondary' ) {
	$wrapper_classes[] = 'bg-info-b-010';
}
?>

<div class="<?php echo implode( ' ', $wrapper_classes ) ?>">
	<?php if ( $title ) : ?>
		<div class="text-heading-xs font-bold mb-3">
			<?php echo esc_html( $title ); ?>
		</div>
	<?php endif; ?>

	<?php if ( $description ) : ?>
		<div class="mb-3">
			<?php echo wp_kses_post( $description ); ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $links ) ) {
		echo '<div class="flex flex-col @md:flex-row gap-2">';
		foreach ( $links as $link ) {

			if ( ! $link['link'] ) {
				continue;
			}

			$link_type = $link['link_style'];

			get_component( 'button', [ 
				'variant'            => $link_type ?? 'primary',
				'label'              => $link['link']['title'],
				'suffix_icon'        => $link['link']['target'] === '_blank' ? 'fa fa-external-link-alt' : '',
				'a_tag'              => true,
				'link'               => $link['link']['url'],
				'link_target'        => $link['link']['target'],
				'classes'            => [ 'justify-center' ],
				'screen_reader_text' => __( '(opens in new tab)', 'wicket' ),
			] );
		}
		echo '</div>';
	} ?>
</div>