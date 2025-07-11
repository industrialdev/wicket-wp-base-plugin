<?php

/*
 * THIS IS MEANT FOR WHEN WE USE THE "User Switching" PLUGIN (IMPERSONATION). 
 * This will provide the most up to date roles on a user if an admin was impersonating them.
 *
 * 1 - Detect a switched session with current_user_switched().
 * 2 - Use the WP session token as a unique per‑browser key.
 * 3 - Use a transient keyed to that token so the sync runs at most once.
 */
$maybe_run_sync = function () {
  // 1. Only proceed if we *are* in a switched session.
  if ( ! function_exists( 'current_user_switched' ) || ! current_user_switched() ) {
    return;
  }

  // 2. Grab the session‑token string that WordPress stores in the auth cookie.
  $token = wp_get_session_token();
  if ( ! $token ) {
    // Extremely rare, but bail safely if the token can't be found.
    return;
  }

  // 3. Transient key: unique to this browser session.
  $transient_key = 'wicket_sync_done_' . md5( $token );

  // If the transient is missing, run the sync *once* and set it.
  if ( false === get_transient( $transient_key ) ) {

    $user = wp_get_current_user();
    if (is_string($user->user_login) && (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $user->user_login) == 1)) {
      sync_wicket_data_for_person($user->user_login);
    }   

    // Keep the flag for 12 hours (long enough for most sessions).
    set_transient( $transient_key, 1, 12 * HOUR_IN_SECONDS );
  }
};

if ( did_action( 'plugins_loaded' ) ) {
  $maybe_run_sync();
} else {
  add_action( 'plugins_loaded', $maybe_run_sync );
}
