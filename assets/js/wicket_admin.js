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
});