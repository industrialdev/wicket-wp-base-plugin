<?php 

function get_component( $slug, array $args = array(), $output = true ) {
	/* $args will be available in the component file */
	if ( ! $output ) {
		ob_start();
	}
	$template_file = __DIR__ . "/components/{$slug}.php";
	//$template_file = locate_template( "components/{$slug}.php", false, false );
	if ( file_exists( $template_file ) ) :
		require ( $template_file );
	else :
		throw new \RuntimeException( "Could not find component $slug" );
	endif;
	if ( ! $output ) {
		return ob_get_clean();
	}
}

function component_exists( $slug ) {
	$template_file = __DIR__ . "/components/{$slug}.php";
	//$template_file = locate_template( "components/{$slug}.php", false, false );
	if ( file_exists( $template_file ) ) {
		return true;
	}
	return false;
}