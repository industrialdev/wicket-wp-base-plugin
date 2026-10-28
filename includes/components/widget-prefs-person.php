<?php
/**
 * MDP editPersonPreferences widget.
 *
 * 'widget_config' passes editor-authored MDP widget options through after a
 * blocklist strip. Trusted keys are merged last so they always win.
 *
 * @see atlas://plans/feature-widget-prefs-json-config.md
 */
$defaults = [
    'classes'                      => [],
    'hide_comm_prefs'              => false,
    'preferences_data_field_name'  => 'preferences-info',
    'validation_data_field_name'   => 'preferences-validation',
    'person_id'                    => '',
    'widget_config'                => [],
    'show_title'                   => true,
    'root_id'                      => '',
    'dispatch_events'              => true,
];

$args = wp_parse_args($args, $defaults);
$classes = $args['classes'];
$hide_comm_prefs = $args['hide_comm_prefs'];
$preferences_data_field_name = $args['preferences_data_field_name'];
$validation_data_field_name = $args['validation_data_field_name'];
$person_id = $args['person_id'] ?? wicket_current_person_uuid();
$show_title = (bool) $args['show_title'];
$dispatch_events = (bool) $args['dispatch_events'];
$root_id = is_string($args['root_id']) && $args['root_id'] !== ''
    ? $args['root_id']
    : 'preferences-widget-' . rand(1, PHP_INT_MAX);

$widget_config_blocklist = ['rootEl', 'apiRoot', 'accessToken', 'personId', '__proto__', 'constructor', 'prototype'];
$widget_config = is_array($args['widget_config']) && $args['widget_config'] !== [] && !array_is_list($args['widget_config'])
    ? array_diff_key($args['widget_config'], array_flip($widget_config_blocklist))
    : [];

$wicket_settings = get_wicket_settings();

// JSON_HEX_TAG/AMP keep editor text from closing or re-opening the inline script.
$json_flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES;

$trusted_options = [
    'lang'        => wicket_get_current_language(),
    'apiRoot'     => $wicket_settings['api_endpoint'],
    'accessToken' => wicket_access_token_for_person(wicket_current_person_uuid()),
    'personId'    => $person_id,
];

$widget_options_json = wp_json_encode(
    array_merge(['hideCommunicationPreferences' => (bool) $hide_comm_prefs], $widget_config, $trusted_options),
    $json_flags
);
if ($widget_options_json === false) {
    // An unencodable config (e.g. invalid UTF-8) must not break the widget.
    $widget_options_json = wp_json_encode(
        array_merge(['hideCommunicationPreferences' => (bool) $hide_comm_prefs], $trusted_options),
        $json_flags
    );
}
?>

<div class="wicket-section <?php echo esc_attr(implode(' ', $classes)); ?>"<?php if ($show_title) : ?>
  role="complementary"<?php endif; ?>>
  <?php if ($show_title) : ?>
  <h2>Preferences</h2>
  <?php endif; ?>
  <div id="<?php echo esc_attr($root_id); ?>">
  </div>
  <input type="hidden"
    name="<?php echo esc_attr($preferences_data_field_name); ?>" />
  <input type="hidden"
    name="<?php echo esc_attr($validation_data_field_name); ?>" />
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
      <?php echo wp_json_encode($wicket_settings['wicket_admin'] . '/dist/widgets.js', $json_flags); ?>
      );
  }
</script>

<script>
  (function() {
    function widgetPrefsPersonUpdateHiddenFields(payload) {
      let userInfoDataField = document.querySelector(
        <?php echo wp_json_encode('input[name="' . $preferences_data_field_name . '"]', $json_flags); ?>
        );
      let validationDataField = document.querySelector(
        <?php echo wp_json_encode('input[name="' . $validation_data_field_name . '"]', $json_flags); ?>
        );

      userInfoDataField.value = JSON.stringify(payload);

      validationDataField.value = true;
      if (payload.incompleteRequiredFields) {
        if (payload.incompleteRequiredFields.length > 0) {
          validationDataField.value = false;
        }
      }
    }

    function mountWidgetPrefsPerson() {
      Wicket.ready(function() {
        // JSON.parse keeps any nested "__proto__" key as plain data.
        let options = Object.assign(
          { rootEl: document.getElementById(<?php echo wp_json_encode($root_id, $json_flags); ?>) },
          JSON.parse(<?php echo wp_json_encode($widget_options_json, $json_flags); ?>)
        );

        Wicket.widgets.editPersonPreferences(options).then(function(widget) {
          widget.listen(widget.eventTypes.WIDGET_LOADED, function(payload) {
            <?php if ($dispatch_events) : ?>
            window.dispatchEvent(new CustomEvent("wwidget-component-prefs-person-loaded", {
              detail: payload
            }));
            window.dispatchEvent(new CustomEvent("wwidget-component-common-loaded", {
              detail: payload
            }));
            <?php endif; ?>
            widgetPrefsPersonUpdateHiddenFields(payload);
          });
          widget.listen(widget.eventTypes.SAVE_SUCCESS, function(payload) {
            <?php if ($dispatch_events) : ?>
            window.dispatchEvent(new CustomEvent("wwidget-component-prefs-person-save-success", {
              detail: payload
            }));
            <?php endif; ?>
            widgetPrefsPersonUpdateHiddenFields(payload);
          });
          <?php if ($dispatch_events) : ?>
          widget.listen(widget.eventTypes.DELETE_SUCCESS, function(payload) {
            window.dispatchEvent(new CustomEvent("wwidget-component-prefs-person-delete-success", {
              detail: payload
            }));
          });
          <?php endif; ?>
        });
      });
    }

    // The editor preview injects this markup after DOMContentLoaded has fired.
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', mountWidgetPrefsPerson);
    } else {
      mountWidgetPrefsPerson();
    }
  })();
</script>
