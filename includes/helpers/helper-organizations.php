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

        $org_memberships = wicket_get_org_memberships($org_id);
        $seat_summary = wicket_get_active_membership_seat_summary($org_memberships);

        $base_results[$index]['active_membership_seat_summary'] = $seat_summary;
        $base_results[$index]['active_membership'] = $seat_summary['has_active_membership'] ?? ($result['active_membership'] ?? false);
    }

    return $base_results;
}
