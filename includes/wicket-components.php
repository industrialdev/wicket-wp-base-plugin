<?php 

function get_component( $slug, array $args = array(), $output = true ) {
	/* $args will be available in the component file */
	if ( ! $output ) {
		ob_start();
	}

	// Try themes first in case an override or custom component was added to the child theme,
	// otherwise use the component file in the plugin if present
	$theme_component_file = locate_template( "components/{$slug}.php", false, false );
	$plugin_component_file = __DIR__ . "/components/{$slug}.php";
	
	if ( file_exists( $theme_component_file ) ) {
		require ( $theme_component_file );
	} else if ( file_exists( $plugin_component_file ) ) {
		require( $plugin_component_file );
	} else {
		throw new \RuntimeException( "Could not find component $slug" );
	}
		
	if ( ! $output ) {
		return ob_get_clean();
	}
}

function component_exists( $slug ) {
	$theme_component_file = locate_template( "components/{$slug}.php", false, false );
	$plugin_component_file = __DIR__ . "/components/{$slug}.php";

	if ( file_exists( $theme_component_file ) ) {
		return true;
	} else if ( file_exists( $plugin_component_file ) ) {
		return true;
	}

	return false;
}

function get_components_dir() {
	return __DIR__ . "/components/";
}

// Remove WP content filter that sometimes breaks component rendering
remove_filter('the_content', 'wptexturize');