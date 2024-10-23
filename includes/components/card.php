<?php
$defaults          = array(
	'classes'           => [],
	'title'             => '',
	'subtitle'          => '',
	'subtitle_location' => 'after-heading',
	'pre_heading'       => '',
	'excerpt'           => '',
	'link'              => [ 
		'url'    => '#',
		'text'   => 'Go somewhere',
		'target' => '_self',
	],
	'cta_style'         => 'link',
	'image'             => '',
);
$args              = wp_parse_args( $args, $defaults );
$classes           = $args['classes'];
$title             = $args['title'];
$subtitle          = $args['subtitle'];
$subtitle_location = $args['subtitle_location'];
$pre_heading       = $args['pre_heading'];
$excerpt           = $args['excerpt'];
$link              = $args['link'];
$cta_style         = $args['cta_style'];
$image             = $args['image'];

$classes[] = 'component-card';

if ( ! defined( 'WICKET_WP_THEME_V2' ) ) {
	$classes[] = 'p-4 bg-white shadow-4 flex flex-col gap-4 relative @2xl:flex-row @2xl:items-center';
}
?>

<div class="@container">
	<div class="<?php echo implode( ' ', $classes ) ?>">
		<?php if ( $image ) { ?>
			<div class="@2xl:basis-1/2">
				<?php get_component( 'image', [ 
					'id'           => $image['id'],
					'alt'          => $image['alt'],
					'aspect_ratio' => '3/2',
				] ); ?>
			</div>
		<?php } ?>

		<div
			<?php if ( defined( 'WICKET_WP_THEME_V2' ) ): ?>
				class="component-card__container <?php echo $image ? 'component-card__container--with-image' : '' ?>"
			<?php else: ?>
				class="component-card__container flex flex-col gap-4 <?php echo $image ? '@2xl:basis-1/2' : '' ?>"
			<?php endif; ?>
		>

			<?php if ( $pre_heading ) { ?>
				<div class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-card__pre-heading' : 'block uppercase text-dark-060 font-semibold leading-7 text-body-md @2xl:text-body-lg -mb-3' ?>">
					<?php echo $pre_heading; ?>
				</div>
			<?php } ?>

			<?php if ( $title ) { ?>
				<div class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-card__title' : 'block text-dark-100 font-bold leading-7 text-heading-xs @2xl:text-heading-md' ?>">
					<?php echo $title; ?>
				</div>
			<?php } ?>

			<?php if ( $subtitle && $subtitle_location == 'after-heading' ) { ?>
				<div class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-card__subtitle' : 'text-dark-070 italic' ?>">
					<?php echo $subtitle; ?>
				</div>
			<?php } ?>

			<?php if ( $excerpt ) { ?>
				<div class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-card__excerpt' : 'leading-6' ?>">
					<?php echo $excerpt; ?>
				</div>
			<?php } ?>

			<?php if ( $subtitle && $subtitle_location == 'after-excerpt' ) { ?>
				<div class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-card__subtitle' : 'text-dark-070 italic' ?>">
					<?php echo $subtitle; ?>
				</div>
			<?php } ?>

			<?php if ( $link ) { ?>
				<div class="mt-auto">
					<?php
					if ( $cta_style === 'link' ) {
						get_component( 'link', [ 
							'url'      => $link['url'],
							'text'     => $link['title'],
							'target'   => $link['target'],
							'icon_end' => [ 
								'icon' => $link['target'] === '_blank' ? 'fa fa-external-link-alt' : 'fa-solid fa-arrow-right',
								'text' => 'Open link',
							],
						] );
					}

					if ( $cta_style === 'button' ) {
						get_component( 'button', [ 
							'link'        => $link['url'],
							'label'       => $link['title'],
							'a_tag'       => true,
							'link_target' => $link['target'],
							'suffix_icon' => $link['target'] === '_blank' ? 'fa fa-external-link-alt' : 'fa-solid fa-arrow-right',
						] );
					}

					?>
				</div>
			<?php } ?>
		</div>
	</div>
</div>