<?php

use WicketWP\Log;

/**
 * Hide the Group Product Tab unless subscription product chosen.
 */
add_action('admin_footer', 'custom_toggle_wicket_tab_js');
function custom_toggle_wicket_tab_js()
{
    if (!get_current_screen() || get_current_screen()->post_type !== 'product') {
        return;
    }
    ?>
  <script>
    jQuery(function($) {
      function toggleWicketTab() {
        const type = $('#product-type').val();
        console.log('product_type: ' + type)
        if (type === 'subscription' || type === 'variable-subscription') {
          $('.group_product_assignment_tab_tab').show();
        } else {
          $('.group_product_assignment_tab_tab').hide();
        }
      }

      toggleWicketTab();
      $('#product-type').on('change', toggleWicketTab);
    });
  </script>
  <?php
}

/*
 * Show group product tab on group category products
 */
add_filter('woocommerce_product_data_tabs', 'wicket_base_product_data_tabs');

function wicket_base_product_data_tabs($tabs)
{
    if (empty(wicket_get_option('wicket_admin_settings_group_assignment_subscription_products'))) {
        return $tabs;
    }
    global $post;
    $group_product_category = wicket_get_option('wicket_admin_settings_group_assignment_product_category');
    if (has_term($group_product_category, 'product_cat', $post->ID)) {
        $tabs['group_product_assignment_tab'] = [
            'label' => __('Group Product Assignment', 'wicket-child'),
            'target' => 'group_assignment_product',
        ];
    }

    return $tabs;
}

/*
 * Add fields to the group product tab
 */
add_action('woocommerce_product_data_panels', 'wicket_base_product_tab_content');

function wicket_base_product_tab_content()
{
    if (empty(wicket_get_option('wicket_admin_settings_group_assignment_subscription_products'))) {
        return;
    }
    global $post;
    $product = wc_get_product($post->ID);
    $group_options[''] = __('None', 'wicket-child');
    $group_role_options[''] = __('None', 'wicket-child');
    $client = wicket_api_client();

    $group_product_category = wicket_get_option('wicket_admin_settings_group_assignment_product_category');
    $resource_type_slug = wicket_get_option('wicket_admin_settings_group_assignment_role_entity_object');

    if (has_term($group_product_category, 'product_cat', $post->ID)) { ?>
    <style>
      .custom-wicket-tab {
        display: none;
      }
    </style>
    <div id="group_assignment_product" class="panel woocommerce_options_panel wicket_product_data custom-wicket-tab">
      <div class="options_group">
        <?php
          $groups = $client->get('groups?filter[active_true]=true&page%5Bnumber%5D=1&page%5Bsize%5D=9999');
        $assigned_group_uuid = get_post_meta($post->ID, '_group_assignment_uuid', true);
        foreach ($groups['data'] as $group) {
            $group_options[$group['attributes']['uuid']] = $group['attributes']['name'];
        }
        woocommerce_wp_select(
            [
                'id' => '_group_assignment_uuid',
                'label' => __('Group Assigned', 'wicket-child'),
                'options' => $group_options,
                'value'    => $assigned_group_uuid,
                'description' => sprintf('<em>%s</em>', __('Purchase of this product will assign person to the selected group.', 'wicket-child')),
            ]
        );
        ?>
        <?php
        $group_role_entity = $client->get('entity_types?page%5Bnumber%5D=1&page%5Bsize%5D=9999');
        foreach ($group_role_entity['data'] as $resource_type) {
            if ($resource_type['attributes']['code'] == $resource_type_slug) {
                $group_role_entity_type = $resource_type['attributes']['uuid'];
                break;
            }
        }
        if (empty($group_role_entity_type)) {
            echo "<p style='color:red;font-weight:bold'>Group role entity slug from <code>$resource_type_slug</code> set on page <a href='?page=wicket-settings&tab=integrations&section=woocommerce'>Wicket > Settings > Integrations > Woocommerce</a> not found. Please correct this value to use this feature.</p>";
        } else {
            $group_roles = $client->get('resource_types?filter[entity_type_uuid_eq]=' . $group_role_entity_type . '&page%5Bnumber%5D=1&page%5Bsize%5D=9999');
            foreach ($group_roles['data'] as $grouprole) {
                $group_role_options[$grouprole['attributes']['slug']] = $grouprole['attributes']['name'];
            }
            $assigned_group_role_slug = get_post_meta($post->ID, '_group_role_assignment_slug', true);
            $group_role_options[''] = __('None', 'wicket-child');
            woocommerce_wp_select(
                [
                    'id' => '_group_role_assignment_slug',
                    'label' => __('Role Assigned', 'wicket-child'),
                    'options' => $group_role_options,
                    'value'    => $assigned_group_role_slug,
                    'description' => sprintf('<em>%s</em>', __('Purchase of this product will assign person the selected role in the group.', 'wicket-child')),
                ]
            );
        }
        ?>

      </div>
      <div>
        <p style="font-size:15px;">Subscriptions created with this product can create and manage the User's Group Membership(s) in Wicket. The following subscription changes will make a corresponding change to the group membership.</p>
        <p style="font-size:15px;">When a subscription is purchased a Group Membership will be created with the selected Role for the chosen Group with the Next Payment Date as the End Date.</p>
        <p style="font-size:15px;">When the subscription renews the End Date of the Group Membership will be changed to the new Next Payment Date.</p>
        <p style="font-size:15px;">If the Next Payment Date of the subscription is changed manually it will update the Group Membership End Date in Wicket.</p>
        <p style="font-size:15px;">When the subscription is Cancelled the Group Membership End Date will be changed to the current date.</p>
      </div>
    </div>
<?php
    }
}

