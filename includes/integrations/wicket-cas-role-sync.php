<?php

use Wicket\Client;

/**------------------------------------------------------------------
 * Perform operations when the user is authed via CAS, but not yet in Wordpress
 * For testing purposes since $cas_user_data contains data straight from CAS
 * NOTE: Do not put person UUID here or anything else in $_SESSION for any reason.
 * PHP Sessions can be unreliable and collisions can happen.
 ------------------------------------------------------------------*/
function custom_action_before_auth_user_wordpress($cas_user_data) {
  // perhaps log CAS payload, etc
}
add_action('wp_cassify_before_auth_user_wordpress', 'custom_action_before_auth_user_wordpress', 1, 1);

/**------------------------------------------------------------------
 * Perform operations when the user is logging in to Wordpress
 ------------------------------------------------------------------*/
function sync_wicket_data() {
  // if they're logged in via CAS...
  if (wp_get_current_user()->user_login) {
    $client = wicket_api_client_current_user();
  	$person = wicket_current_person();

    $user = wp_get_current_user();
    // first remove all existing roles
    $user->set_role('');

    global $wp_roles;
    if (!isset($wp_roles)){
      $wp_roles = new WP_Roles();
    }

    $roles = $person->role_names;

    // Ignore certain security roles from being synced
    if ( wicket_get_option('wicket_admin_settings_wpcassify_ignore_roles') != '') {
      $ignored_roles = explode(',',wicket_get_option('wicket_admin_settings_wpcassify_ignore_roles'));
      // remove any spaces between the commas in the field if being used
      $ignored_roles = array_map('trim', $ignored_roles);
      foreach ($roles as $key => $role) {
        if (in_array($role, $ignored_roles)) {
          unset($roles[$key]);
        }
      }
    }

    // Sync membership tiers as roles in WP if the option is set
    if ( wicket_get_option('wicket_admin_settings_wpcassify_sync_memberships_as_roles') === '1') {
      // get current person active memberships ids, find the active memberships slug from ids, assign user with roles from active membership tiers
      $memberships = wicket_get_current_person_memberships();
      $active_memberships_ids = [];
      if(isset($memberships["data"])) {
        foreach ($memberships["data"] as $key => $membership) {
          if($membership["attributes"]["status"] == 'Active') {
            $active_memberships_ids[$key] = $membership["relationships"]["membership"]["data"]["id"];
          }
        }
      }
      // look if included membership are active and if yes add to $roles[]
      if(isset($memberships["included"])) {
        foreach ($memberships["included"] as $key => $membership) {
          if (in_array($membership['id'], $active_memberships_ids)) {
            $roles[] = $membership['attributes']['name'];
          }
        }
      }
    }

    // Sync user tags as roles in WP if the option is set
    if ( wicket_get_option('wicket_admin_settings_wpcassify_sync_tags_as_roles') != '') {
      $allowed_tags = explode(',',wicket_get_option('wicket_admin_settings_wpcassify_sync_tags_as_roles'));
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
      }else {
        // clone the subsciber capabilities into a new role
        $subscriber_role = $wp_roles->get_role('subscriber');
        $role_machine = str_replace(' ','_',$role);
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
    return;
}
add_action('wp_login', 'sync_wicket_data');


/**------------------------------------------------------------------
 * Sync data on a specific user at any given point
 ------------------------------------------------------------------*/
function sync_wicket_data_for_person($person_uuid) {
  if (!$person_uuid) {
    return;
  }

	$person = wicket_get_person_by_id($person_uuid);

  $user = get_user_by('login', $person_uuid);
  // first remove all existing roles
  $user->set_role('');

  global $wp_roles;
  if (!isset($wp_roles)){
    $wp_roles = new WP_Roles();
  }

  $roles = $person->role_names;

  // Ignore certain security roles from being synced
  if ( wicket_get_option('wicket_admin_settings_wpcassify_ignore_roles') != '') {
    $ignored_roles = explode(',',wicket_get_option('wicket_admin_settings_wpcassify_ignore_roles'));
    // remove any spaces between the commas in the field if being used
    $ignored_roles = array_map('trim', $ignored_roles);
    foreach ($roles as $key => $role) {
      if (in_array($role, $ignored_roles)) {
        unset($roles[$key]);
      }
    }
  }

  // Sync membership tiers as roles in WP if the option is set
  if ( wicket_get_option('wicket_admin_settings_wpcassify_sync_memberships_as_roles') === '1') {
    // get current person active memberships ids, find the active memberships slug from ids, assign user with roles from active membership tiers
    $memberships = wicket_get_current_person_active_memberships();
    $active_memberships_ids = [];
    if(isset($memberships["data"])) {
      foreach ($memberships["data"] as $key => $membership) {
        if($membership["attributes"]["status"] == 'Active') {
          $active_memberships_ids[$key] = $membership["relationships"]["membership"]["data"]["id"];
        }
      }
    }
    // look if included membership are active and if yes add to $roles[]
    if(isset($memberships["included"])) {
      foreach ($memberships["included"] as $key => $membership) {
        if (in_array($membership['id'], $active_memberships_ids)) {
          $roles[] = $membership['attributes']['name'];
        }
      }
    }
  }

  // Sync user tags as roles in WP if the option is set
  if ( wicket_get_option('wicket_admin_settings_wpcassify_sync_tags_as_roles') != '') {
    $allowed_tags = explode(',',wicket_get_option('wicket_admin_settings_wpcassify_sync_tags_as_roles'));
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
    }else {
      // clone the subsciber capabilities into a new role
      $subscriber_role = $wp_roles->get_role('subscriber');
      $role_machine = str_replace(' ','_',$role);
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

  return;
}

