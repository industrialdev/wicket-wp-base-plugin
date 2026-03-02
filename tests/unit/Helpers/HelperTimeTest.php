<?php

declare(strict_types=1);

namespace WicketWP\Tests\Helpers;

use WicketWP\Tests\AbstractTestCase;

class HelperTimeTest extends AbstractTestCase
{
    private string|false $previousGetenvTimezone = false;

    private mixed $previousEnvTimezone = null;

    private bool $hadEnvTimezone = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once dirname(__DIR__, 3) . '/includes/helpers/helper-time.php';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousGetenvTimezone = getenv('WICKET_MSHIP_MDP_TIMEZONE');
        $this->hadEnvTimezone = array_key_exists('WICKET_MSHIP_MDP_TIMEZONE', $_ENV);
        $this->previousEnvTimezone = $this->hadEnvTimezone ? $_ENV['WICKET_MSHIP_MDP_TIMEZONE'] : null;
    }

    protected function tearDown(): void
    {
        if ($this->previousGetenvTimezone === false) {
            putenv('WICKET_MSHIP_MDP_TIMEZONE');
        } else {
            putenv('WICKET_MSHIP_MDP_TIMEZONE=' . $this->previousGetenvTimezone);
        }

        if ($this->hadEnvTimezone) {
            $_ENV['WICKET_MSHIP_MDP_TIMEZONE'] = $this->previousEnvTimezone;
        } else {
            unset($_ENV['WICKET_MSHIP_MDP_TIMEZONE']);
        }

        parent::tearDown();
    }

    public function test_timezone_uses_getenv_fallback_when_env_array_missing(): void
    {
        unset($_ENV['WICKET_MSHIP_MDP_TIMEZONE']);
        putenv('WICKET_MSHIP_MDP_TIMEZONE=America/Toronto');

        $timezone = \wicket_time_get_mdp_timezone();

        $this->assertSame('America/Toronto', $timezone->getName());
    }

    public function test_timezone_falls_back_to_utc_when_env_value_invalid(): void
    {
        unset($_ENV['WICKET_MSHIP_MDP_TIMEZONE']);
        putenv('WICKET_MSHIP_MDP_TIMEZONE=Not/ARealTimezone');

        $timezone = \wicket_time_get_mdp_timezone();

        $this->assertSame('UTC', $timezone->getName());
    }

    public function test_invalid_date_input_falls_back_to_now_utc(): void
    {
        $_ENV['WICKET_MSHIP_MDP_TIMEZONE'] = 'UTC';
        putenv('WICKET_MSHIP_MDP_TIMEZONE=UTC');

        $before = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $date = \wicket_time_get_utc_datetime('this-is-not-a-date');
        $after = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $this->assertGreaterThanOrEqual($before->getTimestamp() - 1, $date->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp() + 1, $date->getTimestamp());
    }

    public function test_invalid_date_input_still_produces_day_boundaries(): void
    {
        $_ENV['WICKET_MSHIP_MDP_TIMEZONE'] = 'America/Toronto';
        putenv('WICKET_MSHIP_MDP_TIMEZONE=America/Toronto');

        $start = \wicket_time_get_mdp_day_start_utc('not-a-date');
        $end = \wicket_time_get_mdp_day_end_utc('not-a-date');

        $timezone = new \DateTimeZone('America/Toronto');

        $this->assertSame('00:00:00', $start->setTimezone($timezone)->format('H:i:s'));
        $this->assertSame('23:59:59', $end->setTimezone($timezone)->format('H:i:s'));
    }
}
