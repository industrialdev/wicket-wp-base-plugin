<?php

// No direct access
defined('ABSPATH') || exit;

/**
 * Finance Mapping – Product admin tab scaffolding
 *
 * Subtask 2.1: Finance Mapping Tab Creation
 * - Add "Finance Mapping" tab within "Product data" metabox
 * - Applies to ALL product types
 * - For Variable products: tab appears at parent level only (not per variation)
 */

/**
 * Add Finance Mapping tab to the WooCommerce product data tabs
 *
 * @param array $tabs Existing tabs
 * @return array
 */
function wicket_finance_add_product_data_tab( $tabs ) {
	$tabs['wicket_finance_mapping'] = array(
		'label'  => __( 'Finance Mapping', 'wicket' ),
		'target' => 'wicket_finance_mapping_product',
		'class'  => array(
			// Show on common product types at the parent (product) level
			'show_if_simple',
			'show_if_variable',
			'show_if_grouped',
			'show_if_external',
			// If Woo Subscriptions is active these classes are respected
			'show_if_subscription',
			'show_if_variable-subscription',
		),
	);

	return $tabs;
}
add_filter( 'woocommerce_product_data_tabs', 'wicket_finance_add_product_data_tab', 90 );

/**
 * Output Finance Mapping tab panel (empty scaffold for now)
 */
function wicket_finance_add_product_data_panel() {
	?>
	<div id="wicket_finance_mapping_product" class="panel woocommerce_options_panel">
		<div class="options_group">
			<?php
			// GL Code field
			woocommerce_wp_text_input( array(
				'id'          => '_wicket_finance_gl_code',
				'label'       => __( 'GL Code', 'wicket' ),
				'type'        => 'text',
				'description' => sprintf( '<em>%s</em>', __( 'GL mapping from your financial management system.', 'wicket' ) ),
			) );

			// Deferred revenue required
			woocommerce_wp_checkbox( array(
				'id'          => '_wicket_finance_deferred_required',
				'label'       => __( 'Deferred revenue required', 'wicket' ),
				'description' => sprintf( '<em>%s</em>', __( 'Select if this product will use a deferred revenue schedule in your your financial management system.', 'wicket' ) ),
				'wrapper_class' => 'wicket-finance-deferred-required',
			) );
			?>
		</div>
	</div>
	<?php
}
add_action( 'woocommerce_product_data_panels', 'wicket_finance_add_product_data_panel' );

/**
 * Save Finance Mapping fields
 */
function wicket_finance_save_product_meta( $post_id ) {
	$gl_code = isset( $_POST['_wicket_finance_gl_code'] ) ? sanitize_text_field( wp_unslash( $_POST['_wicket_finance_gl_code'] ) ) : '';
	update_post_meta( $post_id, '_wicket_finance_gl_code', $gl_code );

	$deferred_required = isset( $_POST['_wicket_finance_deferred_required'] ) ? 'yes' : 'no';
	update_post_meta( $post_id, '_wicket_finance_deferred_required', $deferred_required );
}
add_action( 'woocommerce_process_product_meta', 'wicket_finance_save_product_meta' );


