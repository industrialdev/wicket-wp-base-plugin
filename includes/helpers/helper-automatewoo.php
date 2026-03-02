<?php

// No direct access
defined('ABSPATH') || exit;

if ( ! function_exists( 'wicket_remove_end_date_from_subscription' ) ) {
  /**
   * Remove the subscription end date from an AutomateWoo workflow subscription.
   *
   * @param object $workflow AutomateWoo workflow instance.
   * @return void
   */
  function wicket_remove_end_date_from_subscription($workflow) {
    $subscription = $workflow->data_layer()->get_subscription();
    if ( !empty($subscription) ) {
        $subscription->delete_date('end');
    }
  }
}

if ( ! function_exists( 'wicket_remove_next_payment_date_from_subscription' ) ) {
  /**
   * Remove the next payment date from an AutomateWoo workflow subscription.
   *
   * @param object $workflow AutomateWoo workflow instance.
   * @return void
   */
  function wicket_remove_next_payment_date_from_subscription($workflow) {
    $subscription = $workflow->data_layer()->get_subscription();
    if ( !empty($subscription) ) {
        $subscription->delete_date('next_payment');
    }
  }
}

if ( ! function_exists( 'wicket_process_renewal_subscription_payment' ) ) {
  /**
   * Trigger scheduled subscription payment processing from an AutomateWoo workflow.
   *
   * @param object $workflow AutomateWoo workflow instance.
   * @return void
   */
  function wicket_process_renewal_subscription_payment($workflow) {
    $subscription = $workflow->data_layer()->get_subscription();
    if ( !empty($subscription) ) {
      do_action( 'woocommerce_scheduled_subscription_payment', $subscription->get_id() );
    }
  }
}

if ( ! function_exists( 'wicket_generate_renewal_order' ) ) {
  /**
   * Put subscription on-hold and generate a renewal order from an AutomateWoo workflow.
   *
   * @param object $workflow AutomateWoo workflow instance.
   * @return void
   */
  function wicket_generate_renewal_order($workflow) {
    $subscription = $workflow->data_layer()->get_subscription();
    if ( !empty($subscription) ) {
      $subscription->update_status('on-hold'); //must be on-hold to accept payment
      wcs_create_renewal_order($subscription);
    }
  }
}
