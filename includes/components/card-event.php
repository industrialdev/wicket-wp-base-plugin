<?php

$defaults                   = array(
	'classes'                    => [],
	'post_id'                    => '',
	'hide_excerpt'               => false,
	'hide_date'                  => false,
	'hide_event_category'        => false,
	'hide_event_format_location' => false,
	'hide_start_date_indicator'  => false,
	'hide_price'                 => false,
	'member_only'                => false,
	'cta'                        => null,
	'cta_label'                  => '',
	'remove_drop_shadow'         => false,
	'show_tags'                  => false,
	'tag_taxonomy'               => '',
);
$args                       = wp_parse_args( $args, $defaults );
$classes                    = $args['classes'];
$post_id                    = $args['post_id'];
$hide_excerpt               = $args['hide_excerpt'];
$hide_date                  = $args['hide_date'];
$hide_event_category        = $args['hide_event_category'];
$hide_event_format_location = $args['hide_event_format_location'];
$hide_start_date_indicator  = $args['hide_start_date_indicator'];
$hide_price                 = $args['hide_price'];
$remove_drop_shadow         = $args['remove_drop_shadow'];
$cta                        = $args['cta'];
$cta_label                  = $args['cta_label'];
$show_tags                  = $args['show_tags'];
$tag_taxonomy               = $args['tag_taxonomy'];

$title            = get_the_title( $post_id );
$excerpt          = ! $hide_excerpt ? get_the_excerpt( $post_id ) : '';
$event_category   = ! $hide_event_category ? get_related_content_type_term( $post_id ) : '';
$event_start_date = ! $hide_date ? tribe_get_start_date( $post_id ) : '';
$event_end_date   = ! $hide_date ? tribe_get_end_date( $post_id ) : '';
$link             = get_permalink( $post_id );

// Create combined date display for multi-day events
$event_display_date = '';
if ( $event_start_date && $event_end_date ) {
	// Check if this is a multi-day event by comparing formatted dates
	$start_date_formatted = tribe_get_start_date( $post_id, false, 'Y-m-d' );
	$end_date_formatted   = tribe_get_end_date( $post_id, false, 'Y-m-d' );

	if ( $start_date_formatted === $end_date_formatted ) {
		// Same day event - show only start date
		$event_display_date = $event_start_date;
	} else {
		// Multi-day event - show both dates
		$event_display_date = $event_start_date . ' - ' . $event_end_date;
	}
} elseif ( $event_start_date ) {
	// Only start date available
	$event_display_date = $event_start_date;
}
$image         = $args['image'];
$member_only   = is_member_only( $post_id );
$has_venue     = tribe_has_venue( $post_id );
$venue_name    = ( ! $hide_event_format_location && $has_venue ) ? tribe_get_venue( $post_id ) : '';
$venue_address = tribe_get_full_address( $post_id );

// Check for virtual or hybrid events
$is_virtual         = get_post_meta( $post_id, '_tribe_events_is_virtual', true );
$virtual_event_type = get_post_meta( $post_id, '_tribe_virtual_events_type', true );
$is_hybrid          = ( $virtual_event_type === 'hybrid' );
$event_day_name     = tribe_get_start_date( $post_id, false, 'D' );
$event_day_number   = tribe_get_start_date( $post_id, false, 'j' );

$ticket_price    = '';
$login_link      = '';
$tags            = [];
$show_login_link = false;


