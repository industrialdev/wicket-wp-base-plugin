<?php
$defaults  = array(
	'classes' => [],
  'style'   => 'normal' // 'normal' or 'reversed'
);
$args      = wp_parse_args( $args, $defaults );
$classes   = $args['classes'];
?>

<div class="wicket-breadcrumb component-breadcrumbs hidden md:flex items-center <?php echo implode( ' ', $classes ) ?> <?php echo $args['style']; ?>">
	<?php wicket_breadcrumb(); ?>
</div>
