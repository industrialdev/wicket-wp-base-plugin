<?php
$defaults = [
    'classes'                    => [],
    'user_info_data_field_name'  => 'profile-user-info',
    'validation_data_field_name' => 'profile-validation',
    'profile_required_resources' => '{}',
    'org_id'                     => '',
    'hidden_fields'              => [],
    'fields'                     => [],
    'person_id'                  => '',
];

$args = wp_parse_args($args, $defaults);
$classes = $args['classes'];
$user_info_data_field_name = $args['user_info_data_field_name'];
$validation_data_field_name = $args['validation_data_field_name'];
$org_id = $args['org_id'];
$profile_required_resources = $args['profile_required_resources'] === '' ? '{}' : $args['profile_required_resources'];
$hidden_fields = $args['hidden_fields'];
$fields = $args['fields'];
$person_id = $args['person_id'] ?? wicket_current_person_uuid();
$unique_widget_id = rand(1, PHP_INT_MAX);

$wicket_settings = get_wicket_settings();
?>

<div class="wicket-section <?php implode(' ', $classes); ?>"
  role="complementary">
  <!-- <h2><?php _e('Profile', 'wicket'); ?></h2> -->
  <div id="profile-<?php echo $unique_widget_id; ?>"></div>
  <input type="hidden"
    name="<?php echo $user_info_data_field_name; ?>" />
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

