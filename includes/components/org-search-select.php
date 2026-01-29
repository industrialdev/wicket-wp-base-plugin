<?php
//<script>window.WICKET_ORGSS_DEBUG = false;</script>
//define('WICKET_ORGSS_DEBUG', true);

$defaults = [
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
    'active_membership_seat_messaging_enabled'      => false,
    'active_membership_seat_available_alert_title'  => __('Seats available for this organization', 'wicket'),
    'active_membership_seat_available_alert_body'   => __('This organization has an active membership with open seats. You can proceed now or review the helpful links below.', 'wicket'),
    'active_membership_seat_available_alert_button_1_text' => '',
    'active_membership_seat_available_alert_button_1_url'  => '',
    'active_membership_seat_available_alert_button_1_style' => 'primary',
    'active_membership_seat_available_alert_button_1_new_tab' => false,
    'active_membership_seat_available_alert_button_2_text' => '',
    'active_membership_seat_available_alert_button_2_url'  => '',
    'active_membership_seat_available_alert_button_2_style' => 'secondary',
    'active_membership_seat_available_alert_button_2_new_tab' => false,
    'active_membership_seat_unavailable_alert_title'  => __('No seats remaining for this organization', 'wicket'),
    'active_membership_seat_unavailable_alert_body'   => __('All seats for this organization are already assigned. Remove an existing assignment or contact support to continue.', 'wicket'),
    'active_membership_seat_unavailable_alert_button_1_text' => '',
    'active_membership_seat_unavailable_alert_button_1_url'  => '',
    'active_membership_seat_unavailable_alert_button_1_style' => 'primary',
    'active_membership_seat_unavailable_alert_button_1_new_tab' => false,
    'active_membership_seat_unavailable_alert_button_2_text' => '',
    'active_membership_seat_unavailable_alert_button_2_url'  => '',
    'active_membership_seat_unavailable_alert_button_2_style' => 'secondary',
    'active_membership_seat_unavailable_alert_button_2_new_tab' => false,
    'active_membership_notify_enabled' => false,
    'active_membership_notify_email_subject' => '',
    'active_membership_notify_email_body' => '',
    'active_membership_seat_unavailable_notify_enabled' => false,
    'active_membership_seat_unavailable_notify_email_subject' => '',
    'active_membership_seat_unavailable_notify_email_body' => '',
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
    'display_org_fields'                            => 'name', // Options: name, name_location, name_address
    'display_org_type'                              => false,
    'form_id'                                       => 0,
];
$args = wp_parse_args($args, $defaults);
$classes = $args['classes'];
$searchMode = $args['search_mode'];
$searchOrgType = $args['search_org_type'];
$relationshipTypeUponOrgCreation = $args['relationship_type_upon_org_creation'];
$relationshipMode = $args['relationship_mode'];
$relationshipTypeFilter = $args['relationship_type_filter'];
$enable_relationship_filtering = $args['enable_relationship_filtering'];
$newOrgTypeOverride = $args['new_org_type_override'];
$selectedUuidHiddenFieldName = $args['selected_uuid_hidden_field_name'];
$checkboxIdNewOrg = $args['checkbox_id_new_org'];
$key = $args['key'];
$orgTermSingular = $args['org_term_singular'];
$orgTermPlural = $args['org_term_plural'];
$noResultsFoundMessage = $args['no_results_found_message'];
$disable_create_org_ui = $args['disable_create_org_ui'];
$disable_selecting_orgs_with_active_membership = $args['disable_selecting_orgs_with_active_membership'];
$active_membership_alert_title = $args['active_membership_alert_title'];
$active_membership_alert_body = $args['active_membership_alert_body'];
$active_membership_alert_button_1_text = $args['active_membership_alert_button_1_text'];
$active_membership_alert_button_1_url = $args['active_membership_alert_button_1_url']; // Can be a URL, or "PROCEED" to continue
$active_membership_alert_button_1_style = $args['active_membership_alert_button_1_style'];
$active_membership_alert_button_1_new_tab = $args['active_membership_alert_button_1_new_tab'];
$active_membership_alert_button_2_text = $args['active_membership_alert_button_2_text'];
$active_membership_alert_button_2_url = $args['active_membership_alert_button_2_url'];
$active_membership_alert_button_2_style = $args['active_membership_alert_button_2_style'];
$active_membership_alert_button_2_new_tab = $args['active_membership_alert_button_2_new_tab'];
$active_membership_seat_messaging_enabled = (bool) $args['active_membership_seat_messaging_enabled'];
$active_membership_seat_available_alert_title = $args['active_membership_seat_available_alert_title'];
$active_membership_seat_available_alert_body = $args['active_membership_seat_available_alert_body'];
$active_membership_seat_available_alert_button_1_text = $args['active_membership_seat_available_alert_button_1_text'];
$active_membership_seat_available_alert_button_1_url = $args['active_membership_seat_available_alert_button_1_url'];
$active_membership_seat_available_alert_button_1_style = $args['active_membership_seat_available_alert_button_1_style'];
$active_membership_seat_available_alert_button_1_new_tab = (bool) $args['active_membership_seat_available_alert_button_1_new_tab'];
$active_membership_seat_available_alert_button_2_text = $args['active_membership_seat_available_alert_button_2_text'];
$active_membership_seat_available_alert_button_2_url = $args['active_membership_seat_available_alert_button_2_url'];
$active_membership_seat_available_alert_button_2_style = $args['active_membership_seat_available_alert_button_2_style'];
$active_membership_seat_available_alert_button_2_new_tab = (bool) $args['active_membership_seat_available_alert_button_2_new_tab'];
$active_membership_seat_unavailable_alert_title = $args['active_membership_seat_unavailable_alert_title'];
$active_membership_seat_unavailable_alert_body = $args['active_membership_seat_unavailable_alert_body'];
$active_membership_seat_unavailable_alert_button_1_text = $args['active_membership_seat_unavailable_alert_button_1_text'];
$active_membership_seat_unavailable_alert_button_1_url = $args['active_membership_seat_unavailable_alert_button_1_url'];
$active_membership_seat_unavailable_alert_button_1_style = $args['active_membership_seat_unavailable_alert_button_1_style'];
$active_membership_seat_unavailable_alert_button_1_new_tab = (bool) $args['active_membership_seat_unavailable_alert_button_1_new_tab'];
$active_membership_seat_unavailable_alert_button_2_text = $args['active_membership_seat_unavailable_alert_button_2_text'];
$active_membership_seat_unavailable_alert_button_2_url = $args['active_membership_seat_unavailable_alert_button_2_url'];
$active_membership_seat_unavailable_alert_button_2_style = $args['active_membership_seat_unavailable_alert_button_2_style'];
$active_membership_seat_unavailable_alert_button_2_new_tab = (bool) $args['active_membership_seat_unavailable_alert_button_2_new_tab'];
$active_membership_seat_unavailable_notify_enabled = (bool) $args['active_membership_seat_unavailable_notify_enabled'];
$active_membership_seat_unavailable_notify_email_subject = $args['active_membership_seat_unavailable_notify_email_subject'];
$active_membership_seat_unavailable_notify_email_body = $args['active_membership_seat_unavailable_notify_email_body'];
$active_membership_notify_enabled = (bool) $args['active_membership_notify_enabled'];
$active_membership_notify_email_subject = $args['active_membership_notify_email_subject'];
$active_membership_notify_email_body = $args['active_membership_notify_email_body'];
$grant_roster_man_on_purchase = $args['grant_roster_man_on_purchase'];
$grant_org_editor_on_select = $args['grant_org_editor_on_select'];
$grant_org_editor_on_purchase = $args['grant_org_editor_on_purchase'];
$hide_remove_buttons = $args['hide_remove_buttons'];
$hide_select_buttons = $args['hide_select_buttons'];
$display_removal_alert_message = $args['display_removal_alert_message'];
$title = $args['title'];
$responseMessage = $args['response_message'];
$description = $args['description'];
$job_title = $args['job_title'];
$display_org_fields = $args['display_org_fields'];
$display_org_type = $args['display_org_type'] ?? false;
$formId = $args['form_id'];
$lang = wicket_get_current_language();
$is_wicket_theme = defined('WICKET_THEME');

