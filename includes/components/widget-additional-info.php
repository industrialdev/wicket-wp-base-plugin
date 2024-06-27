<?php
$defaults        = array(
	'classes'                          => [],
  'additional_info_data_field_name'  => 'additional-info-data',
  'validation_data_field_name'       => 'additional-info-validation',
  'resource_type'                    => 'people',
  'schemas_and_overrides'            => [],
);
$args                             = wp_parse_args( $args, $defaults );
$classes                          = $args['classes'];
$additional_info_data_field_name  = $args['additional_info_data_field_name'];
$additional_info_validation       = $args['validation_data_field_name'];
$resource_type                    = $args['resource_type'];
$schemas_and_overrides            = $args['schemas_and_overrides'];

// DEMO
// $schemas_and_overrides = [
//   [
//     '779c8b9b-22b4-4ad2-8e25-a9f640b55f5a'
//   ],
// ];

// Example $schemas_and_overrides:
/* 
[
  [
    '1234-1234-1234-1234',
    '1234-1234-1234-1234' // The optional override
  ],
  [
    '1234-1234-1234-1234' // Just a schema no override
  ]
]
*/

$wicket_settings = get_wicket_settings(); 

?>

<div class="wicket-section <?php implode( ' ', $classes ); ?>" role="complementary">
  <h2>Additional Info</h2>
  <div id="additional-info"></div>
  <input type="hidden" name="<?php echo $additional_info_data_field_name; ?>" />
  <input type="hidden" name="<?php echo $additional_info_validation; ?>" />
</div>

<script>
    window.Wicket=function(doc,tag,id,script){
    var w=window.Wicket||{};if(doc.getElementById(id))return w;var ref=doc.getElementsByTagName(tag)[0];var js=doc.createElement(tag);js.id=id;js.src=script;ref.parentNode.insertBefore(js,ref);w._q=[];w.ready=function(f){w._q.push(f)};return w
    }(document,"script","wicket-widgets","<?php echo $wicket_settings['wicket_admin'] ?>/dist/widgets.js");
</script>

<script>
    (function (){
        Wicket.ready(function () {
            var widgetRoot = document.getElementById('additional-info');
            Wicket.widgets.editAdditionalInfo({
              loadIcons: true,
              rootEl: widgetRoot,
              apiRoot: '<?php echo $wicket_settings['api_endpoint'] ?>',
              accessToken: '<?php echo wicket_access_token_for_person(wicket_current_person_uuid()) ?>',
              resource: {
                type: '<?php echo $resource_type; ?>',
                id: '<?php echo wicket_current_person_uuid(); ?>'
              },
              lang: "<?php echo defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : 'en' ?>",
              schemas: [
                // If schemas are not provided, the widget defaults to show all schemas.
                // separate schema overrides exist to make certain fields readonly just in the account center, hence the resource ID below.
                // To access resource schema overrides, login to wicket using a wicket.io email -> settings -> additional info
                
                <?php foreach( $schemas_and_overrides as $schema ) {
                  $output = "{ id: '".$schema[0]."'";
                  if( isset( $schema[1] ) ) {
                    $output .= ", resourceId: '".$schema[1]."' },";
                  } else {
                    $output .= "},";
                  }
                  echo $output;
                } ?>
              ],
            }).then(function (widget) {
              // Dispatch custom events to the page on each available widget listener,
              // so that actions can be taken based on that information if needed,
              // such as in the Gravity Forms wrapper. Also update hidden fields to
              // make data available in multiple ways on the page
              widget.listen(widget.eventTypes.WIDGET_LOADED, function (payload) {
                let event = new CustomEvent("wwidget-component-additional-info-loaded", {
                  detail: payload
                });

                window.dispatchEvent(event);
                widgetAiUpdateHiddenFields(payload);
              });
              widget.listen(widget.eventTypes.SAVE_SUCCESS, function (payload) {
                let event = new CustomEvent("wwidget-component-additional-info-save-success", {
                  detail: payload
                });

                window.dispatchEvent(event);
                widgetAiUpdateHiddenFields(payload);
              });
              widget.listen(widget.eventTypes.DELETE_SUCCESS, function (payload) {
                let event = new CustomEvent("wwidget-component-additional-info-delete-success", {
                  detail: payload
                });

                window.dispatchEvent(event);
              });
            });
        });

        function widgetAiUpdateHiddenFields(payload) {
          let aiDataField = document.querySelector('input[name="<?php echo $additional_info_data_field_name; ?>"]');
          let validationDataField = document.querySelector('input[name="<?php echo $additional_info_validation; ?>"]');

          aiDataField.value = JSON.stringify(payload);

          validationDataField.value = true;
          // TODO: Update with AI-widget specific validation logic
          // if( payload.incompleteRequiredFields ) {
          //   if( payload.incompleteRequiredFields.length > 0 ) {
          //     validationDataField.value = false;
          //   }
          // }
        }
    })()
</script>