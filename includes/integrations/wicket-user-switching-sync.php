<?php

/*
 * THIS IS MEANT FOR WHEN WE USE THE "User Switching" PLUGIN (IMPERSONATION). 
 * This will provide the most up to date roles on a user if an admin was impersonating them.
 */
function wicket_switch_to_user_sync($user_id){
    $user_info = get_userdata($user_id);
    // check if this is a uuid format (we don't want this to fire for non-wicket accounts)
    // https://gist.github.com/joel-james/3a6201861f12a7acf4f2
    // https://stackoverflow.com/questions/12808597/php-verify-valid-uuid
    if (is_string($user_info->user_login) && (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $user_info->user_login) == 1)) {
        sync_wicket_data_for_person($user_info->user_login);
    }
}
if (function_exists('switch_to_user')) {
    add_action( 'switch_to_user', 'wicket_switch_to_user_sync');
}
