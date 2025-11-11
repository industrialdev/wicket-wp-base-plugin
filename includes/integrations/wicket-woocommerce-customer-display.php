<?php
/**
 * WooCommerce Customer Display Integration
 *
 * Handles customer-facing display of deferral dates on order confirmation pages,
 * emails, My Account pages, subscriptions, and PDF invoices.
 *
 * @package Wicket
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Initialize customer display functionality
 */
function wicket_finance_init_customer_display() {
	// Skip if finance system is not enabled
	if ( ! function_exists( 'wicket_is_finance_system_enabled' ) || ! wicket_is_finance_system_enabled() ) {
		return;
	}

	// Order confirmation page
	if (wicket_get_finance_option( 'wicket_finance_display_order_confirmation' ) === '1' ) {
		add_action( 'woocommerce_order_details_after_order_table', 'wicket_finance_display_order_confirmation_dates', 10, 1 );
	}

	// Email integration
	if (wicket_get_finance_option( 'wicket_finance_display_emails' ) === '1' ) {
		add_action( 'woocommerce_email_after_order_table', 'wicket_finance_display_email_dates', 10, 4 );
	}

	// My Account order details
	if (wicket_get_finance_option( 'wicket_finance_display_my_account' ) === '1' ) {
		add_action( 'woocommerce_order_details_after_order_table', 'wicket_finance_display_my_account_dates', 10, 1 );
	}

	// WooCommerce Subscriptions integration
	if (wicket_get_finance_option( 'wicket_finance_display_subscriptions' ) === '1' && function_exists( 'wcs_get_subscriptions' ) ) {
		add_action( 'woocommerce_subscription_details_after_subscription_table', 'wicket_finance_display_subscription_dates', 10, 1 );
	}

	// PDF Invoice integration
	if (wicket_get_finance_option( 'wicket_finance_display_pdf_invoices' ) === '1' ) {
		// Support for WooCommerce PDF Invoices & Packing Slips plugin
		add_action( 'wpo_wcpdf_after_order_details', 'wicket_finance_display_pdf_invoice_dates', 10, 2 );
	}
}
add_action( 'init', 'wicket_finance_init_customer_display' );

/**
 * Display deferral dates on order confirmation page
 *
 * @param WC_Order $order The order object
 */
function wicket_finance_display_order_confirmation_dates( $order ) {
	if ( ! is_wc_endpoint_url( 'order-received' ) ) {
		return;
	}

	wicket_finance_render_order_deferral_dates( $order, 'order-confirmation' );
}

/**
 * Display deferral dates in My Account order details
 *
 * @param WC_Order $order The order object
 */
function wicket_finance_display_my_account_dates( $order ) {
	if ( ! is_wc_endpoint_url( 'view-order' ) ) {
		return;
	}

	wicket_finance_render_order_deferral_dates( $order, 'my-account' );
}

/**
 * Display deferral dates in WooCommerce emails
 *
 * @param WC_Order $order The order object
 * @param bool $sent_to_admin Whether the email is being sent to admin
 * @param bool $plain_text Whether the email is in plain text format
 * @param WC_Email $email The email object
 */
function wicket_finance_display_email_dates( $order, $sent_to_admin, $plain_text, $email ) {
	// Only show to customers, not admins
	if ( $sent_to_admin ) {
		return;
	}

	// Check if this email type should show deferral dates
	$email_id = $email->id ?? '';
	$allowed_emails = array( 'customer_processing_order', 'customer_completed_order', 'customer_on_hold_order', 'customer_pending_order' );

	if ( ! in_array( $email_id, $allowed_emails ) ) {
		return;
	}

	wicket_finance_render_order_deferral_dates( $order, 'email', $plain_text );
}

/**
 * Display deferral dates in subscription details
 *
 * @param WC_Subscription $subscription The subscription object
 */
function wicket_finance_display_subscription_dates( $subscription ) {
	// Get the parent order to access line items with deferral dates
	$parent_order = $subscription->get_parent();
	if ( ! $parent_order ) {
		return;
	}

	wicket_finance_render_order_deferral_dates( $parent_order, 'subscription' );
}

/**
 * Display deferral dates in PDF invoices
 *
 * @param string $document_type The document type (invoice, packing-slip, etc.)
 * @param WC_Order $order The order object
 */
function wicket_finance_display_pdf_invoice_dates( $document_type, $order ) {
	if ( $document_type !== 'invoice' ) {
		return;
	}

	wicket_finance_render_order_deferral_dates( $order, 'pdf-invoice' );
}

/**
 * Render deferral dates for an order
 *
 * @param WC_Order $order The order object
 * @param string $context The display context (order-confirmation, email, my-account, subscription, pdf-invoice)
 * @param bool $plain_text Whether to render plain text (for emails)
 */
