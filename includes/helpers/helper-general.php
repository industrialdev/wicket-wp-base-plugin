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
