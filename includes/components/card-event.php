<?php

$defaults                   = array(
	'classes'                    => [],
	'post_id'                    => '',
	'hide_excerpt'               => false,
	'hide_date'                  => false,
	'hide_event_category'        => false,
	'hide_event_format_location' => false,
	'hide_start_date_indicator'  => false,
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
$remove_drop_shadow         = $args['remove_drop_shadow'];
$cta                        = $args['cta'];
$cta_label                  = $args['cta_label'];
$show_tags                  = $args['show_tags'];
$tag_taxonomy               = $args['tag_taxonomy'];

$title            = get_the_title( $post_id );
$excerpt          = ! $hide_excerpt ? get_the_excerpt( $post_id ) : '';
$event_category   = ! $hide_event_category ? get_related_content_type_term( $post_id ) : '';
$event_start_date = ! $hide_date ? tribe_get_start_date( $post_id ) : '';
$link             = get_permalink( $post_id );
$image            = $args['image'];
$member_only      = is_member_only( $post_id );
$has_venue        = tribe_has_venue( $post_id );
$venue_name       = ( ! $hide_event_format_location && $has_venue ) ? tribe_get_venue( $post_id ) : '';
$venue_address    = tribe_get_full_address( $post_id );
$event_day_name   = tribe_get_start_date( $post_id, false, 'D' );
$event_day_number = tribe_get_start_date( $post_id, false, 'j' );

$ticket_price = '';
$login_link   = '';
$tags         = [];


if ( class_exists( 'Tribe__Tickets__Tickets' ) ) {
	$tickets         = Tribe__Tickets__Tickets::get_all_event_tickets( $post_id );
	$ticket_currency = tec_tickets_commerce_currency_symbol();
	$currency_output = esc_html( $ticket_currency );

	if ( $tickets ) {
		$ticket_prices = [];

		foreach ( $tickets as $ticket ) {
			$ticket_prices[] = $ticket->price;
		}

		$cheapest_ticket_price  = min( $ticket_prices );
		$expensive_ticket_price = max( $ticket_prices ) === $cheapest_ticket_price ? '' : max( $ticket_prices );

		$ticket_price = $ticket_currency . $cheapest_ticket_price . ( $expensive_ticket_price ? ' - ' . $ticket_currency . $expensive_ticket_price : '' );

		if ( ! is_user_logged_in() ) {
			$login_link = '<a href="' . get_login_url() . '"><span class="font-bold underline">' . __( 'Login to Purchase Ticket', 'woocommerce' ) . '</span> <i class="fa-solid fa-arrow-up-right-from-square"></i></a>';
		}
	}
}

if ( $show_tags && $tag_taxonomy['taxonomy'] ) {
	$tags = get_the_terms( $post_id, $tag_taxonomy['taxonomy'] );
}

$classes[]               = 'component-card-event bg-white flex flex-col gap-4 relative items-start';
$image_wrapper_classes   = [];
$title_classes           = [ 'component-card-event__title block text-dark-100 font-bold leading-7 text-heading-xs' ];
$classes[]               = 'component-card-event--events @2xl:flex-row @md:flex-row @2xl:items-start justify-between';
$image_wrapper_classes[] = 'component-card-event__image-wrapper @xs:basis-full @lg:basis-3/12 @5xl:basis-5/12';

if ( ! $remove_drop_shadow ) {
	$classes[] = 'shadow-4';
}

?>

<div class="@container">
	<div class="<?php echo implode( ' ', $classes ) ?>">
		<?php if ( $member_only ) { ?>
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
				class="component-card-event__date-indicator bg-dark-100 text-white font-bold text-center uppercase flex @xs:flex-row @xl:flex-col shrink-0 justify-center items-center @xl:w-[70px] @xl:h-[70px] rounded-100 px-2 py-3 gap-2 @xl:gap-0">
				<div class="component-card-event__date-indicator-day text-heading-xs leading-none">
					<?php echo $event_day_name; ?>
				</div>
				<div class="component-card-event__date-indicator-date text-heading-sm leading-none">
					<?php echo $event_day_number; ?>
				</div>
			</div>
		<?php endif; ?>

		<div class="component-card-event__content-wrapper flex flex-col grow items-start gap-3">
			<?php if ( $event_category ) { ?>
				<div class="component-card-event__content-type text-dark-070 uppercase font-bold leading-none">
					<?php echo $event_category; ?>
				</div>
			<?php } ?>

			<?php if ( $title ) { ?>
				<a href="<?php echo $link ?>" class="<?php echo implode( ' ', $title_classes ) ?>">
					<?php echo $title; ?>
				</a>
			<?php } ?>

			<?php if ( $event_start_date ) { ?>
				<div class="component-card-event__date text-dark-070 font-bold">
					<?php echo $event_start_date; ?>
				</div>
			<?php } ?>

			<?php if ( $venue_name ) { ?>
				<div class="component-card-event__date text-dark-070">
					<span class="font-bold"><?php echo $venue_name; ?></span>
					<?php if ( $venue_address )
						echo $venue_address ?>
					</div>
			<?php } ?>

			<?php if ( $excerpt ) { ?>
				<div class="component-card-event__excerpt leading-6">
					<?php echo $excerpt; ?>
				</div>
			<?php } ?>

			<?php if ( $ticket_price ) : ?>
				<div class="component-card-event__ticket-price text-dark-070">
					<span class="font-bold"><?php echo __( 'Get Tickets', 'wicket' ); ?></span>
					<?php echo $ticket_price; ?>
				</div>
			<?php endif; ?>

			<?php if ( $login_link ) {
				echo $login_link;
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
							'link'  => get_term_link( $tag->term_id ),
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