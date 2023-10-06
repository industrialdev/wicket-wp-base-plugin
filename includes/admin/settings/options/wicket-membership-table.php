<?php
/**
 * WPSettings Custom Option - Wicket Membership Overview Table
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
    $options['wicket-membership-overview'] = WicketMembershipOverview::class;
    return $options;
});

class WicketMembershipOverview extends OptionAbstract {
    public $view = 'wicket-membership-overview';

    public function render()
    { ?>

        <br />
        <input type="submit" value="<?php echo __('Refresh Wicket Memberships', 'wicket'); ?>" class="button wicket-sync-memberships">

        <p><?php echo __('A total of 6 Membership Tiers were found in your Wicket account.', 'wicket'); ?></p>

        <table class="widefat striped" id="wicket-memebership-overview">
        	<thead>
        		<tr>
        			<th>Wicket Membership Tier</th>
        			<th>Status</th>
        			<th>Type</th>
        			<th>WooCommerce Membership</th>
        			<th># Products</th>
        			<th># Teams</th>
        			<th># Members</th>
        		</tr>
        	</thead>
        	<tbody>
        		<tr>
        			<td>Individual</td>
        			<td>Active</td>
        			<td>Individual<span class="row-actions alignright"></span></td>
        			<td><a href="#" class="wc-membership-plan" data-list-id="individual_slug">Individual (#520)</a></td>
        			<td>1</td>
        			<td>-</td>
        			<td>13,064</td>
        		</tr>
        		<tr>
        			<td>Student</td>
        			<td>Inactive</td>
        			<td>Individual<span class="row-actions alignright"></span></td>
        			<td><em>None</em></td>
        			<td>-</td>
        			<td>-</td>
        			<td>-</td>
        		</tr>
        		<tr>
        			<td>Professional</td>
        			<td>Active</td>
        			<td>Corporate<span class="row-actions alignright"></span></td>
        			<td><a href="#" class="wc-membership-plan" data-list-id="corporate_slug">Professional (#523)</a></td>
        			<td>2</td>
        			<td>160</td>
        			<td>6,050</td>
        		</tr>
           	</tbody>
        </table>

    <?php }
}