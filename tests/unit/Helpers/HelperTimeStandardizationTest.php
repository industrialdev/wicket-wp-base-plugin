<?php

declare(strict_types=1);

namespace WicketWP\Tests\Helpers;

use Brain\Monkey;
use WicketWP\Tests\AbstractTestCase;

class HelperTimeStandardizationTest extends AbstractTestCase
{
    private FakeWicketApiClient $apiClient;

    private string|false $previousGetenvTimezone = false;

    private mixed $previousEnvTimezone = null;

    private bool $hadEnvTimezone = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once dirname(__DIR__, 3) . '/includes/helpers/helper-time.php';
        require_once dirname(__DIR__, 3) . '/includes/helpers/helper-groups.php';
        require_once dirname(__DIR__, 3) . '/includes/helpers/helper-unsorted.php';
        require_once dirname(__DIR__, 3) . '/includes/helpers/helper-connections.php';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousGetenvTimezone = getenv('WICKET_MSHIP_MDP_TIMEZONE');
        $this->hadEnvTimezone = array_key_exists('WICKET_MSHIP_MDP_TIMEZONE', $_ENV);
        $this->previousEnvTimezone = $this->hadEnvTimezone ? $_ENV['WICKET_MSHIP_MDP_TIMEZONE'] : null;

        $_ENV['WICKET_MSHIP_MDP_TIMEZONE'] = 'UTC';
        putenv('WICKET_MSHIP_MDP_TIMEZONE=UTC');

        $this->apiClient = new FakeWicketApiClient();
        Monkey\Functions\when('wicket_api_client')->justReturn($this->apiClient);
        Monkey\Functions\when('wp_parse_args')->alias(static function ($args, $defaults) {
            if (!is_array($args)) {
                $args = [];
            }

            return array_merge($defaults, $args);
        });
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

    public function test_add_group_member_defaults_start_date_to_mdp_day_start_utc_iso8601(): void
    {
        \wicket_add_group_member('person-1', 'group-1', 'member', ['skip_if_exists' => false]);

        $call = $this->apiClient->lastCall();
        $payload = $call['args']['json']['data']['attributes'] ?? [];

        $this->assertSame('post', $call['method']);
        $this->assertSame('group_members', $call['path']);
        $this->assertSame(\wicket_time_get_mdp_day_start_iso8601_utc(), $payload['start_date'] ?? null);
    }

    public function test_membership_helpers_default_dates_use_standardized_utc_iso8601(): void
    {
        $cases = [
            [
                'function' => 'wicket_assign_organization_membership',
                'args' => ['person-1', 'org-1', 'membership-1'],
                'method' => 'post',
                'path' => 'organization_memberships',
            ],
            [
                'function' => 'wicket_assign_individual_membership',
                'args' => ['person-1', 'membership-1'],
                'method' => 'post',
                'path' => 'person_memberships',
            ],
            [
                'function' => 'wicket_update_individual_membership_dates',
                'args' => ['membership-1'],
                'method' => 'patch',
                'path' => '/person_memberships/membership-1',
            ],
            [
                'function' => 'wicket_update_organization_membership_dates',
                'args' => ['membership-1'],
                'method' => 'patch',
                'path' => 'organization_memberships/membership-1',
            ],
        ];

        foreach ($cases as $case) {
            $this->apiClient->reset();

            call_user_func_array('\\' . $case['function'], $case['args']);

            $call = $this->apiClient->lastCall();
            $attributes = $call['args']['json']['data']['attributes'] ?? [];

            $this->assertSame($case['method'], $call['method']);
            $this->assertSame($case['path'], $call['path']);
            $this->assertMatchesRegularExpression('/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}Z$/', $attributes['starts_at'] ?? '');
            $this->assertMatchesRegularExpression('/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}Z$/', $attributes['ends_at'] ?? '');

            $start = new \DateTimeImmutable($attributes['starts_at']);
            $end = new \DateTimeImmutable($attributes['ends_at']);
            $delta = $end->getTimestamp() - $start->getTimestamp();

            $this->assertGreaterThanOrEqual(31536000 - 5, $delta);
            $this->assertLessThanOrEqual(31622400 + 5, $delta);
        }
    }

    public function test_end_connection_uses_standardized_utc_iso8601_end_date(): void
    {
        $this->apiClient->reset();

        $endTime = new \DateTimeImmutable('2026-03-02 15:30:00', new \DateTimeZone('America/Toronto'));
        \wicket_end_connection('connection-1', $endTime);

        $this->assertCount(2, $this->apiClient->calls);

        $patchCall = $this->apiClient->calls[1];
        $actualEndAt = $patchCall['args']['json']['data']['attributes']['ends_at'] ?? '';
        $expectedEndAt = \wicket_time_format_iso8601_utc($endTime);

        $this->assertSame('patch', $patchCall['method']);
        $this->assertSame('connections/connection-1', $patchCall['path']);
        $this->assertSame($expectedEndAt, $actualEndAt);
        $this->assertMatchesRegularExpression('/Z$/', $actualEndAt);
    }
}

class FakeWicketApiClient
{
    /** @var array<int, array<string, mixed>> */
    public array $calls = [];

    public function reset(): void
    {
        $this->calls = [];
    }

    public function post(string $path, array $args): array
    {
        $this->calls[] = [
            'method' => 'post',
            'path' => $path,
            'args' => $args,
        ];

        return ['data' => $args['json']['data'] ?? []];
    }

    public function patch(string $path, array $args): array
    {
        $this->calls[] = [
            'method' => 'patch',
            'path' => $path,
            'args' => $args,
        ];

        return ['data' => $args['json']['data'] ?? []];
    }

    public function get(string $path): array
    {
        $this->calls[] = [
            'method' => 'get',
            'path' => $path,
            'args' => [],
        ];

        if (str_starts_with($path, 'connections/')) {
            $connectionId = substr($path, strlen('connections/'));

            return [
                'data' => [
                    'id' => $connectionId,
                    'type' => 'connections',
                    'attributes' => [
                        'starts_at' => null,
                        'ends_at' => null,
                        'description' => null,
                        'custom_data_field' => null,
                        'tags' => [],
                    ],
                    'relationships' => [
                        'from' => ['data' => ['id' => 'person-1', 'type' => 'people']],
                        'to' => ['data' => ['id' => 'org-1', 'type' => 'organizations']],
                    ],
                ],
            ];
        }

        return ['data' => []];
    }

    /** @return array<string, mixed> */
    public function lastCall(): array
    {
        $last = end($this->calls);

        if ($last === false) {
            return [];
        }

        return $last;
    }
}
