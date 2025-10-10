<?php

// No direct access
defined('ABSPATH') || exit;

/**
 * Finance Mapping – Order Line Item Management
 *
 * Subtask 3: Order Line Item Data - Admin Order Management
 * - Add Start/End date inputs on order line items for products that require deferral
 * - Auto-populate from product defaults but allow independent editing
 * - Validate dates and save as order item meta
 * - Track changes with order notes
 */

/**
 * Add deferral date fields to order line items in admin
 * Only show for products with "Deferred revenue required" checked
 */
function wicket_finance_order_item_meta_fields($item_id, $item, $product)
{
	// Safety check: ensure we have a valid product
	if (!$product || !is_a($product, 'WC_Product')) {
		return;
	}

	// Get the parent product ID for variations
	$product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();

	// Check if deferred revenue is required for this product
	$deferred_required = get_post_meta($product_id, '_wicket_finance_deferred_required', true);
	if ($deferred_required !== 'yes') {
		return;  // Only show for products that require deferred revenue
	}

	// Get current values from order item meta
	$start_date = $item->get_meta('_wicket_finance_start_date', true);
	$end_date = $item->get_meta('_wicket_finance_end_date', true);
	$gl_code = $item->get_meta('_wicket_finance_gl_code', true);

	// If no values exist, try to auto-populate from product defaults
	if (empty($start_date) && empty($end_date)) {
		// For variations, get dates from the variation, otherwise from the main product
		$date_source_id = $product->get_parent_id() ? $product->get_id() : $product_id;
		$product_start = get_post_meta($date_source_id, '_wicket_finance_deferral_start_date', true);
		$product_end = get_post_meta($date_source_id, '_wicket_finance_deferral_end_date', true);

		$start_date = $product_start;
		$end_date = $product_end;
	}

	// If no GL code exists, get from product
	if (empty($gl_code)) {
		$gl_code = get_post_meta($product_id, '_wicket_finance_gl_code', true);
	}

	// Normalize dates to Y-m-d for HTML date inputs (consistent with product general info fields)
	$start_date_attr = '';
	$end_date_attr = '';
	if (!empty($start_date)) {
		$start_ts = strtotime($start_date);
		$start_date_attr = $start_ts ? date('Y-m-d', $start_ts) : '';
	}
	if (!empty($end_date)) {
		$end_ts = strtotime($end_date);
		$end_date_attr = $end_ts ? date('Y-m-d', $end_ts) : '';
	}

	?>
	<div class="wicket-finance-order-item-meta" style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd;">
		<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #555;">
			<?php _e('Finance & Deferral Dates', 'wicket'); ?>
		</h4>

		<div style="display: flex; gap: 15px; align-items: center; margin-bottom: 5px;">
			<div style="flex: 1;">
				<label for="wicket_finance_start_date_<?php echo esc_attr($item_id); ?>" style="display: block; font-weight: 600; margin-bottom: 3px;">
					<?php _e('Start date:', 'wicket'); ?>
				</label>
				<input
					type="text"
					class="wicket-date-picker date-picker"
					data-date-format="yy-mm-dd"
					placeholder="YYYY-MM-DD"
					id="wicket_finance_start_date_<?php echo esc_attr($item_id); ?>"
					name="wicket_finance_start_date[<?php echo esc_attr($item_id); ?>]"
					value="<?php echo esc_attr($start_date_attr); ?>"
					style="width: 100%;"
				/>
			</div>

			<div style="flex: 1;">
				<label for="wicket_finance_end_date_<?php echo esc_attr($item_id); ?>" style="display: block; font-weight: 600; margin-bottom: 3px;">
					<?php _e('End date:', 'wicket'); ?>
				</label>
				<input
					type="text"
					class="wicket-date-picker date-picker"
					data-date-format="yy-mm-dd"
					placeholder="YYYY-MM-DD"
					id="wicket_finance_end_date_<?php echo esc_attr($item_id); ?>"
					name="wicket_finance_end_date[<?php echo esc_attr($item_id); ?>]"
					value="<?php echo esc_attr($end_date_attr); ?>"
					style="width: 100%;"
				/>
			</div>
		</div>

		<?php if (!empty($gl_code)): ?>
		<div style="margin-top: 8px; font-size: 12px; color: #666;">
			<strong><?php _e('GL Code:', 'wicket'); ?></strong> <?php echo esc_html($gl_code); ?>
			<input type="hidden" name="wicket_finance_gl_code[<?php echo esc_attr($item_id); ?>]" value="<?php echo esc_attr($gl_code); ?>" />
		</div>
		<?php endif; ?>
	</div>
	<?php
}

