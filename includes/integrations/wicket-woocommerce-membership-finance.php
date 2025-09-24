<?php
/**
 * WooCommerce Membership Finance Integration
 *
 * Handles dynamic deferral dates for membership products based on order status triggers
 * and membership plugin integration.
 *
 * @package Wicket
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hook into WooCommerce order status changes to trigger membership deferral date writing
 */
function wicket_finance_order_status_changed( $order_id, $old_status, $new_status, $order ) {
	// Skip if finance system is not enabled
	if ( ! function_exists( 'wicket_is_finance_system_enabled' ) || ! wicket_is_finance_system_enabled() ) {
		return;
	}

	// Get configured trigger statuses from admin settings
	$trigger_statuses = wicket_finance_get_trigger_statuses();

	// Always trigger on processing status (regardless of config)
	if ( $new_status === 'processing' || in_array( $new_status, $trigger_statuses ) ) {
		wicket_finance_write_membership_dates( $order );
	}
}
add_action( 'woocommerce_order_status_changed', 'wicket_finance_order_status_changed', 10, 4 );

/**
 * Get configured order status triggers from admin settings
 *
 * @return array Array of order status slugs that should trigger membership date writing
 */
function wicket_finance_get_trigger_statuses() {
	$triggers = array();

	// Check each possible trigger status
	$possible_triggers = array( 'draft', 'pending', 'on-hold', 'completed' );

	foreach ( $possible_triggers as $status ) {
		$option_key = 'wicket_finance_trigger_' . str_replace( '-', '_', $status );
		if ( get_option( $option_key ) === '1' ) {
			$triggers[] = $status;
		}
	}

	// Always include processing (mandatory)
	if ( ! in_array( 'processing', $triggers ) ) {
		$triggers[] = 'processing';
	}

	return $triggers;
}

/**
 * Write membership dates to order line items
 *
 * @param WC_Order $order The order object
 */
function wicket_finance_write_membership_dates( $order ) {
	if ( ! $order ) {
		return;
	}

	$items = $order->get_items();

	foreach ( $items as $item_id => $item ) {
		// Check if this line item is for a membership product with deferral required
		if ( wicket_finance_is_membership_line_item( $item ) ) {
			wicket_finance_process_membership_line_item( $order, $item_id, $item );
		}
	}
}

/**
 * Check if a line item is for a membership product that requires deferral
 *
 * @param WC_Order_Item_Product $item The order line item
 * @return bool True if this is a membership line item requiring deferral
 */
