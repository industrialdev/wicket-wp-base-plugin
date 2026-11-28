<?php

declare(strict_types=1);

namespace WicketWP\Cli;

use DateTime;
use WC_Subscription;
use WicketWP\Log;
use WP_CLI;
use WP_CLI\Utils;

// No direct access
defined('ABSPATH') || exit;

/**
 * Relinks group subscriptions to their correct MDP group membership rows.
 *
 * Registered as `wp wicket group-subscriptions`. Run `relink` immediately after an MDP data
 * repair. A subscription can store the UUID of a row that an earlier defect revived; after the
 * repair, the next renewal or date change would change that old row again.
 *
 * The command never creates rows, never changes a start date, and never changes a row other
 * than the target row of a subscription.
 *
 * Shared rows: two active subscriptions of one person can link the same row. On MSA, a FIG
 * is renewed with a new order while the current subscription is still active, and the duplicate
 * check links the new subscription to the existing active row. MSA's AutomateWoo workflow 3122
 * then rewrites the subscription dates after checkout (audit appendix, "MSA environment"), so
 * each subscription carries its own end date. Setting the row once per subscription let the last
 * one processed win, and could shorten the newer, paid period. The command therefore gives a
 * shared row the latest end date of all subscriptions that link it, in one PATCH.
 *
 * @see atlas://plans/fix-group-subscription-membership-targeting.md (change 7)
 * @see atlas://audits/2026-10-group-subscription-membership-damage.md (W5, MSA environment)
 *
 * @since 2.14.0
 */
class GroupSubscriptionRelinkCommand
{
    /**
     * Subscription statuses that can still renew or change dates.
     *
     * @var list<string>
     */
    private const STATUSES = ['active', 'on-hold', 'pending-cancel'];

    /**
     * Report columns, one row for each subscription group.
     *
     * @var list<string>
     */
    private const FIELDS = ['subscription', 'group', 'role', 'old_uuid', 'new_uuid', 'shared_with', 'sub_end', 'old_end', 'new_end', 'action'];

    /**
     * Point each group subscription at its row, and set that row's end date.
     *
     * Reads active, on-hold, and pending-cancel subscriptions that activated a group
     * (`_group_assigned_uuid`). For each group and role, the target row is the row with the
     * subscription start date. If no row agrees, the target is the active row with the latest
     * start date. The command stores the target UUID on each subscription. Each target row gets
     * one end date: the latest of its subscriptions' next payment dates (or end dates).
     *
     * Actions in the report: none, relink, end_date, relink+end_date, no_target (nothing
     * changes), lookup_failed, failed (the MDP PATCH failed). `shared_with` lists the other
     * subscriptions that link the same row. On a shared row, only the first line PATCHes.
     *
     * ## OPTIONS
     *
     * [--apply]
     * : Write the changes. Without it, the command is a dry run and writes nothing.
     *
     * [--subscription=<id>]
     * : Report and write only this subscription ID. A shared row still gets the latest end date
     *   of every subscription that links it.
     *
     * [--format=<format>]
     * : Output format for the report.
     * ---
     * default: table
     * options:
     *   - table
     *   - csv
     *   - json
     * ---
     *
     * ## EXAMPLES
     *
     *     # Review the planned changes after the MDP repair.
     *     $ wp wicket group-subscriptions relink --format=csv > relink-plan.csv
     *
     *     # Apply them.
     *     $ wp wicket group-subscriptions relink --apply
     *
     * @param array<int, string>    $args       Positional arguments (unused).
     * @param array<string, string> $assoc_args Flags: apply, subscription, format.
     *
     * @return void
     */
    public function relink(array $args, array $assoc_args): void
    {
        if (empty(wicket_get_option('wicket_admin_settings_group_assignment_subscription_products'))) {
            WP_CLI::warning('Group subscriptions are off on this site. Nothing to relink.');

            return;
        }

        $apply = (bool) Utils\get_flag_value($assoc_args, 'apply', false);
        $format = (string) Utils\get_flag_value($assoc_args, 'format', 'table');
        $only = (string) Utils\get_flag_value($assoc_args, 'subscription', '');

        $plan = $this->plan($this->subscriptions());
        if ($only !== '') {
            $plan = array_values(array_filter($plan, static fn (array $item): bool => $item['line']['subscription'] === $only));
            if ($plan === []) {
                WP_CLI::error('Subscription ' . $only . ' is not a renewable group subscription that activated a group.');
            }
        }
        // After the filter, so a single-subscription run still PATCHes its shared row once.
        $plan = $this->assignPatches($plan);

        $report = [];
        foreach ($plan as $item) {
            if ($apply && $item['line']['action'] !== 'none' && $item['target'] !== null) {
                $item['line']['action'] = $this->write($item);
            }
            $report[] = $item['line'];
        }

        if ($report === []) {
            WP_CLI::success('No group subscriptions to relink.');

            return;
        }

        Utils\format_items($format, $report, self::FIELDS);

        $changes = count(array_filter($report, static fn (array $line): bool => in_array($line['action'], ['relink', 'end_date', 'relink+end_date'], true)));
        $problems = count(array_filter($report, static fn (array $line): bool => in_array($line['action'], ['no_target', 'lookup_failed', 'failed'], true)));
        $summary = sprintf('%d change(s), %d to review, %d line(s).', $changes, $problems, count($report));
        if ($apply) {
            WP_CLI::success('Applied. ' . $summary);
        } else {
            WP_CLI::log('Dry run. ' . $summary . ' Run again with --apply to write the changes.');
        }
    }

