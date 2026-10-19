<?php
/**
 * WPSettings Custom Option - Group Role Entity Object Slug check.
 *
 * Compares the saved slug with the group role entity code looked up in Wicket.
 *
 * @version  1.0.0
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/*
 * External dependencies
 */
use Jeffreyvr\WPSettings\Options\OptionAbstract;

add_filter('wp_settings_option_type_map', function ($options) {
    $options['wicket-group-role-entity-check'] = WicketGroupRoleEntityCheck::class;

    return $options;
});

class WicketGroupRoleEntityCheck extends OptionAbstract
{
    public $view = 'wicket-group-role-entity-check';

    public function render()
    {
        if (!wicket_api_client()) {
            return;
        }

        $saved_slug = (string) wicket_get_option('wicket_admin_settings_group_assignment_role_entity_object', '');
        $entity_code = wicket_get_group_role_entity_code();
        ?>
      <tr valign="top">
        <th scope="row" class="titledesc">
          <?php echo __('Group Role Entity Check', 'wicket'); ?>
        </th>
        <td class="forminp forminp-wicket-group-role-entity-check">
          <?php if ($entity_code === ''): ?>
            <p><?php echo __('Could not look up the group role entity code in Wicket.', 'wicket'); ?></p>
          <?php elseif ($saved_slug === $entity_code): ?>
            <span class="wicket-api-status positive"><?php echo __('CORRECT', 'wicket'); ?></span>
            <p><?php printf(__('%s is the group role entity code in Wicket for this site.', 'wicket'), '<code>' . esc_html($entity_code) . '</code>'); ?></p>
          <?php else: ?>
            <span class="wicket-api-status negative"><?php echo __('NOT FOUND', 'wicket'); ?></span>
            <p style="color:red;font-weight:bold"><?php printf(
                __('Group role entity slug %1$s not found. Please correct this value to use this feature. The group role entity code in Wicket is %2$s.', 'wicket'),
                '<code>' . esc_html($saved_slug) . '</code>',
                '<code>' . esc_html($entity_code) . '</code>'
            ); ?></p>
          <?php endif; ?>
        </td>
      </tr>
    <?php }
    }
