<?php
$defaults                   = array(
	'classes'                    => [ 'px-4', 'lg:px-0' ],
	'title'                      => '',
	'hide_block_title'           => false,
	'show_view_all'              => false,
	'set_custom_view_all'        => false,
	'view_all_link'              => [],
	'column_count'               => 3,
	'max_posts'                  => 3,
	'taxonomies'                 => [],
	'hide_excerpt'               => false,
	'hide_date'                  => false,
	'hide_featured_image'        => false,
	'hide_event_category'        => false,
	'hide_price'                 => false,
	'hide_start_date_indicator'  => false,
	'hide_event_format_location' => false,
	'remove_drop_shadow'         => false,
	'highlight_featured_posts'   => false,
	'number_of_featured_posts'   => 1,
	'show_cta'                   => false,
	'cta_options'                => '',
	'cta_style'                  => 'primary',
	'cta_label'                  => '',
	'current_post_id'            => '',
	'show_tags'                  => false,
	'tag_taxonomy'               => '',
);
$args                       = wp_parse_args( $args, $defaults );
$classes                    = $args['classes'];
$post_type                  = 'tribe_events';
$title                      = $args['title'];
$hide_block_title           = $args['hide_block_title'];
$show_view_all              = $args['show_view_all'];
$set_custom_view_all        = $args['set_custom_view_all'];
$view_all_link              = $args['view_all_link'];
$column_count               = $args['column_count'];
$max_posts                  = $args['max_posts'];
$taxonomies                 = $args['taxonomies'];
$hide_excerpt               = $args['hide_excerpt'];
$hide_date                  = $args['hide_date'];
$hide_featured_image        = $args['hide_featured_image'];
$hide_event_category        = $args['hide_event_category'];
$hide_price                 = $args['hide_price'];
$hide_start_date_indicator  = $args['hide_start_date_indicator'];
$hide_event_format_location = $args['hide_event_format_location'];
$remove_drop_shadow         = $args['remove_drop_shadow'];
$highlight_featured_posts   = $args['highlight_featured_posts'];
$number_of_featured_posts   = $args['number_of_featured_posts'];
$show_cta                   = $args['show_cta'];
$cta_options                = $args['cta_options'];
$cta_style                  = $args['cta_style'];
$cta_label                  = $args['cta_label'];
$current_post_id            = $args['current_post_id'];
$show_tags                  = $args['show_tags'];
$tag_taxonomy               = $args['tag_taxonomy'];

if ( $title == '' ) {
	$title = __( 'Related Events', 'wicket' );
}

// WP_Query arguments
$query_args = array(
	'post_type'      => $post_type,
	'posts_per_page' => $max_posts,
	'post__not_in'   => [ $current_post_id ],
);

// If taxonomies are set, add them to the query
if ( ! empty( $taxonomies ) ) {
	foreach ( $taxonomies as $taxonomy ) {
		$taxonomy_terms = isset( $taxonomy['taxonomy_terms'] ) ? $taxonomy['taxonomy_terms'] : '';
		$relation       = isset( $taxonomy['relation'] ) ? $taxonomy['relation'] : '';

		// Get taxonomy slug from taxonomy_term
		$relation_args             = array();
		$relation_args['relation'] = $relation;
		foreach ( $taxonomy_terms as $term ) {
			$term_object     = get_term( $term['taxonomy_term'] );
			$relation_args[] = array(
				'taxonomy' => $term_object->taxonomy,
				'field'    => 'slug',
				'terms'    => [ $term_object->slug ],
				'operator' => 'IN',
			);
		}

		// Add tax_query to query_args
		$query_args['tax_query'] = $relation_args;
	}
}

// The Query
$related_posts = new WP_Query( $query_args );
// Get post type archive link
$post_type_archive_link = get_post_type_archive_link( $post_type );

$classes[] = 'component-related-events';

$featured_posts = [];

// Get sticky posts if highlight_featured_posts is true
if ( $highlight_featured_posts ) {

	if ( ! function_exists( 'tribe_get_events' ) ) {
		return;
	}
	$sticky_posts   = tribe_get_events( [ 
		'start_date'     => 'now',
		'posts_per_page' => 8,
		'featured'       => true,
	] );
	$featured_posts = $sticky_posts;

	// Exclude featured posts from related posts
	$featured_post_ids          = wp_list_pluck( $featured_posts, 'ID' );
	$query_args['post__not_in'] = array_merge( $query_args['post__not_in'], $featured_post_ids );
	$related_posts              = new WP_Query( $query_args );
}
?>

