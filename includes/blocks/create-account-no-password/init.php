<?php
/**
 * Wicket Create Account No Password block
 */
namespace Wicket\Blocks\Wicket_Create_Account_No_Password;
function site($block = []) {
    global $wp_widget_factory;
    if (isset($wp_widget_factory->widgets['wicket_create_account_no_password'])) {
        the_widget('wicket_create_account_no_password');
    } elseif (isset($wp_widget_factory->widgets['WicketWP\\Widgets\\CreateAccountNoPassword'])) {
        the_widget('WicketWP\\Widgets\\CreateAccountNoPassword');
    } else {
        echo '<p>Widget not available</p>';
    }
}
