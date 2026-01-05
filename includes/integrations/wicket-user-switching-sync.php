<?php

/*
 * Sync (or revert) when using the "User Switching" plugin.
 * Ensures we DO NOT remain switched if the target Wicket person no longer exists.
 */

/**
 * Feature flag: enable the "Attempt to fix WP<>MDP UUID mismatch" link.
 * Set to true to show/enable the amend link; false disables it.
 */
const WICKET_ALLOW_UUID_AMEND_LINK = false;

/**
 * Handle a user switch attempt. If the target (Wicket) user no longer exists remotely, revert immediately
 * and surface an admin error notice. Otherwise, perform the usual sync so roles are current.
 *
 * @param int    $new_user_id  The ID of the user being switched to.
 * @param int    $old_user_id  The ID of the user being switched from.
 * @param string $new_token    New session token (unused here).
 * @param string $old_token    Old session token (unused here).
 */
function wicket_switch_to_user_sync($new_user_id, $old_user_id, $new_token = '', $old_token = '')
{
    $user_info = get_userdata($new_user_id);
    if (!($user_info instanceof WP_User)) {
        return;
    }

    // Only act for Wicket-managed accounts (UUID v4 style login values).
    $login = $user_info->user_login;
    $is_uuid = is_string($login) && (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $login) === 1);
    if (!$is_uuid) {
        return; // Non-Wicket account; nothing to do.
    }

    $sync_ok = true;
    try {
        // Attempt to sync roles/data. Assume a falsey return or WP_Error indicates missing remote person.
        $result = sync_wicket_data_for_person($login);
        if ($result === false || (is_object($result) && ($result instanceof WP_Error))) {
            $sync_ok = false;
        }
    } catch (Throwable $e) {
        $sync_ok = false;
    }

    if (!$sync_ok) {
        $error_message = __('Unable to switch: The Wicket account no longer exists or the UUIDs do not match.', 'wicket');

        // --- BREEZE FIX START: Disable Breeze Hooks ---
        // https://wicket.zendesk.com/agent/tickets/6911
        // We unhook Breeze so it doesn't try to set/clear cookies during this sensitive revert.
        if (function_exists('breeze_auth_cookie_set')) {
            remove_action('set_auth_cookie', 'breeze_auth_cookie_set', 15);
        }
        if (function_exists('breeze_auth_cookie_clear')) {
            remove_action('clear_auth_cookie', 'breeze_auth_cookie_clear');
        }
        // --- BREEZE FIX END ---

        // Revert the switch – restore original user session.
        if (function_exists('switch_to_user') && $old_user_id) {
            // Use plugin's helper so cookies & session are correctly reset. Third param false triggers switch_back logic.
            switch_to_user($old_user_id, false, false);
        } else {
            // Fallback manual restoration.
            wp_clear_auth_cookie();
            wp_set_auth_cookie($old_user_id, false);
            wp_set_current_user($old_user_id);
        }

        // Redirect back to the referring page to prevent the default User Switching redirect.
        $redirect_url = wp_get_referer();
        if (!$redirect_url) {
            // Fallback to admin users page if no referer available.
            $redirect_url = admin_url('users.php');
        }

        // Add error payload to URL for the notice when amend link is enabled.
        if (WICKET_ALLOW_UUID_AMEND_LINK) {
            $redirect_url = add_query_arg(
                [
                    'wicket_switch_failed' => '1',
                    'wicket_switch_msg'    => rawurlencode($error_message),
                    'wicket_switch_target' => (int) $new_user_id,
                ],
                $redirect_url
            );
        }

        // Use wp_redirect for reliability if safe redirect fails
        if (!wp_safe_redirect($redirect_url)) {
            wp_redirect($redirect_url);
        }
        exit;
    }
}

/**
 * Display an admin notice if a previous switch attempt failed due to a missing Wicket account.
 */
function wicket_switch_error_notice()
{
    $current_id = get_current_user_id();
    if (!$current_id) {
        return;
    }
    $has_error = isset($_GET['wicket_switch_failed']) && $_GET['wicket_switch_failed'] === '1';
    $message = isset($_GET['wicket_switch_msg']) ? sanitize_text_field(wp_unslash($_GET['wicket_switch_msg'])) : '';
    $target_user_id = isset($_GET['wicket_switch_target']) ? (int) $_GET['wicket_switch_target'] : 0;

    if ($has_error && $message && WICKET_ALLOW_UUID_AMEND_LINK) {
        $fix_url = '';
        $fix_alert = __('This will look up the user in MDP by email and update the WP user_login to the MDP UUID. Proceed?', 'wicket');
        if ($target_user_id) {
            $fix_url = wp_nonce_url(
                add_query_arg(
                    [
                        'wicket_fix_uuid'  => 1,
                        'target_user_id'   => $target_user_id,
                    ],
                    admin_url('users.php')
                ),
                'wicket_fix_uuid_' . $target_user_id
            );
        }
        ?>
        <div class="notice notice-error is-dismissible" style="border-left-width: 4px; border-left-color: #d63638; background: #fcf0f1; padding: 15px;">
            <p style="font-size: 14px; font-weight: 600; margin: 0;">
                <span class="dashicons dashicons-warning" style="color: #d63638; font-size: 20px; vertical-align: middle; margin-right: 8px;"></span>
                <?php echo esc_html($message); ?>
                <?php if ($fix_url) : ?>
                    <a href="<?php echo esc_url($fix_url); ?>" style="margin-left: 8px; font-weight: 600;" onclick="return confirm('<?php echo esc_js($fix_alert); ?>');">
                        <?php esc_html_e('Attempt to fix WP<>MDP UUID mismatch.', 'wicket'); ?>
                    </a>
                <?php endif; ?>
            </p>
        </div>
        <?php
    }
}

