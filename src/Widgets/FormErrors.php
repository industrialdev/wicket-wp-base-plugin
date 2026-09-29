<?php

declare(strict_types=1);

namespace WicketWP\Widgets;

/**
 * Formats validation errors for the account widgets as whole translatable strings.
 */
final class FormErrors
{
    /**
     * "Field: message", e.g. "First name: can't be blank". Plain text; escape on output.
     * API error titles are looked up in the plugin domain too, so they can be translated.
     */
    public static function field_message(string $label, string $message): string
    {
        /* translators: 1: form field name, 2: validation message for that field. */
        return sprintf(_x('%1$s: %2$s', 'message', 'wicket-base'), $label, translate($message, 'wicket-base'));
    }

    /**
     * One escaped <li> for the error summary, linking to the field.
     */
    public static function list_item(string $anchor, int $number, string $label, string $message): string
    {
        /* translators: %d: error number in the list. */
        $heading = sprintf(_x('Error: %d', 'label', 'wicket-base'), $number);

        return sprintf(
            "<li><a href='#%s'><strong>%s</strong> %s</a></li>",
            esc_attr($anchor),
            esc_html($heading),
            esc_html(self::field_message($label, $message))
        );
    }
}
