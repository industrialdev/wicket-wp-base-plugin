<?php
$defaults        = [
  'classes'                    => [],
  'org_id'                     => '',
  'org_info_data_field_name'   => 'profile-org-info',
  'validation_data_field_name' => 'profile-org-validation',
];

$args                       = wp_parse_args($args, $defaults);
$classes                    = $args['classes'];
$org_id                     = $args['org_id'];
$org_required_resources     = $args['org_required_resources'] === '' ? '{}' : $args['org_required_resources'];
$org_info_data_field_name   = $args['org_info_data_field_name'];
$validation_data_field_name = $args['validation_data_field_name'];
$unique_widget_id           = rand(1, PHP_INT_MAX);

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
         });

         // Listen for save success
         widget.listen(widget.eventTypes.SAVE_SUCCESS, function(payload) {
           let event = new CustomEvent(
             "wwidget-component-profile-org-save-success", {
               detail: payload
             });

           window.dispatchEvent(event);
           widgetProfileOrgUpdateHiddenFields(payload);
         });

         // Listen for save errors and re-evaluate validation state
        widget.listen(widget.eventTypes.SAVE_ERROR, function(payload) {
          widgetProfileOrgUpdateHiddenFields(payload);
        });

         // Listen for validation errors and re-evaluate validation state
        widget.listen(widget.eventTypes.VALIDATION_ERROR, function(payload) {
          widgetProfileOrgUpdateHiddenFields(payload);
        });



         widget.listen(widget.eventTypes.DELETE_SUCCESS, function(payload) {
          let event = new CustomEvent(
            "wwidget-component-profile-org-delete-success", {
              detail: payload
            });

          window.dispatchEvent(event);
          widgetProfileOrgUpdateHiddenFields(payload);
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
