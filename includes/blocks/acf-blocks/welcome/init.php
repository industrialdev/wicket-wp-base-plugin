<?php
/**
 * Wicket Welcome block
 *
 **/

namespace Wicket\Blocks\Wicket_Welcome;

/**
 * Admin Welcome
 */
function site( $block = [] ) {
$wicket_person = wicket_person_has_uuid();

	if ( $wicket_person ) {
		$person = wicket_current_person();
		echo '<h2>Welcome: '.$person->given_name.'</h2>';
    	echo get_field('block_welcome_description');
	}
	
}
