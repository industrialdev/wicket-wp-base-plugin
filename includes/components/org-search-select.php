<?php


/**
 * COMPONENT NOTES (Newest to oldest)
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
 *     at the top of the component gets updated after the user is successfully added to a group.
 * 
 * General TODOs:
 *  [] Allow component to be used with (or focus on) different 'types' of orgs via a component param
 *  [] Update the 'active connection' logic to check for an active membership status rather than an 
 *     active connection
 *  [] Determine how to cleanly trigger a "next step" in various contexts while providing the selected UUID
 *     and any other needed information. For example, if it's used on a custom template, we may want to emit
 *     a custom JS event or call a specified function name after the user has completed the needed operations,
 *     whereas in a Gravity Form, we'll need to talk to our wrapper so the wrapper can make the GF-specific calls
 *     to indicate a completed form field. In a Block we may give the user the option to provide a URL that they can
 *     redirect to when they're done with the info passed in as parameters. TLDR: yea I think we'll just emit a custom
 *     JS event and the custom template or block/GF element wrapper can listen for that and do what they need to do.
 *  [] Proper localization support.
 */



$defaults  = array(
	'classes'               => [],
);
$args                            = wp_parse_args( $args, $defaults );
$classes                         = $args['classes'];
$searchMode                      = 'org'; // Options: org, groups, ...
$relationshipTypeUponOrgCreation = 'employee';
$relationshipMode                = 'person_to_organization';
$newOrgTypeOverride              = '';

// TODO: Make component configurable to focus on different 'types' of orgs, and also groups

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
  
      // TODO: change 'active' to 'connection_active' and retrive if the org has an active membership tier/status
      // in wicket. 
  
      $person_to_org_connections[] = [
        'connection_id'   => $connection['id'],
        'connection_type' => $connection['attributes']['connection_type'],
        'starts_at'       => $connection['attributes']['starts_at'],
        'ends_at'         => $connection['attributes']['ends_at'],
        'tags'            => $connection['attributes']['tags'],
        'active'          => $connection['attributes']['active'],
        'org_id'          => $connection['relationships']['organization']['data']['id'],
        'org_name'        => $org_info['org_name'],
        'org_description' => $org_info['org_description'],
        'org_type'        => $org_info['org_type_pretty'],
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

<div class="container component-org-search-select relative <?php implode(' ', $classes); ?>" x-data="orgss" x-init="init">

  <?php // Loading overlay ?>
  <div x-transition x-cloak 
    class="flex justify-center items-center w-full text-dark-100 text-heading-3xl py-10 absolute h-full left-0 right-0 mx-auto bg-white bg-opacity-70"
    x-bind:class="isLoading ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none' "
    >
    <i class="fa-solid fa-arrows-rotate fa-spin"></i>
  </div>

  <form class="orgss-search-form flex flex-col bg-dark-100 bg-opacity-5 rounded-100 p-3" x-on:submit="handleSubmit">
    <div x-show="currentConnections.length > 0" x-cloak>
      <h2 class="font-bold text-heading-md my-3"
        x-text=" searchType == 'groups' ? 'Your Current Group(s)' : 'Your Current Organization(s)' "></h2>

      <template x-for="(connection, index) in currentConnections" :key="connection.connection_id" x-transition>
        <div x-show="connection.connection_type == relationshipMode" class="rounded-100 flex justify-between bg-white border border-dark-100 border-opacity-5 p-4 mb-3">
          <div class="current-org-listing-left">
            <div class="font-bold text-body-md" x-text="connection.org_type"></div>
            <div class="flex mb-2 items-center">
              <div x-text="connection.org_name" class="font-bold text-heading-sm mr-5"></div>
              <div>
                <template x-if="connection.active">
                  <div>
                    <i class="fa-solid fa-circle" style="color:#08d608;"></i> <span>Active Membership</span>
                  </div>
                </template>
                <template x-if="! connection.active">
                  <div>
                    <i class="fa-solid fa-circle" style="color:#A1A1A1;"></i> <span>Inactive Membership</span>
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
                'label'    => __( 'Select Organization', 'wicket' ),
                'type'     => 'primary',
                'classes'  => [ '' ],
                'atts'     => [ 'x-on:click="selectOrg($data.connection.org_id)"',  ]
              ] ); ?>
              <?php get_component( 'button', [ 
                'variant'  => 'primary',
                'label'    => __( 'Remove', 'wicket' ),
                'reversed' => true,
                'suffix_icon' => 'fa-regular fa-trash',
                'type'     => 'button',
                'classes'  => [ '' ],
                'atts'     => [ 'x-on:click="terminateRelationship($data.connection.connection_id)"',  ]
              ] ); ?>
            </div>
          </div>
        </div>
      </template>
    </div>
    
    <?php if($searchMode == 'groups'):?>
      <div class="font-bold text-heading-sm mb-2" x-text=" currentConnections.length > 0 ? 'Looking for a different group?' : 'Search for your group' "></div>
    <?php else: ?>
      <div class="font-bold text-heading-sm mb-2" x-text=" currentConnections.length > 0 ? 'Looking for a different organization?' : 'Search for your organization' "></div>
    <?php endif; ?>

    <div class="flex">
      <input type="text" class="orgss-search-box w-full mr-2" placeholder="Search by organization name" x-model="searchBox" />
      <?php get_component( 'button', [ 
        'variant'  => 'primary',
        'label'    => __( 'Search', 'wicket' ),
        'type'     => 'submit',
        'classes'  => [ '' ],
      ] ); ?>
     </div>
     <div class="mt-4 mb-1" x-show="firstSearchSubmitted || isLoading" x-cloak>Matching organizations (Selected org: <span x-text="selectedOrgUuid"></span>)</div>
     <div class="orgss-results">
      <div class="flex flex-col bg-white px-4">
        <div x-show="results.length == 0 && searchBox.length > 0 && firstSearchSubmitted && !isLoading" x-transition x-cloak class="flex justify-center items-center w-full text-dark-100 text-body-xl py-4">
          Sorry, no organizations match your search. Please try again.
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
              'atts'     => [ 'x-on:click="selectOrgAndCreateRelationship($data.result.id)"',  ]
            ] ); ?>
          </div>
        </template>

      </div>
    </div>
  </form>

  <form x-show="firstSearchSubmitted" x-cloak class="orgss-create-org-form mt-4 flex flex-col bg-dark-100 bg-opacity-5 rounded-100 p-3" x-on:submit="handleOrgCreate">
    <div class="font-bold text-heading-sm mb-2"
    x-text=" searchType=='groups' ? 'Can\'t find your group?' : 'Can\'t find your company / organization?' "></div>
    <div class="flex">
      <div x-bind:class="newOrgTypeOverride.length <= 0 ? 'w-5/12' : 'w-10/12'" class="flex flex-col mr-2">
        <label
          x-text=" searchType=='groups' ? 'Name of the Group*' : 'Name of the Organization*' "></label>
        <input x-model="newOrgNameBox" type="text" name="company-name" class="w-full" />
      </div>
      <div x-show="newOrgTypeOverride.length <= 0" class="flex flex-col w-5/12 mr-2">
      <label
          x-text=" searchType=='groups' ? 'Type of Group*' : 'Type of Organization*' "></label>
        <select x-model="newOrgTypeSelect" x-init="console.log()" class="w-full">
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
            'type'     => 'submit',
            'classes'  => [ 'w-full', 'justify-center' ],
          ] ); ?>
        </div>
      </div>
  </form>