add_action('woocommerce_before_order_itemmeta', 'wicket_finance_order_item_meta_fields', 10, 3);

/**
 * Save order item meta when order is saved
 */
function wicket_finance_save_order_item_meta($order_id, $items)
{
	// Safety check
	if (!$order_id || empty($items)) {
		return;
	}

	// Process each item
	foreach ($items as $item_id => $item) {
		// Get the submitted values
		$start_date = isset($_POST['wicket_finance_start_date'][$item_id]) ? sanitize_text_field(wp_unslash($_POST['wicket_finance_start_date'][$item_id])) : '';
		$end_date = isset($_POST['wicket_finance_end_date'][$item_id]) ? sanitize_text_field(wp_unslash($_POST['wicket_finance_end_date'][$item_id])) : '';
		$gl_code = isset($_POST['wicket_finance_gl_code'][$item_id]) ? sanitize_text_field(wp_unslash($_POST['wicket_finance_gl_code'][$item_id])) : '';

		// Get the order item object
		$order_item = WC_Order_Factory::get_order_item($item_id);
		if (!$order_item) {
			continue;
		}

		// Basic validation: if start date is provided, end date is required
		if (!empty($start_date) && empty($end_date)) {
			wc_add_notice(sprintf(__('End date is required when start date is provided for item: %s', 'wicket'), $order_item->get_name()), 'error');
			continue;
		}

		// Date validation: end date must be >= start date
		if (!empty($start_date) && !empty($end_date)) {
			$start_ts = strtotime($start_date);
			$end_ts = strtotime($end_date);
			if ($start_ts !== false && $end_ts !== false && $end_ts < $start_ts) {
				wc_add_notice(sprintf(__('End date must be greater than or equal to start date for item: %s', 'wicket'), $order_item->get_name()), 'error');
				continue;
			}
		}

		// Get current values to track changes
		$old_start = $order_item->get_meta('_wicket_finance_start_date', true);
		$old_end = $order_item->get_meta('_wicket_finance_end_date', true);
		$old_gl = $order_item->get_meta('_wicket_finance_gl_code', true);

		// Save the meta data
		$order_item->update_meta_data('_wicket_finance_start_date', $start_date);
		$order_item->update_meta_data('_wicket_finance_end_date', $end_date);
		if (!empty($gl_code)) {
			$order_item->update_meta_data('_wicket_finance_gl_code', $gl_code);
		}
		$order_item->save();

		// Track changes in order notes
		$changes = array();
		if ($old_start !== $start_date) {
			$changes[] = sprintf(__('Start date: %s → %s', 'wicket'), $old_start ?: __('(empty)', 'wicket'), $start_date ?: __('(empty)', 'wicket'));
		}
		if ($old_end !== $end_date) {
			$changes[] = sprintf(__('End date: %s → %s', 'wicket'), $old_end ?: __('(empty)', 'wicket'), $end_date ?: __('(empty)', 'wicket'));
		}
		if ($old_gl !== $gl_code && !empty($gl_code)) {
			$changes[] = sprintf(__('GL Code: %s → %s', 'wicket'), $old_gl ?: __('(empty)', 'wicket'), $gl_code);
		}

		// Add order note if there were changes
		if (!empty($changes)) {
			$order = wc_get_order($order_id);
			if ($order) {
				$note = sprintf(
					__('Finance data updated for "%s": %s', 'wicket'),
					$order_item->get_name(),
					implode(', ', $changes)
				);
				$order->add_order_note($note);
			}
		}
	}
}

add_action('woocommerce_saved_order_items', 'wicket_finance_save_order_item_meta', 10, 2);

/**
 * Also persist finance fields when WooCommerce saves each order item (covers AJAX save flows)
 */
