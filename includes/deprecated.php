<?php

// No direct access
defined('ABSPATH') || exit;

/**
 * Deprecated functions for backward compatibility.
 * These are wrappers around the Rest class methods.
 */

require_once WICKET_PLUGIN_DIR . 'src/Rest.php';

use WicketWP\Rest;

/**
 * Register REST API routes for Wicket Base plugin.
 *
 * @deprecated Use Rest::register_rest_routes instead.
 * @since 1.0.0
 * @return void
 */
function wicket_base_register_rest_routes()
{
    _deprecated_function(__FUNCTION__, '1.0.0', 'Rest::register_routes');
    $rest = new Rest();
    $rest->register_routes();
}

/**
 * Calls the Wicket helper functions to search for a given organization name
 * and provide a list of results.
 *
 * @deprecated Use Rest::search_orgs instead.
 * @param WP_REST_Request $request that contains JSON params, notably a 'searchTerm' and an optional 'lang'.
 * @return JSON success:false or success:true, along with any related information or notices.
 */
function wicket_internal_endpoint_search_orgs($request)
{
    _deprecated_function(__FUNCTION__, '1.0.0', 'Rest::search_orgs');
    $rest = new Rest();

    return $rest->search_orgs($request);
}

/**
 * Calls the Wicket helper functions to search for a given group name
 * and provide a list of results.
 *
 * @deprecated Use Rest::search_groups instead.
 * @param WP_REST_Request $request that contains JSON params, notably a 'searchTerm' and a 'lang'.
 * @return JSON success:false or success:true, along with any related information or notices.
 */
function wicket_internal_endpoint_search_groups($request)
{
    _deprecated_function(__FUNCTION__, '1.0.0', 'Rest::search_groups');
    $rest = new Rest();

    return $rest->search_groups($request);
}

/**
 * Calls the Wicket helper functions to terminate a relationship.
 *
 * @deprecated Use Rest::terminate_relationship instead.
 * @param WP_REST_Request $request that contains JSON params, notably a 'connectionId'.
 * @return JSON success:false or success:true, along with any related information or notices.
 */
function wicket_internal_endpoint_terminate_relationship($request)
{
    _deprecated_function(__FUNCTION__, '1.0.0', 'Rest::terminate_relationship');
    $rest = new Rest();

    return $rest->terminate_relationship($request);
}

/**
 * Calls the Wicket helper functions to create a relationship.
 *
 * @deprecated Use Rest::create_or_update_relationship instead.
 * @param WP_REST_Request $request that contains JSON params.
 * @return JSON success:false or success:true, along with any related information or notices.
 */
function wicket_internal_endpoint_create_or_update_relationship($request)
{
    _deprecated_function(__FUNCTION__, '1.0.0', 'Rest::create_or_update_relationship');
    $rest = new Rest();

    return $rest->create_or_update_relationship($request);
}

/**
 * Calls the Wicket helper functions to create an organization.
 *
 * @deprecated Use Rest::create_org instead.
 * @param WP_REST_Request $request that contains JSON params.
 * @return JSON success:false or success:true, along with any related information or notices.
 */
function wicket_internal_endpoint_create_org($request)
{
    _deprecated_function(__FUNCTION__, '1.0.0', 'Rest::create_org');
    $rest = new Rest();

    return $rest->create_org($request);
}

/**
 * Sets a temporary piece of user meta so that the user will get Roster Management access for the given org UUID on the next order_complete containing a membership product.
 *
 * @deprecated Use Rest::flag_for_rm_access instead.
 * @param WP_REST_Request $request that contains JSON params.
 * @return JSON success:false or success:true, along with any related information or notices.
 */
function wicket_internal_endpoint_flag_for_rm_access($request)
{
    _deprecated_function(__FUNCTION__, '1.0.0', 'Rest::flag_for_rm_access');
    $rest = new Rest();

    return $rest->flag_for_rm_access($request);
}

/**
 * Sets a temporary piece of user meta so that the user will get Org Editor access for the given org UUID on the next order_complete containing a membership product.
 *
 * @deprecated Use Rest::flag_for_org_editor_access instead.
 * @param WP_REST_Request $request that contains JSON params.
 * @return JSON success:false or success:true, along with any related information or notices.
 */
function wicket_internal_endpoint_flag_for_org_editor_access($request)
{
    _deprecated_function(__FUNCTION__, '1.0.0', 'Rest::flag_for_org_editor_access');
    $rest = new Rest();

    return $rest->flag_for_org_editor_access($request);
}

/**
 * @deprecated Use Rest::grant_org_editor instead.
 */
function wicket_internal_endpoint_grant_org_editor($request)
{
    _deprecated_function(__FUNCTION__, '1.0.0', 'Rest::grant_org_editor');
    $rest = new Rest();

    return $rest->grant_org_editor($request);
}

/**
 * Calls the Wicket helper functions to assign an organization a parent relationship.
 *
 * @deprecated Use Rest::organization_parent instead.
 * @param WP_REST_Request $request that contains JSON params.
 * @return JSON success:false or success:true, along with any related information or notices.
 */
function wicket_internal_endpoint_organization_parent($request)
{
    _deprecated_function(__FUNCTION__, '1.0.0', 'Rest::organization_parent');
    $rest = new Rest();

    return $rest->organization_parent($request);
}

/**
 * @deprecated Use Rest::component_do_action instead.
 */
function wicket_internal_endpoint_component_do_action($request)
{
    _deprecated_function(__FUNCTION__, '1.0.0', 'Rest::component_do_action');
    $rest = new Rest();

    return $rest->component_do_action($request);
}
