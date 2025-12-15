<?php
$defaults = [
    'classes' => [],
    'style'   => 'normal', // 'normal' or 'reversed'
];
$args = wp_parse_args($args, $defaults);
$classes = $args['classes'];

$reversed = $args['style'] === 'reversed';

if (defined('WICKET_WP_THEME_V2')) {
    $classes[] = 'component-breadcrumbs';
} else {
    $classes = array_merge($classes, ['wicket-breadcrumb', 'component-breadcrumbs', 'hidden', 'md:flex', 'items-center']);
}
?>

<div class="<?php echo implode(' ', $classes) ?> <?php echo $args['style']; ?>">
	<?php wicket_breadcrumb($reversed); ?>
</div>
