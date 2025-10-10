/*!
 * Wicket Plugin Admin JS
 *
 */

jQuery(document).ready(function ($) {
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

  $('#wicket_settingswicket_admin_settings_group_assignment_subscription_products').on('click', function () {

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


  function parseYMD(value) {
    // Expect yyyy-mm-dd
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
    if (!m) return null;
    const d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
    return isNaN(d.getTime()) ? null : d;
  }

  // Block Editor (Gutenberg) save locking when invalid
  if (typeof wp !== 'undefined' && wp.data && wp.data.select && wp.data.dispatch) {
    const lockName = 'wicket-deferral-dates-validation';
    const noticeId = 'wicket-deferral-date-error';

    function gbValidate() {
      const startVal = ($('#_wicket_finance_deferral_start_date').val() || '').trim();
      const endVal = ($('#_wicket_finance_deferral_end_date').val() || '').trim();

      if (startVal && !endVal) {
        return { valid: false, message: 'Please enter a valid deferral end date when a deferral start date is set.' };
      }
      if (startVal && endVal) {
        const startDateParsed = parseYMD(startVal);
        const endDateParsed = parseYMD(endVal);
        if (!startDateParsed || !endDateParsed || endDateParsed < startDateParsed) {
          return { valid: false, message: 'Deferral end date must be greater than or equal to the deferral start date (YYYY-MM-DD).' };
        }
      }
      return { valid: true };
    }

    function checkAndLockEditor() {
      const res = gbValidate();
      const isLocked = wp.data.select('core/editor').isPostSavingLocked();
      if (!res.valid && !isLocked) {
        wp.data.dispatch('core/editor').lockPostSaving(lockName);
        wp.data.dispatch('core/notices').createErrorNotice(res.message, { id: noticeId, isDismissible: true });
      } else if (res.valid && isLocked) {
        wp.data.dispatch('core/editor').unlockPostSaving(lockName);
        wp.data.dispatch('core/notices').removeNotice(noticeId);
      }
    }

    // React to field changes
    $(document).on('change keyup', '#_wicket_finance_deferral_start_date, #_wicket_finance_deferral_end_date', function () {
      checkAndLockEditor();
    });

    // Initial check after editor loads
    setTimeout(checkAndLockEditor, 800);
  }
});