    /**
     * Every renewable subscription that activated a group.
     *
     * All of them are always read, so a shared row sees every subscription that links it.
     *
     * @return list<WC_Subscription>
     */
    private function subscriptions(): array
    {
        $subs = wcs_get_subscriptions([
            'subscription_status'    => self::STATUSES,
            'subscriptions_per_page' => -1,
        ]);

        // Only subscriptions that activated a group own a row.
        return array_values(array_filter($subs, static fn ($sub): bool => !empty(get_post_meta($sub->get_id(), '_group_assigned_uuid', false))));
    }

    /**
     * Build the full plan: find each target row, then give each row one end date.
     *
     * Pass 1 finds the target row of each subscription group. Pass 2 groups the lines by target
     * row. The row end date is the latest subscription end date of the group. Nothing is written
     * here; assignPatches() decides which line PATCHes.
     *
     * @param list<WC_Subscription> $subs The subscriptions in scope.
     *
     * @return list<array{sub: WC_Subscription, meta_key: string, target: array|null, end_time: int, relink: bool, row_change: bool, end_date: bool, line: array<string, string>}>
     */
    private function plan(array $subs): array
    {
        $plan = [];
        foreach ($subs as $sub) {
            foreach ($this->targets($sub) as $item) {
                $plan[] = $item;
            }
        }

        // Pass 2: one end date for each target row, the latest of the subscriptions that link it.
        foreach ($this->byRow($plan) as $indexes) {
            $row_end_time = max(array_map(static fn (int $i): int => $plan[$i]['end_time'], $indexes));
            $row_end = $row_end_time ? $this->siteDate($row_end_time) : '';
            $ids = array_map(static fn (int $i): string => $plan[$i]['line']['subscription'], $indexes);
            foreach ($indexes as $i) {
                $plan[$i]['end_time'] = $row_end_time;
                $plan[$i]['row_change'] = $row_end !== '' && $plan[$i]['line']['old_end'] !== $row_end;
                $plan[$i]['line']['new_end'] = $row_end;
                $plan[$i]['line']['shared_with'] = implode(',', array_values(array_diff($ids, [$plan[$i]['line']['subscription']])));
            }
        }

        return $plan;
    }

    /**
     * Give each changed row exactly one PATCH: the first line of the row in the plan.
     *
     * @param list<array{sub: WC_Subscription, meta_key: string, target: array|null, end_time: int, relink: bool, row_change: bool, end_date: bool, line: array<string, string>}> $plan The plan.
     *
     * @return list<array{sub: WC_Subscription, meta_key: string, target: array|null, end_time: int, relink: bool, row_change: bool, end_date: bool, line: array<string, string>}>
     */
    private function assignPatches(array $plan): array
    {
        foreach ($this->byRow($plan) as $indexes) {
            foreach ($indexes as $position => $i) {
                $plan[$i]['end_date'] = $position === 0 && $plan[$i]['row_change'];
                $plan[$i]['line']['action'] = $this->action($plan[$i]['relink'], $plan[$i]['end_date']);
            }
        }

        return $plan;
    }

    /**
     * Plan indexes grouped by target row UUID. Lines without a target are left out.
     *
     * @param list<array{target: array|null, line: array<string, string>}> $plan The plan.
     *
     * @return array<string, list<int>>
     */
    private function byRow(array $plan): array
    {
        $by_row = [];
        foreach ($plan as $index => $item) {
            if ($item['target'] !== null) {
                $by_row[$item['line']['new_uuid']][] = $index;
            }
        }

        return $by_row;
    }