function wicket_finance_is_membership_line_item( $item ) {
	// Get variation ID if it's a variation, otherwise get product ID
	$product_id = $item->get_variation_id() !== 0 ? $item->get_variation_id() : $item->get_product_id();
	$parent_product_id = $item->get_variation_id() !== 0 ? $item->get_product_id() : $product_id;

	// Check if the parent product has "Deferred revenue required" enabled
	$deferred_required = get_post_meta( $parent_product_id, '_wicket_finance_deferred_required', true );

	if ( $deferred_required !== 'yes' ) {
		return false;
	}

	// Check if product is in the "membership" category
	$categories = wp_get_post_terms( $parent_product_id, 'product_cat', array( 'fields' => 'slugs' ) );
	if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
		if ( in_array( 'membership', $categories, true ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Process a membership line item and write deferral dates
 *
 * @param WC_Order $order The order object
 * @param int $item_id The order item ID
 * @param WC_Order_Item_Product $item The order line item
 */
function wicket_finance_process_membership_line_item( $order, $item_id, $item ) {
	// Get current dates from line item meta
	$current_start = $item->get_meta( '_wicket_finance_start_date', true );
	$current_end = $item->get_meta( '_wicket_finance_end_date', true );

	// Try to get membership dates
	$membership_dates = wicket_finance_get_membership_dates( $order, $item );

	if ( empty( $membership_dates ) ) {
		// If we can't get membership dates yet, try to use product defaults
		$membership_dates = wicket_finance_get_product_default_dates( $item );

		if ( empty( $membership_dates ) ) {
			return; // No dates available
		}
	}

	$new_start = $membership_dates['start_date'];
	$new_end   = $membership_dates['end_date'];

	// Only update if dates have changed
	if ( $current_start !== $new_start || $current_end !== $new_end ) {
		// Update line item meta
		wc_update_order_item_meta( $item_id, '_wicket_finance_start_date', $new_start );
		wc_update_order_item_meta( $item_id, '_wicket_finance_end_date', $new_end );

		// Also update GL Code if available from product
		$product_id = $item->get_variation_id() !== 0 ? $item->get_variation_id() : $item->get_product_id();
		$parent_product_id = $item->get_variation_id() !== 0 ? $item->get_product_id() : $product_id;
		$gl_code = get_post_meta( $parent_product_id, '_wicket_finance_gl_code', true );

		if ( ! empty( $gl_code ) ) {
			wc_update_order_item_meta( $item_id, '_wicket_finance_gl_code', $gl_code );
		}

		// Add order note about the change
		$note = sprintf(
			__( 'Dynamic deferral dates updated for line item "%s": Start date %s → %s, End date %s → %s', 'wicket' ),
			$item->get_name(),
			$current_start ?: __( '(empty)', 'wicket' ),
			$new_start ?: __( '(empty)', 'wicket' ),
			$current_end ?: __( '(empty)', 'wicket' ),
			$new_end ?: __( '(empty)', 'wicket' )
		);
		$order->add_order_note( $note );
	}
}

/**
 * Get membership dates from membership plugin or order context
 *
 * @param WC_Order $order The order object
 * @param WC_Order_Item_Product $item The order line item
 * @return array|null Array with 'start_date' and 'end_date' keys, or null if not available
 */
function wicket_finance_get_membership_dates( $order, $item ) {
	// First, try to get dates from existing membership record if it exists
	$membership_uuid = $item->get_meta( '_membership_wicket_uuid', true );

	if ( ! empty( $membership_uuid ) ) {
		// If we have a membership UUID, try to find the membership record
		$membership_posts = get_posts( array(
			'post_type' => 'wicket_membership',
			'meta_key' => 'membership_wicket_uuid',
			'meta_value' => $membership_uuid,
			'posts_per_page' => 1,
		) );

		if ( ! empty( $membership_posts ) ) {
			$membership_post = $membership_posts[0];
			$membership_starts_at = get_post_meta( $membership_post->ID, 'membership_starts_at', true );
			$membership_ends_at = get_post_meta( $membership_post->ID, 'membership_ends_at', true );

			if ( ! empty( $membership_starts_at ) && ! empty( $membership_ends_at ) ) {
				// Convert from datetime format to Y-m-d format
				$start_date = ( new DateTime( $membership_starts_at ) )->format('Y-m-d');
				$end_date = ( new DateTime( $membership_ends_at ) )->format('Y-m-d');

				return array(
					'start_date' => $start_date,
					'end_date' => $end_date,
				);
			}
		}
	}

	// For early order statuses (draft, pending, on-hold), membership record may not exist yet
	// In these cases, we'll rely on the membership plugin hooks to write the dates
	// when the membership is actually created

	return null;
}

/**
 * Get default deferral dates from product settings
 *
 * @param WC_Order_Item_Product $item The order line item
 * @return array|null Array with 'start_date' and 'end_date' keys, or null if not available
 */
function wicket_finance_get_product_default_dates( $item ) {
	$product_id = $item->get_variation_id() !== 0 ? $item->get_variation_id() : $item->get_product_id();

	$start_date = get_post_meta( $product_id, '_wicket_finance_deferral_start_date', true );
	$end_date = get_post_meta( $product_id, '_wicket_finance_deferral_end_date', true );

	if ( empty( $start_date ) || empty( $end_date ) ) {
		return null;
	}

	return array(
		'start_date' => $start_date,
		'end_date'   => $end_date,
	);
}

/**
 * Hook into membership creation to write deferral dates
 * This integrates with the wicket-wp-memberships plugin to automatically
 * populate line item deferral dates with actual membership term dates
 */
function wicket_finance_member_create_record( $membership ) {
	$order = wc_get_order( $membership['membership_parent_order_id'] );

	if ( $order == false ) {
		return;
	}

	$items = $order->get_items();

	foreach ( $items as $item ) {
		$item_id = $item->get_id();

		// Get variation ID if it's a variation, otherwise get product ID
		$product_id = $item->get_variation_id() !== 0 ? $item->get_variation_id() : $item->get_product_id();

		if ( $product_id == $membership['membership_product_id'] ) {
			// Get current dates
			$current_start = $item->get_meta( '_wicket_finance_start_date', true );
			$current_end = $item->get_meta( '_wicket_finance_end_date', true );

			// Convert membership dates to Y-m-d format
			$membership_start_date = ( new DateTime( $membership['membership_starts_at'] ) )->format('Y-m-d');
			$membership_end_date = ( new DateTime( $membership['membership_ends_at'] ) )->format('Y-m-d');

			// Update line item meta
			wc_update_order_item_meta( $item_id, '_wicket_finance_start_date', $membership_start_date );
			wc_update_order_item_meta( $item_id, '_wicket_finance_end_date', $membership_end_date );

			// Also update GL Code if available from product
			$parent_product_id = $item->get_variation_id() !== 0 ? $item->get_product_id() : $product_id;
			$gl_code = get_post_meta( $parent_product_id, '_wicket_finance_gl_code', true );

			if ( ! empty( $gl_code ) ) {
				wc_update_order_item_meta( $item_id, '_wicket_finance_gl_code', $gl_code );
			}

			// Add order note about the membership date update
			$note = sprintf(
				__( 'Membership deferral dates set for line item "%s": Start date %s → %s, End date %s → %s', 'wicket' ),
				$item->get_name(),
				$current_start ?: __( '(empty)', 'wicket' ),
				$membership_start_date,
				$current_end ?: __( '(empty)', 'wicket' ),
				$membership_end_date
			);
			$order->add_order_note( $note );
		}
	}
}
add_action( 'wicket_member_create_record', 'wicket_finance_member_create_record', 10, 1 );

/**
 * Hook into membership creation (MDP) to add membership UUID
 * This replaces the existing ASAE-specific implementation
 */
function wicket_finance_member_order_uuid( $membership ) {
	$order = wc_get_order( $membership['membership_parent_order_id'] );

	if ( $order == false ) {
		return;
	}

	$items = $order->get_items();

	foreach ( $items as $item ) {
		$item_id = $item->get_id();

		// Get variation ID if it's a variation, otherwise get product ID
		$product_id = $item->get_variation_id() !== 0 ? $item->get_variation_id() : $item->get_product_id();

		if ( $product_id == $membership['membership_product_id'] ) {
			// Add wicket membership uuid to the order meta
			wc_update_order_item_meta( $item_id, '_membership_wicket_uuid', $membership['membership_wicket_uuid'] );
		}
	}
}
add_action( 'wicket_membership_created_mdp', 'wicket_finance_member_order_uuid', 10, 1 );
