<?php

declare(strict_types=1);

/**
 * WPSettings Custom Option - write-only secret field.
 *
 * @version  1.0.0
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/*
 * External dependencies
 */
use Jeffreyvr\WPSettings\Options\Text;

add_filter('wp_settings_option_type_map', function ($options) {
    $options['secret'] = WicketSecret::class;

    return $options;
});

/**
 * Write-only text option for credentials (API secrets, reCAPTCHA secret).
 *
 * The stock text view prints the stored value into value="", even for a
 * password input, so any script on the settings page can read it. This type
 * never sends the stored value to the browser. A blank submission keeps the
 * stored value, the same way WordPress handles user passwords.
 *
 * @see atlas://quirks/wp-settings-text-option-renders-stored-value.md
 */
class WicketSecret extends Text
{
    /**
     * Register the option and the save filter that keeps the stored value on a blank submit.
     *
     * @param mixed                $section WPSettings section instance.
     * @param array<string, mixed> $args    Option arguments (name, label, description, css).
     */
    public function __construct($section, $args = [])
    {
        parent::__construct($section, $args);

        $name = $this->get_name();
        if (!empty($name)) {
            add_filter('wp_settings_new_options_' . $name, [$this, 'keep_stored_value_when_blank'], 10, 2);
        }
    }

    /**
     * Never expose the stored value through the public value accessor.
     *
     * @return string Always an empty string.
     */
    public function get_value_attribute()
    {
        return '';
    }

    /**
     * Whether a non-empty value is already saved for this option.
     *
     * @return bool True when a value is stored.
     */
    public function has_stored_value(): bool
    {
        $stored = $this->get_stored_value();

        return is_scalar($stored) && (string) $stored !== '';
    }

    /**
     * Keep the stored secret when the field is submitted blank.
     *
     * Hooked to `wp_settings_new_options_{name}`, which WPSettings::save() fires
     * with the sanitized submitted value before it writes the option.
     *
     * @param mixed                                          $value  Sanitized submitted value.
     * @param \Jeffreyvr\WPSettings\Options\OptionAbstract $option Option implementation being saved.
     *
     * @return mixed The submitted value, or the stored value when the submission is blank.
     */
    public function keep_stored_value_when_blank($value, $option)
    {
        if ($option !== $this) {
            return $value;
        }

        if ($value !== null && $value !== '') {
            return $value;
        }

        return $this->get_stored_value() ?? $value;
    }

    /**
     * Render the option row as an empty password input.
     *
     * Renders its own markup because the WPSettings view() helper only loads
     * templates from the vendor package.
     *
     * @return void
     */
    public function render()
    {
        $id = $this->get_id_attribute();
        $description = $this->get_arg('description');
        $error = $this->has_error();

        // The ignore attributes stop password managers filling in the admin's
        // login here, which would overwrite the secret on save.
        ?>
      <tr valign="top">
        <th scope="row" class="titledesc">
          <label for="<?php echo esc_attr($id); ?>" class="<?php echo $this->get_label_class_attribute(); ?>"><?php echo $this->get_label(); ?></label>
        </th>
        <td class="forminp forminp-text forminp-secret">
          <input
            name="<?php echo esc_attr($this->get_name_attribute()); ?>"
            id="<?php echo esc_attr($id); ?>"
            type="password"
            value=""
            autocomplete="new-password"
            data-1p-ignore
            data-lpignore="true"
            data-bwignore
            class="<?php echo $this->get_input_class_attribute(); ?>">

          <?php if ($description) { ?>
            <p class="description"><?php echo wp_kses_post($description); ?></p>
          <?php } ?>

          <?php if ($this->has_stored_value()) { ?>
            <p class="description"><em><?php esc_html_e('A value is saved and hidden for security. Enter a new value to replace it, or leave this blank to keep it.', 'wicket'); ?></em></p>
          <?php } ?>

          <?php if ($error) { ?>
            <div class="wps-error-feedback"><?php echo wp_kses_post($error); ?></div>
          <?php } ?>
        </td>
      </tr>
    <?php
    }

    /**
     * Read the stored value for server-side checks only. Never print it.
     *
     * @return mixed Stored value, or the option default (null) when unset.
     */
    private function get_stored_value()
    {
        return parent::get_value_attribute();
    }
}
