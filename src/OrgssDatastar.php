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

    /**
     * Create a person-to-organization connection, or reopen an existing one.
     *
     * Session-scoped: the person is always the authenticated user, never a
     * request-supplied UUID. Shared by the orgss-select and orgss-create-org
     * templates so the connection logic lives in one place. Returns true on
     * success, false on failure.
     */
    public static function createOrReopenConnection(string $person_uuid, string $org_uuid, string $connection_type, string $role): bool
    {
        if (!function_exists('wicket_find_person_org_connection')) {
            return false;
        }

        $existing = wicket_find_person_org_connection($person_uuid, $org_uuid, $connection_type, $role, true);

        if ($existing) {
            $connection_id = $existing['id'] ?? '';
            if ($connection_id === '' || !function_exists('wicket_update_connection_attributes')) {
                return false;
            }

            $updated = wicket_update_connection_attributes($connection_id, [
                'description' => null,
                'ends_at'     => null,
            ]);

            return $updated !== false;
        }

        if (!function_exists('wicket_create_connection')) {
            return false;
        }

        $starts_at = function_exists('wicket_time_get_current_iso8601_utc')
            ? wicket_time_get_current_iso8601_utc()
            : gmdate('c');

        $payload = [
            'data' => [
                'type'       => 'connections',
                'attributes' => [
                    'connection_type' => $connection_type,
                    'type'            => $role,
                    'starts_at'       => $starts_at,
                    'ends_at'         => null,
                    'description'     => null,
                    'tags'            => [],
                ],
                'relationships' => [
                    'from' => [
                        'data' => [
                            'type' => 'people',
                            'id'   => $person_uuid,
                            'meta' => ['can_manage' => false, 'can_update' => false],
                        ],
                    ],
                    'to' => [
                        'data' => ['type' => 'organizations', 'id' => $org_uuid],
                    ],
                ],
            ],
        ];

        try {
            $created = wicket_create_connection($payload);

            return !empty($created['data']['id']);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
