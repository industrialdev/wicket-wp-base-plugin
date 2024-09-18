<?php
$defaults        = array(
	'classes'                      => [],
  'hide_comm_prefs'              => false,
  'preferences_data_field_name'  => 'preferences-info',
  'validation_data_field_name'   => 'preferences-validation',
);
$args                         = wp_parse_args( $args, $defaults );
$classes                      = $args['classes'];
$hide_comm_prefs              = $args['hide_comm_prefs'];
$preferences_data_field_name  = $args['preferences_data_field_name'];
$unique_widget_id             = rand( 1, 9999999 );

$wicket_settings = get_wicket_settings(); 

?>

<div class="wicket-section <?php implode( ' ', $classes ); ?>" role="complementary">
  <h2>Preferences</h2>
  <div id="preferences-widget-<?php echo $unique_widget_id; ?>"></div>
  <input type="hidden" name="<?php echo $preferences_data_field_name; ?>" />
  <?php /* No hidden validation field as the preferences widget doesn't need validation */ ?>
</div>

<script>
    window.Wicket=function(doc,tag,id,script){
    var w=window.Wicket||{};if(doc.getElementById(id))return w;var ref=doc.getElementsByTagName(tag)[0];var js=doc.createElement(tag);js.id=id;js.src=script;ref.parentNode.insertBefore(js,ref);w._q=[];w.ready=function(f){w._q.push(f)};return w
    }(document,"script","wicket-widgets","<?php echo $wicket_settings['wicket_admin'] ?>/dist/widgets.js");
</script>

<script>
    (function (){
        Wicket.ready(function () {
            var widgetRoot = document.getElementById('preferences-widget-<?php echo $unique_widget_id; ?>');

            Wicket.widgets.editPersonPreferences({
              rootEl: widgetRoot,
              apiRoot: '<?php echo $wicket_settings['api_endpoint'] ?>',
              accessToken: '<?php echo wicket_access_token_for_person(wicket_current_person_uuid()) ?>',
              personId: '<?php echo wicket_current_person_uuid(); ?>',
              lang: "<?php echo defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : 'en' ?>",
              hideCommunicationPreferences: <?php echo $hide_comm_prefs ? 'true' : 'false' ?>,
            }).then(function (widget) {
              // Dispatch custom events to the page on each available widget listener,
              // so that actions can be taken based on that information if needed,
              // such as in the Gravity Forms wrapper. Also update hidden fields to
              // make data available in multiple ways on the page
              widget.listen(widget.eventTypes.WIDGET_LOADED, function (payload) {
                let event = new CustomEvent("wwidget-component-prefs-person-loaded", {
                  detail: payload
                });

                window.dispatchEvent(event);
                widgetPrefsPersonUpdateHiddenFields(payload);
              });
              widget.listen(widget.eventTypes.SAVE_SUCCESS, function (payload) {
                let event = new CustomEvent("wwidget-component-prefs-person-save-success", {
                  detail: payload
                });

                window.dispatchEvent(event);
                widgetPrefsPersonUpdateHiddenFields(payload);
              });
              widget.listen(widget.eventTypes.DELETE_SUCCESS, function (payload) {
                let event = new CustomEvent("wwidget-component-prefs-person-delete-success", {
                  detail: payload
                });

                window.dispatchEvent(event);
              });
            });
        });

        function widgetPrefsPersonUpdateHiddenFields(payload) {
          let userInfoDataField = document.querySelector('input[name="<?php echo $preferences_data_field_name; ?>"]');

          userInfoDataField.value = JSON.stringify(payload);
        }
    })()
</script>