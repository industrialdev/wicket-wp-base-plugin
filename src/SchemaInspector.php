<?php

declare(strict_types=1);

namespace WicketWP;

// No direct access
defined('ABSPATH') || exit;

/**
 * Hidden dev tool: browser view of the MDP json_schemas for the connected tenant.
 *
 *   /?mdp_schemas                  HTML list of all schemas
 *   /?mdp_schemas&schema=<id>      raw JSON of one schema (uuid, slug, or key)
 *
 * Capability-gated to manage_options. Anyone else gets a 404 so the URL
 * neither works for them nor confirms its own existence.
 */
class SchemaInspector
{
    private const QUERY_VAR = 'mdp_schemas';

    private const SCHEMA_PARAM = 'schema';

    /**
     * Instance of the Main class.
     *
     * @var Main|null
     */
    private $main;

    public function __construct(?Main $main = null)
    {
        $this->main = $main;
    }

    /**
     * Register hooks.
     *
     * @return void
     */
    public function init(): void
    {
        add_action('template_redirect', [$this, 'maybeRender']);
    }

    /**
     * Handle the inspector URL when present.
     *
     * Runs on template_redirect: fires on every permalink config, before any
     * template output, with current_user_can() already reliable.
     *
     * @return void
     */
    public function maybeRender(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tool, capability-gated below
        if (!isset($_GET[self::QUERY_VAR])) {
            return;
        }

        nocache_headers();

        if (!current_user_can('manage_options')) {
            status_header(404);
            exit;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- see above
        $identifier = isset($_GET[self::SCHEMA_PARAM]) ? sanitize_text_field(wp_unslash((string) $_GET[self::SCHEMA_PARAM])) : '';

        if ($identifier !== '') {
            $this->renderSchemaJson($identifier);
        }

        $this->renderIndex();
    }

    /**
     * All schema resources for the tenant, shape-normalized.
     *
     * wicket_get_schemas() returns the {data: [...]} envelope from the MDP,
     * but a bare resource list is accepted too (see atlas quirk
     * mdp-json-schemas-field-enumeration.md).
     *
     * @return array<int, array>
     */
    private function schemas(): array
    {
        $response = wicket_get_schemas();

        $resources = $response['data'] ?? $response;

        if (!is_array($resources)) {
            return [];
        }

        return array_values(array_filter($resources, 'is_array'));
    }

    /**
     * Find one schema by uuid, slug, or key.
     *
     * @param array<int, array> $schemas
     * @param string            $identifier
     * @return array|null
     */
    private function findSchema(array $schemas, string $identifier): ?array
    {
        foreach ($schemas as $resource) {
            if (
                ($resource['id'] ?? null) === $identifier
                || ($resource['attributes']['slug'] ?? null) === $identifier
                || ($resource['attributes']['key'] ?? null) === $identifier
            ) {
                return $resource;
            }
        }

        return null;
    }

    /**
     * Output one schema as pretty JSON.
     *
     * @param string $identifier
     * @return never
     */
    private function renderSchemaJson(string $identifier): void
    {
        $resource = $this->findSchema($this->schemas(), $identifier);

        header('Content-Type: application/json; charset=utf-8');

        if ($resource === null) {
            status_header(404);
            echo wp_json_encode(['error' => 'schema_not_found', 'identifier' => $identifier]);

            exit;
        }

        echo wp_json_encode($resource, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        exit;
    }

    /**
     * Output the HTML index of all schemas, grouped by resource type.
     *
     * @return never
     */
    private function renderIndex(): void
    {
        $schemas = $this->schemas();

        if ($schemas === []) {
            \Wicket()->log('error', 'MDP schema inspector: no schemas returned by the API', ['source' => __CLASS__]);
            wp_die('No MDP JSON schemas were returned by the API.');
        }

        // API index already sorts resource_type asc, weight asc; keep its order inside groups.
        $groups = [];
        foreach ($schemas as $resource) {
            $groups[(string) ($resource['attributes']['resource_type'] ?? 'unknown')][] = $resource;
        }
        ksort($groups);

        header('Content-Type: text/html; charset=utf-8');

        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>MDP Schemas</title><style>';
        echo 'body{font-family:monospace;margin:2rem}h1{font-size:1.2rem}h2{font-size:1rem;margin:1.5rem 0 .5rem}';
        echo 'table{border-collapse:collapse}th,td{border:1px solid #ccc;padding:.3rem .6rem;text-align:left}';
        echo 'td.id{color:#888}</style></head><body>';
        echo '<h1>MDP JSON Schemas (' . count($schemas) . ')</h1>';

        foreach ($groups as $type => $resources) {
            echo '<h2>' . esc_html($type) . ' (' . count($resources) . ')</h2><table><tr><th>Slug</th><th>Title</th><th>UUID</th><th></th></tr>';

            foreach ($resources as $resource) {
                $attributes = $resource['attributes'] ?? [];
                $slug = (string) ($attributes['slug'] ?? $attributes['key'] ?? '');
                $title = (string) ($attributes['schema']['title'] ?? '');
                $uuid = (string) ($resource['id'] ?? '');
                $url = add_query_arg([self::QUERY_VAR => '1', self::SCHEMA_PARAM => $uuid], home_url('/'));

                echo '<tr><td>' . esc_html($slug) . '</td><td>' . esc_html($title) . '</td>';
                echo '<td class="id">' . esc_html($uuid) . '</td>';
                echo '<td><a href="' . esc_url($url) . '">view JSON</a></td></tr>';
            }

            echo '</table>';
        }

        echo '</body></html>';

        exit;
    }
}
