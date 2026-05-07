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