function wicket_finance_render_order_deferral_dates( $order, $context, $plain_text = false ) {
	$items_with_dates = wicket_finance_get_order_items_with_deferral_dates( $order );

	if ( empty( $items_with_dates ) ) {
		return;
	}

	// Get context-specific CSS class
	$css_class = 'wicket-finance-deferral-dates wicket-finance-' . $context;

	if ( $plain_text ) {
		// Plain text format for emails
		echo "\n" . __( 'Deferral Dates:', 'wicket' ) . "\n";
		echo str_repeat( '-', 20 ) . "\n";

		foreach ( $items_with_dates as $item_data ) {
			echo sprintf(
				"%s: %s - %s\n",
				$item_data['name'],
				$item_data['start_date_formatted'],
				$item_data['end_date_formatted']
			);
		}
		echo "\n";
	} else {
		// HTML format
		?>
		<div class="<?php echo esc_attr( $css_class ); ?>">
			<h3><?php esc_html_e( 'Deferral Dates', 'wicket' ); ?></h3>
			<table class="wicket-finance-dates-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Item', 'wicket' ); ?></th>
						<th><?php esc_html_e( 'Start date:', 'wicket' ); ?></th>
						<th><?php esc_html_e( 'End date:', 'wicket' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $items_with_dates as $item_data ) : ?>
						<tr>
							<td><?php echo esc_html( $item_data['name'] ); ?></td>
							<td><?php echo esc_html( $item_data['start_date_formatted'] ); ?></td>
							<td><?php echo esc_html( $item_data['end_date_formatted'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}

/**
 * Get order items that have deferral dates and are eligible for customer display
 *
 * @param WC_Order $order The order object
 * @return array Array of item data with deferral dates
 */
function wicket_finance_get_order_items_with_deferral_dates( $order ) {
	$items_with_dates = array();
	$eligible_categories = wicket_finance_get_eligible_categories();

	if ( empty( $eligible_categories ) ) {
		return $items_with_dates;
	}

	foreach ( $order->get_items() as $item_id => $item ) {
		// Check if item is eligible for customer display
		if ( ! wicket_finance_is_item_eligible_for_customer_display( $item, $eligible_categories ) ) {
			continue;
		}

		// Get deferral dates from line item meta
		$start_date = $item->get_meta( '_wicket_finance_start_date', true );
		$end_date = $item->get_meta( '_wicket_finance_end_date', true );

		// Both dates must exist
		if ( empty( $start_date ) || empty( $end_date ) ) {
			continue;
		}

		// Format dates for display
		$start_date_formatted = wicket_finance_format_date_for_display( $start_date );
		$end_date_formatted = wicket_finance_format_date_for_display( $end_date );

		$items_with_dates[] = array(
			'item_id' => $item_id,
			'name' => $item->get_name(),
			'start_date' => $start_date,
			'end_date' => $end_date,
			'start_date_formatted' => $start_date_formatted,
			'end_date_formatted' => $end_date_formatted,
		);
	}

	return $items_with_dates;
}

/**
 * Check if an order item is eligible for customer display
 *
 * @param WC_Order_Item_Product $item The order line item
 * @param array $eligible_categories Array of eligible product category slugs
 * @return bool True if item is eligible for display
 */
function wicket_finance_is_item_eligible_for_customer_display( $item, $eligible_categories ) {
	// Get product ID (use parent for variations)
	$product_id = $item->get_variation_id() !== 0 ? $item->get_variation_id() : $item->get_product_id();
	$parent_product_id = $item->get_variation_id() !== 0 ? $item->get_product_id() : $product_id;

	// Check if product is in eligible categories
	$product_categories = wp_get_post_terms( $parent_product_id, 'product_cat', array( 'fields' => 'ids' ) );

	if ( is_wp_error( $product_categories ) || empty( $product_categories ) ) {
		return false;
	}

	// Check if any product category is in the eligible list
	$category_match = array_intersect( $product_categories, $eligible_categories );

	return ! empty( $category_match );
}

/**
 * Get eligible product categories for customer display
 *
 * @return array Array of product category slugs
 */
function wicket_finance_get_eligible_categories() {
	$categories = wicket_get_finance_option( 'wicket_finance_customer_visible_categories', array() );

	if ( ! is_array( $categories ) ) {
		return array();
	}

	return $categories;
}

/**
 * Format a date for customer display using WordPress date format
 *
 * @param string $date Date in Y-m-d format
 * @return string Formatted date string
 */
function wicket_finance_format_date_for_display( $date ) {
	if ( empty( $date ) ) {
		return '';
	}

	// Convert to timestamp and format using WordPress date format
	$timestamp = strtotime( $date );
	if ( $timestamp === false ) {
		return $date; // Return original if conversion fails
	}

	return date_i18n( get_option( 'date_format' ), $timestamp );
}

/**
 * Add basic CSS styles for deferral dates display
 */
function wicket_finance_add_customer_display_styles() {
	if ( ! function_exists( 'wicket_is_finance_system_enabled' ) || ! wicket_is_finance_system_enabled() ) {
		return;
	}

	// Only add styles on relevant pages
	if ( ! is_wc_endpoint_url() && ! is_account_page() && ! is_order_received_page() ) {
		return;
	}

	?>
	<style type="text/css">
		.wicket-finance-deferral-dates {
			margin: 20px 0;
			padding: 15px;
			background-color: #f8f9fa;
			border: 1px solid #dee2e6;
			border-radius: 4px;
		}

		.wicket-finance-deferral-dates h3 {
			margin: 0 0 15px 0;
			font-size: 1.2em;
			color: #495057;
		}

		.wicket-finance-dates-table {
			width: 100%;
			border-collapse: collapse;
		}

		.wicket-finance-dates-table th,
		.wicket-finance-dates-table td {
			padding: 8px 12px;
			text-align: left;
			border-bottom: 1px solid #dee2e6;
		}

		.wicket-finance-dates-table th {
			background-color: #e9ecef;
			font-weight: 600;
		}

		.wicket-finance-dates-table tbody tr:last-child td {
			border-bottom: none;
		}

		/* PDF-specific styles */
		.wicket-finance-pdf-invoice .wicket-finance-dates-table {
			font-size: 12px;
		}

		.wicket-finance-pdf-invoice .wicket-finance-dates-table th,
		.wicket-finance-pdf-invoice .wicket-finance-dates-table td {
			padding: 6px 8px;
		}
	</style>
	<?php
}
add_action( 'wp_head', 'wicket_finance_add_customer_display_styles' );
