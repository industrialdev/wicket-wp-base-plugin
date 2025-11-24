<?php

/**
 * Since we need to check settings to determine rendering on the public facing side, we need some functions outside of the admin UI.
 */

/**
 * Check if finance system is enabled
 *
 * @return bool True if enabled, false otherwise
 */
function wicket_is_finance_system_enabled() {
	return wicket_get_finance_option( 'wicket_finance_enable_system', '0' ) === '1';
}

/**
 * Helper function to get finance setting value
 *
 * @param string $option_name The option name
 * @param mixed  $default     Default value if option doesn't exist
 * @return mixed The option value
 */
function wicket_get_finance_option( $option_name, $default = null ) {
	return wicket_get_option( $option_name, $default );
}