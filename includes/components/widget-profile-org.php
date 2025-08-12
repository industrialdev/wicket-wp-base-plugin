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

<div class="wicket-section wicket__widgets <?php implode(' ', $classes); ?>"
  role="complementary">
  <h2>Organization Profile</h2>
  <div id="org-profile-widget-<?php echo $unique_widget_id; ?>">
  </div>
  <input type="hidden"
    name="<?php echo $org_info_data_field_name; ?>" />
  <input type="hidden"
    name="<?php echo $validation_data_field_name; ?>"
    value="false" />
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
    Wicket.ready(function() {
      let widgetRoot_<?php echo $unique_widget_id; ?> =
        document.getElementById(
          'org-profile-widget-<?php echo $unique_widget_id; ?>'
          );

      Wicket.widgets.editOrganizationProfile({
        rootEl: widgetRoot_<?php echo $unique_widget_id; ?>,
        apiRoot: '<?php echo $wicket_settings['api_endpoint'] ?>',
        accessToken: '<?php echo wicket_get_access_token(wicket_current_person_uuid(), $org_id); ?>',
        orgId: '<?php echo $org_id; ?>',
        lang: "<?php echo defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : 'en' ?>",
        requiredResources: <?php echo $org_required_resources; ?> ,
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
          // Add visual required markers
          markRequiredLabels(document.getElementById(
            'org-profile-widget-<?php echo $unique_widget_id; ?>'
            ));
          markAddressHeadingAsRequired(document.getElementById(
            'org-profile-widget-<?php echo $unique_widget_id; ?>'
          ));
          markAddPhoneButtonAsRequired(document.getElementById(
            'org-profile-widget-<?php echo $unique_widget_id; ?>'
          ));
        });
        widget.listen(widget.eventTypes.SAVE_SUCCESS, function(payload) {
          let event = new CustomEvent(
            "wwidget-component-profile-org-save-success", {
              detail: payload
            });

          window.dispatchEvent(event);
          widgetProfileOrgUpdateHiddenFields(payload);
          // Refresh visual required markers
          markRequiredLabels(document.getElementById(
            'org-profile-widget-<?php echo $unique_widget_id; ?>'
            ));
          markAddressHeadingAsRequired(document.getElementById(
            'org-profile-widget-<?php echo $unique_widget_id; ?>'
          ));
          markAddPhoneButtonAsRequired(document.getElementById(
            'org-profile-widget-<?php echo $unique_widget_id; ?>'
          ));
        });
        widget.listen(widget.eventTypes.DELETE_SUCCESS, function(payload) {
          let event = new CustomEvent(
            "wwidget-component-profile-org-delete-success", {
              detail: payload
            });

          window.dispatchEvent(event);
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
      if (payload.incompleteRequiredFields) {
        if (payload.incompleteRequiredFields.length > 0) {
          validationDataField.value = false;
        }
      }

      if (payload.incompleteRequiredResources) {
        if (payload.incompleteRequiredResources.length > 0) {
          validationDataField.value = false;
        }
      }

      // Toggle body classes for simple global checks
      try {
        // Additional requireds: Org name, Org type, Primary Address, Website, Phone, Email
        const attrs = payload?.attributes || payload || {};
        const hasName = !!(attrs.name || attrs.fullName || attrs.legalName);
        const hasType = !!(attrs.organizationType || attrs.orgType || attrs.type || attrs.typeExternalId);
        const hasPrimaryAddress = Array.isArray(payload?.addresses) && payload.addresses.some(function(
          addr) {
          const a = addr?.attributes || addr || {};
          return a.primary === true || a.active === true;
        });
        const hasWebsite = Array.isArray(payload?.webAddresses) && payload.webAddresses.length > 0;
        const hasPhone = Array.isArray(payload?.phones) && payload.phones.length > 0;
        const hasEmail = Array.isArray(payload?.emails) && payload.emails.length > 0;
        if (!(hasName && hasType && hasPrimaryAddress && hasWebsite && hasPhone && hasEmail)) {
          validationDataField.value = false;
        }
      } catch (e) {}

      const approved = String(validationDataField.value) === 'true';
      document.body.classList.toggle('wicket-org-profile-approved', approved);
      document.body.classList.toggle('wicket-org-profile-denied', !approved);
    }

    // Add a required asterisk to the "Add phone" button label
    function markAddPhoneButtonAsRequired(rootEl) {
      try {
        if (!rootEl) { return; }
        const buttons = rootEl.querySelectorAll('button[data-cy="uni-email_phone_web-add_btn"]');
        buttons.forEach(function(btn) {
          const labelSpan = btn.querySelector('.btn-label');
          if (!labelSpan) { return; }
          const text = (labelSpan.textContent || '').trim().toLowerCase();
          if (text.includes('add phone')) {
            labelSpan.classList.add('wicket-required');
          }
        });
      } catch (e) {}
    }

    // Mark labels for required inputs within the widget root
    function markRequiredLabels(rootEl) {
      try {
        if (!rootEl) {
          return;
        }
        const requiredInputs = rootEl.querySelectorAll(
          'input[required], select[required], textarea[required], [aria-required="true"]');
        requiredInputs.forEach(function(input) {
          let label = rootEl.querySelector('label[for="' + (input.id || '') + '"]');
          if (!label) {
            label = input.closest('label');
          }
          if (!label) {
            const container = input.closest(
              '.form-field, .field, .input-wrap, .ww-form-field, .wicket-form-field, .wicket-field'
              );
            if (container) {
              label = container.querySelector('label');
            }
          }
          if (label) {
            label.classList.add('wicket-required');
          }
        });
      } catch (e) {}
    }

    // Add a required asterisk to the "Address details" heading
    function markAddressHeadingAsRequired(rootEl) {
      try {
        if (!rootEl) { return; }
        // Prefer structural detection: find AddressList container and walk backwards to h4
        const addressList = rootEl.querySelector('.AddressList');
        if (addressList) {
          let prev = addressList.previousElementSibling;
          while (prev && prev.tagName !== 'H4') { prev = prev.previousElementSibling; }
          if (prev && prev.tagName === 'H4') {
            prev.classList.add('wicket-required');
            return;
          }
        }
        // Fallback: find H4 that includes the word Address
        const headings = rootEl.querySelectorAll('h4');
        headings.forEach(function(h){
          const txt = (h.textContent || '').trim().toLowerCase();
          if (txt.includes('address')) { h.classList.add('wicket-required'); }
        });
      } catch (e) {}
    }

    // Guard the GF Next button on the page containing this widget
    function setupOrgNextButtonGuard() {
      try {
        const widgetRoot = document.getElementById(
          'org-profile-widget-<?php echo $unique_widget_id; ?>'
          );
        if (!widgetRoot) {
          return;
        }
        const formEl = widgetRoot.closest('form');
        if (!formEl) {
          return;
        }
        const pageEl = widgetRoot.closest('.gform_page');

        const handleClick = function(e) {
          const t = e.target;
          if (!t || !t.id) {
            return;
          }
          if (t.id.indexOf('gform_next_button_') === 0) {
            if (pageEl && t.closest && t.closest('.gform_page') !== pageEl) {
              return;
            }
            const validationField = document.querySelector(
              'input[name="<?php echo $validation_data_field_name; ?>"]'
              );
            const approved = validationField && String(validationField.value) === 'true';
            if (!approved) {
              e.preventDefault();
              e.stopPropagation();
              // Lightweight notice; widget UI already indicates requireds
              alert(
                'Please complete the Organization Profile: Org Name, Org Type, Primary Address, Website, Phone, and Email.');
            }
          }
        };

        // Delegate clicks from the form to handle Next buttons even if re-rendered
        formEl.addEventListener('click', handleClick, true);
      } catch (err) {
        // no-op
      }
    }

    setupOrgNextButtonGuard();
  });
</script>
<style>
  .wicket__widgets {
    & .wicket-required::after {
      content: " *";
      color: #d32f2f;
      margin-left: 2px;
      font-weight: 600;
    }

    ul, li {
      list-style: none !important;
    }
  }
</style>
