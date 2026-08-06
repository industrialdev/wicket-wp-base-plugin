<?php

declare(strict_types=1);

namespace WicketWP;

// No direct access
defined('ABSPATH') || exit;

/**
 * Experimental Datastar org-search-select variant controller.
 *
 * Active only when the 'wicket_orgss_variant' filter returns 'datastar'. In the
 * default ('alpine') state it registers nothing.
 *
 * The variant builds on HyperPress-Core (estebanforge/hyperpress-core), which
 * base-plugin now requires. This class wires the variant into HyperPress:
 *
 *   - registers base-plugin's hypermedia/ templates under the 'wicket:' namespace
 *     so the variant's @get targets resolve to them via HyperPress's router
 *     (/wp-html/v1/wicket:<template>);
 *   - forces HyperPress's active library to 'datastar' while the experiment is
 *     on, so the Datastar runtime and the auto-nonce fetch wrapper load on the
 *     pages where the variant renders.
 *
 * The variant UI (includes/components/org-search-select-datastar.php) and the
 * endpoint templates (hypermedia/*.hp.php) do the real work. HyperPress owns the
 * SSE helpers (hp_ds_*), the nonce, and the client enqueue, so this class and
 * the endpoint templates stay small.
 *
 * @see docs/org-search-select-datastar-migration-spec.md
 */
class OrgssDatastar
{
    public function init(): void
    {
        if (apply_filters('wicket_orgss_variant', 'alpine') !== 'datastar') {
            return;
        }

        add_filter('hyperpress/render/register_template_path', [$this, 'register_template_path']);
        add_filter('hyperpress/options', [$this, 'force_datastar_library']);
    }

    /**
     * Register base-plugin's hypermedia/ directory under the 'wicket' namespace.
     * Endpoint templates resolve as 'wicket:<name>' -> hypermedia/<name>.hp.php.
     */
    public function register_template_path(array $paths): array
    {
        $paths['wicket'] = dirname(__DIR__) . '/hypermedia';

        return $paths;
    }

    /**
     * Force the Datastar runtime while the experiment is active. HyperPress
     * applies the 'hyperpress/options' filter last, so this wins over stored
     * options without touching the database.
     */
    public function force_datastar_library(array $options): array
    {
        $options['active_library'] = 'datastar';

        return $options;
    }
}
