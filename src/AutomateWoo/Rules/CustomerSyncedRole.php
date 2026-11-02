<?php

declare(strict_types=1);

namespace WicketWP\AutomateWoo\Rules;

// No direct access
defined('ABSPATH') || exit;

use AutomateWoo\DataTypes\DataTypes;
use AutomateWoo\Rules\Preloaded_Select_Rule_Abstract;

/**
 * AutomateWoo rule "Customer - Synced User Roles".
 *
 * Syncs the customer's roles from the MDP, then matches all of the customer's
 * WordPress roles. MDP tags and membership tiers reach WordPress as roles only
 * through the role sync, and members often do not log in before a workflow
 * runs, so the rule syncs first. The built-in "Customer - User Role" rule
 * compares only the first role of the user.
 *
 * Saved workflows store the rule key `wicket_customer_synced_role`. Do not change it.
 *
 * @see sync_wicket_data_for_person()
 * @see atlas://quirks/automatewoo-customer-role-rule-first-role-only.md
 * @see atlas://plans/feature-automatewoo-synced-role-rule.md
 */
class CustomerSyncedRole extends Preloaded_Select_Rule_Abstract
{
    /**
     * Rule key that saved workflows store.
     */
    public const KEY = 'wicket_customer_synced_role';

    /**
     * Data item the rule validates.
     *
     * @var string
     */
    public $data_item = DataTypes::CUSTOMER;

    /**
     * Match any, all, or none of the selected roles.
     *
     * @var bool
     */
    public $is_multi = true;

    /**
     * Set the compare types and the title.
     *
     * @return void
     */
    public function init()
    {
        parent::init();

        $this->title = __('Customer - Synced User Roles', 'wicket-base');
    }

    /**
     * List the WordPress roles as the rule choices.
     *
     * @global \WP_Roles $wp_roles
     *
     * @return array<string, string> Role names keyed by role slug.
     */
    public function load_select_choices()
    {
        global $wp_roles;

        $choices = [];
        foreach ($wp_roles->roles as $key => $role) {
            $choices[$key] = $role['name'];
        }

        return $choices;
    }

    /**
     * Sync the customer's roles from the MDP, then match the selected roles.
     *
     * A guest has no WordPress user and no roles. If the sync fails, the rule
     * matches the roles the user has, so the workflow still gets a decision.
     *
     * @param \AutomateWoo\Customer $customer Workflow customer.
     * @param string                $compare  matches_any, matches_all, or matches_none.
     * @param string[]              $value    Selected role slugs.
     *
     * @return bool True when the customer's roles match.
     */
    public function validate($customer, $compare, $value)
    {
        $user = $customer->get_user();
        if (!$user) {
            return $this->validate_select([], $compare, $value);
        }

        try {
            sync_wicket_data_for_person($user->user_login);
        } catch (\Throwable $e) {
            Wicket()->log()->error('AutomateWoo synced role rule: role sync failed: ' . $e->getMessage(), [
                'source' => 'wicket-base',
                'user_id' => $user->ID,
            ]);
        }

        // The sync replaces the roles on a separate WP_User instance, so read them again.
        $user = get_userdata($user->ID);

        return $this->validate_select($user ? $user->roles : [], $compare, $value);
    }
}