    /**
     * Pass 1 for one subscription: the target row of each activated group.
     *
     * @param WC_Subscription $sub The subscription.
     *
     * @return list<array{sub: WC_Subscription, meta_key: string, target: array|null, end_time: int, relink: bool, row_change: bool, end_date: bool, line: array<string, string>}>
     */
    private function targets(WC_Subscription $sub): array
    {
        $items = [];
        $user = get_user_by('id', $sub->get_user_id());
        if (!$user) {
            return $items;
        }

        $subscription_id = $sub->get_id();
        $assigned_group_ids = (array) get_post_meta($subscription_id, '_group_assigned_uuid', false);
        $start_date = wicket_group_subscription_start_date($sub);
        $end_time = $this->endTime($sub);

        foreach (wicket_get_subscription_group_ids($sub) as $role_key => $group_id) {
            // A group the subscription never activated owns no row (plan change 5).
            if (!in_array($group_id, $assigned_group_ids, true)) {
                continue;
            }
            // Keys are "role|product_id", so one subscription can hold the same role in two groups.
            $role = explode('|', (string) $role_key)[0];
            $meta_key = wicket_group_subscription_membership_meta_key($group_id, $role);
            $old_uuid = (string) get_post_meta($subscription_id, $meta_key, true);
            $item = [
                'sub'      => $sub,
                'meta_key' => $meta_key,
                'target'   => null,
                'end_time'   => $end_time,
                'relink'     => false,
                'row_change' => false,
                'end_date'   => false,
                'line'     => [
                    'subscription' => (string) $subscription_id,
                    'group'        => (string) $group_id,
                    'role'         => $role,
                    'old_uuid'     => $old_uuid,
                    'new_uuid'     => '',
                    'shared_with'  => '',
                    'sub_end'      => $end_time ? $this->siteDate($end_time) : '',
                    'old_end'      => '',
                    'new_end'      => '',
                    'action'       => 'none',
                ],
            ];

            $rows = wicket_get_group_members_all_pages($group_id, [
                'person_uuid' => $user->user_login,
                'role'        => $role,
                'active'      => null,
            ]);
            if (is_wp_error($rows)) {
                $item['line']['action'] = 'lookup_failed';
                $items[] = $item;
                continue;
            }

            $target = wicket_group_subscription_start_date_row($rows, $role, $start_date)
                ?? wicket_group_subscription_latest_active_row($rows, $role);
            if ($target === null) {
                $item['line']['action'] = 'no_target';
                $items[] = $item;
                continue;
            }

            $item['target'] = $target;
            $item['line']['new_uuid'] = (string) ($target['attributes']['uuid'] ?? $target['id'] ?? '');
            $item['line']['old_end'] = substr((string) ($target['attributes']['end_date'] ?? ''), 0, 10);
            $item['relink'] = $item['line']['new_uuid'] !== $old_uuid;
            $items[] = $item;
        }

        return $items;
    }

    /**
     * The report action for a planned line.
     *
     * @param bool $relink   The stored UUID changes.
     * @param bool $end_date The row end date changes.
     *
     * @return string none, relink, end_date, or relink+end_date.
     */
    private function action(bool $relink, bool $end_date): string
    {
        if ($relink && $end_date) {
            return 'relink+end_date';
        }

        return $relink ? 'relink' : ($end_date ? 'end_date' : 'none');
    }

    /**
     * Apply one planned line: PATCH the row end date, then store the target UUID.
     *
     * @param array{sub: WC_Subscription, meta_key: string, target: array|null, end_time: int, relink: bool, row_change: bool, end_date: bool, line: array<string, string>} $item The planned line.
     *
     * @return string The report action: the planned action, or failed.
     */
    private function write(array $item): string
    {
        $sub = $item['sub'];
        $line = $item['line'];
        if ($item['end_date']) {
            // Same format as the handlers: the site timezone with its offset.
            $response = wicket_wicket_update_group_membership($line['new_uuid'], [
                'end_date' => (new DateTime('@' . $item['end_time']))->setTimezone(wp_timezone())->format('Y-m-d\TH:i:sP'),
            ]);
            if (is_wp_error($response)) {
                wicket_wc_log_group_sync(['RELINK_PATCH_FAILED', 'subscription_id' => $sub->get_id(), 'line' => $line, 'error' => $response->get_error_message()]);

                return 'failed';
            }
        }
        if ($item['relink']) {
            update_post_meta($sub->get_id(), $item['meta_key'], $line['new_uuid']);
        }

        wicket_wc_log_group_sync(['RELINK_APPLIED', 'subscription_id' => $sub->get_id(), 'line' => $line], Log::LOG_LEVEL_AUDIT);
        $sub->add_order_note(sprintf(
            'Group subscription relinked for group %s: row %s (was %s), row end date %s (was %s)%s.',
            $line['group'],
            $line['new_uuid'],
            $line['old_uuid'] !== '' ? $line['old_uuid'] : 'none',
            $line['new_end'],
            $line['old_end'] !== '' ? $line['old_end'] : 'none',
            $line['shared_with'] !== '' ? ', row shared with subscription ' . $line['shared_with'] : ''
        ));

        return $line['action'];
    }

    /**
     * The end date the handlers give the row: the next payment date, or else the end date.
     *
     * @param WC_Subscription $sub The subscription.
     *
     * @return int Unix timestamp, or 0 when the subscription has neither date.
     */
    private function endTime(WC_Subscription $sub): int
    {
        $next_payment = (int) $sub->get_time('next_payment');

        return $next_payment !== 0 ? $next_payment : (int) $sub->get_time('end_date');
    }

    /**
     * A timestamp as `Y-m-d` in the site timezone, the day the MDP stores.
     *
     * @param int $time Unix timestamp.
     *
     * @return string The date.
     */
    private function siteDate(int $time): string
    {
        return (new DateTime('@' . $time))->setTimezone(wp_timezone())->format('Y-m-d');
    }
}
