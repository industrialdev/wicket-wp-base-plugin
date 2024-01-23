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
    $options['wicket-api-check'] = WicketAPICheck::class;
    return $options;
});

class WicketAPICheck extends OptionAbstract {
    public $view = 'wicket-api-check-prod';
    public function render()
    { 
      $client = wicket_api_client();
      ?>
      <tr valign="top">
        <th scope="row" class="titledesc">
          <?php echo __('Status', 'wicket'); ?>
        </th>
        <td class="forminp forminp-wicket-api-status">
          <?php if($client): ?>
            <span class="wicket-api-status positive"><?php echo __('CONNECTED', 'wicket'); ?></span>
          <?php else: ?>
            <span class="wicket-api-status negative"><?php echo __('NOT CONNECTED', 'wicket'); ?></span>
          <?php endif; ?>
        </td>
      </tr>
    <?php }
}