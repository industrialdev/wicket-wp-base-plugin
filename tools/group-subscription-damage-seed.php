<?php

declare(strict_types=1);

/**
 * Seed MSA-shaped group membership damage on a TEST site, for WWID-2835 / WWID-2741.
 *
 * Builds the end state the pre-fix group subscription code left on MSA (atlas audit
 * audits/2026-10-group-subscription-membership-damage.md, Tables A and B), so the fix can be
 * run against damaged data before any cleanup.
 *
 * Usage (on the test site):
 *   SEED="$(wp plugin path wicket-wp-base-plugin --dir)/tools/group-subscription-damage-seed.php"
 *   wp eval-file "$SEED" seed            # create personas, MDP rows, subscriptions
 *   wp eval-file "$SEED" report > base.json
 *   wp eval-file "$SEED" diff base.json  # what changed since the snapshot
 *   wp eval-file "$SEED" reset           # delete seeded rows and subscriptions
 *
 * Test tooling for the WWID-2741 branch only. It runs only on one host (WICKET_GS_SEED_HOST, else
 * GS_SEED_HOST), never with the prod Wicket environment, and it exits on a
 * direct web request. Remove it from the branch before the PR merges, so client sites never get it.
 *
 * Seeding never runs the group subscription hooks: it blanks the group-products option for this
 * process only. AutomateWoo must be inactive while seeding (its async workflows would run later
 * with the real option and PATCH rows). Pass "allow-aw" to skip that check.
 *
 * The rows are written in MSA's order (import history oldest first, then the current-year row,
 * then the PATCH that revives the oldest row, then the site row), so the API returns them in the
 * same order the fix sees on MSA.
 */

if (!defined('ABSPATH') || !defined('WP_CLI')) {
    exit;
}

const GS_SEED_HOST = 'product-test-2.ind.ninja';
const GS_SEED_OPTION = 'wicket_gs_seed_manifest';
const GS_SEED_EMAIL = 'gs-seed-%s@example.com';

$gs_cmd = $args[0] ?? '';

// ---------------------------------------------------------------------------------------------
// Guards
// ---------------------------------------------------------------------------------------------

/**
 * The one host the seeder may write to.
 *
 * A test site sets WICKET_GS_SEED_HOST (constant, or env from Bedrock .env) to move the seeder
 * without a code change. Without it, the default GS_SEED_HOST applies.
 *
 * @return string Host name, for example product-test-2.ind.ninja.
 */
function gs_seed_allowed_host()
{
    if (defined('WICKET_GS_SEED_HOST') && WICKET_GS_SEED_HOST) {
        return (string) WICKET_GS_SEED_HOST;
    }
    $env = getenv('WICKET_GS_SEED_HOST') ?: ($_ENV['WICKET_GS_SEED_HOST'] ?? '');

    return $env ? (string) $env : GS_SEED_HOST;
}

if (wp_parse_url(home_url(), PHP_URL_HOST) !== gs_seed_allowed_host()) {
    WP_CLI::error('Refusing to run: this site is ' . home_url() . ', not ' . gs_seed_allowed_host() . '. Set WICKET_GS_SEED_HOST to allow a different test host.');
}
if (wicket_get_option('wicket_admin_settings_environment') === 'prod') {
    WP_CLI::error('Refusing to run: the Wicket environment setting is prod.');
}

// Seeding and reset must not fire the integration. Every handler returns early on an empty option.
function gs_seed_mute_integration()
{
    add_filter('option_wicket_settings', function ($options) {
        $options = is_array($options) ? $options : [];
        $options['wicket_admin_settings_group_assignment_subscription_products'] = '';

        return $options;
    }, 999);
}

// ---------------------------------------------------------------------------------------------
// MDP and WooCommerce helpers
// ---------------------------------------------------------------------------------------------

