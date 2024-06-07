<?php
$defaults  = array(
	'classes'               => [],
);
$args                            = wp_parse_args( $args, $defaults );
$classes                         = $args['classes'];
$searchMode                      = 'org'; // Options: org, groups, ...
$relationshipTypeUponOrgCreation = 'employee';
$relationshipMode                = 'person_to_organization';

// TODO: Make component configurable to focus on different 'types' of orgs, and also groups

$current_person_uuid = wicket_current_person_uuid();

// Check if this person already has a connection to this org, and if so delete it first
$current_connections = wicket_get_person_connections();
$person_to_org_connections = [];
foreach( $current_connections['data'] as $connection ) {
  $connection_id = $connection['id'];
  if( isset( $connection['attributes']['connection_type'] ) ) {
    $org_id = $connection['relationships']['organization']['data']['id'];
    $org_info = wicket_get_organization_basic_info( $org_id );

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
?>

<div class="container component-org-search-select " x-data="orgss" x-init="init">

  <div x-transition x-cloak 
    class="flex justify-center items-center w-full text-dark-100 text-heading-3xl py-10 absolute h-full left-0 right-0 mx-auto bg-white bg-opacity-70"
    x-bind:class="isLoading ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none' "
    >
    <i class="fa-solid fa-arrows-rotate fa-spin"></i>
  </div>

  <?php // TODO: add conditional CTA displaying the currently selected UUID ?>

  <form class="orgss-search-form flex flex-col bg-dark-100 bg-opacity-5 rounded-100 p-3" x-on:submit="handleSubmit">
    <div x-show="currentConnections.length > 0" x-cloak>
      <h2 class="font-bold text-heading-md my-3">Your Current Organization(s)</h2>

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
                'type'     => 'button',
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
    
    <div class="font-bold text-heading-sm mb-2" x-text=" currentConnections.length > 0 ? 'Looking for a different organization?' : 'Search for your organization' "></div>

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
            <div class="font-bold" x-text="result.org_name"></div>
            <?php get_component( 'button', [ 
              'variant'  => 'primary',
              'reversed' => true,
              'label'    => __( 'Select', 'wicket' ),
              'type'     => 'button',
              'classes'  => [ '' ], 
              'atts'     => [ 'x-on:click="selectOrgAndCreateRelationship($data.result.org_id)"',  ]
            ] ); ?>
          </div>
        </template>

      </div>
    </div>
  </form>

</div>

<?php /* Broken-out Alpine data for tidyness */ ?>
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('orgss', () => ({
            isLoading: false,
            firstSearchSubmitted: false,
            searchType: '<?php echo $searchMode; ?>',
            relationshipTypeUponOrgCreation: '<?php echo $relationshipTypeUponOrgCreation; ?>',
            relationshipMode: '<?php echo $relationshipMode; ?>',
            selectedOrgUuid: '',
            searchBox: '',
            results: [],
            apiUrl: "<?php echo get_rest_url( null, 'wicket-base/v1/' ); ?>",
            currentConnections: <?php echo json_encode( $person_to_org_connections ); ?>,
            currentPersonUuid: "<?php echo $current_person_uuid; ?>",


            init() {
              //console.log(this.currentConnections);
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
              this.createRelationship( this.currentPersonUuid, orgUuid, this.relationshipMode, this.relationshipTypeUponOrgCreation );
              this.selectOrg( orgUuid );
            },
            searchGroups( searchTerm ) {
              console.log(`Searching groups by term ${searchTerm}`);
              //
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
            createOrg( name, address ) {
                //
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