/*
 * Save custom product fields
 */
add_action('woocommerce_process_product_meta', 'wicket_base_woocommerce_process_product_meta');

function wicket_base_woocommerce_process_product_meta($post_id)
{
    $group_assignment_uuid = isset($_POST['_group_assignment_uuid']) ? sanitize_text_field($_POST['_group_assignment_uuid']) : '';
    $group_role_assignment_slug = isset($_POST['_group_role_assignment_slug']) ? sanitize_text_field($_POST['_group_role_assignment_slug']) : '';
    if (!empty($group_assignment_uuid)) {
        update_post_meta($post_id, '_group_assignment_uuid', $group_assignment_uuid);
    }
    if (!empty($group_role_assignment_slug)) {
        update_post_meta($post_id, '_group_role_assignment_slug', $group_role_assignment_slug);
    }
}

/**************************************
 * Hooks for Group Products management
 **************************************/

/*
 * When a subscription is renewed update the group end date if it is a group product
 * https://app.asana.com/1/1138832104141584/project/1206673098186132/task/1209126856654404
 * Tested: May 3 2025 - Wicket Memberships Test Site
 */
add_action('wcs_subscription_renewal_payment_complete', 'wicket_base_group_membership_subscription_renewal_completed', 10, 1);

function wicket_base_group_membership_subscription_renewal_completed($sub)
{
    if (empty(wicket_get_option('wicket_admin_settings_group_assignment_subscription_products'))) {
        return $sub;
    }

    wicket_group_subscription_update_end_dates($sub, $sub->get_time('next_payment'), 'Renewal Order Payment');
}

/*
 * When a subscription next payment date is changed we need to update group end dates associated to the user
 * https://app.asana.com/1/1138832104141584/project/1206673098186132/task/1209126856654408
 * We do not have a wcs hook and use generic update post meta to catch the specific subscription meta update
 * Tested: May 3 2025 - Wicket Memberships Test Site
 */

add_action('woocommerce_subscription_date_updated', function ($sub, $date_type, $datetime) {
    if (empty(wicket_get_option('wicket_admin_settings_group_assignment_subscription_products'))) {
        return $sub;
    }

    if ($date_type != 'next_payment') {
        return;
    }

    $subscription_id = $sub->get_id();
    $group_assigned_next_payment = get_post_meta($subscription_id, '_group_assigned_next_payment', true);

    if (empty($group_assigned_next_payment) || $group_assigned_next_payment == $sub->get_time('next_payment')) {
        //$sub->add_order_note("Group subscription creation incomplete or skipped a date change on creation. You may try reactivating subscription if group membership failed.");
        wicket_group_membership_subscription_status_active($sub);
    }

    $response = wicket_group_subscription_update_end_dates($sub, strtotime($datetime), 'Next Payment Date changed');
    if (!empty($response)) {
        wicket_wc_log_group_sync(['woocommerce_subscription_date_updated', 'API RESPONSE', $response]);
    }
}, 10, 3);

