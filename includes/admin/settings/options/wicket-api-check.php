<?php
/**
 * WPSettings Custom Option - Wicket API Connection Check - Production
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
    $options['wicket-api-check-prod'] = WicketAPICheckProd::class;
    $options['wicket-api-check-stage'] = WicketAPICheckStage::class;
    return $options;
});

class WicketAPICheckProd extends OptionAbstract {
    public $view = 'wicket-api-check-prod';
    public function render()
    { ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <?php echo __('Status', 'wicket'); ?>
            </th>
            <td class="forminp forminp-wicket-api-status">
                <span class="wicket-api-status positive"><?php echo __('CONNECTED', 'wicket'); ?></span>
            </td>
        </tr>
    <?php }
}

class WicketAPICheckStage extends OptionAbstract {
    public $view = 'wicket-api-check-stage';
    public function render()
    { ?>

        <tr valign="top">
            <th scope="row" class="titledesc">
                <?php echo __('Status', 'wicket'); ?>
            </th>
            <td class="forminp forminp-wicket-api-status">
                <span class="wicket-api-status negative"><?php echo __('NOT CONNECTED', 'wicket'); ?></span>
            </td>
        </tr>
    <?php }
}