function wicket_finance_before_save_order_item($item_or_id)
{
	// Accept both an item object and an item ID
	if (is_object($item_or_id)) {
		$item = $item_or_id;
		$item_id = method_exists($item, 'get_id') ? $item->get_id() : 0;
	} else {
		$item_id = is_numeric($item_or_id) ? intval($item_or_id) : 0;
		$item = $item_id ? WC_Order_Factory::get_order_item($item_id) : null;
	}

	// Load the item object and ensure it's a product line item
	if (!$item || !is_a($item, 'WC_Order_Item_Product')) {
		return;
	}

	// Read submitted values
	$start_date = ($item_id && isset($_POST['wicket_finance_start_date']) && is_array($_POST['wicket_finance_start_date']) && isset($_POST['wicket_finance_start_date'][$item_id])) ? sanitize_text_field(wp_unslash($_POST['wicket_finance_start_date'][$item_id])) : '';
	$end_date = ($item_id && isset($_POST['wicket_finance_end_date']) && is_array($_POST['wicket_finance_end_date']) && isset($_POST['wicket_finance_end_date'][$item_id])) ? sanitize_text_field(wp_unslash($_POST['wicket_finance_end_date'][$item_id])) : '';
	$gl_code = ($item_id && isset($_POST['wicket_finance_gl_code']) && is_array($_POST['wicket_finance_gl_code']) && isset($_POST['wicket_finance_gl_code'][$item_id])) ? sanitize_text_field(wp_unslash($_POST['wicket_finance_gl_code'][$item_id])) : '';

	// If nothing was submitted for this item, skip.
	if ($start_date === '' && $end_date === '' && $gl_code === '') {
		return;
	}

	// Minimal validation to avoid invalid ordering (mirror main save handler)
	if (!empty($start_date) && !empty($end_date)) {
		$start_ts = strtotime($start_date);
		$end_ts = strtotime($end_date);
		if ($start_ts !== false && $end_ts !== false && $end_ts < $start_ts) {
			// Do not persist invalid combo; let main handler/notices deal with UI feedback
			return;
		}
	} elseif (!empty($start_date) && empty($end_date)) {
		// Incomplete pair, do not persist here
		return;
	}

	// Persist values to the item meta
	$item->update_meta_data('_wicket_finance_start_date', $start_date);
	$item->update_meta_data('_wicket_finance_end_date', $end_date);
	if (!empty($gl_code)) {
		$item->update_meta_data('_wicket_finance_gl_code', $gl_code);
	}
}

add_action('woocommerce_before_save_order_item', 'wicket_finance_before_save_order_item', 10, 1);

/**
 * Ensure finance meta fields are included in WooCommerce exports
 * This adds our custom meta keys to the list of exportable order item meta
 */
function wicket_finance_add_export_order_item_meta($meta_keys)
{
	$meta_keys[] = '_wicket_finance_start_date';
	$meta_keys[] = '_wicket_finance_end_date';
	$meta_keys[] = '_wicket_finance_gl_code';

	return $meta_keys;
}

add_filter('woocommerce_csv_product_import_mapping_default_columns', 'wicket_finance_add_export_order_item_meta');

/**
 * Add custom columns to order export
 * This ensures our finance fields appear as separate columns in exports
 */
function wicket_finance_add_order_export_columns($columns)
{
	$columns['wicket_finance_start_date'] = __('Finance Start Date', 'wicket');
	$columns['wicket_finance_end_date'] = __('Finance End Date', 'wicket');
	$columns['wicket_finance_gl_code'] = __('Finance GL Code', 'wicket');

	return $columns;
}

add_filter('woocommerce_order_export_column_names', 'wicket_finance_add_order_export_columns');

/**
 * Add finance data to order export rows
 * This populates the export columns with actual data from order items
 */
function wicket_finance_add_order_export_data($row, $order)
{
	$finance_data = array();

	// Get all line items from the order
	$items = $order->get_items();

	foreach ($items as $item_id => $item) {
		$start_date = $item->get_meta('_wicket_finance_start_date', true);
		$end_date = $item->get_meta('_wicket_finance_end_date', true);
		$gl_code = $item->get_meta('_wicket_finance_gl_code', true);

		if (!empty($start_date) || !empty($end_date) || !empty($gl_code)) {
			$finance_data[] = sprintf(
				'%s: Start=%s, End=%s, GL=%s',
				$item->get_name(),
				$start_date ?: 'N/A',
				$end_date ?: 'N/A',
				$gl_code ?: 'N/A'
			);
		}
	}

	// Add finance data to the row
	$row['wicket_finance_start_date'] = !empty($finance_data) ? implode(' | ', array_filter(array_column($items, function ($item) {
		return $item->get_meta('_wicket_finance_start_date', true);
	}))) : '';

	$row['wicket_finance_end_date'] = !empty($finance_data) ? implode(' | ', array_filter(array_map(function ($item) {
		return $item->get_meta('_wicket_finance_end_date', true);
	}, $items))) : '';

	$row['wicket_finance_gl_code'] = !empty($finance_data) ? implode(' | ', array_filter(array_map(function ($item) {
		return $item->get_meta('_wicket_finance_gl_code', true);
	}, $items))) : '';

	return $row;
}