/*
 * When a subscription is cancelled and it is a group product create a group membership in MDP
 * https://app.asana.com/0/1206673098186132/1209126856654406
 * Tested: May 3 2025 - Wicket Memberships Test Site
 */
add_action('woocommerce_subscription_status_cancelled', function ($sub) {
    if (empty(wicket_get_option('wicket_admin_settings_group_assignment_subscription_products'))) {
        return $sub;
    }

    $sub = wcs_get_subscription($sub);

    wicket_group_subscription_update_end_dates($sub, $sub->get_time('end_date'), 'Subscription Cancelled');
}, 10, 1);

/*
 * When a subscription goes active after being created we need to create a group membership
 * https://app.asana.com/1/1138832104141584/project/1206673098186132/task/1209024694826429
 * Tested: May 3 2025 - Wicket Memberships Test Site
 */
add_action('woocommerce_subscription_status_active', 'wicket_group_membership_subscription_status_active', 1, 1);

function wicket_group_membership_subscription_status_active($sub)
{
    static $processing_subscriptions = [];

    if (empty(wicket_get_option('wicket_admin_settings_group_assignment_subscription_products'))) {
        return $sub;
    }

    $group_product_category = wicket_get_option('wicket_admin_settings_group_assignment_product_category');
    //$sub = wcs_get_subscription( $sub );
    $subscription_id = $sub->get_id();

    // check if we are already running on a different hook for this subscription
    if (isset($processing_subscriptions[$subscription_id])) {
        wicket_wc_log_group_sync(['Prevented duplicate group membership creation', $subscription_id]);

        return $sub;
    }

    // Mark this subscription as currently processing
    $processing_subscriptions[$subscription_id] = true;
    $group_assigned_next_payment = get_post_meta($subscription_id, '_group_assigned_next_payment', true);
    if (!empty($group_assigned_next_payment)) {
        //wicket_base_group_membership_subscription_renewal_completed($sub);
        //return;
    }

    $items = $sub->get_items();

    foreach ($items as $item) {
        $item_product_id = $item->get_product_id();
        if (!empty($item_product_id) && has_term($group_product_category, 'product_cat', $item_product_id)) {
            $found_group_role = get_post_meta($item_product_id, '_group_role_assignment_slug', true);
            $found_group_id = get_post_meta($item_product_id, '_group_assignment_uuid', true);
            $group_ids[$found_group_role . '|' . $item_product_id] = $found_group_id;
            //$sub->add_order_note("Group subscription activated firing for $found_group_id.")  ;
        }
    }

    wicket_wc_log_group_sync(['wicket_group_membership_subscription_status_active - items found', $group_ids]);

    if (!empty($group_ids)) {
        $user_id = $sub->get_user_id();
        $user = get_user_by('id', $user_id);
        $person_id = $user->user_login;

        $sub_start_date = $sub->get_time('start_date');
        $start_date = (new DateTime('@' . $sub_start_date))->setTimezone(wp_timezone())->format('Y-m-d\TH:i:sP');

        $sub_next_payment_date = $sub->get_time('next_payment');

        // If next payment date is not set then use end date instead.
        if ($sub_next_payment_date === 0) {
            $sub_next_payment_date = $sub->get_time('end_date');
        }

        $next_payment_date = (new DateTime('@' . $sub_next_payment_date))->setTimezone(wp_timezone())->format('Y-m-d\TH:i:sP');

        if ('prod' != wicket_get_option('wicket_admin_settings_environment')) {
            Wicket()->log()->debug(wc_print_r(['Processing Group Subscription Product' => [$group_ids, $person_id, $start_date, $next_payment_date]], true), ['source' => 'wicket-group-sync']);
        }

        foreach ($group_ids as $group_role_slug => $group_id) {
            $group_role_slug_parts = explode('|', $group_role_slug);
            $group_role_slug = $group_role_slug_parts[0];
            $group_info = wicket_wicket_get_group_info($group_id);
            $group_name = $group_info['data']['attributes']['name'];

            // The subscription owns one row for each group and role. Activation runs again on
            // reactivation and renewal, so do not create a second row.
            $membership_meta_key = wicket_group_subscription_membership_meta_key($group_id, $group_role_slug);
            if (!empty(get_post_meta($subscription_id, $membership_meta_key, true))) {
                wicket_wc_log_group_sync([
                    'ACTIVATION_SKIPPED_ROW_STORED',
                    'subscription_id' => $subscription_id,
                    'group_id' => $group_id,
                    'group_role_slug' => $group_role_slug,
                ], Log::LOG_LEVEL_AUDIT);
                continue;
            }

            // A subscription from before the fix: link the row with its start date.
            if (!empty(wicket_group_subscription_membership_uuid($sub, $person_id, $group_id, $group_role_slug))) {
                $sub->add_order_note("Group subscription linked to the existing group membership for $group_name.");
                continue;
            }

            $wicket_api_response = wicket_wicket_add_group_member($person_id, $group_id, $group_role_slug, $start_date, $next_payment_date, true);
            if (is_wp_error($wicket_api_response)) {
                $errors = $wicket_api_response->get_error_data('wicket_api_error');
                $error = is_array($errors) && isset($errors[0]->title) ? $errors[0]->title : $wicket_api_response->get_error_message();
                $sub->add_order_note("Failed group subscription for $group_name. ($error)");
            } else {
                // A created row comes back in data, a matched row comes back as the row.
                $membership_row = $wicket_api_response['data'] ?? $wicket_api_response;
                $membership_uuid = $membership_row['attributes']['uuid'] ?? $membership_row['id'] ?? '';
                update_post_meta($subscription_id, $membership_meta_key, $membership_uuid);
                wicket_wc_log_group_sync([
                    'ACTIVATION_ROW_STORED',
                    'subscription_id' => $subscription_id,
                    'group_id' => $group_id,
                    'group_role_slug' => $group_role_slug,
                    'group_membership_uuid' => $membership_uuid,
                ], Log::LOG_LEVEL_AUDIT);
                add_post_meta($subscription_id, '_group_assigned_uuid', $group_id);
                $group_assigned_next_payment = add_post_meta($subscription_id, '_group_assigned_next_payment', $sub_next_payment_date);
                $sub->add_order_note("Group subscription added or exists for $group_name.");
            }
        }
    }

    unset($processing_subscriptions[$subscription_id]);
}

