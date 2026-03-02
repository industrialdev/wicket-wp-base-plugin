<?php

declare(strict_types=1);

// No direct access
defined('ABSPATH') || exit;

/**
 * Resolve the configured MDP timezone.
 *
 * Falls back to UTC when no env var is set or the value is invalid.
 *
 * @return \DateTimeZone
 */
function wicket_time_get_mdp_timezone(): \DateTimeZone
{
    $timezone = (string) ($_ENV['WICKET_MSHIP_MDP_TIMEZONE'] ?? 'UTC');

    try {
        return new \DateTimeZone($timezone);
    } catch (\Exception $e) {
        return new \DateTimeZone('UTC');
    }
}

/**
 * Get a UTC datetime for the provided date string.
 *
 * @param string $date_string
 * @return \DateTimeImmutable
 */
function wicket_time_get_utc_datetime(string $date_string = 'now'): \DateTimeImmutable
{
    $mdp_timezone = wicket_time_get_mdp_timezone();

    try {
        $date = new \DateTimeImmutable($date_string, $mdp_timezone);
    } catch (\Exception $e) {
        $date = new \DateTimeImmutable('now', $mdp_timezone);
    }

    return $date->setTimezone(new \DateTimeZone('UTC'));
}

/**
 * Get the start of a day in MDP timezone, converted to UTC.
 *
 * @param string $date_string
 * @return \DateTimeImmutable
 */
function wicket_time_get_mdp_day_start_utc(string $date_string = 'now'): \DateTimeImmutable
{
    $mdp_timezone = wicket_time_get_mdp_timezone();

    try {
        $date = new \DateTimeImmutable($date_string, $mdp_timezone);
    } catch (\Exception $e) {
        $date = new \DateTimeImmutable('now', $mdp_timezone);
    }

    $date = $date
        ->setTimezone($mdp_timezone)
        ->setTime(0, 0, 0);

    return $date->setTimezone(new \DateTimeZone('UTC'));
}

/**
 * Get the end of a day in MDP timezone, converted to UTC.
 *
 * @param string $date_string
 * @return \DateTimeImmutable
 */
function wicket_time_get_mdp_day_end_utc(string $date_string = 'now'): \DateTimeImmutable
{
    $mdp_timezone = wicket_time_get_mdp_timezone();

    try {
        $date = new \DateTimeImmutable($date_string, $mdp_timezone);
    } catch (\Exception $e) {
        $date = new \DateTimeImmutable('now', $mdp_timezone);
    }

    $date = $date
        ->setTimezone($mdp_timezone)
        ->setTime(23, 59, 59);

    return $date->setTimezone(new \DateTimeZone('UTC'));
}

/**
 * Format a datetime as MDP-compatible UTC ISO8601 string.
 *
 * @param \DateTimeInterface $date_time
 * @return string
 */
function wicket_time_format_iso8601_utc(\DateTimeInterface $date_time): string
{
    $utc_date = \DateTimeImmutable::createFromInterface($date_time)->setTimezone(new \DateTimeZone('UTC'));

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
