<?php

/**------------------------------------------------------------------
 * Perform operations when the user is authed via CAS, but not yet in Wordpress
 * For testing purposes since $cas_user_data contains data straight from CAS
 * NOTE: Do not put person UUID here or anything else in $_SESSION for any reason.
 * PHP Sessions can be unreliable and collisions can happen.
 ------------------------------------------------------------------*/
function custom_action_before_auth_user_wordpress($cas_user_data)
{
    // perhaps log CAS payload, etc
}
add_action('wp_cassify_before_auth_user_wordpress', 'custom_action_before_auth_user_wordpress', 1, 1);

/**------------------------------------------------------------------
 * Perform operations when the user is logging in to Wordpress
 ------------------------------------------------------------------*/
function sync_wicket_data()
{
    // if they're logged in via CAS...
    if (wp_get_current_user()->user_login) {
        $client = wicket_api_client_current_user();
        $person = wicket_current_person();

        $user = wp_get_current_user();
        // first remove all existing roles
        $user->set_role('');

        global $wp_roles;
        if (!isset($wp_roles)) {
            $wp_roles = new WP_Roles();
        }

        $roles = $person->role_names;

        // Ignore certain security roles from being synced
        if (wicket_get_option('wicket_admin_settings_wpcassify_ignore_roles') != '') {
            $ignored_roles = explode(',', wicket_get_option('wicket_admin_settings_wpcassify_ignore_roles'));
            // remove any spaces between the commas in the field if being used
            $ignored_roles = array_map('trim', $ignored_roles);
            foreach ($roles as $key => $role) {
                if (in_array($role, $ignored_roles)) {
                    unset($roles[$key]);
                }
            }
        }

        // Sync membership tiers as roles in WP if the option is set
        if (wicket_get_option('wicket_admin_settings_wpcassify_sync_memberships_as_roles') === '1') {
            // get current person active memberships ids, find the active memberships slug from ids, assign user with roles from active membership tiers
            $memberships = wicket_get_current_person_active_memberships();
            $active_memberships_ids = [];
            if (isset($memberships['data'])) {
                foreach ($memberships['data'] as $key => $membership) {
                    if ($membership['attributes']['status'] == 'Active') {
                        $active_memberships_ids[$key] = $membership['relationships']['membership']['data']['id'];
                    }
                }
            }
            // look if included membership are active and if yes add to $roles[]
            if (isset($memberships['included'])) {
                foreach ($memberships['included'] as $key => $membership) {
                    if (in_array($membership['id'], $active_memberships_ids)) {
                        $roles[] = $membership['attributes']['name'];
                    }
                }
            }
        }

        // Sync user tags as roles in WP if the option is set
        if (wicket_get_option('wicket_admin_settings_wpcassify_sync_tags_as_roles') != '') {
            $allowed_tags = explode(',', wicket_get_option('wicket_admin_settings_wpcassify_sync_tags_as_roles'));
            // remove any spaces between the commas in the field if being used
            $allowed_tags = array_map('trim', $allowed_tags);
            foreach ($person->tags as $tag) {
                if (in_array($tag, $allowed_tags)) {
                    $roles[] = $tag;
                }
            }
        }

        // update user with roles from Wicket
        foreach ($roles as $role) {
            // check if the role exists in WP already
            $role_exists = wp_roles()->is_role($role);
            if ($role_exists) {
                // assign the role to the user
                $user->add_role($role);
            } else {
                // clone the subsciber capabilities into a new role
                $subscriber_role = $wp_roles->get_role('subscriber');
                $role_machine = str_replace(' ', '_', $role);
                $role_human = ucwords($role);
                $wp_roles->add_role($role_machine, $role_human, $subscriber_role->capabilities);
                // add new role to user
                $user->add_role($role_machine);
            }
        }

        // update the user with the appropriate metadata
        $user->nickname = $person->full_name;
        $user->display_name = $person->full_name;
        $user->first_name = $person->given_name;
        $user->user_email = $person->user['email'];
        $user->last_name = $person->family_name;
        wp_update_user($user);
    }

}
add_action('wp_login', 'sync_wicket_data');

/**------------------------------------------------------------------
 * Re-sync Wicket roles when a membership purchase completes (WWID-2258)
 *
 * Roles granted by the MDP mid-session (org onboarding purchases grant
 * membership_owner / membership_manager) are only mirrored into WordPress
 * at login. Until the next login, role-gated UI such as the "Manage My
 * Organization" account nav item stays hidden for the buyer.
 *
 * wicket-wp-memberships fires this action after the MDP membership record
 * is created, so the person's role_names are already updated when we sync.
 ------------------------------------------------------------------*/
function sync_wicket_data_on_membership_created($membership_post_data)
{
    $person_uuid = is_array($membership_post_data) ? ($membership_post_data['membership_user_uuid'] ?? '') : '';
    if (empty($person_uuid)) {
        return;
    }

    // The sync must never break the order-completion flow: an MDP hiccup
    // here leaves roles to the next login instead of failing checkout.
    try {
        sync_wicket_data_for_person($person_uuid);
    } catch (Throwable $e) {
        error_log('wicket-cas-role-sync: post-purchase role sync failed for person ' . $person_uuid . ': ' . $e->getMessage());
    }
}
add_action('wicket_membership_created_mdp', 'sync_wicket_data_on_membership_created', 10, 1);

