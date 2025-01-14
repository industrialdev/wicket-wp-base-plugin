<?php


/**
 * COMPONENT NOTES (Newest to oldest)
 *
 * 2025-01-07 - CoulterPeterson
 * 
 * I'm probably a bit behind on these updates, but a big one worth noting is that when the 
 * 'disable_selecting_orgs_with_active_membership' is enabled, and if enough of the active_membership_alert_* 
 * fields are filled out, then a configurable popup modal will appear when the user tries to select an org that they
 * already have a membership with. And, if the user configures one of the buttons as a "BUTTON" (as in not a link or PROCEED action),
 * a dev can hook into that button being clicked to do something advanced with a hook like this:
 * add_action('wicket_component_orgss_active_membership_alert_button_1_clicked', function($action_data){wicket_write_log('HELLO!');wicket_write_log($action_data);}, 10, 1);
 * 
 * 2024-10-23 - CoulterPeterson
 *
 * Added 'display_removal_alert_message' toggleable parameter to the component, which controls a
 * sanity modal prior to the user removing a relationship.
 *
 * 2024-10-10 - CoulterPeterson
 *
 * Added 'no_results_found_message' param so the no results found message can be overriden.
 *
 * 2024-07-30 - CoulterPeterson
 *
 * Added grant_org_editor_on_select param to component.
 *
 * 2024-07-17 - CoulterPeterson
 *
 * Updated the 'active connection' logic to check for an active org membership status rather than an
 * active connection. Also made it so orgs with active connections can't be selected, if a
 * 'disable_selecting_orgs_with_active_membership' param is passed as true.
 *
 * Also added new param 'grant_roster_man_on_purchase' that, if true, willcall to a new internal endpoint
 * that uses wicket_internal_endpoint_flag_for_rm_access()
 * which sets a temporary piece of user meta (roster_man_org_to_grant) so that the user will get
 * Roster Mangement access for the given org UUID on the next order_complete containing a membership product.
 *
 * 2024-07-11 - CoulterPeterson
 *
 * Added checkbox_id_new_org param so that a checkbox field ID can be passed into the component, which will
 * get checked if a new org ends up being created in the process. This way other conditional fields can be
 * shown or hidden based on the value of that checkbox.
 *
 * 2024-07-04 - CoulterPeterson
 *
 * Added disable_create_org_ui param.
 *
 * 2024-06-26 - CoulterPeterson
 *
 * Adding visual indicator for the currently selected organization. Also allowing multiple hidden
 * data fields to exist on the page at once by passing the value of 'selected_uuid_hidden_field_name'
 * to the JS element selector. Lastly, filtered the user's current org relationships by the org type filter,
 * if set.
 *
 * 2024-06-25 - CoulterPeterson
 *
 * Added the 'key' paremeter so a unique identifier can be passed to the component to distinguish it from
 * other components used on the page, if necessary. If the parameter is not passed, a random number is
 * generated and assigned as the components key, making the chance of a conflict extremely low.
 *
 * Also added parameters 'org_term_singular' and 'org_term_plural' so the implementor can change the
 * verbiage used to describe what they're searching for and selecting.
 *
 * 2024-06-17 - CoulterPeterson
 *
 * Moved away from <form> tags and "submit" buttons so that the component will play nicer with Gravity Forms
 * and other methods of embedding it. Added a hidden text field where the selected UUID will be updated,
 * and the name of that field can be changed with a new component parameter. Various bug fixes.
 *
 * 2024-06-13 - CoulterPeterson
 *
 * [X] DONE Allow component to be used with (or focus on) different 'types' of orgs via a component param
 * [X] DONE Emitting custom JS event on org selection with relevant information, so it can be intercepted
 *     by its various intended wrappers.
 *
 * 2024-06-13 - CoulterPeterson
 *
 * Component can currently provide search UI and functionality for searching
 * organizations, creating a relationship between the current user and that
 * organization, and creating a new organization if not initially found (a
 * relationship is created between the user and the new org after org creation).
 * Pre-existing or new user-to-org relationships can also be terminated from the UI,
 * which calls an internal endpoint for that purpose.
 *
 * AlpineJS state variables are leveraged as often as possible when it comes to conditionally
 * displaying certain types of UI, to keep a simple point of truth for the state of the page.
 * PHP component parameters are passed into it, and PHP is only used to conditionally render
 * something if it's a single element where it would be cleaner code to change wording conditionally
 * by using PHP conditionals.
 *
 * The relationship type between the user and newly-created org can be set by passing
 * the $relationshipTypeUponOrgCreation component paremeter, and the 'mode' of relationship
 * can be set by passing $relationshipMode. If the $newOrgTypeOverride param is left blank,
 * the user will be able to choose the type of the newly-created organization from the frontend,
 * but if an override org type slug is provided via $newOrgTypeOverride, then the frontend dropdown
 * will be hidden and the override value will be used.
 *
 * $searchMode is set to 'org' by default, but 'group' mode is now partially supported. When set to
 * 'group', the UI labels will change to group, and a different search endpoint will be called to
 * search groups instead of orgs. TODOs for supporting groups mode fully:
 *  [] Search for current user's group memberships on load and populate $person_to_org_connections
 *  [] Create and connect an internal endpoint for creating a new group, if we will be supporting that.
 *  [] Create and connect an internal endpoint for adding a user to a group when they press 'select'
 *     on one in search, using wicket_add_group_member()
 *  [] Misc. UI adjustments related to these changes, such as ensuring that the list of "current groups"
 *     at the top of the component gets updated after the user is successfully added to a group. (Like
 *     in selectOrgAndCreateRelationship() )
 *
 * General TODOs:
 *  [X] Allow component to be used with (or focus on) different 'types' of orgs via a component param
 *  [x] Update the 'active connection' logic to check for an active membership status rather than an
 *     active connection
 *  [X] Determine how to cleanly trigger a "next step" in various contexts while providing the selected UUID
 *     and any other needed information. For example, if it's used on a custom template, we may want to emit
 *     a custom JS event or call a specified function name after the user has completed the needed operations,
 *     whereas in a Gravity Form, we'll need to talk to our wrapper so the wrapper can make the GF-specific calls
 *     to indicate a completed form field. In a Block we may give the user the option to provide a URL that they can
 *     redirect to when they're done with the info passed in as parameters. TLDR: yea I think we'll just emit a custom
 *     JS event and the custom template or block/GF element wrapper can listen for that and do what they need to do.
 *  [] Proper localization support.
 */


