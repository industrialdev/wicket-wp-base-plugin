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

		<?php
			$memberships = get_individual_memberships();
			if($memberships && isset($memberships['data'])):
			$amount_memberships = count($memberships['data']);
		?>

			<br />
			<input type="submit" value="<?php echo __('Refresh Wicket Memberships', 'wicket'); ?>" class="button wicket-sync-memberships">

			<p><?php echo sprintf(__('A total of %s Membership Tier(s) were found in your Wicket account.', 'wicket'), $amount_memberships); ?></p>

			<table class="widefat striped" id="wicket-memebership-overview">
				<thead>
					<tr>
						<th><?php echo __('Wicket Membership Tier', 'wicket'); ?></th>
						<th><?php echo __('Status', 'wicket'); ?></th>
						<th><?php echo __('Type', 'wicket'); ?></th>
						<th><?php echo __('WooCommerce Membership', 'wicket'); ?></th>
						<th><?php echo __('# Products', 'wicket'); ?></th>
						<th><?php echo __('# Teams', 'wicket'); ?></th>
						<th><?php echo __('# Members', 'wicket'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php  foreach ($memberships['data'] as $membership):
						$active = (isset($membership['attributes']['active']) && $membership['attributes']['active'] == 1) ? 'Active' : 'Inactive';
						$membership_slug = ($membership['attributes']['slug']) ?? $membership['attributes']['slug'];
						$membership_type = ($membership['attributes']['type']) ?? $membership['attributes']['type'];
						$membership_plan = (function_exists('wc_memberships_get_membership_plan')) ? wc_memberships_get_membership_plan($membership_slug) : '';
						$membership_plan_id = ($membership_plan) ? $membership_plan->get_id() : '';
						$membership_product_ids = ($membership_plan) ? get_post_meta($membership_plan->get_id(), '_product_ids') : '';
					?>
						<tr>
							<td><?php echo $membership['attributes']['name_en']; ?></td>
							<td><?php echo $active; ?></td>
							<td><?php echo ucfirst($membership_type); ?></td>
							<td>
								<?php if($membership_plan){ ?>
									<a href="<?php echo get_admin_url(); ?>/post.php?post=<?php echo $membership_plan_id; ?>&action=edit&lang=en" class="wc-membership-plan" data-list-id="individual_slug" target="_blank"><?php echo $membership_plan->get_name(); ?></a>
								<?php } ?>
							</td>
							<td>
								<?php 
									if($membership_product_ids && isset($membership_product_ids[0])){
										echo count($membership_product_ids[0]);
									}
								?>
							</td>
							<td>-</td>
							<td>
								<?php if($membership_plan){
									$view_members = admin_url( "edit.php?post_type=wc_user_membership&action=-1&post_parent={$membership_plan_id}" );
									echo '<a href="' . esc_url( $view_members ) . '" title="' . esc_html__( 'View Members', 'woocommerce-memberships' ) . '">';
									echo $membership_plan->get_memberships_count();
									echo '</a>';
								} ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	<?php }
}