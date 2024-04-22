<?php
$defaults         = array(
	'classes'          => [],
	'title '           => '',
	'intro'            => '',
	'show_breadcrumbs' => false,
	'show_post_type'   => false,
	'show_share'       => false,
	'show_date'        => false,
	'member_only'      => false,
	'text_alignment'   => 'left',
	'image'            => '',
	'custom_image'     => '',
	'call_to_action'   => '',
	'background_style' => 'light',
	'background_image' => '',
	'back_link'        => '',
	'download_file'    => '',
	'helper_link'      => '',
);
$args             = wp_parse_args( $args, $defaults );
$classes          = $args['classes'];
$title            = $args['title'];
$intro            = $args['intro'];
$show_breadcrumbs = $args['show_breadcrumbs'];
$show_post_type   = $args['show_post_type'];
$show_share       = $args['show_share'];
$show_date        = $args['show_date'];
$member_only      = $args['member_only'];
$text_alignment   = $args['text_alignment'];
$image            = $args['image'];
$custom_image     = $args['custom_image'];
$call_to_action   = $args['call_to_action'];
$background_style = $args['background_style'];
$background_image = $args['background_image'];
$back_link        = $args['back_link'];
$download_file    = $args['download_file'];
$helper_link      = $args['helper_link'];

$text_alignment_class = 'text-' . $text_alignment;
$wrapper_classes      = [ 'component-banner py-8 px-4 mb-16 relative' ]; // border-b border-light-020
$reversed             = ( $background_style === 'reversed' || $background_style === 'image' );
$cta_classes          = [ 'cta-card flex-1 p-6 rounded-050 basis-full w-full lg:basis-3/12' ];

if ( $reversed ) {
	$wrapper_classes[] = 'bg-dark-100 text-white';
	$cta_classes[]     = 'bg-[#393F46]';
} else {
	$cta_classes[] = 'bg-light-010';
}
if ( $background_style === 'reversed' ) {
	$wrapper_classes[] = 'bg-mode-reversed';
} else if ( $background_style === 'image' ) {
	$wrapper_classes[] = 'bg-mode-image';
} else {
	$wrapper_classes[] = 'bg-accent-a-100 bg-mode-light';
}

if ( $image === 'featured-image' && has_post_thumbnail() ) {
	$featured_image = wp_get_attachment_image_src( get_post_thumbnail_id(), 'full' );
	$featured_image = array(
		'id'  => get_post_thumbnail_id(),
		'alt' => get_post_meta( get_post_thumbnail_id(), '_wp_attachment_image_alt', TRUE ),
	);
} elseif ( $image === 'custom-image' && $custom_image ) {
	$featured_image = $custom_image;
} else {
	$featured_image = '';
}

?>

