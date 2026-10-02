<?php

declare(strict_types=1);

namespace WicketWP\Widgets;

/*
 * Wicket Create Account
 * Description: Providies a widget to sign up to Wicket as a person
 *
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/*
 * The widget class
 * http://www.wpexplorer.com/create-widget-plugin-wordpress
 */
class CreateAccount extends \WP_Widget
{
    public $errors;

    // Main constructor
    public function __construct()
    {
        parent::__construct(
            'wicket_create_account',
            _x('Wicket Create Account', 'widget name', 'wicket-base'),
            [
                'customize_selective_refresh' => true,
            ]
        );
    }

    public static function init()
    {
        add_action('init', function () {
            if (isset($_POST['wicket_create_account'])) {
                // Get the widget instance that's registered
                global $wp_widget_factory;
                if (isset($wp_widget_factory->widgets['wicket_create_account'])) {
                    $widget = $wp_widget_factory->widgets['wicket_create_account'];
                    $widget->process_wicket_create_account_form();
                } else {
                    // Fallback to creating a new instance
                    $widget = new self();
                    $widget->process_wicket_create_account_form();
                }
            }
        });
    }

    public function form($instance)
    {
        return $instance;
    }

    public function update($new_instance, $old_instance)
    {
        return $old_instance;
    }

    // Display the widget
    public function widget($args, $instance)
    {
        $client = wicket_api_client();
        if (!$client) {
            // If the API isn't up, display an error to admins.

            echo '<div class="alert alert-danger" role="alert">';
            echo '<strong>' . _x('Wicket Block Error:', 'label', 'wicket-base') . '</strong> ';
            echo __('Wicket API credentials are not configured. Please define them in your configuration to enable this block.', 'wicket-base');
            echo '</div>';

            return;
        }
        $this->build_form();
    }

    /*
    * Google Captcha
    *
    */
    public function wicket_check_google_captcha()
    {
        if (!isset($_POST['g-recaptcha-response'])) {
            return false;
        }
        $ch = curl_init();
        $secret = wicket_get_option('wicket_admin_settings_google_captcha_secret_key');
        $response = $_POST['g-recaptcha-response'];
        $remoteip = $_SERVER['REMOTE_ADDR'];

        curl_setopt($ch, CURLOPT_URL, 'https://www.google.com/recaptcha/api/siteverify');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            "secret=$secret&response=$response&remoteip=$remoteip"
        );

        // receive server response ...
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $google_response = curl_exec($ch);
        curl_close($ch);

        // curl_exec returns false on transport failure; json_decode demands a string.
        if (!is_string($google_response)) {
            return false;
        }

        $decoded = json_decode($google_response);

