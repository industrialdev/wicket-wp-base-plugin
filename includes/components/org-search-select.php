<?php
$defaults  = array(
	'classes'               => [],
);
$args                  = wp_parse_args( $args, $defaults );
$classes               = $args['classes'];
$searchMode            = 'org'; // Options: org, groups, ...

// TODO: Check current user org relationship to see if they already have a selected org,
// and if so populate selectedOrgUuid in Apline

?>

<div class="component-org-search-select " x-data="orgss">

  <?php // TODO: add conditional CTA displaying the currently selected UUID ?>

  <form class="orgss-search-form" x-on:submit="handleSubmit">
    <input type="text" class="orgss-search-box" x-model="searchBox" />
  </form>

</div>

<?php /* Broken-out Alpine data for tidyness */ ?>
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('orgss', () => ({
            searchType: <?php echo $searchMode; ?>,
            selectedOrgUuid: '',
            searchBox: '',
            results: [],

 
            handleSubmit() {
              if( searchType == 'org' ) {
                searchOrgs( searchBox );
              } else if( searchType == 'groups' ) {
                searchGroups( searchBox );
              }
            },
            searchOrgs( searchTerm ) {
              console.log(`Searching orgs by term ${searchTerm}`);
              //
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