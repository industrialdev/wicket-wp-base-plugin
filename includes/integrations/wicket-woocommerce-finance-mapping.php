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

	// Simple product deferral dates (defaults)
	$start = isset( $_POST['_wicket_finance_deferral_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['_wicket_finance_deferral_start_date'] ) ) : '';
	$end   = isset( $_POST['_wicket_finance_deferral_end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['_wicket_finance_deferral_end_date'] ) ) : '';

    if ( ! empty( $start ) && empty( $end ) ) {
        if ( class_exists( 'WC_Admin_Meta_Boxes' ) ) {
            WC_Admin_Meta_Boxes::add_error( __( 'Deferral dates were not updated. Please enter a valid deferral end date.', 'wicket' ) );
        }
        // Do not persist partial values
        update_post_meta( $post_id, '_wicket_finance_deferral_start_date', '' );
        update_post_meta( $post_id, '_wicket_finance_deferral_end_date', '' );
        return;
    }

    if ( ! empty( $start ) && ! empty( $end ) ) {
        $start_ts = strtotime( $start );
        $end_ts   = strtotime( $end );
        if ( $start_ts !== false && $end_ts !== false && $end_ts < $start_ts ) {
            if ( class_exists( 'WC_Admin_Meta_Boxes' ) ) {
                WC_Admin_Meta_Boxes::add_error( __( 'Deferral dates were not updated. The deferral end date must be greater than or equal to the deferral start date.', 'wicket' ) );
            }
            // Reset invalid values
            update_post_meta( $post_id, '_wicket_finance_deferral_start_date', '' );
            update_post_meta( $post_id, '_wicket_finance_deferral_end_date', '' );
            return;
        }
    }

    update_post_meta( $post_id, '_wicket_finance_deferral_start_date', $start );
    update_post_meta( $post_id, '_wicket_finance_deferral_end_date', $end );

    // Handle variation deferral dates when saving the main product
    if ( isset( $_POST['variable_post_id'] ) && is_array( $_POST['variable_post_id'] ) ) {
        foreach ( $_POST['variable_post_id'] as $index => $variation_id ) {
            if ( ! empty( $variation_id ) && is_numeric( $variation_id ) ) {
                // Get variation deferral dates from POST data
                $variation_start = isset( $_POST["_wicket_finance_deferral_start_date_{$variation_id}"] ) ? sanitize_text_field( wp_unslash( $_POST["_wicket_finance_deferral_start_date_{$variation_id}"] ) ) : '';
                $variation_end   = isset( $_POST["_wicket_finance_deferral_end_date_{$variation_id}"] ) ? sanitize_text_field( wp_unslash( $_POST["_wicket_finance_deferral_end_date_{$variation_id}"] ) ) : '';

                // Validation: if start is set, end must also be set
                if ( ! empty( $variation_start ) && empty( $variation_end ) ) {
                    if ( class_exists( 'WC_Admin_Meta_Boxes' ) ) {
                        WC_Admin_Meta_Boxes::add_error( sprintf( __( 'Deferral dates were not updated for variation #%s. Please enter a valid deferral end date.', 'wicket' ), $variation_id ) );
                    }
                    update_post_meta( $variation_id, '_wicket_finance_deferral_start_date', '' );
                    update_post_meta( $variation_id, '_wicket_finance_deferral_end_date', '' );
                    continue;
                }

                // Validation: end date must be >= start date
                if ( ! empty( $variation_start ) && ! empty( $variation_end ) ) {
                    $start_ts = strtotime( $variation_start );
                    $end_ts   = strtotime( $variation_end );
                    if ( $start_ts !== false && $end_ts !== false && $end_ts < $start_ts ) {
                        if ( class_exists( 'WC_Admin_Meta_Boxes' ) ) {
                            WC_Admin_Meta_Boxes::add_error( sprintf( __( 'Deferral dates were not updated for variation #%s. The deferral end date must be greater than or equal to the deferral start date.', 'wicket' ), $variation_id ) );
                        }
                        update_post_meta( $variation_id, '_wicket_finance_deferral_start_date', '' );
                        update_post_meta( $variation_id, '_wicket_finance_deferral_end_date', '' );
                        continue;
                    }
                }

                // Save the variation deferral dates
                update_post_meta( $variation_id, '_wicket_finance_deferral_start_date', $variation_start );
                update_post_meta( $variation_id, '_wicket_finance_deferral_end_date', $variation_end );
            }
        }
    }
}
add_action( 'woocommerce_process_product_meta', 'wicket_finance_save_product_meta' );

/**
 * Add deferral date fields to General tab for Simple products
 */
function wicket_finance_product_options_general() {
	global $post;
	if ( empty( $post ) ) {
		return;
	}

	$deferred_required = get_post_meta( $post->ID, '_wicket_finance_deferred_required', true );
	if ( $deferred_required !== 'yes' ) {
		return; // only show when deferred is required
	}

	$start = get_post_meta( $post->ID, '_wicket_finance_deferral_start_date', true );
	$end   = get_post_meta( $post->ID, '_wicket_finance_deferral_end_date', true );

	echo '<div class="options_group show_if_simple">';

	woocommerce_wp_text_input( array(
		'id'            => '_wicket_finance_deferral_start_date',
		'label'         => __( 'Deferral start date', 'wicket' ) . ':',
		'type'          => 'text',
		'class'         => 'date-picker',
		'wrapper_class' => 'form-field',
		'custom_attributes' => array(
			'pattern'   => '[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])',
			'maxlength' => '10',
		),
		'value'         => $start,
	) );

	woocommerce_wp_text_input( array(
		'id'            => '_wicket_finance_deferral_end_date',
		'label'         => __( 'Deferral end date', 'wicket' ) . ':',
		'type'          => 'text',
		'class'         => 'date-picker',
		'wrapper_class' => 'form-field',
		'custom_attributes' => array(
			'pattern'   => '[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])',
			'maxlength' => '10',
		),
		'value'         => $end,
	) );

	echo '</div>';

	// ensure Woo datepickers initialize
	echo '<script>jQuery(document.body).trigger("wc-init-datepickers");</script>';
}
add_action( 'woocommerce_product_options_general_product_data', 'wicket_finance_product_options_general', 30 );

/**
 * Variation-level deferral date fields (when deferred is required on parent)
 */
function wicket_finance_variation_options( $loop, $variation_data, $variation ) {
	$parent_id = wp_get_post_parent_id( $variation->ID );
	if ( ! $parent_id ) {
		return;
	}
	$deferred_required = get_post_meta( $parent_id, '_wicket_finance_deferred_required', true );
	if ( $deferred_required !== 'yes' ) {
		return;
	}

	$start = get_post_meta( $variation->ID, '_wicket_finance_deferral_start_date', true );
	$end   = get_post_meta( $variation->ID, '_wicket_finance_deferral_end_date', true );
	?>
	<span style="float:right;padding:4px 1em 2px 0;">
		<span class="form-field" style="display:block;font-style:italic;font-size:.7rem;">
			(<?php echo esc_html__( 'Works only if "Finance Mapping > Deferred revenue required" is enabled on the parent.', 'wicket' ); ?>)
		</span>
		<span class="form-field">
			<label for="_wicket_finance_deferral_start_date_<?php echo esc_attr( $variation->ID ); ?>"><?php echo esc_html__( 'Deferral start date:', 'wicket' ); ?></label>
			<input type="text" class="date-picker" name="_wicket_finance_deferral_start_date_<?php echo esc_attr( $variation->ID ); ?>" maxlength="10" value="<?php echo esc_attr( $start ); ?>" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])">
		</span>
		<span class="form-field">
			<label for="_wicket_finance_deferral_end_date_<?php echo esc_attr( $variation->ID ); ?>"><?php echo esc_html__( 'Deferral end date:', 'wicket' ); ?></label>
			<input type="text" class="date-picker" name="_wicket_finance_deferral_end_date_<?php echo esc_attr( $variation->ID ); ?>" maxlength="10" value="<?php echo esc_attr( $end ); ?>" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])">
		</span>
	</span>
	<span style="clear:both;display:block;"></span>
	<script>jQuery(document.body).trigger('wc-init-datepickers');</script>
	<?php
}
add_action( 'woocommerce_variation_options', 'wicket_finance_variation_options', 10, 3 );