if (trim((string) $active_membership_seat_available_alert_title) === '') {
    $active_membership_seat_available_alert_title = __('Seats available for this organization', 'wicket');
}
if (trim((string) $active_membership_seat_available_alert_body) === '') {
    $active_membership_seat_available_alert_body = __('This organization has an active membership with open seats. You can proceed now or review the helpful links below.', 'wicket');
}
if (trim((string) $active_membership_seat_unavailable_alert_title) === '') {
    $active_membership_seat_unavailable_alert_title = __('No seats remaining for this organization', 'wicket');
}
if (trim((string) $active_membership_seat_unavailable_alert_body) === '') {
    $active_membership_seat_unavailable_alert_body = __('All seats for this organization are already assigned. Remove an existing assignment or contact support to continue.', 'wicket');
}

if (trim((string) $active_membership_seat_available_alert_button_1_text) !== '') {
    if (trim((string) $active_membership_seat_available_alert_button_1_url) === '') {
        $active_membership_seat_available_alert_button_1_url = 'PROCEED';
    }
    if (trim((string) $active_membership_seat_available_alert_button_1_style) === '') {
        $active_membership_seat_available_alert_button_1_style = 'primary';
    }
}

$seat_available_message_configured = (bool) array_filter([
    $active_membership_seat_available_alert_title,
    $active_membership_seat_available_alert_body,
], function ($value) {
    return trim((string) $value) !== '';
});

$seat_unavailable_message_configured = (bool) array_filter([
    $active_membership_seat_unavailable_alert_title,
    $active_membership_seat_unavailable_alert_body,
], function ($value) {
    return trim((string) $value) !== '';
});

if ($active_membership_seat_unavailable_notify_enabled) {
    if (trim((string) $active_membership_seat_unavailable_alert_button_2_text) === '') {
        $active_membership_seat_unavailable_alert_button_2_text = __('Notify your account manager', 'wicket');
    }
    if (trim((string) $active_membership_seat_unavailable_alert_button_2_style) === '') {
        $active_membership_seat_unavailable_alert_button_2_style = 'secondary';
    }
    $active_membership_seat_unavailable_alert_button_2_url = 'NOTIFY_OWNER';
    $active_membership_seat_unavailable_alert_button_2_new_tab = false;
}

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

$orgTermSingularCap = ucfirst(strtolower($orgTermSingular));
$orgTermSingularLower = strtolower($orgTermSingular);

if (!empty($orgTermPlural)) {
    $orgTermPlural = __($orgTermPlural, 'wicket');
} else {
    if ($searchMode == 'org') {
        $orgTermPlural = __('Organizations', 'wicket');
    }
    if ($searchMode == 'groups') {
        $orgTermPlural = __('Groups', 'wicket');
    }
}

$orgTermPluralCap = ucfirst(strtolower($orgTermPlural));
$orgTermPluralLower = strtolower($orgTermPlural);
if (empty($noResultsFoundMessage)) {
    $noResultsFoundMessage = sprintf(__('Sorry, no %s match your search. Please try again.', 'wicket'), $orgTermPluralLower);
} else {
    $noResultsFoundMessage = __($noResultsFoundMessage, 'wicket');
}