/**
 * Subscription meta key that stores the UUID of the group membership row the subscription owns.
 * @param string $group_id
 * @param string $group_role_slug
 * @return string
 */
function wicket_group_subscription_membership_meta_key($group_id, $group_role_slug)
{
    return "_group_membership_uuid_{$group_id}_{$group_role_slug}";
}

/**
 * Get the UUID of the group membership row that the subscription owns.
 * A subscription from before the stored UUID links the row with the subscription
 * start date (first match), and stores its UUID.
 * @param WC_Subscription $sub
 * @param string $person_id
 * @param string $group_id
 * @param string $group_role_slug
 * @return string The row UUID, or an empty string if no row is linked.
 */
function wicket_group_subscription_membership_uuid($sub, $person_id, $group_id, $group_role_slug)
{
    $subscription_id = $sub->get_id();
    $meta_key = wicket_group_subscription_membership_meta_key($group_id, $group_role_slug);
    $stored_uuid = get_post_meta($subscription_id, $meta_key, true);
    if (!empty($stored_uuid)) {
        return $stored_uuid;
    }

    $rows = wicket_get_group_members_all_pages($group_id, [
        'person_uuid' => $person_id,
        'role'        => $group_role_slug,
        'active'      => null,
    ]);
    if (is_wp_error($rows)) {
        wicket_wc_log_group_sync([
            'LINK_LOOKUP_FAILED',
            'subscription_id' => $subscription_id,
            'group_id' => $group_id,
            'error' => $rows->get_error_message(),
        ]);

        return '';
    }

    // Activation sends the start date in the site timezone. MDP keeps the date part as sent.
    $start_date = (new DateTime('@' . $sub->get_time('start_date')))->setTimezone(wp_timezone())->format('Y-m-d');
    foreach ($rows as $row) {
        if (
            ($row['attributes']['type'] ?? '') == $group_role_slug
            && substr((string) ($row['attributes']['start_date'] ?? ''), 0, 10) === $start_date
        ) {
            update_post_meta($subscription_id, $meta_key, $row['attributes']['uuid']);
            wicket_wc_log_group_sync([
                'LINKED_BY_START_DATE',
                'subscription_id' => $subscription_id,
                'group_id' => $group_id,
                'group_role_slug' => $group_role_slug,
                'start_date' => $start_date,
                'group_membership_uuid' => $row['attributes']['uuid'],
            ], Log::LOG_LEVEL_AUDIT);

            return $row['attributes']['uuid'];
        }
    }

    wicket_wc_log_group_sync([
        'LINK_NO_MATCH',
        'subscription_id' => $subscription_id,
        'group_id' => $group_id,
        'group_role_slug' => $group_role_slug,
        'start_date' => $start_date,
        'rows_found' => count($rows),
    ], Log::LOG_LEVEL_AUDIT);

    return '';
}

