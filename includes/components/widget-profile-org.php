<?php
$defaults = [
    'classes'                    => [],
    'org_id'                     => '',
    'org_info_data_field_name'   => 'profile-org-info',
    'validation_data_field_name' => 'profile-org-validation',
];

$args = wp_parse_args($args, $defaults);
$classes = $args['classes'];
$org_id = $args['org_id'];
$org_required_resources = $args['org_required_resources'] === '' ? '{}' : $args['org_required_resources'];
$org_info_data_field_name = $args['org_info_data_field_name'];
$validation_data_field_name = $args['validation_data_field_name'];
$unique_widget_id = rand(1, PHP_INT_MAX);

if (empty($org_id)) {
    return; // Returning nothing in case this needs to be dynamically reloaded, so the user doesn't see the error
}

if (strlen(strval($org_id)) < 4) {
    return; // Bail out if an improper org_id has been passed in, like a placeholder field ID in the GF wrapper
}

$wicket_settings = get_wicket_settings();
?>

<div class="wicket-section <?php implode(' ', $classes); ?>"
  role="complementary">
  <h2><?php _e('Organization Profile', 'wicket'); ?></h2>
  <div id="org-profile-widget-<?php echo $unique_widget_id; ?>">
  </div>
  <input type="hidden"
    name="<?php echo $org_info_data_field_name; ?>" />
  <input type="hidden"
    name="<?php echo $validation_data_field_name; ?>" />
</div>

<script type="text/javascript">
  if (typeof window.Wicket === 'undefined') {
    window.Wicket = function(doc, tag, id, script) {
      var w = window.Wicket || {};
      if (doc.getElementById(id)) return w;
      var ref = doc.getElementsByTagName(tag)[0];
      var js = doc.createElement(tag);
      js.id = id;
      js.src = script;
      ref.parentNode.insertBefore(js, ref);
      w._q = [];
      w.ready = function(f) {
        w._q.push(f)
      };
      return w
    }(document, "script", "wicket-widgets",
      "<?php echo $wicket_settings['wicket_admin'] ?>/dist/widgets.js"
      );
  }
</script>

<?php
  $widget_profile_org_extra_fields = apply_filters('widget_profile_org_extra_fields', ['type']);
$widget_profile_org_extra_fields = json_encode($widget_profile_org_extra_fields);
?>

