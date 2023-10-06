<?php
/**
 * Wicket Additional Information
 * Description: Provides a widget containing the additional information form from Wicket
 *
 */

use Wicket\Client;

// The widget class
// http://www.wpexplorer.com/create-widget-plugin-wordpress
class wicket_additional_information extends WP_Widget {

	public $errors;

	// Main constructor
	public function __construct()
	{
		parent::__construct(
			'wicket_additional_information',
			__('Wicket Additional Information', 'wicket'),
			array(
				'customize_selective_refresh' => true,
			)
		);
	}

	public function form($instance) {
		return $instance;
	}

	public function update($new_instance, $old_instance) {
		return $old_instance;
	}

	// Display the widget
	public function widget($args, $instance)
	{
		
		$wicket_settings = get_wicket_settings();
	
		?>

		<script type="text/javascript">
			window.Wicket=function(doc,tag,id,script){
				var w=window.Wicket||{};if(doc.getElementById(id))return w;var ref=doc.getElementsByTagName(tag)[0];var js=doc.createElement(tag);js.id=id;js.src=script;ref.parentNode.insertBefore(js,ref);w._q=[];w.ready=function(f){w._q.push(f)};return w
			}(document,"script","wicket-widgets","<?php echo $wicket_settings['wicket_admin'] ?>/dist/widgets.js");
		</script>

		<div id="additional_info"></div>

		<?php 
		$environment = get_option('wicket_admin_settings_environment');
		$rcmp_information_schema = $environment[0] == 'prod' ? '14452756-67cd-41b6-94a1-658db279d2bc' : '14452756-67cd-41b6-94a1-658db279d2bc';
		?>

		<script type="text/javascript">
			(function (){
				Wicket.ready(function(){
					var widgetRoot = document.getElementById('additional_info');

					Wicket.widgets.editAdditionalInfo({
						loadIcons: true,
						rootEl: widgetRoot,
						apiRoot: '<?php echo $wicket_settings['api_endpoint'] ?>',
						accessToken: '<?php echo wicket_access_token_for_person(wicket_current_person_uuid()) ?>',
						resource: { type: "people", id: '<?php echo wicket_current_person_uuid(); ?>' },
						lang:  "<?php echo defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : 'en' ?>",
					}).then(function (widget) {
						widget.listen(widget.eventTypes.SAVE_SUCCESS, function(payload) {
							
						});
					});
				});
			})()
		</script>

	<?php

	}

}



// Register the widget
function register_custom_widget_wicket_additonal_information() {
	register_widget('wicket_additional_information');
}
add_action('widgets_init', 'register_custom_widget_wicket_additonal_information');
