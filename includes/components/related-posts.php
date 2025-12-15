<?php
$defaults = [
    'classes'                  => defined('WICKET_WP_THEME_V2') ? [] : ['px-4', 'lg:px-0'],
    'title'                    => '',
    'hide_block_title'         => false,
    'column_count'             => 3,
    'max_posts'                => 3,
    'post_type'                => [],
    'taxonomies'               => [],
    'highlight_featured_posts' => false,
    'number_of_featured_posts' => 1,
    'hide_excerpt'             => false,
    'hide_date'                => false,
    'hide_featured_image'      => false,
    'hide_content_type'        => false,
    'show_cta'                 => false,
    'show_view_all'            => false,
    'set_custom_view_all'      => false,
    'view_all_link'            => [],
    'cta_style'                => 'primary',
    'cta_label'                => '',
    'current_post_id'          => '',
];
$args = wp_parse_args($args, $defaults);
$classes = $args['classes'];
$title = $args['title'];
$hide_block_title = $args['hide_block_title'];
$column_count = $args['column_count'];
$max_posts = $args['max_posts'];
$post_type = $args['post_type'];
$taxonomies = $args['taxonomies'];
$highlight_featured_posts = $args['highlight_featured_posts'];
$number_of_featured_posts = $args['number_of_featured_posts'];
$hide_excerpt = $args['hide_excerpt'];
$hide_date = $args['hide_date'];
$hide_featured_image = $args['hide_featured_image'];
$hide_content_type = $args['hide_content_type'];
$show_cta = $args['show_cta'];
$show_view_all = $args['show_view_all'];
$set_custom_view_all = $args['set_custom_view_all'];
$view_all_link = $args['view_all_link'];
$cta_style = $args['cta_style'];
$cta_label = $args['cta_label'];
$current_post_id = $args['current_post_id'];

if ($title == '') {
    $post_type_object = get_post_type_object($post_type['post_type']);
    $post_type_label = $post_type_object->labels->name;
    $title = __('Related ', 'wicket');
    $title .= $post_type_label;
}

// WP_Query arguments
$query_args = [
    'post_type'      => $post_type['post_type'],
    'posts_per_page' => $max_posts,
    'post__not_in'   => [$current_post_id],
];

// If taxonomies are set, add them to the query
if (!empty($taxonomies)) {
    foreach ($taxonomies as $taxonomy) {
        $taxonomy_terms = $taxonomy['taxonomy_terms'] ?? '';
        $relation = $taxonomy['relation'] ?? '';

        // Get taxonomy slug from taxonomy_term
        $relation_args = [];
        $relation_args['relation'] = $relation;
        foreach ($taxonomy_terms as $term) {
            $term_object = get_term($term['taxonomy_term']);
            $relation_args[] = [
                'taxonomy' => $term_object->taxonomy,
                'field'    => 'slug',
                'terms'    => [$term_object->slug],
                'operator' => 'IN',
            ];
        }

        // Add tax_query to query_args
        $query_args['tax_query'] = $relation_args;
    }
}

// The Query
$related_posts = new WP_Query($query_args);
// Get post type archive link
$post_type_archive_link = get_post_type_archive_link($post_type['post_type']);

$classes[] = 'component-related-posts';

$featured_posts = [];

// Get sticky posts if highlight_featured_posts is true
if ($highlight_featured_posts) {

    // If sticky posts exist, get them
    if ($post_type['post_type'] == 'post') {
        $sticky_posts = get_option('sticky_posts');

        $sticky_args = [
            'post__in'       => $sticky_posts,
            'posts_per_page' => $number_of_featured_posts,
            'orderby'        => 'post__in',
        ];

        $sticky_posts = new WP_Query($sticky_args);
        $featured_posts = $sticky_posts->posts;
    } elseif ($post_type['post_type'] == 'tribe_events') {
        if (!function_exists('tribe_get_events')) {
            return;
        }
        $sticky_posts = tribe_get_events([
            'start_date'     => 'now',
            'posts_per_page' => 8,
            'featured'       => true,
        ]);
        $featured_posts = $sticky_posts;
    }

    // Exclude featured posts from related posts
    $featured_post_ids = wp_list_pluck($featured_posts, 'ID');
    $query_args['post__not_in'] = array_merge($query_args['post__not_in'], $featured_post_ids);
    $related_posts = new WP_Query($query_args);
}
?>

