<?php
/**
 * Finance Settings for Wicket Base Plugin
 *
 * @package  Wicket\Settings
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Add Finance tab and settings to Wicket Settings
 *
 * @param WPSettings $settings The settings instance
 */
function wicket_add_finance_settings( $settings ) {

	// Only add Finance tab if WooCommerce is active
	if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
		return;
	}

	/*
	 * Finance Tab
	 */
	$finance_tab = $settings->add_tab( __( 'Finance', 'wicket' ), 'finance' );

	/*
	 * Revenue Deferral Dates — Feature Control
	 * (WPSettings does not support nested sections, so we add separate sections under the tab)
	 */
	$feature_control_section = $finance_tab->add_section( __( 'Revenue Deferral Dates — Feature Control', 'wicket' ) );

	$feature_control_section->add_option( 'checkbox', [
		'id'          => 'wicket_finance_enable_system',
		'label'       => __( 'Enable Finance Mapping System', 'wicket' ),
		'description' => __( 'Enable the entire finance mapping and deferral dates system.', 'wicket' ),
		'default'     => '0',
	] );

	$feature_control_section->add_option( 'checkbox', [
		'id'          => 'wicket_finance_enable_deferral_dates',
		'label'       => __( 'Enable Deferral Date Functionality', 'wicket' ),
		'description' => __( 'Enable deferral date fields and functionality for products and orders.', 'wicket' ),
		'default'     => '1',
	] );

	$feature_control_section->add_option( 'checkbox', [
		'id'          => 'wicket_finance_enable_lms_integration',
		'label'       => __( 'Enable LMS Course Integration', 'wicket' ),
		'description' => __( 'Enable LMS course data fields for products.', 'wicket' ),
		'default'     => '0',
	] );

	$feature_control_section->add_option( 'checkbox', [
		'id'          => 'wicket_finance_enable_customer_display',
		'label'       => __( 'Enable Customer-Facing Display Options', 'wicket' ),
		'description' => __( 'Allow deferral dates to be displayed to customers on various surfaces.', 'wicket' ),
		'default'     => '0',
	] );

	/*
	 * Revenue Deferral Dates — Customer Visibility
	 */
	$customer_visibility_section = $finance_tab->add_section( __( 'Revenue Deferral Dates — Customer Visibility', 'wicket' ) );

	// Get product categories for multi-select
	$product_categories = [];
	if ( function_exists( 'get_terms' ) && taxonomy_exists( 'product_cat' ) ) {
		$categories = get_terms( [
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
		] );

		if ( ! is_wp_error( $categories ) ) {
			foreach ( $categories as $category ) {
				$product_categories[ $category->term_id ] = $category->name;
			}
		}
	}

	$customer_visibility_section->add_option( 'select-multiple', [
		'name'        => 'wicket_finance_customer_visible_categories',
		'label'       => __( 'Product Categories for Customer Display', 'wicket' ),
		'description' => __( 'Select product categories that should display deferral dates to customers. Only products in these categories will show deferral dates on customer-facing surfaces.', 'wicket' ),
		'options'     => $product_categories,
		'default'     => [],
	] );

	$customer_visibility_section->add_option( 'checkbox', [
		'name'        => 'wicket_finance_display_order_confirmation',
		'label'       => __( 'Order Confirmation Page', 'wicket' ),
		'description' => __( 'Display deferral dates on the order confirmation page.', 'wicket' ),
		'default'     => '0',
	] );

	$customer_visibility_section->add_option( 'checkbox', [
		'name'        => 'wicket_finance_display_emails',
		'label'       => __( 'Email Notifications', 'wicket' ),
		'description' => __( 'Display deferral dates in email notifications (Pending payment, On hold, Processing, Completed, Renewal).', 'wicket' ),
		'default'     => '0',
	] );

	$customer_visibility_section->add_option( 'checkbox', [
		'name'        => 'wicket_finance_display_my_account',
		'label'       => __( 'My Account › Orders', 'wicket' ),
		'description' => __( 'Display deferral dates in the My Account order details view.', 'wicket' ),
		'default'     => '0',
	] );

	// Only show subscriptions option if WooCommerce Subscriptions is active
	if ( is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' ) ) {
		$customer_visibility_section->add_option( 'checkbox', [
			'name'        => 'wicket_finance_display_subscriptions',
			'label'       => __( 'Subscriptions Details', 'wicket' ),
			'description' => __( 'Display deferral dates in subscription details (WooCommerce Subscriptions required).', 'wicket' ),
			'default'     => '0',
		] );
	}

	// Check for supported invoice plugins and add option if found
	$invoice_plugins_active    = false;
	$supported_invoice_plugins = [
		'woocommerce-pdf-invoices-packing-slips/woocommerce-pdf-invoices-packingslips.php',
		'woocommerce-pdf-invoice/woocommerce-pdf-invoice.php',
	];

	foreach ( $supported_invoice_plugins as $plugin ) {
		if ( is_plugin_active( $plugin ) ) {
			$invoice_plugins_active = true;
			break;
		}
	}

	if ( $invoice_plugins_active ) {
		$customer_visibility_section->add_option( 'checkbox', [
			'name'        => 'wicket_finance_display_pdf_invoices',
			'label'       => __( 'PDF Invoices', 'wicket' ),
			'description' => __( 'Display deferral dates in PDF invoices (supported invoice plugin required).', 'wicket' ),
			'default'     => '0',
		] );
	}

	/*
	 * Revenue Deferral Dates — Dynamic Deferral Dates Trigger
	 */
	$dynamic_trigger_section = $finance_tab->add_section( __( 'Revenue Deferral Dates — Dynamic Deferral Dates Trigger', 'wicket' ) );

	$dynamic_trigger_section->add_option( 'text', [
		'name'   => 'wicket_finance_dynamic_trigger_help',
		'render' => function () {
			return '<p><em>' . __( 'Determines the WooCommerce order status that triggers dynamic deferral dates to be written. Regardless of this setting, dates will always be written when the order reaches "Processing" status.', 'wicket' ) . '</em></p>';
		},
	] );

	$dynamic_trigger_section->add_option( 'checkbox', [
		'name'        => 'wicket_finance_trigger_draft',
		'label'       => __( 'Draft', 'wicket' ),
		'description' => __( 'Write dynamic deferral dates when order status changes to Draft.', 'wicket' ),
		'default'     => '0',
	] );

	$dynamic_trigger_section->add_option( 'checkbox', [
		'name'        => 'wicket_finance_trigger_pending',
		'label'       => __( 'Pending Payment', 'wicket' ),
		'description' => __( 'Write dynamic deferral dates when order status changes to Pending Payment.', 'wicket' ),
		'default'     => '0',
	] );

	$dynamic_trigger_section->add_option( 'checkbox', [
		'name'        => 'wicket_finance_trigger_on_hold',
		'label'       => __( 'On Hold', 'wicket' ),
		'description' => __( 'Write dynamic deferral dates when order status changes to On Hold.', 'wicket' ),
		'default'     => '0',
	] );

	$dynamic_trigger_section->add_option( 'checkbox', [
		'name'        => 'wicket_finance_trigger_processing',
		'label'       => __( 'Processing (Required)', 'wicket' ),
		'description' => __( 'Write dynamic deferral dates when order status changes to Processing. This option is always enabled and cannot be disabled.', 'wicket' ),
		'default'     => '1',
		'attributes'  => [
			'disabled' => 'disabled',
			'checked'  => 'checked',
		],
	] );

	$dynamic_trigger_section->add_option( 'checkbox', [
		'name'        => 'wicket_finance_trigger_completed',
		'label'       => __( 'Completed', 'wicket' ),
		'description' => __( 'Write dynamic deferral dates when order status changes to Completed.', 'wicket' ),
		'default'     => '0',
	] );

}