<script type="text/javascript">
  document.addEventListener("DOMContentLoaded", function() {
    const widgetProfileOrgDebug = {
      enabled: true,
      init() {},
      log(message, data) {
        if (!this.enabled || !window.console || !window.console.log) {
          return;
        }
        if (typeof data === 'undefined') {
          window.console.log(message);
          return;
        }
        window.console.log(message, data);
      },
      warn(message, data) {
        if (!this.enabled || !window.console || !window.console.warn) {
          return;
        }
        if (typeof data === 'undefined') {
          window.console.warn(message);
          return;
        }
        window.console.warn(message, data);
      }
    };
    widgetProfileOrgDebug.init();
    Wicket.ready(function() {

      let widgetRoot_<?php echo $unique_widget_id; ?> =
        document.getElementById(
          'org-profile-widget-<?php echo $unique_widget_id; ?>'
          );

             Wicket.widgets.editOrganizationProfile({
         rootEl: widgetRoot_<?php echo $unique_widget_id; ?> ,
         apiRoot: '<?php echo $wicket_settings['api_endpoint'] ?>',
         accessToken: '<?php echo wicket_get_access_token(wicket_current_person_uuid(), $org_id); ?>',
         orgId: '<?php echo $org_id; ?>',
         lang: "<?php echo defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : 'en' ?>",
         extraFields: <?php echo $widget_profile_org_extra_fields; ?>,
         requiredResources: <?php echo $org_required_resources; ?>,
       }).then(function(widget) {
        <?php
                // Dispatch custom events to the page on each available widget listener,
                // so that actions can be taken based on that information if needed,
                // such as in the Gravity Forms wrapper. Also update hidden fields to
                // make data available in multiple ways on the page
?>
                 widget.listen(widget.eventTypes.WIDGET_LOADED, function(payload) {

         let event = new CustomEvent("wwidget-component-profile-org-loaded", {
             detail: payload
           });

           window.dispatchEvent(event);

           let commonEventLoaded = new CustomEvent(
             "wwidget-component-common-loaded", {
               detail: payload
             });

           window.dispatchEvent(commonEventLoaded);

                      widgetProfileOrgUpdateHiddenFields(payload);
                      widgetProfileOrgDebug.log('WGF-ORG-PROFILE: loaded', payload);
         });

         // Listen for save success
         widget.listen(widget.eventTypes.SAVE_SUCCESS, function(payload) {
           let event = new CustomEvent(
             "wwidget-component-profile-org-save-success", {
               detail: payload
             });

           window.dispatchEvent(event);
           widgetProfileOrgUpdateHiddenFields(payload);
           widgetProfileOrgDebug.log('WGF-ORG-PROFILE: save success', payload);
         });

         // Listen for save errors and re-evaluate validation state
        widget.listen(widget.eventTypes.SAVE_ERROR, function(payload) {
          widgetProfileOrgUpdateHiddenFields(payload);
          widgetProfileOrgDebug.warn('WGF-ORG-PROFILE: save error', payload);
        });

         // Listen for validation errors and re-evaluate validation state
        widget.listen(widget.eventTypes.VALIDATION_ERROR, function(payload) {
          widgetProfileOrgUpdateHiddenFields(payload);
          widgetProfileOrgDebug.warn('WGF-ORG-PROFILE: validation error', payload);
        });



         widget.listen(widget.eventTypes.DELETE_SUCCESS, function(payload) {
          let event = new CustomEvent(
            "wwidget-component-profile-org-delete-success", {
              detail: payload
            });

          window.dispatchEvent(event);
          widgetProfileOrgUpdateHiddenFields(payload);
          widgetProfileOrgDebug.log('WGF-ORG-PROFILE: delete success', payload);
        });
      });
        });

         function widgetProfileOrgUpdateHiddenFields(payload) {
       let userInfoDataField = document.querySelector(
         'input[name="<?php echo $org_info_data_field_name; ?>"]'
         );
       let validationDataField = document.querySelector(
         'input[name="<?php echo $validation_data_field_name; ?>"]'
         );

       if (!userInfoDataField || !validationDataField) {
         widgetProfileOrgDebug.warn('WGF-ORG-PROFILE: hidden fields missing', {
           userInfoDataFieldFound: !!userInfoDataField,
           validationDataFieldFound: !!validationDataField
         });
         return;
       }

       userInfoDataField.value = JSON.stringify(payload);

       validationDataField.value = true;
       let validationFailures = [];

       if (payload.incompleteRequiredFields) {
         if (payload.incompleteRequiredFields.length > 0) {
           validationDataField.value = false;
           validationFailures.push('incomplete required fields: ' + payload.incompleteRequiredFields.join(', '));
         }
       }

       // Validate required resources so the hidden validation field reflects current state
      if (payload.incompleteRequiredResources) {
        if (payload.incompleteRequiredResources.length > 0) {
          validationDataField.value = false;
          validationFailures.push('incomplete required resources: ' + payload.incompleteRequiredResources.join(', '));
        }
      }

      // Fallback/override: if all four resource collections have at least one item, consider resources satisfied
      const hasAddresses    = Array.isArray(payload.addresses) && payload.addresses.length > 0;
      const hasEmails       = Array.isArray(payload.emails) && payload.emails.length > 0;
      const hasPhones       = Array.isArray(payload.phones) && payload.phones.length > 0;
      const hasWebAddresses = Array.isArray(payload.webAddresses) && payload.webAddresses.length > 0;
      if (hasAddresses && hasEmails && hasPhones && hasWebAddresses) {
        // Remove any previously-added resource-related failure message
        validationFailures = validationFailures.filter(function(msg){
          return !/^incomplete required resources:/.test(msg);
        });
        // Only keep fields-related failures, otherwise mark as valid
        if (validationFailures.length === 0) {
          validationDataField.value = true;
        }
      }

     if (validationFailures.length > 0) {
       console.log('DEBUG: Validation failures:', validationFailures);
     }

      widgetProfileOrgDebug.log('WGF-ORG-PROFILE: hidden fields updated', {
        orgInfoField: '<?php echo $org_info_data_field_name; ?>',
        validationField: '<?php echo $validation_data_field_name; ?>',
        validationValue: validationDataField.value,
        validationFailures: validationFailures
      });
     }

    function logFormFieldState(origin) {
      const infoField = document.querySelector('input[name="<?php echo $org_info_data_field_name; ?>"]');
      const validationField = document.querySelector('input[name="<?php echo $validation_data_field_name; ?>"]');
      const form = infoField ? infoField.form : (validationField ? validationField.form : null);
      if (!form) {
        widgetProfileOrgDebug.warn('WGF-ORG-PROFILE: form not found', {
          origin,
          infoFieldFound: !!infoField,
          validationFieldFound: !!validationField
        });
        return;
      }

      const infoFields = form.querySelectorAll('input[name="<?php echo $org_info_data_field_name; ?>"]');
      const validationFields = form.querySelectorAll('input[name="<?php echo $validation_data_field_name; ?>"]');
      let formDataInfo = [];
      let formDataValidation = [];
      try {
        const formData = new FormData(form);
        formDataInfo = formData.getAll('<?php echo $org_info_data_field_name; ?>');
        formDataValidation = formData.getAll('<?php echo $validation_data_field_name; ?>');
      } catch (e) {}

      const infoValues = Array.from(infoFields).map((field) => field.value || '');
      const validationValues = Array.from(validationFields).map((field) => field.value || '');
      widgetProfileOrgDebug.log('WGF-ORG-PROFILE: form field state', {
        origin,
        formId: form.id || null,
        infoFieldCount: infoFields.length,
        validationFieldCount: validationFields.length,
        infoFieldValues: infoValues,
        validationFieldValues: validationValues,
        formDataInfoCount: formDataInfo.length,
        formDataValidationCount: formDataValidation.length,
        formDataInfoLengths: formDataInfo.map((value) => (value || '').length),
        formDataValidationValues: formDataValidation
      });

      widgetProfileOrgDebug.log('WGF-ORG-PROFILE: form field state (flat)', {
        origin,
        formId: form.id || null,
        infoFieldLengths: infoValues.map((value) => value.length),
        infoFieldPreview: infoValues.map((value) => value.substring(0, 200)),
        validationFieldValues: validationValues,
        formDataInfoPreview: formDataInfo.map((value) => (value || '').substring(0, 200)),
        formDataValidationValues: formDataValidation
      });

      widgetProfileOrgDebug.log('WGF-ORG-PROFILE: form field state (flat json)', JSON.stringify({
        origin,
        formId: form.id || null,
        infoFieldLengths: infoValues.map((value) => value.length),
        infoFieldPreview: infoValues.map((value) => value.substring(0, 200)),
        validationFieldValues: validationValues,
        formDataInfoPreview: formDataInfo.map((value) => (value || '').substring(0, 200)),
        formDataValidationValues: formDataValidation
      }));
    }

    if (widgetProfileOrgDebug.enabled) {
      document.addEventListener('click', function(event) {
        const target = event.target;
        const nextButton = target.closest('.gform_next_button, [id^="gform_next_button_"], .gform_submit_button, [id^="gform_submit_button_"]');
        if (!nextButton) {
          return;
        }
        const infoField = document.querySelector('input[name="<?php echo $org_info_data_field_name; ?>"]');
        const validationField = document.querySelector('input[name="<?php echo $validation_data_field_name; ?>"]');
        widgetProfileOrgDebug.log('WGF-ORG-PROFILE: GF button click', {
          infoFieldFound: !!infoField,
          validationFieldFound: !!validationField,
          infoFieldLength: infoField ? (infoField.value || '').length : null,
          validationFieldValue: validationField ? validationField.value : null
        });
        logFormFieldState('click');
      }, true);

      document.addEventListener('submit', function(event) {
        const form = event.target;
        if (!form || !form.id || !form.id.startsWith('gform_')) {
          return;
        }
        logFormFieldState('submit');
      }, true);
    }

  });
</script>
<style>
  .wicket__widgets {
    ul,
    li {
      list-style: none !important;
    }

    /* Add Address button with red asterisk indicator */
    [data-cy="uni-add_address_btn"] .btn-label::after {
      content: " *";
      color: #e62600;
      font-weight: bold;
    }

    /* Organization profile: Add asterisk for Email, Phone and Web address */
    .OrganizationProfile [data-cy="uni-email_phone_web-add_btn"] .btn-label::after {
      content: " *";
      color: #e62600;
      font-weight: bold;
    }
  }
</style>
