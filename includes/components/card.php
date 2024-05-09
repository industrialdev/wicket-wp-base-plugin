<?php
$defaults  = array(
	'classes'   => [],
	'title'     => '',
	'subtitle'  => '',
	'excerpt'   => '',
	'link'      => [ 
		'url'    => '#',
		'text'   => 'Go somewhere',
		'target' => '_self',
	],
	'cta_style' => 'link',
	'image'     => '',
);
$args      = wp_parse_args( $args, $defaults );
$classes   = $args['classes'];
$title     = $args['title'];
$subtitle  = $args['subtitle'];
$excerpt   = $args['excerpt'];
$link      = $args['link'];
$cta_style = $args['cta_style'];
$image     = $args['image'];

$classes[] = 'component-card p-4 bg-white shadow-4 flex flex-col gap-4 relative @2xl:flex-row @2xl:items-center';
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

		<div class="flex flex-col gap-4">

			<?php if ( $title ) { ?>
				<div class="block text-dark-100 font-bold leading-7 text-heading-xs @2xl:text-heading-md">
					<?php echo $title; ?>
				</div>
			<?php } ?>

			<?php if ( $subtitle ) { ?>
				<div class="text-dark-070 italic">
					<?php echo $subtitle; ?>
				</div>
			<?php } ?>

			<?php if ( $excerpt ) { ?>
				<div class="leading-6">
					<?php echo $excerpt; ?>
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