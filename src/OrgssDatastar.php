<?php

declare(strict_types=1);

namespace WicketWP;

// No direct access
defined('ABSPATH') || exit;

/**
 * Experimental Datastar endpoints and client enqueue for the org-search-select
 * variant.
 *
 * Active only when the 'wicket_orgss_variant' filter returns 'datastar'. In the
 * default ('alpine') state this class registers no routes and enqueues nothing,
 * so the shipped REST surface and frontend stay unchanged.
 *
 * Slice 0: a single ping endpoint that proves SSE transport end to end. The
 * variant shell calls it to confirm the wiring before any real UI is built.
 *
 * @see docs/org-search-select-datastar-migration-spec.md (slice 0)
 */
class OrgssDatastar
{
    private const DATASTAR_VERSION = '1.0.1';
    private const NAMESPACE = 'wicket-base/v1';

    public function init(): void
    {
        if (apply_filters('wicket_orgss_variant', 'alpine') !== 'datastar') {
            return;
        }

        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_client']);
    }

    public function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, 'orgss-ds/ping', [
            'methods'             => 'GET',
            'callback'            => [$this, 'ping'],
            'permission_callback' => static fn () => is_user_logged_in(),
        ]);
    }

    /**
     * Load the same Datastar runtime account centre uses, as a module script.
     * Called here (not from the component file) so it fires on wp_enqueue_scripts
     * regardless of where the variant renders on the page.
     */
    public function enqueue_client(): void
    {
        $src = 'https://cdn.jsdelivr.net/gh/starfederation/datastar@v'
            . self::DATASTAR_VERSION . '/bundles/datastar.js';
        wp_enqueue_script_module('wicket-orgss-datastar', $src, [], self::DATASTAR_VERSION);
    }

    /**
     * Trivial SSE response proving the transport: patches a signal, then an
     * element with a matching id (outer morph). Emits the stream and exits so
     * WP REST does not re-serialize the response.
     */
    public function ping(\WP_REST_Request $request): void
    {
        $this->send_sse_headers();

        $stamp      = gmdate('Y-m-d H:i:s');
        $signals    = wp_json_encode(['orgssPingAt' => $stamp]);
        $element    = '<div id="orgss-ds-ping-target">pong @ ' . esc_html($stamp) . '</div>';

        // Patch a signal.
        echo "event: datastar-patch-signals\n";
        echo 'data: signals ' . $signals . "\n";
        echo "\n";

        // Patch the element with matching id (outer morph is the default mode).
        echo "event: datastar-patch-elements\n";
        echo 'data: elements ' . $element . "\n";
        echo "\n";

        exit;
    }

    private function send_sse_headers(): void
    {
        if (ob_get_level() > 0) {
            ob_clean();
        }

        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-transform');
        header('X-Accel-Buffering: no');
    }
}
