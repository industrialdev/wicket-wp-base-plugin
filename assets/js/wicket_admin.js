/*!
 * Wicket Plugin Admin JS
 *
 */

jQuery(document).ready(function($) {
	// Add Select 2 to page select options
    $('.wicket-create-account,.wicket-verify-account').select2({
	  placeholder: 'Select a page',
	  allowClear: true,
	});

	// Add Select 2 to category select options
	$('.wicket-membership-categories').select2({
	  placeholder: 'Select a category',
	  allowClear: true,
	});

  var productCategoryRow = $('#wicket_settingswicket_admin_settings_group_assignment_product_category').closest('tr');
  var roleEntityObjectRow = $('#wicket_settingswicket_admin_settings_group_assignment_role_entity_object').closest('tr');

  $('#wicket_settingswicket_admin_settings_group_assignment_subscription_products').on('click', function() {

    if ($(this).is(':checked')) {
      productCategoryRow.show();
      roleEntityObjectRow.show();
    } else {
      productCategoryRow.hide();
      roleEntityObjectRow.hide();
    }
  });

  productCategoryRow.hide();
  roleEntityObjectRow.hide();

  if ($('#wicket_settingswicket_admin_settings_group_assignment_subscription_products').is(':checked')) {
    productCategoryRow.show();
    roleEntityObjectRow.show();
  }

  // Initialize datepickers for finance/order line item fields when present
  if ($.fn.datepicker) {
    $('.wicket-date-picker').datepicker({ dateFormat: 'yy-mm-dd' });
  }
});