add_filter('woocommerce_order_export_row_data', 'wicket_finance_add_order_export_data', 10, 2);

/**
 * Make finance meta visible in order item meta display
 * This ensures the meta shows up in admin order views and potentially exports
 */
function wicket_finance_display_order_item_meta($formatted_meta, $item)
{
	$start_date = $item->get_meta('_wicket_finance_start_date', true);
	$end_date = $item->get_meta('_wicket_finance_end_date', true);
	$gl_code = $item->get_meta('_wicket_finance_gl_code', true);

	if (!empty($start_date)) {
		$formatted_meta[] = (object) array(
			'key' => '_wicket_finance_start_date',
			'value' => $start_date,
			'display_key' => __('Finance Start Date', 'wicket'),
			'display_value' => date_i18n(get_option('date_format'), strtotime($start_date)),
		);
	}

	if (!empty($end_date)) {
		$formatted_meta[] = (object) array(
			'key' => '_wicket_finance_end_date',
			'value' => $end_date,
			'display_key' => __('Finance End Date', 'wicket'),
			'display_value' => date_i18n(get_option('date_format'), strtotime($end_date)),
		);
	}

	if (!empty($gl_code)) {
		$formatted_meta[] = (object) array(
			'key' => '_wicket_finance_gl_code',
			'value' => $gl_code,
			'display_key' => __('Finance GL Code', 'wicket'),
			'display_value' => $gl_code,
		);
	}

	return $formatted_meta;
}

add_filter('woocommerce_order_item_get_formatted_meta_data', 'wicket_finance_display_order_item_meta', 10, 2);

/**
 * Hide raw finance meta keys in order item meta display to avoid duplication.
 */
function wicket_finance_hide_raw_order_item_meta_keys($hidden)
{
	$hidden[] = '_wicket_finance_start_date';
	$hidden[] = '_wicket_finance_end_date';
	$hidden[] = '_wicket_finance_gl_code';
	return $hidden;
}

add_filter('woocommerce_hidden_order_itemmeta', 'wicket_finance_hide_raw_order_item_meta_keys');

/**
 * Add finance fields to WooCommerce CSV export headers
 * This ensures compatibility with various export plugins
 */
function wicket_finance_csv_export_headers($headers)
{
	$headers['finance_start_date'] = __('Finance Start Date', 'wicket');
	$headers['finance_end_date'] = __('Finance End Date', 'wicket');
	$headers['finance_gl_code'] = __('Finance GL Code', 'wicket');

	return $headers;
}

add_filter('woocommerce_csv_order_export_column_headers', 'wicket_finance_csv_export_headers');

/**
 * Add finance data to CSV export rows
 * This populates CSV exports with finance data
 */
function wicket_finance_csv_export_data($row_data, $order)
{
	$items = $order->get_items();
	$start_dates = array();
	$end_dates = array();
	$gl_codes = array();

	foreach ($items as $item) {
		$start_date = $item->get_meta('_wicket_finance_start_date', true);
		$end_date = $item->get_meta('_wicket_finance_end_date', true);
		$gl_code = $item->get_meta('_wicket_finance_gl_code', true);

		if (!empty($start_date)) {
			$start_dates[] = $start_date;
		}
		if (!empty($end_date)) {
			$end_dates[] = $end_date;
		}
		if (!empty($gl_code)) {
			$gl_codes[] = $gl_code;
		}
	}

	$row_data['finance_start_date'] = implode(', ', $start_dates);
	$row_data['finance_end_date'] = implode(', ', $end_dates);
	$row_data['finance_gl_code'] = implode(', ', $gl_codes);

	return $row_data;
}

add_filter('woocommerce_csv_order_export_row_data', 'wicket_finance_csv_export_data', 10, 2);
