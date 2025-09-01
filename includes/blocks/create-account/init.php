<?php

/**
 * Wicket Create Account block
 *
 **/

namespace Wicket\Blocks\Wicket_Create_Account;

/**
 * Create Account
 */
function site($block = [])
{
    // Try to use the widget class directly
    global $wp_widget_factory;
    if (isset($wp_widget_factory->widgets['wicket_create_account'])) {
        the_widget('wicket_create_account');
    } elseif (isset($wp_widget_factory->widgets['WicketWP\Widgets\CreateAccount'])) {
        the_widget('WicketWP\Widgets\CreateAccount');
    } else {
        echo '<p>Widget not available</p>';
    }
}
