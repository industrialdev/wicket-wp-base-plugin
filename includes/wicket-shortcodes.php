<?php 

/**
  * Create a simple shortcode to output the Wicket widgets
  *
  */

// Contact Information
function wicket_contact_information_shortcode() {
		the_widget( 'wicket_contact_information' );
}
add_shortcode('wicket_contact_information', 'wicket_contact_information_shortcode');

// Additional Information
function wicket_additional_information_shortcode() {
		the_widget( 'wicket_additional_information' );
}
add_shortcode('wicket_additional_information', 'wicket_additional_information_shortcode');

// get_component() wrapper shortcode
function wicket_get_component_shortcode($atts) {
  $the_atts = shortcode_atts([
    "slug" => "",
    "args_json" => "{}", // e.g. {"attribute": {"sub-att":"val","sub-att2":"val"}}
  ], $atts);

  $component_args = json_decode( $the_atts['args_json'], true );

  if( !empty( $the_atts['slug'] ) ) {
    get_component( $the_atts['slug'], $component_args, true );
  }
  

}
add_shortcode('wicket_get_component', 'wicket_get_component_shortcode');