<?php
$defaults        = array(
	'classes'     => [],
	'post_id'     => '',
	'title'       => '',
	'excerpt'     => '',
	'date'        => '',
	'link'        => '',
	'image'       => '',
	'member_only' => false,
	'cta'         => null,
	'cta_label'   => '',
);
$args            = wp_parse_args( $args, $defaults );
$classes         = $args['classes'];
$product_id      = $args['post_id'];
$content_type    = $args['content_type'];
$title           = $args['title'];
$link            = $args['link'];
$image           = $args['image'];
$member_only     = $args['member_only'];
$cta             = $args['cta'];
$cta_label       = $args['cta_label'];
$add_to_cart_url = '';

if ( defined( 'WICKET_WP_THEME_V2' ) ) {
	$classes[] = 'component-card-product';
} else {
	$classes = array_merge( $classes, [ 'component-card-product', 'bg-white', 'shadow-4', 'flex', 'flex-col', 'items-center', 'gap-4', 'relative', 'h-full' ] );
}

$image_wrapper_classes = [];

if ( defined( 'WICKET_WP_THEME_V2' ) ) {
	$title_classes = [ 'component-card-product__title' ];
} else {
	$title_classes = [ 'component-card-product__title', 'block', 'text-dark-100', 'font-bold', 'leading-7', 'text-heading-xs' ];
}

// If woocommerce is not active, return early
if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

// Get the product and set it globally so WooCommerce functions can use it
global $product;
$product = wc_get_product($product_id);
?>

<div class="@container">
	<div class="<?php echo implode( ' ', $classes ) ?>">
		<a href="<?php the_permalink(); ?>" class="relative">
			<?php woocommerce_show_product_loop_sale_flash(); ?>
			<?php woocommerce_template_loop_product_thumbnail(); ?>
		</a>
		<a class="component-card-product__title-link" href="<?php the_permalink(); ?>">
			<h2 class="<?php echo implode( ' ', $title_classes ) ?>"><?php the_title(); ?></h2>
		</a>
		<?php woocommerce_template_loop_price(); ?>
		<?php woocommerce_template_loop_add_to_cart(); ?>
	</div>
</div>