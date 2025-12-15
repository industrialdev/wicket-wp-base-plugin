<?php
/**
 * Wicket WooCommerce Membership Team Metabox and Fields
 * Description: Add metabox and UUID field to Teams.
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('Wicket_Woo_Team_Metabox')) {

    class Wicket_Woo_Team_Metabox
    {
        /**
         * Constructor of class.
         */
        public function __construct()
        {

            // whitelist custom fields
            add_filter('wc_memberships_for_teams_allowed_meta_box_ids', [$this, 'allow_custom_wicket_field_metaboxes']);

            // create meta box
            add_action('add_meta_boxes', [$this, 'wicket_team_meta_box']);
            // Save MetaBox Values
            add_action('save_post_wc_memberships_team', [$this, 'wicket_team_save_metabox_values']);

        }

        /**
         * Adds custom field metaboxes to the array of allowed metaboxes.
         *
         * @param array $allowed required The array of allowed metabox ids
         * @return array $allowed The array with custom field metaboxes added in
         */
        public function allow_custom_wicket_field_metaboxes($allowed)
        {

            $allowed[] = 'wc-memberships-for-teams-wicket';

            return $allowed;
        }

        /**
         * Add meta box in WC Membership Team custom post type.
         */
        public function wicket_team_meta_box()
        {
            add_meta_box(
                'wc-memberships-for-teams-wicket', // $id
                __('Wicket Team Settings', 'wicket'), // $title which will be shown at top of metabox.
                [$this, 'wicket_team_metabox_cb'], // callback function name.
                'wc_memberships_team', // The screen or screens on which to show the box (such as a post type, 'link', or 'comment').
                'normal', // $context
                'high' // $priority
            );
        }

        /**
         * Add fields in custom meta box.
         */
        public function wicket_team_metabox_cb()
        {

            global $post;

            wp_nonce_field('wicket_team_fields_nonce', 'wicket_team_fields_nonce');

            $wicket_team_uuid = get_post_meta(intval($post->ID), 'wicket_team_uuid_fld', true);

            ?>
            <div class="wicket-team-form">

                <tr class="addify-option-field">

                    <th>

                        <div class="option-head">

                            <h3>

                                <?php esc_html_e('Wicket Organization', 'wicket'); ?>

                            </h3>

                        </div>  

                    </th>

                    <td>

                        <input type="text" class="wicket_input width-60" name="wicket_team_uuid_fld" id="wicket_team_uuid_fld" value="<?php echo esc_attr($wicket_team_uuid) ? esc_attr($wicket_team_uuid) : ''; ?>" />
                        <br>
                        <p><?php esc_html_e('Enter the organization UUID value from Wicket. This is require to establish a connection between this WooCommerce Membership Team and Wicket Organization.', 'wicket'); ?></p>
                    </td>

                </tr>

            </div>

            <?php

        }

        /**
         * Save fields.
         *
         * @param int $post_id Post id.
         */
        public function wicket_team_save_metabox_values($post_id)
        {

            // return if we're doing an auto save
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return;
            }

            if (get_post_status($post_id) === 'auto-draft' || get_post_status($post_id) === 'trash') {
                return;
            }

            // if our nonce isn't there, or we can't verify it, return
            if (!isset($_POST['wicket_team_fields_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['wicket_team_fields_nonce']), 'wicket_team_fields_nonce')) {
                die('Failed Security Check!');
            }

            // if our current user can't edit this post, return
            if (!current_user_can('edit_posts')) {
                return;
            }

            if (isset($_POST['wicket_team_uuid_fld'])) {

                update_post_meta($post_id, 'wicket_team_uuid_fld', sanitize_meta('', wp_unslash($_POST['wicket_team_uuid_fld']), ''));

            }

        }
    }

    new Wicket_Woo_Team_Metabox();

}
