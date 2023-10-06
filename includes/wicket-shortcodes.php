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