$current_person_uuid = wicket_current_person_uuid();
// Get users current org/group relationships
$person_to_org_connections = [];
if ($searchMode == 'org') {
    $current_connections = wicket_get_person_connections([
        'dedupe' => 'org_id',
    ]);

    foreach ($current_connections['data'] as $connection) {
        $connection_id = $connection['id'];
        if (isset($connection['attributes']['connection_type'])) {
            $org_id = $connection['relationships']['organization']['data']['id'];

            $org_info = wicket_get_organization_basic_info($org_id);

            $org_memberships = wicket_get_org_memberships($org_id);
            $seat_summary = function_exists('wicket_get_active_membership_seat_summary')
                ? wicket_get_active_membership_seat_summary($org_memberships)
                : [
                    'has_active_membership' => false,
                    'assigned'              => null,
                    'max'                   => null,
                    'unlimited'             => false,
                    'has_available_seats'   => null,
                ];

            $has_active_membership = $seat_summary['has_active_membership'] ?? false;

            /**------------------------------------------------------------------
             * Get Primary Address (only when address/location is displayed)
            ------------------------------------------------------------------*/
            $address1 = '';
            $city = '';
            $zip_code = '';
            $state_name = '';
            $country_code = '';

            $address_fields_needed = in_array($display_org_fields, ['name_location', 'name_address'], true);
            if ($address_fields_needed) {
                $org_addresses = wicket_get_organization_addresses($org_id);

                if (!empty($org_addresses['data']) && is_array($org_addresses['data'])) {
                    foreach ($org_addresses['data'] as $address) {
                        if (
                            isset($address['attributes']['primary'])
                            && $address['attributes']['primary'] === true
                        ) {
                            $address1 = $address['attributes']['address1'] ?? '';
                            $city = $address['attributes']['city'] ?? '';
                            $zip_code = $address['attributes']['zip_code'] ?? '';
                            $state_name = $address['attributes']['state_name'] ?? '';
                            $country_code = $address['attributes']['country_code'] ?? '';
                            break; // stop after finding primary address
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
                'active_membership_seat_summary' => $seat_summary,
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
                'address1'          => $address1,
                'city'              => $city,
                'zip_code'          => $zip_code,
                'state_name'        => $state_name,
                'country_code'      => $country_code,
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
  <?php // Loading overlay ?>
  <div x-transition x-cloak
    class="component-org-search-select__loading-overlay flex justify-center items-center w-full text-dark-100 text-heading-3xl py-10 absolute h-full left-0 right-0 mx-auto bg-white bg-opacity-70"
    x-bind:class="isLoading ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none' ">
    <i class="fa-solid fa-arrows-rotate fa-spin"></i>
  </div> <!-- / .component-org-search-select__loading-overlay -->

  <div class="component-org-search-select__removal-error-wrapper mb-4" x-cloak x-show="removalErrorVisible" x-transition>
    <div class="component-org-search-select__removal-error border border-[var(--state-error,#B42318)] rounded-100 bg-[var(--state-error-light,#FEF3F2)] p-4 text-sm text-dark-100">
      <div class="font-semibold text-dark-100" x-text="removalErrorMessage"></div>
      <div class="mt-1" x-text="removalErrorHint"></div>
      <template x-if="removalErrorDetail">
        <pre class="mt-3 whitespace-pre-wrap break-words bg-white/80 rounded-50 p-3 text-[13px]" x-text="removalErrorDetail"></pre>
      </template>
      <button type="button" class="mt-3 text-[var(--interactive,#0044C1)] underline" x-on:click="clearRemovalError()">
        <?php _e('Dismiss message', 'wicket'); ?>
      </button>
    </div>
  </div>

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
        <span x-text="'<?php echo esc_js(__('Please confirm: You\'d like to remove your connection with %s?', 'wicket')); ?>'.replace('%s', removeConfirmationOrgName)"></span>
      </div>
      <div class="component-org-search-select__confirmation-popup-body mt-4 mb-6">
        <span x-text="'<?php echo esc_js(__('This will remove all connections you have with %s, including membership.', 'wicket')); ?>'.replace('%s', removeConfirmationOrgName)"></span>
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
                'w-5/12',
            ],
        ]); ?>
        <?php get_component('button', [
            'variant'  => 'primary',
            'label'    => '',
            'type'     => 'button',
            'atts'  => [
                'x-on:click.prevent="terminateRelationship()"',
                'x-text' => 'removeButtonLabel',
            ],
            'classes' => [
                'component-org-search-select__confirmation-popup-remove-button',
                'items-center',
                'justify-center',
                'w-5/12',
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
      <div x-html="activeMembershipAlertTitle"
        class="component-org-search-select__active-membership-alert-title"
        style="font-size: var(--heading-md-font-size); margin-bottom: var(--space-400);"></div>
      <div x-html="activeMembershipAlertBody"
        class="component-org-search-select__active-membership-alert-body"
        style="font-size: var(--body-md-font-size); margin-bottom: var(--space-400); line-height: 1.6;"></div>
      <div x-show="notifyOwnerMessage" x-cloak
        class="component-org-search-select__active-membership-alert-note"
        x-text="notifyOwnerMessage"
        style="margin-bottom: var(--space-400);"></div>
      <div x-show="notifyOwnerIsLoading" x-cloak
        class="component-org-search-select__active-membership-alert-note"
        style="margin-bottom: var(--space-400); display: flex; align-items: center; gap: 8px;">
        <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
        <span><?php _e('Sending...', 'wicket'); ?></span>
      </div>
      <div class="component-org-search-select__active-membership-alert-actions flex w-full justify-evenly">
        <?php
        // Helper function to render a button pair for the active membership alert
        // This reduces code duplication across the 3 message states (base, available, unavailable)
        $render_alert_buttons = function(
            $button_1_text, $button_1_url, $button_1_style, $button_1_new_tab,
            $button_2_text, $button_2_url, $button_2_style, $button_2_new_tab,
            $action_suffix = ''
        ) {
            $request_uri_clean = str_replace('/', '', $_SERVER['REQUEST_URI']);

            // Button 1
            if (
                !empty($button_1_text)
                && !empty($button_1_url)
                && !empty($button_1_style)
            ) {
                if ($button_1_url == 'PROCEED') {
                    get_component('button', [
                        'variant'  => $button_1_style ?? 'primary',
                        'size'     => 'md',
                        'reversed' => false,
                        'label'    => $button_1_text,
                        'type'     => 'button',
                        'atts'  => [
                            'x-on:click="doWpAction(\'orgss_active_membership_alert_button_1_clicked' . $action_suffix . '\');maybeNotifyBaseProceed();activeMembershipAlertProceedChosen = true;selectOrgAndCreateRelationship(activeMembershipAlertOrgUuid, activeMembershipAlertEvent, true, false, activeMembershipAlertSeatSummary);"',
                        ],
                        'classes' => [
                            'component-org-search-select__active-membership-alert-button',
                            'items-center',
                            'justify-center',
                            'w-5/12',
                        ],
                    ]);
                } elseif ($button_1_url == 'BUTTON') {
                    $button_1_is_close_only = ($action_suffix === '_seats_unavailable');
                    get_component('button', [
                        'variant'  => $button_1_style ?? 'primary',
                        'size'     => 'md',
                        'reversed' => false,
                        'label'    => $button_1_text,
                        'type'     => 'button',
                        'atts'  => [
                            $button_1_is_close_only
                                ? 'x-on:click="doWpAction(\'orgss_active_membership_alert_button_1_clicked' . $action_suffix . '\');showingActiveMembershipAlert = false;"'
                                : 'x-on:click="doWpAction(\'orgss_active_membership_alert_button_1_clicked' . $action_suffix . '\');dispatchWindowEvent(\'orgss-existing-membership-modal-button1-' . $request_uri_clean . '\', {});showingActiveMembershipAlert = false;"',
                        ],
                        'classes' => [
                            'component-org-search-select__active-membership-alert-button',
                            'items-center',
                            'justify-center',
                            'w-5/12',
                        ],
                    ]);
                } else {
                    get_component('button', [
                        'variant'  => $button_1_style ?? 'primary',
                        'size'     => 'md',
                        'reversed' => false,
                        'label'    => $button_1_text,
                        'type'     => 'button',
                        'a_tag'    => true,
                        'link'     => $button_1_url,
                        'link_target' => $button_1_new_tab ? '_blank' : '_self',
                        'atts'  => [
                            'x-on:click="doWpAction(\'orgss_active_membership_alert_button_1_clicked' . $action_suffix . '\');"',
                        ],
                        'classes' => [
                            'component-org-search-select__active-membership-alert-button',
                            'items-center',
                            'justify-center',
                            'w-5/12',
                        ],
                    ]);
                }
            }

            // Button 2
            if (
                !empty($button_2_text)
                && !empty($button_2_url)
                && !empty($button_2_style)
            ) {
                if ($button_2_url == 'PROCEED') {
                    get_component('button', [
                        'variant'  => $button_2_style ?? 'secondary',
                        'size'     => 'md',
                        'reversed' => false,
                        'label'    => $button_2_text,
                        'type'     => 'button',
                        'atts'  => [
                            'style="margin-left:20px;"',
                            'x-on:click="doWpAction(\'orgss_active_membership_alert_button_2_clicked' . $action_suffix . '\');activeMembershipAlertProceedChosen = true;selectOrgAndCreateRelationship(activeMembershipAlertOrgUuid, activeMembershipAlertEvent, true, false, activeMembershipAlertSeatSummary);"',
                        ],
                        'classes' => [
                            'component-org-search-select__active-membership-alert-button',
                            'items-center',
                            'justify-center',
                            'w-5/12',
                        ],
                    ]);
                } elseif ($button_2_url == 'BUTTON') {
                    get_component('button', [
                        'variant'  => $button_2_style ?? 'secondary',
                        'size'     => 'md',
                        'reversed' => false,
                        'label'    => $button_2_text,
                        'type'     => 'button',
                        'atts'  => [
                            'style="margin-left:20px;"',
                            'x-on:click="doWpAction(\'orgss_active_membership_alert_button_2_clicked' . $action_suffix . '\');dispatchWindowEvent(\'orgss-existing-membership-modal-button2-' . $request_uri_clean . '\', {});showingActiveMembershipAlert = false;"',
                        ],
                        'classes' => [
                            'component-org-search-select__active-membership-alert-button',
                            'items-center',
                            'justify-center',
                            'w-5/12',
                        ],
                    ]);
                } elseif ($button_2_url == 'NOTIFY_OWNER') {
                    get_component('button', [
                        'variant'  => $button_2_style ?? 'secondary',
                        'size'     => 'md',
                        'reversed' => false,
                        'label'    => $button_2_text,
                        'type'     => 'button',
                        'atts'  => [
                            'style="margin-left:20px;"',
                            'x-on:click.prevent="notifyOrgOwner()"',
                            'x-bind:disabled="notifyOwnerIsLoading"',
                            'x-bind:class="{ \'orgss_disabled_button\': notifyOwnerIsLoading }"',
                            'x-bind:aria-busy="notifyOwnerIsLoading ? \'true\' : \'false\'"',
                        ],
                        'classes' => [
                            'component-org-search-select__active-membership-alert-button',
                            'items-center',
                            'justify-center',
                            'w-5/12',
                        ],
                    ]);
                } else {
                    get_component('button', [
                        'variant'  => $button_2_style ?? 'secondary',
                        'size'     => 'md',
                        'reversed' => false,
                        'label'    => $button_2_text,
                        'type'     => 'button',
                        'a_tag'    => true,
                        'link'     => $button_2_url,
                        'link_target' => $button_2_new_tab ? '_blank' : '_self',
                        'atts'  => [
                            'style="margin-left:20px;"',
                            'x-on:click="doWpAction(\'orgss_active_membership_alert_button_2_clicked' . $action_suffix . '\');"',
                        ],
                        'classes' => [
                            'component-org-search-select__active-membership-alert-button',
                            'items-center',
                            'justify-center',
                            'w-5/12',
                        ],
                    ]);
                }
            }
        };
        ?>

        <!-- Base/Default Buttons (shown when no seat-specific message is configured or as fallback) -->
        <div x-show="activeMembershipSeatMessageState === 'base'" x-cloak class="flex w-full justify-evenly">
          <?php
          $render_alert_buttons(
              $active_membership_alert_button_1_text,
              $active_membership_alert_button_1_url,
              $active_membership_alert_button_1_style,
              $active_membership_alert_button_1_new_tab,
              $active_membership_alert_button_2_text,
              $active_membership_alert_button_2_url,
              $active_membership_alert_button_2_style,
              $active_membership_alert_button_2_new_tab,
              ''
          );
          ?>
        </div>

        <!-- Seats Available Buttons -->
        <div x-show="activeMembershipSeatMessageState === 'available'" x-cloak class="flex w-full justify-evenly">
          <?php
          $render_alert_buttons(
              $active_membership_seat_available_alert_button_1_text,
              $active_membership_seat_available_alert_button_1_url,
              $active_membership_seat_available_alert_button_1_style,
              $active_membership_seat_available_alert_button_1_new_tab,
              $active_membership_seat_available_alert_button_2_text,
              $active_membership_seat_available_alert_button_2_url,
              $active_membership_seat_available_alert_button_2_style,
              $active_membership_seat_available_alert_button_2_new_tab,
              '_seats_available'
          );
          ?>
        </div>

        <!-- No Seats Available Buttons -->
        <div x-show="activeMembershipSeatMessageState === 'unavailable'" x-cloak class="flex w-full justify-evenly">
          <?php
          $render_alert_buttons(
              $active_membership_seat_unavailable_alert_button_1_text,
              $active_membership_seat_unavailable_alert_button_1_url,
              $active_membership_seat_unavailable_alert_button_1_style,
              $active_membership_seat_unavailable_alert_button_1_new_tab,
              $active_membership_seat_unavailable_alert_button_2_text,
              $active_membership_seat_unavailable_alert_button_2_url,
              $active_membership_seat_unavailable_alert_button_2_style,
              $active_membership_seat_unavailable_alert_button_2_new_tab,
              '_seats_unavailable'
          );
          ?>
        </div>
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
        <?php
$buttonClasses = ['component-org-search-select__search-button', 'w-full', 'sm:w-auto'];

if (!$is_wicket_theme) {
    $buttonClasses[] = 'button--reversed';
} ?>
        <?php get_component('button', [
    'variant'  => 'primary',
    'label'    => __('Search', 'wicket'),
    'type'     => 'button',
    'classes'  => $buttonClasses,
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
      x-show="(firstSearchSubmitted || isLoading) && !justCreatedNewOrg">
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
            <div>
              <div class="component-org-search-select__matching-org-title mb-1 <?php echo defined('WICKET_WP_THEME_V2') ? '' : 'font-bold' ?>"
                x-text="result.name"></div>
              <div class="component-org-search-select__matching-org-subtitle"
                <?php if ($display_org_fields === 'name_location') : ?>
                  x-text="`${result.city ? result.city + (result.state_name ? ', ' : '') : ''}${result.state_name ? result.state_name : ''}${result.country_code ? (result.city || result.state_name ? ', ' : '') + result.country_code : ''}`"
                <?php elseif ($display_org_fields === 'name_address') : ?>
                  x-text="`${result.address1 ? result.address1 + (result.city ? ', ' : '') : ''}${result.city ? result.city + (result.state_name ? ', ' : '') : ''}${result.state_name ? result.state_name + (result.zip_code ? ' ' : '') : ''}${result.zip_code ? result.zip_code : ''}${result.country_code ? (result.address1 || result.city || result.state_name || result.zip_code ? ', ' : '') + result.country_code : ''}`"
                <?php endif; ?>
                ></div>
            </div>
            <?php if ($display_org_type === true) : ?>
              <div class="component-org-search-select__matching-org-type" x-text="result.type_name"></div>
            <?php endif; ?>
            <div class="component-org-search-select__matching-org-action" >
              <?php get_component('button', [
          'variant'  => 'secondary',
          'reversed' => false,
          'label'    => __('Select', 'wicket'),
          'type'     => 'button',
          'classes'  => ['component-org-search-select__select-result-button'],
          'atts'     => [
              'x-on:click.prevent="selectOrgFromSearchResult(result, $event)"',
              'x-bind:class="{
                    \'orgss_disabled_button_hollow\': isOrgAlreadyAConnection($data.result.id)
                      || (disableSelectingOrgsWithActiveMembership && result.active_membership)
                  }"',
              'x-bind:disabled="isOrgAlreadyAConnection($data.result.id)
                || (disableSelectingOrgsWithActiveMembership && result.active_membership)"',
              'x-bind:aria-disabled="(isOrgAlreadyAConnection($data.result.id)
                || (disableSelectingOrgsWithActiveMembership && result.active_membership)) ? \'true\' : \'false\'"',
          ],
      ]); ?>
            </div>
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
          'x-on:click.prevent="selectedOrgUuid = \'\'; hideGfNextButton(); $dispatch(\'wicket:org_search_select_cleared\', { orgSearchSelectKey: \'' . $key . '\' });"',
      ],
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
                this.orgParentName = connection.org_parent_name;
              }
            }
          }
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
            <div class="component-org-search-select__org-subtitle"
              <?php if ($display_org_fields === 'name_location') : ?>
                x-text="`${connection.city ? connection.city + (connection.state_name ? ', ' : '') : ''}${connection.state_name ? connection.state_name : ''}${connection.country_code ? (connection.city || connection.state_name ? ', ' : '') + connection.country_code : ''}`"
              <?php elseif ($display_org_fields === 'name_address') : ?>
                x-text="`${connection.address1 ? connection.address1 + (connection.city ? ', ' : '') : ''}${connection.city ? connection.city + (connection.state_name ? ', ' : '') : ''}${connection.state_name ? connection.state_name + (connection.zip_code ? ' ' : '') : ''}${connection.zip_code ? connection.zip_code : ''}${connection.country_code ? (connection.address1 || connection.city || connection.state_name || connection.zip_code ? ', ' : '') + connection.country_code : ''}`"
              <?php endif; ?>
              >
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
          ],
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
              'x-on:click.prevent="selectOrgAndCreateRelationship($data.connection.org_id, $event, connection.active_membership, true, connection.active_membership_seat_summary)"',
              'x-bind:class="{
                    \'orgss_disabled_button\': connection.active_membership && disableSelectingOrgsWithActiveMembership
                  }"',
              'x-bind:disabled="connection.active_membership && disableSelectingOrgsWithActiveMembership"',
              'x-bind:aria-disabled="(connection.active_membership && disableSelectingOrgsWithActiveMembership) ? \'true\' : \'false\'"',
              'x-show="!hideSelectButtons && connection.org_id !== selectedOrgUuid"',
          ],
      ]); ?>
            </template>
            <template x-if="connection.org_id === selectedOrgUuid">
              <?php get_component('button', [
          'variant'  => 'secondary',
          'reversed' => false,
          'label'    => '✓ ' . __('Selected', 'wicket'),
          'type'     => 'button',
          'classes'  => ['component-org-search-select__select-button', 'whitespace-nowrap', 'orgss_disabled_button'],
          'atts'     => [
              'x-show="!hideSelectButtons"',
          ],
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
            'x-show="!hideRemoveButtons"',
        ],
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
      <div
        class="component-org-search-select__create-org-name-wrapper flex flex-col mr-2 w-5/12">
        <label
          class="component-org-search-select__create-org-label"><?php _e('Name of the', 'wicket') ?>
          <?php echo $orgTermSingularCap; ?>*</label>
        <input x-model="newOrgNameBox" @keyup.enter.prevent.stop="handleOrgCreate($event)" type="text"
          name="company-name" class="component-org-search-select__create-org-name-input w-full" />
      </div>
      <div
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
  function wicketOrgssDebug() {}
  wicketOrgssDebug.enabled = !!window.WICKET_ORGSS_DEBUG;
  wicketOrgssDebug.setEnabled = function(value) {
    wicketOrgssDebug.enabled = !!value;
  };
  wicketOrgssDebug.isEnabled = function() {
    return wicketOrgssDebug.enabled;
  };
  wicketOrgssDebug.log = function() {
    if (!wicketOrgssDebug.enabled || !window.console || !window.console.log) {
      return;
    }
    window.console.log.apply(window.console, arguments);
  };
  wicketOrgssDebug.warn = function() {
    if (!wicketOrgssDebug.enabled || !window.console || !window.console.warn) {
      return;
    }
    window.console.warn.apply(window.console, arguments);
  };

  document.addEventListener('alpine:init', () => {
    Alpine.data('orgss_<?php echo $key; ?>', () => ({
      searchQuery: '',
      selectedOrg: null,
      lang: '<?php echo $lang; ?>',
      isLoading: false,
      showingRemoveConfirmation: false,
      removeConfirmationIsEnabled: <?php echo $display_removal_alert_message ? 'true' : 'false'; ?>,
      connectionIdSelectedForRemoval: '',
      removeConfirmationOrgName: '',
      removeButtonLabel: '',
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
      activeMembershipAlertSeatSummary: null,
      defaultActiveMembershipAlertTitle: <?php echo json_encode($active_membership_alert_title); ?>,
      defaultActiveMembershipAlertBody: <?php echo json_encode($active_membership_alert_body); ?>,
      activeMembershipAlertTitle: <?php echo json_encode($active_membership_alert_title); ?>,
      activeMembershipAlertBody: <?php echo json_encode($active_membership_alert_body); ?>,
      activeMembershipSeatMessagingEnabled: <?php echo ($active_membership_seat_messaging_enabled || $seat_available_message_configured || $seat_unavailable_message_configured) ? 'true' : 'false'; ?>,
      seatMessageHasAvailableSeats: <?php echo $seat_available_message_configured ? 'true' : 'false'; ?>,
      seatMessageHasNoSeats: <?php echo $seat_unavailable_message_configured ? 'true' : 'false'; ?>,
      seatAvailableMessageTitle: <?php echo json_encode($active_membership_seat_available_alert_title); ?>,
      seatAvailableMessageBody: <?php echo json_encode($active_membership_seat_available_alert_body); ?>,
      seatUnavailableMessageTitle: <?php echo json_encode($active_membership_seat_unavailable_alert_title); ?>,
      seatUnavailableMessageBody: <?php echo json_encode($active_membership_seat_unavailable_alert_body); ?>,
      activeMembershipNotifyEnabled: <?php echo $active_membership_notify_enabled ? 'true' : 'false'; ?>,
      activeMembershipNotifyEmailSubject: <?php echo json_encode($active_membership_notify_email_subject); ?>,
      activeMembershipNotifyEmailBody: <?php echo json_encode($active_membership_notify_email_body); ?>,
      notifyOwnerEnabled: <?php echo $active_membership_seat_unavailable_notify_enabled ? 'true' : 'false'; ?>,
      notifyOwnerEmailSubject: <?php echo json_encode($active_membership_seat_unavailable_notify_email_subject); ?>,
      notifyOwnerEmailBody: <?php echo json_encode($active_membership_seat_unavailable_notify_email_body); ?>,
      notifyOwnerMessage: '',
      notifyOwnerStatus: '',
      notifyOwnerIsLoading: false,
      notifyOwnerSuccessMessage: <?php echo json_encode(__('Thanks, the organization owner has been notified.', 'wicket')); ?>,
      notifyOwnerThrottleMessage: <?php echo json_encode(__('The organization owner was already notified recently. Please wait before trying again.', 'wicket')); ?>,
      notifyOwnerErrorMessage: <?php echo json_encode(__('We could not notify the organization owner. Please try again later.', 'wicket')); ?>,
      activeMembershipSeatMessageState: 'base',
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
      displayOrgFields: '<?php echo esc_js($display_org_fields); ?>',
      relationshipTypeFilter: '<?php echo $relationshipTypeFilter; ?>',
      removalErrorVisible: false,
      removalErrorMessage: '',
      removalErrorHint: '',
      removalErrorDetail: '',

      // Encapsulate filtering logic to avoid complex inline expressions
      matchesFilter(connection) {
        const rm = this.relationshipMode;
        const sType = (this.searchOrgType || '').toLowerCase();
        const sTypes = sType.split(',').map(type => type.trim()).filter(type => type !== '');
        const filt = (this.relationshipTypeFilter || '').toLowerCase();

        if (connection.connection_type != rm) return false;
        const connOrgType = (connection.org_type || '').toLowerCase();
        if (!(sTypes.includes(connOrgType) || sTypes.length === 0)) return false;
        if (!connection.active_connection) {
          if (this.selectedOrgUuid && connection.org_id === this.selectedOrgUuid) {
            if (!this.enableRelationshipFiltering || filt === '') return true;
            const relType = ((connection.relationship_type || '') + '').toLowerCase();
            return relType === filt;
          }
          return false;
        }

        // If filtering disabled or empty filter, pass through
        if (!this.enableRelationshipFiltering || filt === '') return true;

        // Strict: only match on definitive relationship_type
        const relType = ((connection.relationship_type || '') + '').toLowerCase();
        return relType === filt;
      },

      init() {
        wicketOrgssDebug.log('ORGSS: init', {
          key: '<?php echo $key; ?>',
          searchType: this.searchType,
          currentConnections: Array.isArray(this.currentConnections) ? this.currentConnections.length : 0,
          displayOrgFields: this.displayOrgFields,
          searchOrgType: this.searchOrgType,
        });
        this.clearRemovalError();

        // Normalize optional filter for backward compatibility
        if (typeof this.relationshipTypeFilter !== 'string') {
          this.relationshipTypeFilter = '';
        }
        this.relationshipTypeFilter = this.relationshipTypeFilter.trim();

        // Determine if the active membership modal has enough data to use
        // Modal is available if either base message OR seat-specific messages are configured
        const hasBaseMessage = this.activeMembershipAlertTitle.length > 0 || this.activeMembershipAlertBody.length > 0;
        const hasSeatMessages = this.activeMembershipSeatMessagingEnabled && (this.seatMessageHasAvailableSeats || this.seatMessageHasNoSeats);
        if (this.disableSelectingOrgsWithActiveMembership && (hasBaseMessage || hasSeatMessages)) {
          this.activeMembershipAlertAvailable = true;
        }

        // Override available org types when creating a new org, if specified
        if (this.newOrgTypeOverride.length > 0) {
          let newOrgTypes = this.newOrgTypeOverride.split(',').map(type => type.trim());

          // We should have only specified slugs from newOrgTypes, and exclude the rest.
          this.availableOrgTypes.data = this.availableOrgTypes.data.filter(orgType =>
            newOrgTypes.includes(orgType.attributes.slug)
          );
        }

        this.$watch('searchBox', (value) => {
          if (value === '') {
            this.results = [];
            this.firstSearchSubmitted = false;
            this.showSearchMessage = false;
            wicketOrgssDebug.log('ORGSS: cleared search');
          }
        });
      },
      prepareSeatBasedActiveMembershipMessage(seatSummary = null) {
        // Reset to base state initially
        this.activeMembershipSeatMessageState = 'base';
        this.activeMembershipAlertTitle = this.defaultActiveMembershipAlertTitle;
        this.activeMembershipAlertBody = this.defaultActiveMembershipAlertBody;

        // If seat messaging not enabled, use base message
        if (!this.activeMembershipSeatMessagingEnabled) {
          return;
        }

        // If no seat data provided, use base message
        if (!seatSummary || typeof seatSummary !== 'object') {
          wicketOrgssDebug.warn('ORGSS: Seat summary data is missing or invalid', seatSummary);
          return;
        }

        const hasAvailableSeats = seatSummary.has_available_seats;
        const isUnlimited = seatSummary.unlimited;
        const hasActiveMembership = seatSummary.has_active_membership;

        // Only apply seat logic if org has an active membership
        if (!hasActiveMembership) {
          return;
        }

        // Check for seats available (true or unlimited)
        if (hasAvailableSeats === true || isUnlimited === true) {
          if (this.seatMessageHasAvailableSeats) {
            this.activeMembershipAlertTitle = <?php echo json_encode($active_membership_seat_available_alert_title); ?>;
            this.activeMembershipAlertBody = <?php echo json_encode($active_membership_seat_available_alert_body); ?>;
            this.activeMembershipSeatMessageState = 'available';
            wicketOrgssDebug.log('ORGSS: Showing seats AVAILABLE message');
            return;
          }
        }

        // Check for no seats available (explicitly false)
        if (hasAvailableSeats === false) {
          if (this.seatMessageHasNoSeats) {
            this.activeMembershipAlertTitle = <?php echo json_encode($active_membership_seat_unavailable_alert_title); ?>;
            this.activeMembershipAlertBody = <?php echo json_encode($active_membership_seat_unavailable_alert_body); ?>;
            this.activeMembershipSeatMessageState = 'unavailable';
            wicketOrgssDebug.log('ORGSS: Showing NO SEATS message');
            return;
          }
        }

        // Fallback to base message if seat data is null/undefined or messages not configured
        wicketOrgssDebug.warn('ORGSS: Falling back to base message. hasAvailableSeats:', hasAvailableSeats, 'seatMessageFlags:', {
          hasAvailableSeats: this.seatMessageHasAvailableSeats,
          hasNoSeats: this.seatMessageHasNoSeats
        });
      },
      handleSearch(e = null) {
        if (e) {
          e.preventDefault();
        }

        wicketOrgssDebug.log('ORGSS: handleSearch', {
          searchBox: this.searchBox,
          searchType: this.searchType,
          firstSearchSubmitted: this.firstSearchSubmitted,
        });

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
            alert('<?php _e('Please enter an organization name.', 'wicket') ?>');
            return;
          }

          let newOrgType = this.newOrgTypeSelect;

          if (!newOrgType) {
            alert('<?php _e('Please select an organization type.', 'wicket') ?>');
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

        const includeLocation = this.displayOrgFields === 'name_location' || this.displayOrgFields === 'name_address';
        const includeMembershipSummary = this.disableSelectingOrgsWithActiveMembership;
        const orgTypes = this.searchOrgType.split(',').map(type => type.trim());
        let data = {};
        if (this.searchOrgType.length > 0) {
          data = {
            "searchTerm": searchTerm,
            "orgType": orgTypes,
            "includeMembershipSummary": includeMembershipSummary,
            "includeLocation": includeLocation,
          };
        } else {
          data = {
            "searchTerm": searchTerm,
            "includeMembershipSummary": includeMembershipSummary,
            "includeLocation": includeLocation,
          };
        }

        wicketOrgssDebug.log('ORGSS: searchOrgs request', {
          searchTerm,
          orgTypes,
          includeMembershipSummary,
          includeLocation,
        });

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
            const resultCount = Array.isArray(data.data) ? data.data.length : 0;
            const resultIds = Array.isArray(data.data) ? data.data.map(item => item.id) : [];
            wicketOrgssDebug.log('ORGSS: searchOrgs response', {
              success: !!data.success,
              resultCount,
              resultIds,
              data,
            });
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
      async fetchSeatSummary(orgUuid) {
        this.isLoading = true;
        let seatSummary = null;
        let hasActiveMembership = false;

        try {
          let results = await fetch(this.apiUrl + 'orgss-seat-summary', {
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
              body: JSON.stringify({
                "orgUuid": orgUuid
              }),
            }).then(response => response.json())
            .then(data => data);

          if (results && results.success && results.data) {
            seatSummary = results.data.seat_summary || null;
            hasActiveMembership = !!results.data.active_membership;
          }
        } catch (error) {
          wicketOrgssDebug.warn('ORGSS: seat summary fetch failed', error);
          seatSummary = null;
          hasActiveMembership = false;
        } finally {
          this.isLoading = false;
        }

        return {
          seatSummary,
          hasActiveMembership,
        };
      },
      async selectOrgFromSearchResult(result, event = null) {
        const orgUuid = result.id;

        if (!orgUuid) {
          return;
        }

        if (this.disableSelectingOrgsWithActiveMembership && result.active_membership) {
          return;
        }

        if (this.disableSelectingOrgsWithActiveMembership) {
          const seatData = await this.fetchSeatSummary(orgUuid);
          if (seatData.hasActiveMembership) {
            return;
          }
        }

        const created = await this.createOrUpdateRelationship(
          this.currentPersonUuid,
          orgUuid,
          this.relationshipMode,
          this.relationshipTypeUponOrgCreation
        );

        if (created && created.success) {
          this.selectOrg(orgUuid, event);
          this.searchBox = '';
          const existingConnection = this.getOrgFromConnectionsByUuid(orgUuid);
          if (!existingConnection || !existingConnection.org_id) {
            const fallbackPayload = {
              connection_id: '',
              connection_type: this.relationshipMode,
              relationship_type: this.relationshipTypeUponOrgCreation,
              starts_at: '',
              ends_at: '',
              tags: [],
              active_membership: false,
              active_membership_seat_summary: null,
              active_connection: true,
              org_id: orgUuid,
              org_name: result.name || '',
              org_description: '',
              org_type_pretty: '',
              org_type: result.type || '',
              org_type_slug: result.type || '',
              org_type_name: result.type_name || '',
              org_status: '',
              org_parent_id: '',
              org_parent_name: '',
              person_id: this.currentPersonUuid,
              address1: result.address1 || '',
              city: result.city || '',
              zip_code: result.zip_code || '',
              state_name: result.state_name || '',
              country_code: result.country_code || '',
            };
            this.addConnection(fallbackPayload);
          }
          return;
        }

        this.setSearchMessage('<?php echo esc_js(__('There was an error creating the connection. Please try again.', 'wicket')); ?>');
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
      showGfNextButton() {
        const formId = <?php echo (int) $formId; ?>;
        const form = formId ? document.getElementById('gform_' + formId) : null;
        if (!form) {
          return;
        }

        const revealElement = (el) => {
          if (!el) {
            return;
          }
          el.style.setProperty('display', 'block', 'important');
          el.style.setProperty('opacity', '1', 'important');
          el.style.setProperty('max-height', 'none', 'important');
          el.style.setProperty('height', 'auto', 'important');
          el.style.setProperty('visibility', 'visible', 'important');
          el.hidden = false;
          el.classList.remove('gform_hidden', 'hidden', 'gf_invisible');
          el.removeAttribute('aria-hidden');
        };

        const selectors = [
          '.gform_page_footer',
          '.gform-page-footer',
        ];
        const footer = form.querySelector(selectors.join(','));
        revealElement(footer);

        const buttonSelectors = [
          '.gform_next_button',
          '.gform_button',
          '.gform_submit_button'
        ];
        const buttons = form.querySelectorAll(buttonSelectors.join(','));
        buttons.forEach((button) => revealElement(button));

        wicketOrgssDebug.log('ORGSS: revealed GF next/submit buttons', {
          formId,
          footerFound: !!footer,
          buttonCount: buttons.length
        });
      },
      hideGfNextButton() {
        const formId = <?php echo (int) $formId; ?>;
        const form = formId ? document.getElementById('gform_' + formId) : null;
        if (!form) {
          return;
        }

        const hideElement = (el) => {
          if (!el) {
            return;
          }
          el.style.setProperty('display', 'none', 'important');
          el.style.setProperty('opacity', '0', 'important');
          el.style.setProperty('max-height', '0px', 'important');
          el.style.setProperty('height', '0px', 'important');
          el.style.setProperty('visibility', 'hidden', 'important');
          el.hidden = true;
          el.classList.add('gform_hidden');
          el.setAttribute('aria-hidden', 'true');
        };

        const selectors = [
          '.gform_page_footer',
          '.gform-page-footer',
        ];
        const footer = form.querySelector(selectors.join(','));
        hideElement(footer);

        const buttonSelectors = [
          '.gform_next_button',
          '.gform_button',
          '.gform_submit_button'
        ];
        const buttons = form.querySelectorAll(buttonSelectors.join(','));
        buttons.forEach((button) => hideElement(button));

        wicketOrgssDebug.log('ORGSS: hid GF next/submit buttons', {
          formId,
          footerFound: !!footer,
          buttonCount: buttons.length
        });
      },
      selectOrgAndCreateRelationship(orgUuid, event = null, existingActiveMembership = false,
        skipCreateRelationship = false, seatSummary = null) {
        // TODO: Handle when a Group is selected instead of an org

        if (existingActiveMembership && this.disableSelectingOrgsWithActiveMembership) {
          return;
        }

        // Reset proceed flag when switching orgs so each selection is evaluated independently.
        if (this.activeMembershipAlertProceedChosen && this.activeMembershipAlertOrgUuid && this.activeMembershipAlertOrgUuid !== orgUuid) {
          this.activeMembershipAlertProceedChosen = false;
          this.activeMembershipAlertSeatSummary = null;
        }

        // ------------------
        // Active Membership modal alert
        // ------------------

        // Only show if the 'Disable Org Select with Active Membership' feature is enabled, sufficient modal data
        // was populated, and the user hasn't already chosen to proceed with the usual actions
        if (existingActiveMembership && this.disableSelectingOrgsWithActiveMembership && this
          .activeMembershipAlertAvailable && !this.activeMembershipAlertProceedChosen) {
          if (seatSummary && typeof seatSummary === 'object') {
            if (typeof seatSummary.has_active_membership !== 'boolean') {
              seatSummary.has_active_membership = existingActiveMembership;
            }

            const assigned = Number(seatSummary.assigned);
            const max = Number(seatSummary.max);
            const hasCounts = Number.isFinite(assigned) && Number.isFinite(max) && max > 0;
            if (hasCounts) {
              seatSummary.has_available_seats = assigned < max;
              seatSummary.unlimited = false;
            } else if (seatSummary.has_available_seats === null || typeof seatSummary.has_available_seats === 'undefined') {
              if (Number.isFinite(assigned) && Number.isFinite(max)) {
                seatSummary.has_available_seats = assigned < max;
              }
            }

            if (seatSummary.unlimited === true && Number.isFinite(assigned)) {
              seatSummary.has_available_seats = true;
            }
          }
          wicketOrgssDebug.log('ORGSS: Seat summary values', {
            assigned: seatSummary?.assigned,
            max: seatSummary?.max,
            hasAvailableSeats: seatSummary?.has_available_seats,
            unlimited: seatSummary?.unlimited,
            hasActiveMembership: seatSummary?.has_active_membership,
          });
          wicketOrgssDebug.log('ORGSS: Triggering modal for org with active membership', {
            orgUuid,
            seatSummary,
            seatMessagingEnabled: this.activeMembershipSeatMessagingEnabled,
            seatMessageFlags: {
              hasAvailableSeats: this.seatMessageHasAvailableSeats,
              hasNoSeats: this.seatMessageHasNoSeats
            }
          });
          this.notifyOwnerMessage = '';
          this.notifyOwnerStatus = '';
          this.prepareSeatBasedActiveMembershipMessage(seatSummary);
          // Display the modal
          this.showingActiveMembershipAlert = true;

          // Stash data away so we can resume this operation later
          this.activeMembershipAlertOrgUuid = orgUuid;
          this.activeMembershipAlertSeatSummary = seatSummary;
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
          this.activeMembershipAlertSeatSummary = null;

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
        this.showGfNextButton();
        this.$nextTick(() => {
          window.requestAnimationFrame(() => this.showGfNextButton());
        });

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

        wicketOrgssDebug.log('ORGSS: searchGroups request', {
          searchTerm,
        });

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
            const resultCount = Array.isArray(data.data) ? data.data.length : 0;
            const resultIds = Array.isArray(data.data) ? data.data.map(item => item.id) : [];
            wicketOrgssDebug.log('ORGSS: searchGroups response', {
              success: !!data.success,
              resultCount,
              resultIds,
              data,
            });
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
            return data;
          });

        return results;
      },

      async terminateRelationship(connectionId = null) {
        this.clearRemovalError();

        // If we're using the remove confirmation feature
        if (this.removeConfirmationIsEnabled) {
          // If we're already showing the remove confirmation,
          // proceed as usual as they've confirmed the action
          if (this.showingRemoveConfirmation) {
            // Just close the modal
            this.showingRemoveConfirmation = false;
          } else {
            this.connectionIdSelectedForRemoval = connectionId;
            // Find the connection to get the org name
            this.currentConnections.forEach((connection) => {
              if (connection.connection_id == connectionId) {
                this.removeConfirmationOrgName = connection.org_name;
                this.removeButtonLabel = '<?php _e('Remove', 'wicket') ?> ' + connection.org_name;
              }
            });
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
              const serverMessage = typeof data.data === 'string' ? data.data : '';
              this.showRemovalError(connectionId, serverMessage);
            } else {
              if (data.success) {
                this.clearRemovalError();
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
        const normalized = {
          ...payload,
          active_connection: payload.active_connection ?? true,
        };
        if (normalized.active_connection === false && !normalized.ends_at) {
          normalized.active_connection = true;
        }
        if (normalized.active_connection === false && normalized.ends_at) {
          const endsAtTime = Date.parse(normalized.ends_at);
          if (Number.isFinite(endsAtTime) && endsAtTime > Date.now()) {
            normalized.active_connection = true;
          }
        }
        const isDuplicate = this.currentConnections.some(
          conn => conn.org_id === normalized.org_id || conn.connection_id === normalized.connection_id
        );
        if (!isDuplicate) {
          this.currentConnections.push(normalized);
        } else {
          wicketOrgssDebug.warn('ORGSS: duplicate connection skipped', {
            incoming: normalized,
            currentConnections: this.currentConnections.length
          });
        }
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
          this.activeMembershipAlertProceedChosen = false;
          this.activeMembershipAlertOrgUuid = '';
          this.activeMembershipAlertSeatSummary = null;

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
      getConnectionById(connectionId) {
        let foundConnection = null;
        this.currentConnections.forEach((connection) => {
          if (connection.connection_id == connectionId) {
            foundConnection = connection;
          }
        });
        return foundConnection;
      },
      showRemovalError(connectionId = '', serverMessage = '') {
        const connection = this.getConnectionById(connectionId) || {};
        const orgName = connection.org_name || '<?php echo esc_js(__('this organization', 'wicket')); ?>';
        const connectionType = connection.relationship_type || '<?php echo esc_js(__('Unknown relationship', 'wicket')); ?>';
        const configuredType = this.relationshipTypeUponOrgCreation || '<?php echo esc_js(__('Not specified', 'wicket')); ?>';

        let friendlyMessage = '<?php echo esc_js(__('We could not remove %s.', 'wicket')); ?>'.replace('%s', orgName);
        let friendlyHint = '<?php echo esc_js(__('Review the details below or contact support if this continues.', 'wicket')); ?>';
        const normalized = (serverMessage || '').toLowerCase();

        if (normalized.indexOf('relationships do not match') !== -1) {
          friendlyHint = '<?php echo esc_js(__('This tool is limited to removing "%1$s" links, but this record is "%2$s".', 'wicket')); ?>'
            .replace('%1$s', configuredType)
            .replace('%2$s', connectionType);
        } else if (normalized.indexOf('wrong setting the end date of the connection') !== -1) {
          friendlyHint = "<?php echo esc_js(__('The MDP blocked ending this connection on the same day it was created. Try again tomorrow or contact support.', 'wicket')); ?>";
        } else if (serverMessage) {
          friendlyHint = serverMessage;
        }

        const detailLines = [];
        if (connectionId) {
          detailLines.push('Connection ID: ' + connectionId);
        }
        detailLines.push('Configured relationship type: ' + configuredType);
        detailLines.push('Connection relationship type: ' + connectionType);
        if (serverMessage) {
          detailLines.push('Server message: ' + serverMessage);
        }

        this.removalErrorMessage = friendlyMessage;
        this.removalErrorHint = friendlyHint;
        this.removalErrorDetail = detailLines.join('\n');
        this.removalErrorVisible = true;
      },
      clearRemovalError() {
        this.removalErrorVisible = false;
        this.removalErrorMessage = '';
        this.removalErrorHint = '';
        this.removalErrorDetail = '';
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
      maybeNotifyBaseProceed() {
        if (!this.activeMembershipNotifyEnabled) {
          return;
        }
        if (this.activeMembershipSeatMessageState !== 'base') {
          return;
        }

        this.notifyOwnerRosterAdded();
      },
      async notifyOwnerRosterAdded() {
        const orgUuid = this.activeMembershipAlertOrgUuid || this.selectedOrgUuid;
        if (!orgUuid) {
          return;
        }

        let data = {
          "orgUuid": orgUuid,
          "emailSubject": this.activeMembershipNotifyEmailSubject || '',
          "emailBody": this.activeMembershipNotifyEmailBody || '',
        };

        let results = await fetch(this.apiUrl + 'orgss-notify-owner-roster-added', {
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
          .then(() => {})
          .catch(() => {});

        return results;
      },
      async notifyOrgOwner() {
        if (this.notifyOwnerIsLoading) {
          return;
        }

        const orgUuid = this.activeMembershipAlertOrgUuid || this.selectedOrgUuid;
        if (!orgUuid || !this.notifyOwnerEnabled) {
          this.notifyOwnerMessage = this.notifyOwnerErrorMessage;
          this.notifyOwnerStatus = 'error';
          return;
        }

        this.notifyOwnerIsLoading = true;
        this.notifyOwnerMessage = '';
        this.notifyOwnerStatus = '';

        let data = {
          "orgUuid": orgUuid,
          "emailSubject": this.notifyOwnerEmailSubject || '',
          "emailBody": this.notifyOwnerEmailBody || '',
        };

        let results = await fetch(this.apiUrl + 'orgss-notify-owner', {
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
              const message = (data.data && data.data.message) ? data.data.message : this.notifyOwnerErrorMessage;
              this.notifyOwnerMessage = message;
              this.notifyOwnerStatus = 'error';
            } else {
              const message = (data.data && data.data.message) ? data.data.message : this.notifyOwnerSuccessMessage;
              this.notifyOwnerMessage = message;
              this.notifyOwnerStatus = 'success';
            }
          }).catch(() => {
            this.notifyOwnerMessage = this.notifyOwnerErrorMessage;
            this.notifyOwnerStatus = 'error';
          });

        this.notifyOwnerIsLoading = false;

        return results;
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
