<?php
$defaults    = array(
	'classes'     => [ 'flex', 'gap-2', 'w-full' ],
	'placeholder' => __( 'Search by Keyword', 'wicket' ),
);
$args        = wp_parse_args( $args, $defaults );
$classes     = $args['classes'];
$placeholder = $args['placeholder'];

$classes[] = 'component-search-form form';
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
		<input type="search" id="keyword" name="keyword"
			value="<?php echo isset ( $_GET['keyword'] ) ? $_GET['keyword'] : ''; ?>"
			placeholder="<?php echo $placeholder; ?>" class="!pl-10 w-full" />
	</div>

	<button class="button inline-flex button--primary">
		<?php echo __( 'Search', 'wicket' ); ?>
	</button>
</div>