<?php

// No direct access
defined('ABSPATH') || exit;

if (!function_exists('wicket_remove_end_date_from_subscription')) {
    /**
     * Remove the subscription end date from an AutomateWoo workflow subscription.
     *
     * @param object $workflow AutomateWoo workflow instance.
     * @return void
     */
    function wicket_remove_end_date_from_subscription($workflow)
    {
        $subscription = $workflow->data_layer()->get_subscription();
        if (!empty($subscription)) {
            $subscription->delete_date('end');
        }
    }
}

if (!function_exists('wicket_remove_next_payment_date_from_subscription')) {
    /**
     * Remove the next payment date from an AutomateWoo workflow subscription.
     *
     * @param object $workflow AutomateWoo workflow instance.
     * @return void
     */
    function wicket_remove_next_payment_date_from_subscription($workflow)
    {
        $subscription = $workflow->data_layer()->get_subscription();
        if (!empty($subscription)) {
            $subscription->delete_date('next_payment');
        }
    }
}

if (!function_exists('wicket_process_renewal_subscription_payment')) {
    /**
     * Trigger scheduled subscription payment processing from an AutomateWoo workflow.
     *
     * @param object $workflow AutomateWoo workflow instance.
     * @return void
     */
    function wicket_process_renewal_subscription_payment($workflow)
    {
        $subscription = $workflow->data_layer()->get_subscription();
        if (!empty($subscription)) {
            do_action('woocommerce_scheduled_subscription_payment', $subscription->get_id());
        }
    }
}

if (!function_exists('wicket_generate_renewal_order')) {
    /**
     * Put subscription on-hold and generate a renewal order from an AutomateWoo workflow.
     *
     * Includes a deduplication guard: if the subscription's most-recent renewal order
     * already needs payment (pending / failed / on-hold), no new order is created.
     * AutomateWoo workflows can fire more than once while a subscription remains
     * on-hold (scheduled daily runs, retry events, etc.) — without this guard each
     * firing would produce an additional pending renewal order.
     *
     * Originated from rahb-website-wordpress/src/web/app/themes/wicket-child/custom/woocommerce.php
     * — wicket_generate_renewal_order() — and ported here as the canonical base-plugin version.
     *
     * @param object $workflow AutomateWoo workflow instance.
     * @return void
     */
    function wicket_generate_renewal_order($workflow)
    {
        $subscription = $workflow->data_layer()->get_subscription();
        if (empty($subscription)) {
            return;
        }

        // Bail if an unpaid renewal order already exists to prevent duplicates when
        // the workflow fires more than once while the subscription is on-hold.
        $last_renewal = wcs_get_last_renewal_order($subscription);
        if ($last_renewal && $last_renewal->needs_payment()) {
            return;
        }

        $subscription->update_status('on-hold'); // must be on-hold to accept payment
        wcs_create_renewal_order($subscription);
    }
}