/**
 * Handle request to repair a WP<>MDP UUID mismatch by updating user_login to the MDP UUID.
 */
function wicket_fix_uuid_mismatch_action()
{
    if (!isset($_GET['wicket_fix_uuid'], $_GET['target_user_id'])) {
        return;
    }

    if (!current_user_can('edit_users')) {
        return;
    }

    $target_user_id = (int) $_GET['target_user_id'];
    if (!$target_user_id) {
        return;
    }

    check_admin_referer('wicket_fix_uuid_' . $target_user_id);

    $redirect = remove_query_arg(['wicket_fix_uuid', 'target_user_id', '_wpnonce']);
    $redirect = add_query_arg('wicket_fix_result', 'fail', $redirect);

    $user = get_userdata($target_user_id);
    if (!($user instanceof WP_User)) {
        wp_safe_redirect($redirect);
        exit;
    }

    $email = $user->user_email;
    if (empty($email) || !function_exists('wicket_get_person_by_email')) {
        wp_safe_redirect($redirect);
        exit;
    }

    $mdp_person = wicket_get_person_by_email($email);
    $mdp_uuid = is_array($mdp_person) ? ($mdp_person['id'] ?? '') : ($mdp_person->id ?? '');
    if (empty($mdp_uuid)) {
        wp_safe_redirect($redirect);
        exit;
    }

    global $wpdb;
    $new_login = sanitize_user($mdp_uuid);
    $old_login = $user->user_login;

    // Prevent collision: if another account already has this login, abort with explicit failure flag.
    if (username_exists($new_login)) {
        $redirect = add_query_arg('wicket_fix_result', 'conflict', $redirect);
        wp_safe_redirect($redirect);
        exit;
    }

    $updated_login = $wpdb->update(
        $wpdb->users,
        ['user_login' => $new_login],
        ['ID' => $target_user_id],
        ['%s'],
        ['%d']
    );

    // Align nicename/display_name if they still match the old login (avoid clobbering custom values).
    if ($updated_login !== false) {
        if ($user->user_nicename === $old_login) {
            $wpdb->update(
                $wpdb->users,
                ['user_nicename' => $new_login],
                ['ID' => $target_user_id],
                ['%s'],
                ['%d']
            );
        }
        if ($user->display_name === $old_login) {
            $wpdb->update(
                $wpdb->users,
                ['display_name' => $new_login],
                ['ID' => $target_user_id],
                ['%s'],
                ['%d']
            );
        }
        $nickname = get_user_meta($target_user_id, 'nickname', true);
        if ($nickname === $old_login) {
            update_user_meta($target_user_id, 'nickname', $new_login);
        }
    }

    if ($updated_login !== false) {
        clean_user_cache($target_user_id);
        $redirect = add_query_arg('wicket_fix_result', 'success', $redirect);
    }

    wp_safe_redirect($redirect);
    exit;
}

/**
 * Show result notice for UUID mismatch repair attempts.
 */
function wicket_fix_uuid_mismatch_notice()
{
    if (!isset($_GET['wicket_fix_result'])) {
        return;
    }

    $result = sanitize_text_field(wp_unslash($_GET['wicket_fix_result']));
    $is_success = $result === 'success';
    $class = $is_success ? 'notice-success' : 'notice-error';
    if ($result === 'conflict') {
        $message = __('UUID mismatch repair failed: another account already uses this UUID as login. Resolve duplicate before retrying.', 'wicket');
    } else {
        $message = $is_success
            ? __('UUID mismatch repaired: WP user_login updated to correct MDP user UUID matched by email.', 'wicket')
            : __('UUID mismatch repair failed. Ensure the email is associated to a user in the MDP.', 'wicket');
    }
    ?>
    <div class="notice <?php echo esc_attr($class); ?> is-dismissible">
        <p><?php echo esc_html($message); ?></p>
    </div>
    <?php
}

/**
 * Add quick inline styling for the User Switching "switch back" footer banner.
 */
function wicket_switching_footer_style()
{
    if (!is_user_logged_in()) {
        return;
    }
    ?>
    <style>
        #user_switching_switch_on {
            position: fixed !important;
            bottom: 16px !important;
            left: 16px !important;
            margin: 0 !important;
            padding: 0 !important;
            z-index: 99999 !important;
            font-size: 13px !important;
        }
        #user_switching_switch_on a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            background: #ffffff;
            color: #1f2937;
            border: 1px solid #d0d7de;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(17, 24, 39, 0.18);
            text-decoration: none;
            font-weight: 600;
        }
        #user_switching_switch_on a:hover {
            border-color: #3858e9;
            color: #2237a7;
            box-shadow: 0 12px 36px rgba(56, 88, 233, 0.28);
        }
    </style>
    <?php
}

if (function_exists('switch_to_user')) {
    // Priority 5: run early so any subsequent hooks see reverted state if we abort.
    add_action('switch_to_user', 'wicket_switch_to_user_sync', 5, 4);
    add_action('switch_back_user', 'wicket_switch_to_user_sync', 5, 4);
    add_action('admin_notices', 'wicket_switch_error_notice');
    add_action('admin_init', 'wicket_fix_uuid_mismatch_action');
    add_action('admin_notices', 'wicket_fix_uuid_mismatch_notice');
    add_action('admin_footer', 'wicket_switching_footer_style');
    add_action('wp_footer', 'wicket_switching_footer_style');
}