/**
 * Helper function to get finance setting value
 *
 * @param string $option_name The option name
 * @param mixed  $default     Default value if option doesn't exist
 * @return mixed The option value
 */
function wicket_get_finance_option( $option_name, $default = null ) {
	return wicket_get_option( $option_name, $default );
}

/**
 * Helper function to get parsed organizations as array
 *
 * @return array Array of organizations in format ['slug' => 'Display Name']
 */
function wicket_get_finance_organizations() {
	$organizations_text = wicket_get_finance_option( 'wicket_finance_organizations', '' );
	$organizations      = [];

	if ( ! empty( $organizations_text ) ) {
		$lines = explode( "\n", $organizations_text );
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( ! empty( $line ) && strpos( $line, '|' ) !== false ) {
				list( $slug, $name )            = explode( '|', $line, 2 );
				$organizations[ trim( $slug ) ] = trim( $name );
			}
		}
	}

	return $organizations;
}

/**
 * Helper function to get parsed delivery vendors as array
 *
 * @return array Array of vendors in format ['slug' => 'Display Name']
 */
function wicket_get_finance_delivery_vendors() {
	$vendors_text = wicket_get_finance_option( 'wicket_finance_delivery_vendors', '' );
	$vendors      = [];

	if ( ! empty( $vendors_text ) ) {
		$lines = explode( "\n", $vendors_text );
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( ! empty( $line ) && strpos( $line, '|' ) !== false ) {
				list( $slug, $name )      = explode( '|', $line, 2 );
				$vendors[ trim( $slug ) ] = trim( $name );
			}
		}
	}

	return $vendors;
}

