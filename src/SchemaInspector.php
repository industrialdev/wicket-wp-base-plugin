<?php

/**
 * Hidden browser for tenant MDP json_schemas.
 *
 * /?mdp_schemas                      HTML list grouped by resource type.
 * /?mdp_schemas&schema=<uuid|slug|key>  One schema as pretty JSON.
 * /?mdp_schemas&mdp_schemas_refresh=1   Bypass the transient cache.
 *
 * Gate: manage_options. Anyone else never enters this class, so the URL
 * behaves like any other query var (normal page) and does not announce
 * itself. Responses carry no-cache headers plus DONOTCACHEPAGE so page
 * caches cannot store an admin view.
 *
 * Data comes straight from the MDP API with the site's service-person
 * token: any WP admin reads schemas with that person's rights, not their
 * own. On multisite every subsite admin passes manage_options.
 */

declare(strict_types=1);

namespace WicketWP;

defined('ABSPATH') || exit;

/**
 * Renders the hidden MDP schema inspector.
 */
class SchemaInspector
{
    /**
     * Transient key holding the fetched schema list.
     */
    private const CACHE_KEY = 'wicket_mdp_schemas';

    /**
     * Cache lifetime in seconds.
     */
    private const CACHE_TTL = 300;

    /**
     * Page size for the paginated json_schemas fetch.
     */
    private const PAGE_SIZE = 100;

    /**
     * Hard cap on pages fetched per refresh.
     */
    private const MAX_PAGES = 10;

    /**
     * Register hooks.
     *
     * @return self
     */
    public function init(): self
    {
        add_action('template_redirect', [$this, 'maybe_render']);

        return $this;
    }

