<?php

declare(strict_types=1);

namespace WicketWP;

// No direct access
defined('ABSPATH') || exit;

use WicketWP\Hypermedia\SSE;

/**
 * Experimental Datastar endpoints and client enqueue for the org-search-select
 * variant.
 *
 * Active only when the 'wicket_orgss_variant' filter returns 'datastar'. In the
 * default ('alpine') state this class registers no routes and enqueues nothing,
 * so the shipped REST surface and frontend stay unchanged.
 *
 * Endpoints emit Datastar SSE via WicketWP\Hypermedia\SSE (which delegates to
 * the datastar-php SDK vendored by account-centre).
 *
 * Slice status:
 *   - ping:   transport proof.
 *   - search: live org search rendered server-side and morphed into the
 *             variant's results region. Select sets a signal only; relationship
 *             creation is a later slice.
 *
 * @see docs/org-search-select-datastar-migration-spec.md
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

        register_rest_route(self::NAMESPACE, 'orgss-ds/search', [
            'methods'             => 'GET',
            'callback'            => [$this, 'search'],
            'permission_callback' => static fn () => is_user_logged_in(),
        ]);
    }

    /**
     * Load the same Datastar runtime account centre uses, as a module script.
     */
    public function enqueue_client(): void
    {
        $src = 'https://cdn.jsdelivr.net/gh/starfederation/datastar@v'
            . self::DATASTAR_VERSION . '/bundles/datastar.js';
        wp_enqueue_script_module('wicket-orgss-datastar', $src, [], self::DATASTAR_VERSION);
    }

    /**
     * Transport proof: patch a signal and an element with a matching id.
     */
    public function ping(\WP_REST_Request $request): void
    {
        $stamp = gmdate('Y-m-d H:i:s');

        SSE::patchSignals(['orgssPingAt' => $stamp]);
        SSE::patchElements('<div id="orgss-ds-ping-target">pong @ ' . esc_html($stamp) . '</div>');

        SSE::done();
    }

    /**
     * Search adapter. Runs the same MDP search the JSON search_orgs handler
     * runs, then renders the results server-side and morphs the variant's
     * results region. Selecting a result only sets the selectedOrgUuid signal;
     * relationship creation lands in a later slice.
     */
    public function search(\WP_REST_Request $request): void
    {
        $term      = sanitize_text_field((string) ($request->get_param('searchTerm') ?? ''));
        $org_type  = sanitize_text_field((string) ($request->get_param('orgType') ?? ''));
        $lang      = sanitize_text_field((string) ($request->get_param('lang') ?? 'en'));
        $key       = sanitize_text_field((string) ($request->get_param('orgssKey') ?? ''));
        $display   = sanitize_text_field((string) ($request->get_param('display') ?? 'name'));
        $show_type = rest_sanitize_boolean($request->get_param('displayOrgType') ?? false);

        $target = $key !== '' ? '#orgss-results-' . $key : '#orgss-results';

        if (trim($term) === '') {
            SSE::patchElements(
                $this->render_message(__('Please provide a search term', 'wicket')),
                ['selector' => $target, 'mode' => 'inner']
            );
            SSE::done();
        }

        // Same call the JSON handler makes on the non-membership-summary path.
        $results = wicket_search_organizations($term, 'org_name', $org_type, false, $lang, false);

        if ($results === false) {
            SSE::patchElements(
                $this->render_message(__('There was a problem searching organizations.', 'wicket')),
                ['selector' => $target, 'mode' => 'inner']
            );
            SSE::done();
        }

        SSE::patchElements(
            $this->render_results((array) $results, $key, $display, $show_type),
            ['selector' => $target, 'mode' => 'inner']
        );

        SSE::done();
    }

    /**
     * Render the results list as HTML. Each row bakes its org id into reactive
     * Datastar expressions so the "selected" state updates without re-search.
     */
    private function render_results(array $results, string $key, string $display, bool $show_type): string
    {
        if (empty($results)) {
            return $this->render_message(
                __('Sorry, no organizations match your search. Please try again.', 'wicket')
            );
        }

        $ns = $key !== '' ? 'orgss_' . $key : 'orgss';

        ob_start();
        ?>
        <div class="component-org-search-select__results-list flex flex-col">
            <?php foreach ($results as $result) :
                $id = isset($result['id']) ? (string) $result['id'] : '';
                if ($id === '') {
                    continue;
                }

                $name      = isset($result['name']) ? (string) $result['name'] : '';
                $type_name = isset($result['type_name']) ? (string) $result['type_name'] : '';
                $subtitle  = $this->build_subtitle($result, $display);

                $select_expr     = $ns . ".selectedOrgUuid = '" . esc_js($id) . "'";
                $is_selected_expr = $ns . ".selectedOrgUuid === '" . esc_js($id) . "'";
                $text_expr       = $is_selected_expr . " ? '\u2713 " . esc_js(__('Selected', 'wicket')) . "' : '" . esc_js(__('Select', 'wicket')) . "'";
            ?>
                <div class="component-org-search-select__matching-org-item flex justify-between items-center px-1 py-3 border-b border-dark-100 border-opacity-5">
                    <div>
                        <div class="component-org-search-select__matching-org-title mb-1 font-bold"><?php echo esc_html($name); ?></div>
                        <?php if ($subtitle !== '') : ?>
                            <div class="component-org-search-select__matching-org-subtitle"><?php echo esc_html($subtitle); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if ($show_type && $type_name !== '') : ?>
                        <div class="component-org-search-select__matching-org-type"><?php echo esc_html($type_name); ?></div>
                    <?php endif; ?>
                    <div class="component-org-search-select__matching-org-action">
                        <button type="button"
                                class="component-org-search-select__select-result-button"
                                data-on:click="<?php echo esc_attr($select_expr); ?>"
                                data-text="<?php echo esc_attr($text_expr); ?>"
                                data-class:orgss_disabled_button="<?php echo esc_attr($is_selected_expr); ?>">
                            <?php esc_html_e('Select', 'wicket'); ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Build the location/address subtitle for a result, matching the Alpine
     * component's two display modes.
     */
    private function build_subtitle(array $result, string $display): string
    {
        if ($display === 'name') {
            return '';
        }

        $city    = (string) ($result['city'] ?? '');
        $state   = (string) ($result['state_name'] ?? '');
        $country = (string) ($result['country_code'] ?? '');

        if ($display === 'name_location') {
            $segments = array_filter([$city, $state, $country], fn ($v) => $v !== '');

            return implode(', ', $segments);
        }

        // name_address
        $address1 = (string) ($result['address1'] ?? '');
        $zip      = (string) ($result['zip_code'] ?? '');

        $state_zip = trim(($state !== '' ? $state : '') . ($zip !== '' ? ($state !== '' ? ' ' : '') . $zip : ''));
        $segments  = array_filter([$address1, $city, $state_zip, $country], fn ($v) => $v !== '');

        return implode(', ', $segments);
    }

    private function render_message(string $message): string
    {
        return '<div class="component-org-search-select__search-message">' . esc_html($message) . '</div>';
    }
}
