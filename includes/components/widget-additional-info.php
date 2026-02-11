<?php
$defaults = [
    'classes'                          => [],
    'additional_info_data_field_name'  => 'additional-info-data',
    'validation_data_field_name'       => 'additional-info-validation',
    'resource_type'                    => 'people',
    'org_uuid'                         => '',
    'schemas_and_overrides'            => [],
];
$args = wp_parse_args($args, $defaults);
$classes = $args['classes'];
$additional_info_data_field_name = $args['additional_info_data_field_name'];
$additional_info_validation = $args['validation_data_field_name'];
$resource_type = $args['resource_type'];
$org_uuid = (string) $args['org_uuid'];
$schemas_and_overrides = $args['schemas_and_overrides'];
$unique_widget_id = rand(1, PHP_INT_MAX);

// Example $schemas_and_overrides:
/*
[
  [
    'slug'           => '1234-1234-1234-1234', // If present, will take precedence over id
    'id'             => '1234-1234-1234-1234',
    'resourceSlug'   => '1234-1234-1234-1234', // If present, will take precedence over id
    'resourceId'     => '1234-1234-1234-1234',
    'showAsRequired' => true,
  ],
]
*/

$wicket_settings = get_wicket_settings();
?>

<div class="wicket-section <?php implode(' ', $classes); ?>"
  id="<?php echo esc_attr($unique_widget_id); ?>"
  data-widget-id="<?php echo esc_attr($unique_widget_id); ?>"
  role="complementary">
  <h2><?php _e('Additional Info', 'wicket'); ?></h2>
  <div id="additional-info-<?php echo esc_attr($unique_widget_id); ?>"></div>
  <input type="hidden" name="<?php echo $additional_info_data_field_name; ?>" />
  <input type="hidden" name="<?php echo $additional_info_validation; ?>" />
</div>

<script type="text/javascript">
    if (typeof window.Wicket === 'undefined') {
        window.Wicket=function(doc,tag,id,script){
        var w=window.Wicket||{};if(doc.getElementById(id))return w;var ref=doc.getElementsByTagName(tag)[0];var js=doc.createElement(tag);js.id=id;js.src=script;ref.parentNode.insertBefore(js,ref);w._q=[];w.ready=function(f){w._q.push(f)};return w
        }(document,"script","wicket-widgets","<?php echo esc_url($wicket_settings['wicket_admin']); ?>/dist/widgets.js");
    }
</script>

<?php
  $access_token = wicket_access_token_for_person(wicket_current_person_uuid());

  if ($resource_type == 'organizations') {
    $access_token = wicket_get_access_token(wicket_current_person_uuid(), $org_uuid);
  }
?>

<script type="text/javascript">
    document.addEventListener("DOMContentLoaded", function() {
        const widgetAdditionalInfoDebug = {
            enabled: true,
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
        Wicket.ready(function () {

            let widgetRoot_<?php echo esc_js($unique_widget_id); ?> = document.getElementById('additional-info-<?php echo esc_attr($unique_widget_id); ?>');
            widgetAdditionalInfoDebug.log('WGF-AI: init', {
                widgetId: '<?php echo esc_js($unique_widget_id); ?>',
                resourceType: '<?php echo esc_js($resource_type); ?>',
                orgUuid: '<?php echo esc_js($org_uuid); ?>'
            });

            const options = {
              loadIcons: true,
              rootEl: widgetRoot_<?php echo esc_js($unique_widget_id); ?>,
              apiRoot: '<?php echo $wicket_settings['api_endpoint'] ?>',
              accessToken: '<?php echo $access_token; ?>',
              resource: {
                type: '<?php echo $resource_type; ?>',
                id: '<?php if ($resource_type == 'people') {
                    echo wicket_current_person_uuid();
                } elseif ($resource_type == 'organizations') {
                    echo $org_uuid;
                } ?>',
              },
              lang: "<?php echo esc_js(wicket_get_current_language()); ?>",
              schemas: [
                <?php
                $schema_outputs = [];
foreach ($schemas_and_overrides as $schema) {
    $schema_obj = [];

    if (isset($schema['slug']) && !empty($schema['slug'])) {
        $schema_obj[] = "slug: '" . esc_js($schema['slug']) . "'";
        if (isset($schema['resourceSlug']) && !empty($schema['resourceSlug'])) {
            $schema_obj[] = "resourceSlug: '" . esc_js($schema['resourceSlug']) . "'";
        }
    } else {
        $schema_obj[] = "id: '" . esc_js($schema['id']) . "'";
        if (isset($schema['resourceId']) && !empty($schema['resourceId'])) {
            $schema_obj[] = "resourceId: '" . esc_js($schema['resourceId']) . "'";
        }
    }

    if (isset($schema['showAsRequired']) && $schema['showAsRequired']) {
        $schema_obj[] = 'showAsRequired: true';
    }

    $schema_outputs[] = '{ ' . implode(', ', $schema_obj) . ' }';
}
echo implode(",\n                ", $schema_outputs);
?>
              ],
            };

            Wicket.widgets.editAdditionalInfo(options).then(function(widget) {
              widget.listen(widget.eventTypes.WIDGET_LOADED, function(payload) {
                window.dispatchEvent(new CustomEvent("wwidget-component-additional-info-loaded"));
                window.dispatchEvent(new CustomEvent("wwidget-component-common-loaded"));
                widgetAiUpdateHiddenFields(payload);
                widgetAdditionalInfoDebug.log('WGF-AI: loaded', payload);
              });
              widget.listen(widget.eventTypes.SAVE_SUCCESS, function (payload) {
                let event = new CustomEvent("wwidget-component-additional-info-save-success", {
                  detail: payload
                });
                window.dispatchEvent(event);
                widgetAiUpdateHiddenFields(payload);
                widgetAdditionalInfoDebug.log('WGF-AI: save success', payload);
              });
              widget.listen(widget.eventTypes.VALIDATION_ERROR, function (payload) {
                widgetAiUpdateHiddenFields(payload);
                widgetAdditionalInfoDebug.warn('WGF-AI: validation error', payload);
              });
              widget.listen(widget.eventTypes.SAVE_ERROR, function (payload) {
                widgetAiUpdateHiddenFields(payload);
                widgetAdditionalInfoDebug.warn('WGF-AI: save error', payload);
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
          let aiDataFields = document.querySelectorAll('input[name="<?php echo $additional_info_data_field_name; ?>"]');
          let validationDataFields = document.querySelectorAll('input[name="<?php echo $additional_info_validation; ?>"]');

          if (!aiDataFields.length || !validationDataFields.length) {
              widgetAdditionalInfoDebug.warn('WGF-AI: hidden fields missing', {
                  dataFieldCount: aiDataFields.length,
                  validationFieldCount: validationDataFields.length
              });
              return;
          }

          const payloadValue = JSON.stringify(payload);
          aiDataFields.forEach((field) => {
              field.value = payloadValue;
          });

          validationDataFields.forEach((field) => {
              field.value = true;
              if (typeof jQuery !== 'undefined') {
                jQuery(field).trigger('change');
              }
          });

          widgetAdditionalInfoDebug.log('WGF-AI: hidden fields updated', {
              dataField: '<?php echo $additional_info_data_field_name; ?>',
              validationField: '<?php echo $additional_info_validation; ?>',
              dataFieldCount: aiDataFields.length,
              validationFieldCount: validationDataFields.length,
              dataLength: payloadValue.length,
              validationValues: Array.from(validationDataFields).map((field) => field.value)
          });
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
