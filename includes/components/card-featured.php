<?php
$defaults = [
    'classes'        => [],
    'post_id'        => '',
    'post_type'      => '',
    'content_type'   => '',
    'title'          => '',
    'excerpt'        => '',
    'date'           => '',
    'link'           => '',
    'image'          => '',
    'image_position' => 'top',
    'member_only'    => false,
    'cta'            => null,
    'cta_label'      => '',
];
$args = wp_parse_args($args, $defaults);
$classes = $args['classes'];
$post_id = $args['post_id'];
$post_type = $args['post_type'];
$content_type = $args['content_type'];
$title = $args['title'];
$excerpt = $args['excerpt'];
$date = $args['date'];
$link = $args['link'];
$image = $args['image'];
$image_position = $args['image_position']; // top, left, right
$member_only = $args['member_only'];
$cta = $args['cta'];
$cta_label = $args['cta_label'];

if (defined('WICKET_WP_THEME_V2')) {
    $classes[] = 'component-card-featured';
    $title_classes = ['component-card-featured__title'];
} else {
    $classes[] = 'component-card-featured bg-white shadow-4 flex flex-col gap-4 relative';
    $title_classes = ['component-card-featured__title block text-dark-100 font-bold leading-7 text-heading-xs'];
}

$classes[] = 'component-card-featured--' . $post_type;
$classes[] = 'component-card-featured--image-position-' . $image_position;

$image_wrapper_classes = ['component-card-featured__image-wrapper'];

if ($image_position === 'top') {
    $classes[] = '@2xl:flex-col';
    if ($image) {
        $classes[] = '@2xl:items-center';
    }
    $image_wrapper_classes[] = '@2xl:basis-1/2';
    $title_classes[] = '@2xl:text-heading-md';
}

if ($image_position === 'left') {
    $classes[] = '@2xl:flex-row @2xl:items-start justify-between';
    $image_wrapper_classes[] = 'hidden @lg:block basis-1/4 flex-none';
}

if ($image_position === 'right') {
    $classes[] = '@2xl:flex-row-reverse lg:flex-row-reverse @2xl:items-start justify-between';
    $image_wrapper_classes[] = 'hidden @lg:block basis-1/4 flex-none';
}
?>

<div class="@container">
	<div class="<?php echo implode(' ', $classes) ?>">
		<?php if ($member_only && $image_position === 'top') { ?>
			<div class="absolute left-1/2 top-[-16px] -translate-x-1/2 -translate-y-1/2">
				<?php get_component('tag', [
				    'label'   => __('Members Only', 'wicket'),
				    'icon'    => 'fa-regular fa-lock',
				    'link'    => '',
				    'classes' => ['rounded-b-[0px]'],
				]); ?>
			</div>
		<?php } ?>

		<?php if ($image) { ?>
			<div class="<?php echo implode(' ', $image_wrapper_classes) ?>">
				<?php get_component('image', [
				    'id'           => $image['id'],
				    'alt'          => $image['alt'],
				    'aspect_ratio' => '3/2',
				]); ?>
			</div>
		<?php } ?>

		<div class="component-card-featured__content-wrapper <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'flex flex-col grow items-start gap-3' ?>">

			<?php if ($content_type) { ?>
				<div class="component-card-featured__content-type <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'text-dark-070 uppercase font-bold leading-none' ?>">
					<?php echo $content_type; ?>
				</div>
			<?php } ?>

			<?php if ($title) { ?>
				<a href="<?php echo $link ?>" class="<?php echo implode(' ', $title_classes) ?>">
					<?php echo $title; ?>

					<?php if ($member_only && ($image_position === 'left' || $image_position === 'right')) { ?>
						<?php get_component('tag', [
						    'label'   => '',
						    'icon'    => 'fa-regular fa-lock',
						    'link'    => '',
						    'classes' => ['text-body-sm'],
						]); ?>
					<?php } ?>
				</a>
			<?php } ?>

			<?php do_action('wicket_card_featured_after_title', $args); ?>

			<?php if ($excerpt) { ?>
				<div class="component-card-featured__excerpt <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'leading-6' ?>">
					<?php echo $excerpt; ?>
				</div>
			<?php } ?>

			<?php if ($date) { ?>
				<div class="component-card-featured__date <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'text-body-sm text-dark-070 italic' ?>">
					<?php echo $date; ?>
				</div>
			<?php } ?>

			<?php if ($cta && $link) {
			    get_component('button', [
			        'variant' => $cta,
			        'label'   => $cta_label ?: __('Read More', 'wicket'),
			        'a_tag'   => true,
			        'link'    => $link,
			        'classes' => ['component-card-featured__cta'],
			    ]);
			} ?>

		</div>
	</div>
</div>