/**
 * Set the end date of each group membership row that the subscription owns.
 * @param WC_Subscription $sub
 * @param int $end_time Unix timestamp of the new end date
 * @param string $event Event name for the order note
 * @return array The API responses
 */
function wicket_group_subscription_update_end_dates($sub, $end_time, $event)
{
    $responses = [];
    $group_ids = wicket_get_subscription_group_ids($sub);
    if (empty($group_ids)) {
        return $responses;
    }

    $person_id = get_user_by('id', $sub->get_user_id())->user_login;
    $args['end_date'] = (new DateTime('@' . $end_time))->setTimezone(wp_timezone())->format('Y-m-d\TH:i:sP');

    foreach ($group_ids as $group_role_slug => $group_id) {
        //this is necessary to handle case where same role on different groups in same subscription
        $group_role_slug = explode('|', $group_role_slug)[0];
        $group_info = wicket_wicket_get_group_info($group_id);
        $group_name = is_array($group_info) ? ($group_info['data']['attributes']['name'] ?? $group_id) : $group_id;

        $group_membership_uuid = wicket_group_subscription_membership_uuid($sub, $person_id, $group_id, $group_role_slug);
        if (empty($group_membership_uuid)) {
            $sub->add_order_note("Group subscription end date not updated on $event for Group: $group_name. No group membership is linked to this subscription.");
            continue;
        }

        $responses[] = wicket_wicket_update_group_membership($group_membership_uuid, $args);
        $sub->add_order_note('Group subscription end date updated to '
          . date('Y-m-d', $end_time) . " on $event for Group: $group_name.");
    }

    return $responses;
}
/**
 * Extract just the specific role membership id from a group memberships response.
 * @param string $group_role_slug
 * @param array $group_memberships
 * @return array
 */
function wicket_get_group_membership_uuids($group_memberships)
{
    foreach ($group_memberships as $group_role_slug => $group_membership) {
        //this is necessary to handle case where same role on different groups in same subscription
        $group_role_slug_parts = explode('|', $group_role_slug);
        $group_role_slug = $group_role_slug_parts[0];

        $attributes = $group_membership['data'][0]['attributes'];
        $group_id = $group_membership['data'][0]['relationships']['group']['data']['id'];
        if ($attributes['type'] == $group_role_slug) {
            $group_membership_uuids_item['id'] = $attributes['uuid'];
            $group_membership_uuids_group_info = wicket_wicket_get_group_info($group_id);
            $group_membership_uuids_item['name'] = $group_membership_uuids_group_info['data']['attributes']['name'];
            $group_membership_uuids[] = $group_membership_uuids_item;
        }
    }
    wicket_wc_log_group_sync(['wicket_get_group_membership_uuids', $group_membership_uuids]);

    return $group_membership_uuids;
}

/**
 * Lookup group memberships in mdp with people and group ids.
 * @param string $person_id
 * @param array $group_ids
 * @return array|bool|WP_Error
 */
function wicket_get_group_memberships($person_id, $group_ids)
{
    $group_memberships = [];
    foreach ($group_ids as $group_role => $group_id) {
        $group_memberships[$group_role] = wicket_wicket_get_group_membership($person_id, $group_id);
    }

    return $group_memberships;
}