$defaults  = [
	'classes'                                       => [],
  'search_mode'                                   => 'org', // Options: org, groups, ...
  'search_org_type'                               => '',
  'relationship_type_upon_org_creation'           => 'employee',
  'relationship_mode'                             => 'person_to_organization',
  'new_org_type_override'                         => '',
  'selected_uuid_hidden_field_name'               => 'orgss-selected-uuid',
  'checkbox_id_new_org'                           => '',
  'key'                                           => rand(0,99999999),
  'org_term_singular'                             => '',
  'org_term_plural'                               => '',
  'no_results_found_message'                      => '',
  'disable_create_org_ui'                         => false,
  'disable_selecting_orgs_with_active_membership' => false,
  'active_membership_alert_title'                 => '',
  'active_membership_alert_body'                  => '',
  'active_membership_alert_button_1_text'         => __('Proceed', 'wicket'),
  'active_membership_alert_button_1_url'          => 'PROCEED', // non URLs can be PROCEED, and BUTTON for a dummy press devs will do something with
  'active_membership_alert_button_1_style'        => 'primary',
  'active_membership_alert_button_1_new_tab'      => false,
  'active_membership_alert_button_2_text'         => '',
  'active_membership_alert_button_2_url'          => '',
  'active_membership_alert_button_2_style'        => 'secondary',
  'active_membership_alert_button_2_new_tab'      => false,
  'grant_roster_man_on_purchase'                  => false, // Grants membership_manager role for selected org on next payment_complete hook
  'grant_org_editor_on_select'                    => false, // Grants org_editor role for selected role instantly on select
  'hide_remove_buttons'                           => false,
  'hide_select_buttons'                           => false,
  'display_removal_alert_message'                 => false,
  'title'                                         => '',
  'response_message'                              => '', // class name of the container where the response message will be displayed
];
$args                                          = wp_parse_args( $args, $defaults );
$classes                                       = $args['classes'];
$searchMode                                    = $args['search_mode'];
$searchOrgType                                 = $args['search_org_type'];
$relationshipTypeUponOrgCreation               = $args['relationship_type_upon_org_creation'];
$relationshipMode                              = $args['relationship_mode'];
$newOrgTypeOverride                            = $args['new_org_type_override'];
$selectedUuidHiddenFieldName                   = $args['selected_uuid_hidden_field_name'];
$checkboxIdNewOrg                              = $args['checkbox_id_new_org'];
$key                                           = $args['key'];
$orgTermSingular                               = $args['org_term_singular']; 
$orgTermPlural                                 = $args['org_term_plural'];
$noResultsFoundMessage                         = $args['no_results_found_message'];
$disable_create_org_ui                         = $args['disable_create_org_ui'];
$disable_selecting_orgs_with_active_membership = $args['disable_selecting_orgs_with_active_membership'];
$active_membership_alert_title                 = $args['active_membership_alert_title'];              
$active_membership_alert_body                  = $args['active_membership_alert_body'];
$active_membership_alert_button_1_text         = $args['active_membership_alert_button_1_text'];
$active_membership_alert_button_1_url          = $args['active_membership_alert_button_1_url']; // Can be a URL, or "PROCEED" to continue 
$active_membership_alert_button_1_style        = $args['active_membership_alert_button_1_style'];
$active_membership_alert_button_1_new_tab      = $args['active_membership_alert_button_1_new_tab'];
$active_membership_alert_button_2_text         = $args['active_membership_alert_button_2_text'];
$active_membership_alert_button_2_url          = $args['active_membership_alert_button_2_url'];
$active_membership_alert_button_2_style        = $args['active_membership_alert_button_2_style'];
$active_membership_alert_button_2_new_tab      = $args['active_membership_alert_button_2_new_tab'];
$grant_roster_man_on_purchase                  = $args['grant_roster_man_on_purchase'];
$grant_org_editor_on_select                    = $args['grant_org_editor_on_select'];
$hide_remove_buttons                           = $args['hide_remove_buttons'];
$hide_select_buttons                           = $args['hide_select_buttons'];
$display_removal_alert_message                 = $args['display_removal_alert_message'];
$title                                         = $args['title'];
$responseMessage                               = $args['response_message'];

if( !empty($orgTermSingular) ) {
  $orgTermSingular = __($orgTermSingular, 'wicket');
} else {
  if( $searchMode == 'org' ) {
    $orgTermSingular = __('Organization', 'wicket');
  }
  if( $searchMode == 'groups' ) {
    $orgTermSingular = __('Group', 'wicket');
  }
}

$orgTermSingularCap              = ucfirst(strtolower( $orgTermSingular ));
$orgTermSingularLower            = strtolower( $orgTermSingular );

if( !empty($orgTermPlural) ) {
  $orgTermPlural = __($orgTermPlural, 'wicket');
} else {
  if( $searchMode == 'org' ) {
    $orgTermPlural  = __('Organizations', 'wicket');
  }
  if( $searchMode == 'groups' ) {
    $orgTermPlural  = __('Groups', 'wicket');
  }
}

$orgTermPluralCap              = ucfirst(strtolower( $orgTermPlural ));
$orgTermPluralLower            = strtolower( $orgTermPlural );
if( empty($noResultsFoundMessage) ) {
  $noResultsFoundMessage = sprintf(__('Sorry, no %s match your search. Please try again.', 'wicket'), $orgTermPluralLower);
} else {
  $noResultsFoundMessage = __($noResultsFoundMessage, 'wicket');
}

$current_person_uuid = wicket_current_person_uuid();

// Get current lang
$lang = 'en';
if( defined( 'ICL_LANGUAGE_CODE' ) ) {
  $lang = ICL_LANGUAGE_CODE;
}

// Get users current org/group relationships
$person_to_org_connections = [];
if( $searchMode == 'org' ) {
  $current_connections = wicket_get_person_connections();

  foreach( $current_connections['data'] as $connection ) {
    $connection_id = $connection['id'];
    if( isset( $connection['attributes']['connection_type'] ) ) {
      $org_id = $connection['relationships']['organization']['data']['id'];

      $org_info = wicket_get_organization_basic_info( $org_id, $lang );
      $org_memberships = wicket_get_org_memberships($org_id );

      $has_active_membership = false;
      if( !empty( $org_memberships ) ) {
        foreach( $org_memberships as $membership ) {
          if( isset( $membership['membership'] ) ) {
            if( isset( $membership['membership']['attributes'] ) ) {
              if( isset( $membership['membership']['attributes']['active'] ) ) {
                if( $membership['membership']['attributes']['active'] ) {
                  $has_active_membership = true;
                }
              }
            }
          }
        }
      }
      $person_to_org_connections[] = [
        'connection_id'     => $connection['id'],
        'connection_type'   => $connection['attributes']['connection_type'],
        'starts_at'         => $connection['attributes']['starts_at'],
        'ends_at'           => $connection['attributes']['ends_at'],
        'tags'              => $connection['attributes']['tags'],
        'active_membership' => $has_active_membership,
        'active_connection' => $connection['attributes']['active'],
        'org_id'            => $org_id,
        'org_name'          => $org_info['org_name'],
        'org_description'   => $org_info['org_description'],
        'org_type_pretty'   => __($org_info['org_type_pretty'], 'wicket'),
        'org_type'          => $org_info['org_type'], 'wicket',
        'org_status'        => $org_info['org_status'],
        'org_parent_id'     => $org_info['org_parent_id'],
        'org_parent_name'   => $org_info['org_parent_name'],
        'person_id'         => $connection['relationships']['person']['data']['id'],
      ];
    }
  }
} else if( $searchMode == 'groups' ) {
  // TODO: Get current MDP org memberships and save to $person_to_org_connections
}