</div>

<?php /* Broken-out Alpine data for tidyness */ ?>
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('orgss', () => ({
            lang: '<?php echo $lang; ?>',
            isLoading: false,
            firstSearchSubmitted: false,
            searchType: '<?php echo $searchMode; ?>',
            relationshipTypeUponOrgCreation: '<?php echo $relationshipTypeUponOrgCreation; ?>',
            relationshipMode: '<?php echo $relationshipMode; ?>',
            newOrgTypeOverride: '<?php echo $newOrgTypeOverride; ?>',
            availableOrgTypes: <?php echo json_encode( $available_org_types ); ?>,
            selectedOrgUuid: '',
            searchBox: '',
            newOrgNameBox: '',
            newOrgTypeSelect: '',
            results: [],
            apiUrl: "<?php echo get_rest_url( null, 'wicket-base/v1/' ); ?>",
            currentConnections: <?php echo json_encode( $person_to_org_connections ); ?>,
            currentPersonUuid: "<?php echo $current_person_uuid; ?>",


            init() {
              //console.log(this.currentConnections);

              // Set an initial value for the dynamic select 
              this.newOrgTypeSelect = this.availableOrgTypes.data[0].attributes.slug;
            },
            handleSubmit(e) {
              e.preventDefault();

              this.results = [];
             
              if( this.searchType == 'org' ) {
                this.searchOrgs( this.searchBox );
              } else if( this.searchType == 'groups' ) {
                this.searchGroups( this.searchBox );
              }
            },
            handleOrgCreate(e) {
              e.preventDefault();

              if( this.searchType == 'groups' ) {
                // Handle group creation
              } else {
                // Creating an org
                let newOrgName = this.newOrgNameBox;
                console.log(`Creating new org ${newOrgName}`);

                let newOrgType = '';
                if( this.newOrgTypeOverride.length > 0 ) {
                  newOrgType = this.newOrgTypeOverride;
                } else {
                  newOrgType = this.newOrgTypeSelect;
                }

                this.createOrganization( newOrgName, newOrgType );
              }

            },
            async searchOrgs( searchTerm ) {
              this.isLoading = true;

              let data = {
                "searchTerm": searchTerm,
              };

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
                    this.isLoading = false;
                    this.results = data.data;
                    if( !this.firstSearchSubmitted ) {
                      this.firstSearchSubmitted = true;
                    }
                  }
                });
              
            },
            selectOrg( orgUuid ) {
              this.selectedOrgUuid = orgUuid;
            },
            selectOrgAndCreateRelationship( orgUuid ) {
              // TODO: Handle when a Group is selected instead of an org

              this.createRelationship( this.currentPersonUuid, orgUuid, this.relationshipMode, this.relationshipTypeUponOrgCreation );
              this.selectOrg( orgUuid );
            },
            async searchGroups( searchTerm ) {
              this.isLoading = true;

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
                    this.isLoading = false;
                    console.log(data.data);
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

              console.log(data);

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
        }))
    })
</script>