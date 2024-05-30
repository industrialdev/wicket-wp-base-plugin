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

  <form class="orgss-search-form" x-on:submit="handleSubmit">
    <input type="text" class="orgss-search-box" placeholder="Search by organization name" x-model="searchBox" />
    <?php get_component( 'button', [ 
			'variant'  => 'primary',
			'label'    => __( 'Search', 'wicket' ),
			'type'     => 'submit',
			'classes'  => [ '' ],
		] ); ?>
  </form>

</div>

<?php /* Broken-out Alpine data for tidyness */ ?>
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('orgss', () => ({
            searchType: '<?php echo $searchMode; ?>',
            relationshipTypeUponOrgCreation: '<?php echo $relationshipTypeUponOrgCreation; ?>',
            selectedOrgUuid: '',
            searchBox: '',
            results: [],

 
            handleSubmit(e) {
              e.preventDefault();

              if( this.searchType == 'org' ) {
                this.searchOrgs( this.searchBox );
              } else if( this.searchType == 'groups' ) {
                this.searchGroups( this.searchBox );
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