/** All rows of one person in one group, active and inactive, in API order. */
function gs_seed_rows($group_uuid, $person_uuid)
{
    $client = wicket_api_client();
    $rows = [];
    $page = 1;
    do {
        $res = $client->get("groups/$group_uuid/people", ['query' => [
            'filter' => ['person_uuid_eq' => $person_uuid],
            'page'   => ['number' => $page, 'size' => 100],
        ]]);
        $rows = array_merge($rows, $res['data'] ?? []);
        $pages = (int) ($res['meta']['page']['total_pages'] ?? 1);
        $page++;
    } while ($page <= $pages);

    return $rows;
}

/** Create one row. Returns its UUID. */
function gs_seed_row($person, $group, $role, $start, $end)
{
    $res = wicket_wicket_add_group_member($person, $group, $role, $start, $end, false);
    if (is_wp_error($res)) {
        WP_CLI::error("Row create failed ($group $start-$end): " . $res->get_error_message());
    }

    return $res['data']['attributes']['uuid'] ?? $res['data']['id'];
}

/** Import-shaped history: one row per year, oldest first. Returns the UUIDs. */
function gs_seed_history($person, $group, $role, $from, $to)
{
    $uuids = [];
    for ($y = $from; $y <= $to; $y++) {
        $uuids[] = gs_seed_row($person, $group, $role, "$y-01-01", "$y-12-31");
    }

    return $uuids;
}

/** PATCH an end date, the way the old code did to data[0]. */
function gs_seed_patch_end($uuid, $end)
{
    $res = wicket_wicket_update_group_membership($uuid, ['end_date' => $end]);
    if (is_wp_error($res)) {
        WP_CLI::error("Row PATCH failed ($uuid): " . $res->get_error_message());
    }
}

/** Timestamp for noon, site time, on a date. Noon keeps the date the same in UTC. */
function gs_seed_ts($date)
{
    return (new DateTime("$date 12:00:00", wp_timezone()))->getTimestamp();
}

/** The date string the old activation sent: the timestamp in the site timezone. */
function gs_seed_site_iso($ts)
{
    return (new DateTime('@' . $ts))->setTimezone(wp_timezone())->format('Y-m-d\TH:i:sP');
}

/** Find or create the MDP person and the WP user (login = person UUID). */
function gs_seed_person($key)
{
    $email = sprintf(GS_SEED_EMAIL, $key);
    $person = wicket_get_person_by_email($email);
    if (!$person) {
        $created = wicket_create_person('GS Seed', strtoupper($key), $email);
        if (is_wp_error($created) || empty($created['data']['id'])) {
            WP_CLI::error("Person create failed for $email.");
        }
        $person = $created['data'];
    }
    $uuid = $person['id'];
    $user = get_user_by('login', $uuid);
    $user_id = $user ? $user->ID : wp_insert_user([
        'user_login' => $uuid,
        'user_email' => $email,
        'user_pass'  => wp_generate_password(),
        'first_name' => 'GS Seed',
        'last_name'  => strtoupper($key),
        'role'       => 'customer',
    ]);
    if (is_wp_error($user_id)) {
        WP_CLI::error("WP user create failed for $email: " . $user_id->get_error_message());
    }

    return [$uuid, $user_id];
}

/**
 * Create a subscription in the state the old code left it.
 * $s: start, end (dates), next (date or null), status, products, assigned (bool: old activation ran),
 * notes (strings).
 */
