<?php
$defaults    = array(
	'classes'     => [],
	'title '      => '',
	'description' => '',
	'email'       => '',
	'phone'       => '',
	'style'       => 'primary',
);
$args        = wp_parse_args($args, $defaults);
$classes     = $args['classes'];
$title       = $args['title'];
$description = $args['description'];
$email       = $args['email'];
$phone       = $args['phone'];
$style       = $args['style'];

if (defined('WICKET_WP_THEME_V2')) {
	$wrapper_classes = ['component-card-contact'];

	$wrapper_classes[] = "component-card-contact--{$style}";
} else {
	$wrapper_classes = ['component-card-contact p-5 rounded-100'];

	if ($style === 'primary') {
		$wrapper_classes[] = 'bg-info-a-010';
	}

	if ($style === 'secondary') {
		$wrapper_classes[] = 'bg-info-b-010';
	}
}
?>

<div class="<?php echo implode(' ', $wrapper_classes) ?>">
	<?php if ($title) : ?>
		<div class="<?php echo defined('WICKET_WP_THEME_V2') ? 'component-card-contact__title' : 'text-heading-xs font-bold mb-3' ?>">
			<?php echo esc_html($title); ?>
		</div>
	<?php endif; ?>

	<?php if ($description) : ?>
		<div class="<?php echo defined('WICKET_WP_THEME_V2') ? 'component-card-contact__description' : 'mb-3' ?>">
			<?php echo wp_kses_post($description); ?>
		</div>
	<?php endif; ?>

	<?php if ($email || $phone) : ?>
		<div class="<?php echo defined('WICKET_WP_THEME_V2') ? 'component-card-contact__links' : 'flex flex-col items-start gap-2' ?> ">
			<?php if ($email) : ?>
				<?php get_component('link', [
					'url'        => 'mailto:' . esc_attr($email),
					'text'       => esc_html($email),
					'icon_start' => [
						'icon' => 'fa-regular fa-envelope',
						'text' => 'Icon text',
					]
				]); ?>
			<?php endif; ?>

			<?php if ($phone) : ?>
				<?php get_component('link', [
					'url'        => 'tel:' . esc_attr($phone),
					'text'       => esc_html($phone),
					'icon_start' => [
						'icon' => 'fa-regular fa-phone',
						'text' => 'Icon text',
					]
				]); ?>
			<?php endif; ?>

		</div>
	<?php endif; ?>
</div>