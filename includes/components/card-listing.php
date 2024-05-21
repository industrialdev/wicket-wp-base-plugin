<?php
$defaults       = array(
	'classes'        => [],
	'content_type'   => '',
	'title'          => '',
	'excerpt'        => '',
	'date'           => '',
	'topics'         => [],
	'link'           => [ 
		'url'    => '#',
		'text'   => 'Go somewhere',
		'target' => '_self',
	],
	'link_type'      => 'title',
	'member_only'    => false,
	'featured_image' => '',
);
$args           = wp_parse_args( $args, $defaults );
$classes        = $args['classes'];
$content_type   = $args['content_type'];
$title          = $args['title'];
$excerpt        = $args['excerpt'];
$date           = $args['date'];
$topics         = $args['topics'];
$link           = $args['link'];
$link_type      = $args['link_type'];
$member_only    = $args['member_only'];
$featured_image = $args['featured_image'];
$image          = '';

$classes[] = 'component-card-listing p-4 bg-white shadow-4 flex flex-col md:flex-row gap-4 relative';

if ( $featured_image ) {
	$image = wp_get_attachment_image( $featured_image, 'large' );
}
?>

<div class="<?php echo implode( ' ', $classes ) ?>">
	<div class="flex-auto">
		<?php if ( $member_only ) { ?>
			<div class="absolute left-1/2 top-0 -translate-x-1/2 -translate-y-1/2">
				<?php get_component( 'tag', [ 
					'label' => __( 'Members Only', 'wicket' ),
					'icon'  => 'fa fa-lock',
					'link'  => '',
				] ); ?>
			</div>
		<?php } ?>


		<?php if ( $content_type ) { ?>
			<div class="text-dark-070 uppercase font-bold mb-3">
				<?php echo $content_type; ?>
			</div>
		<?php } ?>

		<?php if ( $title && $link_type == 'title' ) { ?>
			<a href="<?php echo $link['url'] ?>" <?php $link['target'] === '_blank' ? 'target="_blank"' : '' ?>
				class="block text-dark-100 font-bold leading-7 mb-3 hover:underline text-[18px] lg:text-body-lg">
				<?php echo $title; ?>
			</a>
		<?php } else if ( $title ) { ?>
			<div class="block text-dark-100 font-bold leading-7 mb-3 text-[18px] lg:text-body-lg">
				<?php echo $title; ?></div>
		<?php } ?>

		<?php if ( $excerpt ) { ?>
			<div class="leading-6 mb-3">
				<?php echo $excerpt; ?>
			</div>
		<?php } ?>

		<?php if ( $date ) { ?>
			<div class="text-body-sm text-dark-070 italic mb-3">
				<?php echo $date; ?>
			</div>
		<?php } ?>

		<?php if ( $topics ) { ?>
			<div class="card__topics">
				<?php foreach ( $topics as $topic ) { ?>
					<?php
					get_component( 'tag', [ 
						'label' => $topic->name,
						'link'  => get_term_link( $topic->term_id ),
					] ); ?>
				<?php } ?>
			</div>
		<?php } ?>

		<?php if ( $link['url'] != '#' && $link_type == 'button' ) { 
			get_component( 'button', [ 
				'link'        => $link['url'],
				'label'       => $link['text'],
				'a_tag'       => true,
				'link_target' => $link['target'],
				'suffix_icon' => $link['target'] === '_blank' ? 'fa fa-external-link-alt' : 'fa-solid fa-arrow-right',
				'variant'     => 'secondary',
			] );	
		} ?>
	</div>

	<?php if ( $image ) { ?>
		<div class="flex-none md:basis-[200px] lg:basis-[300px]">
			<?php echo $image; ?>
		</div>
	<?php } ?>
</div>