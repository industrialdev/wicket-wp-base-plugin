<?php
$defaults        = array(
	'classes'                    => [],
  'org_id'                     => '',
  'org_info_data_field_name'   => 'profile-org-info',
  'validation_data_field_name' => 'profile-org-validation',
);
$args                       = wp_parse_args( $args, $defaults );
$classes                    = $args['classes'];
$org_id                     = $args['org_id'];
$org_info_data_field_name   = $args['org_info_data_field_name'];
$validation_data_field_name = $args['validation_data_field_name'];

if( empty( $org_id ) ) {
  //echo '<div>Error: Organization ID must be provided</div>';
  return; // Returning nothing in case this needs to be dynamically reloaded, so the user doesn't see the error
}

$wicket_settings = get_wicket_settings(); 

?>

<div class="wicket-section <?php implode( ' ', $classes ); ?>" role="complementary">
  <h2>Organization Profile</h2>
  <div id="org-profile-widget"></div>
  <input type="hidden" name="<?php echo $org_info_data_field_name; ?>" />
  <input type="hidden" name="<?php echo $validation_data_field_name; ?>" />
</div>

<script>
    window.Wicket=function(doc,tag,id,script){
    var w=window.Wicket||{};if(doc.getElementById(id))return w;var ref=doc.getElementsByTagName(tag)[0];var js=doc.createElement(tag);js.id=id;js.src=script;ref.parentNode.insertBefore(js,ref);w._q=[];w.ready=function(f){w._q.push(f)};return w
    }(document,"script","wicket-widgets","<?php echo $wicket_settings['wicket_admin'] ?>/dist/widgets.js");
</script>

<script type="text/javascript">
  (function (){
    Wicket.ready(function () {
      var widgetRoot = document.getElementById('org-profile-widget');
      Wicket.widgets.editOrganizationProfile({
        rootEl: widgetRoot,
        apiRoot: '<?php echo $wicket_settings['api_endpoint'] ?>',
        accessToken: '<?php echo wicket_get_access_token( wicket_current_person_uuid() , $org_id ); ?>',
        orgId: '<?php echo $org_id; ?>',
        lang: "<?php echo defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : 'en' ?>"
      }).then(function (widget) {
        // Dispatch custom events to the page on each available widget listener,
        // so that actions can be taken based on that information if needed,
        // such as in the Gravity Forms wrapper. Also update hidden fields to
        // make data available in multiple ways on the page
        widget.listen(widget.eventTypes.WIDGET_LOADED, function (payload) {
          let event = new CustomEvent("wwidget-component-profile-org-loaded", {
            detail: payload
          });

          window.dispatchEvent(event);
          widgetProfileOrgUpdateHiddenFields(payload);
        });
        widget.listen(widget.eventTypes.SAVE_SUCCESS, function (payload) {
          let event = new CustomEvent("wwidget-component-profile-org-save-success", {
            detail: payload
          });

          window.dispatchEvent(event);
          widgetProfileOrgUpdateHiddenFields(payload);
        });
        widget.listen(widget.eventTypes.DELETE_SUCCESS, function (payload) {
          let event = new CustomEvent("wwidget-component-profile-org-delete-success", {
            detail: payload
          });

          window.dispatchEvent(event);
        });
      });
    });

    function widgetProfileOrgUpdateHiddenFields(payload) {
      let userInfoDataField = document.querySelector('input[name="<?php echo $org_info_data_field_name; ?>"]');
      let validationDataField = document.querySelector('input[name="<?php echo $validation_data_field_name; ?>"]');

      userInfoDataField.value = JSON.stringify(payload);

      validationDataField.value = true;
      if( payload.incompleteRequiredFields ) {
        if( payload.incompleteRequiredFields.length > 0 ) {
          validationDataField.value = false;
        }
      }
    }

  })()
</script>