<script type="text/javascript">
  document.addEventListener("DOMContentLoaded", function() {
    const wicketGfDebugEnabled = window.location.search.indexOf('wicketGfDebug=1') !== -1;
    const widgetProfileIndDebug = {
      enabled: wicketGfDebugEnabled,
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
    widgetProfileIndDebug.init();
    Wicket.ready(function() {
      let widgetRoot_<?php echo $unique_widget_id; ?> =
        document.getElementById(
          'profile-<?php echo $unique_widget_id; ?>');

             Wicket.widgets.createPersonProfile({
         rootEl: widgetRoot_<?php echo $unique_widget_id; ?> ,
         apiRoot: '<?php echo $wicket_settings['api_endpoint'] ?>',
         accessToken: '<?php echo wicket_access_token_for_person(wicket_current_person_uuid()) ?>',
         personId: '<?php echo $person_id; ?>',
         orgId: '<?php echo $org_id; ?>',
         <?php if (!empty($hidden_fields)) : ?>
         hiddenFields: <?php echo json_encode($hidden_fields); ?>,
         <?php endif; ?>
        <?php if (!empty($fields)) : ?>
          fields: <?php echo json_encode($fields); ?>,
        <?php endif; ?>
        lang: "<?php echo wicket_get_current_language(); ?>",
         requiredResources: <?php echo $profile_required_resources; ?>,
       }).then(function(widget) {
        const eventTypes = widget && widget.eventTypes ? widget.eventTypes : {};
        <?php
                // Dispatch custom events to the page on each available widget listener,
                // so that actions can be taken based on that information if needed,
                // such as in the Gravity Forms wrapper. Also update hidden fields to
                // make data available in multiple ways on the page
?>
        wicketBindWidgetEventIfSupported(widget, eventTypes.WIDGET_LOADED, function(payload) {
          let event = new CustomEvent("wwidget-component-profile-ind-loaded", {
            detail: payload
          });

          window.dispatchEvent(event);

          let commonEventLoaded = new CustomEvent(
            "wwidget-component-common-loaded", {
              detail: payload
            });

          window.dispatchEvent(commonEventLoaded);

          wicketWidgetProfileIndUpdateHiddenFields(payload);
          widgetProfileIndDebug.log('WGF-PROFILE-IND: loaded', payload);
        });
        wicketBindWidgetEventIfSupported(widget, eventTypes.SAVE_SUCCESS, function(payload) {
          let event = new CustomEvent(
            "wwidget-component-profile-ind-save-success", {
              detail: payload
            });

          window.dispatchEvent(event);
          wicketWidgetProfileIndUpdateHiddenFields(payload);
          widgetProfileIndDebug.log('WGF-PROFILE-IND: save success', payload);
        });
        // Keep hidden validation state in sync while users edit, even before explicit save.
        wicketBindWidgetEventIfSupported(widget, eventTypes.STATE_CHANGED, function(payload) {
          let event = new CustomEvent(
            "wwidget-component-profile-ind-state-changed", {
              detail: payload
            });

          window.dispatchEvent(event);
          wicketWidgetProfileIndUpdateHiddenFields(payload);
          widgetProfileIndDebug.log('WGF-PROFILE-IND: state changed', payload);
        });
        wicketBindWidgetEventIfSupported(widget, eventTypes.WIDGET_STATE_CHANGED, function(payload) {
          let event = new CustomEvent(
            "wwidget-component-profile-ind-state-changed", {
              detail: payload
            });

          window.dispatchEvent(event);
          wicketWidgetProfileIndUpdateHiddenFields(payload);
          widgetProfileIndDebug.log('WGF-PROFILE-IND: widget state changed', payload);
        });
        wicketBindWidgetEventIfSupported(widget, eventTypes.SAVE_ERROR, function(payload) {
          wicketWidgetProfileIndUpdateHiddenFields(payload);
          widgetProfileIndDebug.warn('WGF-PROFILE-IND: save error', payload);
        });
        wicketBindWidgetEventIfSupported(widget, eventTypes.VALIDATION_ERROR, function(payload) {
          wicketWidgetProfileIndUpdateHiddenFields(payload);
          widgetProfileIndDebug.warn('WGF-PROFILE-IND: validation error', payload);
        });
        wicketBindWidgetEventIfSupported(widget, eventTypes.DELETE_SUCCESS, function(payload) {
          let event = new CustomEvent(
            "wwidget-component-profile-ind-delete-success", {
              detail: payload
            });

          window.dispatchEvent(event);
          widgetProfileIndDebug.log('WGF-PROFILE-IND: delete success', payload);
        });
      });
    });

    function wicketBindWidgetEventIfSupported(widget, eventType, handler) {
      if (!widget || !widget.listen || !eventType) {
        return;
      }

      widget.listen(eventType, handler);
    }

    function wicketWidgetProfileIndUpdateHiddenFields(payload) {
      const hiddenFields = <?php echo json_encode(array_values($hidden_fields)); ?>;
      let userInfoDataField = document.querySelector(
        'input[name="<?php echo $user_info_data_field_name; ?>"]'
        );
      let validationDataField = document.querySelector(
        'input[name="<?php echo $validation_data_field_name; ?>"]'
        );

      if (!userInfoDataField || !validationDataField) {
        widgetProfileIndDebug.warn('WGF-PROFILE-IND: hidden fields missing', {
          userInfoDataFieldFound: !!userInfoDataField,
          validationDataFieldFound: !!validationDataField
        });
        return;
      }

      const normalizedPayload = payload && typeof payload === 'object' ? payload : {};
      const rawIncompleteFields = Array.isArray(normalizedPayload.incompleteRequiredFields)
        ? normalizedPayload.incompleteRequiredFields
        : [];
      const filteredIncompleteFields = rawIncompleteFields.filter(function(fieldKey) {
        return hiddenFields.indexOf(fieldKey) === -1;
      });
      normalizedPayload.incompleteRequiredFields = filteredIncompleteFields;

      userInfoDataField.value = JSON.stringify(normalizedPayload);

      validationDataField.value = 'true';
      if (normalizedPayload.incompleteRequiredFields) {
        if (normalizedPayload.incompleteRequiredFields.length > 0) {
          validationDataField.value = 'false';
        }
      }

      if (normalizedPayload.incompleteRequiredResources) {
        if (normalizedPayload.incompleteRequiredResources.length > 0) {
          validationDataField.value = 'false';
        }
      }

      widgetProfileIndDebug.log('WGF-PROFILE-IND: hidden fields updated', {
        userInfoField: '<?php echo $user_info_data_field_name; ?>',
        validationField: '<?php echo $validation_data_field_name; ?>',
        validationValue: validationDataField.value,
        incompleteRequiredFields: normalizedPayload.incompleteRequiredFields || [],
        incompleteRequiredResources: normalizedPayload.incompleteRequiredResources || []
      });
    }

    function wicketLogFormFieldState(origin) {
      const infoField = document.querySelector('input[name="<?php echo $user_info_data_field_name; ?>"]');
      const validationField = document.querySelector('input[name="<?php echo $validation_data_field_name; ?>"]');
      const form = infoField ? infoField.form : (validationField ? validationField.form : null);
      if (!form) {
        widgetProfileIndDebug.warn('WGF-PROFILE-IND: form not found', {
          origin,
          infoFieldFound: !!infoField,
          validationFieldFound: !!validationField
        });
        return;
      }

      let infoPayload = {};
      if (infoField && infoField.value) {
        try {
          infoPayload = JSON.parse(infoField.value);
        } catch (e) {
          infoPayload = {};
        }
      }

      widgetProfileIndDebug.log('WGF-PROFILE-IND: form field state', {
        origin,
        formId: form.id || null,
        infoFieldLength: infoField ? (infoField.value || '').length : 0,
        validationFieldValue: validationField ? validationField.value : null,
        incompleteRequiredFields: Array.isArray(infoPayload.incompleteRequiredFields) ? infoPayload.incompleteRequiredFields : [],
        incompleteRequiredResources: Array.isArray(infoPayload.incompleteRequiredResources) ? infoPayload.incompleteRequiredResources : []
      });
    }

    if (widgetProfileIndDebug.enabled) {
      document.addEventListener('click', function(event) {
        const target = event.target;
        const nextButton = target.closest('.gform_next_button, [id^="gform_next_button_"], .gform_submit_button, [id^="gform_submit_button_"]');
        if (!nextButton) {
          return;
        }

        wicketLogFormFieldState('click');
      }, true);

      document.addEventListener('submit', function(event) {
        const form = event.target;
        if (!form || !form.id || !form.id.startsWith('gform_')) {
          return;
        }

        wicketLogFormFieldState('submit');
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

    /* Person profile: Add asterisk for Email and Phone add buttons (exclude Web) */
    .PersonProfile .ResourceListRow__row:not(:last-of-type) [data-cy="uni-email_phone_web-add_btn"] .btn-label::after {
      content: " *";
      color: #e62600;
      font-weight: bold;
    }

    /* Hide "Personal details" title */
    .MembersDetailsProfile > div.flex.flex--centered h4 {
      display: none;
    }
  }
</style>