/**
 * Get the group_id and group_role_slug from group products found in a subscription.
 * @param object $sub
 * @return array
 */
function wicket_get_subscription_group_ids($sub)
{
    $group_ids = [];
    $group_product_category = wicket_get_option('wicket_admin_settings_group_assignment_product_category');

    if (is_a($sub, 'WC_Subscription')) {
        $items = $sub->get_items();
        foreach ($items as $item) {
            $item_product_id = $item->get_product_id();
            if (!empty($item_product_id) && has_term($group_product_category, 'product_cat', $item_product_id)) {
                $group_role_slug = get_post_meta($item_product_id, '_group_role_assignment_slug', true);
                $group_ids[$group_role_slug . '|' . $item_product_id] = get_post_meta($item_product_id, '_group_assignment_uuid', true);
            }
        }
    }

    return $group_ids;
}

/************************************************
 * Wicket Group API Requests
 ************************************************/

/**
 * Add a member to a group with the specified role.
 *
 * @param int|string $person_id ID of the person to add
 * @param int|string $group_id ID of the group to add the member to
 * @param string $group_role_slug The type of group role to assign to the person
 * @param string $start_date [optional] The date to start the group membership
 * @param string $end_date [optional] The date to end the group membership
 * @param string $skip_if_exists [optional] Don't create duplicates
 *
 * @return object The response object from the Wicket API
 */
function wicket_wicket_add_group_member($person_id, $group_id, $group_role_slug, $start_date = null, $end_date = null, $skip_if_exists = false)
{
    if ($skip_if_exists) {
        // Check if the person already has an active row in this group with this role, on any page
        $existing_rows = wicket_get_group_members_all_pages($group_id, [
            'person_uuid' => $person_id,
            'role'        => $group_role_slug,
            'active'      => true,
        ]);
        if (is_wp_error($existing_rows)) {
            wicket_wc_log_group_sync([
                'DUPLICATE_CHECK_FAILED',
                'person_id' => $person_id,
                'group_id' => $group_id,
                'group_role_slug' => $group_role_slug,
                'error' => $existing_rows->get_error_message(),
            ]);

            // Do not create a row when we cannot tell if one exists
            return new WP_Error('wicket_dup_check_failed', 'Could not check for an existing group membership. ' . $existing_rows->get_error_message());
        }

        foreach ($existing_rows as $existing_row) {
            if (($existing_row['attributes']['type'] ?? '') == $group_role_slug) {
                wicket_wc_log_group_sync([
                    'DUPLICATE_FOUND',
                    'person_id' => $person_id,
                    'group_id' => $group_id,
                    'group_role_slug' => $group_role_slug,
                    'existing_membership_uuid' => $existing_row['attributes']['uuid'] ?? '',
                ], Log::LOG_LEVEL_AUDIT);

                // Matching group found - returning that group connection instead of adding them to the group again
                return $existing_row;
            }
        }

        wicket_wc_log_group_sync([
            'NO_DUPLICATE_FOUND',
            'person_id' => $person_id,
            'group_id' => $group_id,
            'group_role_slug' => $group_role_slug,
        ], Log::LOG_LEVEL_AUDIT);
    }

    $client = wicket_api_client();

    $payload = [
        'data' => [
            'attributes'   => [
                'custom_data_field' => null,
                'end_date'          => $end_date,
                'person_id'         => $person_id,
                'start_date'        => $start_date,
                'type'              => $group_role_slug,
            ],
            'id'            => null,
            'relationships' => [
                'group' => [
                    'data' => [
                        'id'   => $group_id,
                        // 'meta' => [
                        //   'can_manage' => true,
                        //   'can_update' => true,
                        // ],
                        'type' => 'groups',
                    ],
                ],
            ],
            'type'          => 'group_members',
        ],
    ];

    try {
        $response = $client->post('group_members', ['json' => $payload]);
    } catch (Exception $e) {
        // Match wicket_add_group_member(): string message, structured errors
        // in get_error_data(). Array messages fatal typed string consumers.
        $api_errors = null;
        if ($e->hasResponse()) {
            $api_errors = json_decode((string) $e->getResponse()->getBody())->errors ?? null;
        }
        $response = new WP_Error(
            'wicket_api_error',
            $api_errors[0]->detail ?? $api_errors[0]->title ?? 'Wicket API error.',
            $api_errors
        );

        // Log: API error when creating group member
        wicket_wc_log_group_sync([
            'API_ERROR_CREATE_GROUP_MEMBER',
            'person_id' => $person_id,
            'group_id' => $group_id,
            'group_role_slug' => $group_role_slug,
            'error_message' => $e->getMessage(),
            'api_errors' => $api_errors,
        ], 'error');
    }

    return $response;
}

