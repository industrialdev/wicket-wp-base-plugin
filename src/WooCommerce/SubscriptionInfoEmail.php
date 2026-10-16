<?php

declare(strict_types=1);

namespace WicketWP\WooCommerce;

// No direct access
defined('ABSPATH') || exit;

/**
 * Hides the WooCommerce Subscriptions "Subscription information" table from order emails.
 *
 * WooCommerce Subscriptions appends the table (start date, end date, recurring
 * total) to every order email linked to a subscription, customer and admin alike.
 * Renewal order emails are often sent before membership processing updates the
 * subscription dates, so the table shows the current term and confuses members.
 * The table is hidden unless the site opts in from the WooCommerce settings.
 */
class SubscriptionInfoEmail
{
    public const OPTION_SHOW = 'wicket_admin_settings_woo_email_show_subscription_info';

    private const EMAIL_HOOK = 'woocommerce_email_after_order_table';
    private const WCS_CALLBACK = 'WC_Subscriptions_Order::add_sub_info_email';

    /**
     * Register hooks.
     *
     * WooCommerce Subscriptions registers its callback when its plugin file loads,
     * so it is in place by init. init also fires in every context that sends
     * emails (admin, AJAX, REST, cron/Action Scheduler, WP-CLI), unlike wp.
     *
     * @return void
     */
    public function init(): void
    {
        add_action('init', [$this, 'maybe_hide_subscription_info']);
    }

    /**
     * Remove the Subscriptions email callback unless the site opted in.
     *
     * @return void
     */
    public function maybe_hide_subscription_info(): void
    {
        if ($this->is_shown()) {
            return;
        }

        // False when WooCommerce Subscriptions is inactive. Look the priority up
        // rather than hard-coding 15 so a future WCS change can't break removal.
        $priority = has_action(self::EMAIL_HOOK, self::WCS_CALLBACK);
        if ($priority === false) {
            return;
        }

        remove_action(self::EMAIL_HOOK, self::WCS_CALLBACK, $priority);
    }

    /**
     * Whether the site opted in to showing the table.
     *
     * Never-saved and unchecked settings both mean hidden. The checkbox saves
     * '1', but values written by WP-CLI or code (1, true, 'yes') also count.
     *
     * @return bool
     */
    private function is_shown(): bool
    {
        $settings = get_option('wicket_settings', []);

        return is_array($settings)
            && filter_var($settings[self::OPTION_SHOW] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
}
