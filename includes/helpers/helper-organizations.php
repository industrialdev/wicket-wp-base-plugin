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