        return (bool) ($decoded->success ?? false);
    }

    /*
    * Process Form
    *
    */
    public function process_wicket_create_account_form()
    {
        $errors = [];

        if (!headers_sent()) {
            if (!session_id()) {
                session_start();
            }
        }

        if (isset($_POST['wicket_create_account'])) {
            $client = wicket_api_client();
            /**------------------------------------------------------------------
             * Create Account
            ------------------------------------------------------------------*/
            $first_name = sanitize_text_field($_POST['given_name'] ?? '');
            $last_name = sanitize_text_field($_POST['family_name'] ?? '');
            $email = sanitize_email($_POST['address'] ?? '');
            $password = $_POST['password'] ?? '';
            $password_confirmation = $_POST['password_confirmation'] ?? '';

            if ($first_name == '') {
                $first_name_blank = new \stdClass();
                $first_name_blank->meta = (object) ['field' => 'user.given_name'];
                $first_name_blank->title = _x("can't be blank", 'message', 'wicket-base');
                $errors[] = $first_name_blank;
            }
            if ($last_name == '') {
                $last_name_blank = new \stdClass();
                $last_name_blank->meta = (object) ['field' => 'user.family_name'];
                $last_name_blank->title = _x("can't be blank", 'message', 'wicket-base');
                $errors[] = $last_name_blank;
            }
            if ($email == '') {
                $email_blank = new \stdClass();
                $email_blank->meta = (object) ['field' => 'emails.address'];
                $email_blank->title = _x("can't be blank", 'message', 'wicket-base');
                $errors[] = $email_blank;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $email_invalid = new \stdClass();
                $email_invalid->meta = (object) ['field' => 'emails.address'];
                $email_invalid->title = __('must be a valid email address', 'wicket-base');
                $errors[] = $email_invalid;
            }
            if (strlen($password) < 8) {
                $pass_blank = new \stdClass();
                $pass_blank->meta = (object) ['field' => 'user.password'];
                $pass_blank->title = __('must be a minimum of 8 characters', 'wicket-base');
                $errors[] = $pass_blank;
            }
            if ($password == '') {
                $pass_blank = new \stdClass();
                $pass_blank->meta = (object) ['field' => 'user.password'];
                $pass_blank->title = _x("can't be blank", 'message', 'wicket-base');
                $errors[] = $pass_blank;
            }
            if ($password_confirmation == '') {
                $confirm_pass_blank = new \stdClass();
                $confirm_pass_blank->meta = (object) ['field' => 'user.password_confirmation'];
                $confirm_pass_blank->title = _x("can't be blank", 'message', 'wicket-base');
                $errors[] = $confirm_pass_blank;
            }
            if ($password_confirmation != $password) {
                $pass_blank = new \stdClass();
                $pass_blank->meta = (object) ['field' => 'user.password'];
                $pass_blank->title = __('passwords do not match', 'wicket-base');
                $errors[] = $pass_blank;
            }
            $enable_google_captcha = wicket_get_option('wicket_admin_settings_google_captcha_enable');
            if ($enable_google_captcha === '1') {
                $passes_google_check = $this->wicket_check_google_captcha();
                if (!$passes_google_check) {
                    $errors[] = (object) [
                        'title' => __('please complete the captcha below', 'wicket-base'),
                        'meta' => (object) [
                            'field' => 'google',
                        ],
                    ];
                }
            }
            $_SESSION['wicket_create_account_form_errors'] = $errors;

            // don't send anything if errors
            if (empty($errors)) {
                $new_person = wicket_create_person($first_name, $last_name, $email, $password, $password_confirmation);

                if (isset($new_person['errors'])) {
                    foreach ($new_person['errors'] as $error) {
                        if ($error->meta->error == 'taken' && $error->meta->field == 'emails.address' && $error->meta->claimable == 1) {
                            // build person payload
                            $payload = [
                                'data' => [
                                    'type' => 'people',
                                    'attributes' => [
                                        'given_name' => $first_name,
                                        'family_name' => $last_name,
                                        'user' => [
                                            'password' => $password,
                                            'password_confirmation' => $password_confirmation,
                                        ],
                                    ],
                                ],
                            ];

                            try {
                                $patch_person = $client->patch('people/' . $error->meta->taken_by->id, ['json' => $payload]);
                                break;
                            } catch (\Throwable $th) {
                                $_SESSION['wicket_create_account_form_errors'] = $new_person['errors'];
                            }
                        } else {
                            $_SESSION['wicket_create_account_form_errors'] = $new_person['errors'];
                        }
                    }
                }
                /**------------------------------------------------------------------
                 * Redirect to a verify page if person was created
                ------------------------------------------------------------------*/
                if (empty($_SESSION['wicket_create_account_form_errors'])) {
                    unset($_SESSION['wicket_create_account_form_errors']);

                    // Allow devs to hook in once new person exists
                    do_action('after_person_create_account', $new_person);

                    // Try to get the configured redirect page
                    $creation_redirect_id = wicket_get_option('wicket_admin_settings_person_creation_redirect');
                    $creation_redirect_path = $creation_redirect_id ? get_permalink($creation_redirect_id) : false;

                    // Fallback if missing
                    if (!$creation_redirect_path) {
                        $creation_redirect_path = site_url('/verify-account');
                        \Wicket()->log()->warning('[wicket-create-account] Redirect option missing or invalid, falling back to /verify-account. Please set this under Wicket -> General -> New Account Redirect', ['source' => 'wicket-base']);
                    }

                    ob_clean();
                    wp_safe_redirect($creation_redirect_path);
                    exit;
                }
            }
        } else {
            if (isset($_SESSION['wicket_create_account_form_errors'])) {
                unset($_SESSION['wicket_create_account_form_errors']);
            }
        }
    }

    private function build_form()
    {
        if (!isset($_POST['wicket_create_account'])) {
            if (isset($_SESSION['wicket_create_account_form_errors'])) {
                unset($_SESSION['wicket_create_account_form_errors']);
            }
        }
        ?>
    <div class="wicket-base-plugin-form">
        <script src='https://www.google.com/recaptcha/api.js?hl=en'></script>
        <?php if (isset($_SESSION['wicket_create_account_form_errors']) && !empty($_SESSION['wicket_create_account_form_errors'])) : ?>
            <div class='alert alert-danger' role="alert">
                <p><?php /* translators: %s: number of errors in the form. */ printf(_n('The form could not be submitted because %s error was found', 'The form could not be submitted because %s errors were found', count($_SESSION['wicket_create_account_form_errors']), 'wicket-base'), number_format_i18n(count($_SESSION['wicket_create_account_form_errors']))); ?></p>
                <?php
                        $fields = [
                'user.given_name' => ['given_name', _x('First name', 'label', 'wicket-base')],
                'user.family_name' => ['family_name', _x('Last name', 'label', 'wicket-base')],
                'emails.address' => ['address', _x('Email', 'label', 'wicket-base')],
                'user.password' => ['password', _x('Password', 'label', 'wicket-base')],
                'user.password_confirmation' => ['password_confirmation', _x('Confirm password', 'label', 'wicket-base')],
                'google' => ['google', _x('Captcha', 'label', 'wicket-base')],
            ];
            $counter = 1;
            echo '<ul>';
            foreach ($_SESSION['wicket_create_account_form_errors'] as $error) {
                $field = $error->meta->field ?? '';
                if (isset($fields[$field])) {
                    echo FormErrors::list_item($fields[$field][0], $counter, $fields[$field][1], (string) $error->title);
                }
                $counter++;
            }
            echo '</ul>';
            ?>
            </div>
        <?php elseif (isset($_GET['success'])) : ?>
            <div class='alert alert--success'>
                <p><?php _ex('Successfully Created', 'message', 'wicket-base'); ?></p>
            </div>
        <?php endif; ?>

        <form class='manage_password_form' method="post">
            <div class="form__group">
                <label class="form__label" for="given_name"><?php _ex('First name', 'label', 'wicket-base') ?>
                    <span class="required" aria-label="<?php _ex('Required', 'accessibility label', 'wicket-base') ?>">*</span>
                    <?php
                if (isset($_SESSION['wicket_create_account_form_errors']) && !empty($_SESSION['wicket_create_account_form_errors'])) {
                    foreach ($_SESSION['wicket_create_account_form_errors'] as $key => $error) {
                        if (isset($error->meta->field) && $error->meta->field == 'user.given_name') {
                            $given_name_err = true;
                        }
                    }
                }
        ?>
                </label>
                <input class="form__input <?php echo isset($given_name_err) ? 'error_input' : '' ?>" required type="text" id="given_name" name="given_name" value="<?php echo esc_attr($_POST['given_name'] ?? '') ?>">
            </div>

            <div class="form__group">
                <label class="form__label" for="family_name"><?php _ex('Last name', 'label', 'wicket-base') ?>
                    <span class="required" aria-label="<?php _ex('Required', 'accessibility label', 'wicket-base') ?>">*</span>
                    <?php
        if (isset($_SESSION['wicket_create_account_form_errors']) && !empty($_SESSION['wicket_create_account_form_errors'])) {
            foreach ($_SESSION['wicket_create_account_form_errors'] as $key => $error) {
                if (isset($error->meta->field) && $error->meta->field == 'user.family_name') {
                    $last_name_err = true;
                }
            }
        }
        ?>
                </label>
                <input class="form__input <?php echo isset($last_name_err) ? 'error_input' : '' ?>" required type="text" id="family_name" name="family_name" value="<?php echo esc_attr($_POST['family_name'] ?? '') ?>">
            </div>

            <div class="form__group">
                <label class="form__label" for="address"><?php _ex('Email', 'label', 'wicket-base') ?>
                    <span class="required" aria-label="<?php _ex('Required', 'accessibility label', 'wicket-base') ?>">*</span>
                    <?php
        if (isset($_SESSION['wicket_create_account_form_errors']) && !empty($_SESSION['wicket_create_account_form_errors'])) {
            foreach ($_SESSION['wicket_create_account_form_errors'] as $key => $error) {
                if (isset($error->meta->field) && $error->meta->field == 'emails.address') {
                    $address_err = true;
                }
            }
        }
        ?>
                </label>
                <input class="form__input <?php echo isset($address_err) ? 'error_input' : '' ?>" required type="text" id="address" name="address" value="<?php echo esc_attr($_POST['address'] ?? '') ?>">
            </div>

            <div class="form__group">
                <label class="form__label" for="password"><?php _ex('Password', 'label', 'wicket-base') ?>
                    <span class="required" aria-label="<?php _ex('Required', 'accessibility label', 'wicket-base') ?>">*</span>
                    <?php
        if (isset($_SESSION['wicket_create_account_form_errors']) && !empty($_SESSION['wicket_create_account_form_errors'])) {
            foreach ($_SESSION['wicket_create_account_form_errors'] as $key => $error) {
                if (isset($error->meta->field) && $error->meta->field == 'user.password') {
                    $password_err = true;
                }
            }
        }
        ?>
                </label>
                <small id='create_account_form_minimum_char_message'><?php _e('Minimum of 8 characters', 'wicket-base') ?></small>
                <input class="form__input <?php echo isset($password_err) ? 'error_input' : '' ?>" required type="password" name="password" id="password" value="">
            </div>

            <div class="form__group">
                <label class="form__label" for="password_confirmation"><?php _ex('Confirm password', 'label', 'wicket-base') ?>
                    <span class="required" aria-label="<?php _ex('Required', 'accessibility label', 'wicket-base') ?>">*</span>
                    <?php
        if (isset($_SESSION['wicket_create_account_form_errors']) && !empty($_SESSION['wicket_create_account_form_errors'])) {
            foreach ($_SESSION['wicket_create_account_form_errors'] as $key => $error) {
                if (isset($error->meta->field) && $error->meta->field == 'user.password_confirmation') {
                    $password_confirm_err = true;
                }
            }
        }
        ?>
                </label>
                <input class="form__input <?php echo isset($password_confirm_err) ? 'error_input' : '' ?>" type="password" id="password_confirmation" name="password_confirmation" value="">
            </div>

            <a name="google"></a>
            <?php
            $enable_google_captcha = wicket_get_option('wicket_admin_settings_google_captcha_enable');
        $recaptcha_key = wicket_get_option('wicket_admin_settings_google_captcha_key');
        if (($enable_google_captcha === '1') && $recaptcha_key) :
            ?>
                <div class="g-recaptcha" data-sitekey="<?php echo $recaptcha_key ?>"></div>
            <?php endif; ?>
            <input type="hidden" name="wicket_create_account" value="<?php echo $this->id_base . '-' . $this->number; ?>" />

            <?php
                get_component('button', [
                    'label'    => _x('Submit', 'button label', 'wicket-base'),
                    'type'    => 'submit',
                    'variant' => 'primary',
                ]);
        ?>
        </form>
    </div>
<?php
    }
}
