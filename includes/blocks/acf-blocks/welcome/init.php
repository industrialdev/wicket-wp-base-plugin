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

	echo '<h2>Welcome: '.wicket_person_name().'</h2>';
    echo get_field('block_welcome_description');
	
}
