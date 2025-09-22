<?php
$defaults  = [
  'classes'                                       => [],
  'search_mode'                                   => 'org', // Options: org, groups, ...
  'search_org_type'                               => '',
  'relationship_type_upon_org_creation'           => 'employee',
  'relationship_mode'                             => 'person_to_organization',
  'relationship_type_filter'                      => '',
  'enable_relationship_filtering'                 => false,
  'new_org_type_override'                         => '',
  'selected_uuid_hidden_field_name'               => 'orgss-selected-uuid',
  'checkbox_id_new_org'                           => '',
  'key'                                           => rand(1, PHP_INT_MAX),
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
  'grant_org_editor_on_purchase'                  => false, // Grants org_editor role for selected org on next payment_complete hook
  'hide_remove_buttons'                           => false,
  'hide_select_buttons'                           => false,
  'display_removal_alert_message'                 => false,
  'title'                                         => '',
  'response_message'                              => '', // class name of the container where the response message will be displayed
  'description'                                   => '', // Description to be set on the connection
  'job_title'                                     => '',
  'form_id'                                       => 0,
];
$args                                          = wp_parse_args($args, $defaults);
$classes                                       = $args['classes'];
$searchMode                                    = $args['search_mode'];
$searchOrgType                                 = $args['search_org_type'];
$relationshipTypeUponOrgCreation               = $args['relationship_type_upon_org_creation'];
$relationshipMode                              = $args['relationship_mode'];
$relationshipTypeFilter                        = $args['relationship_type_filter'];
$enable_relationship_filtering                 = $args['enable_relationship_filtering'];
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
$grant_org_editor_on_purchase                  = $args['grant_org_editor_on_purchase'];
$hide_remove_buttons                           = $args['hide_remove_buttons'];
$hide_select_buttons                           = $args['hide_select_buttons'];
$display_removal_alert_message                 = $args['display_removal_alert_message'];
$title                                         = $args['title'];
$responseMessage                               = $args['response_message'];
$description                                   = $args['description'];
$job_title                                     = $args['job_title'];
$formId                                        = $args['form_id'];

if (!empty($orgTermSingular)) {
  $orgTermSingular = __($orgTermSingular, 'wicket');
} else {
  if ($searchMode == 'org') {
    $orgTermSingular = __('Organization', 'wicket');
  }
  if ($searchMode == 'groups') {
    $orgTermSingular = __('Group', 'wicket');
  }
}

$orgTermSingularCap              = ucfirst(strtolower($orgTermSingular));
$orgTermSingularLower            = strtolower($orgTermSingular);

if (!empty($orgTermPlural)) {
  $orgTermPlural = __($orgTermPlural, 'wicket');
} else {
  if ($searchMode == 'org') {
    $orgTermPlural  = __('Organizations', 'wicket');
  }
  if ($searchMode == 'groups') {
    $orgTermPlural  = __('Groups', 'wicket');
  }
}

$orgTermPluralCap              = ucfirst(strtolower($orgTermPlural));
$orgTermPluralLower            = strtolower($orgTermPlural);
if (empty($noResultsFoundMessage)) {
  $noResultsFoundMessage = sprintf(__('Sorry, no %s match your search. Please try again.', 'wicket'), $orgTermPluralLower);
} else {
  $noResultsFoundMessage = __($noResultsFoundMessage, 'wicket');
}

$current_person_uuid = wicket_current_person_uuid();

// Get current lang
$lang = wicket_get_current_language();

