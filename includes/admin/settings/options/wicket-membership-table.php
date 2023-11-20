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
			$categories = wicket_get_option( 'wicket_admin_settings_membership_categories' );
			$memberships = $this->get_memberships_table_data($categories);
			if($memberships):
				$amount_memberships = count($memberships);
			// echo '<pre>';
			// print_r($memberships);
			// echo '</pre>';

			// die;
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
					<?php foreach($memberships as $membership): ?>
						<tr>
							<td><?php echo $membership['name']; ?></td>
							<td><?php echo $membership['status']; ?></td>
							<td><?php echo ucfirst($membership['type']); ?></td>
							<td>
								<?php if($membership['membership_plan_id']){
								$membership_plan = (function_exists('wc_memberships_get_membership_plan')) ? wc_memberships_get_membership_plan($membership['slug']) : '';
								?>
									<a href="<?php echo get_admin_url(); ?>/post.php?post=<?php echo $membership['membership_plan_id']; ?>&action=edit&lang=en" class="wc-membership-plan" data-list-id="individual_slug" target="_blank"><?php echo $membership_plan->get_name(); ?></a>
								<?php } ?>
							</td>
							<td>
								<?php 
									if($membership['product_ids'] && isset($membership['product_ids'][0])){
										echo count($membership['product_ids'][0]);
									}
								?>
							</td>
							<td>-</td>
							<td>
								<?php 
								if($membership['membership_plan_id']){
									$view_members = admin_url( "edit.php?post_type=wc_user_membership&action=-1&post_parent={$membership['membership_plan_id']}" );
									echo '<a href="' . esc_url( $view_members ) . '" title="' . esc_html__( 'View Members', 'woocommerce-memberships' ) . '">';
									echo $membership_plan->get_memberships_count();
									echo '</a>';
								}
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
				<?php endif; ?>
			</table>
	<?php }


	public function get_memberships_table_data($categories = null)
	{
		$memberships = [];
		$individual_memberships = get_individual_memberships();
		if($individual_memberships && isset($individual_memberships['data'])) {

			foreach ($individual_memberships['data'] as $key => $value) {
				$has_category = false;
				$membership_slug = ($value['attributes']['slug']) ?? $value['attributes']['slug'];
				$membership_plan = (function_exists('wc_memberships_get_membership_plan')) ? wc_memberships_get_membership_plan($membership_slug) : '';
				$product_ids = ($membership_plan) ? get_post_meta($membership_plan->get_id(), '_product_ids') : '';
				if($product_ids && isset($product_ids[0]) && $categories){
					foreach($categories as $category_id) {
						foreach ( $product_ids[0] as $product_id ) {
							if(has_term($category_id, 'product_cat', $product_id)){
								$has_category = true;
							}
						}
					}
				}

				if(($has_category && $categories) || (!$categories)){
					$memberships[$key]['status'] = (isset($value['attributes']['active']) && $value['attributes']['active'] == 1) ? 'Active' : 'Inactive';
					$memberships[$key]['type'] = ($value['attributes']['type']) ?? $value['attributes']['type'];
					$memberships[$key]['name'] = ($value['attributes']['name_en']) ?? $value['attributes']['name_en'];
					$memberships[$key]['slug'] = $membership_slug;
					$memberships[$key]['membership_plan_id'] = ($membership_plan) ? $membership_plan->get_id() : '';
					$memberships[$key]['product_ids'] = $product_ids;
				}

				
			}

		}

		return $memberships;
	}
}