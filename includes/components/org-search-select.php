<?php


/**
 * COMPONENT NOTES (Newest to oldest)
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


$defaults  = array(
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
  'disable_create_org_ui'                         => false,
  'disable_selecting_orgs_with_active_membership' => false,
  'grant_roster_man_on_purchase'                  => false,
  'grant_org_editor_on_select'                      => false,
);
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
$disable_create_org_ui                         = $args['disable_create_org_ui'];
$disable_selecting_orgs_with_active_membership = $args['disable_selecting_orgs_with_active_membership'];
$grant_roster_man_on_purchase                  = $args['grant_roster_man_on_purchase'];
$grant_org_editor_on_select                      = $args['grant_org_editor_on_select'];

if( empty( $orgTermSingular ) && $searchMode == 'org' ) { 
  $orgTermSingular = 'Organization'; 
}
if( empty( $orgTermSingular ) && $searchMode == 'groups' ) { 
  $orgTermSingular = 'Group'; 
}
$orgTermSingularCap              = ucfirst(strtolower( $orgTermSingular ));
$orgTermSingularLower            = strtolower( $orgTermSingular );
if( empty( $orgTermPlural  ) && $searchMode == 'org' ) { 
  $orgTermPlural  = 'Organizations'; 
}
if( empty( $orgTermPlural  ) && $searchMode == 'groups' ) { 
  $orgTermPlural  = 'Groups'; 
}
$orgTermPluralCap              = ucfirst(strtolower( $orgTermPlural ));
$orgTermPluralLower            = strtolower( $orgTermPlural );

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
  // TODO: change 'active' to 'connection_active' and retrive if the org has an active membership tier/status
      // in wicket. 
  // $current_memberships = wicket_get_current_person_memberships();
  // wicket_get_org_memberships
  

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
        'connection_id'   => $connection['id'],
        'connection_type' => $connection['attributes']['connection_type'],
        'starts_at'       => $connection['attributes']['starts_at'],
        'ends_at'         => $connection['attributes']['ends_at'],
        'tags'            => $connection['attributes']['tags'],
        'active'          => $has_active_membership,
        'org_id'          => $org_id,
        'org_name'        => $org_info['org_name'],
        'org_description' => $org_info['org_description'],
        'org_type_pretty' => $org_info['org_type_pretty'],
        'org_type'        => $org_info['org_type'],
        'org_status'      => $org_info['org_status'],
        'org_parent_id'   => $org_info['org_parent_id'],
        'org_parent_name' => $org_info['org_parent_name'],
        'person_id'       => $connection['relationships']['person']['data']['id'],
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
    background: #efefef;
    color: #a3a3a3;
    border-color: #efefef;
    pointer-events: none;
  }
</style>

<div class="container component-org-search-select relative <?php implode(' ', $classes); ?>" x-data="orgss_<?php echo $key; ?>" x-init="init">

  <?php // Debug log of the selection custom event when fired ?>
  <pre x-on:orgss-selection-made.window="console.log($event.detail)"></pre>

  <?php // Loading overlay ?>
  <div x-transition x-cloak 
    class="flex justify-center items-center w-full text-dark-100 text-heading-3xl py-10 absolute h-full left-0 right-0 mx-auto bg-white bg-opacity-70"
    x-bind:class="isLoading ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none' "
    >
    <i class="fa-solid fa-arrows-rotate fa-spin"></i>
  </div>

  <div class="orgss-search-form flex flex-col bg-dark-100 bg-opacity-5 rounded-100 p-3">
    <div x-show="currentConnections.length > 0" x-cloak>
      <h2 class="font-bold text-body-lg my-3">Your current <?php echo $orgTermPluralCap; ?></h2>

      <template x-for="(connection, index) in currentConnections" :key="connection.connection_id" x-transition>
        <div 
          x-show="connection.connection_type == relationshipMode && 
                 ( connection.org_type.toLowerCase() === searchOrgType.toLowerCase() || searchOrgType === '' )" 
          class="rounded-100 flex justify-between bg-white p-4 mb-3"
          x-bind:class="connection.org_id == selectedOrgUuid ? 'border-success-040 border-opacity-100 border-4' : 'border border-dark-100 border-opacity-5' "
        >
        
        <div class="current-org-listing-left">
            <div class="font-bold text-body-xs" x-text="connection.org_type_pretty"></div>
            <div class="flex mb-2 items-center">
              <div x-text="connection.org_name" class="font-bold text-body-sm mr-5"></div>
              <div>
                <template x-if="connection.active">
                  <div>
                    <i class="fa-solid fa-circle" style="color:#08d608;"></i> <span class="text-body-xs">Active Membership</span>
                  </div>
                </template>
                <template x-if="! connection.active">
                  <div>
                    <i class="fa-solid fa-circle" style="color:#A1A1A1;"></i> <span class="text-body-xs">Inactive Membership</span>
                  </div>
                </template>
              </div>
            </div>
            <div x-show="connection.org_parent_name.length > 0" class="mb-3" x-text="connection.org_parent_name"></div>
            <div x-text="connection.org_description"></div>
          </div>
          <div class="current-org-listing-right flex items-center">
            <div>
              <?php get_component( 'button', [ 
                'variant'  => 'primary',
                'label'    => __( 'Select ' . $orgTermSingularCap, 'wicket' ),
                'type'     => 'primary',
                'classes'  => [ '' ],
                'atts'     => [ 
                  'x-on:click.prevent="selectOrg($data.connection.org_id)"',
                  'x-bind:class="connection.active && disableSelectingOrgsWithActiveMembership ? \'orgss_disabled_button\' : \'\' "'
                ]
              ] ); ?>
              <?php get_component( 'button', [ 
                'variant'  => 'primary',
                'label'    => __( 'Remove', 'wicket' ),
                'reversed' => true,
                'suffix_icon' => 'fa-regular fa-trash',
                'type'     => 'button',
                'classes'  => [ '' ],
                'atts'     => [ 'x-on:click.prevent="terminateRelationship($data.connection.connection_id)"',  ]
              ] ); ?>
            </div>
          </div>
        </div>
      </template>
    </div>
    
    <div class="font-bold text-body-md mb-2" x-text=" currentConnections.length > 0 ? 'Looking for a different <?php echo $orgTermSingularLower; ?>?' : 'Search for your <?php echo $orgTermSingularLower; ?>' "></div>

    <div class="flex">
      <?php // Can add `@keyup="if($el.value.length > 3){ handleSearch(); } "` to get autocomplete, but it's not quite fast enough ?>
      <input x-model="searchBox" @keyup.enter.prevent="handleSearch()" type="text" class="orgss-search-box w-full mr-2" placeholder="Search by <?php echo $orgTermSingularLower; ?> name" />
      <?php get_component( 'button', [ 
        'variant'  => 'primary',
        'label'    => __( 'Search', 'wicket' ),
        'type'     => 'button',
        'atts'  => [ 'x-on:click.prevent="handleSearch()"' ],
      ] ); ?>
     </div>
     <div class="mt-4 mb-1" x-show="firstSearchSubmitted || isLoading" x-cloak>Matching <?php echo $orgTermPluralLower; ?><?php // (Selected org: <span x-text="selectedOrgUuid"></span>)?></div>
     <div class="orgss-results">
      <div class="flex flex-col bg-white px-4 max-h-80 overflow-y-scroll">
        <div x-show="results.length == 0 && searchBox.length > 0 && firstSearchSubmitted && !isLoading" x-transition x-cloak class="flex justify-center items-center w-full text-dark-100 text-body-md py-4">
          Sorry, no <?php echo $orgTermPluralLower; ?> match your search. Please try again.
        </div>

        <template x-for="(result, uuid) in results" x-cloak>
          <div class="px-1 py-3 border-b border-dark-100 border-opacity-5 flex justify-between items-center">
            <div class="font-bold" x-text="result.name"></div>
            <?php get_component( 'button', [ 
              'variant'  => 'primary',
              'reversed' => true,
              'label'    => __( 'Select', 'wicket' ),
              'type'     => 'button',
              'classes'  => [ '' ], 
              'atts'     => [ 'x-on:click.prevent="selectOrgAndCreateRelationship($data.result.id)"',  ]
            ] ); ?>
          </div>
        </template>

      </div>
    </div>
  </div>

  <div x-show="firstSearchSubmitted && !disableCreateOrgUi" x-cloak class="orgss-create-org-form mt-4 flex flex-col bg-dark-100 bg-opacity-5 rounded-100 p-3">
    <div class="font-bold text-body-md mb-2">Can't find your <?php echo $orgTermSingularLower; ?>?</div>
    <div class="flex">
      <div x-bind:class="newOrgTypeOverride.length <= 0 ? 'w-5/12' : 'w-10/12'" class="flex flex-col mr-2">
        <label>Name of the <?php echo $orgTermSingularCap; ?>*</label>
        <input x-model="newOrgNameBox" @keyup.enter.prevent="handleOrgCreate()" type="text" name="company-name" class="w-full" />
      </div>
      <div x-show="newOrgTypeOverride.length <= 0" class="flex flex-col w-5/12 mr-2">
      <label>Type of <?php echo $orgTermSingularCap; ?>*</label>
        <select x-model="newOrgTypeSelect" class="w-full">
          <template x-for="(orgType, index) in availableOrgTypes.data">
            <option x-bind:value="orgType.attributes.slug" x-text="orgType['attributes']['name_' + lang]" 
              >Org type</option>
          </template>
        </select>
      </div>
      <div class="flex flex-col w-2/12 items-center justify-end">
        <?php get_component( 'button', [ 
            'variant'  => 'primary',
            'label'    => __( 'Add Details', 'wicket' ),
            'type'     => 'button',
            'classes'  => [ 'w-full', 'justify-center' ],
            'atts'  => [ 'x-on:click.prevent="handleOrgCreate()"' ],
          ] ); ?>
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
            firstSearchSubmitted: false,
            searchType: '<?php echo $searchMode; ?>',
            relationshipTypeUponOrgCreation: '<?php echo $relationshipTypeUponOrgCreation; ?>',
            relationshipMode: '<?php echo $relationshipMode; ?>',
            newOrgTypeOverride: '<?php echo $newOrgTypeOverride; ?>',
            searchOrgType: '<?php echo $searchOrgType; ?>',
            availableOrgTypes: <?php echo json_encode( $available_org_types ); ?>,
            disableCreateOrgUi: <?php echo $disable_create_org_ui ? 'true' : 'false'; ?>,
            disableSelectingOrgsWithActiveMembership: <?php echo $disable_selecting_orgs_with_active_membership ? 'true' : 'false'; ?>,
            selectedOrgUuid: '',
            searchBox: '',
            newOrgNameBox: '',
            newOrgTypeSelect: '',
            results: [],
            apiUrl: "<?php echo get_rest_url( null, 'wicket-base/v1/' ); ?>",
            currentConnections: <?php echo json_encode( $person_to_org_connections ); ?>,
            currentPersonUuid: "<?php echo $current_person_uuid; ?>",
            grantRosterManOnPurchase: <?php echo $grant_roster_man_on_purchase ? 'true' : 'false'; ?>,
            grantOrgEditorOnSelect: <?php echo $grant_org_editor_on_select  ? 'true' : 'false'; ?>,


            init() {
              //console.log(this.currentConnections);

              // Set an initial value for the dynamic select 
              this.newOrgTypeSelect = this.availableOrgTypes.data[0].attributes.slug;
            },
            handleSearch(e = null) {
              if(e) {
                e.preventDefault();
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

                this.createOrganization( newOrgName, newOrgType );
              }

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
            selectOrg( orgUuid ) {
              // Update state 
              this.selectedOrgUuid = orgUuid;
              document.querySelector('input[name="<?php echo $selectedUuidHiddenFieldName; ?>"]').value = orgUuid;

              // selectOrg() is the last function call used in the process, whether selecting an existing
              // org or creating a new one and then selecting it, so from here we'll dispatch the selection info
              let orgInfo = this.getOrgFromConnectionsByUuid( orgUuid );

              event = new CustomEvent("orgss-selection-made", {
                detail: {
                  uuid: orgUuid,
                  searchType: this.searchType,
                  orgDetails: orgInfo,
                }
              });

              window.dispatchEvent(event);

              if(this.grantRosterManOnPurchase) {
                this.flagForRosterManagementAccess(orgUuid);
              }
              if(this.grantOrgEditorOnSelect) {
                this.grantOrgEditor( this.currentPersonUuid, orgUuid );
              }
            },
            selectOrgAndCreateRelationship( orgUuid ) {
              // TODO: Handle when a Group is selected instead of an org

              this.createRelationship( this.currentPersonUuid, orgUuid, this.relationshipMode, this.relationshipTypeUponOrgCreation );
              this.selectOrg( orgUuid );
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

              let results = await fetch(this.apiUrl + 'create-relationship', {
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
                      this.addConnection( data.data );
                    }

                  }
                });
              
            },

            async terminateRelationship( connectionId ) {
              this.isLoading = true;

              let data = {
                "connectionId": connectionId,
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
                  } else {
                    if( data.success ) {
                      this.removeConnection( connectionId );
                    }

                  }
                });
              
            },
            async createOrganization( orgName, orgType ) {
              this.isLoading = true;

              if( orgName.length <= 0 || orgType.length <= 0 ) {
                console.log('createOrganization name or type not provided');
                return false;
              }

              let data = {
                "orgName": orgName,
                "orgType": orgType,
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
                  } else {
                    if( data.success ) {
                      let newOrgUuid = data.data.data.id;
                      this.selectOrgAndCreateRelationship( newOrgUuid );

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
            getOrgFromConnectionsByUuid( uuid ) {
              let found = {};
              this.currentConnections.forEach( (connection, index) => {
                if( connection.org_id == uuid ) {
                  found = connection;
                  return;
                }
              });
              return found;
            }
        }))
    })
</script>