// Get list of org types to make available to the user if an admin override is not provided
$available_org_types = wicket_get_resource_types( 'organizations' );

?>

<style>
  .orgss_disabled_button {
    background: #efefef !important;
    color: #a3a3a3 !important;
    border-color: #efefef !important;
    pointer-events: none;
  }
  .orgss_disabled_button_hollow {
    background: rgba(0,0,0,0) !important;
    color: #a3a3a3 !important;
    border-color: rgba(0,0,0,0) !important;
    pointer-events: none;
  }
  .orgss_error {
    color: red;
    font-size: .8em;
    margin-top: 5px;
  }
</style>

<div class="container component-org-search-select relative form <?php echo implode(' ', $classes); ?>" x-data="orgss_<?php echo $key; ?>" x-init="init">

  <?php // Debug log of the selection custom event when fired ?>

  <?php // Loading overlay ?>
  <div x-transition x-cloak
    class="flex justify-center items-center w-full text-dark-100 text-heading-3xl py-10 absolute h-full left-0 right-0 mx-auto bg-white bg-opacity-70"
    x-bind:class="isLoading ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none' "
    >
    <i class="fa-solid fa-arrows-rotate fa-spin"></i>
  </div>

  <?php // Confirmation Popup ?>
  <div x-transition x-cloak
    class="flex justify-center items-center w-full text-dark-100 py-10 absolute h-full left-0 right-0 mx-auto bg-white bg-opacity-70"
    x-bind:class="showingRemoveConfirmation && removeConfirmationIsEnabled ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none' "
    >
    <div x-transition class="rounded-150 bg-white border flex items-center flex-col p-5">
      <div class="flex w-full justify-end mb-4">
        <button x-on:click.prevent="showingRemoveConfirmation = false" class="font-semibold"><?php _e('Close X', 'wicket') ?></button>
      </div>
      <div class="font-semibold"><?php _e('Please confirm you\'d like to end your relationship with this Organization', 'wicket') ?></div>
      <div class="mt-4 mb-6">
        <?php _e('Any assigned membership with the organization will be inactivated.', 'wicket') ?>
      </div>
      <div class="flex w-full justify-evenly">
        <?php get_component( 'button', [
          'variant'  => 'secondary',
          'reversed' => false,
          'label'    => __( 'Cancel', 'wicket' ),
          'type'     => 'button',
          'atts'  => [
            'x-on:click.prevent="showingRemoveConfirmation = false"',
          ],
          'classes' => [
            'items-center',
            'justify-center',
            'w-5/12'
          ]
        ] ); ?>
        <?php get_component( 'button', [
          'variant'  => 'primary',
          'label'    => __( 'Remove Organization', 'wicket' ),
          'type'     => 'button',
          'atts'  => [ 'x-on:click.prevent="terminateRelationship()"' ],
          'classes' => [
            'items-center',
            'justify-center',
            'w-5/12'
          ],
        ] ); ?>
      </div>
    </div>
  </div> <?php // End confirmation popup ?>

  <?php // Active Membership Alert Popup ?>
  <div x-transition x-cloak
    class="flex justify-center items-center w-full text-dark-100 py-10 absolute h-full left-0 right-0 mx-auto bg-white bg-opacity-70"
    x-bind:class="showingActiveMembershipAlert && activeMembershipAlertAvailable ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none' "
    >
    <div x-transition class="rounded-150 bg-white border flex items-center flex-col p-5">
      <div class="flex w-full justify-end mb-4">
        <button x-on:click.prevent="showingActiveMembershipAlert = false" class="font-semibold"><?php _e('Close X', 'wicket') ?></button>
      </div>
      <div x-text="activeMembershipAlertTitle" class="font-semibold"></div>
      <div x-text="activeMembershipAlertBody" class="mt-4 mb-6"></div>
      <div class="flex w-full justify-evenly">
        <?php 
        if(!empty($active_membership_alert_button_1_text)
           && !empty($active_membership_alert_button_1_url)
           && !empty($active_membership_alert_button_1_style)
          ) {
          if($active_membership_alert_button_1_url == 'PROCEED') {
            get_component( 'button', [
              'variant'  => $active_membership_alert_button_1_style,
              'reversed' => false,
              'label'    => $active_membership_alert_button_1_text, 
              'type'     => 'button',
              'atts'  => [
                'x-on:click="doWpAction(\'orgss_active_membership_alert_button_1_clicked\');activeMembershipAlertProceedChosen = true;selectOrgAndCreateRelationship(activeMembershipAlertOrgUuid, activeMembershipAlertEvent);"'
              ],
              'classes' => [
                'items-center',
                'justify-center',
                'w-5/12'
              ]
            ] );
          } else if($active_membership_alert_button_1_url == 'BUTTON') {
            // If this is set to be a developer button, fire the action, dismiss the modal, and do nothing else (no org selection)
            get_component( 'button', [
              'variant'  => $active_membership_alert_button_1_style,
              'reversed' => false,
              'label'    => $active_membership_alert_button_1_text, 
              'type'     => 'button',
              'atts'  => [
                'x-on:click="doWpAction(\'orgss_active_membership_alert_button_1_clicked\');dispatchWindowEvent(\'orgss-existing-membership-modal-button1-' . str_replace('/', '', $_SERVER['REQUEST_URI']) . '\', {});showingActiveMembershipAlert = false;"'
              ],
              'classes' => [
                'items-center',
                'justify-center',
                'w-5/12'
              ]
            ] );
          } else {
            // Treat it as a link
            get_component( 'button', [
              'variant'  => $active_membership_alert_button_1_style,
              'reversed' => false,
              'label'    => $active_membership_alert_button_1_text,
              'type'     => 'button',
              'a_tag'    => true,
              'link'     => $active_membership_alert_button_1_url,
              'link_target' => $active_membership_alert_button_1_new_tab ? '_blank' : '_self',
              'atts'  => [
                'x-on:click="doWpAction(\'orgss_active_membership_alert_button_1_clicked\');"'
              ],
              'classes' => [
                'items-center',
                'justify-center',
                'w-5/12'
              ]
            ] );
          }
        }

        if(!empty($active_membership_alert_button_2_text)
        && !empty($active_membership_alert_button_2_url)
        && !empty($active_membership_alert_button_2_style)
        ) {
          if($active_membership_alert_button_2_url == 'PROCEED') {
            get_component( 'button', [
              'variant'  => $active_membership_alert_button_2_style,
              'reversed' => false,
              'label'    => $active_membership_alert_button_2_text, 
              'type'     => 'button',
              'atts'  => [
                'style="margin-left:20px;"',
                'x-on:click="doWpAction(\'orgss_active_membership_alert_button_2_clicked\');activeMembershipAlertProceedChosen = true;selectOrgAndCreateRelationship(activeMembershipAlertOrgUuid, activeMembershipAlertEvent);"'
              ],
              'classes' => [
                'items-center',
                'justify-center',
                'w-5/12'
              ]
            ] );
          } else if($active_membership_alert_button_2_url == 'BUTTON') {
            // If this is set to be a developer button, fire the action, dismiss the modal, and do nothing else (no org selection)
            get_component( 'button', [
              'variant'  => $active_membership_alert_button_1_style,
              'reversed' => false,
              'label'    => $active_membership_alert_button_1_text, 
              'type'     => 'button',
              'atts'  => [
                'style="margin-left:20px;"',
                'x-on:click="doWpAction(\'orgss_active_membership_alert_button_2_clicked\');dispatchWindowEvent(\'orgss-existing-membership-modal-button2-' . str_replace('/', '', $_SERVER['REQUEST_URI']) . '\', {});showingActiveMembershipAlert = false;"'
              ],
              'classes' => [
                'items-center',
                'justify-center',
                'w-5/12'
              ]
            ] );
          } else {
            // Treat it as a link
            get_component( 'button', [
              'variant'  => $active_membership_alert_button_2_style,
              'reversed' => false,
              'label'    => $active_membership_alert_button_2_text,
              'type'     => 'button',
              'a_tag'    => true,
              'link'     => $active_membership_alert_button_2_url,
              'link_target' => $active_membership_alert_button_2_new_tab ? '_blank' : '_self',
              'atts'  => [
                'style="margin-left:20px;"',
                'x-on:click="doWpAction(\'orgss_active_membership_alert_button_2_clicked\');"'
              ],
              'classes' => [
                'items-center',
                'justify-center',
                'w-5/12'
              ]
            ] );
          }
        }
        ?>
      </div>
    </div>
  </div><?php // End active membership alert modal ?>

  <div class="orgss-search-form flex flex-col bg-dark-100 bg-opacity-5 rounded-100 p-3">
    <div x-show="currentConnections.length > 0" x-cloak>
      <?php
      if(empty($title)) : ?>
        <h2 class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-org-search-select__current-orgs-title' : 'font-bold text-body-lg my-3 orgss-search-form__title' ?>"><?php _e('Your current', 'wicket') ?> <?php echo $orgTermPluralLower; ?></h2>
      <?php else: ?>
        <h2 class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-org-search-select__current-orgs-title' : 'font-bold text-body-lg my-3 orgss-search-form__title' ?>"><?php esc_html_e($title, 'wicket'); ?></h2>
      <?php endif; ?>

      <template x-for="(connection, index) in currentConnections" :key="connection.connection_id" x-transition>
        <div
          x-show="connection.connection_type == relationshipMode
                && ( connection.org_type.toLowerCase() === searchOrgType.toLowerCase() || searchOrgType === '' )
                && connection.active_connection" 
          
          <?php if ( defined( 'WICKET_WP_THEME_V2' ) ) : ?>
            class="component-org-search-select__card"
            x-bind:class="connection.org_id == selectedOrgUuid ? 'component-org-search-select__card--selected' : '' "
          <?php else : ?>
            class="rounded-100 flex justify-between bg-white p-4 mb-3"
            x-bind:class="connection.org_id == selectedOrgUuid ? 'border-success-040 border-opacity-100 border-4' : 'border border-dark-100 border-opacity-5' "
          <?php endif; ?>
        >

        <div class="current-org-listing-left" x-data="{
          description: connection.org_description,
          cutoffLength: 70,
          showOrgParentName: false,
          orgParentName: '',

          init() {
            // Truncate the description
            if(this.description) {
              let addElipses = this.description.length > this.cutoffLength;
              this.description = this.description.substring(0, this.cutoffLength);
              if(addElipses) {
                this.description = this.description.trim() + '...';
              }
            }

            if(connection.org_parent_name) {
              if(connection.org_parent_name !== null) {
                this.showOrgParentName = connection.org_parent_name.length > 0;
                orgParentName = connection.org_parent_name;
              }
            }
          },
        }">
            <div class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-org-search-select__org-type' : 'font-bold text-body-xs' ?>" x-text="connection.org_type_pretty"></div>
            <div class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-org-search-select__card-top-header' : 'flex mb-2 items-center' ?>">
              <div x-text="connection.org_name" class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-org-search-select__org-name' : 'font-bold text-body-sm mr-5' ?>"></div>
              <div>
                <template x-if="connection.active_membership">
                  <div class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-org-search-select__active-membership-label' : '' ?>" >
                    <i class="fa-solid fa-circle <?php echo defined( 'WICKET_WP_THEME_V2' ) ? '' : 'text-[#08d608]' ?>"></i> <span class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? '' : 'text-body-xs' ?>"><?php _e('Active Membership', 'wicket') ?></span>
                  </div>
                </template>
                <template x-if="! connection.active_membership">
                  <div class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-org-search-select__inactive-membership-label' : '' ?>" >
                    <i class="fa-solid fa-circle <?php echo defined( 'WICKET_WP_THEME_V2' ) ? '' : 'text-[#A1A1A1]' ?>"></i> <span class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? '' : 'text-body-xs' ?>"><?php _e('Inactive Membership', 'wicket') ?></span>
                  </div>
                </template>
              </div>
            </div>
            <div x-show="showOrgParentName" class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-org-search-select__org-parent-name' : 'mb-3' ?>" x-text="orgParentName"></div>
            <div x-text="description" class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-org-search-select__org-description' : '' ?>" ></div>
          </div>
          <div class="current-org-listing-right <?php echo defined( 'WICKET_WP_THEME_V2' ) ? '' : 'flex items-center gap-1' ?>">
            <?php get_component( 'button', [ 
              'variant'  => 'secondary',
              'reversed' => false,
              'label'    => __( 'Select', 'wicket' ) . ' ' . $orgTermSingularCap,
              'type'     => 'button',
              'classes'  => [ 'whitespace-nowrap' ],
              'atts'     => [ 
                'x-on:click.prevent="selectOrgAndCreateRelationship($data.connection.org_id, $event, connection.active_membership, true)"',
                'x-bind:class="{
                  \'orgss_disabled_button\': connection.active_membership && ( disableSelectingOrgsWithActiveMembership && !activeMembershipAlertAvailable )
                }"',
                'x-show="!hideSelectButtons"',
              ]
            ] ); ?>
            <?php get_component( 'button', [ 
              'variant'  => 'ghost',
              'label'    => __( 'Remove', 'wicket' ),
              'suffix_icon' => 'fa-regular fa-trash',
              'type'     => 'button',
              'classes'  => [ 'whitespace-nowrap' ],
              'atts'     => [ 'x-on:click.prevent="terminateRelationship($data.connection.connection_id)"', 
                              'x-show="!hideRemoveButtons"' ]
            ] ); ?>
          </div>
        </div>
      </template>
    </div>
    
    <div 
      class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'label' : 'font-bold text-body-md mb-2' ?>"
      x-text=" currentConnections.length > 0 ? 'Looking for a different <?php echo $orgTermSingularLower; ?>?' : '<?php _e('Search for your', 'wicket') ?> <?php echo $orgTermSingularLower; ?>' "></div>

    <div class="flex">
      <?php // Can add `@keyup="if($el.value.length > 3){ handleSearch(); } "` to get autocomplete, but it's not quite fast enough ?>
      <input x-model="searchBox" @keydown.enter.prevent.stop="handleSearch()" type="text" class="orgss-search-box w-full mr-2" placeholder="Search by <?php echo $orgTermSingularLower; ?> name" />
      <?php get_component( 'button', [
        'variant'  => 'primary',
        'label'    => __( 'Search', 'wicket' ),
        'type'     => 'button',
        'atts'  => [ 'x-on:click.prevent="handleSearch()"' ],
      ] ); ?>
     </div>
     <div id="orgss_search_message" class="orgss_error" x-cloak x-show="showSearchMessage"></div>
     <div class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-org-search-select__matching-orgs-title' : 'mt-4 mb-1' ?>" x-show="firstSearchSubmitted || isLoading" x-cloak><?php _e('Matching', 'wicket') ?> <?php echo $orgTermPluralLower; ?><?php // (Selected org: <span x-text="selectedOrgUuid"></span>)?></div>
     <div class="orgss-results" x-bind:class="results.length == 0 ? '' : 'orgss-results--has-results' " >
      <div class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-org-search-select__search-container' : 'flex flex-col bg-white px-4 max-h-80 overflow-y-scroll' ?>">
        <div x-show="results.length == 0 && searchBox.length > 0 && firstSearchSubmitted && !isLoading" x-transition x-cloak 
          class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-org-search-select__no-results' : 'flex justify-center items-center w-full text-dark-100 text-body-md py-4' ?>">
          <?php echo $noResultsFoundMessage; ?>
        </div>
        <template x-for="(result, uuid) in results" x-cloak>
          <div
            class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-org-search-select__matching-org-item' : 'px-1 py-3 border-b border-dark-100 border-opacity-5 flex justify-between items-center' ?>"
          >
            <div class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-org-search-select__matching-org-title' : 'font-bold' ?>" x-text="result.name"></div>
            <?php get_component( 'button', [
              'variant'  => 'secondary',
              'reversed' => false,
              'label'    => __( 'Select', 'wicket' ),
              'type'     => 'button',
              'classes'  => [ '' ],
              'atts'     => [
                'x-on:click.prevent="selectOrgAndCreateRelationship($data.result.id, $event, result.active_membership )"',
                'x-bind:class="{
                  \'orgss_disabled_button_hollow\': isOrgAlreadyAConnection( $data.result.id ),
                  \'orgss_disabled_button_hollow\': result.active_membership && ( disableSelectingOrgsWithActiveMembership && !activeMembershipAlertAvailable )
                }"'
              ]
            ] ); ?>
          </div>
        </template>

      </div>
    </div>
  </div>

  <div x-show="firstSearchSubmitted && !disableCreateOrgUi" x-cloak class="orgss-create-org-form <?php echo defined( 'WICKET_WP_THEME_V2' ) ? '' : 'mt-4 flex flex-col bg-dark-100 bg-opacity-5 rounded-100 p-3' ?>">
    <div class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-org-search-select__cant-find-org-title' : 'font-bold text-body-md mb-2' ?>"><?php _e('Can\'t find your', 'wicket') ?> <?php echo $orgTermSingularLower; ?>?</div>
    <div class="flex">
      <div x-bind:class="newOrgTypeOverride.length <= 0 ? 'w-5/12' : 'w-10/12'" class="flex flex-col mr-2">
        <label><?php _e('Name of the', 'wicket') ?> <?php echo $orgTermSingularCap; ?>*</label>
        <input x-model="newOrgNameBox" @keyup.enter.prevent.stop="handleOrgCreate($event)" type="text" name="company-name" class="w-full" />
      </div>
      <div x-show="newOrgTypeOverride.length <= 0" class="flex flex-col w-5/12 mr-2">
      <label><?php _e('Type of', 'wicket') ?> <?php echo $orgTermSingularCap; ?>*</label>
        <select x-model="newOrgTypeSelect" x-on:change="newOrgTypeSelect = $el.value;" class="w-full">
          <template x-for="(orgType, index) in availableOrgTypes.data">
            <option x-bind:class="'orgss_org_type_' + orgType.attributes.slug" x-bind:value="orgType.attributes.slug" x-text="orgType['attributes']['name_' + lang]"
              ><?php _e('Org type', 'wicket') ?></option>
          </template>
        </select>
      </div>
      <div class="flex flex-col w-2/12 items-center justify-end">
        <?php get_component( 'button', [
            'variant'  => 'primary',
            'label'    => __( 'Add Details', 'wicket' ),
            'type'     => 'button',
            'classes'  => [ 'w-full', 'justify-center' ],
            'atts'  => [ 'x-on:click.prevent="handleOrgCreate($event)"' ],
          ] ); ?>
        </div>
      </div>
      <div x-show="displayDuplicateOrgWarning" class="orgss-red-alert flex mt-4 p-4 border-solid border-l-4 border-t-0 border-r-0 border-b-0 <?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'bg-[--state-error-light] border-[--state-error]' : 'bg-[#f5c2c7] border-[#dc3545]' ?>">
          <div class="icon-col flex flex-col justify-center px-2">
            <?php 
              if (defined( 'WICKET_WP_THEME_V2' )) {
                get_component( 'icon', [
                  'classes' => ['text-heading-xl', 'text-[--state-error]'],
                  'icon'  => 'fa-regular fa-triangle-exclamation',
                ] );
              } else {
                get_component( 'icon', [
                  'classes' => ['text-heading-xl', 'text-[#dc3545]'],
                  'icon'  => 'fa-regular fa-triangle-exclamation',
                ] );
              } ?>
          </div>
          <div class="text-col">
            <div class="font-bold text-body-lg"><?php echo sprintf(__('%s you are trying to add already exists', 'wicket'), $orgTermSingularCap);?></div>
            <div><?php _e('Please enter the name in the search field above to find the existing record.', 'wicket');?></div>
          </div>
      </div>
    </div>

    <?php // Hidden form field that can be used to pass the selected UUID along, like in Gravity Forms ?>
    <input type="hidden" name="<?php echo $selectedUuidHiddenFieldName; ?>" value="<?php if(isset($_POST[$selectedUuidHiddenFieldName])){echo $_POST[$selectedUuidHiddenFieldName];} ?>" />

