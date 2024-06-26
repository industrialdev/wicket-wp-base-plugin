<?php
$defaults    = array(
	'classes'     => [ 'flex', 'gap-2', 'w-full' ],
	'placeholder' => __( 'Search by Keyword', 'wicket' ),
	'url-param'   => 'keyword',
);
$args        = wp_parse_args( $args, $defaults );
$classes     = $args['classes'];
$placeholder = $args['placeholder'];
$url_param   = $args['url-param'];

$classes[]      = 'component-search-form form';
$button_variant = apply_filters( 'wicket_search_form_button_variant', 'primary' );
?>

<div class="<?php echo implode( ' ', $classes ); ?>">
	<div class="relative w-full">
		<?php get_component( 'icon', [ 
			'icon'    => 'fa fa-search',
			'text'    => __( 'Search' ),
			'classes' => [ 
				'absolute',
				'left-4',
				'top-1/2',
				'-translate-y-1/2',
				'text-dark-100',
				'text-lg',
			],
		] ); ?>
		<input type="search" id="<?php echo $url_param; ?>" name="<?php echo $url_param; ?>"
			value="<?php echo isset( $_GET[ $url_param ] ) ? $_GET[ $url_param ] : ''; ?>"
			placeholder="<?php echo $placeholder; ?>" class="!pl-10 w-full" />
	</div>

	<button class="button inline-flex button--<?php echo $button_variant ?>">
		<?php echo __( 'Search', 'wicket' ); ?>
	</button>
</div>