    /**
     * Render the inspector when the query var is present and the user is an admin.
     *
     * @return void
     */
    public function maybe_render(): void
    {
        if (!isset($_GET['mdp_schemas']) || !is_string($_GET['mdp_schemas'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            // Render the normal page: no 404, no fingerprint.
            return;
        }

        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        nocache_headers();
        @header('Vary: Cookie');
        @header('X-Robots-Tag: noindex, nofollow');

        $identifier = $_GET['schema'] ?? null;
        if (!is_string($identifier) || $identifier === '') {
            $this->render_index();
            exit;
        }

        $this->render_single(wp_unslash($identifier));
        exit;
    }

    /**
     * Fetch and normalize the tenant schema list.
     *
     * @return array|false Normalized resource list, or false when the API is unavailable.
     */
    private function schemas()
    {
        $refresh = isset($_GET['mdp_schemas_refresh']) && is_string($_GET['mdp_schemas_refresh']);
        if (!$refresh) {
            $cached = get_transient(self::CACHE_KEY);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $raw = $this->fetch_all();
        if ($raw === false) {
            return false;
        }

        $resources = $raw['data'] ?? $raw;
        if (!is_array($resources)) {
            \Wicket()->log('error', 'SchemaInspector: unexpected json_schemas payload shape.');

            return false;
        }

        set_transient(self::CACHE_KEY, $resources, self::CACHE_TTL);

        return $resources;
    }

    /**
     * Fetch every page of json_schemas with a bounded timeout.
     *
     * @return array|false The raw API payload (envelope or list), or false on failure.
     */
    private function fetch_all()
    {
        $client = wicket_api_client();
        if (!$client) {
            \Wicket()->log('error', 'SchemaInspector: MDP API client unavailable.');

            return false;
        }

        $resources = [];
        try {
            for ($page = 1; $page <= self::MAX_PAGES; $page++) {
                $response = $client->get(
                    'json_schemas',
                    [
                        'query'           => [
                            'page[size]'   => self::PAGE_SIZE,
                            'page[number]' => $page,
                        ],
                        'timeout'         => 15,
                        'connect_timeout' => 5,
                    ]
                );

                $rows = $response['data'] ?? (is_array($response) ? $response : []);
                if (!is_array($rows)) {
                    break;
                }

                $resources = array_merge($resources, $rows);
                if (count($rows) < self::PAGE_SIZE) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            \Wicket()->log('error', 'SchemaInspector: json_schemas fetch failed: ' . $e->getMessage());

            return false;
        }

        return ['data' => $resources];
    }

    /**
     * Resolve one schema by UUID, slug, or key.
     *
     * @param string $identifier Lookup value.
     *
     * @return array|null The resource, or null when unmatched.
     */
    private function find_schema(string $identifier): ?array
    {
        foreach ($this->schemas() ?: [] as $resource) {
            $attributes = $resource['attributes'] ?? [];
            if (
                ($resource['id'] ?? null) === $identifier
                || ($attributes['slug'] ?? null) === $identifier
                || ($attributes['key'] ?? null) === $identifier
            ) {
                return $resource;
            }
        }

        return null;
    }

    /**
     * Output the single-schema JSON view.
     *
     * @param string $identifier Lookup value.
     *
     * @return void
     */
    private function render_single(string $identifier): void
    {
        @header('Content-Type: application/json; charset=utf-8');
        @header('X-Content-Type-Options: nosniff');

        $schema = $this->find_schema($identifier);
        if ($schema === null) {
            status_header(404);
            echo wp_json_encode(
                ['error' => 'schema_not_found'],
                JSON_UNESCAPED_SLASHES
            );
            exit;
        }

        $json = wp_json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            status_header(500);
            echo wp_json_encode(['error' => 'encode_failed']);
            exit;
        }

        status_header(200);
        echo $json;
        exit;
    }

    /**
     * Output the index view: all schemas grouped by resource type.
     *
     * @return void
     */
    private function render_index(): void
    {
        $schemas = $this->schemas();

        $style = <<<'CSS'
            body{font-family:monospace;margin:2rem}
            h1{font-size:1.2rem}
            h2{font-size:1rem;margin:1.5rem 0 .5rem}
            table{border-collapse:collapse}
            th,td{border:1px solid #ccc;padding:.3rem .6rem;text-align:left}
            td.id{color:#888}
            CSS;
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>MDP Schemas</title><style>' . $style . '</style></head><body>';

        if ($schemas === false) {
            echo '<h1>MDP Schemas</h1><p>The MDP API is unavailable. Retry, or add <code>&amp;mdp_schemas_refresh=1</code> after fixing the cause. Details are in the plugin log.</p></body></html>';

            return;
        }

        $groups = [];
        foreach ($schemas as $resource) {
            $type = $resource['attributes']['resource_type'] ?? null;
            $type = is_string($type) && $type !== '' ? $type : 'unknown';
            $groups[$type][] = $resource;
        }
        ksort($groups);

        $count = count($schemas);
        echo '<h1>MDP JSON Schemas (' . esc_html((string) $count) . ')</h1>';

        if ($count === 0) {
            echo '<p>No json_schemas exist for this tenant.</p></body></html>';

            return;
        }

        foreach ($groups as $type => $resources) {
            echo '<h2>' . esc_html($type) . ' (' . esc_html((string) count($resources)) . ')</h2>';
            echo '<table><tr><th>Slug</th><th>Title</th><th>UUID</th><th></th></tr>';
            foreach ($resources as $resource) {
                $attributes = $resource['attributes'] ?? [];
                $slug = is_string($attributes['slug'] ?? null) ? $attributes['slug'] : '';
                $title = is_string($attributes['title'] ?? null) ? $attributes['title'] : $slug;
                $uuid = is_string($resource['id'] ?? null) ? $resource['id'] : '';
                $url = add_query_arg(
                    [
                        'mdp_schemas' => '1',
                        'schema'      => $uuid,
                    ],
                    home_url('/')
                );
                echo '<tr>'
                    . '<td>' . esc_html($slug) . '</td>'
                    . '<td>' . esc_html($title) . '</td>'
                    . '<td class="id">' . esc_html($uuid) . '</td>'
                    . '<td><a href="' . esc_url($url) . '">view JSON</a></td>'
                    . '</tr>';
            }
            echo '</table>';
        }

        echo '<p style="color:#888">Cached ' . esc_html((string) self::CACHE_TTL) . 's. '
            . '<a href="' . esc_url(add_query_arg(['mdp_schemas' => '1', 'mdp_schemas_refresh' => '1'], home_url('/'))) . '">refresh from API</a></p>';
        echo '</body></html>';
    }
}
