<?php

/*
 * Sync (or revert) when using the "User Switching" plugin.
 * Ensures we DO NOT remain switched if the target Wicket person no longer exists.
 */

// Transient prefix used to pass an error message to the next page load.
const WICKET_SWITCH_ERROR_PREFIX = 'wicket_switch_error_';

/**
 * Handle a user switch attempt. If the target (Wicket) user no longer exists remotely, revert immediately
 * and surface an admin error notice. Otherwise, perform the usual sync so roles are current.
 *
 * @param int    $new_user_id  The ID of the user being switched to.
 * @param int    $old_user_id  The ID of the user being switched from.
 * @param string $new_token    New session token (unused here).
 * @param string $old_token    Old session token (unused here).
 */
function wicket_switch_to_user_sync($new_user_id, $old_user_id, $new_token = '', $old_token = '') {
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

        // Store an error message (short TTL – just for the immediate next page load).
        set_transient(WICKET_SWITCH_ERROR_PREFIX . (int) $old_user_id, __('Unable to switch: The Wicket account no longer exists or the UUIDs do not match.', 'wicket'), 60);
        
        // Redirect back to the referring page to prevent the default User Switching redirect.
        $redirect_url = wp_get_referer();
        if (!$redirect_url) {
            // Fallback to admin users page if no referer available.
            $redirect_url = admin_url('users.php');
        }
        
        // Add error flag to URL for the notice.
        $redirect_url = add_query_arg('wicket_switch_failed', '1', $redirect_url);
        
        wp_safe_redirect($redirect_url);
        exit;
    }
}

/**
 * Display an admin notice if a previous switch attempt failed due to a missing Wicket account.
 */
function wicket_switch_error_notice() {
    $current_id = get_current_user_id();
    if (!$current_id) {
        return;
    }
    $message = get_transient(WICKET_SWITCH_ERROR_PREFIX . (int) $current_id);
    if ($message) {
        ?>
        <div class="notice notice-error is-dismissible" style="border-left-width: 4px; border-left-color: #d63638; background: #fcf0f1; padding: 15px;">
            <p style="font-size: 14px; font-weight: 600; margin: 0;">
                <span class="dashicons dashicons-warning" style="color: #d63638; font-size: 20px; vertical-align: middle; margin-right: 8px;"></span>
                <?php echo esc_html($message); ?>
            </p>
        </div>
        <?php
        delete_transient(WICKET_SWITCH_ERROR_PREFIX . (int) $current_id);
    }
}

if (function_exists('switch_to_user')) {
    // Priority 5: run early so any subsequent hooks see reverted state if we abort.
    add_action('switch_to_user', 'wicket_switch_to_user_sync', 5, 4);
    add_action('switch_back_user', 'wicket_switch_to_user_sync', 5, 4);
    add_action('admin_notices', 'wicket_switch_error_notice');
}