<div class="<?php echo implode( ' ', $wrapper_classes ) ?>">
	<div class="container z-10 relative">
		<div class="flex flex-col lg:flex-row items-start gap-5">
			<div class="flex grow flex-col gap-8 basis-full lg:basis-7/12">

				<?php if ( $show_breadcrumbs ) {
					get_component( 'breadcrumbs' );
				} ?>

				<?php if ( is_single() && $back_link ) {
					get_component( 'link', [ 
						'url'        => $back_link,
						'text'       => 'Back',
						'icon_start' => [ 
							'icon' => 'fa-solid fa-arrow-left',
							'text' => 'Icon text',
						],
					] );
				} ?>

				<?php if ( $title ) : ?>
					<div>
						<?php if ( $show_post_type ) : ?>
							<div class="text-dark-070 uppercase font-bold <?php echo esc_attr( $text_alignment_class ); ?>">
								<?php
								$post_id = get_the_ID();
								echo get_related_content_type_term( $post_id );
								?>
							</div>
						<?php endif; ?>
						<h1 class="text-heading-3xl font-bold <?php echo esc_attr( $text_alignment_class ); ?>">
							<?php echo esc_html( $title ); ?>
						</h1>
					</div>
				<?php endif; ?>

				<?php if ( $text_alignment === 'center' ) : ?>
					<hr class="w-14 mx-auto border-t border-t-4 <?php echo $reversed ? 'border-light-090' : 'border-dark-060' ?>">
				<?php endif; ?>

				<?php if ( $intro ) : ?>
					<div class="text-body-lg <?php echo esc_attr( $text_alignment_class ); ?>">
						<?php echo wp_kses_post( $intro ); ?>
					</div>
				<?php endif; ?>

				<?php if ( $show_date ) : ?>
					<?php
					$date = get_the_date( 'F j, Y' );
					?>
					<div class="text-body-sm text-dark-070 italic mb-3">
						<?php echo $date; ?>
					</div>
				<?php endif; ?>

				<?php if ( is_single() && ( $download_file || $helper_link ) ) : ?>
					<div class="flex items-start gap-4">
						<?php
						if ( ! empty( $download_file ) ) {
							get_component( 'button', [ 
								'variant'     => 'primary',
								'label'       => 'Download',
								'suffix_icon' => 'fa-solid fa-arrow-down-to-bracket',
								'a_tag'       => true,
								'link'        => $download_file['url'],
								'atts'        => [ 'download' ],
							] );
						}

						if ( ! empty( $helper_link ) ) {
							get_component( 'button', [ 
								'variant'     => 'secondary',
								'label'       => $helper_link['title'],
								'suffix_icon' => $helper_link['target'] === '_blank' ? 'fa fa-external-link-alt' : '',
								'a_tag'       => true,
								'link'        => $helper_link['url'],
								'link_target' => $helper_link['target'],
								'reversed'    => $reversed,
							] );
						}

						?>
					</div>
				<?php endif; ?>


				<?php if ( $show_share || $member_only ) : ?>
					<div class="flex flex-col md:flex-row items-start md:items-center gap-3 md:gap-5">

						<?php if ( $show_share ) {
							get_component( 'social-sharing', [ 
								'reversed' => $reversed,
							] );
						} ?>

						<?php if ( $member_only ) {
							get_component( 'tag', [ 
								'label' => __( 'Members Only', 'wicket' ),
								'icon'  => 'fa-regular fa-lock',
								'link'  => '',
							] );
						} ?>

					</div>
				<?php endif; ?>
			</div>

			<?php if ( $featured_image ) : ?>
				<div class="flex-grow-0 flex-shrink-0 basis-full lg:basis-5/12">
					<?php get_component( 'image', [ 
						'id'      => $featured_image['id'],
						'alt'     => $featured_image['alt'],
						'classes' => [ 'lg:max-h-[425px] ml-auto' ],
					] ); ?>
				</div>
			<?php elseif ( ! empty( $call_to_action ) && $call_to_action['title'] ) : ?>
				<div class="<?php echo implode( ' ', $cta_classes ) ?>">
					<div class="text-[24px] font-bold mb-3">
						<?php echo esc_html( $call_to_action['title'] ); ?>
					</div>
					<?php if ( $call_to_action['description'] ) : ?>
						<div class="mb-3">
							<?php echo wp_kses_post( $call_to_action['description'] ); ?>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $call_to_action['links'] ) ) {
						echo '<div class="flex flex-col gap-3">';
						foreach ( $call_to_action['links'] as $cta ) {
							if ( $cta['link'] ) {
								get_component( 'button', [ 
									'variant'     => $cta['variant'],
									'label'       => $cta['link']['title'],
									'suffix_icon' => $cta['link']['target'] === '_blank' ? 'fa fa-external-link-alt' : '',
									'a_tag'       => true,
									'link'        => $cta['link']['url'],
									'link_target' => $cta['link']['target'],
									'classes'     => [ 'w-full', 'justify-center' ],
									'reversed'    => $reversed,
								] );
							}
						}
						echo '</div>';
					} ?>
				</div>
			<?php endif; ?>

		</div>
	</div>

	<?php if ( ! empty( $background_image ) ) {
		get_component( 'image', [ 
			'id'           => $background_image['id'],
			'alt'          => $background_image['alt'],
			'aspect_ratio' => '3/2',
			'classes'      => [ 'absolute inset-0 w-full h-full object-cover object-center z-0 opacity-[.1]' ],
		] );
	} ?>
</div>