/**
 * Helper function to get parsed sales representatives as array
 *
 * @return array Array of sales reps in format ['code' => 'Display Name']
 */
function wicket_get_finance_sales_reps() {
	$sales_reps_text = wicket_get_finance_option( 'wicket_finance_sales_reps', '' );
	$sales_reps      = [];

	if ( ! empty( $sales_reps_text ) ) {
		$lines = explode( "\n", $sales_reps_text );
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( ! empty( $line ) && strpos( $line, '|' ) !== false ) {
				list( $code, $name )         = explode( '|', $line, 2 );
				$sales_reps[ trim( $code ) ] = trim( $name );
			}
		}
	}

	return $sales_reps;
}

/**
 * Check if finance system is enabled
 *
 * @return bool True if enabled, false otherwise
 */
function wicket_is_finance_system_enabled() {
	return wicket_get_finance_option( 'wicket_finance_enable_system', '0' ) === '1';
}

/**
 * Check if deferral dates functionality is enabled
 *
 * @return bool True if enabled, false otherwise
 */
function wicket_is_deferral_dates_enabled() {
	return wicket_get_finance_option( 'wicket_finance_enable_deferral_dates', '1' ) === '1';
}

/**
 * Check if LMS integration is enabled
 *
 * @return bool True if enabled, false otherwise
 */
function wicket_is_lms_integration_enabled() {
	return wicket_get_finance_option( 'wicket_finance_enable_lms_integration', '0' ) === '1';
}

/**
 * Check if customer-facing display is enabled
 *
 * @return bool True if enabled, false otherwise
 */
function wicket_is_customer_display_enabled() {
	return wicket_get_finance_option( 'wicket_finance_enable_customer_display', '0' ) === '1';
}

/**
 * Get array of order statuses that should trigger dynamic deferral dates
 *
 * @return array Array of order status slugs
 */
function wicket_get_dynamic_deferral_triggers() {
	$triggers = [ 'processing' ]; // Always include processing

	if ( wicket_get_finance_option( 'wicket_finance_trigger_draft', '0' ) === '1' ) {
		$triggers[] = 'draft';
	}

	if ( wicket_get_finance_option( 'wicket_finance_trigger_pending', '0' ) === '1' ) {
		$triggers[] = 'pending';
	}

	if ( wicket_get_finance_option( 'wicket_finance_trigger_on_hold', '0' ) === '1' ) {
		$triggers[] = 'on-hold';
	}

	if ( wicket_get_finance_option( 'wicket_finance_trigger_completed', '0' ) === '1' ) {
		$triggers[] = 'completed';
	}

	return $triggers;
}

/**
 * Check if a product category is eligible for customer-facing deferral date display
 *
 * @param int|array $product_categories Product category ID(s) to check
 * @return bool True if eligible, false otherwise
 */
function wicket_is_product_category_eligible_for_customer_display( $product_categories ) {
	if ( ! wicket_is_customer_display_enabled() ) {
		return false;
	}

	$eligible_categories = wicket_get_finance_option( 'wicket_finance_customer_visible_categories', [] );

	if ( empty( $eligible_categories ) ) {
		return false;
	}

	// Ensure we have an array
	if ( ! is_array( $product_categories ) ) {
		$product_categories = [ $product_categories ];
	}

	// Check if any of the product categories are in the eligible list
	foreach ( $product_categories as $category_id ) {
		if ( in_array( $category_id, $eligible_categories ) ) {
			return true;
		}
	}

	return false;
}
