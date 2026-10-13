<?php

declare(strict_types=1);

namespace WicketWP;

/**
 * Language class
 * Loads the 'wicket-base' text domain and falls back to the legacy 'wicket' domain.
 */
class Language
{
    public const DOMAIN = 'wicket-base';

    public const LEGACY_DOMAIN = 'wicket';

    /**
     * Reference to Main.
     *
     * @var Main
     */
    protected $main;

    public function __construct(Main $main)
    {
        $this->main = $main;
    }

    /**
     * Register the text domain loader and the legacy fallback filters.
     */
    public function init()
    {
        add_action('init', [$this, 'load_textdomain'], 1);

        // Sites translated under the old 'wicket' domain keep working until they move to 'wicket-base'.
        add_filter('gettext_' . self::DOMAIN, [$this, 'fallback_gettext'], 10, 2);
        add_filter('gettext_with_context_' . self::DOMAIN, [$this, 'fallback_gettext_with_context'], 10, 3);
        add_filter('ngettext_' . self::DOMAIN, [$this, 'fallback_ngettext'], 10, 4);
        add_filter('ngettext_with_context_' . self::DOMAIN, [$this, 'fallback_ngettext_with_context'], 10, 5);
    }

    /**
     * Load bundled translations from the plugin's languages folder.
     */
    public function load_textdomain()
    {
        load_plugin_textdomain(self::DOMAIN, false, dirname(WICKET_BASENAME) . '/languages');
    }

    public function fallback_gettext($translation, $text)
    {
        return $translation === $text ? translate($text, self::LEGACY_DOMAIN) : $translation;
    }

    public function fallback_gettext_with_context($translation, $text, $context)
    {
        return $translation === $text ? translate_with_gettext_context($text, $context, self::LEGACY_DOMAIN) : $translation;
    }

    public function fallback_ngettext($translation, $single, $plural, $number)
    {
        return $translation === ($number == 1 ? $single : $plural) ? _n($single, $plural, $number, self::LEGACY_DOMAIN) : $translation;
    }

    public function fallback_ngettext_with_context($translation, $single, $plural, $number, $context)
    {
        return $translation === ($number == 1 ? $single : $plural) ? _nx($single, $plural, $number, $context, self::LEGACY_DOMAIN) : $translation;
    }
}
