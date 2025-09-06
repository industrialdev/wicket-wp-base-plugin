<?php

declare(strict_types=1);

namespace WicketWP;

// No direct access
defined('ABSPATH') || exit;

/**
 * Class Includes
 *
 * Centralized file inclusion for the Wicket plugin
 *
 * @package WicketWP
 */
class Includes {

    /**
     * Instance of the main plugin class
     *
     * @var WicketWP
     */
    protected $plugin;

    /**
     * Constructor
     *
     * @param WicketWP $plugin Instance of the main plugin class
     */
    public function __construct($plugin) {
        $this->plugin = $plugin;

        // Hook into WordPress
        add_action('init', [$this, 'wicket_includes'], 0);
    }

    /**
     * Includes all the necessary files for the plugin.
     *
     * @return void
     */
    public function wicket_includes()
    {
        // Debug
        include_once WICKET_PLUGIN_DIR . 'includes/wicket-debug.php';

        // Include admin files
        if (is_admin()) {
            // include admin class
            include_once WICKET_PLUGIN_DIR . 'includes/admin/class-wicket-admin.php';
        }

        // Include to allow other functions to check if plugins are active on front end
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');

        // Include MDP Helpers
        include_once WICKET_PLUGIN_DIR . 'includes/helpers/helper-init.php';

        // Include wicket shortcodes
        include_once WICKET_PLUGIN_DIR . 'includes/wicket-shortcodes.php';

        // Include wicket components
        include_once WICKET_PLUGIN_DIR . 'includes/wicket-components.php';

        // Group subscriptions
        include_once WICKET_PLUGIN_DIR . 'includes/integrations/group-subscriptions.php';

        // Mailtrap settings for stage
        include_once WICKET_PLUGIN_DIR . 'includes/integrations/wicket-mailtrap.php';


// Deprecated REST endpoints
include_once WICKET_PLUGIN_DIR . 'includes/deprecated.php';

        // Include Wicket MDP Schema Merge Tag Generator
        include_once WICKET_PLUGIN_DIR . 'includes/class-wicket-mdp-schema-merge-tag-generator.php';

        // Include event tickets attendee registered touchpoints
        if (wicket_get_option('wicket_admin_settings_tp_event_ticket_attendees_checkin') === '1') {
            include_once WICKET_PLUGIN_DIR . 'includes/touchpoints/event_ticket_attendees_checkin.php';
        }

        // Include event tickets attendee registered touchpoints
        if (wicket_get_option('wicket_admin_settings_tp_event_ticket_attendees_rsvp') === '1') {
            include_once WICKET_PLUGIN_DIR . 'includes/touchpoints/event_ticket_attendees_rsvp.php';
        }

        // Include event tickets attendee field hooks to provide last name field by default, rename 'name' field to first name and re-sort fields
        // Not sure if this applies to rsvp fields as well or just attendee registration, but will include it if any of the above options are enabled
        if (
            wicket_get_option('wicket_admin_settings_tp_event_ticket_attendees') === '1' ||
            wicket_get_option('wicket_admin_settings_tp_event_ticket_attendees_checkin') === '1' ||
            wicket_get_option('wicket_admin_settings_tp_event_ticket_attendees_rsvp') === '1'
        ) {
            include_once WICKET_PLUGIN_DIR . 'includes/touchpoints/event_ticket_attendees_field_hooks.php';
        }

        // Include wp-cassify sync
        if (is_plugin_active('wp-cassify/wp-cassify.php') && (wicket_get_option('wicket_admin_settings_wpcassify_sync_roles') === '1')) {
            include_once WICKET_PLUGIN_DIR . 'includes/integrations/wicket-cas-role-sync.php';
        }

        // Include woocommerce functions
        if (is_plugin_active('woocommerce/woocommerce.php')) {
            // Include woo order touchpoints
            if (wicket_get_option('wicket_admin_settings_tp_woo_order') === '1') {
                include_once WICKET_PLUGIN_DIR . 'includes/touchpoints/woocommerce-order.php';
            }

            // Include event tickets attendee registered touchpoints
            if (wicket_get_option('wicket_admin_settings_tp_event_ticket_attendees') === '1') {
                include_once WICKET_PLUGIN_DIR . 'includes/touchpoints/woocommerce_payment_complete_event_ticket_attendees.php';
            }

            if (wicket_get_option('wicket_admin_settings_woo_sync_addresses') === '1') {
                include_once WICKET_PLUGIN_DIR . 'includes/integrations/wicket-woocommerce-checkout-addresses.php';
            }

            include_once WICKET_PLUGIN_DIR . 'includes/integrations/org-search-select-woocommerce.php';
            include_once WICKET_PLUGIN_DIR . 'includes/integrations/wicket-woocommerce-customizations.php';
        }

        // Include WooCommerce memberships team functions
        if (is_plugin_active('woocommerce-memberships-for-teams/woocommerce-memberships-for-teams.php')) {
            // include_once WICKET_PLUGIN_DIR . 'includes/integrations/wicket-woocommerce-membership-team-metabox.php';
            include_once WICKET_PLUGIN_DIR . 'includes/integrations/wicket-woocommerce-membership-team.php';
        }

        // Include user switching functions
        if (is_plugin_active('user-switching/user-switching.php')) {
            include_once WICKET_PLUGIN_DIR . 'includes/integrations/wicket-user-switching-sync.php';
        }
    }
}