<?php if ( $related_posts->have_posts() ) : ?>
	<div class="<?php echo implode( ' ', $classes ) ?>">
		<div class="container">
			<?php if ( $title && ! $hide_block_title ) : ?>
				<div class="mb-10">
					<span class="text-heading-sm font-bold">
						<?php echo $title; ?>
					</span>

					<?php if ( $show_view_all ) : ?>
						<?php if ( $set_custom_view_all && isset( $view_all_link['url'] ) ) : ?>
							<a href="<?php echo $view_all_link['url'] ?>" target="<?php echo $view_all_link['target'] ?>"
								class="component-related-events__view-all underline ml-4 pl-4 border-l border-dark-070">
								<?php echo $view_all_link['title'] ?>
							</a>
						<?php else : ?>
							<a href="<?php echo $post_type_archive_link ?>"
								class="component-related-events__view-all underline ml-4 pl-4 border-l border-dark-070 hover:no-underline">
								<?php echo __( 'View All', 'wicket' ) ?>
							</a>
						<?php endif; ?>
					<?php endif; ?>

				</div>
			<?php endif; ?>

			<?php if ( ! empty( $featured_posts ) ) : ?>
				<?php

				// Foreach through featured posts
				foreach ( $featured_posts as $featured_post ) {
					$post_id            = $featured_post->ID;
					$post_type          = get_post_type( $post_id );
					$image              = [];
					$featured_image_id  = get_post_thumbnail_id( $post_id );
					$featured_image_alt = get_post_meta( $featured_image_id, '_wp_attachment_image_alt', true );
					$date_format        = apply_filters( 'wicket_general_date_format', 'F j, Y' );
					$date               = get_the_date( $date_format, $post_id );

					if ( ! $hide_featured_image && $featured_image_id !== 0 ) {
						$image = [ 
							'id'  => $featured_image_id,
							'alt' => $featured_image_alt,
						];
					}

					$date = tribe_get_start_date( $post_id, false, $date_format );

					get_component( 'card-event', [ 
						'classes'      => defined( 'WICKET_WP_THEME_V2' ) ? [ "cols-{$column_count}" ] : [ 'p-4', 'mb-4' ],
						'post_type'    => $post_type,
						'content_type' => ! $hide_content_type ? get_related_content_type_term( $post_id ) : '',
						'title'        => get_the_title( $post_id ),
						'excerpt'      => ! $hide_excerpt ? get_the_excerpt( $post_id ) : '',
						'date'         => ! $hide_date ? $date : '',
						'image'        => $image,
						'member_only'  => is_member_only( $post_id ),
						'link'         => get_permalink( $post_id ),
						'cta'          => $show_cta ? $cta_style : null,
						'cta_label'    => $cta_label,
					] );
				}
				?>
			<?php endif; ?>



			<div class="grid gap-10 grid-cols-1 lg:gap-x-4 lg:gap-y-6 lg:grid-cols-<?php echo $column_count ?>">
				<?php
				while ( $related_posts->have_posts() ) {
					$related_posts->the_post();
					$post_id            = get_the_ID();
					$post_type          = get_post_type( $post_id );
					$image              = [];
					$featured_image_id  = get_post_thumbnail_id( $post_id );
					$featured_image_alt = get_post_meta( $featured_image_id,
						'_wp_attachment_image_alt', true );
					$date_format        = apply_filters( 'wicket_general_date_format', 'F j, Y' );
					$date               = get_the_date( $date_format, $post_id );

					if ( ! $hide_featured_image && $featured_image_id !== 0 ) {
						$image = [ 
							'id'  => $featured_image_id,
							'alt' => $featured_image_alt,
						];
					}

					$date = tribe_get_start_date( $post_id, false, $date_format );

					get_component( 'card-event', [ 
						'classes'                    => defined( 'WICKET_WP_THEME_V2' ) ? [ "cols-{$column_count}" ] : [ 'p-4' ],
						'post_id'                    => $post_id,
						'hide_excerpt'               => $hide_excerpt,
						'hide_date'                  => $hide_date,
						'hide_event_category'        => $hide_event_category,
						'hide_event_format_location' => $hide_event_format_location,
						'hide_start_date_indicator'  => $hide_start_date_indicator,
						'image'                      => $image,
						'cta'                        => $show_cta ? $cta_style : null,
						'cta_label'                  => $cta_label,
						'remove_drop_shadow'         => $remove_drop_shadow,
						'show_tags'                  => $show_tags,
						'tag_taxonomy'               => $tag_taxonomy,
					] );
				}
				wp_reset_postdata();
				?>
			</div>
		</div>
	</div>

<?php elseif ( is_admin() ) : ?>
	<div class="container">
		<?php
		get_component( 'alert', [ 
			'content' => __( 'No related posts found.', 'wicket' ),
		] );
		?>
	</div>
<?php endif; ?>