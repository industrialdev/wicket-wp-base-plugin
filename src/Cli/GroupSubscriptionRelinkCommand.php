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
 * @see atlas://plans/fix-group-subscription-membership-targeting.md (change 7)
 * @see atlas://audits/2026-10-group-subscription-membership-damage.md (W5)
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
    private const FIELDS = ['subscription', 'group', 'role', 'old_uuid', 'new_uuid', 'old_end', 'new_end', 'action'];

    /**
     * Point each group subscription at its row, and set that row's end date.
     *
     * Reads active, on-hold, and pending-cancel subscriptions that activated a group
     * (`_group_assigned_uuid`). For each group and role, the target row is the row with the
     * subscription start date. If no row agrees, the target is the active row with the latest
     * start date. The command stores the target UUID and sets the target end date from the
     * subscription: the next payment date, or the end date.
     *
     * Actions in the report: none, relink, end_date, relink+end_date, no_target (nothing
     * changes), lookup_failed, failed (the MDP PATCH failed).
     *
     * ## OPTIONS
     *
     * [--apply]
     * : Write the changes. Without it, the command is a dry run and writes nothing.
     *
     * [--subscription=<id>]
     * : Relink one subscription ID instead of all group subscriptions.
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
        $report = [];

        foreach ($this->subscriptions($assoc_args) as $sub) {
            foreach ($this->relinkSubscription($sub, $apply) as $line) {
                $report[] = $line;
            }
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
     * Subscriptions in scope: one ID, or every renewable subscription that activated a group.
     *
     * @param array<string, string> $assoc_args Command flags.
     *
     * @return list<WC_Subscription>
     */
    private function subscriptions(array $assoc_args): array
    {
        if (!empty($assoc_args['subscription'])) {
            $sub = wcs_get_subscription((int) $assoc_args['subscription']);
            if (!$sub instanceof WC_Subscription) {
                WP_CLI::error('Subscription ' . $assoc_args['subscription'] . ' not found.');
            }

            return [$sub];
        }

        $subs = wcs_get_subscriptions([
            'subscription_status'    => self::STATUSES,
            'subscriptions_per_page' => -1,
        ]);

        // Only subscriptions that activated a group own a row.
        return array_values(array_filter($subs, static fn ($sub): bool => !empty(get_post_meta($sub->get_id(), '_group_assigned_uuid', false))));
    }

    /**
     * Relink each activated group of one subscription.
     *
     * @param WC_Subscription $sub   The subscription.
     * @param bool            $apply Write the changes when true.
     *
     * @return list<array<string, string>> One report line for each group.
     */
    private function relinkSubscription(WC_Subscription $sub, bool $apply): array
    {
        $lines = [];
        $user = get_user_by('id', $sub->get_user_id());
        if (!$user) {
            return $lines;
        }

        $subscription_id = $sub->get_id();
        $assigned_group_ids = (array) get_post_meta($subscription_id, '_group_assigned_uuid', false);
        $start_date = wicket_group_subscription_start_date($sub);
        $end_time = $this->endTime($sub);
        $new_end = $end_time ? (new DateTime('@' . $end_time))->setTimezone(wp_timezone())->format('Y-m-d') : '';

        foreach (wicket_get_subscription_group_ids($sub) as $role_key => $group_id) {
            // A group the subscription never activated owns no row (plan change 5).
            if (!in_array($group_id, $assigned_group_ids, true)) {
                continue;
            }
            // Keys are "role|product_id", so one subscription can hold the same role in two groups.
            $role = explode('|', (string) $role_key)[0];
            $meta_key = wicket_group_subscription_membership_meta_key($group_id, $role);
            $old_uuid = (string) get_post_meta($subscription_id, $meta_key, true);
            $line = [
                'subscription' => (string) $subscription_id,
                'group'        => (string) $group_id,
                'role'         => $role,
                'old_uuid'     => $old_uuid,
                'new_uuid'     => '',
                'old_end'      => '',
                'new_end'      => $new_end,
                'action'       => 'none',
            ];

            $rows = wicket_get_group_members_all_pages($group_id, [
                'person_uuid' => $user->user_login,
                'role'        => $role,
                'active'      => null,
            ]);
            if (is_wp_error($rows)) {
                $line['action'] = 'lookup_failed';
                $lines[] = $line;
                continue;
            }

            $target = wicket_group_subscription_start_date_row($rows, $role, $start_date)
                ?? wicket_group_subscription_latest_active_row($rows, $role);
            if ($target === null) {
                $line['action'] = 'no_target';
                $lines[] = $line;
                continue;
            }

            $line['new_uuid'] = (string) ($target['attributes']['uuid'] ?? $target['id'] ?? '');
            $line['old_end'] = substr((string) ($target['attributes']['end_date'] ?? ''), 0, 10);
            $relink = $line['new_uuid'] !== $old_uuid;
            $end_date = $new_end !== '' && $line['old_end'] !== $new_end;
            $line['action'] = $relink && $end_date ? 'relink+end_date' : ($relink ? 'relink' : ($end_date ? 'end_date' : 'none'));

            if ($apply && ($relink || $end_date)) {
                $line['action'] = $this->write($sub, $line, $meta_key, $end_time, $relink, $end_date);
            }
            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * Store the target UUID and PATCH the target end date.
     *
     * @param WC_Subscription       $sub      The subscription.
     * @param array<string, string> $line     The report line for the group.
     * @param string                $meta_key The subscription meta key that stores the row UUID.
     * @param int                   $end_time Unix timestamp of the end date to set.
     * @param bool                  $relink   Store the target UUID.
     * @param bool                  $end_date PATCH the target end date.
     *
     * @return string The report action: the planned action, or failed.
     */
    private function write(WC_Subscription $sub, array $line, string $meta_key, int $end_time, bool $relink, bool $end_date): string
    {
        if ($end_date) {
            // Same format as the handlers: the site timezone with its offset.
            $response = wicket_wicket_update_group_membership($line['new_uuid'], [
                'end_date' => (new DateTime('@' . $end_time))->setTimezone(wp_timezone())->format('Y-m-d\TH:i:sP'),
            ]);
            if (is_wp_error($response)) {
                wicket_wc_log_group_sync(['RELINK_PATCH_FAILED', 'subscription_id' => $sub->get_id(), 'line' => $line, 'error' => $response->get_error_message()]);

                return 'failed';
            }
        }
        if ($relink) {
            update_post_meta($sub->get_id(), $meta_key, $line['new_uuid']);
        }

        wicket_wc_log_group_sync(['RELINK_APPLIED', 'subscription_id' => $sub->get_id(), 'line' => $line], Log::LOG_LEVEL_AUDIT);
        $sub->add_order_note(sprintf(
            'Group subscription relinked for group %s: row %s (was %s), end date %s (was %s).',
            $line['group'],
            $line['new_uuid'],
            $line['old_uuid'] !== '' ? $line['old_uuid'] : 'none',
            $line['new_end'],
            $line['old_end'] !== '' ? $line['old_end'] : 'none'
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
}
