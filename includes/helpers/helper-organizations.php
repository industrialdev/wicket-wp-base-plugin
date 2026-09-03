<?php

// No direct access
defined('ABSPATH') || exit;

/**
 * Get organization addresses.
 *
 * @param string $org_id Organization ID.
 * @return mixed|false Organization addresses or false on failure.
 */
function wicket_get_organization_addresses($org_id)
{
    $client = wicket_api_client();

    try {
        $org = $client->get("organizations/$org_id/addresses");

        return $org;
    } catch (Exception $e) {
        $errors = json_decode($e->getResponse()->getBody())->errors;
    }

    return false;
}

/**
 * Search organizations and include active membership + seat summary details.
 *
 * Mirrors wicket_search_organizations() while augmenting each result with an
 * `active_membership_seat_summary` array derived from helper-memberships.php.
 *
 * @param string       $search_term Search term, e.g. "My company".
 * @param string       $search_by   Currently unused; kept for parity with the original helper.
 * @param string|array $org_type    Org type slug(s) to filter by.
 * @param bool         $autocomplete Whether to hit the autocomplete endpoint.
 * @param string       $lang         Language code (defaults to 'en').
 *
 * @return bool|array False on failure, or array of results with seat summaries.
 */
function wicket_search_organizations_with_membership_details($search_term, $search_by = 'org_name', $org_type = null, $autocomplete = false, $lang = 'en')
{
    // Leverage the legacy helper for backwards compatibility.
    $base_results = wicket_search_organizations($search_term, $search_by, $org_type, $autocomplete, $lang);

    if ($base_results === false) {
        return false;
    }

    // Autocomplete responses are simple indexed arrays; enhance each record by org id when available.
    foreach ($base_results as $index => $result) {
        $org_id = $result['id'] ?? null;
        if (empty($org_id)) {
            $base_results[$index]['active_membership_seat_summary'] = null;
            continue;
        }

        $org_memberships = $result['org_memberships'] ?? null;
        if (!is_array($org_memberships)) {
            $org_memberships = wicket_get_org_memberships($org_id);
        }
        $seat_summary = wicket_get_active_membership_seat_summary($org_memberships);

        $base_results[$index]['active_membership_seat_summary'] = $seat_summary;
        $base_results[$index]['active_membership'] = $seat_summary['has_active_membership'] ?? ($result['active_membership'] ?? false);
    }

    return $base_results;
}

/**
 * Reduce a list of organization UUIDs to the ones that still exist in the MDP.
 *
 * The MDP search index can hold on to documents for organizations that have since been
 * deleted or merged away, so a search hit is not proof that the record still exists. Those
 * stale hits are selectable in the front end but every follow-up call against them 404s.
 *
 * Existence is confirmed in bulk (one request per chunk of UUIDs) rather than fetching each
 * organization individually. Every failure path fails open and keeps the UUIDs it could not
 * verify, so a hiccup here degrades to the previous behaviour instead of emptying search
 * results.
 *
 * @param array $org_ids Organization UUIDs to verify.
 *
 * @return array The subset of $org_ids that still resolve to an organization, original order kept.
 */
function wicket_filter_existing_organization_ids(array $org_ids): array
{
    $org_ids = array_values(array_unique(array_filter($org_ids)));

    if (empty($org_ids)) {
        return [];
    }

    $client = wicket_api_client();

    if (!$client) {
        // Fail open: cannot verify, keep all hits.
        return $org_ids;
    }

    $existing = [];

    foreach (array_chunk($org_ids, 50) as $chunk) {
        $query = http_build_query([
            'fields' => ['organizations' => 'legal_name'],
            'page'   => ['size' => count($chunk)],
            'filter' => ['uuid_in' => $chunk],
        ]);
        // Ruby doesn't like the numeric keys PHP adds to array params, e.g. filter[uuid_in][0].
        $query = preg_replace('/\%5B\d+\%5D/', '%5B%5D', $query);

        try {
            $response = $client->get('organizations?' . $query);
        } catch (Exception $e) {
            Wicket()->log()->warning(
                'wicket_filter_existing_organization_ids lookup failed, keeping unverified results: ' . $e->getMessage(),
                ['source' => 'wicket-base']
            );

            // Fail open for this chunk.
            $existing = array_merge($existing, $chunk);
            continue;
        }

        foreach ($response['data'] ?? [] as $organization) {
            if (!empty($organization['id'])) {
                $existing[$organization['id']] = $organization['id'];
            }
        }
    }

    $existing = array_flip(array_values($existing));

    return array_values(array_filter($org_ids, function ($org_id) use ($existing) {
        return isset($existing[$org_id]);
    }));
}