/**------------------------------------------------------------------
 * Sync data on a specific user at any given point
 ------------------------------------------------------------------*/
function sync_wicket_data_for_person($person_uuid)
{
    if (!$person_uuid) {
        return;
    }

    $person = wicket_get_person_by_id($person_uuid);

    // Person fetch failed (invalid UUID, MDP down): nothing to sync. Never
    // fatal here, callers such as the membership-purchase hook run inside
    // the WooCommerce order flow.
    if (!$person) {
        return;
    }

    $user = get_user_by('login', $person_uuid);

    // No WordPress user for this person yet (guest checkout, unmirrored
    // person): roles would land on null and fatal. Bail instead.
    if (!($user instanceof WP_User)) {
        return;
    }

    // first remove all existing roles
    $user->set_role('');

    global $wp_roles;
    if (!isset($wp_roles)) {
        $wp_roles = new WP_Roles();
    }

    $roles = $person->role_names;

    // Guard against a malformed person payload (missing role_names) so the
    // foreach below cannot fatal inside the order flow.
    if (!is_array($roles)) {
        $roles = [];
    }

    // Ignore certain security roles from being synced
    if (wicket_get_option('wicket_admin_settings_wpcassify_ignore_roles') != '') {
        $ignored_roles = explode(',', wicket_get_option('wicket_admin_settings_wpcassify_ignore_roles'));
        // remove any spaces between the commas in the field if being used
        $ignored_roles = array_map('trim', $ignored_roles);
        foreach ($roles as $key => $role) {
            if (in_array($role, $ignored_roles)) {
                unset($roles[$key]);
            }
        }
    }

    // Sync membership tiers as roles in WP if the option is set
    if (wicket_get_option('wicket_admin_settings_wpcassify_sync_memberships_as_roles') === '1') {
        // get current person active memberships ids, find the active memberships slug from ids, assign user with roles from active membership tiers
        $memberships = wicket_get_current_person_active_memberships();
        $active_memberships_ids = [];
        if (isset($memberships['data'])) {
            foreach ($memberships['data'] as $key => $membership) {
                if ($membership['attributes']['status'] == 'Active') {
                    $active_memberships_ids[$key] = $membership['relationships']['membership']['data']['id'];
                }
            }
        }
        // look if included membership are active and if yes add to $roles[]
        if (isset($memberships['included'])) {
            foreach ($memberships['included'] as $key => $membership) {
                if (in_array($membership['id'], $active_memberships_ids)) {
                    $roles[] = $membership['attributes']['name'];
                }
            }
        }
    }

    // Sync user tags as roles in WP if the option is set
    if (wicket_get_option('wicket_admin_settings_wpcassify_sync_tags_as_roles') != '') {
        $allowed_tags = explode(',', wicket_get_option('wicket_admin_settings_wpcassify_sync_tags_as_roles'));
        // remove any spaces between the commas in the field if being used
        $allowed_tags = array_map('trim', $allowed_tags);
        foreach ($person->tags as $tag) {
            if (in_array($tag, $allowed_tags)) {
                $roles[] = $tag;
            }
        }
    }

    // update user with roles from Wicket
    foreach ($roles as $role) {
        // check if the role exists in WP already
        $role_exists = wp_roles()->is_role($role);
        if ($role_exists) {
            // assign the role to the user
            $user->add_role($role);
        } else {
            // clone the subsciber capabilities into a new role
            $subscriber_role = $wp_roles->get_role('subscriber');
            $role_machine = str_replace(' ', '_', $role);
            $role_human = ucwords($role);
            $wp_roles->add_role($role_machine, $role_human, $subscriber_role->capabilities);
            // add new role to user
            $user->add_role($role_machine);
        }
    }

    // update the user with the appropriate metadata
    $user->nickname = $person->full_name;
    $user->display_name = $person->full_name;
    $user->first_name = $person->given_name;
    $user->user_email = $person->user['email'];
    $user->last_name = $person->family_name;
    wp_update_user($user);

}

/**------------------------------------------------------------------
 * Additional hooks to ensure display_name is synced on profile update and user registration
 ------------------------------------------------------------------*/
add_action('profile_update', 'wicket_sync_display_name_from_profile', 999, 1);
add_action('user_register', 'wicket_sync_display_name_from_profile', 999, 1);

function wicket_sync_display_name_from_profile($user_id)
{
    static $updating = [];

    // Prevent recursion for this user
    if (isset($updating[$user_id])) {
        return;
    }

    $first = get_user_meta($user_id, 'first_name', true);
    $last = get_user_meta($user_id, 'last_name', true);
    $display = trim($first . ' ' . $last);
    if (!$display) {
        return;
    }
    $user = get_userdata($user_id);
    if ($user && $user->display_name === $display) {
        return;
    }

    // Mark this user as being updated
    $updating[$user_id] = true;

    update_user_meta($user_id, 'nickname', $display);
    wp_update_user([
        'ID'           => $user_id,
        'display_name' => $display,
    ]);

    // Clear the flag
    unset($updating[$user_id]);
}