<?php if ($related_posts->have_posts()) : ?>
	<div class="<?php echo implode(' ', $classes) ?>">
		<div class="container">
			<?php if ($title && !$hide_block_title) : ?>
				<div class="<?php echo defined('WICKET_WP_THEME_V2') ? 'component-related-posts__top-wrap' : 'mb-10' ?>">
					<span class="<?php echo defined('WICKET_WP_THEME_V2') ? 'component-related-posts__title' : 'text-heading-sm font-bold' ?>"><?php echo $title; ?></span>

					<?php if ($show_view_all) : ?>
						<?php if ($set_custom_view_all && isset($view_all_link['url'])) : ?>
							<?php get_component('link', [
							    'url'        => $view_all_link['url'],
							    'target'     => $view_all_link['target'],
							    'text'       => $view_all_link['title'],
							    'default_link_style' => true,
							    'classes'	   => defined('WICKET_WP_THEME_V2') ? ['component-related-posts__view-all'] : ['component-related-posts__view-all', 'underline', 'ml-4', 'pl-4', 'border-l', 'border-dark-070'],
							]); ?>
						<?php else : ?>
							<?php get_component('link', [
							    'url'        => $post_type_archive_link,
							    'text'       => __('View All', 'wicket'),
							    'default_link_style' => true,
							    'classes'	   => defined('WICKET_WP_THEME_V2') ? ['component-related-posts__view-all'] : ['component-related-posts__view-all', 'underline', 'hover:no-underline', 'ml-4', 'pl-4', 'border-l', 'border-dark-070'],
							]); ?>
						<?php endif; ?>
					<?php endif; ?>

				</div>
			<?php endif; ?>

			<?php if (!empty($featured_posts)) : ?>
				<?php

                // Foreach through featured posts
                foreach ($featured_posts as $featured_post) {
                    $post_id = $featured_post->ID;
                    $post_type = get_post_type($post_id);
                    $image = [];
                    $featured_image_id = get_post_thumbnail_id($post_id);
                    $featured_image_alt = get_post_meta($featured_image_id, '_wp_attachment_image_alt', true);
                    $date_format = apply_filters('wicket_general_date_format', 'F j, Y');
                    $date = get_the_date($date_format, $post_id);

                    if (!$hide_featured_image && $featured_image_id !== 0) {
                        $image = [
                            'id'  => $featured_image_id,
                            'alt' => $featured_image_alt,
                        ];
                    }

                    if ($post_type == 'tribe_events') {
                        $start_date = tribe_get_start_date($post_id, false, $date_format);
                        $end_date = tribe_get_end_date($post_id, false, $date_format);

                        if ($start_date === $end_date) {
                            $date = $start_date;
                        } else {
                            $date = $start_date . ' - ' . $end_date;
                        }
                    }

                    get_component('card-featured', [
                        'classes'        => defined('WICKET_WP_THEME_V2') ? [] : ['p-4', 'mb-4'],
                        'post_type'      => $post_type,
                        'content_type'   => !$hide_content_type ? get_related_content_type_term($post_id) : '',
                        'title'          => get_the_title($post_id),
                        'excerpt'        => !$hide_excerpt ? get_the_excerpt($post_id) : '',
                        'date'           => !$hide_date ? $date : '',
                        'image'          => $image,
                        'image_position' => 'left',
                        'member_only'    => is_member_only($post_id),
                        'link'           => get_permalink($post_id),
                        'cta'            => $show_cta ? $cta_style : null,
                        'cta_label'      => $cta_label,
                    ]);
                }
?>
			<?php endif; ?>

			<div class="component-related-posts__col-<?php echo $column_count ?> grid gap-10 grid-cols-1 lg:grid-cols-<?php echo $column_count ?> <?php echo defined('WICKET_WP_THEME_V2') ? 'lg:gap-[--space-150]' : 'lg:gap-4' ?>">
				<?php
while ($related_posts->have_posts()) {
    $related_posts->the_post();
    $post_id = get_the_ID();
    $post_type = get_post_type($post_id);
    $image = [];
    $featured_image_id = get_post_thumbnail_id($post_id);
    $featured_image_alt = get_post_meta(
        $featured_image_id,
        '_wp_attachment_image_alt',
        true
    );
    $date_format = apply_filters('wicket_general_date_format', 'F j, Y');
    $date = get_the_date($date_format, $post_id);

    if (!$hide_featured_image && $featured_image_id !== 0) {
        $image = [
            'id'  => $featured_image_id,
            'alt' => $featured_image_alt,
        ];
    }

    if ($post_type == 'tribe_events') {
        $start_date = tribe_get_start_date($post_id, false, $date_format);
        $end_date = tribe_get_end_date($post_id, false, $date_format);

        if ($start_date === $end_date) {
            $date = $start_date;
        } else {
            $date = $start_date . ' - ' . $end_date;
        }
    }

    get_component('card-featured', [
        'classes'        => defined('WICKET_WP_THEME_V2') ? [] : ['p-4'],
        'post_type'      => $post_type,
        'content_type'   => !$hide_content_type ? get_related_content_type_term($post_id) : '',
        'title'          => get_the_title($post_id),
        'excerpt'        => !$hide_excerpt ? get_the_excerpt($post_id) : '',
        'date'           => !$hide_date ? $date : '',
        'image'          => $image,
        'image_position' => $column_count == '1' ? 'right' : 'top',
        'member_only'    => is_member_only($post_id),
        'link'           => get_permalink($post_id),
        'cta'            => $show_cta ? $cta_style : null,
        'cta_label'      => $cta_label,
    ]);
}
wp_reset_postdata();
?>
			</div>
		</div>
	</div>

<?php elseif (is_admin()) : ?>
	<div class="container">
		<?php
        get_component('alert', [
            'content' => __('No related posts found.', 'wicket'),
        ]);
    ?>
	</div>
<?php endif; ?>