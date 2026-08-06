<?php

declare(strict_types=1);

namespace WicketWP\Hypermedia;

// No direct access
defined('ABSPATH') || exit;

use starfederation\datastar\ServerSentEventGenerator;

/**
 * Thin Datastar SSE response facade for base-plugin.
 *
 * Delegates to the datastar-php SDK (vendored by wicket-wp-account-centre) so
 * the wire format stays exactly correct: per-line splitting of multi-line
 * element HTML, selector and mode handling, and post-event flush. This keeps
 * base-plugin call sites clean and self-contained while the experiment assumes
 * account-centre is active.
 *
 * Typical adapter usage:
 *
 *   SSE::patchElements($html, ['selector' => '#region', 'mode' => 'inner']);
 *   SSE::patchSignals(['orgss_1.loading' => false]);
 *   SSE::done();
 *
 * `done()` exits so WP REST does not re-serialize the response.
 */
class SSE
{
    private static ?ServerSentEventGenerator $generator = null;

    private static function generator(): ServerSentEventGenerator
    {
        if (self::$generator === null) {
            // Constructor arms ignore_user_abort(false); sendHeaders() is a
            // no-op if headers were already sent.
            self::$generator = new ServerSentEventGenerator();
            self::$generator->sendHeaders();
        }

        return self::$generator;
    }

    public static function patchSignals(array $signals, array $options = []): void
    {
        self::generator()->patchSignals($signals, $options);
    }

    public static function patchElements(string $elements, array $options = []): void
    {
        self::generator()->patchElements($elements, $options);
    }

    public static function removeElements(string $selector, array $options = []): void
    {
        self::generator()->removeElements($selector, $options);
    }

    public static function executeScript(string $script, array $options = []): void
    {
        self::generator()->executeScript($script, $options);
    }

    public static function location(string $uri, array $options = []): void
    {
        self::generator()->location($uri, $options);
    }

    /**
     * End the SSE response. Adapters call this last.
     */
    public static function done(): void
    {
        exit;
    }
}
