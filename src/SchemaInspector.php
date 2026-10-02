<?php

/**
 * Hidden browser for tenant MDP data relevant to form building.
 *
 * /?mdp_schemas                            HTML index: json_schemas grouped by resource type.
 * /?mdp_schemas&schema=<uuid|slug|key>     One schema as pretty JSON.
 * /?mdp_schemas&schema=<id>&view=fields    One schema's GF-mappable fields (slug, label, type, enums).
 * /?mdp_schemas&resource_types=1           Resource type list values (address/phone/web types, genders, ...).
 * /?mdp_schemas&memberships=1              Membership tier definitions (slug, code, category, flags).
 * /?mdp_schemas&communications=1           Communications preferences config (email + sublists).
 * /?mdp_schemas&format=csv                 All of the above slugs as one CSV download.
 * /?mdp_schemas&mdp_schemas_refresh=1      Bypass the transient caches.
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
        if (is_string($format) && $format === 'csv') {
            $this->render_csv();
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
     * @param string $path API path.
     *
     * @return array|false The raw API payload (envelope or list), or false on failure.
     */
    private function fetch_all(string $path)
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
                    $path,
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
            \Wicket()->log('error', 'SchemaInspector: ' . $path . ' fetch failed: ' . $e->getMessage());

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
            . '<a href="' . esc_url($this->url(['format' => 'csv'])) . '">Download slugs CSV</a>'
            . '</nav>';

        if (count($schemas) === 0) {
            $this->foot();

            return;
        }

        $groups = [];
        foreach ($schemas as $resource) {
            $type = $resource['attributes']['resource_type'] ?? null;
            $type = is_string($type) && $type !== '' ? $type : 'unknown';
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

        echo '<h1>' . esc_html($slug) . ' fields (' . esc_html((string) count($fields)) . ')</h1>';
        echo '<p class="id">' . esc_html($uuid) . ' | '
            . '<a href="' . esc_url($this->url(['schema' => $uuid])) . '">view raw JSON</a></p>';

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
            $rows[] = ['json_schema', '', $title, $slug, ''];
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
