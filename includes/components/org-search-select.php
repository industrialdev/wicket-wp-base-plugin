<?php
$defaults  = array(
	'classes'               => [],
);
$args                            = wp_parse_args( $args, $defaults );
$classes                         = $args['classes'];
$searchMode                      = 'org'; // Options: org, groups, ...
$relationshipTypeUponOrgCreation = 'employee';


// TODO: Check current user org relationship to see if they already have a selected org,
// and if so populate selectedOrgUuid in Apline

?>

<div class="container component-org-search-select " x-data="orgss">

  <?php // TODO: add conditional CTA displaying the currently selected UUID ?>

  <form class="orgss-search-form flex flex-col bg-dark-100 bg-opacity-5 rounded-100 p-3" x-on:submit="handleSubmit">
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

        <div x-show="isLoading" x-transition x-cloak class="flex justify-center items-center w-full text-dark-100 text-heading-3xl py-10">
          <i class="fa-solid fa-arrows-rotate fa-spin"></i>
        </div>

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
              'atts'     => [ 'x-on:click="selectOrg($data.result.org_id)"',  ]
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
            selectedOrgUuid: '',
            searchBox: '',
            results: [],
            apiUrl: "<?php echo get_rest_url( null, 'wicket-base/v1/' ); ?>",

 
            handleSubmit(e) {
              e.preventDefault();

              this.results = [];
              this.isLoading = true;
             
              if( this.searchType == 'org' ) {
                this.searchOrgs( this.searchBox );
              } else if( this.searchType == 'groups' ) {
                this.searchGroups( this.searchBox );
              }
            },
            async searchOrgs( searchTerm ) {
              //console.log(`Searching orgs by term ${searchTerm}`);
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
                    console.log(data.data);
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
            searchGroups( searchTerm ) {
              console.log(`Searching groups by term ${searchTerm}`);
              //
            },
            createOrgRelationship( userUuid, orgUuid ) {
                //
            },
            terminateOrgRelationship( userUuid, orgUuid ) {
                //
            },
            createOrg( name, address ) {
                //
            },
        }))
    })
</script>