// Get users current org/group relationships
$person_to_org_connections = [];
if ($searchMode == 'org') {
  $current_connections = wicket_get_person_connections();

  foreach ($current_connections['data'] as $connection) {
    $connection_id = $connection['id'];
    if (isset($connection['attributes']['connection_type'])) {
      $org_id = $connection['relationships']['organization']['data']['id'];

      $org_info = wicket_get_organization_basic_info($org_id, $lang);
      $org_memberships = wicket_get_org_memberships($org_id);

      $has_active_membership = false;
      if (!empty($org_memberships)) {
        foreach ($org_memberships as $membership) {
          if (isset($membership['membership'])) {
            if (isset($membership['membership']['attributes'])) {
              if (isset($membership['membership']['attributes']['active'])) {
                if ($membership['membership']['attributes']['active']) {
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
        'relationship_type' => $connection['attributes']['type'] ?? '',
        'starts_at'         => $connection['attributes']['starts_at'],
        'ends_at'           => $connection['attributes']['ends_at'],
        'tags'              => $connection['attributes']['tags'],
        'active_membership' => $has_active_membership,
        'active_connection' => $connection['attributes']['active'],
        'org_id'            => $org_id,
        'org_name'          => $org_info['org_name'],
        'org_description'   => $org_info['org_description'],
        'org_type_pretty'   => __($org_info['org_type_pretty'], 'wicket'),
        'org_type'          => $org_info['org_type'],
        'org_type_slug'     => $org_info['org_type_slug'],
        'org_type_name'     => __($org_info['org_type_name'], 'wicket'),
        'org_status'        => $org_info['org_status'],
        'org_parent_id'     => $org_info['org_parent_id'],
        'org_parent_name'   => $org_info['org_parent_name'],
        'person_id'         => $connection['relationships']['person']['data']['id'],
      ];
    }
  }
} elseif ($searchMode == 'groups') {
  // TODO: Get current MDP org memberships and save to $person_to_org_connections
}

// Get list of org types to make available to the user if an admin override is not provided
$available_org_types = wicket_get_resource_types('organizations');
?>

<style>
  .orgss_disabled_button {
    background: #efefef !important;
    color: #a3a3a3 !important;
    border-color: #efefef !important;
    pointer-events: none;
  }

  .orgss_disabled_button_hollow {
    background: rgba(0, 0, 0, 0) !important;
    color: #a3a3a3 !important;
    border-color: rgba(0, 0, 0, 0) !important;
    pointer-events: none;
  }

  .orgss_error {
    color: red;
    font-size: .8em;
    margin-top: 5px;
  }
</style>

<div class="container component-org-search-select relative form <?php echo implode(' ', $classes); ?>"
  x-data="orgss_<?php echo $key; ?>" x-init="">

  <?php // Loading overlay
  ?>
  <div x-transition x-cloak
    class="component-org-search-select__loading-overlay flex justify-center items-center w-full text-dark-100 text-heading-3xl py-10 absolute h-full left-0 right-0 mx-auto bg-white bg-opacity-70"
    x-bind:class="isLoading ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none' ">
    <i class="fa-solid fa-arrows-rotate fa-spin"></i>
  </div> <!-- / .component-org-search-select__loading-overlay -->

  <?php // Confirmation Popup
  ?>
  <div x-transition x-cloak
    class="component-org-search-select__confirmation-popup flex justify-center items-center w-full text-dark-100 py-10 absolute h-full left-0 right-0 mx-auto bg-white bg-opacity-70"
    x-bind:class="showingRemoveConfirmation && removeConfirmationIsEnabled ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none' ">
    <div x-transition
      class="component-org-search-select__confirmation-popup-content rounded-150 bg-white border flex items-center flex-col p-5">
      <div class="component-org-search-select__confirmation-popup-header flex w-full justify-end mb-4">
        <button x-on:click.prevent="showingRemoveConfirmation = false"
          class="component-org-search-select__confirmation-popup-close-button font-semibold"><?php _e('Close X', 'wicket') ?></button>
      </div>
      <div class="component-org-search-select__confirmation-popup-title font-semibold">
        <?php _e('Please confirm you\'d like to end your relationship with this Organization', 'wicket') ?>
      </div>
      <div class="component-org-search-select__confirmation-popup-body mt-4 mb-6">
        <?php _e('Any assigned membership with the organization will be inactivated.', 'wicket') ?>
      </div>
      <div class="component-org-search-select__confirmation-popup-actions flex w-full justify-evenly">
        <?php get_component('button', [
          'variant'  => 'secondary',
          'reversed' => false,
          'label'    => __('Cancel', 'wicket'),
          'type'     => 'button',
          'atts'  => [
            'x-on:click.prevent="showingRemoveConfirmation = false"',
          ],
          'classes' => [
            'component-org-search-select__confirmation-popup-cancel-button',
            'items-center',
            'justify-center',
            'w-5/12'
          ]
        ]); ?>
        <?php get_component('button', [
          'variant'  => 'primary',
          'label'    => __('Remove Location/Subsidiary', 'wicket'),
          'type'     => 'button',
          'atts'  => ['x-on:click.prevent="terminateRelationship()"'],
          'classes' => [
            'component-org-search-select__confirmation-popup-remove-button',
            'items-center',
            'justify-center',
            'w-5/12'
          ],
        ]); ?>
      </div>
    </div>
  </div> <!-- / .component-org-search-select__confirmation-popup -->

  <div x-transition x-cloak
    class="component-org-search-select__active-membership-alert flex justify-center items-center w-full py-10 absolute h-full left-0 right-0 mx-auto"
    style="background: var(--modal-bg-overlay);"
    x-bind:class="showingActiveMembershipAlert && activeMembershipAlertAvailable ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none' ">
    <div x-transition
      class="component-org-search-select__active-membership-alert-content rounded-150 border flex flex-col text-left m-[1rem]" style="background-color: var(--bg-white); padding: var(--space-400);">
      <div class="component-org-search-select__active-membership-alert-header flex w-full justify-end"
        style="margin-bottom: var(--space-400);">
        <button x-on:click.prevent="showingActiveMembershipAlert = false"
          class="component-org-search-select__active-membership-alert-close-button"
          style="color: var(--interactive); background: none; border: none; cursor: pointer;"><?php _e('Close X', 'wicket') ?></button>
      </div>
      <div x-text="activeMembershipAlertTitle"
        class="component-org-search-select__active-membership-alert-title"
        style="font-size: var(--heading-md-font-size); margin-bottom: var(--space-400);"></div>
      <div x-text="activeMembershipAlertBody"
        class="component-org-search-select__active-membership-alert-body"
        style="font-size: var(--body-md-font-size); margin-bottom: var(--space-400); line-height: 1.6;"></div>
      <div class="component-org-search-select__active-membership-alert-actions flex w-full justify-evenly">
        <?php
        if (
          !empty($active_membership_alert_button_1_text)
          && !empty($active_membership_alert_button_1_url)
          && !empty($active_membership_alert_button_1_style)
        ) {
          if ($active_membership_alert_button_1_url == 'PROCEED') {
            get_component('button', [
              'variant'  => 'primary',
              'size'     => 'md',
              'reversed' => false,
              'label'    => $active_membership_alert_button_1_text,
              'type'     => 'button',
              'atts'  => [
                'x-on:click="doWpAction(\'orgss_active_membership_alert_button_1_clicked\');activeMembershipAlertProceedChosen = true;selectOrgAndCreateRelationship(activeMembershipAlertOrgUuid, activeMembershipAlertEvent);"'
              ],
              'classes' => [
                'component-org-search-select__active-membership-alert-button',
                'items-center',
                'justify-center',
                'w-5/12'
              ]
            ]);
          } elseif ($active_membership_alert_button_1_url == 'BUTTON') {
            // If this is set to be a developer button, fire the action, dismiss the modal, and do nothing else (no org selection)
            get_component('button', [
              'variant'  => 'primary',
              'size'     => 'md',
              'reversed' => false,
              'label'    => $active_membership_alert_button_1_text,
              'type'     => 'button',
              'atts'  => [
                'x-on:click="doWpAction(\'orgss_active_membership_alert_button_1_clicked\');dispatchWindowEvent(\'orgss-existing-membership-modal-button1-' . str_replace('/', '', $_SERVER['REQUEST_URI']) . '\', {});showingActiveMembershipAlert = false;"'
              ],
              'classes' => [
                'component-org-search-select__active-membership-alert-button',
                'items-center',
                'justify-center',
                'w-5/12'
              ]
            ]);
          } else {
            // Treat it as a link
            get_component('button', [
              'variant'  => 'primary',
              'size'     => 'md',
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
                'component-org-search-select__active-membership-alert-button',
                'items-center',
                'justify-center',
                'w-5/12'
              ]
            ]);
          }
        }

        if (
          !empty($active_membership_alert_button_2_text)
          && !empty($active_membership_alert_button_2_url)
          && !empty($active_membership_alert_button_2_style)
        ) {
          if ($active_membership_alert_button_2_url == 'PROCEED') {
            get_component('button', [
              'variant'  => 'secondary',
              'size'     => 'md',
              'reversed' => false,
              'label'    => $active_membership_alert_button_2_text,
              'type'     => 'button',
              'atts'  => [
                'style="margin-left:20px;"',
                'x-on:click="doWpAction(\'orgss_active_membership_alert_button_2_clicked\');activeMembershipAlertProceedChosen = true;selectOrgAndCreateRelationship(activeMembershipAlertOrgUuid, activeMembershipAlertEvent);"'
              ],
              'classes' => [
                'component-org-search-select__active-membership-alert-button',
                'items-center',
                'justify-center',
                'w-5/12'
              ]
            ]);
          } elseif ($active_membership_alert_button_2_url == 'BUTTON') {
            // If this is set to be a developer button, fire the action, dismiss the modal, and do nothing else (no org selection)
            get_component('button', [
              'variant'  => 'secondary',
              'size'     => 'md',
              'reversed' => false,
              'label'    => $active_membership_alert_button_2_text,
              'type'     => 'button',
              'atts'  => [
                'style="margin-left:20px;"',
                'x-on:click="doWpAction(\'orgss_active_membership_alert_button_2_clicked\');dispatchWindowEvent(\'orgss-existing-membership-modal-button2-' . str_replace('/', '', $_SERVER['REQUEST_URI']) . '\', {});showingActiveMembershipAlert = false;"'
              ],
              'classes' => [
                'component-org-search-select__active-membership-alert-button',
                'items-center',
                'justify-center',
                'w-5/12'
              ]
            ]);
          } else {
            // Treat it as a link
            get_component('button', [
              'variant'  => 'secondary',
              'size'     => 'md',
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
                'component-org-search-select__active-membership-alert-button',
                'items-center',
                'justify-center',
                'w-5/12'
              ]
            ]);
          }
        }
        ?>
      </div>
    </div>
  </div> <!-- / .component-org-search-select__active-membership-alert -->

  <div
    class="orgss-search-form component-org-search-select__search-form flex flex-col bg-dark-100 bg-opacity-5 rounded-100 p-3">

    <div class="component-org-search-select__search-controls flex flex-col sm:flex-row sm:items-center gap-2" x-show="!justCreatedNewOrg">
      <?php // Can add `@keyup=\"if($el.value.length > 3){ handleSearch(); } \"` to get autocomplete, but it's not quite fast enough
      ?>
      <div class="flex-grow">
        <input x-model="searchBox" @keydown.enter.prevent.stop="handleSearch()" type="text"
          class="orgss-search-box component-org-search-select__search-input w-full"
          placeholder="<?php _e('Search by ' . $orgTermSingularLower . ' name', 'wicket') ?>" />
      </div>
      <div class="sm:flex-shrink-0" x-show="!firstSearchSubmitted">
        <?php get_component('button', [
          'variant'  => 'primary',
          'label'    => __('Search', 'wicket'),
          'type'     => 'button',
          'classes'  => ['component-org-search-select__search-button', 'w-full', 'sm:w-auto'],
          'atts'  => ['x-on:click.prevent="handleSearch()"'],
        ]); ?>
      </div>
      <div class="sm:flex-shrink-0" x-show="firstSearchSubmitted" x-cloak>
        <?php get_component('button', [
          'variant'  => 'primary',
          'label'    => __('Clear', 'wicket'),
          'type'     => 'button',
          'classes'  => ['component-org-search-select__clear-button', 'w-full', 'sm:w-auto'],
          'atts'  => ['x-on:click.prevent="searchBox = \'\'"'],
        ]); ?>
      </div>
    </div>
    <div id="orgss_search_message" class="orgss_error component-org-search-select__search-message" x-cloak
      x-show="showSearchMessage"></div>
    <div class="component-org-search-select__matching-orgs-title <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'mt-4 mb-1' ?>"
      x-show="(firstSearchSubmitted || isLoading) && !justCreatedNewOrg" x-cloak>
      <?php _e('Matching Organization(s)', 'wicket') ?>
    </div>
    <div class="orgss-results component-org-search-select__results"
      x-bind:class="results.length == 0 ? '' : 'orgss-results--has-results' "
      x-show="!justCreatedNewOrg">
      <div
        class="component-org-search-select__search-container <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'flex flex-col bg-white px-4 max-h-80 overflow-y-scroll' ?>">
        <div x-show="results.length == 0 && searchBox.length > 0 && firstSearchSubmitted && !isLoading"
          x-transition x-cloak
          class="component-org-search-select__no-results <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'flex justify-center items-center w-full text-dark-100 text-body-md py-4' ?>">
          <?php echo $noResultsFoundMessage; ?>
        </div>
        <template x-for="(result, uuid) in results" x-cloak>
          <div
            class="component-org-search-select__matching-org-item <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'px-1 py-3 border-b border-dark-100 border-opacity-5 flex justify-between items-center' ?>">
            <div class="component-org-search-select__matching-org-title <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'font-bold' ?>"
              x-text="result.name"></div>
            <?php get_component('button', [
              'variant'  => 'secondary',
              'reversed' => false,
              'label'    => __('Select', 'wicket'),
              'type'     => 'button',
              'classes'  => ['component-org-search-select__select-result-button'],
              'atts'     => [
                'x-on:click.prevent="selectOrgAndCreateRelationship($data.result.id, $event, result.active_membership )"',
                'x-bind:class="{
                  \'orgss_disabled_button_hollow\': isOrgAlreadyAConnection( $data.result.id ),
                  \'orgss_disabled_button_hollow\': result.active_membership && ( disableSelectingOrgsWithActiveMembership && !activeMembershipAlertAvailable )
                }"'
              ]
            ]); ?>
          </div>
        </template>

      </div>
    </div>
    <div class="component-org-search-select__current-orgs" x-show="currentConnections.length > 0 && !firstSearchSubmitted" x-cloak>
      <div class="component-org-search-select__header-section flex justify-between items-center">
        <?php
        if (empty($title)) : ?>
          <h2
            class="component-org-search-select__current-orgs-title <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'font-bold text-body-lg my-3 orgss-search-form__title' ?>"
            x-text="selectedOrgUuid ? '<?php _e('Selected Organization:', 'wicket') ?>' : '<?php _e('Your current Organization(s)', 'wicket') ?>'">
          </h2>
        <?php else: ?>
          <h2
            class="component-org-search-select__current-orgs-title <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'font-bold text-body-lg my-3 orgss-search-form__title' ?>"
            x-text="selectedOrgUuid ? '<?php _e('Selected Organization:', 'wicket') ?>' : '<?php esc_html_e($title, 'wicket'); ?>'">
          </h2>
        <?php endif; ?>

        <div x-show="selectedOrgUuid" x-cloak>
          <?php get_component('button', [
            'variant'  => 'secondary',
            'label'    => __('Clear Selection', 'wicket'),
            'type'     => 'button',
            'classes'  => ['component-org-search-select__clear-selection-button'],
            'atts'     => [
              'x-on:click.prevent="selectedOrgUuid = \'\'; $dispatch(\'wicket:org_search_select_cleared\', { orgSearchSelectKey: \'' . $key . '\' });"'
            ]
          ]); ?>
        </div>
      </div>

      <template x-for="(connection, index) in currentConnections" :key="connection.connection_id" x-transition>
        <div x-show="matchesFilter(connection) && (!justCreatedNewOrg || connection.org_id === justCreatedOrgUuid) && (selectedOrgUuid === '' || connection.org_id === selectedOrgUuid)"
          class="item-org-card component-org-search-select__card <?php if (!defined('WICKET_WP_THEME_V2')) : ?>rounded-100 flex flex-col md:flex-row md:justify-between p-4 mb-3<?php endif; ?>"
          x-bind:class="{
            '<?php echo defined('WICKET_WP_THEME_V2') ? 'component-org-search-select__card--selected' : 'border-success-040 border-opacity-100 border-4' ?>': connection.org_id == selectedOrgUuid,
            '<?php echo defined('WICKET_WP_THEME_V2') ? '' : 'border border-dark-100 border-opacity-5' ?>': connection.org_id != selectedOrgUuid,
            'bg-[var(--highlight-light)]': justCreatedNewOrg && connection.org_id === justCreatedOrgUuid,
            'bg-white': !(justCreatedNewOrg && connection.org_id === justCreatedOrgUuid) && !<?php echo defined('WICKET_WP_THEME_V2') ? 'true' : 'false' ?>
          }"
          x-ref="'org_card_' + connection.org_id">

          <div class="current-org-listing-left component-org-search-select__card-left" x-data="{
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
            <div class="component-org-search-select__org-type <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'font-bold text-body-xs' ?>"
              x-text="connection.org_type_name"></div>
            <div
              class="component-org-search-select__card-top-header <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'flex flex-col sm:flex-row mb-2 sm:items-center' ?>">
              <div x-text="connection.org_name"
                class="component-org-search-select__org-name <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'font-bold text-body-sm sm:mr-5' ?>">
              </div>
              <div>
                <template x-if="connection.active_membership">
                  <div
                    class="component-org-search-select__active-membership-label <?php echo defined('WICKET_WP_THEME_V2') ? '' : '' ?>">
                    <i
                      class="fa-solid fa-circle <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'text-[#08d608]' ?>"></i>
                    <span
                      class="<?php echo defined('WICKET_WP_THEME_V2') ? '' : 'text-body-xs' ?>"><?php _e('Active Membership', 'wicket') ?></span>
                  </div>
                </template>
                <template x-if="! connection.active_membership">
                  <div
                    class="component-org-search-select__inactive-membership-label <?php echo defined('WICKET_WP_THEME_V2') ? '' : '' ?>">
                    <i
                      class="fa-solid fa-circle <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'text-[#A1A1A1]' ?>"></i>
                    <span
                      class="<?php echo defined('WICKET_WP_THEME_V2') ? '' : 'text-body-xs' ?>"><?php _e('Inactive Membership', 'wicket') ?></span>
                  </div>
                </template>
              </div>
            </div>
            <div x-show="showOrgParentName"
              class="component-org-search-select__org-parent-name <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'mb-3' ?>"
              x-text="orgParentName"></div>
            <div x-text="description"
              class="component-org-search-select__org-description <?php echo defined('WICKET_WP_THEME_V2') ? '' : '' ?>">
            </div>
          </div>
          <div
            class="current-org-listing-right component-org-search-select__card-right <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'flex flex-col sm:flex-row items-start sm:items-center gap-2 mt-4 md:mt-0' ?>">
            <template x-if="justCreatedNewOrg && connection.org_id === justCreatedOrgUuid">
              <?php get_component('button', [
                'variant'  => 'secondary',
                'reversed' => false,
                'label'    => '✓ ' . __('Selected', 'wicket'),
                'type'     => 'button',
                'classes'  => ['component-org-search-select__select-button', 'whitespace-nowrap', 'orgss_disabled_button'],
                'atts'     => [
                  'x-show="!hideSelectButtons"',
                ]
              ]); ?>
            </template>
            <template x-if="!(justCreatedNewOrg && connection.org_id === justCreatedOrgUuid)">
              <?php get_component('button', [
                'variant'  => 'secondary',
                'reversed' => false,
                'label'    => __('Select', 'wicket') . ' ' . $orgTermSingularCap,
                'type'     => 'button',
                'classes'  => ['component-org-search-select__select-button', 'whitespace-nowrap'],
                'atts'     => [
                  'x-on:click.prevent="selectOrgAndCreateRelationship($data.connection.org_id, $event, connection.active_membership, true)"',
                  'x-bind:class="{
                    \'orgss_disabled_button\': connection.active_membership && ( disableSelectingOrgsWithActiveMembership && !activeMembershipAlertAvailable )
                  }"',
                  'x-show="!hideSelectButtons && connection.org_id !== selectedOrgUuid"',
                ]
              ]); ?>
            </template>
            <template x-if="connection.org_id === selectedOrgUuid">
              <?php get_component('button', [
                'variant'  => 'secondary',
                'reversed' => false,
                'label'    => '✓ Selected',
                'type'     => 'button',
                'classes'  => ['component-org-search-select__select-button', 'whitespace-nowrap', 'orgss_disabled_button'],
                'atts'     => [
                  'x-show="!hideSelectButtons"',
                ]
              ]); ?>
            </template>
            <?php get_component('button', [
              'variant'  => 'ghost',
              'label'    => __('Remove', 'wicket'),
              'suffix_icon' => 'fa-regular fa-trash',
              'type'     => 'button',
              'classes'  => ['component-org-search-select__remove-button', 'whitespace-nowrap'],
              'atts'     => [
                'x-on:click.prevent="terminateRelationship($data.connection.connection_id)"',
                'x-show="!hideRemoveButtons"'
              ]
            ]); ?>
          </div>
        </div>
      </template>
    </div>

    <?php /*<div class="component-org-search-select__search-label <?php echo defined('WICKET_WP_THEME_V2') ? 'label' : 'font-bold text-body-md mb-2' ?>"
      x-text=" currentConnections.length > 0 ? 'Looking for a different <?php echo $orgTermSingularLower; ?>?' : '<?php _e('Search for your', 'wicket') ?> <?php echo $orgTermSingularLower; ?>' "
      x-show="!justCreatedNewOrg">
    </div>*/ ?>

  </div> <!-- / .component-org-search-select__search-form -->

  <div x-show="firstSearchSubmitted && !disableCreateOrgUi && !justCreatedNewOrg" x-cloak
    class="orgss-create-org-form component-org-search-select__create-org-form <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'mt-4 flex flex-col bg-dark-100 bg-opacity-5 rounded-100 p-3' ?>">
    <div
      class="component-org-search-select__cant-find-org-title font-extralight <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'text-body-md mb-2' ?>">
      <?php _e('Can\'t find your', 'wicket') ?>
      <?php echo $orgTermSingularLower; ?>?<br /><span class="font-bold"><?php _e('Create a new one:', 'wicket') ?></span>
    </div>
    <div class="flex component-org-search-select__create-org-fields">
      <div x-bind:class="newOrgTypeOverride.length <= 0 ? 'w-5/12' : 'w-10/12'"
        class="component-org-search-select__create-org-name-wrapper flex flex-col mr-2">
        <label
          class="component-org-search-select__create-org-label"><?php _e('Name of the', 'wicket') ?>
          <?php echo $orgTermSingularCap; ?>*</label>
        <input x-model="newOrgNameBox" @keyup.enter.prevent.stop="handleOrgCreate($event)" type="text"
          name="company-name" class="component-org-search-select__create-org-name-input w-full" />
      </div>
      <div x-show="newOrgTypeOverride.length <= 0"
        class="component-org-search-select__create-org-type-wrapper flex flex-col w-5/12 mr-2">
        <label
          class="component-org-search-select__create-org-label"><?php _e('Type of', 'wicket') ?>
          <?php echo $orgTermSingularCap; ?>*</label>
        <select x-model="newOrgTypeSelect" x-on:change="newOrgTypeSelect = $el.value;" class="component-org-search-select__create-org-type-select w-full">
          <option value=""><?php _e('Select one', 'wicket') ?></option>
          <template x-for="(orgType, index) in availableOrgTypes.data">
            <option x-bind:class="'orgss_org_type_' + orgType.attributes.slug"
              x-bind:value="orgType.attributes.slug" x-text="orgType['attributes']['name_' + lang]">
              <?php _e('Org type', 'wicket') ?>
            </option>
          </template>
        </select>
      </div>
      <div
        class="component-org-search-select__create-org-button-wrapper flex flex-col w-2/12 items-center justify-end">
        <?php get_component('button', [
          'variant'  => 'primary',
          'label'    => __('Add', 'wicket'),
          'type'     => 'button',
          'classes'  => ['component-org-search-select__create-org-button', 'w-full', 'justify-center'],
          'atts'  => ['x-on:click.prevent="handleOrgCreate($event)"'],
        ]); ?>
      </div>
    </div>
    <div x-show="displayDuplicateOrgWarning"
      class="orgss-red-alert component-org-search-select__duplicate-warning flex mt-4 p-4 border-solid border-l-4 border-t-0 border-r-0 border-b-0 <?php echo defined('WICKET_WP_THEME_V2') ? 'border-[--state-error]' : 'bg-[#f5c2c7] border-[#dc3545]' ?>" style="background-color: var(--state-error-light);">
      <div
        class="icon-col component-org-search-select__duplicate-warning-icon-col flex flex-col justify-center px-2">
        <?php
        if (defined('WICKET_WP_THEME_V2')) {
          get_component('icon', [
            'classes' => ['text-heading-xl', 'text-[--state-error]'],
            'icon'  => 'fa-regular fa-triangle-exclamation',
          ]);
        } else {
          get_component('icon', [
            'classes' => ['text-heading-xl', 'text-[#dc3545]'],
            'icon'  => 'fa-regular fa-triangle-exclamation',
          ]);
        } ?>
      </div>
      <div class="text-col component-org-search-select__duplicate-warning-text-col">
        <div class="component-org-search-select__duplicate-warning-title font-bold text-body-lg">
          <?php echo sprintf(__('%s you are trying to add already exists', 'wicket'), $orgTermSingularCap); ?>
        </div>
        <div class="component-org-search-select__duplicate-warning-body">
          <?php _e('Please enter the name in the search field above to find the existing record.', 'wicket'); ?>
        </div>
      </div>
    </div>
  </div> <!-- / .component-org-search-select__create-org-form -->

  <input type="hidden" name="<?php echo $selectedUuidHiddenFieldName; ?>" value="<?php if (isset($_POST[$selectedUuidHiddenFieldName])) {
                                                                                    echo $_POST[$selectedUuidHiddenFieldName];
                                                                                  } ?>" />
