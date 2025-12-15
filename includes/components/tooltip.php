<?php
$defaults = [
    'classes'  => [],
    'content'  => '',
    'position' => 'right',
];
$args = wp_parse_args($args, $defaults);
$classes = $args['classes'];
$content = $args['content'];
$position = $args['position'];
$caret_classes = ['absolute'];

if (defined('WICKET_WP_THEME_V2')) {
    $classes[] = 'component-tooltip';
    $content_classes = ['component-tooltip-content'];
} else {
    $classes[] = 'component-tooltip';
    $content_classes = ['bg-dark-100', 'text-body-sm', 'text-white', 'p-2', 'pl-4', 'rounded-100', 'absolute', 'z-10', 'hidden', 'min-w-40', 'text-left'];
}

if ($position === 'right') {
    $content_classes[] = 'left-full top-1/2 transform -translate-y-1/2 translate-x-3';
    $caret_classes[] = 'left-0 top-1/2 transform -translate-y-1/2 -translate-x-2';
}

if ($position === 'left') {
    $content_classes[] = 'right-full top-1/2 transform -translate-y-1/2 -translate-x-3';
    $caret_classes[] = 'right-0 top-1/2 transform -translate-y-1/2 translate-x-2';
}

if ($position === 'top') {
    $content_classes[] = 'bottom-full left-1/2 transform -translate-x-1/2 -translate-y-3';
    $caret_classes[] = 'bottom-0 left-1/2 transform -translate-x-1/2 translate-y-2';
}

if ($position === 'bottom') {
    $content_classes[] = 'top-full left-1/2 transform -translate-x-1/2 translate-y-3';
    $caret_classes[] = 'top-0 left-1/2 transform -translate-x-1/2 -translate-y-2';
}
?>

<?php if (defined('WICKET_WP_THEME_V2')) : ?>
	<div class="group relative inline-flex <?php echo implode(' ', $classes) ?>">
		<span class="inline-flex items-center justify-center">
			<?php get_component('icon', [
			    'icon'    => 'fa-regular fa-circle-info',
			    'classes' => ['icon-default'],
			]); ?>
			<?php get_component('icon', [
			    'icon'    => 'fa-solid fa-circle-info',
			    'classes' => ['icon-hover'],
			]); ?>
		</span>

		<div class="<?php echo implode(' ', $content_classes) ?> group-hover:block">
			<svg width="23" height="23" class="<?php echo implode(' ', $caret_classes) ?>" viewBox="0 0 23 23" fill="none"
				xmlns="http://www.w3.org/2000/svg">
				<rect width="15.2965" height="16" transform="translate(11.9137) rotate(45)" fill="currentColor" />
			</svg>
			<?php echo $content; ?>
		</div>
	</div>
<?php else: ?>
	<div class="group relative inline-flex <?php echo implode(' ', $classes) ?>">
		<span class="w-4 h-4 inline-flex items-center justify-center border border-3 border-dark-100 rounded-[50%]">
			<?php get_component('icon', [
			    'icon'    => 'fa-regular fa-circle-info',
			    'classes' => ['text-[9px]'],
			]); ?>
		</span>

		<div class="<?php echo implode(' ', $content_classes) ?> group-hover:block">
			<svg width="23" height="23" class="<?php echo implode(' ', $caret_classes) ?>" viewBox="0 0 23 23" fill="none"
				xmlns="http://www.w3.org/2000/svg">
				<rect width="15.2965" height="16" transform="translate(11.9137) rotate(45)" fill="#232A31" />
			</svg>
			<?php echo $content; ?>
		</div>
	</div>
<?php endif; ?>