/**
 * Save variation deferral date fields with validation
 */
function wicket_finance_save_product_variation( $variation_id, $i = null ) {
	// Safety check: ensure we have a valid variation ID
	if ( empty( $variation_id ) || ! is_numeric( $variation_id ) ) {
		return;
	}

	// Safety check: only process if we're in the right context (not during AJAX removal)
	if ( isset( $_POST['action'] ) && $_POST['action'] === 'woocommerce_remove_variations' ) {
		return;
	}

	// Get the dates from POST data - they come as _wicket_finance_deferral_start_date_{variation_id}
	$start = isset( $_POST["_wicket_finance_deferral_start_date_{$variation_id}"] ) ? sanitize_text_field( wp_unslash( $_POST["_wicket_finance_deferral_start_date_{$variation_id}"] ) ) : '';
	$end   = isset( $_POST["_wicket_finance_deferral_end_date_{$variation_id}"] ) ? sanitize_text_field( wp_unslash( $_POST["_wicket_finance_deferral_end_date_{$variation_id}"] ) ) : '';

    // Validation: if start is set, end must also be set
    if ( ! empty( $start ) && empty( $end ) ) {
        if ( class_exists( 'WC_Admin_Meta_Boxes' ) ) {
            WC_Admin_Meta_Boxes::add_error( sprintf( __( 'Deferral dates were not updated for variation #%s. Please enter a valid deferral end date.', 'wicket' ), $variation_id ) );
        }
        update_post_meta( $variation_id, '_wicket_finance_deferral_start_date', '' );
        update_post_meta( $variation_id, '_wicket_finance_deferral_end_date', '' );
        return;
    }

    // Validation: end date must be >= start date
    if ( ! empty( $start ) && ! empty( $end ) ) {
        $start_ts = strtotime( $start );
        $end_ts   = strtotime( $end );
        if ( $start_ts !== false && $end_ts !== false && $end_ts < $start_ts ) {
            if ( class_exists( 'WC_Admin_Meta_Boxes' ) ) {
                WC_Admin_Meta_Boxes::add_error( sprintf( __( 'Deferral dates were not updated for variation #%s. The deferral end date must be greater than or equal to the deferral start date.', 'wicket' ), $variation_id ) );
            }
            update_post_meta( $variation_id, '_wicket_finance_deferral_start_date', '' );
            update_post_meta( $variation_id, '_wicket_finance_deferral_end_date', '' );
            return;
        }
    }

    // Save the dates
    update_post_meta( $variation_id, '_wicket_finance_deferral_start_date', $start );
    update_post_meta( $variation_id, '_wicket_finance_deferral_end_date', $end );
}
add_action( 'woocommerce_save_product_variation', 'wicket_finance_save_product_variation', 10, 2 );