</div>

<script>
  document.addEventListener('alpine:init', () => {
    Alpine.data('orgss_<?php echo $key; ?>', () => ({
      searchQuery: '',
      selectedOrg: null,
      lang: '<?php echo $lang; ?>',
      isLoading: false,
      showingRemoveConfirmation: false,
      removeConfirmationIsEnabled: <?php echo $display_removal_alert_message ? 'true' : 'false'; ?>,
      connectionIdSelectedForRemoval: '',
      firstSearchSubmitted: false,
      searchType: '<?php echo $searchMode; ?>',
      relationshipTypeUponOrgCreation: '<?php echo $relationshipTypeUponOrgCreation; ?>',
      relationshipMode: '<?php echo $relationshipMode; ?>',
      newOrgTypeOverride: '<?php echo $newOrgTypeOverride; ?>',
      searchOrgType: '<?php echo $searchOrgType; ?>',
      availableOrgTypes: <?php echo json_encode($available_org_types); ?>,
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
      apiUrl: "<?php echo get_rest_url(null, 'wicket-base/v1/'); ?>",
      currentConnections: <?php echo json_encode($person_to_org_connections); ?>,
      currentPersonUuid: "<?php echo $current_person_uuid; ?>",
      grantRosterManOnPurchase: <?php echo $grant_roster_man_on_purchase ? 'true' : 'false'; ?>,
      grantOrgEditorOnSelect: <?php echo $grant_org_editor_on_select ? 'true' : 'false'; ?>,
      grantOrgEditorOnPurchase: <?php echo $grant_org_editor_on_purchase ? 'true' : 'false'; ?>,
      hideRemoveButtons: <?php echo $hide_remove_buttons ? 'true' : 'false'; ?>,
      hideSelectButtons: <?php echo $hide_select_buttons ? 'true' : 'false'; ?>,
      enableRelationshipFiltering: <?php echo $enable_relationship_filtering ? 'true' : 'false'; ?>,
      forceCreateOnExistingSelect: <?php echo apply_filters('wicket_orgss_force_create_on_existing_select', false) ? 'true' : 'false'; ?>,
      displayDuplicateOrgWarning: false,
      justCreatedNewOrg: false,
      justCreatedOrgUuid: '',
      description: '<?php echo esc_js($description); ?>',
      jobTitle: '<?php echo esc_js($job_title); ?>',
      relationshipTypeFilter: '<?php echo $relationshipTypeFilter; ?>',

      // Encapsulate filtering logic to avoid complex inline expressions
      matchesFilter(connection) {
        const rm = this.relationshipMode;
        const sType = (this.searchOrgType || '').toLowerCase();
        const filt = (this.relationshipTypeFilter || '').toLowerCase();

        if (connection.connection_type != rm) return false;
        const connOrgType = (connection.org_type || '').toLowerCase();
        if (!(connOrgType === sType || sType === '')) return false;
        if (!connection.active_connection) return false;

        // If filtering disabled or empty filter, pass through
        if (!this.enableRelationshipFiltering || filt === '') return true;

        // Strict: only match on definitive relationship_type
        const relType = ((connection.relationship_type || '') + '').toLowerCase();
        return relType === filt;
      },

      init() {
        // Normalize optional filter for backward compatibility
        if (typeof this.relationshipTypeFilter !== 'string') {
          this.relationshipTypeFilter = '';
        }
        this.relationshipTypeFilter = this.relationshipTypeFilter.trim();

        // Determine if the active membership modal has enough data to use
        if (this.disableSelectingOrgsWithActiveMembership && (this.activeMembershipAlertTitle
            .length > 0 || this.activeMembershipAlertBody.length > 0)) {
          this.activeMembershipAlertAvailable = true;
        }

        this.$watch('searchBox', (value) => {
          if (value === '') {
            this.results = [];
            this.firstSearchSubmitted = false;
            this.showSearchMessage = false;
          }
        });
      },
      handleSearch(e = null) {
        if (e) {
          e.preventDefault();
        }

        if (this.searchBox.length < 1) {
          this.setSearchMessage(
            '<?php _e('Please provide a search term', 'wicket') ?>'
          );
          return;
        } else {
          this.showSearchMessage = false; // Clear notice in case its visible
        }

        this.results = [];

        if (this.searchType == 'org') {
          this.searchOrgs(this.searchBox);
        } else if (this.searchType == 'groups') {
          this.searchGroups(this.searchBox);
        }
      },
      handleOrgCreate(e = null) {
        if (e) {
          e.preventDefault();
        }

        if (this.searchType == 'groups') {
          // Handle group creation
        } else {
          // Creating an org
          let newOrgName = this.newOrgNameBox;

          if (!newOrgName.trim()) {
            alert('Please enter an organization name.');
            return;
          }

          let newOrgType = '';
          if (this.newOrgTypeOverride.length > 0) {
            newOrgType = this.newOrgTypeOverride;
          } else {
            newOrgType = this.newOrgTypeSelect;
          }

          if (!newOrgType) {
            alert('Please select an organization type.');
            return;
          }

          this.createOrganization(newOrgName, newOrgType, e);
        }

      },
      setSearchMessage(message) {
        document.getElementById('orgss_search_message').innerHTML = message;
        this.showSearchMessage = true;
      },
      async searchOrgs(searchTerm, showLoading = true) {
        if (showLoading) {
          this.isLoading = true;
        }

        let orgType = this.searchOrgType;
        let data = {};
        if (orgType.length > 0) {
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
            body: JSON.stringify(
              data), // body data type must match "Content-Type" header
          }).then(response => response.json())
          .then(data => {
            if (!data.success) {
              this.results = [];
            } else {
              this.results = data.data;
            }

            if (showLoading) {
              this.isLoading = false;
            }

            if (!this.firstSearchSubmitted) {
              this.firstSearchSubmitted = true;
            }
          });

      },
      selectOrg(orgUuid, incomingEvent = null, dispatchEvent = true) {
        // Update state
        this.selectedOrgUuid = orgUuid;

        // Update both hidden fields to ensure compatibility
        const componentField = document.querySelector('input[name="<?php echo $selectedUuidHiddenFieldName; ?>"]');
        if (componentField) {
          componentField.value = orgUuid;
        }

        // Also update the standard GF hidden field if it exists
        const gfField = document.querySelector('input[name="input_<?php echo $key; ?>"]');
        if (gfField) {
          gfField.value = orgUuid;
        }

        // Scroll to the selected org card
        this.$nextTick(() => {
          const refName = 'org_card_' + orgUuid;
          const card = this.$refs[refName];
          if (card && card.scrollIntoView) {
            card.scrollIntoView({
              behavior: 'smooth',
              block: 'center'
            });
          }
        });

        if (dispatchEvent) {
          // selectOrg() is the last function call used in the process, whether selecting an existing
          // org or creating a new one and then selecting it, so from here we'll dispatch the selection info
          let orgInfo = this.getOrgFromConnectionsByUuid(orgUuid);

          this.dispatchWindowEvent("orgss-selection-made", {
            uuid: orgUuid,
            searchType: this.searchType,
            orgDetails: orgInfo,
            event: incomingEvent,
            formId: <?php echo $formId; ?>
          });
        }



        if (this.grantRosterManOnPurchase) {
          this.flagForRosterManagementAccess(orgUuid);
        }
        if (this.grantOrgEditorOnSelect) {
          this.grantOrgEditor(this.currentPersonUuid, orgUuid);
        }
        if (this.grantOrgEditorOnPurchase) {
          this.flagForOrgEditorAccess(orgUuid);
        }
      },
      dispatchWindowEvent(name, details) {
        newEvent = new CustomEvent(name, {
          detail: details
        });
        window.dispatchEvent(newEvent);
      },
      selectOrgAndCreateRelationship(orgUuid, event = null, existingActiveMembership = false,
        skipCreateRelationship = false) {
        // TODO: Handle when a Group is selected instead of an org

        // ------------------
        // Active Membership modal alert
        // ------------------

        // Only show if the 'Disable Org Select with Active Membership' feature is enabled, sufficient modal data
        // was populated, and the user hasn't already chosen to proceed with the usual actions
        if (existingActiveMembership && this.disableSelectingOrgsWithActiveMembership && this
          .activeMembershipAlertAvailable && !this.activeMembershipAlertProceedChosen) {
          // Display the modal
          this.showingActiveMembershipAlert = true;

          // Stash data away so we can resume this operation later
          this.activeMembershipAlertOrgUuid = orgUuid;
          if (event) {
            this.activeMembershipAlertEvent = event;
          }

          this.selectOrg(orgUuid, event,
            false
          ); // Make the selection so we have the org UUID available for possible PHP hook usage off the modal
          return;
        }

        // The user choose to proceed the modal
        if (this.disableSelectingOrgsWithActiveMembership && this
          .activeMembershipAlertAvailable && this.activeMembershipAlertProceedChosen) {
          // Clear temp data
          this.activeMembershipAlertOrgUuid = '';
          this.activeMembershipAlertEvent = null;

          // Close modal
          this.showingActiveMembershipAlert = false;
        }

        // ------------------
        // Usual operations
        // ------------------
        // If the site forces creation on select, ignore the skip flag passed by the caller
        const shouldCreate = this.forceCreateOnExistingSelect || !skipCreateRelationship;
        if (shouldCreate) {
          this.createOrUpdateRelationship(this.currentPersonUuid, orgUuid, this.relationshipMode,
            this.relationshipTypeUponOrgCreation);
        }
        this.selectOrg(orgUuid, event);

        this.searchBox = '';
      },
      async flagForRosterManagementAccess(orgUuid) {
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
            if (!data.success) {
              // Handle error
            } else {
              // Handle success if needed
            }
          });
      },
      async grantOrgEditor(personUuid, orgUuid) {
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
            if (!data.success) {
              // Handle error
            } else {
              // Handle success if needed
            }
          });
      },
      async searchGroups(searchTerm, showLoading = true) {
        if (showLoading) {
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
            if (!data.success) {
              // Handle error
            } else {
              if (showLoading) {
                this.isLoading = false;
              }
              this.results = data.data;
              if (!this.firstSearchSubmitted) {
                this.firstSearchSubmitted = true;
              }
            }
          });

      },
      async createOrUpdateRelationship(fromUuid, toUuid, relationshipType, userRoleInRelationship) {
        this.isLoading = true;

        let data = {
          "fromUuid": fromUuid,
          "toUuid": toUuid,
          "relationshipType": relationshipType,
          "userRoleInRelationship": userRoleInRelationship,
          "description": this.description,
        };

        let endPointUrl = this.apiUrl + 'create-relationship';

        if (data.relationshipType === 'organization_parent') {
          data.fromUuid =
            '<?php echo $org_id; ?>';
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
            if (!data.success) {
              // Handle error
            } else {
              if (data.success) {
                <?php if ($relationshipMode === 'organization_parent') : ?>
                  this.results = [];
                  this.isLoading = false;
                  this.firstSearchSubmitted = false;

                  let responseText =
                    '<?php esc_attr_e('Organization connected successfully', 'wicket'); ?>';

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

                if (typeof data.data[0] === 'undefined') {
                  // A single connection array was returned
                  this.addConnection(data.data);
                } else {
                  // An array of connections was returned
                  // and we'll use the first one for org info
                  this.addConnection(data.data[0]);
                }
              }
            }
          });

      },

      async terminateRelationship(connectionId = null) {
        // If we're using the remove confirmation feature
        if (this.removeConfirmationIsEnabled) {
          // If we're already showing the remove confirmation,
          // proceed as usual as they've confirmed the action
          if (this.showingRemoveConfirmation) {
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
        if (!connectionId) {
          if (this.connectionIdSelectedForRemoval) {
            connectionId = this.connectionIdSelectedForRemoval;
            this.connectionIdSelectedForRemoval = ''; // Clear it for later use
          }
        }

        this.isLoading = true;

        let data = {
          "connectionId": connectionId,
          "relationshipType": this
            .relationshipTypeUponOrgCreation, // Restricts the removal to the defined relationship type
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
            if (!data.success) {
              // Handle error
              let errorMessage = data.data.toLowerCase();
              if (errorMessage.indexOf('relationships do not match') !== -1) {
                // The user tried to remove a relationship of a different type than was specified
                // for this ORGSS component. If this is a common occurrence, should display an error.
              }
              if (errorMessage.indexOf(
                  'wrong setting the end date of the connection') !== -1) {
                // Display an error; they were likely trying to set the end date to the same day it was created
                this.setSearchMessage(
                  "<?php _e('There was an error removing that relationship. Note that you can\'t remove relationships on the same day you create them.', 'wicket'); ?>"
                );
              }
            } else {
              if (data.success) {
                this.removeConnection(connectionId);
              }

            }
          });

      },
      async createOrganization(orgName, orgType, event = null) {
        this.isLoading = true;

        if (orgName.length <= 0 || orgType.length <= 0) {
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
            if (!data.success) {
              // Handle error
              this.isLoading = false;
              if (data.data.includes('Duplicate org is not allowed')) {
                this.displayDuplicateOrgWarning = true;
                return;
              }
              this.setSearchMessage(sprintf(__(
                'There was an error creating the %s, please try again.',
                'wicket'), $orgTermSingularLower));
            } else {
              if (data.success) {
                let newOrgUuid = data.data.data.id;

                // Set the flag to indicate a new org was just created
                this.justCreatedNewOrg = true;
                this.justCreatedOrgUuid = newOrgUuid;

                this.selectOrgAndCreateRelationship(newOrgUuid, event);

                // Check a user-defined checkbox (if provided) so the form an respond to a new org
                // being created or not
                if ("<?php echo $checkboxIdNewOrg; ?>" !==
                  "") {
                  document.getElementById(
                    "<?php echo $checkboxIdNewOrg; ?>"
                  ).checked = true;
                }
              }

            }
          });

      },
      addConnection(payload) {
        this.currentConnections.push(payload);
      },
      removeConnection(connectionId) {
        let connections = this.currentConnections;
        let removedOrgId = null;

        connections.forEach((val, i, array) => {
          if (val.connection_id == connectionId) {
            removedOrgId = val.org_id;
            array.splice(i, 1);
          }
        });

        this.currentConnections = connections;

        // Reset to initial state if removing the newly created org
        if (this.justCreatedNewOrg && removedOrgId === this.justCreatedOrgUuid) {
          this.justCreatedNewOrg = false;
          this.justCreatedOrgUuid = '';
        }

        // Clear selection state if removing the currently selected org
        if (removedOrgId === this.selectedOrgUuid) {
          this.selectedOrgUuid = '';

          // Clear both hidden field values
          const componentField = document.querySelector('input[name="<?php echo $selectedUuidHiddenFieldName; ?>"]');
          if (componentField) {
            componentField.value = '';
          }

          // Also clear the standard GF hidden field if it exists
          const gfField = document.querySelector('input[name="input_<?php echo $key; ?>"]');
          if (gfField) {
            gfField.value = '';
          }
        }
      },
      isOrgAlreadyAConnection(uuid) {
        let connections = this.currentConnections;
        let isOrgAlreadyAConnection = false;

        connections.forEach((val, i, array) => {
          let org_id = val.org_id;
          //console.log(`Checking if already a connection: incoming ${uuid} vs existing ${org_id}`);
          if (org_id == uuid) {
            //console.log('Is already a connection');
            isOrgAlreadyAConnection = true;
          }
        });

        return isOrgAlreadyAConnection;
      },
      getOrgFromConnectionsByUuid(uuid) {
        let found = {};
        this.currentConnections.forEach((connection, index) => {
          if (connection.org_id == uuid) {
            found = connection;
            return;
          }
        });
        return found;
      },
      async doWpAction(actionType) {
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
            if (!data.success) {
              // Handle error
            } else {
              // ...
            }
          });

        return;
      },
      async flagForOrgEditorAccess(orgUuid) {
        let data = {
          "orgUuid": orgUuid,
        };

        let results = await fetch(this.apiUrl + 'flag-for-org-editor-access', {
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
            if (!data.success) {
              // Handle error
            } else {
              // Handle success if needed
            }
          });
      }
    }))
  })
</script>