/**
 * Summary of wicket_wicket_get_group_info.
 * @param string $group_uuid
 * @return array|bool|WP_Error
 */
function wicket_wicket_get_group_info($group_uuid)
{
    $client = wicket_api_client();
    try {
        $response = $client->get("/groups/$group_uuid");
    } catch (Exception $e) {
        // Transport failures (timeouts, DNS) carry no HTTP response.
        $response_body = (method_exists($e, 'getResponse') && $e->getResponse())
            ? (string) $e->getResponse()->getBody()
            : '';
        $decoded = json_decode($response_body, true);
        $wicket_api_error = $decoded['errors'][0]['detail'] ?? $e->getMessage();
        wicket_wc_log_group_sync(['error' => $e->getMessage(), 'response' => $response_body]);
        $response = new WP_Error('wicket_api_error', $wicket_api_error);
    }

    return $response;
}

/**
 * Summary of wicket_wicket_get_group_membership.
 * @param string $person_uuid
 * @param string $group_uuid
 * @param array $args
 * @return array|bool|WP_Error
 */
function wicket_wicket_get_group_membership($person_uuid, $group_uuid, $args = [])
{
    $client = wicket_api_client();
    try {
        $response = $client->get("/groups/$group_uuid/people?filter[person_uuid_eq]=$person_uuid");
    } catch (Exception $e) {
        // Transport failures (timeouts, DNS) carry no HTTP response.
        $response_body = (method_exists($e, 'getResponse') && $e->getResponse())
            ? (string) $e->getResponse()->getBody()
            : '';
        $decoded = json_decode($response_body, true);
        $wicket_api_error = $decoded['errors'][0]['detail'] ?? $e->getMessage();
        wicket_wc_log_group_sync(['error' => $e->getMessage(), 'response' => $response_body]);
        $response = new WP_Error('wicket_api_error', $wicket_api_error);
    }

    return $response;
}

/**
 * Summary of wicket_wicket_update_group_membership.
 * @param mixed $group_membership_uuid
 * @param mixed $args options [start_date => Y-m-d, end_date => Y-m-d]
 * @return void|array|bool|WP_Error
 */
function wicket_wicket_update_group_membership($group_membership_uuid, $args = [])
{
    $allowed_args = ['start_date', 'end_date'];
    $payload = [
        'data' => [
            'attributes'   => [
                'custom_data_field' => null,
            ],
            'type' => 'group_members',
        ],
    ];
    if (!empty($args)) {
        foreach ($args as $key => $val) {
            if (in_array($key, $allowed_args)) {
                $payload['data']['attributes'][$key] = $val;
            }
        }
        if (!empty($payload)) {
            $client = wicket_api_client();
            try {
                $response = $client->patch("/group_members/$group_membership_uuid", ['json' => $payload]);
            } catch (Exception $e) {
                // Transport failures (timeouts, DNS) carry no HTTP response.
                $response_body = (method_exists($e, 'getResponse') && $e->getResponse())
                    ? (string) $e->getResponse()->getBody()
                    : '';
                $decoded = json_decode($response_body, true);
                $wicket_api_error = $decoded['errors'][0]['detail'] ?? $e->getMessage();
                wicket_wc_log_group_sync(['error' => $e->getMessage(), 'response' => $response_body]);
                $response = new WP_Error('wicket_api_error', $wicket_api_error);
            }

            return $response;
        }
    }
}

function wicket_wc_log_group_sync($data, $level = 'error')
{
    if (is_array($data)) {
        $data = wc_print_r($data, true);
    }
    Wicket()->log()->log($level, $data, ['source' => 'wicket-group-sync']);
}
