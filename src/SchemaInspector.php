<?php

/**
 * Hidden browser for tenant MDP data relevant to form building.
 *
 * /?mdp_schemas                            HTML index: json_schemas grouped by scope (people, organizations, ...).
 * /?mdp_schemas&schema=<uuid|slug|key>     One schema as pretty JSON.
 * /?mdp_schemas&schema=<id>&view=fields    One schema's GF-mappable fields (slug, label, type, enums).
 * /?mdp_schemas&resource_types=1           Resource type list values (address/phone/web types, genders, ...).
 * /?mdp_schemas&memberships=1              Membership tier definitions (slug, code, category, flags).
 * /?mdp_schemas&communications=1           Communications preferences config (email + sublists).
 * /?mdp_schemas&mdp_api=1                  API data explorer: every top-level v1 index
 *                                          endpoint with its served attribute names,
 *                                          value-shape types, and relationships.
 * /?mdp_schemas&mdp_api=people             One endpoint's full derived shape.
 * /?mdp_schemas&mdp_api=1&format=json      The explorer metadata as one JSON download
 *                                          (agent-feed friendly).
 * /?mdp_schemas&mdp_api=1&format=csv       The explorer metadata as one CSV download.
 * /?mdp_schemas&format=csv                 All of the above slugs as one CSV download.
 * /?mdp_schemas&mdp_schemas_refresh=1      Bypass the transient caches.
 *
 * Scope: a schema's person/organization binding lives in its
 * json_schema_resources relationship (resource_type people, organizations,
 * orders, groups, connections...), not in schema attributes. The index groups
 * by it, the fields view prints it, and the CSV puts it in the json_schema
 * row's extra column.
 *
 * The CSV mirrors the per-client slug reference format (kind,list,name,slug,extra)
 * produced by MDP configuration work: json_schema, json_schema_property,
 * membership, resource_type, comm, and comm_sublist rows. Store it per client
 * and feed it to form-building: choice values, wicket_field_slug values, and
 * data_field.<schema_slug>.<property> targets all come from these rows.
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
     * Transient key holding the fetched resource type list.
     */
    private const RESOURCE_TYPE_CACHE_KEY = 'wicket_mdp_resource_types';

    /**
     * Transient key holding the fetched membership tier list.
     */
    private const MEMBERSHIP_CACHE_KEY = 'wicket_mdp_memberships';

    /**
     * Transient key holding the fetched communications config.
     */
    private const COMMUNICATIONS_CACHE_KEY = 'wicket_mdp_communications';

    /**
     * Transient key mapping schema UUID to its json_schema_resources
     * resource_type keys (people, organizations, groups, ...).
     */
    private const SCOPES_CACHE_KEY = 'wicket_mdp_schema_scopes';

    /**
     * Transient key holding the API explorer metadata map
     * (path => attributes/types/relationships/count).
     */
    private const EXPLORER_CACHE_KEY = 'wicket_mdp_api_explorer';

    /**
     * Top-level v1 index endpoints the explorer probes, grouped for the
     * table. Paths are API paths; labels are human. Nested-only resources
     * (addresses, phones, emails, web_addresses, leaves, per-record comments
     * and orders, statements under subscriptions, webhook attempts) are
     * listed as a note because their indexes need a parent id.
     */
    private const EXPLORER_ENDPOINTS = [
        'Directory' => [
            'people'            => 'People',
            'organizations'     => 'Organizations',
            'connections'       => 'Connections',
            'groups'            => 'Groups',
            'group_members'     => 'Group members',
            'person_templates'  => 'Person templates',
            'comments'          => 'Comments',
            'todos'             => 'Todos',
        ],
        'Membership' => [
            'memberships'                      => 'Membership tiers',
            'person_memberships'               => 'Person memberships',
            'organization_memberships'         => 'Organization memberships',
            'membership_bundles'               => 'Membership bundles',
            'person_member_histories'          => 'Person member histories',
            'organization_membership_histories' => 'Organization member histories',
            'intervals'                        => 'Intervals',
        ],
        'Billing' => [
            'orders'              => 'Orders',
            'variants'            => 'Variants',
            'subscriptions'       => 'Subscriptions',
            'fees'                => 'Fees',
            'promotions'          => 'Promotions',
            'donations'           => 'Donations',
            'tax_rates'           => 'Tax rates',
            'tax_categories'      => 'Tax categories',
            'zones'               => 'Zones',
            'payment_methods'     => 'Payment methods',
            'payment_options'     => 'Payment options',
            'payment_gateways'    => 'Payment gateways',
            'insurance_options'    => 'Insurance options',
            'insurance_forms'      => 'Insurance forms',
            'insurance_submissions' => 'Insurance submissions',
        ],
        'Engagement' => [
            'touchpoints'          => 'Touchpoints',
            'touchpoints_stats'    => 'Touchpoint stats',
            'messages'             => 'Messages',
            'outreach_campaigns'   => 'Outreach campaigns',
            'communication_preferences' => 'Communication preferences',
            'automation_by_tags'   => 'Automation by tags',
        ],
        'Config & system' => [
            'roles'             => 'Roles',
            'role_summaries'    => 'Role summaries',
            'entity_types'      => 'Entity types',
            'resource_tags'     => 'Resource tags',
            'pinned_tags'       => 'Pinned tags',
            'import_jobs'       => 'Import jobs',
            'versions'          => 'Record versions (audit trail)',
            'role_audit_events' => 'Role audit events',
            'service_identities' => 'Service identities',
            'user_identities'   => 'User identities',
            'countries'         => 'Countries',
            'webhook/endpoints' => 'Webhook endpoints',
        ],
    ];

    /**
     * Cache lifetime in seconds.
     */
    private const CACHE_TTL = 300;

    /**
     * Page size for paginated fetches.
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

        $format = $_GET['format'] ?? null;
        $format = is_string($format) ? $format : null;

        if ($format === 'csv' && !isset($_GET['mdp_api'])) {
            $this->render_csv();
            exit;
        }

        if (isset($_GET['mdp_api'])) {
            $api = wp_unslash((string) $_GET['mdp_api']);
            if ($format === 'csv') {
                $this->render_explorer_download($api, 'csv');
                exit;
            }
            if ($format === 'json') {
                $this->render_explorer_download($api, 'json');
                exit;
            }
            $this->render_explorer($api);
            exit;
        }

        if (isset($_GET['resource_types'])) {
            $this->render_resource_types();
            exit;
        }

        if (isset($_GET['memberships'])) {
            $this->render_memberships();
            exit;
        }

        if (isset($_GET['communications'])) {
            $this->render_communications();
            exit;
        }

        if (isset($_GET['mdp_api'])) {
            $this->render_explorer(wp_unslash((string) $_GET['mdp_api']));
            exit;
        }

        $identifier = $_GET['schema'] ?? null;
        if (is_string($identifier) && $identifier !== '') {
            if (($_GET['view'] ?? null) === 'fields') {
                $this->render_fields(wp_unslash($identifier));
                exit;
            }

            $this->render_single(wp_unslash($identifier));
            exit;
        }

        $this->render_index();
        exit;
    }

    /**
     * Build an inspector URL.
     *
     * @param array<string, string|int> $params Extra query args.
     *
     * @return string
     */
    private function url(array $params = []): string
    {
        return add_query_arg(array_merge(['mdp_schemas' => '1'], $params), home_url('/'));
    }

    /**
     * Shared CSS plus page head.
     *
     * @param string $title Page title.
     *
     * @return void
     */
    private function head(string $title): void
    {
        $style = <<<'CSS'
            body{font-family:monospace;margin:2rem}
            h1{font-size:1.2rem}
            h2{font-size:1rem;margin:1.5rem 0 .5rem}
            table{border-collapse:collapse}
            th,td{border:1px solid #ccc;padding:.3rem .6rem;text-align:left;vertical-align:top}
            td.id{color:#888}
            td.values{color:#444;max-width:52rem}
            nav a{margin-right:1rem}
            CSS;
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . esc_html($title) . '</title><style>' . $style . '</style></head><body>';
    }

    /**
     * Shared footer: cache note, refresh link, index link.
     *
     * @return void
     */
    private function foot(): void
    {
        echo '<p style="color:#888">Cached ' . esc_html((string) self::CACHE_TTL) . 's. '
            . '<a href="' . esc_url($this->url(['mdp_schemas_refresh' => '1'])) . '">refresh from API</a> | '
            . '<a href="' . esc_url($this->url()) . '">back to index</a></p>';
        echo '</body></html>';
    }

    /**
     * Cached fetch of a paginated MDP endpoint, normalized to a resource list.
     *
     * @param string $key  Transient key.
     * @param string $path API path.
     *
     * @return array|false Normalized resource list, or false when the API is unavailable.
     */
    private function cached_list(string $key, string $path)
    {
        if (!isset($_GET['mdp_schemas_refresh'])) {
            $cached = get_transient($key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $raw = $this->fetch_all($path);
        if ($raw === false) {
            return false;
        }

        $resources = $raw['data'] ?? $raw;
        if (!is_array($resources)) {
            \Wicket()->log('error', 'SchemaInspector: unexpected payload shape from ' . $path . '.');

            return false;
        }

        set_transient($key, $resources, self::CACHE_TTL);

        return $resources;
    }

    /**
     * The tenant json_schemas.
     *
     * @return array|false
     */
    private function schemas()
    {
        return $this->cached_list(self::CACHE_KEY, 'json_schemas');
    }

    /**
     * Map of schema UUID to the resource_type keys of its
     * json_schema_resources records (people, organizations, groups, ...
     * per JsonSchemaResource::RESOURCE_TYPE_MAPPING). This relationship, not
     * a schema attribute, is where the MDP records what a schema profiles.
     *
     * @return array<string, array<string, true>> Empty array when the API is down.
     */
    private function schema_scopes(): array
    {
        if (!isset($_GET['mdp_schemas_refresh'])) {
            $cached = get_transient(self::SCOPES_CACHE_KEY);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $raw = $this->fetch_all('json_schemas', ['include' => 'json_schema_resources']);
        if ($raw === false) {
            return [];
        }

        $map = [];
        foreach ($raw['included'] as $record) {
            if (($record['type'] ?? '') !== 'json_schema_resources') {
                continue;
            }
            $owner = $record['relationships']['json_schema']['data']['id'] ?? null;
            $type = $record['attributes']['resource_type'] ?? null;
            if (is_string($owner) && $owner !== '' && is_string($type) && $type !== '') {
                $map[$owner][$type] = true;
            }
        }

        set_transient(self::SCOPES_CACHE_KEY, $map, self::CACHE_TTL);

        return $map;
    }

    /**
     * Scope keys of one schema resource, sorted.
     *
     * @param array $resource A json_schemas resource.
     *
     * @return string[] Empty when the schema has no binding.
     */
    private function schema_scope_names(array $resource): array
    {
        $map = $this->schema_scopes();
        $names = array_keys($map[$resource['id'] ?? null] ?? []);
        sort($names);

        return $names;
    }

    /**
     * The tenant resource type list values (address/phone/web types, genders, ...).
     *
     * @return array|false
     */
    private function resource_types()
    {
        return $this->cached_list(self::RESOURCE_TYPE_CACHE_KEY, 'resource_types');
    }

    /**
     * The tenant membership tier definitions.
     *
     * @return array|false
     */
    private function memberships()
    {
        return $this->cached_list(self::MEMBERSHIP_CACHE_KEY, 'memberships');
    }

    /**
     * The tenant communication preferences catalog (sublist keys behind
     * communications.sublists.<key> targets).
     *
     * @return array|false
     */
    private function communications()
    {
        return $this->cached_list(self::COMMUNICATIONS_CACHE_KEY, 'communication_preferences');
    }

    /**
     * Fetch every page of a paginated endpoint with a bounded timeout.
     *
     * @param string $path  API path.
     * @param array  $query Extra query params merged under the pagination ones.
     *
     * @return array|false The raw API payload (envelope or list), or false on failure.
     */
    private function fetch_all(string $path, array $query = [])
    {
        $client = wicket_api_client();
        if (!$client) {
            \Wicket()->log('error', 'SchemaInspector: MDP API client unavailable.');

            return false;
        }

        $resources = [];
        $included = [];
        try {
            for ($page = 1; $page <= self::MAX_PAGES; $page++) {
                $response = $client->get(
                    $path,
                    [
                        'query'           => array_merge($query, [
                            'page[size]'   => self::PAGE_SIZE,
                            'page[number]' => $page,
                        ]),
                        'timeout'         => 15,
                        'connect_timeout' => 5,
                    ]
                );

                $rows = $response['data'] ?? (is_array($response) ? $response : []);
                if (!is_array($rows)) {
                    break;
                }

                $resources = array_merge($resources, $rows);
                if (isset($response['included']) && is_array($response['included'])) {
                    $included = array_merge($included, $response['included']);
                }
                if (count($rows) < self::PAGE_SIZE) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            \Wicket()->log('error', 'SchemaInspector: ' . $path . ' fetch failed: ' . $e->getMessage());

            return false;
        }

        return ['data' => $resources, 'included' => $included];
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
     * Language code used for ui_schema i18n lookups (for example "en", "fr").
     *
     * @return string
     */
    private function lang_code(): string
    {
        $lang = (string) get_bloginfo('language');
        $lang = strtok($lang, '-');

        return is_string($lang) && $lang !== '' ? $lang : 'en';
    }

    /**
     * The GF-mappable fields of one schema: scalar properties and enum lists.
     *
     * Walks schema.properties and merges schema.oneOf branch properties by
     * slug (polymorphic widgets define fields under branches). Excludes
     * composite shapes (repeaters, nested objects) that a single GF field
     * cannot map to.
     *
     * @param array $resource A json_schemas resource.
     *
     * @return array<array{slug: string, label: string, type: string, values: array<array{value: string, label: string}>}>
     */
    private function mappable_fields(array $resource): array
    {
        $schema = $resource['attributes']['schema'] ?? null;
        if (!is_array($schema)) {
            return [];
        }

        $ui = $resource['attributes']['ui_schema'] ?? [];
        if (!is_array($ui)) {
            $ui = [];
        }
        $lang = $this->lang_code();

        $props = [];
        foreach ($schema['properties'] ?? [] as $key => $prop) {
            if (is_string($key) && is_array($prop)) {
                $props[$key] = $prop;
            }
        }
        foreach ($schema['oneOf'] ?? [] as $branch) {
            foreach (($branch['properties'] ?? []) as $key => $prop) {
                if (is_string($key) && is_array($prop) && !isset($props[$key])) {
                    $props[$key] = $prop;
                }
            }
        }

        $fields = [];
        foreach ($props as $key => $prop) {
            $type = $this->field_type($prop);
            if ($type === '') {
                continue;
            }
            $fields[] = [
                'slug'   => $key,
                'label'  => $this->field_label($key, $prop, $ui, $lang),
                'type'   => $type,
                'values' => $this->enum_values($key, $prop, $ui, $lang),
            ];
        }

        return $fields;
    }

    /**
     * The GF-mappable type of one schema property, or '' when composite.
     *
     * @param array $prop Property definition.
     *
     * @return string string|integer|number|boolean|array, or '' when not mappable.
     */
    private function field_type(array $prop): string
    {
        if (isset($prop['enum']) && is_array($prop['enum'])) {
            return 'string';
        }
        if (isset($prop['items']['enum']) && is_array($prop['items']['enum'])) {
            return 'array';
        }
        $type = $prop['type'] ?? '';
        if (is_array($type)) {
            $type = implode('|', array_filter(array_map('strval', $type)));
        }

        return in_array($type, ['string', 'integer', 'number', 'boolean'], true) ? $type : '';
    }

    /**
     * Resolve a field label: ui_schema i18n title, ui:title, property title, then the slug.
     *
     * @param string $key   Property key.
     * @param array  $prop  Property definition.
     * @param array  $ui    Schema ui_schema.
     * @param string $lang  Language code.
     *
     * @return string
     */
    private function field_label(string $key, array $prop, array $ui, string $lang): string
    {
        $candidates = [
            $ui[$key]['ui:i18n']['title'][$lang] ?? null,
            $ui[$key]['ui:i18n']['title']['en'] ?? null,
            $ui[$key]['ui:title'] ?? null,
            $prop['title'] ?? null,
        ];
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return $key;
    }

    /**
     * Enum options of one property as value/label pairs.
     *
     * Label order: property enumNames, then ui_schema i18n enumNames for the
     * current language, then English.
     *
     * @param string $key  Property key.
     * @param array  $prop Property definition.
     * @param array  $ui   Schema ui_schema.
     * @param string $lang Language code.
     *
     * @return array<array{value: string, label: string}>
     */
    private function enum_values(string $key, array $prop, array $ui, string $lang): array
    {
        $values = $prop['enum'] ?? $prop['items']['enum'] ?? null;
        if (!is_array($values)) {
            return [];
        }

        $names = is_array($prop['enumNames'] ?? null) ? $prop['enumNames'] : null;
        if ($names === null) {
            $candidate = $ui[$key]['ui:i18n']['enumNames'][$lang]
                ?? $ui[$key]['ui:i18n']['enumNames']['en']
                ?? $ui[$key]['enumNames']
                ?? null;
            $names = is_array($candidate) ? $candidate : [];
        }

        $pairs = [];
        foreach (array_values($values) as $index => $value) {
            $label = $names[$index] ?? '';
            $pairs[] = [
                'value' => (string) $value,
                'label' => is_string($label) ? $label : '',
            ];
        }

        return $pairs;
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
     * Render an HTML table of rows.
     *
     * @param list<string>      $headers Column headers.
     * @param list<list<string>>$rows    Cell rows, already escaped by the caller.
     *
     * @return void
     */
    private function table(array $headers, array $rows): void
    {
        echo '<table><tr>';
        foreach ($headers as $header) {
            echo '<th>' . esc_html($header) . '</th>';
        }
        echo '</tr>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $cell) {
                echo '<td>' . $cell . '</td>'; // Cells arrive pre-escaped.
            }
            echo '</tr>';
        }
        echo '</table>';
    }

    /**
     * Output the index view: schemas grouped by resource type, plus links to
     * the other tenant datasets and the CSV export.
     *
     * @return void
     */
    private function render_index(): void
    {
        $schemas = $this->schemas();

        $this->head('MDP Schemas');

        if ($schemas === false) {
            echo '<h1>MDP Schemas</h1><p>The MDP API is unavailable. Retry, or add <code>&amp;mdp_schemas_refresh=1</code> after fixing the cause. Details are in the plugin log.</p></body></html>';

            return;
        }

        $resource_types = $this->resource_types();
        $memberships = $this->memberships();
        $communications = $this->communications();

        echo '<h1>MDP JSON Schemas (' . esc_html((string) count($schemas)) . ')</h1>';

        echo '<nav><a href="' . esc_url($this->url(['resource_types' => '1'])) . '">Resource types'
            . (is_array($resource_types) ? ' (' . count($resource_types) . ')' : '') . '</a>'
            . '<a href="' . esc_url($this->url(['memberships' => '1'])) . '">Membership tiers'
            . (is_array($memberships) ? ' (' . count($memberships) . ')' : '') . '</a>'
            . '<a href="' . esc_url($this->url(['communications' => '1'])) . '">Communications'
            . (is_array($communications) ? ' (' . count($communications) . ')' : '') . '</a>'
            . '<a href="' . esc_url($this->url(['mdp_api' => '1'])) . '">API data</a>'
            . '<a href="' . esc_url($this->url(['format' => 'csv'])) . '">Download slugs CSV</a>'
            . '</nav>';

        if (count($schemas) === 0) {
            $this->foot();

            return;
        }

        $groups = [];
        foreach ($schemas as $resource) {
            $scopes = $this->schema_scope_names($resource);
            $type = $scopes !== [] ? implode(', ', $scopes) : 'unknown';
            $groups[$type][] = $resource;
        }
        ksort($groups);

        foreach ($groups as $type => $resources) {
            echo '<h2>' . esc_html($type) . ' (' . esc_html((string) count($resources)) . ')</h2>';
            $rows = [];
            foreach ($resources as $resource) {
                $attributes = $resource['attributes'] ?? [];
                $slug = is_string($attributes['slug'] ?? null) ? $attributes['slug'] : '';
                $title = is_string($attributes['title'] ?? null) ? $attributes['title'] : $slug;
                $uuid = is_string($resource['id'] ?? null) ? $resource['id'] : '';
                $rows[] = [
                    esc_html($slug),
                    esc_html($title),
                    '<span class="id">' . esc_html($uuid) . '</span>',
                    '<a href="' . esc_url($this->url(['schema' => $uuid])) . '">view JSON</a> | '
                        . '<a href="' . esc_url($this->url(['schema' => $uuid, 'view' => 'fields'])) . '">fields</a>',
                ];
            }
            $this->table(['Slug', 'Title', 'UUID', ''], $rows);
        }

        $this->foot();
    }

    /**
     * Output the mappable-fields view for one schema: the property slugs,
     * labels, types, and enum values a GF author needs for
     * data_field.<schema_slug>.<property> targets and choice values.
     *
     * @param string $identifier Lookup value.
     *
     * @return void
     */
    private function render_fields(string $identifier): void
    {
        $schema = $this->find_schema($identifier);

        $this->head('MDP Schema Fields');

        if ($schema === null) {
            status_header(404);
            echo '<h1>MDP Schema Fields</h1><p>No schema matches <code>' . esc_html($identifier) . '</code>.</p>';
            $this->foot();

            return;
        }

        $attributes = $schema['attributes'] ?? [];
        $slug = is_string($attributes['slug'] ?? null) ? $attributes['slug'] : '';
        $uuid = is_string($schema['id'] ?? null) ? $schema['id'] : '';
        $fields = $this->mappable_fields($schema);
        $scopes = $this->schema_scope_names($schema);

        echo '<h1>' . esc_html($slug) . ' fields (' . esc_html((string) count($fields)) . ')</h1>';
        echo '<p class="id">' . esc_html($uuid) . ' | '
            . '<a href="' . esc_url($this->url(['schema' => $uuid])) . '">view raw JSON</a></p>';
        echo '<p>Applies to: ' . esc_html($scopes !== [] ? implode(', ', $scopes) : 'unknown (no json_schema_resources binding)') . '</p>';

        if (count($fields) === 0) {
            echo '<p>No GF-mappable fields: every property is a composite shape (repeater or nested object).</p>';
            $this->foot();

            return;
        }

        $rows = [];
        foreach ($fields as $field) {
            $values = [];
            foreach ($field['values'] as $index => $pair) {
                if ($index >= 8) {
                    $values[] = '+' . (string) (count($field['values']) - 8) . ' more';
                    break;
                }
                $text = $pair['value'];
                if ($pair['label'] !== '' && $pair['label'] !== $pair['value']) {
                    $text .= ' = ' . $pair['label'];
                }
                $values[] = esc_html($text);
            }
            $rows[] = [
                esc_html($field['slug']),
                esc_html($field['label']),
                esc_html($field['type']),
                '<span class="values">' . implode('<br>', $values) . '</span>',
            ];
        }
        $this->table(['Property', 'Label', 'Type', 'Values'], $rows);

        $this->foot();
    }

    /**
     * Output the resource type list values view: the exact choice values a
     * GF select/radio/checkbox must carry when a field syncs to MDP.
     *
     * @return void
     */
    private function render_resource_types(): void
    {
        $resource_types = $this->resource_types();

        $this->head('MDP Resource Types');

        if ($resource_types === false) {
            echo '<h1>MDP Resource Types</h1><p>The MDP API is unavailable. Details are in the plugin log.</p>';
            $this->foot();

            return;
        }

        $groups = [];
        foreach ($resource_types as $resource) {
            $attributes = $resource['attributes'] ?? [];
            $group = $attributes['resource_type'] ?? null;
            $group = is_string($group) && $group !== '' ? $group : 'unknown';
            $groups[$group][] = $attributes;
        }
        ksort($groups);

        echo '<h1>MDP Resource Types (' . esc_html((string) count($resource_types)) . ')</h1>';

        foreach ($groups as $group => $items) {
            usort($items, fn (array $a, array $b): int => strnatcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));
            echo '<h2>' . esc_html($group) . ' (' . esc_html((string) count($items)) . ')</h2>';
            $rows = [];
            foreach ($items as $attributes) {
                $rows[] = [
                    esc_html(is_string($attributes['name'] ?? null) ? $attributes['name'] : ''),
                    esc_html(is_string($attributes['slug'] ?? null) ? $attributes['slug'] : ''),
                    esc_html(is_string($attributes['available_for_entity'] ?? null) ? $attributes['available_for_entity'] : ''),
                    ($attributes['default'] ?? null) === true ? 'yes' : '',
                ];
            }
            $this->table(['Name', 'Slug', 'For entities', 'Default'], $rows);
        }

        $this->foot();
    }

    /**
     * Output the membership tiers view: tier slugs and machine codes the
     * memberships plugin and renewal forms key on.
     *
     * @return void
     */
    private function render_memberships(): void
    {
        $memberships = $this->memberships();

        $this->head('MDP Membership Tiers');

        if ($memberships === false) {
            echo '<h1>MDP Membership Tiers</h1><p>The MDP API is unavailable. Details are in the plugin log.</p>';
            $this->foot();

            return;
        }

        usort($memberships, function (array $a, array $b): int {
            $a_attributes = $a['attributes'] ?? [];
            $b_attributes = $b['attributes'] ?? [];
            $by_type = strnatcasecmp((string) ($a_attributes['type'] ?? ''), (string) ($b_attributes['type'] ?? ''));
            if ($by_type !== 0) {
                return $by_type;
            }
            $a_weight = is_numeric($a_attributes['weight'] ?? null) ? (int) $a_attributes['weight'] : 0;
            $b_weight = is_numeric($b_attributes['weight'] ?? null) ? (int) $b_attributes['weight'] : 0;
            if ($a_weight !== $b_weight) {
                return $b_weight <=> $a_weight;
            }

            return strnatcasecmp((string) ($a_attributes['name'] ?? ''), (string) ($b_attributes['name'] ?? ''));
        });

        echo '<h1>MDP Membership Tiers (' . esc_html((string) count($memberships)) . ')</h1>';

        $rows = [];
        foreach ($memberships as $membership) {
            $attributes = $membership['attributes'] ?? [];
            $text = fn (string $key): string => esc_html(is_string($attributes[$key] ?? null) || is_numeric($attributes[$key] ?? null) ? (string) $attributes[$key] : '');
            $rows[] = [
                $text('name'),
                $text('slug'),
                $text('code'),
                $text('type'),
                $text('category'),
                $text('renewable'),
                $text('approval'),
                $text('default_grace_period_days'),
                $text('max_assignments'),
                ($attributes['active'] ?? null) === false ? 'no' : 'yes',
            ];
        }
        $this->table(['Name', 'Slug', 'Code', 'Type', 'Category', 'Renewable', 'Approval', 'Grace days', 'Max', 'Active'], $rows);

        $this->foot();
    }

    /**
     * The label of one communication preference: short label, English
     * description, localized description, then the sublist key.
     *
     * @param array $attributes Preference attributes.
     *
     * @return string
     */
    private function communication_label(array $attributes): string
    {
        $candidates = [
            $attributes['short_label'] ?? null,
            $attributes['description_en'] ?? null,
            $attributes['description'] ?? null,
        ];
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return (string) ($attributes['sublist_key'] ?? '');
    }

    /**
     * Output the communications preferences view: the email flag and the
     * tenant sublist keys behind communications.email /
     * communications.sublists.<key> targets.
     *
     * @return void
     */
    private function render_communications(): void
    {
        $preferences = $this->communications();

        $this->head('MDP Communications');

        if ($preferences === false) {
            echo '<h1>MDP Communications</h1><p>The MDP API is unavailable. Details are in the plugin log.</p>';
            $this->foot();

            return;
        }

        echo '<h1>MDP Communications Preferences (' . esc_html((string) count($preferences)) . ')</h1>';

        $rows = [
            ['email', 'Email Opt-in', '', 'communications.email'],
        ];
        foreach ($preferences as $preference) {
            $attributes = $preference['attributes'] ?? [];
            $key = is_string($attributes['sublist_key'] ?? null) ? $attributes['sublist_key'] : '';
            $merge = is_string($attributes['merge_field_name'] ?? null) ? $attributes['merge_field_name'] : '';
            $rows[] = [
                esc_html($key),
                esc_html($this->communication_label($attributes)),
                esc_html($merge),
                esc_html('communications.sublists.' . $key),
            ];
        }

        $this->table(['Key', 'Label', 'Merge field', 'Mapping target'], $rows);

        $this->foot();
    }

    /**
     * Infer a JSON type name from one attribute value. Values are never
     * rendered anywhere; only their shape becomes metadata.
     *
     * @param mixed $value Sample value.
     *
     * @return string One of string, integer, number, boolean, array, object, mixed.
     */
    private function json_type_of($value): string
    {
        if (is_int($value)) {
            return 'integer';
        }
        if (is_float($value)) {
            return 'number';
        }
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_string($value)) {
            return 'string';
        }
        if (is_array($value)) {
            return array_values($value) === $value ? 'array' : 'object';
        }

        return 'mixed';
    }

    /**
     * Probe one v1 index endpoint for page 1 of size 1 and derive its
     * served shape: attribute names with value-shape types, relationship
     * names with target types, and a row count when the payload reports one.
     *
     * @param string $path API path.
     *
     * @return array{status: string, count: int|string|null, attributes: array<string, string>, relationships: array<string, string>}
     */
    private function probe_endpoint(string $path): array
    {
        $client = wicket_api_client();
        if (!$client) {
            return ['status' => 'error', 'count' => null, 'attributes' => [], 'relationships' => []];
        }

        try {
            $response = $client->get(
                $path,
                [
                    'query'           => ['page[size]' => 1],
                    'timeout'         => 15,
                    'connect_timeout' => 5,
                ]
            );
        } catch (\Throwable $e) {
            \Wicket()->log('error', 'SchemaInspector: api explorer probe failed for ' . $path . ': ' . $e->getMessage());

            return ['status' => 'error', 'count' => null, 'attributes' => [], 'relationships' => []];
        }

        $rows = $response['data'] ?? null;
        if (!is_array($rows)) {
            return ['status' => 'error', 'count' => null, 'attributes' => [], 'relationships' => []];
        }

        $first = $rows[0] ?? null;
        $attributes = [];
        $relationships = [];
        if (is_array($first)) {
            foreach ($first['attributes'] ?? [] as $name => $value) {
                if (is_string($name) && $name !== '') {
                    $attributes[$name] = $this->json_type_of($value);
                }
            }
            foreach ($first['relationships'] ?? [] as $name => $rel) {
                if (!is_string($name) || $name === '') {
                    continue;
                }
                $data = $rel['data'] ?? null;
                $target = null;
                if (is_array($data)) {
                    if (isset($data['type']) && is_string($data['type'])) {
                        $target = $data['type'];
                    } elseif (isset($data[0]['type']) && is_string($data[0]['type'])) {
                        $target = $data[0]['type'];
                    }
                }
                $relationships[$name] = $target !== null ? $target : 'unloaded';
            }
        }

        $count = null;
        foreach (['total_records', 'record_count', 'count', 'total'] as $key) {
            $meta = $response['meta'][$key] ?? null;
            if (is_numeric($meta)) {
                $count = (int) $meta;
                break;
            }
        }
        if ($count === null) {
            $count = $rows !== [] ? '1+' : 0;
        }

        return [
            'status'        => $rows === [] ? 'empty' : 'ok',
            'count'         => $count,
            'attributes'    => $attributes,
            'relationships' => $relationships,
        ];
    }

    /**
     * The explorer metadata map for every registered endpoint, cached as
     * one transient. Probes run page[size]=1, so each costs one tiny read;
     * failures are cached too so a down endpoint does not hammer the API.
     *
     * @return array<string, array>
     */
    private function explorer_meta(): array
    {
        if (!isset($_GET['mdp_schemas_refresh'])) {
            $cached = get_transient(self::EXPLORER_CACHE_KEY);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $map = [];
        foreach (self::EXPLORER_ENDPOINTS as $endpoints) {
            foreach ($endpoints as $path => $label) {
                $map[$path] = $this->probe_endpoint($path);
            }
        }

        set_transient(self::EXPLORER_CACHE_KEY, $map, self::CACHE_TTL);

        return $map;
    }

    /**
     * Output the API data explorer: every top-level v1 index endpoint with
     * the attributes, types, and relationships the MDP serves for this
     * tenant. Metadata only; record contents are never fetched into the
     * output. A path argument renders one endpoint's full shape.
     *
     * @param string $detail Endpoint path for the detail view, or "1" for the table.
     *
     * @return void
     */
    private function render_explorer(string $detail): void
    {
        $known = $this->endpoint_group($detail) !== null;
        if (!$known) {
            $detail = '1';
        }

        $map = $this->explorer_meta();

        $this->head('MDP API Data');

        if ($detail !== '1') {
            $this->render_explorer_detail($detail, $map[$detail] ?? ['status' => 'error', 'count' => null, 'attributes' => [], 'relationships' => []]);

            return;
        }

        echo '<h1>MDP API Data</h1>';
        echo '<p>Every top-level v1 index endpoint with the shape this tenant serves. Metadata only: attribute names, value-shape types, and relationship targets. Record contents are never fetched into the page. Types are inferred from one sample record, so they describe what exists, not what must exist.</p>';
        echo '<nav><a href="' . esc_url($this->url(['mdp_schemas' => '1'])) . '">back to index</a>'
            . ' | ' . $this->explorer_download_links('1') . ' (all endpoints)'
            . '</nav>';

        foreach (self::EXPLORER_ENDPOINTS as $group => $endpoints) {
            echo '<h2>' . esc_html($group) . '</h2>';
            $rows = [];
            foreach ($endpoints as $path => $label) {
                $meta = $map[$path] ?? ['status' => 'error', 'count' => null, 'attributes' => [], 'relationships' => []];
                $attr_names = array_keys($meta['attributes']);
                $shown = [];
                foreach ($attr_names as $index => $name) {
                    if ($index >= 8) {
                        $shown[] = '+' . (string) (count($attr_names) - 8) . ' more';
                        break;
                    }
                    $shown[] = esc_html($name);
                }
                $rows[] = [
                    '<a href="' . esc_url($this->url(['mdp_api' => $path])) . '">' . esc_html($label) . '</a>',
                    '<span class="id">' . esc_html($path) . '</span>',
                    $meta['status'] === 'ok' || $meta['status'] === 'empty'
                        ? esc_html((string) count($attr_names)) . ' <span class="values">' . implode(', ', $shown) . '</span>'
                        : '<em>unavailable</em>',
                    esc_html((string) count($meta['relationships'])),
                    $meta['status'] === 'error' ? '<em>error</em>' : esc_html((string) $meta['count']),
                    $this->explorer_download_links($path),
                ];
            }
            $this->table(['Resource', 'Endpoint', 'Attributes', 'Rels', 'Rows', 'Download'], $rows);
        }

        echo '<p>Nested-only or param-required resources are not probed here: addresses, phones, emails, web_addresses, leaves (under people), comments, orders, touchpoints, messages, roles, connections, segment filters, membership entries and histories (under people and organizations), statements (under subscriptions), webhook attempts, group people (groups/&lt;id&gt;/people), and resource_facets (requires filter[id_eq]).</p>';

        $this->foot();
    }

    /**
     * Output one endpoint's full derived shape: every attribute with its
     * type and every relationship with its target type.
     *
     * @param string $path API path.
     * @param array  $meta Cached probe result.
     *
     * @return void
     */
    private function render_explorer_detail(string $path, array $meta): void
    {
        echo '<h1>' . esc_html($path) . '</h1>';
        echo '<nav><a href="' . esc_url($this->url(['mdp_api' => '1'])) . '">back to API data</a>'
            . ' | ' . $this->explorer_download_links($path)
            . ' | <a href="' . esc_url($this->url(['mdp_api' => '1', 'mdp_schemas_refresh' => '1'])) . '">re-probe all endpoints</a></nav>';

        if (($meta['status'] ?? 'error') === 'error') {
            echo '<p>The probe failed (endpoint unavailable for the service token, or the API is down). Details are in the plugin log.</p>';
            $this->foot();

            return;
        }

        $count = $meta['count'];
        echo '<p>' . esc_html((string) $count) . ' rows. ' . esc_html((string) count($meta['attributes'])) . ' attributes, '
            . esc_html((string) count($meta['relationships'])) . ' relationships.</p>';

        if ($meta['attributes'] === []) {
            echo '<p>No records on this tenant, so the served shape is unknown until one exists.</p>';
        } else {
            $rows = [];
            foreach ($meta['attributes'] as $name => $type) {
                $rows[] = [esc_html($name), esc_html($type)];
            }
            $this->table(['Attribute', 'Type'], $rows);
        }

        if ($meta['relationships'] !== []) {
            echo '<h2>Relationships</h2>';
            $rows = [];
            foreach ($meta['relationships'] as $name => $target) {
                $rows[] = [esc_html($name), esc_html($target)];
            }
            $this->table(['Relationship', 'Target type'], $rows);
        }

        $this->foot();
    }

    /**
     * Group name containing one endpoint path, for cheap membership checks.
     *
     * @param string $path API path.
     *
     * @return string|null
     */
    private function endpoint_group(string $path): ?string
    {
        foreach (self::EXPLORER_ENDPOINTS as $group => $endpoints) {
            if (isset($endpoints[$path])) {
                return $group;
            }
        }

        return null;
    }

    /**
     * Download links for one explorer endpoint: JSON and CSV definitions.
     *
     * @param string $path API path.
     *
     * @return string HTML links.
     */
    private function explorer_download_links(string $path): string
    {
        return '<a href="' . esc_url($this->url(['mdp_api' => $path, 'format' => 'json'])) . '">json</a>'
            . ' | '
            . '<a href="' . esc_url($this->url(['mdp_api' => $path, 'format' => 'csv'])) . '">csv</a>';
    }

    /**
     * Stream the explorer metadata for one endpoint (or the whole registry
     * when the path is "1") as a JSON or CSV download, so Wicket staff can
     * feed the tenant's served shape to agents. Metadata only; record
     * contents never appear in the payload.
     *
     * @param string $path   API path, or "1" for every registered endpoint.
     * @param string $format "json" or "csv".
     *
     * @return void Exits after streaming.
     */
    private function render_explorer_download(string $path, string $format): void
    {
        $scoped = $path !== '1';
        if ($scoped && $this->endpoint_group($path) === null) {
            status_header(404);
            echo 'Unknown endpoint: ' . esc_html($path);
            exit;
        }

        $map = $this->explorer_meta();

        $entries = [];
        foreach (self::EXPLORER_ENDPOINTS as $group => $endpoints) {
            foreach ($endpoints as $endpoint => $label) {
                if ($scoped && $endpoint !== $path) {
                    continue;
                }
                $meta = $map[$endpoint] ?? ['status' => 'error', 'count' => null, 'attributes' => [], 'relationships' => []];
                $entries[] = [
                    'group'         => $group,
                    'path'          => $endpoint,
                    'label'         => $label,
                    'status'        => $meta['status'],
                    'rows'          => $meta['count'],
                    'attributes'    => $meta['attributes'],
                    'relationships' => $meta['relationships'],
                ];
            }
        }

        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        $host = is_string($host) && $host !== '' ? $host : 'site';
        $prefix = 'mdp-api' . ($scoped ? '-' . $path : '');
        $scope_label = $scoped ? 'endpoint ' . $path : 'all registered endpoints';

        if ($format === 'json') {
            $payload = [
                'meta' => [
                    'generated_at'    => gmdate('c'),
                    'site'            => $host,
                    'source'          => 'MDP API v1',
                    'scope'           => $scope_label,
                    'record_contents' => 'never included; metadata only',
                    'note'            => 'Attribute and relationship names come from one sample record per endpoint, so they describe what exists on this tenant, not what must exist. Rows are payload meta totals when reported, otherwise 1+.',
                ],
                'endpoints' => array_map(function (array $entry): array {
                    $entry['attributes'] = (object) $entry['attributes'];
                    $entry['relationships'] = (object) $entry['relationships'];

                    return $entry;
                }, $entries),
            ];

            $filename = $prefix . '-' . $host . '.json';
            @header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
            @header('Content-Type: application/json; charset=utf-8');
            @header('X-Content-Type-Options: nosniff');
            echo wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }

        $rows = [['kind', 'group', 'path', 'name', 'detail']];
        foreach ($entries as $entry) {
            $detail = $entry['status'] === 'error' ? 'error' : $entry['status'] . '; ' . (string) $entry['rows'] . ' rows';
            $rows[] = ['endpoint', $entry['group'], $entry['path'], $entry['label'], $detail];
            foreach ($entry['attributes'] as $name => $type) {
                $rows[] = ['attribute', $entry['group'], $entry['path'], (string) $name, (string) $type];
            }
            foreach ($entry['relationships'] as $name => $target) {
                $rows[] = ['relationship', $entry['group'], $entry['path'], (string) $name, (string) $target];
            }
        }

        (new \WicketWP\Support\CsvExporter())->download($prefix . '-' . $host . '.csv', $rows);
    }

    /**
     * Output every tenant slug as one CSV download, in the per-client slug
     * reference format (kind,list,name,slug,extra) that form building and
     * MDP configuration work consume.
     *
     * @return void
     */
    private function render_csv(): void
    {
        $schemas = $this->schemas();
        $resource_types = $this->resource_types();
        $memberships = $this->memberships();
        $communications = $this->communications();

        $failed = [];
        if ($schemas === false) {
            $failed[] = 'json_schemas';
        }
        if ($resource_types === false) {
            $failed[] = 'resource_types';
        }
        if ($memberships === false) {
            $failed[] = 'memberships';
        }
        if ($communications === false) {
            $failed[] = 'communications';
        }
        if ($failed !== []) {
            @header('Content-Type: application/json; charset=utf-8');
            status_header(503);
            echo wp_json_encode(
                ['error' => 'api_unavailable', 'failed' => $failed],
                JSON_UNESCAPED_SLASHES
            );
            exit;
        }

        $rows = [['kind', 'list', 'name', 'slug', 'extra']];

        foreach ($schemas ?: [] as $resource) {
            $attributes = $resource['attributes'] ?? [];
            $slug = is_string($attributes['slug'] ?? null) ? $attributes['slug'] : (string) ($attributes['key'] ?? '');
            $title = is_string($attributes['title'] ?? null) && $attributes['title'] !== ''
                ? $attributes['title']
                : $slug;
            $scopes = $this->schema_scope_names($resource);
            $rows[] = ['json_schema', '', $title, $slug, implode(', ', $scopes)];
            foreach ($this->mappable_fields($resource) as $field) {
                $rows[] = ['json_schema_property', $slug, $field['label'], $field['slug'], $field['type']];
            }
        }

        foreach ($memberships ?: [] as $membership) {
            $attributes = $membership['attributes'] ?? [];
            $rows[] = [
                'membership',
                is_string($attributes['type'] ?? null) ? $attributes['type'] : '',
                is_string($attributes['name'] ?? null) ? $attributes['name'] : '',
                is_string($attributes['slug'] ?? null) ? $attributes['slug'] : '',
                is_string($attributes['code'] ?? null) ? $attributes['code'] : '',
            ];
        }

        foreach ($resource_types ?: [] as $resource) {
            $attributes = $resource['attributes'] ?? [];
            $group = $attributes['resource_type'] ?? null;
            $group = is_string($group) && $group !== '' ? str_replace('_', ' ', $group) : 'unknown';
            $rows[] = [
                'resource_type',
                $group,
                is_string($attributes['name'] ?? null) ? $attributes['name'] : '',
                is_string($attributes['slug'] ?? null) ? $attributes['slug'] : '',
                is_string($attributes['available_for_entity'] ?? null) ? $attributes['available_for_entity'] : '',
            ];
        }

        $rows[] = ['comm', 'communications', 'Email Opt-in', 'email', ''];
        foreach ($communications ?: [] as $preference) {
            $attributes = $preference['attributes'] ?? [];
            $key = is_string($attributes['sublist_key'] ?? null) ? $attributes['sublist_key'] : '';
            $merge = is_string($attributes['merge_field_name'] ?? null) ? $attributes['merge_field_name'] : '';
            $rows[] = ['comm_sublist', 'communications', $this->communication_label($attributes), $key, $merge];
        }

        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        $filename = 'mdp-slugs-' . ($host !== null && $host !== '' ? $host : 'site') . '.csv';

        (new \WicketWP\Support\CsvExporter())->download($filename, $rows);
    }
}
