<?php
/**
 * Admin Settings file for Wicket Base Plugin
 *
 * @package  Wicket\Settings
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use Jeffreyvr\WPSettings\WPSettings;

if ( ! class_exists( 'Wicket_Settings' ) ) {

	/**
	 * Wicket Settings class
	 */
	class Wicket_Settings {

		/**
		 * Constructor of class
		 */
	    public function __construct() {

	        // Add custom option types - must include before registering settings
			include_once WICKET_PLUGIN_DIR . 'includes/admin/settings/options/wicket-membership-table.php';
			include_once WICKET_PLUGIN_DIR . 'includes/admin/settings/options/wicket-api-check.php';
			include_once WICKET_PLUGIN_DIR . 'includes/admin/settings/options/wicket-plugin-check.php';

	    	// Add Settings page options
	        add_action('admin_menu', [$this, 'register_wicket_settings']);

	    }

		/**
		 * Get All Pages - for settings select fields
		 */
	    public function get_all_pages() {
			// Query for listing all pages in the select box loop
			$my_wp_query = new WP_Query();
			$all_wp_pages = $my_wp_query->query( array(
			    'post_type' => 'page',
			    'posts_per_page' => -1
			));
			foreach ($all_wp_pages as $value){
			    $page = get_page($value);
			    $title = $page->post_title . ' (ID: ' . $page->ID . ')';
			    $id = $page->ID;

			    $page_list[ $id ] = $title;
			}
			return $page_list;
	    }

		/**
		 * Get Product Categories - for settings select fields
		 */
	    public function get_product_categories() {
	    	if ( $taxonomy_exist = taxonomy_exists( 'product_cat' ) ) {

				$all_categories = get_terms( ['taxonomy' => 'product_cat', 'hide_empty' => false] );
					foreach ($all_categories as $cat) {
					    $title = $cat->name . ' (ID: ' . $cat->term_id . ')';
					    $id = $cat->term_id;

				        $category_list[$id] = $cat->name;
					}
				return $category_list;

				}
	    }

		/**
		 * Get person to organization relationship (connection) types - for settings select fields
		 */
	    public function get_person_to_organizations_connection_types() {

				$resource_types = wicket_get_resource_types('relationships-person-to-organization');
				$person_to_organizations_connection_types = [];
	    	foreach ($resource_types['data'] as $key => $value) {
					$person_to_organizations_connection_types[$value['attributes']['slug']] = $value['attributes']['name'];
				}
				return $person_to_organizations_connection_types;
	    }

		/**
		 * Create Settings page tabs, sections and options
		 */
	    public function register_wicket_settings() {

			// Create Settings page with same slug as menu
			$settings = new WPSettings(__('Wicket Settings'),('wicket-settings'));
			$settings->set_menu_parent_slug('wicket-settings');

			/*
			 * Start General tab
			 *
			 */
			$tab_gen = $settings->add_tab(__( 'General', 'wicket'));

			$section = $tab_gen->add_section(__('Create Account', 'wicket'));

			$section->add_option('select', [
			    'name' => 'wicket_admin_settings_create_account_page',
			    'label' => __('Create Account Page', 'wicket'),
			    'description' => __('Choose the create acount page. This must contain the "Create Account Form" block.', 'wicket'),
			    'options' => $this->get_all_pages(),
			    'css' => [
			        'input_class' => 'wicket-create-account',
			    ]
			]);
			$section->add_option('select', [
			    'name' => 'wicket_admin_settings_person_creation_redirect',
			    'label' => __('New Account Redirect', 'wicket'),
			    'description' => __('Where users are directed once they complete the create account form. Default is /verify-account', 'wicket'),
			    'options' => $this->get_all_pages(),
			    'css' => [
			        'input_class' => 'wicket-verify-account',
			    ]
			]);

			$section->add_option('checkbox', [
			    'name' => 'wicket_admin_settings_google_captcha_enable',
			    'label' => __('Google Captcha', 'wicket'),
			    'description' => __('Enable Google Captcha on Create Account Form', 'wicket'),
			]);
			$section->add_option('text', [
			    'name' => 'wicket_admin_settings_google_captcha_key',
			    'label' => __('Google Captcha Key', 'wicket'),
			    'description' => __('The key used to dislay Google recaptcha. Obtain a key here <a href="https://www.google.com/recaptcha" target="_blank"> https://www.google.com/recaptcha</a>', 'wicket'),
			    'css' => [
			        'input_class' => 'regular-text',
			    ]
			]);
			$section->add_option('text', [
			    'name' => 'wicket_admin_settings_google_captcha_secret_key',
			    'label' => __('Google Captcha Secret Key', 'wicket'),
			    'description' => __('The secret key used to dislay Google recaptcha. Obtain a key here <a href="https://www.google.com/recaptcha" target="_blank"> https://www.google.com/recaptcha</a>', 'wicket'),
			    'css' => [
			        'input_class' => 'regular-text',
			    ]
			]);

			// Styles section
            $section = $tab_gen->add_section(__('Styles', 'wicket'));
            $section->add_option('checkbox', [
                'name' => 'wicket_admin_settings_disable_default_styling',
                'label' => __('Disable Default Styling', 'wicket'),
                'description' => __('Disable Wicket default styling. Only for advanced users. If you enable this, no styling from this plugin will be loaded. Will be on you to add the necessary styles for all components and blocks.', 'wicket'),
            ]);

			/*
			 * Start Environments tab
			 *
			 */
			$tab_env = $settings->add_tab(__( 'Environments', 'wicket'));

			$section = $tab_env->add_section(__('Connect to Wicket Environments', 'wicket'), [
				'description' => __('Choose which environment to activate.', 'wicket'),
			]);

			$section->add_option('choices', [
			    'name' => 'wicket_admin_settings_environment',
			    'label' => __( 'Wicket Environment', 'wicket' ),
			    'options' => [
			        'stage' => __('Staging','wicket'),
			        'prod' => __('Production','wicket')
			    ]
			]);

			$section->add_option('wicket-api-check');

			// Production Section
			$section = $tab_env->add_section(__('Wicket Production', 'wicket'), [
				'description' => __('Configure Wicket API setting, etc. for production', 'wicket'),
			]);

			$section->add_option('text', [
			    'name' => 'wicket_admin_settings_prod_api_endpoint',
			    'label' => __('API Endpoint', 'wicket'),
				'description' => __('The address of the api endpoint. Ex: https://[client]-api.wicketcloud.com', 'wicket')
 			]);
			$section->add_option('text', [
			    'name' => 'wicket_admin_settings_prod_secret_key',
			    'label' => __('JWT Secret Key', 'wicket'),
				'description' => __('Secret key from Wicket', 'wicket')
 			]);
			$section->add_option('text', [
			    'name' => 'wicket_admin_settings_prod_person_id',
			    'label' => __('Person ID', 'wicket'),
				'description' => __('Person ID from Wicket', 'wicket')
 			]);
			$section->add_option('text', [
			    'name' => 'wicket_admin_settings_prod_parent_org',
			    'label' => __('Parent Org', 'wicket'),
				'description' => __('Top level organization used for creating new people on the create account form. This is the "alternate name" found in Wicket under "Organizations" for the top most organization.', 'wicket')
 			]);
 			$section->add_option('text', [
			    'name' => 'wicket_admin_settings_prod_wicket_admin',
			    'label' => __('Wicket Admin', 'wicket'),
				'description' => __('The address of the admin interface. Ex: https://[client]-admin.wicketcloud.com', 'wicket')
 			]);
			// Staging Section
			$section = $tab_env->add_section(__('Wicket Staging', 'wicket'), [
				'description' => __('Configure Wicket API setting, etc. for staging', 'wicket'),
			]);

			$section->add_option('text', [
			    'name' => 'wicket_admin_settings_stage_api_endpoint',
			    'label' => __('API Endpoint', 'wicket'),
				'description' => __('The address of the api endpoint. Ex: https://[client]-api.wicketcloud.com', 'wicket')
 			]);
			$section->add_option('text', [
			    'name' => 'wicket_admin_settings_stage_secret_key',
			    'label' => __('JWT Secret Key', 'wicket'),
				'description' => __('Secret key from Wicket', 'wicket')
 			]);
			$section->add_option('text', [
			    'name' => 'wicket_admin_settings_stage_person_id',
			    'label' => __('Person ID', 'wicket'),
				'description' => __('Person ID from Wicket', 'wicket')
 			]);
			$section->add_option('text', [
			    'name' => 'wicket_admin_settings_stage_parent_org',
			    'label' => __('Parent Org', 'wicket'),
				'description' => __('Top level organization used for creating new people on the create account form. This is the "alternate name" found in Wicket under "Organizations" for the top most organization.', 'wicket')
 			]);
			$section->add_option('text', [
			    'name' => 'wicket_admin_settings_stage_wicket_admin',
			    'label' => __('Wicket Admin', 'wicket'),
				'description' => __('The address of the admin interface. Ex: https://[client]-admin.wicketcloud.com', 'wicket')
 			]);

			/*
			 * Start Memberships tab
			 *
			 */
			$tab_memb = $settings->add_tab(__( 'Memberships', 'wicket'));

			$section = $tab_memb->add_section(__('Membership Configuration Overview', 'wicket'), [
				'description' => __('The table below shows all Wicket Account Memberships Tiers and corresponding WooCommerce Membership Plans. If you just applied  changes to the Wicket Membership Tiers or changed Wicket Environments, please use the following button to renew the cached list below.', 'wicket'),
			]);

			$section->add_option('wicket-plugin-check');
			$section->add_option('wicket-membership-overview');

			$section = $tab_memb->add_section(__('Membership Settings', 'wicket'), [
				'description' => __('Configure settings related to Memberships', 'wicket'),
			]);

			$section->add_option('select-multiple', [
			    'name' => 'wicket_admin_settings_membership_categories',
			    'label' => __('Membership Categories', 'wicket'),
			    'description' => __('Select which categories are used only for membership products. This is used by other settings or to help with custom development.', 'wicket'),
			    'options' => $this->get_product_categories(),
			    'css' => [
			        'input_class' => 'wicket-membership-categories wicket-admin-select2',
			    ]
			]);

			/*
			 * Start Touchpoints tab
			 *
			 */
			$tab_tp = $settings->add_tab(__( 'Touchpoints', 'wicket'));

			// Default Touchpoints Section
			$section = $tab_tp->add_section('Default', [
				'as_link' => true,
				'description' => __('Touchpoints write data back from WordPress user actions back to Wicket person profiles. Configure which default touchpoint should be used.', 'wicket')
			]);

			$section->add_option('checkbox', [
			    'name' => 'wicket_admin_settings_tp_woo_order',
			    'label' => __('WooCommerce Order', 'wicket'),
			    'description' => __('Enable Order touchpoint and send details to Wicket MDP.', 'wicket')
			]);
			$section->add_option('checkbox', [
				'name' => 'wicket_admin_settings_tp_event_ticket_attendees',
				'label' => __('Event Tickets attendee registered for an event', 'wicket'),
				'description' => __('Enable a touchpoint to be written when attendees register for an event. <br><small>Touchpoint is written on order complete</small>', 'wicket')
			]);
			$section->add_option('checkbox', [
			    'name' => 'wicket_admin_settings_tp_event_ticket_attendees_checkin',
			    'label' => __('Event Tickets attendee check-in for an event', 'wicket'),
			    'description' => __('Enable a touchpoint to be written when attendees check-in for an event', 'wicket')
			]);
			$section->add_option('checkbox', [
			    'name' => 'wicket_admin_settings_tp_event_ticket_attendees_rsvp',
			    'label' => __('Event Tickets attendee RSVP for an event', 'wicket'),
			    'description' => __('Enable a touchpoint to be written when attendees RSVP for an event', 'wicket')
			]);


			//Custom Touchpoints Section
			$section = $tab_tp->add_section('Custom', ['as_link' => true]);

			/*
			 * Start Integrations tab
			 *
			 */
			$tab_int = $settings->add_tab(__('Integrations', 'wicket'));

			// WooCommerce Integration Section
			$section = $tab_int->add_section(__('WooCommerce', 'wicket'), [
				'as_link' => true,
				'description' => __('Configure settings for common customizations with default WooCommerce behaviour.', 'wicket')
			]);

			$section->add_option('checkbox', [
			    'name' => 'wicket_admin_settings_woo_sync_addresses',
			    'label' => __('Sync Checkout Addresses', 'wicket'),
			    'description' => __('Billing and shipping addresses in WooCommerce checkout are pre-populated with the address from the Wicket person record.', 'wicket')
			]);
			$section->add_option('checkbox', [
			    'name' => 'wicket_admin_settings_woo_remove_cart_links',
			    'label' => __('Remove product links from Cart & Checkout', 'wicket'),
			    'description' => __('Remove links to product pages from the cart and checkout pages', 'wicket')
			]);
			$section->add_option('checkbox', [
			    'name' => 'wicket_admin_settings_woo_remove_membership_categories',
			    'label' => __('Hide membership categories', 'wicket'),
			    'description' => __('Hide membership categories and remove from search results. Membership categories are selected in the Membership setting tab.', 'wicket')
			]);
			$section->add_option('checkbox', [
			    'name' => 'wicket_admin_settings_woo_remove_membership_product_single',
			    'label' => __('Hide membership product single pages', 'wicket'),
			    'description' => __('Products in the categories that are identified as Membership Categories in the Wicket Settings -> Memberships Tab will have their single page redirect to the homepage. These products can still be added to cart by other methods such as the onboarding form.', 'wicket')
			]);
			$section->add_option('checkbox', [
			    'name' => 'wicket_admin_settings_woo_remove_added_to_cart_message',
			    'label' => __('Remove product added to cart message', 'wicket'),
			    'description' => __('When redirected to a checkout disable the "X added to cart, continue shopping?" message.', 'wicket')
			]);

			// run a client check, otherwise the whole site will die if there's no connection filled out yet to an MDP
			if ($client = wicket_api_client()) {
				$section->add_option('select-multiple', [
					'name' => 'wicket_admin_settings_woo_person_to_org_types',
					'label' => __('Person to Organization Relationship Types', 'wicket'),
					'description' => __('This is needed to determine which person to organization relationship types we want to identify that are relevant for usage on the site. One example is within woocommerce orders.', 'wicket'),
					'options' => array_merge(['' => ' - Not Applicable - '], $this->get_person_to_organizations_connection_types()),
				]);
			}
			$section->add_option('checkbox', [
        'name' => 'wicket_admin_settings_group_assignment_subscription_products',
        'label' => __('Assign people to groups on product purchase', 'wicket'),
        'description' => __('When certain subscription products are purchased manage group assignments based on susbcription dates.', 'wicket')
      ]);
			$section->add_option('select', [
        'name' => 'wicket_admin_settings_group_assignment_product_category',
        'label' => __('Group Assignment Product Category', 'wicket'),
        'description' => __('Choose the category of product that will show the Group Assignment Tab and Settings.', 'wicket'),
        'options' => $this->get_product_categories(),
        'css' => [
            'input_class' => 'wicket-group-product-categories wicket-admin-select2',
        ]
      ]);

      $section->add_option('text', [
      'name' => 'wicket_admin_settings_group_assignment_role_entity_object',
      'label' => __('Group Role Entity Object Slug', 'wicket'),
      'description' => __('Use <code>group-members</code> unless it isn\'t working and only if you understand why it should be different. This retrieves Group Role resource entity options from Wicket.', 'wicket'),
      'default' => 'group-members'
    ]);

			// WP Cassify Integration Tab
			$section = $tab_int->add_section(__('WP Cassify', 'wicket'), ['as_link' => true]);
			$section->add_option('checkbox', [
				'name' => 'wicket_admin_settings_wpcassify_sync_roles',
				'label' => __('Sync Security Roles', 'wicket'),
				'description' => __('Sync all roles found under profile -> security -> roles in the MDP for each user when they log in to WordPress - Requires WP-CASSIFY plugin.', 'wicket')
			]);
			$section->add_option('checkbox', [
			    'name' => 'wicket_admin_settings_wpcassify_sync_memberships_as_roles',
			    'label' => __('Sync Memberships as Roles', 'wicket'),
			    'description' => __('Sync active user memberships from the MDP for a user when they log in to WordPress. For example, if they have a membership called "Student" within the MDP, when they log in, a role will be created called "Student" if it does not yet exist and assign that role to the user. - NOTE: Requires WP-CASSIFY plugin and <strong><em> the checkbox above to be selected!<em></strong>', 'wicket'),
			]);
			$section->add_option('text', [
			    'name' => 'wicket_admin_settings_wpcassify_sync_tags_as_roles',
			    'label' => __('Sync Tags as Roles', 'wicket'),
			    'description' => __('Sync selected user tags from the MDP for a user when they log in to WordPress. Type the allowed tag(s) in this field. Values in field must match the tag in the MDP exactly. Multiple values must be comma separated.', 'wicket'),
			]);
			$section->add_option('text', [
			    'name' => 'wicket_admin_settings_wpcassify_ignore_roles',
			    'label' => __('Ignore Roles', 'wicket'),
			    'description' => __('Ignore certain user roles from the MDP for a user when they log in to WordPress. Type the allowed role(s) in this field. Values in field must match the roles in the MDP exactly. Multiple values must be comma separated. - NOTE: This only applies to security roles found on the users profile in the MDP. It does not apply to derived roles such as the ones mentioned above for memberships, etc', 'wicket'),
			]);




			// Mailtrap Integration Tab
			$section = $tab_int->add_section(__('Mailtrap', 'wicket'), [
				'as_link' => true,
				'description' => __('Used to send all Wordpress mail to mailtrap. Typically used on staging/local for testing and will only take effect when the Wicket environment is toggled to "Staging". <br> NOTE! Remember to disable any SMTP plugin(s) while using the stage environment. Otherwise these settings won\'t take effect.', 'wicket')
			]);

			$section->add_option('text', [
				'name' => 'wicket_admin_settings_mailtrap_host',
				'label' => __('Host', 'wicket'),
				'description' => __('Can be found under SMTP settings within the inbox -> Show Credentials -> Under SMTP', 'wicket')
		 	]);
			$section->add_option('text', [
				'name' => 'wicket_admin_settings_mailtrap_port',
				'label' => __('Port', 'wicket'),
				'description' => __('This is usually 2525', 'wicket')
		 	]);
			$section->add_option('text', [
				'name' => 'wicket_admin_settings_mailtrap_username',
				'label' => __('Username', 'wicket'),
				'description' => __('Can be found under SMTP settings within the inbox -> Show Credentials -> Under SMTP', 'wicket')
		 	]);
			$section->add_option('text', [
				'name' => 'wicket_admin_settings_mailtrap_password',
				'label' => __('Password', 'wicket'),
				'description' => __('Can be found under SMTP settings within the inbox -> Show Credentials -> Under SMTP', 'wicket')
		 	]);

			/*
			 * Filters to extend the settings above
			 */
			// Extend Settings - add new tab with sections and options
			$settings = apply_filters( 'wicket_settings_extend', $settings );

			// Extend General - add new sections and options
			$tab_gen = apply_filters( 'wicket_settings_tab_gen', $tab_gen );

			// Extend Membership - add new sections and options
			$tab_memb = apply_filters( 'wicket_settings_tab_memb', $tab_memb );

			// Extend Environments - add new sections and options
			$tab_env = apply_filters( 'wicket_settings_tab_env', $tab_env );

			// Extend Touchpoints - add new sections and options
			$tab_tp = apply_filters( 'wicket_settings_tab_tp', $tab_tp );

			// Extend Integrations - add new sections and options
			$tab_int = apply_filters( 'wicket_settings_tab_int', $tab_int );

			$settings->make();
	    }

	}
new Wicket_Settings();
}