function gs_seed_subscription($user_id, array $products, array $s)
{
    $first = wc_get_product($products[0]['product_id']);
    $sub = wcs_create_subscription([
        'customer_id'      => $user_id,
        'start_date'       => gmdate('Y-m-d H:i:s', gs_seed_ts($s['start'])),
        'billing_period'   => WC_Subscriptions_Product::get_period($first) ?: 'year',
        'billing_interval' => WC_Subscriptions_Product::get_interval($first) ?: 1,
        'status'           => 'pending',
    ]);
    if (is_wp_error($sub)) {
        WP_CLI::error('Subscription create failed: ' . $sub->get_error_message());
    }
    foreach ($products as $p) {
        $sub->add_product(wc_get_product($p['product_id']), 1);
    }
    $sub->calculate_totals();

    $dates = [];
    if (!empty($s['next'])) {
        $dates['next_payment'] = gmdate('Y-m-d H:i:s', gs_seed_ts($s['next']));
    }
    if (!empty($s['end'])) {
        $dates['end'] = gmdate('Y-m-d H:i:s', gs_seed_ts($s['end']));
    }
    if ($dates) {
        $sub->update_dates($dates);
    }
    if ($s['status'] !== 'pending') {
        $sub->update_status($s['status']);
    }

    // Pre-fix meta: _group_assigned_uuid per group, the activation next payment, no row UUID.
    if (!empty($s['assigned'])) {
        foreach ($products as $p) {
            add_post_meta($sub->get_id(), '_group_assigned_uuid', $p['group']);
        }
        add_post_meta($sub->get_id(), '_group_assigned_next_payment', gs_seed_ts($s['assigned_next']));
    }
    foreach ($s['notes'] ?? [] as $note) {
        $sub->add_order_note($note);
    }
    $sub->add_order_note('GS SEED: ' . $s['label']);

    return $sub->get_id();
}

/** Group subscription products on this site: [{product_id, group, role, name}], one per group. */
function gs_seed_group_products()
{
    $category = wicket_get_option('wicket_admin_settings_group_assignment_product_category');
    $ids = get_posts([
        'post_type'   => 'product',
        'post_status' => 'publish',
        'numberposts' => -1,
        'fields'      => 'ids',
        'meta_query'  => [['key' => '_group_assignment_uuid', 'compare' => '!=', 'value' => '']],
    ]);
    $found = [];
    foreach ($ids as $id) {
        $group = get_post_meta($id, '_group_assignment_uuid', true);
        $role = get_post_meta($id, '_group_role_assignment_slug', true);
        $product = wc_get_product($id);
        if (!$group || !$role || isset($found[$group]) || !has_term($category, 'product_cat', $id)) {
            continue;
        }
        if (!$product || !WC_Subscriptions_Product::is_subscription($product) || $product->is_type('variable-subscription')) {
            continue;
        }
        $info = wicket_wicket_get_group_info($group);
        $found[$group] = [
            'product_id' => $id,
            'group'      => $group,
            'role'       => $role,
            'name'       => $info['data']['attributes']['name'] ?? $group,
        ];
    }

    return array_values($found);
}

// ---------------------------------------------------------------------------------------------
// Commands
// ---------------------------------------------------------------------------------------------