if ( class_exists( 'Tribe__Tickets__Tickets' ) ) {
	$tickets         = Tribe__Tickets__Tickets::get_all_event_tickets( $post_id );
	$ticket_currency = tec_tickets_commerce_currency_symbol();
	$currency_output = esc_html( $ticket_currency );

	if ( $tickets ) {
		$ticket_prices         = [];
		$ticket_regular_prices = [];
		$ticket_sale_prices    = [];
		$has_sale_prices       = false;

		foreach ( $tickets as $ticket ) {
			// Get WooCommerce product to check for sale prices
			$wc_product = wc_get_product( $ticket->ID );

			if ( $wc_product ) {
				$regular_price = $wc_product->get_regular_price();
				$sale_price    = $wc_product->get_sale_price();

				if ( $sale_price && $sale_price < $regular_price ) {
					// There's a sale price
					$ticket_prices[]         = $sale_price;
					$ticket_sale_prices[]    = $sale_price;
					$ticket_regular_prices[] = $regular_price;
					$has_sale_prices         = true;
				} else {
					// No sale price, use regular price
					$ticket_prices[]         = $regular_price ?: $ticket->price;
					$ticket_regular_prices[] = $regular_price ?: $ticket->price;
				}
			} else {
				// Fallback to original price method
				$ticket_prices[]         = $ticket->price;
				$ticket_regular_prices[] = $ticket->price;
			}
		}

		$cheapest_ticket_price  = min( $ticket_prices );
		$expensive_ticket_price = max( $ticket_prices ) === $cheapest_ticket_price ? '' : max( $ticket_prices );

		if ( $has_sale_prices ) {
			// Find the corresponding regular prices for display
			$cheapest_regular_price  = '';
			$expensive_regular_price = '';

			// Find regular prices that correspond to the sale prices
			foreach ( $tickets as $ticket ) {
				$wc_product = wc_get_product( $ticket->ID );
				if ( $wc_product ) {
					$sale_price    = $wc_product->get_sale_price();
					$regular_price = $wc_product->get_regular_price();

					if ( $sale_price && $sale_price == $cheapest_ticket_price ) {
						$cheapest_regular_price = $regular_price;
					}
					if ( $sale_price && $sale_price == $expensive_ticket_price ) {
						$expensive_regular_price = $regular_price;
					}
				}
			}

			// Build price display with strikethrough for regular prices
			$price_display = '';
			if ( $cheapest_regular_price ) {
				$price_display .= '<span class="component-card-event__price-regular">' . $ticket_currency . $cheapest_regular_price . '</span>';
			}
			$price_display .= '<span class="component-card-event__price-sale">' . $ticket_currency . $cheapest_ticket_price . '</span>';

			if ( $expensive_ticket_price ) {
				$price_display .= ' - ';
				if ( $expensive_regular_price ) {
					$price_display .= '<span class="component-card-event__price-regular">' . $ticket_currency . $expensive_regular_price . '</span>';
				}
				$price_display .= '<span class="component-card-event__price-sale">' . $ticket_currency . $expensive_ticket_price . '</span>';
			}

			$ticket_price = $price_display;
		} else {
			// No sale prices, use regular display
			$ticket_price = $ticket_currency . $cheapest_ticket_price . ( $expensive_ticket_price ? ' - ' . $ticket_currency . $expensive_ticket_price : '' );
		}

		if ( ! is_user_logged_in() ) {
			$show_login_link = true;
		}
	}
}

if ( $show_tags && $tag_taxonomy['taxonomy'] ) {
	$tags = get_the_terms( $post_id, $tag_taxonomy['taxonomy'] );
}

$classes[]             = 'component-card-event component-card-event--events';
$title_classes         = [ 'component-card-event__title' ];
$image_wrapper_classes = [ 'component-card-event__image-wrapper' ];

if ( defined( 'WICKET_WP_THEME_V2' ) ) {
	if ( $remove_drop_shadow ) {
		$classes[] = 'component-card-event--no-drop-shadow';
	}
} else {
	$classes[]               = 'bg-white flex flex-col gap-4 relative items-start';
	$classes[]               = '@2xl:flex-row @md:flex-row @2xl:items-start justify-between';
	$title_classes[]         = 'block text-dark-100 font-bold leading-7 text-heading-xs';
	$image_wrapper_classes[] = '@xs:basis-full @lg:basis-3/12 @5xl:basis-5/12';

	if ( ! $remove_drop_shadow ) {
		$classes[] = 'shadow-4';
	}
}
?>

