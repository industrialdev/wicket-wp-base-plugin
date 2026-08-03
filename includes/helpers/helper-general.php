<?php

declare(strict_types=1);

// No direct access
defined('ABSPATH') || exit;

/**
 * Checks if a given string is a valid UUID (RFC 4122 compliant).
 *
 * @param string $uuid The string to check.
 *
 * @return bool True if the string is a valid UUID, false otherwise.
 */
function isValidUuid(string $uuid): bool
{
    // A regular expression that matches the standard UUID format.
    // It checks for 32 hexadecimal characters, grouped by hyphens in a 8-4-4-4-12 pattern.
    // The 'i' flag makes the match case-insensitive for hexadecimal characters (a-f/A-F).
    // The third group's first character (version) is typically 1-5 for standard UUIDs.
    // The fourth group's first character (variant) is typically 8, 9, a, or b for standard UUIDs.
    $regex = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-5][0-9a-f]{3}-[089ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    return (bool) preg_match($regex, $uuid);
}

/**
 * Map CSV header row to column definition keys using fuzzy matching.
 *
 * Normalizes header strings (lowercase, trim, underscores/hyphens → spaces) and
 * attempts to match each column definition against: declared aliases, the definition's
 * own header label, and the column key itself (underscores → spaces). Returns -1 for
 * any column that could not be matched.
 *
 * @param string[]             $headers            Raw header values from the CSV row.
 * @param array<string, array> $column_definitions Map of column_key → definition array.
 *                                                 Each definition may contain:
 *                                                 - 'enabled' (bool)   — skip when false/absent
 *                                                 - 'header'  (string) — canonical label
 *                                                 - 'aliases' (array)  — alternate labels
 *
 * @return array<string, int> Map of column_key → zero-based header index (or -1 if unmatched).
 */
function wicket_csv_resolve_headers(array $headers, array $column_definitions): array
{
    $normalized = [];
    foreach ($headers as $idx => $header) {
        $key = strtolower(trim((string) $header));
        $key = str_replace(['_', '-'], ' ', $key);
        $normalized[$key] = (int) $idx;
    }

    $index_map = [];
    foreach ($column_definitions as $column_key => $column_definition) {
        $index_map[$column_key] = -1;

        if (empty($column_definition['enabled'])) {
            continue;
        }

        $candidates = [];
        foreach ($column_definition['aliases'] ?? [] as $alias) {
            $candidates[] = strtolower(trim((string) $alias));
        }
        $label = strtolower(trim((string) ($column_definition['header'] ?? '')));
        if ($label !== '') {
            $candidates[] = $label;
        }
        $candidates[] = strtolower(str_replace('_', ' ', (string) $column_key));

        foreach (array_values(array_unique($candidates)) as $candidate) {
            if (isset($normalized[$candidate])) {
                $index_map[$column_key] = $normalized[$candidate];
                break;
            }
        }
    }

    return $index_map;
}

/**
 * Check whether the installed Wicket base plugin supports a given feature.
 *
 * Registry-backed capability detection for forward features. Add a dotted key
 * to $features in the same release that ships the feature; downstream
 * consumers then gate on it instead of version_compare(WICKET_BASE_PLUGIN_VERSION).
 * Unknown keys return false (safe fallback to legacy behavior).
 *
 * Per-key filter "wicket_supports_{$feature}" allows runtime override and is the
 * test seam for QA (e.g. add_filter('wicket_supports_<feature>', '__return_false')).
 * Full usage patterns (force-off, force-on, conditional, removal, dot-tag pitfall):
 * atlas/conventions/capability-detection.md#using-the-filter-runtime-override--test-seam.
 *
 * Key format: '<package>.<subsystem>.<capability>'
 *   package    = qa/ slug from wicket-wp-stack/qa/package-config.json
 *                (the canonical short name for the owning plugin).
 *                Expected values: base-plugin, account-centre, memberships,
 *                gravity-forms, guest-checkout, financial-fields, importer,
 *                portus, admin-org-roster, woo-order-status-limits, theme,
 *                theme-v1. NOT the text-domain and NOT the repo folder name.
 *   subsystem  = structural area inside that plugin (e.g.
 *                organization_membership, orm, renewal, registration).
 *                Called 'subsystem' not 'component' to avoid collision with the
 *                base-plugin UI components subsystem (register_component/)
 *                and with atlas using 'component' to mean a package itself.
 *   capability = the specific feature (e.g. copy_previous_assignments).
 * Example: 'base-plugin.organization_membership.copy_previous_assignments'.
 *
 * Convention: atlas/conventions/capability-detection.md (ADR 0004).
 *
 * @param string $feature Dotted feature key: '<package>.<subsystem>.<capability>'.
 * @return bool
 *
 * Note on strict_types: this file declares strict_types=1, but PHP evaluates it
 * per-CALLING file. A consumer without strict_types that passes a non-string
 * (int, null, object) gets coerced/fatal'd by the caller's own mode, not this
 * declaration. In practice the arg is always a string literal; do not rely on
 * cross-file type enforcement here.
 */
function wicket_supports(string $feature): bool
{
    $features = [
        // Key format: '<package>.<subsystem>.<capability>'
        // package = qa/ slug (see package-config.json), e.g. base-plugin.
        'base-plugin.organization_membership.copy_previous_assignments' => true,
        // Add keys here in the release that ships each feature.
    ];

    return (bool) apply_filters("wicket_supports_{$feature}", $features[$feature] ?? false);
}