function gs_seed_cmd_seed($args)
{
    if (get_option(GS_SEED_OPTION)) {
        WP_CLI::error('Already seeded. Run reset first.');
    }
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    if (!in_array('allow-aw', $args, true) && is_plugin_active('automatewoo/automatewoo.php')) {
        WP_CLI::error('AutomateWoo is active. Deactivate it while seeding, or pass allow-aw.');
    }
    $gp = gs_seed_group_products();
    if (count($gp) < 1) {
        WP_CLI::error('No simple subscription products with _group_assignment_uuid in the group category.');
    }
    if (count($gp) < 3) {
        WP_CLI::warning(count($gp) . ' group product(s) found. P3 and P4 use them all; 3 match MSA best.');
    }
    gs_seed_mute_integration();

    $g1 = $gp[0];
    $three = array_slice($gp, 0, 3);
    $manifest = ['seeded_at' => gmdate('c'), 'products' => $gp, 'personas' => []];
    $add = function ($key, $label, $person, $user_id, $groups, $subs, $expect) use (&$manifest) {
        $manifest['personas'][$key] = compact('label', 'person', 'user_id', 'groups', 'subs', 'expect');
        update_option(GS_SEED_OPTION, $manifest, false);
        WP_CLI::log("Seeded $key: $label");
    };
    $created = fn($g) => "Group subscription added or exists for {$g['name']}.";
    $patched = fn($g, $end, $event) => "Group subscription end date updated to $end on $event for Group: {$g['name']}.";

    // P1 - Table A, 28 of 35: history rows, oldest revived by PATCH, site row from the purchase.
    [$p, $u] = gs_seed_person('p1');
    $hist = gs_seed_history($p, $g1['group'], $g1['role'], 2016, 2025);
    gs_seed_patch_end($hist[0], '2026-12-31');
    $start = gs_seed_ts('2026-07-15');
    gs_seed_row($p, $g1['group'], $g1['role'], gs_seed_site_iso($start), gs_seed_site_iso(gs_seed_ts('2027-01-01')));
    $sub = gs_seed_subscription($u, [$g1], [
        'label' => 'P1 revived row + site row', 'start' => '2026-07-15', 'end' => '2026-12-31', 'next' => null,
        'status' => 'active', 'assigned' => true, 'assigned_next' => '2027-01-01',
        'notes' => [$created($g1), $patched($g1, '2026-12-31', 'Next Payment Date changed')],
    ]);
    $add('p1', 'Revived 2016 row + site row (type A + C)', $p, $u, [$g1['group']], [$sub],
        'Date change, cancel, reactivation: LINKED_BY_START_DATE to the 2026-07-15 site row; only that row changes. The revived 2016 row keeps 2026-12-31.');

    // P2 - Table A #3/#4/#27/#32: activation matched the current-year row, the PATCH revived the oldest.
    [$p, $u] = gs_seed_person('p2');
    $hist = gs_seed_history($p, $g1['group'], $g1['role'], 2018, 2025);
    gs_seed_row($p, $g1['group'], $g1['role'], '2019-06-01', '2019-12-31'); // type E overlap from the import
    gs_seed_row($p, $g1['group'], $g1['role'], '2026-01-01', '2026-12-31'); // current-year import row
    gs_seed_patch_end($hist[0], '2026-12-31');
    $sub = gs_seed_subscription($u, [$g1], [
        'label' => 'P2 matched current-year row, no start-date row', 'start' => '2026-06-29', 'end' => '2026-12-31', 'next' => null,
        'status' => 'active', 'assigned' => true, 'assigned_next' => '2027-01-01',
        'notes' => [$created($g1), $patched($g1, '2026-12-31', 'Next Payment Date changed')],
    ]);
    $add('p2', 'Current-year row + revived 2018 row, no row with the sub start date', $p, $u, [$g1['group']], [$sub],
        'Date change or cancel: note "No group membership is linked", no row changes. Reactivation or renewal: links the FIRST ACTIVE row in API order - record which (revived 2018 or current-year 2026). New purchase: links the same row, no create.');

    // P3 - c804194a: >20 rows so the old duplicate check missed the current-year row on page 2.
    [$p, $u] = gs_seed_person('p3');
    $start = gs_seed_ts('2026-10-06');
    foreach ($three as $g) {
        $hist = gs_seed_history($p, $g['group'], $g['role'], 2005, 2025);
        gs_seed_row($p, $g['group'], $g['role'], '2026-01-01', '2026-12-31');
        gs_seed_patch_end($hist[0], '2027-12-31');
        gs_seed_row($p, $g['group'], $g['role'], gs_seed_site_iso($start), gs_seed_site_iso(gs_seed_ts('2027-01-01')));
    }
    $notes = [];
    foreach ($three as $g) {
        $notes[] = $created($g);
        $notes[] = $patched($g, '2027-12-31', 'Next Payment Date changed');
    }
    $sub = gs_seed_subscription($u, $three, [
        'label' => 'P3 three rows per group (old, current-year, site)', 'start' => '2026-10-06', 'end' => '2027-12-31', 'next' => null,
        'status' => 'active', 'assigned' => true, 'assigned_next' => '2027-01-01', 'notes' => $notes,
    ]);
    $add('p3', 'Three active rows per group, current-year row past page 1', $p, $u, array_column($three, 'group'), [$sub],
        'Date change, cancel, reactivation: each group links its 2026-10-06 site row by start date; only those change. Revived and current-year rows unchanged. No new rows.');

    // P4 - e94af438: a never-active cancel ended old rows; a paid sub owns site rows with the SAME start date.
    [$p, $u] = gs_seed_person('p4');
    $start = gs_seed_ts('2026-08-10');
    $notes_cancel = [];
    $notes_paid = [];
    foreach ($three as $g) {
        $hist = gs_seed_history($p, $g['group'], $g['role'], 2017, 2025);
        gs_seed_patch_end($hist[0], '2026-08-10');
        gs_seed_row($p, $g['group'], $g['role'], gs_seed_site_iso($start), gs_seed_site_iso(gs_seed_ts('2027-01-01')));
        $notes_cancel[] = $patched($g, '2026-08-10', 'Subscription Cancelled');
        $notes_paid[] = $created($g);
    }
    $cancelled = gs_seed_subscription($u, $three, [
        'label' => 'P4a never-active, cancelled (10369)', 'start' => '2026-08-10', 'end' => null, 'next' => null,
        'status' => 'cancelled', 'assigned' => false, 'notes' => $notes_cancel,
    ]);
    $paid = gs_seed_subscription($u, $three, [
        'label' => 'P4b paid (10374)', 'start' => '2026-08-10', 'end' => '2026-12-31', 'next' => null,
        'status' => 'active', 'assigned' => true, 'assigned_next' => '2027-01-01', 'notes' => $notes_paid,
    ]);
    $pending = gs_seed_subscription($u, $three, [
        'label' => 'P4c never-active, PENDING - cancel this one', 'start' => '2026-08-10', 'end' => null, 'next' => '2027-08-10',
        'status' => 'pending', 'assigned' => false,
    ]);
    $add('p4', 'Never-active cancel; paid sub with the same start date', $p, $u, array_column($three, 'group'), [$cancelled, $paid, $pending],
        'Cancel P4c (pending, never active). It shares the 2026-08-10 start date with P4b, so start-date linking can pick P4b\'s rows and end the PAID membership. Expected safe result: no row changes. Old code: PATCHes data[0] (an old row).');

    // P5 - control: one row per person, group and role. The fix must behave exactly like today.
    [$p, $u] = gs_seed_person('p5');
    $start = gs_seed_ts('2026-09-01');
    gs_seed_row($p, $g1['group'], $g1['role'], gs_seed_site_iso($start), gs_seed_site_iso(gs_seed_ts('2027-01-01')));
    $sub = gs_seed_subscription($u, [$g1], [
        'label' => 'P5 clean control', 'start' => '2026-09-01', 'end' => null, 'next' => '2027-01-01',
        'status' => 'active', 'assigned' => true, 'assigned_next' => '2027-01-01', 'notes' => [$created($g1)],
    ]);
    $add('p5', 'Clean control, one row', $p, $u, [$g1['group']], [$sub],
        'Same as old code: the single row changes on date change, renewal and cancel.');

    WP_CLI::success('Seeded. Snapshot now: wp eval-file "$SEED" report > base.json');
}

