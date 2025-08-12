<?php
$defaults        = [
  'classes'                    => [],
  'user_info_data_field_name'  => 'profile-user-info',
  'validation_data_field_name' => 'profile-validation',
];

$args                       = wp_parse_args($args, $defaults);
$classes                    = $args['classes'];
$user_info_data_field_name  = $args['user_info_data_field_name'];
$validation_data_field_name = $args['validation_data_field_name'];
$unique_widget_id           = rand(1, PHP_INT_MAX);

$wicket_settings = get_wicket_settings();
?>

<div class="wicket-section <?php implode(' ', $classes); ?>"
  role="complementary">
  <h2>Profile</h2>
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
    Wicket.ready(function() {
      let widgetRoot_<?php echo $unique_widget_id; ?> =
        document.getElementById(
          'profile-<?php echo $unique_widget_id; ?>');

      Wicket.widgets.createPersonProfile({
        rootEl: widgetRoot_<?php echo $unique_widget_id; ?> ,
        apiRoot: '<?php echo $wicket_settings['api_endpoint'] ?>',
        accessToken: '<?php echo wicket_access_token_for_person(wicket_current_person_uuid()) ?>',
        personId: '<?php echo wicket_current_person_uuid(); ?>',
        lang: "<?php echo wicket_get_current_language(); ?>",
      }).then(function(widget) {
        <?php
                // Dispatch custom events to the page on each available widget listener,
                // so that actions can be taken based on that information if needed,
                // such as in the Gravity Forms wrapper. Also update hidden fields to
                // make data available in multiple ways on the page
?>
        widget.listen(widget.eventTypes.WIDGET_LOADED, function(payload) {
          let event = new CustomEvent("wwidget-component-profile-ind-loaded", {
            detail: payload
          });

          window.dispatchEvent(event);

          let commonEventLoaded = new CustomEvent(
            "wwidget-component-common-loaded", {
              detail: payload
            });

          window.dispatchEvent(commonEventLoaded);

          widgetProfileIndUpdateHiddenFields(payload);
        });
        widget.listen(widget.eventTypes.SAVE_SUCCESS, function(payload) {
          let event = new CustomEvent(
            "wwidget-component-profile-ind-save-success", {
              detail: payload
            });

          window.dispatchEvent(event);
          widgetProfileIndUpdateHiddenFields(payload);
        });
        widget.listen(widget.eventTypes.DELETE_SUCCESS, function(payload) {
          let event = new CustomEvent(
            "wwidget-component-profile-ind-delete-success", {
              detail: payload
            });

          window.dispatchEvent(event);
        });
      });
    });

    function widgetProfileIndUpdateHiddenFields(payload) {
      let userInfoDataField = document.querySelector(
        'input[name="<?php echo $user_info_data_field_name; ?>"]'
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
    }
  });
</script>
