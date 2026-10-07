<?php

/**
 * Admin Settings file for Wicket Base Plugin.
 *
 * @version  1.0.0
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

use Jeffreyvr\WPSettings\WPSettings;

if (!class_exists('Wicket_Settings')) {

    /**
     * Wicket Settings class.
     */
    class Wicket_Settings
    {
        /**
         * Constructor of class.
         */
        public function __construct()
        {

            // Add custom option types - must include before registering settings
            include_once WICKET_PLUGIN_DIR . 'includes/admin/settings/options/wicket-membership-table.php';
            include_once WICKET_PLUGIN_DIR . 'includes/admin/settings/options/wicket-api-check.php';
            include_once WICKET_PLUGIN_DIR . 'includes/admin/settings/options/wicket-plugin-check.php';

            // Add Settings page options
            add_action('admin_menu', [$this, 'register_wicket_settings']);

        }

        /**
         * Get All Pages - for settings select fields.
         */
        public function get_all_pages()
        {
            // Query for listing all pages in the select box loop
            $my_wp_query = new WP_Query();
            $all_wp_pages = $my_wp_query->query([
                'post_type' => 'page',
                'posts_per_page' => -1,
            ]);
            foreach ($all_wp_pages as $value) {
                $page = get_page($value);
                $title = $page->post_title . ' (ID: ' . $page->ID . ')';
                $id = $page->ID;

                $page_list[$id] = $title;
            }

            return $page_list;
        }

        /**
         * Get Product Categories - for settings select fields.
         */
        public function get_product_categories()
        {
            if ($taxonomy_exist = taxonomy_exists('product_cat')) {

                $all_categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
                foreach ($all_categories as $cat) {
                    $title = $cat->name . ' (ID: ' . $cat->term_id . ')';
                    $id = $cat->term_id;

                    $category_list[$id] = $cat->name;
                }

                return $category_list;

            }
        }

        /**
         * Get person to organization relationship (connection) types - for settings select fields.
         */
        public function get_person_to_organizations_connection_types()
        {

            $resource_types = wicket_get_resource_types('relationships-person-to-organization');
            $person_to_organizations_connection_types = [];
            foreach ($resource_types['data'] as $key => $value) {
                $person_to_organizations_connection_types[$value['attributes']['slug']] = $value['attributes']['name'];
            }

            return $person_to_organizations_connection_types;
        }

        /**
         * Create Settings page tabs, sections and options.
         */
        public function register_wicket_settings()
        {

            // Create Settings page with same slug as menu
            $settings = new WPSettings(_x('Wicket Settings', 'label', 'wicket-base'), ('wicket-settings'));
            // Keep parent slug: makes WPSettings register as a child of the 'Wicket'
            // top-level menu (Wicket_Admin::wicket_admin_menu, admin_menu p10).
            // Relabel the submenu 'Settings' instead of the default 'Wicket Settings',
            // so it no longer duplicates WP's auto-inserted 'Wicket' parent link
            // (both pointed at the same wicket-settings slug, producing two entries).
            $settings->set_menu_parent_slug('wicket-settings');
            $settings->set_menu_title(_x('Settings', 'label', 'wicket-base'));

            /*
             * Priority-based tab registration.
             * External plugins can filter 'wicket_settings_tabs' to insert tabs at specific positions.
             *
             * Default priorities:
             *   10 = General
             *   20 = Environments
             *   30 = Memberships
             *   40 = Touchpoints
             *   50 = Integrations
             *
             * Example: To insert a tab between Touchpoints and Integrations, use priority 45.
             */
            $tabs_config = apply_filters('wicket_settings_tabs', [
                10 => [
                    'key' => 'general',
                    'label' => _x('General', 'label', 'wicket-base'),
                    'callback' => [$this, 'register_general_tab'],
                ],
                20 => [
                    'key' => 'environments',
                    'label' => _x('Environments', 'label', 'wicket-base'),
                    'callback' => [$this, 'register_environments_tab'],
                ],
                30 => [
                    'key' => 'memberships',
                    'label' => _x('Memberships', 'label', 'wicket-base'),
                    'callback' => [$this, 'register_memberships_tab'],
                ],
                40 => [
                    'key' => 'touchpoints',
                    'label' => _x('Touchpoints', 'label', 'wicket-base'),
                    'callback' => [$this, 'register_touchpoints_tab'],
                ],
                50 => [
                    'key' => 'integrations',
                    'label' => _x('Integrations', 'label', 'wicket-base'),
                    'callback' => [$this, 'register_integrations_tab'],
                ],
            ]);

            // Sort by priority
            ksort($tabs_config);

            // Track tabs for backward-compatible filters
            $tabs = [];

            // Register tabs in priority order
            foreach ($tabs_config as $priority => $config) {
                $tab = $settings->add_tab($config['label']);

                // Call the registration callback if provided
                if (!empty($config['callback']) && is_callable($config['callback'])) {
                    call_user_func($config['callback'], $tab);
                }

                $tabs[$config['key']] = $tab;
            }

            /*
             * Deprecated filters for backward compatibility.
             *
             * @deprecated 1.1.0 Use 'wicket_settings_tabs' filter with priority-based registration.
             */
            if (isset($tabs['general'])) {
                $tabs['general'] = apply_filters_deprecated(
                    'wicket_settings_tab_gen',
                    [$tabs['general']],
                    '1.1.0',
                    'wicket_settings_tabs'
                );
            }
            if (isset($tabs['environments'])) {
                $tabs['environments'] = apply_filters_deprecated(
                    'wicket_settings_tab_env',
                    [$tabs['environments']],
                    '1.1.0',
                    'wicket_settings_tabs'
                );
            }
            if (isset($tabs['memberships'])) {
                $tabs['memberships'] = apply_filters_deprecated(
                    'wicket_settings_tab_memb',
                    [$tabs['memberships']],
                    '1.1.0',
                    'wicket_settings_tabs'
                );
            }
            if (isset($tabs['touchpoints'])) {
                $tabs['touchpoints'] = apply_filters_deprecated(
                    'wicket_settings_tab_tp',
                    [$tabs['touchpoints']],
                    '1.1.0',
                    'wicket_settings_tabs'
                );
            }
            if (isset($tabs['integrations'])) {
                $tabs['integrations'] = apply_filters_deprecated(
                    'wicket_settings_tab_int',
                    [$tabs['integrations']],
                    '1.1.0',
                    'wicket_settings_tabs'
                );
            }

            $settings = apply_filters_deprecated(
                'wicket_settings_extend',
                [$settings],
                '1.1.0',
                'wicket_settings_tabs'
            );

            $settings->make();
        }

        /**
         * Register General tab sections and options.
         *
         * @param mixed $tab WPSettings tab instance.
         */
        public function register_general_tab($tab)
        {
            $section = $tab->add_section(_x('General', 'label', 'wicket-base'));
            $section->add_option('select', [
                'name' => 'wicket_admin_settings_create_account_page',
                'label' => _x('Create Account Page', 'label', 'wicket-base'),
                'description' => __('Choose the create account page. This must contain the "Create Account Form" block.', 'wicket-base'),
                'options' => $this->get_all_pages(),
                'css' => [
                    'input_class' => 'wicket-create-account',
                ],
            ]);
            $section->add_option('select', [
                'name' => 'wicket_admin_settings_person_creation_redirect',
                'label' => _x('New Account Redirect', 'label', 'wicket-base'),
                'description' => __('Where users are directed once they complete the create account form. Default is /verify-account', 'wicket-base'),
                'options' => $this->get_all_pages(),
                'css' => [
                    'input_class' => 'wicket-verify-account',
                ],
            ]);

            $section->add_option('checkbox', [
                'name' => 'wicket_admin_settings_google_captcha_enable',
                'label' => _x('Google Captcha', 'label', 'wicket-base'),
                'description' => __('Enable Google Captcha on Create Account Form', 'wicket-base'),
            ]);
            $section->add_option('text', [
                'name' => 'wicket_admin_settings_google_captcha_key',
                'label' => _x('Google Captcha Key', 'label', 'wicket-base'),
                'description' => __('The key used to display Google reCAPTCHA. Obtain a key here <a href="https://www.google.com/recaptcha" target="_blank"> https://www.google.com/recaptcha</a>', 'wicket-base'),
                'css' => [
                    'input_class' => 'regular-text',
                ],
            ]);
            $section->add_option('text', [
                'name' => 'wicket_admin_settings_google_captcha_secret_key',
                'label' => __('Google Captcha Secret Key', 'wicket-base'),
                'description' => __('The secret key used to display Google reCAPTCHA. Obtain a key here <a href="https://www.google.com/recaptcha" target="_blank"> https://www.google.com/recaptcha</a>', 'wicket-base'),
                'css' => [
                    'input_class' => 'regular-text',
                ],
            ]);

            $section = $tab->add_section(_x('Styles', 'label', 'wicket-base'));
            $section->add_option('checkbox', [
                'name' => 'wicket_admin_settings_disable_default_styling',
                'label' => _x('Disable Default Styling', 'label', 'wicket-base'),
                'description' => __('Disable Wicket default styling. Only for advanced users. If you enable this, no styling from this plugin will be loaded. Will be on you to add the necessary styles for all components and blocks.', 'wicket-base'),
            ]);
        }

        /**
         * Register Environments tab sections and options.
         *
         * @param mixed $tab WPSettings tab instance.
         */
        public function register_environments_tab($tab)
        {
            $section = $tab->add_section(__('Connect to Wicket Environments', 'wicket-base'), [
                'description' => __('Choose which environment to activate.', 'wicket-base'),
            ]);

            $section->add_option('choices', [
                'name' => 'wicket_admin_settings_environment',
                'label' => _x('Wicket Environment', 'label', 'wicket-base'),
                'options' => [
                    'stage' => _x('Staging', 'label', 'wicket-base'),
                    'prod' => _x('Production', 'label', 'wicket-base'),
                ],
            ]);

            $section->add_option('wicket-api-check');

            // Production Section
            $section = $tab->add_section(_x('Wicket Production', 'label', 'wicket-base'), [
                'description' => __('Configure Wicket API setting, etc. for production', 'wicket-base'),
            ]);

            $section->add_option('text', [
                'name' => 'wicket_admin_settings_prod_api_endpoint',
                'label' => _x('API Endpoint', 'label', 'wicket-base'),
                'description' => __('The address of the API endpoint. Ex: https://[client]-api.wicketcloud.com', 'wicket-base'),
            ]);
            $section->add_option('text', [
                'name' => 'wicket_admin_settings_prod_secret_key',
                'label' => _x('JWT Secret Key', 'label', 'wicket-base'),
                'description' => __('Secret key from Wicket', 'wicket-base'),
            ]);
            $section->add_option('text', [
                'name' => 'wicket_admin_settings_prod_person_id',
                'label' => _x('Person ID', 'label', 'wicket-base'),
                'description' => __('Person ID from Wicket', 'wicket-base'),
            ]);
            $section->add_option('text', [
                'name' => 'wicket_admin_settings_prod_parent_org',
                'label' => _x('Parent Org', 'label', 'wicket-base'),
                'description' => __('Top level organization used for creating new people on the create account form. This is the "alternate name" found in Wicket under "Organizations" for the top most organization.', 'wicket-base'),
            ]);
            $section->add_option('text', [
                'name' => 'wicket_admin_settings_prod_wicket_admin',
                'label' => _x('Wicket Admin', 'label', 'wicket-base'),
                'description' => __('The address of the admin interface. Ex: https://[client]-admin.wicketcloud.com', 'wicket-base'),
            ]);
            // Staging Section
            $section = $tab->add_section(_x('Wicket Staging', 'label', 'wicket-base'), [
                'description' => __('Configure Wicket API setting, etc. for staging', 'wicket-base'),
            ]);

            $section->add_option('text', [
                'name' => 'wicket_admin_settings_stage_api_endpoint',
                'label' => _x('API Endpoint', 'label', 'wicket-base'),
                'description' => __('The address of the API endpoint. Ex: https://[client]-api.wicketcloud.com', 'wicket-base'),
            ]);
            $section->add_option('text', [
                'name' => 'wicket_admin_settings_stage_secret_key',
                'label' => _x('JWT Secret Key', 'label', 'wicket-base'),
                'description' => __('Secret key from Wicket', 'wicket-base'),
            ]);
            $section->add_option('text', [
                'name' => 'wicket_admin_settings_stage_person_id',
                'label' => _x('Person ID', 'label', 'wicket-base'),
                'description' => __('Person ID from Wicket', 'wicket-base'),
            ]);
            $section->add_option('text', [
                'name' => 'wicket_admin_settings_stage_parent_org',
                'label' => _x('Parent Org', 'label', 'wicket-base'),
                'description' => __('Top level organization used for creating new people on the create account form. This is the "alternate name" found in Wicket under "Organizations" for the top most organization.', 'wicket-base'),
            ]);
            $section->add_option('text', [
                'name' => 'wicket_admin_settings_stage_wicket_admin',
                'label' => _x('Wicket Admin', 'label', 'wicket-base'),
                'description' => __('The address of the admin interface. Ex: https://[client]-admin.wicketcloud.com', 'wicket-base'),
            ]);
        }

        /**
         * Register Memberships tab sections and options.
         *
         * @param mixed $tab WPSettings tab instance.
         */
        public function register_memberships_tab($tab)
        {
            $section = $tab->add_section(_x('Membership Configuration Overview', 'label', 'wicket-base'), [
                'description' => __('The table below shows all Wicket Account Membership Tiers and corresponding WooCommerce Membership Plans. If you just applied changes to the Wicket Membership Tiers or changed Wicket Environments, please use the following button to renew the cached list below.', 'wicket-base'),
            ]);

            $section->add_option('wicket-plugin-check');
            $section->add_option('wicket-membership-overview');

            $section = $tab->add_section(_x('Membership Settings', 'label', 'wicket-base'), [
                'description' => __('Configure settings related to Memberships', 'wicket-base'),
            ]);

            $section->add_option('select-multiple', [
                'name' => 'wicket_admin_settings_membership_categories',
                'label' => _x('Membership Categories', 'label', 'wicket-base'),
                'description' => __('Select which categories are used only for membership products. This is used by other settings or to help with custom development.', 'wicket-base'),
                'options' => $this->get_product_categories(),
                'css' => [
                    'input_class' => 'wicket-membership-categories wicket-admin-select2',
                ],
            ]);
        }

        /**
         * Register Touchpoints tab sections and options.
         *
         * @param mixed $tab WPSettings tab instance.
         */
        public function register_touchpoints_tab($tab)
        {
            // Default Touchpoints Section
            $section = $tab->add_section('Default', [
                'as_link' => true,
                'description' => __('Touchpoints write data back from WordPress user actions back to Wicket person profiles. Configure which default touchpoint should be used.', 'wicket-base'),
            ]);

            $section->add_option('checkbox', [
                'name' => 'wicket_admin_settings_tp_woo_order',
                'label' => _x('WooCommerce Order', 'label', 'wicket-base'),
                'description' => __('Enable Order touchpoint and send details to Wicket MDP.', 'wicket-base'),
            ]);
            $section->add_option('checkbox', [
                'name' => 'wicket_admin_settings_tp_event_ticket_attendees',
                'label' => __('Event Tickets attendee registered for an event', 'wicket-base'),
                'description' => __('Enable a touchpoint to be written when attendees register for an event. <br><small>Touchpoint is written on order complete</small>', 'wicket-base'),
            ]);
            $section->add_option('checkbox', [
                'name' => 'wicket_admin_settings_tp_event_ticket_attendees_checkin',
                'label' => __('Event Tickets attendee check-in for an event', 'wicket-base'),
                'description' => __('Enable a touchpoint to be written when attendees check-in for an event', 'wicket-base'),
            ]);
            $section->add_option('checkbox', [
                'name' => 'wicket_admin_settings_tp_event_ticket_attendees_rsvp',
                'label' => __('Event Tickets attendee RSVP for an event', 'wicket-base'),
                'description' => __('Enable a touchpoint to be written when attendees RSVP for an event', 'wicket-base'),
            ]);
            $section->add_option('checkbox', [
                'name' => 'wicket_admin_settings_tp_event_ticket_attendees_added',
                'label' => __('Event Tickets attendee added by an admin or CSV import', 'wicket-base'),
                'description' => __('Enable a touchpoint to be written when an attendee is added from the WordPress admin Attendees screen, or imported from CSV. Recorded as a registration, with the source noted on the touchpoint. <br><small>Imported attendees carry no registration form answers, because the CSV importer does not collect them.</small>', 'wicket-base'),
            ]);
            $section->add_option('checkbox', [
                'name' => 'wicket_admin_settings_tp_event_ticket_attendees_removed',
                'label' => __('Event Tickets attendee removed from an event', 'wicket-base'),
                'description' => __('Enable a touchpoint to be written when an attendee is removed from an event. <br><small>Written once, whether the attendee is moved to the trash or deleted outright.</small>', 'wicket-base'),
            ]);
            $section->add_option('checkbox', [
                'name' => 'wicket_admin_settings_tp_event_ticket_attendees_answers',
                'label' => __('Include registration form answers', 'wicket-base'),
                'description' => __('Include the attendee\'s registration form answers in event touchpoints, as label and value pairs. <br><small>Every answered field is sent. Use the wicket_tec_registration_answers filter to leave fields out.</small>', 'wicket-base'),
            ]);

            //Custom Touchpoints Section
            $section = $tab->add_section('Custom', ['as_link' => true]);
        }

        /**
         * Register Integrations tab sections and options.
         *
         * @param mixed $tab WPSettings tab instance.
         */
        public function register_integrations_tab($tab)
        {
            // WooCommerce Integration Section
            $section = $tab->add_section(_x('WooCommerce', 'label', 'wicket-base'), [
                'as_link' => true,
                'description' => __('Configure settings for common customizations with default WooCommerce behaviour.', 'wicket-base'),
            ]);

            $section->add_option('checkbox', [
                'name' => 'wicket_admin_settings_woo_sync_addresses',
                'label' => _x('Sync Checkout Addresses', 'label', 'wicket-base'),
                'description' => __('Billing and shipping addresses in WooCommerce checkout are pre-populated with the address from the Wicket person record.', 'wicket-base'),
            ]);
            $section->add_option('checkbox', [
                'name' => 'wicket_admin_settings_woo_remove_cart_links',
                'label' => __('Remove product links from Cart & Checkout', 'wicket-base'),
                'description' => __('Remove links to product pages from the cart and checkout pages', 'wicket-base'),
            ]);
            $section->add_option('checkbox', [
                'name' => 'wicket_admin_settings_woo_remove_membership_categories',
                'label' => _x('Hide membership categories', 'label', 'wicket-base'),
                'description' => __('Hide membership categories and remove from search results. Membership categories are selected in the Membership setting tab.', 'wicket-base'),
            ]);
            $section->add_option('checkbox', [
                'name' => 'wicket_admin_settings_woo_remove_membership_product_single',
                'label' => __('Hide membership product single pages', 'wicket-base'),
                'description' => __('Products in the categories that are identified as Membership Categories in the Wicket Settings -> Memberships Tab will have their single page redirect to the homepage. These products can still be added to cart by other methods such as the onboarding form.', 'wicket-base'),
            ]);
            $section->add_option('checkbox', [
                'name' => 'wicket_admin_settings_woo_remove_added_to_cart_message',
                'label' => __('Remove product added to cart message', 'wicket-base'),
                'description' => __('When redirected to a checkout disable the "X added to cart, continue shopping?" message.', 'wicket-base'),
            ]);
            $section->add_option('checkbox', [
                'name' => 'wicket_admin_settings_woo_email_blocker_enabled',
                'label' => __('Block customer emails on admin updates', 'wicket-base'),
                'description' => __('Stops customer emails when admins update orders unless the email is explicitly sent.', 'wicket-base'),
                'default' => '0',
            ]);
            $section->add_option('checkbox', [
                'name' => 'wicket_admin_settings_woo_email_blocker_allow_refund_emails',
                'label' => __('Allow refund emails from admin', 'wicket-base'),
                'description' => __('Allow refund emails to customers when refunds are triggered by an admin.', 'wicket-base'),
                'default' => '0',
            ]);
            $section->add_option('text', [
                'name' => 'wicket_admin_settings_woo_draft_order_retention_days',
                'label' => __('Draft order retention (days)', 'wicket-base'),
                'description' => __('Number of days a draft (checkout-draft) order is kept before being automatically deleted. Defaults to 60 days. Set to 0 to disable automatic deletion entirely. Set to 1 to match WooCommerce\'s built-in default behaviour. Note: this overrides WooCommerce\'s built-in behaviour, which deletes draft orders after just 1 day.', 'wicket-base'),
                'default' => '60',
            ]);

            // run a client check, otherwise the whole site will die if there's no connection filled out yet to an MDP
            if ($client = wicket_api_client()) {
                $section->add_option('select-multiple', [
                    'name' => 'wicket_admin_settings_woo_person_to_org_types',
                    'label' => __('Person to Organization Relationship Types', 'wicket-base'),
                    'description' => __('This is needed to determine which person to organization relationship types we want to identify that are relevant for usage on the site. One example is within WooCommerce orders.', 'wicket-base'),
                    'options' => array_merge(['' => ' - Not Applicable - '], $this->get_person_to_organizations_connection_types()),
                ]);
            }
            $section->add_option('checkbox', [
                'name' => 'wicket_admin_settings_group_assignment_subscription_products',
                'label' => __('Assign people to groups on product purchase', 'wicket-base'),
                'description' => __('When certain subscription products are purchased manage group assignments based on subscription dates.', 'wicket-base'),
            ]);
            $section->add_option('select', [
                'name' => 'wicket_admin_settings_group_assignment_product_category',
                'label' => __('Group Assignment Product Category', 'wicket-base'),
                'description' => __('Choose the category of product that will show the Group Assignment Tab and Settings.', 'wicket-base'),
                'options' => $this->get_product_categories(),
                'css' => [
                    'input_class' => 'wicket-group-product-categories wicket-admin-select2',
                ],
            ]);

            $section->add_option('text', [
                'name' => 'wicket_admin_settings_group_assignment_role_entity_object',
                'label' => __('Group Role Entity Object Slug', 'wicket-base'),
                'description' => __('Use <code>group-members</code> unless it isn\'t working and only if you understand why it should be different. This retrieves Group Role resource entity options from Wicket.', 'wicket-base'),
                'default' => 'group-members',
            ]);

            // WP Cassify Integration Tab
            $section = $tab->add_section(_x('WP Cassify', 'label', 'wicket-base'), ['as_link' => true]);
            $section->add_option('checkbox', [
                'name' => 'wicket_admin_settings_wpcassify_sync_roles',
                'label' => _x('Sync Security Roles', 'label', 'wicket-base'),
                'description' => __('Sync all roles found under profile -> security -> roles in the MDP for each user when they log in to WordPress - Requires WP-CASSIFY plugin.', 'wicket-base'),
            ]);
            $section->add_option('checkbox', [
                'name' => 'wicket_admin_settings_wpcassify_sync_memberships_as_roles',
                'label' => __('Sync Memberships as Roles', 'wicket-base'),
                'description' => __('Sync active user memberships from the MDP for a user when they log in to WordPress. For example, if they have a membership called "Student" within the MDP, when they log in, a role will be created called "Student" if it does not yet exist and assign that role to the user. - NOTE: Requires WP-CASSIFY plugin and <strong><em> the checkbox above to be selected!</em></strong>', 'wicket-base'),
            ]);
            $section->add_option('text', [
                'name' => 'wicket_admin_settings_wpcassify_sync_tags_as_roles',
                'label' => __('Sync Tags as Roles', 'wicket-base'),
                'description' => __('Sync selected user tags from the MDP for a user when they log in to WordPress. Type the allowed tag(s) in this field. Values in field must match the tag in the MDP exactly. Multiple values must be comma separated.', 'wicket-base'),
            ]);
            $section->add_option('text', [
                'name' => 'wicket_admin_settings_wpcassify_ignore_roles',
                'label' => _x('Ignore Roles', 'label', 'wicket-base'),
                'description' => __('Ignore certain user roles from the MDP for a user when they log in to WordPress. Type the allowed role(s) in this field. Values in field must match the roles in the MDP exactly. Multiple values must be comma separated. - NOTE: This only applies to security roles found on the users profile in the MDP. It does not apply to derived roles such as the ones mentioned above for memberships, etc', 'wicket-base'),
            ]);

            // Mailtrap Integration Tab
            $section = $tab->add_section(_x('Mailtrap', 'label', 'wicket-base'), [
                'as_link' => true,
                'description' => __('Used to send all WordPress mail to mailtrap. Typically used on staging/local for testing and will only take effect when the Wicket environment is toggled to "Staging". <br> NOTE! Remember to disable any SMTP plugin(s) while using the stage environment. Otherwise these settings won\'t take effect.', 'wicket-base'),
            ]);

            $section->add_option('text', [
                'name' => 'wicket_admin_settings_mailtrap_host',
                'label' => _x('Host', 'label', 'wicket-base'),
                'description' => __('Can be found under SMTP settings within the inbox -> Show Credentials -> Under SMTP', 'wicket-base'),
            ]);
            $section->add_option('text', [
                'name' => 'wicket_admin_settings_mailtrap_port',
                'label' => _x('Port', 'label', 'wicket-base'),
                'description' => __('This is usually 2525', 'wicket-base'),
            ]);
            $section->add_option('text', [
                'name' => 'wicket_admin_settings_mailtrap_username',
                'label' => _x('Username', 'label', 'wicket-base'),
                'description' => __('Can be found under SMTP settings within the inbox -> Show Credentials -> Under SMTP', 'wicket-base'),
            ]);
            $section->add_option('text', [
                'name' => 'wicket_admin_settings_mailtrap_password',
                'label' => _x('Password', 'label', 'wicket-base'),
                'description' => __('Can be found under SMTP settings within the inbox -> Show Credentials -> Under SMTP', 'wicket-base'),
            ]);
        }
    }
    new Wicket_Settings();
}