function gs_seed_snapshot()
{
    $manifest = get_option(GS_SEED_OPTION);
    if (!$manifest) {
        WP_CLI::error('Nothing seeded.');
    }
    $out = [];
    foreach ($manifest['personas'] as $key => $persona) {
        $rows = [];
        foreach ($persona['groups'] as $group) {
            foreach (gs_seed_rows($group, $persona['person']) as $i => $r) {
                $a = $r['attributes'];
                $rows[$a['uuid'] ?? $r['id']] = [
                    'group' => $group, 'api_order' => $i, 'type' => $a['type'] ?? '',
                    'start' => substr((string) ($a['start_date'] ?? ''), 0, 10),
                    'end' => substr((string) ($a['end_date'] ?? ''), 0, 10),
                    'active' => $a['active'] ?? null,
                ];
            }
        }
        $subs = [];
        foreach ($persona['subs'] as $id) {
            $sub = wcs_get_subscription($id);
            if (!$sub) {
                continue;
            }
            $meta = [];
            foreach (get_post_meta($id) as $k => $v) {
                if (str_starts_with($k, '_group_')) {
                    $meta[$k] = count($v) === 1 ? $v[0] : $v;
                }
            }
            $subs[$id] = [
                'status' => $sub->get_status(),
                'start' => $sub->get_date('start', 'site'),
                'next_payment' => $sub->get_date('next_payment', 'site'),
                'end' => $sub->get_date('end', 'site'),
                'meta' => $meta,
            ];
        }
        $out[$key] = ['label' => $persona['label'], 'expect' => $persona['expect'], 'person' => $persona['person'], 'rows' => $rows, 'subs' => $subs];
    }

    return $out;
}

