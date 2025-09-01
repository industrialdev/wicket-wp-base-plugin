<?php

declare(strict_types=1);

namespace WicketWP\Widgets;

/**
 * Wicket Manage Preference
 * Description: Providies a widget containing basic fields from preferences on person in Wicket
 *
 */

use Wicket\Client;

// The widget class
// http://www.wpexplorer.com/create-widget-plugin-wordpress
class ManagePreferences extends \WP_Widget {

    public $errors;

    // Main constructor
    public function __construct()
    {
        parent::__construct(
            'wicket_preferences',
            __('Wicket Manage Preferences', 'wicket'),
            array(
                'customize_selective_refresh' => true,
            )
        );
    }

    public function form($instance) {
        return $instance;
    }

    public function update($new_instance, $old_instance) {
        return $old_instance;
    }

    // Display the widget
    public function widget($args, $instance)
    {
        $client = wicket_api_client_current_user();
        $result = '';
            if ( !$client ) {
                // if the API isn't up, just stop here
                return;
            }
            $this->build_form();
    }

    public static function init() {
        add_action('init', function () {
            if (isset($_POST['wicket_preferences'])) {
                // Get the widget instance that's registered
                global $wp_widget_factory;
                if (isset($wp_widget_factory->widgets['wicket_preferences'])) {
                    $widget = $wp_widget_factory->widgets['wicket_preferences'];
                    $widget->process_form();
                } else {
                    // Fallback to creating a new instance
                    $widget = new self();
                    $widget->process_form();
                }
            }
        });
    }

    private function process_form() {
        if (isset($_POST['wicket_preferences'])){
            if(!session_id()) session_start();

            $client = wicket_api_client_current_user();

            /**------------------------------------------------------------------
            * Process preference data
            ------------------------------------------------------------------*/
            $person = wicket_current_person();
            $language = isset($_POST['language']) ? strtoupper($_POST['language']) : '';
            $email = isset($_POST['email']) && $_POST['email'] == true ? true : false;
            $email_third_party = isset($_POST['email_third_party']) && $_POST['email_third_party'] == true ? true : false;

            $update_array = [];
            $update_array['data']['communications']['email'] = $email;
            $update_array['data']['communications']['sublists']['one'] = $email_third_party;
            $update_array['language'] = $language;

            $update_user = new \Wicket\Entities\People($update_array);
            $update_user->id = $person->id;
            $update_user->type = $person->type;

            try {
                $client->people->update($update_user);
            } catch (\Exception $e) {
                $_SESSION['wicket_preferences_form_errors'] = json_decode($e->getResponse()->getBody())->errors;
            }
            // redirect here if there was updates made to reload person info and prevent form re-submission
            if (empty($_SESSION['wicket_preferences_form_errors'])) {
                unset($_SESSION['wicket_preferences_form_errors']);
                header('Location: '.strtok($_SERVER["REQUEST_URI"],'?').'?success');
                die;
            }
        } else {
            if (isset($_SESSION['wicket_preferences_form_errors'])) {
                unset($_SESSION['wicket_preferences_form_errors']);
            }
        }
    }

    private function build_form()
    {
        if (!isset($_POST['wicket_preferences'])) {
            if (isset($_SESSION['wicket_preferences_form_errors'])) {
                unset($_SESSION['wicket_preferences_form_errors']);
            }
        }
        $person = wicket_current_person();
        ?>
        <?php if(isset($_GET['success'])): ?>
            <div class='alert alert--success'>
                <p><?php _e("Successfully Updated"); ?></p>
            </div>
        <?php endif; ?>

        <form class='manage_preferences_form' method="post">

            <div class="form__group">
                <label class="form__label" for="language"><?php _e('Language') ?></label>
                <select required id="language" name="language" class="form__input">
                    <option <?php echo $person->language == 'en' ? 'selected' : '' ?> value="en"><?php _e('English') ?></option>
                    <option <?php echo $person->language == 'fr' ? 'selected' : '' ?> value="fr"><?php _e('French') ?></option>
                </select>
            </div>

            <h2><?php _e('Communications'); ?></h2>

            <div class="row">
                <div class="col_md-12">
                    <ul class="create_account_checkbox_list">
                        <li>
                            <input <?php echo $person->data['communications']['email'] == 1 ? 'checked' : '' ?> type="checkbox" name="email" id="email" value="1">
                            <label class="form__label" for="email"><?php _e('Yes, accept communications') ?></label>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="row">
                <div class="col_md-12">
                    <ul class="create_account_checkbox_list">
                        <li>
                            <input <?php echo $person->data['communications']['sublists']['one'] == 1 ? 'checked' : '' ?> type="checkbox" id="email_third_party" name="email_third_party" value="1">
                            <label class="form__label" for="email_third_party"><?php _e('Yes, accept communications from 3rd party vendors') ?></label>
                        </li>
                    </ul>
                </div>
            </div>

            <input type="hidden" name="wicket_preferences" value="<?php echo $this->id_base . '-' . $this->number; ?>" />

            <?php
                get_component( 'button', [
                    'label'    => __('Update Preferences'),
                    'type'    => 'submit',
                    'variant' => 'primary'
                ] );
            ?>
        </form>
        <?php
    }

}
