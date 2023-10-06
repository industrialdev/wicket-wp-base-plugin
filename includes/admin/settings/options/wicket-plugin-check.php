<?php
/**
 * WPSettings Custom Option - Wicket Check For Required Plugins
 *
 * @package  Wicket\Admin
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * External dependencies
 */
use Jeffreyvr\WPSettings\Options\OptionAbstract;

add_filter('wp_settings_option_type_map', function($options){
    $options['wicket-plugin-check'] = WicketPluginCheck::class;
    return $options;
});

class WicketPluginCheck extends OptionAbstract {
    public $view = 'wicket-plugin-check';

    public function render()
    { ?>

        <?php if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) { 
            // All is good. Do nothing.
        } else { ?>

            <div class="notice notice-warning">
                <p><?php echo __('REQUIRED PLUGINS: The following plugins are required to be installed and activated in order to use the Memberships settings. 1. WooCommerce (Installed) | 2. WooCommerce Memberships (Not Installed) | 3. WooCommerce Subscriptions (Not Installed) | 4. Teams for WooCommerce Memberships (Required for Organization Memberships) (Not Installed)', 'wicket'); ?></p>
            </div>
        <?php } ?> 

        <?php }
    }