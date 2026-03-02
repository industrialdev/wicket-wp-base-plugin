<?php

declare(strict_types=1);

// No direct access
defined('ABSPATH') || exit;

/**
 * Log recoverable time helper warnings.
 *
 * @param string $message
 * @return void
 */
function wicket_time_log_warning(string $message): void
{
    if (defined('WICKET_DOING_TESTS') && constant('WICKET_DOING_TESTS') === true) {
        return;
    }

    if (function_exists('error_log')) {
        error_log('[wicket-time] ' . $message);
    }
}

/**
 * Resolve the configured MDP timezone.
 *
 * Falls back to UTC when no env var is set or the value is invalid.
 *
 * @return DateTimeZone
 */
function wicket_time_get_mdp_timezone(): DateTimeZone
{
    $env_timezone = $_ENV['WICKET_MSHIP_MDP_TIMEZONE'] ?? getenv('WICKET_MSHIP_MDP_TIMEZONE');
    $timezone = is_string($env_timezone) ? trim($env_timezone) : '';

    if ($timezone === '') {
        $timezone = 'UTC';
    }

    try {
        return new DateTimeZone($timezone);
    } catch (Exception $e) {
        wicket_time_log_warning(sprintf(
            'Invalid WICKET_MSHIP_MDP_TIMEZONE "%s". Falling back to UTC. Error: %s',
            $timezone,
            $e->getMessage()
        ));

        return new DateTimeZone('UTC');
    }
}

/**
 * Build a datetime in MDP timezone with a warned fallback for invalid input.
 *
 * @param string $date_string
 * @param DateTimeZone $mdp_timezone
 * @return DateTimeImmutable
 */
function wicket_time_parse_mdp_datetime(string $date_string, DateTimeZone $mdp_timezone): DateTimeImmutable
{
    try {
        return new DateTimeImmutable($date_string, $mdp_timezone);
    } catch (Exception $e) {
        wicket_time_log_warning(sprintf(
            'Invalid date input "%s". Falling back to "now" in timezone "%s". Error: %s',
            $date_string,
            $mdp_timezone->getName(),
            $e->getMessage()
        ));

        return new DateTimeImmutable('now', $mdp_timezone);
    }
}

/**
 * Get a UTC datetime for the provided date string.
 *
 * @param string $date_string
 * @return DateTimeImmutable
 */
function wicket_time_get_utc_datetime(string $date_string = 'now'): DateTimeImmutable
{
    $mdp_timezone = wicket_time_get_mdp_timezone();

    $date = wicket_time_parse_mdp_datetime($date_string, $mdp_timezone);

    return $date->setTimezone(new DateTimeZone('UTC'));
}

/**
 * Get the start of a day in MDP timezone, converted to UTC.
 *
 * @param string $date_string
 * @return DateTimeImmutable
 */
function wicket_time_get_mdp_day_start_utc(string $date_string = 'now'): DateTimeImmutable
{
    $mdp_timezone = wicket_time_get_mdp_timezone();

    $date = wicket_time_parse_mdp_datetime($date_string, $mdp_timezone);

    $date = $date
        ->setTimezone($mdp_timezone)
        ->setTime(0, 0, 0);

    return $date->setTimezone(new DateTimeZone('UTC'));
}

/**
 * Get the end of a day in MDP timezone, converted to UTC.
 *
 * @param string $date_string
 * @return DateTimeImmutable
 */
function wicket_time_get_mdp_day_end_utc(string $date_string = 'now'): DateTimeImmutable
{
    $mdp_timezone = wicket_time_get_mdp_timezone();

    $date = wicket_time_parse_mdp_datetime($date_string, $mdp_timezone);

    $date = $date
        ->setTimezone($mdp_timezone)
        ->setTime(23, 59, 59);

    return $date->setTimezone(new DateTimeZone('UTC'));
}

/**
 * Format a datetime as MDP-compatible UTC ISO8601 string.
 *
 * @param DateTimeInterface $date_time
 * @return string
 */
function wicket_time_format_iso8601_utc(DateTimeInterface $date_time): string
{
    $utc_date = DateTimeImmutable::createFromInterface($date_time)->setTimezone(new DateTimeZone('UTC'));

    return $utc_date->format('Y-m-d\\TH:i:s\\Z');
}

/**
 * Get an MDP day start timestamp formatted as UTC ISO8601.
 *
 * @param string $date_string
 * @return string
 */
function wicket_time_get_mdp_day_start_iso8601_utc(string $date_string = 'now'): string
{
    return wicket_time_format_iso8601_utc(wicket_time_get_mdp_day_start_utc($date_string));
}

/**
 * Get an MDP day end timestamp formatted as UTC ISO8601.
 *
 * @param string $date_string
 * @return string
 */
function wicket_time_get_mdp_day_end_iso8601_utc(string $date_string = 'now'): string
{
    return wicket_time_format_iso8601_utc(wicket_time_get_mdp_day_end_utc($date_string));
}