function gs_seed_cmd_diff($file)
{
    $base = json_decode((string) @file_get_contents($file), true);
    if (!is_array($base)) {
        WP_CLI::error("Cannot read baseline $file.");
    }
    foreach (gs_seed_snapshot() as $key => $now) {
        $was = $base[$key] ?? ['rows' => [], 'subs' => []];
        $lines = [];
        foreach ($now['rows'] as $uuid => $r) {
            $o = $was['rows'][$uuid] ?? null;
            if (!$o) {
                $lines[] = "  NEW ROW  $uuid {$r['start']} -> {$r['end']} active=" . var_export($r['active'], true);
            } elseif ($o['end'] !== $r['end'] || $o['start'] !== $r['start'] || $o['active'] !== $r['active']) {
                $lines[] = "  CHANGED  $uuid start {$o['start']} end {$o['end']} -> {$r['end']} active " . var_export($o['active'], true) . ' -> ' . var_export($r['active'], true);
            }
        }
        foreach (array_diff_key($was['rows'], $now['rows']) as $uuid => $r) {
            $lines[] = "  REMOVED  $uuid";
        }
        foreach ($now['subs'] as $id => $s) {
            $o = $was['subs'][$id] ?? [];
            foreach ($s['meta'] as $k => $v) {
                if (($o['meta'][$k] ?? null) !== $v) {
                    $lines[] = "  SUB $id  $k = " . (is_array($v) ? implode(',', $v) : $v);
                }
            }
            if (($o['status'] ?? null) !== $s['status']) {
                $lines[] = "  SUB $id  status " . ($o['status'] ?? '-') . " -> {$s['status']}";
            }
        }
        WP_CLI::log("$key  {$now['label']}");
        WP_CLI::log('  expect: ' . $now['expect']);
        WP_CLI::log($lines ? implode("\n", $lines) : '  no changes');
        WP_CLI::log('');
    }
}

function gs_seed_cmd_reset()
{
    $manifest = get_option(GS_SEED_OPTION);
    if (!$manifest) {
        WP_CLI::success('Nothing to reset.');

        return;
    }
    gs_seed_mute_integration();
    $client = wicket_api_client();
    foreach ($manifest['personas'] as $key => $persona) {
        // Delete every row of the persona in its groups, including rows the tests created.
        foreach ($persona['groups'] as $group) {
            foreach (gs_seed_rows($group, $persona['person']) as $r) {
                $row_uuid = $r['attributes']['uuid'] ?? $r['id'];
                try {
                    $client->delete('group_members/' . $row_uuid);
                } catch (Exception $e) {
                    WP_CLI::warning("Row delete failed ($row_uuid): " . $e->getMessage());
                }
            }
        }
        // Delete the persona's subscriptions and orders, including ones the tests created.
        foreach (wcs_get_users_subscriptions($persona['user_id']) as $sub) {
            foreach ($sub->get_related_orders('all') as $order) {
                $order->delete(true);
            }
            $sub->delete(true);
        }
        WP_CLI::log("Reset $key");
    }
    delete_option(GS_SEED_OPTION);
    WP_CLI::success('Reset. MDP people and WP users are kept and reused by the next seed.');
}

switch ($gs_cmd) {
    case 'seed':
        gs_seed_cmd_seed(array_slice($args, 1));
        break;
    case 'report':
        WP_CLI::line(wp_json_encode(gs_seed_snapshot(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        break;
    case 'diff':
        gs_seed_cmd_diff($args[1] ?? '');
        break;
    case 'reset':
        gs_seed_cmd_reset();
        break;
    default:
        WP_CLI::error('Usage: wp eval-file "$(wp plugin path wicket-wp-base-plugin --dir)/tools/group-subscription-damage-seed.php" seed|report|diff <file>|reset');
}