</div>

<?php /* Broken-out Alpine data for tidyness */ ?>
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('orgss_<?php echo $key; ?>', () => ({
            lang: '<?php echo $lang; ?>',
            isLoading: false,
            showingRemoveConfirmation: false,
            removeConfirmationIsEnabled: <?php echo $display_removal_alert_message  ? 'true' : 'false'; ?>,
            connectionIdSelectedForRemoval: '',
            firstSearchSubmitted: false,
            searchType: '<?php echo $searchMode; ?>',
            relationshipTypeUponOrgCreation: '<?php echo $relationshipTypeUponOrgCreation; ?>',
            relationshipMode: '<?php echo $relationshipMode; ?>',
            newOrgTypeOverride: '<?php echo $newOrgTypeOverride; ?>',
            searchOrgType: '<?php echo $searchOrgType; ?>',
            availableOrgTypes: <?php echo json_encode( $available_org_types ); ?>,
            disableCreateOrgUi: <?php echo $disable_create_org_ui ? 'true' : 'false'; ?>,
            disableSelectingOrgsWithActiveMembership: <?php echo $disable_selecting_orgs_with_active_membership ? 'true' : 'false'; ?>,
            
            showingActiveMembershipAlert: false,
            activeMembershipAlertAvailable: false,
            activeMembershipAlertProceedChosen: false,
            activeMembershipAlertOrgUuid: '',
            activeMembershipAlertEvent: null,
            activeMembershipAlertTitle: '<?php echo $active_membership_alert_title; ?>',
            activeMembershipAlertBody: '<?php echo $active_membership_alert_body; ?>',

            selectedOrgUuid: '',
            searchBox: '',
            newOrgNameBox: '',
            newOrgTypeSelect: '',
            showSearchMessage: false,
            results: [],
            apiUrl: "<?php echo get_rest_url( null, 'wicket-base/v1/' ); ?>",
            currentConnections: <?php echo json_encode( $person_to_org_connections ); ?>,
            currentPersonUuid: "<?php echo $current_person_uuid; ?>",
            grantRosterManOnPurchase: <?php echo $grant_roster_man_on_purchase ? 'true' : 'false'; ?>,
            grantOrgEditorOnSelect: <?php echo $grant_org_editor_on_select  ? 'true' : 'false'; ?>,
            hideRemoveButtons: <?php echo $hide_remove_buttons  ? 'true' : 'false'; ?>,
            hideSelectButtons: <?php echo $hide_select_buttons ? 'true' : 'false'; ?>,
            displayDuplicateOrgWarning: false,

            init() {
              //console.log(this.currentConnections);

              // Set an initial value for the dynamic select
              this.newOrgTypeSelect = this.availableOrgTypes.data[0].attributes.slug;

              // Determine if the active membership modal has enough data to use
              if(this.disableSelectingOrgsWithActiveMembership && (this.activeMembershipAlertTitle.length > 0 || this.activeMembershipAlertBody.length > 0)) {
                this.activeMembershipAlertAvailable = true;
              }
            },
            handleSearch(e = null) {
              if(e) {
                e.preventDefault();
              }

              if( this.searchBox.length < 1 ) {
                this.setSearchMessage('<?php _e('Please provide a search term', 'wicket') ?>');
                return;
              } else {
                this.showSearchMessage = false; // Clear notice in case its visible
              }

              this.results = [];

              if( this.searchType == 'org' ) {
                this.searchOrgs( this.searchBox );
              } else if( this.searchType == 'groups' ) {
                this.searchGroups( this.searchBox );
              }
            },
            handleOrgCreate(e = null) {
              if(e) {
                e.preventDefault();
              }

              if( this.searchType == 'groups' ) {
                // Handle group creation
              } else {
                // Creating an org
                let newOrgName = this.newOrgNameBox;

                let newOrgType = '';
                if( this.newOrgTypeOverride.length > 0 ) {
                  newOrgType = this.newOrgTypeOverride;
                } else {
                  newOrgType = this.newOrgTypeSelect;
                }

                this.createOrganization( newOrgName, newOrgType, e );
              }

            },
            setSearchMessage(message) {
              document.getElementById('orgss_search_message').innerHTML = message;
              this.showSearchMessage = true;
            },
            async searchOrgs( searchTerm, showLoading = true ) {
              if(showLoading) {
                this.isLoading = true;
              }

              let orgType = this.searchOrgType;
              let data = {};
              if( orgType.length > 0 ) {
                data = {
                  "searchTerm": searchTerm,
                  "orgType": orgType,
                };
              } else {
                data = {
                  "searchTerm": searchTerm
                };
              }

              // Ref: https://developer.mozilla.org/en-US/docs/Web/API/Fetch_API/Using_Fetch
              // Ref 2: https://stackoverflow.com/a/43263012
              let results = await fetch(this.apiUrl + 'search-orgs', {
                method: "POST", // *GET, POST, PUT, DELETE, etc.
                mode: "cors", // no-cors, *cors, same-origin
                cache: "no-cache", // *default, no-cache, reload, force-cache, only-if-cached
                credentials: "same-origin", // include, *same-origin, omit
                headers: {
                  "Content-Type": "application/json",
                  // 'Content-Type': 'application/x-www-form-urlencoded',
                  "X-WP-Nonce": "<?php echo wp_create_nonce('wp_rest'); ?>",
                },
                redirect: "follow", // manual, *follow, error
                referrerPolicy: "no-referrer", // no-referrer, *no-referrer-when-downgrade, origin, origin-when-cross-origin, same-origin, strict-origin, strict-origin-when-cross-origin, unsafe-url
                body: JSON.stringify(data), // body data type must match "Content-Type" header
              }).then(response => response.json())
                .then(data => {
                  if( !data.success ) {
                    this.results = [];
                  } else {
                    this.results = data.data;
                  }

                  if(showLoading) {
                    this.isLoading = false;
                  }

                  if( !this.firstSearchSubmitted ) {
                    this.firstSearchSubmitted = true;
                  }
                });

            },
            selectOrg( orgUuid, incomingEvent = null, dispatchEvent = true ) {
              // Update state
              this.selectedOrgUuid = orgUuid;
              document.querySelector('input[name="<?php echo $selectedUuidHiddenFieldName; ?>"]').value = orgUuid;

              if(dispatchEvent) {
                // selectOrg() is the last function call used in the process, whether selecting an existing
                // org or creating a new one and then selecting it, so from here we'll dispatch the selection info
                let orgInfo = this.getOrgFromConnectionsByUuid( orgUuid );

                this.dispatchWindowEvent("orgss-selection-made", {
                  uuid: orgUuid,
                  searchType: this.searchType,
                  orgDetails: orgInfo,
                  event: incomingEvent,
                });
              }
              
              if(this.grantRosterManOnPurchase) {
                this.flagForRosterManagementAccess(orgUuid);
              }
              if(this.grantOrgEditorOnSelect) {
                this.grantOrgEditor( this.currentPersonUuid, orgUuid );
              }
            },
            dispatchWindowEvent(name, details) {
              newEvent = new CustomEvent(name, {
                detail: details
              });
              window.dispatchEvent(newEvent);
            },
            selectOrgAndCreateRelationship( orgUuid, event = null, existingActiveMembership = false, skipCreateRelationship = false ) {
              // TODO: Handle when a Group is selected instead of an org

              // ------------------
              // Active Membership modal alert
              // ------------------

              // Only show if the 'Disable Org Select with Active Membership' feature is enabled, sufficient modal data
              // was populated, and the user hasn't already chosen to proceed with the usual actions
              if(existingActiveMembership && this.disableSelectingOrgsWithActiveMembership && this.activeMembershipAlertAvailable && !this.activeMembershipAlertProceedChosen) {
                // Display the modal
                this.showingActiveMembershipAlert = true;

                // Stash data away so we can resume this operation later
                this.activeMembershipAlertOrgUuid = orgUuid;
                if(event) {
                  this.activeMembershipAlertEvent = event;
                }

                this.selectOrg( orgUuid, event, false ); // Make the selection so we have the org UUID available for possible PHP hook usage off the modal
                return;
              }

              // The user choose to proceed the modal
              if(this.disableSelectingOrgsWithActiveMembership && this.activeMembershipAlertAvailable && this.activeMembershipAlertProceedChosen) {
                // Clear temp data
                this.activeMembershipAlertOrgUuid = '';
                this.activeMembershipAlertEvent = null;

                // Close modal
                this.showingActiveMembershipAlert = false;
              }

              // ------------------
              // Usual operations
              // ------------------
              if(!skipCreateRelationship) {
                this.createRelationship( this.currentPersonUuid, orgUuid, this.relationshipMode, this.relationshipTypeUponOrgCreation );
              }
              this.selectOrg( orgUuid, event );
            },
            async flagForRosterManagementAccess( orgUuid ) {
              let data = {
                "orgUuid": orgUuid,
              };

              let results = await fetch(this.apiUrl + 'flag-for-rm-access', {
                method: "POST",
                mode: "cors",
                cache: "no-cache",
                credentials: "same-origin",
                headers: {
                  "Content-Type": "application/json",
                  "X-WP-Nonce": "<?php echo wp_create_nonce('wp_rest'); ?>",
                },
                redirect: "follow",
                referrerPolicy: "no-referrer",
                body: JSON.stringify(data),
              }).then(response => response.json())
                .then(data => {
                  if( !data.success ) {
                    // Handle error
                  } else {
                    // Handle success if needed
                  }
              });
            },
            async grantOrgEditor( personUuid, orgUuid ) {
              let data = {
                "personUuid": personUuid,
                "orgUuid": orgUuid,
              };

              let results = await fetch(this.apiUrl + 'grant-org-editor', {
                method: "POST",
                mode: "cors",
                cache: "no-cache",
                credentials: "same-origin",
                headers: {
                  "Content-Type": "application/json",
                  "X-WP-Nonce": "<?php echo wp_create_nonce('wp_rest'); ?>",
                },
                redirect: "follow",
                referrerPolicy: "no-referrer",
                body: JSON.stringify(data),
              }).then(response => response.json())
                .then(data => {
                  if( !data.success ) {
                    // Handle error
                  } else {
                    // Handle success if needed
                  }
              });
            },
            async searchGroups( searchTerm, showLoading = true ) {
              if(showLoading) {
                this.isLoading = true;
              }

              let data = {
                "searchTerm": searchTerm,
                "lang": this.lang,
              };

              let results = await fetch(this.apiUrl + 'search-groups', {
                method: "POST",
                mode: "cors",
                cache: "no-cache",
                credentials: "same-origin",
                headers: {
                  "Content-Type": "application/json",
                  "X-WP-Nonce": "<?php echo wp_create_nonce('wp_rest'); ?>",
                },
                redirect: "follow",
                referrerPolicy: "no-referrer",
                body: JSON.stringify(data),
              }).then(response => response.json())
                .then(data => {
                  if( !data.success ) {
                    // Handle error
                  } else {
                    if(showLoading) {
                      this.isLoading = false;
                    }
                    this.results = data.data;
                    if( !this.firstSearchSubmitted ) {
                      this.firstSearchSubmitted = true;
                    }
                  }
                });

            },
            async createRelationship( fromUuid, toUuid, relationshipType, userRoleInRelationship ) {
              this.isLoading = true;

              let data = {
                "fromUuid"              : fromUuid,
                "toUuid"                : toUuid,
                "relationshipType"      : relationshipType,
                "userRoleInRelationship": userRoleInRelationship,
              };

              let endPointUrl = this.apiUrl + 'create-relationship';

              if (data.relationshipType === 'organization_parent') {
                data.fromUuid = '<?php echo $org_id; ?>';
                endPointUrl = this.apiUrl + 'organization-parent';
              }

              let results = await fetch(endPointUrl, {
                method: "POST",
                mode: "cors",
                cache: "no-cache",
                credentials: "same-origin",
                headers: {
                  "Content-Type": "application/json",
                  "X-WP-Nonce": "<?php echo wp_create_nonce('wp_rest'); ?>",
                },
                redirect: "follow",
                referrerPolicy: "no-referrer",
                body: JSON.stringify(data),
              }).then(response => response.json())
                .then(data => {
                  this.isLoading = false;
                  if( !data.success ) {
                    // Handle error
                  } else {
                    if( data.success ) {
                      <?php if ($relationshipMode === 'organization_parent') : ?>
                        this.results = [];
                        this.isLoading = false;
                        this.firstSearchSubmitted = false;

                        let responseText = '<?php esc_attr_e('Organization connected successfully', 'wicket'); ?>';

                        <?php if ($responseMessage) : ?>
                          let newOrgEvent = new CustomEvent("new-org-connected", {
                            detail: {
                              message: responseText
                            }
                          });
                          window.dispatchEvent(newOrgEvent);
                        <?php else: ?>
                          alert(responseText);
                        <?php endif; ?>

                        return;
                      <?php endif; ?>

                      if(typeof data.data[0] === 'undefined') {
                        // A single connection array was returned
                        this.addConnection( data.data );
                      } else {
                        // An array of connections was returned
                        // and we'll use the first one for org info
                        this.addConnection( data.data[0] );
                      }
                    }
                  }
                });

            },

            async terminateRelationship( connectionId = null ) {
              // If we're using the remove confirmation feature
              if(this.removeConfirmationIsEnabled) {
                // If we're already showing the remove confirmation,
                // proceed as usual as they've confirmed the action
                if(this.showingRemoveConfirmation) {
                  // Just close the modal
                  this.showingRemoveConfirmation = false;
                } else {
                  this.connectionIdSelectedForRemoval = connectionId;
                  this.showingRemoveConfirmation = true;
                  return; // Return to skip any logic executing  yet
                }
              }

              // If no connection ID was provided, use the temporary one
              // stored for the removal confirmation modal
              if(!connectionId) {
                if(this.connectionIdSelectedForRemoval) {
                  connectionId = this.connectionIdSelectedForRemoval;
                  this.connectionIdSelectedForRemoval = ''; // Clear it for later use
                }
              }

              this.isLoading = true;

              let data = {
                "connectionId": connectionId,
                "relationshipType": this.relationshipTypeUponOrgCreation, // Restricts the removal to the defined relationship type
              };

              let results = await fetch(this.apiUrl + 'terminate-relationship', {
                method: "POST",
                mode: "cors",
                cache: "no-cache",
                credentials: "same-origin",
                headers: {
                  "Content-Type": "application/json",
                  "X-WP-Nonce": "<?php echo wp_create_nonce('wp_rest'); ?>",
                },
                redirect: "follow",
                referrerPolicy: "no-referrer",
                body: JSON.stringify(data),
              }).then(response => response.json())
                .then(data => {
                  this.isLoading = false;
                  if( !data.success ) {
                    // Handle error
                    let errorMessage = data.data.toLowerCase();
                    console.log(errorMessage);
                    if(errorMessage.indexOf('relationships do not match') !== -1) {
                      // The user tried to remove a relationship of a different type than was specified
                      // for this ORGSS component. If this is a common occurrence, should display an error.
                    }
                    if(errorMessage.indexOf('wrong setting the end date of the connection') !== -1) {
                      // Display an error; they were likely trying to set the end date to the same day it was created
                      this.setSearchMessage("<?php _e('There was an error removing that relationship. Note that you can\'t remove relationships on the same day you create them.', 'wicket'); ?>");
                    }
                  } else {
                    if( data.success ) {
                      this.removeConnection( connectionId );
                    }

                  }
                });

            },
            async createOrganization( orgName, orgType, event = null ) {
              this.isLoading = true;

              if( orgName.length <= 0 || orgType.length <= 0 ) {
                console.log('createOrganization name or type not provided');
                return false;
              }

              let data = {
                "orgName": orgName,
                "orgType": orgType,
                "noDuplicate": true,
              };

              let results = await fetch(this.apiUrl + 'create-org', {
                method: "POST",
                mode: "cors",
                cache: "no-cache",
                credentials: "same-origin",
                headers: {
                  "Content-Type": "application/json",
                  "X-WP-Nonce": "<?php echo wp_create_nonce('wp_rest'); ?>",
                },
                redirect: "follow",
                referrerPolicy: "no-referrer",
                body: JSON.stringify(data),
              }).then(response => response.json())
                .then(data => {
                  if( !data.success ) {
                    // Handle error
                    this.isLoading = false;
                    if(data.data.includes('Duplicate org is not allowed')) {
                      this.displayDuplicateOrgWarning = true;
                      return;
                    }
                    this.setSearchMessage(sprintf(__('There was an error creating the %s, please try again.', 'wicket'), $orgTermSingularLower));
                  } else {
                    if( data.success ) {
                      let newOrgUuid = data.data.data.id;
                      this.selectOrgAndCreateRelationship( newOrgUuid, event );

                      // Check a user-defined checkbox (if provided) so the form an respond to a new org
                      // being created or not
                      if( "<?php echo $checkboxIdNewOrg; ?>" !== "" ) {
                        document.getElementById("<?php echo $checkboxIdNewOrg; ?>").checked = true;
                      }
                    }

                  }
                });

            },
            addConnection( payload ) {
              this.currentConnections.push(payload);
            },
            removeConnection( connectionId ) {
              let connections = this.currentConnections;

              connections.forEach( (val, i, array) => {
                if( val.connection_id == connectionId ) {
                  array.splice( i, 1 );
                }
              } );

              this.currentConnections = connections;
            },
            isOrgAlreadyAConnection( uuid ) {
              let connections = this.currentConnections;
              let isOrgAlreadyAConnection = false;

              connections.forEach( (val, i, array) => {
                let org_id = val.org_id;
                //console.log(`Checking if already a connection: incoming ${uuid} vs existing ${org_id}`);
                if( org_id == uuid ) {
                  //console.log('Is already a connection');
                  isOrgAlreadyAConnection = true;
                }
              } );

              return isOrgAlreadyAConnection;
            },
            getOrgFromConnectionsByUuid( uuid ) {
              let found = {};
              this.currentConnections.forEach( (connection, index) => {
                if( connection.org_id == uuid ) {
                  found = connection;
                  return;
                }
              });
              return found;
            },
            async doWpAction( actionType ) {
              let data = {
                "action_name": actionType,
                "action_data": {
                  "uri": '<?php echo $_SERVER['REQUEST_URI']; ?>',
                  "org_uuid": this.selectedOrgUuid,
                },
              };

              let results = await fetch(this.apiUrl + 'wicket-component-do-action', {
                method: "POST",
                mode: "cors",
                cache: "no-cache",
                credentials: "same-origin",
                headers: {
                  "Content-Type": "application/json",
                  "X-WP-Nonce": "<?php echo wp_create_nonce('wp_rest'); ?>",
                },
                redirect: "follow",
                referrerPolicy: "no-referrer",
                body: JSON.stringify(data),
              }).then(response => response.json())
                .then(data => {
                  if( !data.success ) {
                    // Handle error
                  } else {
                    // ...
                  }
                });

               return; 
            }
        }))
    })
</script>
