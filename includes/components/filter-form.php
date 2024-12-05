<?php
$defaults                     = array(
	'classes'               => [],
	'taxonomies'            => [],
	'post_types'            => [],
	'hide_date_filter'      => false,
	'pre_filter_categories' => [],
);
$args                         = wp_parse_args( $args, $defaults );
$classes                      = $args['classes'];
$taxonomies                   = $args['taxonomies'];
$post_types                   = $args['post_types'];
$hide_date_filter             = $args['hide_date_filter'];
$pre_filter_categories        = $args['pre_filter_categories'];
$pre_filter_category_taxonomy = '';
$classes[]                    = 'component-filter-form';

if ( ! defined( 'WICKET_WP_THEME_V2' ) ) {
	$classes = array_merge( $classes, [ 'py-4', 'px-4', 'lg:py-8', 'lg:px-0', 'lg:pr-3' ] );
}

if ( ! empty( $pre_filter_categories ) ) {
	$pre_filter_category_taxonomy = $pre_filter_categories[0]->taxonomy;
}
?>

<div x-data="{showFilters: <?php echo wp_is_mobile() ? 'false' : 'true' ?>}"
	class="<?php echo implode( ' ', $classes ) ?>">
	<div class="flex items-center justify-between gap-3 component-filter-form__top" @click="showFilters = !showFilters"
		:class="showFilters ? 'open' : 'closed'">
		<span class="flex items-center gap-3">
			<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
				<path
					d="M0 20.25C0 19.6406 0.46875 19.125 1.125 19.125H3.89062C4.35938 17.625 5.8125 16.5 7.5 16.5C9.14062 16.5 10.5938 17.625 11.0625 19.125H22.875C23.4844 19.125 24 19.6406 24 20.25C24 20.9062 23.4844 21.375 22.875 21.375H11.0625C10.5938 22.9219 9.14062 24 7.5 24C5.8125 24 4.35938 22.9219 3.89062 21.375H1.125C0.46875 21.375 0 20.9062 0 20.25ZM9 20.25C9 19.4531 8.29688 18.75 7.5 18.75C6.65625 18.75 6 19.4531 6 20.25C6 21.0938 6.65625 21.75 7.5 21.75C8.29688 21.75 9 21.0938 9 20.25ZM16.5 9C18.1406 9 19.5938 10.125 20.0625 11.625H22.875C23.4844 11.625 24 12.1406 24 12.75C24 13.4062 23.4844 13.875 22.875 13.875H20.0625C19.5938 15.4219 18.1406 16.5 16.5 16.5C14.8125 16.5 13.3594 15.4219 12.8906 13.875H1.125C0.46875 13.875 0 13.4062 0 12.75C0 12.1406 0.46875 11.625 1.125 11.625H12.8906C13.3594 10.125 14.8125 9 16.5 9ZM18 12.75C18 11.9531 17.2969 11.25 16.5 11.25C15.6562 11.25 15 11.9531 15 12.75C15 13.5938 15.6562 14.25 16.5 14.25C17.2969 14.25 18 13.5938 18 12.75ZM22.875 4.125C23.4844 4.125 24 4.64062 24 5.25C24 5.90625 23.4844 6.375 22.875 6.375H12.5625C12.0938 7.92188 10.6406 9 9 9C7.3125 9 5.85938 7.92188 5.39062 6.375H1.125C0.46875 6.375 0 5.90625 0 5.25C0 4.64062 0.46875 4.125 1.125 4.125H5.39062C5.85938 2.625 7.3125 1.5 9 1.5C10.6406 1.5 12.0938 2.625 12.5625 4.125H22.875ZM7.5 5.25C7.5 6.09375 8.15625 6.75 9 6.75C9.79688 6.75 10.5 6.09375 10.5 5.25C10.5 4.45312 9.79688 3.75 9 3.75C8.15625 3.75 7.5 4.45312 7.5 5.25Z"
					fill="#232A31" />
			</svg>

			<span
				class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-filter-form__heading' : 'text-heading-xs font-bold' ?>">
				<?php echo __( 'Refine Results', 'wicket' ); ?>
			</span>
		</span>

		<template x-if="!showFilters">
			<?php get_component( 'icon', [ 
				'icon'    => 'fa-solid fa-plus',
				'text'    => __( 'Toggle filters' ),
				'classes' => [ 'lg:hidden text-[20px]' ],
			] ); ?>
		</template>

		<template x-if="showFilters">
			<?php get_component( 'icon', [ 
				'icon'    => 'fa-solid fa-minus',
				'text'    => __( 'Toggle filters' ),
				'classes' => [ 'lg:hidden text-[20px]' ],
			] ); ?>
		</template>

	</div>

	<div <?php if ( wp_is_mobile() ) : ?>x-show="showFilters" <?php endif; ?>
		class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-filter-form__group' : 'mt-8' ?>">
		<?php if ( ! empty( $post_types ) ) : ?>
			<?php $post_type_filter_key = 'post_type' ?>
			<div x-data="{open: true, selectedItemsCount: 0, showAll: false}"
				class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-filter-form__filter-section' : 'pb-3 mb-3 border-b border-light-020' ?>">
				<button @click="open = ! open" id="<?php echo $post_type_filter_key; ?>-dropdown-toggle" type="button"
					class="flex w-full gap-3 items-center">
					<span
						class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-filter-form__filter-section-label' : 'font-bold' ?>">
						<?php _e( 'Content Types', 'wicket' ); ?>
					</span>
					<span class="ml-auto">
						<template x-if="selectedItemsCount">
							<span x-text="`(${selectedItemsCount})`"
								class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-filter-form__selected-items-indicator' : 'font-bold text-dark-070 mr-3' ?>"></span>
						</template>
						<template x-if="open">
							<i class="fas fa-caret-up component-filter-form__collapse-icon"></i>
						</template>
						<template x-if="!open">
							<i class="fas fa-caret-down component-filter-form__expand-icon"></i>
						</template>
					</span>
				</button>
				<div id="<?php echo $post_type_filter_key; ?>-dropdown" x-show="open">
					<?php
					$post_type_query = isset( $_GET[ $post_type_filter_key ] ) ? $_GET[ $post_type_filter_key ] : '';
					?>
					<ul class="mt-3">
						<?php
						$index = 0;
						foreach ( $post_types as $post_type ) :
							$post_type_obj = get_post_type_object( $post_type['key'] );
							$index++; ?>
							<li class="mb-3" <?php if ( $index > 5 ) : ?>:class="showAll || 'hidden'" <?php endif; ?>>
								<?php
								$checkedState = false;
								if ( is_array( $post_type_query ) ) {
									if ( in_array( $post_type['key'], $post_type_query ) ) {
										$checkedState = true;
									}
								}
								?>
								<div class="flex gap-2 items-center">
									<input id="<?php echo $post_type_filter_key . '_' . $post_type['key']; ?>" class="!m-0 w-4 h-4"
										type="checkbox" x-init="selectedItemsCount = selectedItemsCount + <?php echo $checkedState ? 1 : 0; ?>"
										x-on:change="selectedItemsCount = $event.target.checked ? selectedItemsCount + 1 : selectedItemsCount - 1"
										name="<?php echo $post_type_filter_key; ?>[]" value="<?php echo $post_type['key']; ?>" <?php if ( $checkedState ) : ?>checked<?php endif; ?>>
									<label for="<?php echo $post_type_filter_key . '_' . $post_type['key']; ?>"
										class="font-normal mb-0 leading-none">
										<?php echo $post_type_obj->labels->name; ?>
									</label>
								</div>
							</li>
							<?php
						endforeach; ?>
					</ul>
					<?php if ( count( $post_types ) > 5 ) : ?>
						<button class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-filter-form__see-more' : 'underline' ?>"
							type="button" @click="showAll = !showAll">
							<template x-if="showAll">
								<span>
									<?php echo __( 'See Less', 'wicket' ) ?>
								</span>
							</template>
							<template x-if="!showAll">
								<span>
									<?php echo __( 'See More', 'wicket' ) ?>
								</span>
							</template>
						</button>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<?php
		foreach ( $taxonomies as $taxonomy ) : ?>
			<?php
			$taxonomy_obj = get_taxonomy( $taxonomy['slug'] );
			$terms        = [];

			// If categories are passed, use them to get parent terms
			if ( ! empty( $pre_filter_categories && $pre_filter_category_taxonomy == $taxonomy['slug'] ) ) {
				$parent_categories = [];
				foreach ( $pre_filter_categories as $category ) {
					if ( $category->parent == 0 ) {
						$parent_categories[] = $category;
					}
				}
				$terms = $parent_categories;
			} else {
				$terms = get_terms( [ 
					'taxonomy'   => $taxonomy['slug'],
					'parent'     => 0,
					'hide_empty' => false,
				] );
			}

			if ( is_wp_error( $terms ) ) {
				$terms = [];
			}
			?>
			<div x-data="{open: true, selectedItemsCount: 0, showAll: false}"
				class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-filter-form__filter-section' : 'pb-3 mb-3 border-b border-light-020' ?>">
				<button @click="open = ! open" id="<?php echo $taxonomy['slug']; ?>-dropdown-toggle" type="button"
					class="flex w-full gap-3 items-center">
					<span
						class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-filter-form__filter-section-label' : 'font-bold' ?>">
						<?php echo $taxonomy_obj->labels->singular_name; ?>
					</span>
					<?php if ( $taxonomy['tooltip'] ) {
						get_component( 'tooltip', [ 
							'content'  => $taxonomy['tooltip'],
							'position' => 'right',
						] );
					} ?>
					<span class="ml-auto">
						<template x-if="selectedItemsCount">
							<span x-text="`(${selectedItemsCount})`"
								class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-filter-form__selected-items-indicator' : 'font-bold text-dark-070 mr-3' ?>"></span>
						</template>
						<template x-if="open">
							<i class="fas fa-caret-up component-filter-form__collapse-icon"></i>
						</template>
						<template x-if="!open">
							<i class="fas fa-caret-down component-filter-form__expand-icon"></i>
						</template>
					</span>

				</button>
				<div id="<?php echo $taxonomy['slug']; ?>-dropdown" x-show="open">
					<?php
					$term_query = isset( $_GET[ $taxonomy['slug'] ] ) ? $_GET[ $taxonomy['slug'] ] : '';
					?>
					<ul class="mt-3">
						<?php
						$index = 0;
						foreach ( $terms as $term ) :
							$index++; ?>
							<li class="mb-3" <?php if ( $index > 5 ) : ?>:class="showAll || 'hidden'" <?php endif; ?>>
								<?php
								$checkedState = false;
								if ( is_array( $term_query ) ) {
									if ( in_array( $term->slug, $term_query ) ) {
										$checkedState = true;
									}
								} elseif ( $term_query == $term->slug ) {
									$checkedState = true;
								}
								?>
								<div class="flex gap-2 items-center">
									<input id="<?php echo $taxonomy['slug'] . '_' . $term->slug; ?>" class="!m-0 w-4 h-4" type="checkbox"
										x-init="selectedItemsCount = selectedItemsCount + <?php echo $checkedState ? 1 : 0; ?>"
										x-on:change="selectedItemsCount = $event.target.checked ? selectedItemsCount + 1 : selectedItemsCount - 1"
										name="<?php echo $taxonomy['slug']; ?>[]" value="<?php echo $term->slug; ?>" <?php if ( $checkedState ) : ?>checked<?php endif; ?>>
									<label for="<?php echo $taxonomy['slug'] . '_' . $term->slug; ?>" class="font-normal mb-0 leading-none">
										<?php echo $term->name; ?>
									</label>
								</div>
								<?php
								// Get child terms
								$child_terms = get_terms( $taxonomy['slug'], array( 'parent' => $term->term_id ) );

								// If categories are passed, use them to get child terms
								$child_categories = [];
								if ( $taxonomy['slug'] === $pre_filter_category_taxonomy && ! empty( $pre_filter_categories ) ) {
									foreach ( $pre_filter_categories as $category ) {
										if ( $category->parent == $term->term_id ) {
											$child_categories[] = $category;
										}
									}
									$child_terms = $child_categories;
								}


								if ( ! is_wp_error( $child_terms ) && ! empty( $child_terms ) ) : ?>
									<ul class="ml-4 mt-3">
										<?php foreach ( $child_terms as $child_term ) : ?>
											<li class="mb-3">
												<?php
												$child_checkedState = false;
												if ( is_array( $term_query ) ) {
													if ( in_array( $child_term->slug, $term_query ) ) {
														$child_checkedState = true;
													}
												} elseif ( $term_query == $child_term->slug ) {
													$child_checkedState = true;
												}
												?>
												<div class="flex gap-2 items-center">
													<input id="<?php echo $taxonomy['slug'] . '_' . $child_term->slug; ?>" class="!m-0 w-4 h-4"
														type="checkbox"
														x-init="selectedItemsCount = selectedItemsCount + <?php echo $child_checkedState ? 1 : 0; ?>"
														x-on:change="selectedItemsCount = $event.target.checked ? selectedItemsCount + 1 : selectedItemsCount - 1"
														name="<?php echo $taxonomy['slug']; ?>[]" value="<?php echo $child_term->slug; ?>" <?php if ( $child_checkedState ) : ?>checked<?php endif; ?>>
													<label for="<?php echo $taxonomy['slug'] . '_' . $child_term->slug; ?>"
														class="font-normal mb-0 leading-none">
														<?php echo $child_term->name; ?>
													</label>
												</div>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							</li>
							<?php
						endforeach; ?>
					</ul>
					<?php if ( count( $terms ) > 5 ) : ?>
						<button class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-filter-form__see-more' : 'underline' ?>"
							type="button" @click="showAll = !showAll">
							<template x-if="showAll">
								<span>
									<?php echo __( 'See Less', 'wicket' ) ?>
								</span>
							</template>
							<template x-if="!showAll">
								<span>
									<?php echo __( 'See More', 'wicket' ) ?>
								</span>
							</template>
						</button>
					<?php endif; ?>
				</div>
			</div>
			<?php
		endforeach; ?>

		<?php if ( ! $hide_date_filter ) : ?>
			<div x-data="{open: true}"
				class="date-range-controls <?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-filter-form__filter-section' : 'pb-3 mb-3 border-b border-light-020' ?>">
				<button @click="open = ! open" id="date-dropdown-toggle" type="button" class="flex w-full gap-3 items-center">
					<span
						class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-filter-form__filter-section-label' : 'font-bold' ?>">
						<?php echo __( 'Date Range', 'wicket' ); ?>
					</span>
					<span class="ml-auto">
						<template x-if="open">
							<i class="fas fa-caret-up component-filter-form__collapse-icon"></i>
						</template>
						<template x-if="!open">
							<i class="fas fa-caret-down component-filter-form__expand-icon"></i>
						</template>
					</span>
				</button>
				<div id="date-dropdown" x-show="open">
					<div class="mt-3">
						<?php
						$start_date = isset( $_GET['start_date'] ) ? $_GET['start_date'] : '';
						$end_date   = isset( $_GET['end_date'] ) ? $_GET['end_date'] : '';
						?>
						<div class="group relative mb-3">
							<label for="start_date"
								class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-filter-form__date-label' : 'font-normal mb-0 text-dark-070 absolute top-1/2 translate-y-[-50%] pl-4' ?>">
								<?php echo __( 'From:', 'wicket' ); ?>
							</label>
							<input id="start_date"
								class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-filter-form__date-input' : 'w-full italic pl-16 text-light-040 group-[.has-value]:text-dark-100' ?>"
								type="date" name="start_date" value="<?php echo $start_date; ?>">
						</div>
						<div class="group relative">
							<label for="end_date"
								class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-filter-form__date-label' : 'font-normal mb-0 text-dark-070 absolute top-1/2 translate-y-[-50%] pl-4' ?>">
								<?php echo __( 'To:', 'wicket' ); ?>
							</label>
							<input id="end_date"
								class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-filter-form__date-input' : 'w-full italic pl-16 text-light-040 group-[.has-value]:text-dark-100' ?>"
								type="date" name="end_date" value="<?php echo $end_date; ?>">
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<?php
		get_component( 'button', [ 
			'variant'  => 'primary',
			'label'    => __( 'Apply Filters', 'wicket' ),
			'reversed' => $reversed,
			'type'     => 'submit',
			'classes'  => defined( 'WICKET_WP_THEME_V2' ) ? [ 'component-filter-form__submit' ] : [ 'mt-4' ],
		] );
		?>

		<div>
			<?php
			$clear_all_url = strtok( $_SERVER['REQUEST_URI'], '?' ); // Grabs everything in the URI before a ? character
			if ( isset( $_GET['s'] ) ) {
				$clear_all_url .= '?s=';
			}
			?>

			<?php
			get_component( 'button', [ 
				'variant'     => 'ghost',
				'label'       => __( 'Clear All', 'wicket' ),
				'a_tag'       => true,
				'prefix_icon' => 'fa-solid fa-xmark',
				'link'        => $clear_all_url,
				'classes'     => defined( 'WICKET_WP_THEME_V2' ) ? [ 'component-filter-form__clear-btn' ] : [ 'mt-3' ],
			] );
			?>
		</div>

	</div>

</div>