<div class="@container">
	<div class="<?php echo implode( ' ', $classes ) ?>">
		<?php if ( ! $remove_drop_shadow && $member_only ) { ?>
			<div class="absolute left-1/2 top-[-16px] -translate-x-1/2 -translate-y-1/2">
				<?php get_component( 'tag', [
					'label'   => __( 'Members Only', 'wicket' ),
					'icon'    => 'fa-regular fa-lock',
					'link'    => '',
					'classes' => [ 'rounded-b-[0px] whitespace-nowrap' ],
				] ); ?>
			</div>
		<?php } ?>

		<?php if ( ! $hide_start_date_indicator && $event_day_name ) : ?>
			<div
				class="component-card-event__date-indicator <?php echo defined( 'WICKET_WP_THEME_V2' ) ? '' : 'bg-dark-100 text-white font-bold text-center uppercase flex @xs:flex-row @xl:flex-col shrink-0 justify-center items-center @xl:w-[70px] @xl:h-[70px] rounded-100 px-2 py-3 gap-2 @xl:gap-0' ?>">
				<div
					class="component-card-event__date-indicator-day <?php echo defined( 'WICKET_WP_THEME_V2' ) ? '' : 'text-heading-xs leading-none' ?>">
					<?php echo $event_day_name; ?>
				</div>
				<div
					class="component-card-event__date-indicator-date <?php echo defined( 'WICKET_WP_THEME_V2' ) ? '' : 'text-heading-sm leading-none' ?>">
					<?php echo $event_day_number; ?>
				</div>
			</div>
		<?php endif; ?>

		<div
			class="component-card-event__content-wrapper <?php echo defined( 'WICKET_WP_THEME_V2' ) ? '' : 'flex flex-col grow items-start gap-3' ?>">
			<?php if ( $event_category ) { ?>
				<div
					class="component-card-event__content-type <?php echo defined( 'WICKET_WP_THEME_V2' ) ? '' : 'text-dark-070 uppercase font-bold leading-none' ?>">
					<?php echo $event_category; ?>
				</div>
			<?php } ?>

			<?php if ( $title ) { ?>
				<a href="<?php echo $link ?>" class="<?php echo implode( ' ', $title_classes ) ?>">
					<?php echo $title; ?>

					<?php if ( $remove_drop_shadow && $member_only ) { ?>
						<?php get_component( 'tag', [
							'label'   => '',
							'icon'    => 'fa-regular fa-lock',
							'link'    => '',
							'classes' => [ 'text-body-sm' ],
						] ); ?>
					<?php } ?>

				</a>
			<?php } ?>

			<div>
				<?php if ( $event_display_date ) { ?>
					<div
						class="component-card-event__date <?php echo defined( 'WICKET_WP_THEME_V2' ) ? '' : 'text-dark-070 font-bold' ?>">
						<?php echo $event_display_date; ?>
					</div>
				<?php } ?>

				<?php if ( ! $hide_event_format_location ) { ?>
					<div
						class="component-card-event__venue-name <?php echo defined( 'WICKET_WP_THEME_V2' ) ? '' : 'text-dark-070' ?>">
						<?php if ( $is_virtual && ! $is_hybrid ) { ?>
							<span class="font-bold"><?php echo __( 'Virtual Event', 'wicket' ); ?></span>
						<?php } elseif ( $is_hybrid ) { ?>
							<span class="font-bold"><?php echo __( 'Hybrid Event', 'wicket' ); ?></span>
						<?php } elseif ( $venue_name ) { ?>
							<span class="font-bold"><?php echo $venue_name; ?></span>
							<?php if ( $venue_address ) { ?>
								<span><?php echo $venue_address; ?></span>
							<?php } ?>
						<?php } ?>
					</div>
				<?php } ?>
			</div>


			<?php if ( $excerpt ) { ?>
				<div class="component-card-event__excerpt <?php echo defined( 'WICKET_WP_THEME_V2' ) ? '' : 'leading-6' ?>">
					<?php echo $excerpt; ?>
				</div>
			<?php } ?>

			<?php

			if ( $ticket_price && ! $hide_price ) : ?>
				<div
					class="component-card-event__ticket-price <?php echo defined( 'WICKET_WP_THEME_V2' ) ? '' : 'text-dark-070' ?>">
					<span class="font-bold"><?php echo __( 'Get Tickets', 'wicket' ); ?></span>
					<span class="component-card-event__ticket-price-display">
						<?php echo wp_kses_post( $ticket_price ); ?>
					</span>
				</div>
			<?php endif; ?>

			<?php if ( $show_login_link ) {
				get_component(
					'link', [
						'default_link_style' => true,
						'url'                => get_login_url(),
						'text'               => __( 'Login to Purchase Ticket', 'woocommerce' ),
						'icon_end'           => [
							'icon' => 'fa-solid fa-arrow-up-right-from-square',
						],
					] );
			} ?>

			<?php if ( $cta && $link ) {
				get_component( 'button', [
					'variant' => $cta,
					'label'   => $cta_label ?: __( 'Read More', 'wicket' ),
					'a_tag'   => true,
					'link'    => $link,
					'classes' => [ 'component-card-event__cta' ],
				] );
			} ?>

			<?php if ( $tags ) { ?>
				<div class="card__topics">
					<?php foreach ( $tags as $tag ) { ?>
						<?php
						get_component( 'tag', [
							'label' => $tag->name,
						] ); ?>
					<?php } ?>
				</div>
			<?php } ?>
		</div>

		<?php if ( $image ) { ?>
			<div class="<?php echo implode( ' ', $image_wrapper_classes ) ?>">
				<?php get_component( 'image', [
					'id'           => $image['id'],
					'alt'          => $image['alt'],
					'aspect_ratio' => '3/2',
					'classes'      => [ 'w-full' ],
				] ); ?>
			</div>
		<?php } ?>

	</div>
</div>