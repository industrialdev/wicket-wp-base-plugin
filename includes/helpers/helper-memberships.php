<?php

// No direct access
defined('ABSPATH') || exit;

/**
 * WPML-aware helper to detect if a product belongs to the 'membership' product category.
 * Accepts product ID or WC_Product instance. Falls back to checking translations when WPML is present.
 *
 * @param int|WC_Product $product
 * @return bool
 */
function wicket_is_membership_product($product)
{
    // Normalize to product ID
    $product_id = is_object($product) && method_exists($product, 'get_id') ? (int) $product->get_id() : (int) $product;

    if (!$product_id) {
        return false;
    }

    // If WPML is active, run WPML-aware checks using the 'wpml_object_id' filter.
    // Otherwise just return has_term() directly.
    // Use the multilanguage boolean helper to determine if any multilanguage provider is active.
    $wpml_active = wicket_is_multilang_active();
    if (!$wpml_active) {
        return function_exists('has_term') ? has_term('membership', 'product_cat', $product_id) : false;
    }

    // WPML is active: try checking the translated product (or term) fallback using the filter only.
    // Try to get the product ID for the site's default language and check there
    $default_lang = apply_filters('wpml_default_language', null);
    if ($default_lang) {
        // Get translation of product into default language using the wpml_object_id filter
        $translated_product_id = apply_filters('wpml_object_id', $product_id, 'product', false, $default_lang);
        if ($translated_product_id && $translated_product_id !== $product_id) {
            if (has_term('membership', 'product_cat', $translated_product_id)) {
                return true;
            }
        }
    }

    // Additionally try checking the term translation: lookup the 'membership' term ID in current taxonomy
    $term = get_term_by('slug', 'membership', 'product_cat');
    if ($term && !is_wp_error($term)) {
        $term_id = $term->term_id;
        // Map term id via the wpml_object_id filter in the current language
        $current_lang = apply_filters('wpml_current_language', null);
        $translated_term_id = apply_filters('wpml_object_id', $term_id, 'product_cat', false, $current_lang);

        if ($translated_term_id) {
            $terms = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
            if (!empty($terms) && in_array($translated_term_id, $terms, true)) {
                return true;
            }
        }
    }

    // As a last-resort, check the product's categories for translated slugs that might equal 'membership' in other languages
    if (function_exists('wp_get_post_terms')) {
        $product_terms = wp_get_post_terms($product_id, 'product_cat');
        if (!empty($product_terms) && is_array($product_terms)) {
            foreach ($product_terms as $t) {
                if (!empty($t->slug) && strtolower($t->slug) === 'membership') {
                    return true;
                }
            }
        }
    }

    return false;
}

/**
 * Check whether a person (identified by email) holds an active assignment in a given org membership.
 *
 * Queries the Wicket `/person_memberships/query` endpoint which accepts an email filter.
 * Returns true when at least one active assignment is found for the given membership UUID.
 *
 * @param string $membership_uuid Org-membership UUID to check against.
 * @param string $email           Person email to look up.
 *
 * @return bool True if an active membership assignment exists, false otherwise.
 */
function wicket_person_in_membership(string $membership_uuid, string $email): bool
{
    if ('' === $membership_uuid || '' === $email) {
        return false;
    }

    if (!function_exists('wicket_api_client')) {
        return false;
    }

    try {
        $client = wicket_api_client();
        $response = $client->post('/person_memberships/query', [
            'json' => [
                'filter' => [
                    'organization_membership_uuid_in' => [$membership_uuid],
                    'person_emails_address_eq'        => $email,
                    'active_at'                       => 'now',
                ],
            ],
        ]);

        if (empty($response['data']) || !is_array($response['data'])) {
            return false;
        }

        foreach ($response['data'] as $item) {
            if ((bool) ($item['attributes']['active'] ?? false)) {
                return true;
            }
        }
    } catch (Throwable $e) {
        return false;
    }

    return false;
}

/**
 * Check whether a person (identified by UUID) is an active member of a given org membership.
 *
 * Uses the `/person_memberships/query` endpoint with `active_at=now` so the API
 * evaluates activity from membership dates rather than a potentially stale `active`
 * boolean on the list endpoint.
 *
 * @param string $person_uuid     Person UUID.
 * @param string $membership_uuid Org-membership UUID to check against.
 *
 * @return bool True if an active membership assignment exists for the person.
 */
function wicket_person_has_membership(string $person_uuid, string $membership_uuid): bool
{
    if ('' === $person_uuid || '' === $membership_uuid) {
        return false;
    }

    if (!function_exists('wicket_api_client')) {
        return false;
    }

    try {
        $client = wicket_api_client();
        $response = $client->post('/person_memberships/query', [
            'json' => [
                'filter' => [
                    'organization_membership_uuid_in' => [$membership_uuid],
                    'person_uuid_in'                  => [$person_uuid],
                    'active_at'                       => 'now',
                ],
                'page' => [
                    'number' => 1,
                    'size'   => 1,
                ],
            ],
        ]);

        return !empty($response['data']);
    } catch (Throwable $e) {
        return false;
    }

    return false;
}

/**
 * Build a normalized summary of the active membership seat allocation for an org.
 *
 * @param array $org_memberships Result of wicket_get_org_memberships().
 * @return array{
 *     has_active_membership:bool,
 *     assigned:?int,
 *     max:?int,
 *     unlimited:bool,
 *     has_available_seats:?bool
 * }
 */
function wicket_get_active_membership_seat_summary(array $org_memberships)
{
    $summary = [
        'has_active_membership' => false,
        'assigned'              => null,
        'max'                   => null,
        'unlimited'             => false,
        'has_available_seats'   => null,
    ];

    $fallback_active_summary = null;

    foreach ($org_memberships as $membership) {
        $org_membership = $membership['membership'] ?? [];
        $org_attributes = $org_membership['attributes'] ?? [];
        $included = $membership['included'] ?? [];
        $included_attributes = $included['attributes'] ?? [];

        $active = $org_attributes['active'] ?? ($included_attributes['active'] ?? false);
        if (empty($active)) {
            continue;
        }

        $summary['has_active_membership'] = true;

        $meta = [];
        if (isset($included_attributes['meta']) && is_array($included_attributes['meta'])) {
            $meta = $included_attributes['meta'];
        } elseif (isset($org_attributes['meta']) && is_array($org_attributes['meta'])) {
            $meta = $org_attributes['meta'];
        } elseif (isset($included['meta']) && is_array($included['meta'])) {
            $meta = $included['meta'];
        } elseif (isset($org_membership['meta']) && is_array($org_membership['meta'])) {
            $meta = $org_membership['meta'];
        }

        $meta_unlimited = false;
        if (isset($meta['unlimited_assignments'])) {
            $meta_unlimited = (bool) $meta['unlimited_assignments'];
        } elseif (isset($meta['unlimited_seats'])) {
            $meta_unlimited = (bool) $meta['unlimited_seats'];
        }

        $assigned = null;
        if (isset($included_attributes['active_assignments_count'])) {
            $assigned = (int) $included_attributes['active_assignments_count'];
        } elseif (isset($org_attributes['active_assignments_count'])) {
            $assigned = (int) $org_attributes['active_assignments_count'];
        } elseif (isset($included_attributes['assignments_count'])) {
            $assigned = (int) $included_attributes['assignments_count'];
        } elseif (isset($org_attributes['assignments_count'])) {
            $assigned = (int) $org_attributes['assignments_count'];
        } elseif (isset($meta['active_assignments_count'])) {
            $assigned = (int) $meta['active_assignments_count'];
        } elseif (isset($meta['assignments_count'])) {
            $assigned = (int) $meta['assignments_count'];
        }

        $meta_org_seats = null;
        if (isset($meta['org_seats'])) {
            $meta_org_seats = (int) $meta['org_seats'];
        } elseif (isset($meta['membership_seats'])) {
            $meta_org_seats = (int) $meta['membership_seats'];
        }

        $max = null;
        if (isset($included_attributes['max_assignments'])) {
            $max = (int) $included_attributes['max_assignments'];
        } elseif (isset($org_attributes['max_assignments'])) {
            $max = (int) $org_attributes['max_assignments'];
        }

        $unlimited = !empty($included_attributes['unlimited_assignments']) || !empty($org_attributes['unlimited_assignments']) || $meta_unlimited;
        if ($meta_org_seats !== null && $meta_org_seats > 0) {
            $max = $meta_org_seats;
            $unlimited = false;
        }

        if ($max !== null && $max > 0) {
            $unlimited = false;
        }

        $candidate = [
            'has_active_membership' => true,
            'assigned'              => $assigned,
            'unlimited'             => $unlimited,
            'max'                   => $max,
            'has_available_seats'   => null,
        ];

        if ($unlimited) {
            $candidate['has_available_seats'] = true;
        } elseif (is_null($assigned) || is_null($max)) {
            $candidate['has_available_seats'] = null;
        } else {
            $candidate['has_available_seats'] = $assigned < $max;
        }

        if ($fallback_active_summary === null) {
            $fallback_active_summary = $candidate;
        }

        if ($candidate['max'] !== null || $meta_org_seats !== null) {
            return $candidate;
        }
    }

    return $fallback_active_summary ?? $summary;
}


/**
 * Get an interval resource by ID from the MDP API.
 *
 * @param string $id Interval ID.
 * @return object|false Interval object or false on failure.
 */
function wicket_get_interval($id)
{
    static $interval = null;
    if (is_null($interval)) {
        if ($id) {
            $client = wicket_api_client();
            try {
                $interval = $client->intervals->fetch($id);
            } catch (Exception $e) {
                $interval = false;
            }

            return $interval;
        }
    }

    return $interval;
}

/**
 * Assign a person to an organization membership seat.
 *
 * @param string $person_id The person UUID.
 * @param string $membership_id The membership tier UUID.
 * @param string $org_membership_id The organization membership UUID.
 * @param array $org_membership The organization membership resource data array.
 * @return true|array True on success, or error array on failure.
 */
function wicket_assign_person_to_org_membership($person_id, $membership_id, $org_membership_id, $org_membership)
{
    $client = wicket_api_client();
    // build payload to assign person to the membership on the org

    $payload = [
        'data' => [
            'type' => 'person_memberships',
            'attributes' => [
                'starts_at' => $org_membership['data']['attributes']['starts_at'],
                'ends_at' => $org_membership['data']['attributes']['ends_at'],
                'status' => 'Active',
            ],
            'relationships' => [
                'person' => [
                    'data' => [
                        'id' => $person_id,
                        'type' => 'people',
                    ],
                ],
                'membership' => [
                    'data' => [
                        'id' => $membership_id,
                        'type' => 'memberships',
                    ],
                ],
                'organization_membership' => [
                    'data' => [
                        'id' => $org_membership_id,
                        'type' => 'organization_memberships',
                    ],
                ],
            ],
        ],
    ];

    try {
        $client->post('person_memberships', ['json' => $payload]);

        return true;
    } catch (Exception $e) {
        // Surface the API error to callers instead of silently returning null.
        // DirectAssignmentStrategy::assignPersonToMembershipSeat() inspects the
        // returned value (empty() / isset($result['errors'])) to detect failure,
        // so a null return hid the real cause and forced a generic fallback
        // message. Return a JSON:API-shaped errors array so the actual detail
        // reaches the UI.
        $errors = [];
        $response = method_exists($e, 'getResponse') ? $e->getResponse() : null;
        if ($response) {
            $decoded = json_decode((string) $response->getBody(), true);
            if (is_array($decoded) && !empty($decoded['errors'])) {
                $errors = $decoded['errors'];
            }
        }
        if (empty($errors)) {
            $errors = [['detail' => $e->getMessage() !== '' ? $e->getMessage() : 'Failed to assign person to membership.']];
        }

        return ['errors' => $errors];
    }
}

/**
 * Unassign a person from an organization membership seat.
 *
 * @param string $person_membership_id The person membership UUID to delete.
 * @return bool True on success, false on error.
 */
function wicket_unassign_person_from_org_membership($person_membership_id)
{
    $client = wicket_api_client();
    try {
        $client->delete("person_memberships/$person_membership_id");

        return true;
    } catch (Exception $e) {
        $errors = json_decode($e->getResponse()->getBody())->errors;
    }

    return false;
}

/**
 * Send notification email to an existing user when assigned to an organization team membership.
 *
 * @param \WP_User $user The WordPress user object.
 * @param string $org_id Organization UUID.
 * @return void
 */
function send_person_to_team_assignment_email($user, $org_id)
{
    $org = wicket_get_organization($org_id);
    $lang = defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : 'en';
    $person = wicket_get_person_by_id($user->data->user_login);

    if ($org) {
        $organization_name = $org['data']['attributes']['legal_name_' . $lang];
    }

    $to = $person->primary_email_address;
    $first_name = $person->given_name;
    $last_name = $person->family_name;
    $subject = 'Welcome!';
    $body = "Hi $first_name, <br><br>
    You have been assigned a membership as part of $organization_name.
    <br>
    <br>
    Visit our site and login to complete your profile and explore your member benefits.
    <br>
    <br>
    Thank you,
    <br>
    <br>
    ";
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $headers[] = 'From: Wicket <no-reply@wicketcloud.com>';
    wp_mail($to, $subject, $body, $headers);
}

/**
 * Send notification email to a new user when assigned to an organization team membership.
 *
 * @param string $first_name Recipient given name.
 * @param string $last_name Recipient family name.
 * @param string $email Recipient email address.
 * @param string $org_id Organization UUID.
 * @return void
 */
function send_new_person_to_team_assignment_email($first_name, $last_name, $email, $org_id)
{
    $org = wicket_get_organization($org_id);
    $lang = defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : 'en';

    if ($org) {
        $organization_name = $org['data']['attributes']['legal_name_' . $lang];
    }

    $to = $email;
    $subject = 'Welcome!';
    $body = "Hi $first_name, <br><br>
    You have been assigned a membership as part of $organization_name.
    <br>
    <br>
    You will soon receive an Account Confirmation email with instructions on how to finalize your login account.
    Once you have confirmed your account, visit our site and login to complete your profile and explore your member benefits.
    <br>
    <br>
    Thank you,
    <br>
    <br>
    ";
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $headers[] = 'From: Wicket <no-reply@wicketcloud.com>';
    wp_mail($to, $subject, $body, $headers);
}

/**
 * Send notification email to tier contact address for a new membership pending approval.
 *
 * @param string $email Recipient email address.
 * @param string $membership_link Link to process the membership request.
 * @return void
 */
function send_approval_required_email($email, $membership_link)
{
    $lang = defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : 'en';
    $to = $email;
    $subject = 'Membership Pending Approval';
    $body = "You have a membership pending approval.
    <br>
  Please login with the following link to process the membership request.
  <br>
  $membership_link";

    $outgoing_email = apply_filters('wicket_approval_email_from', get_bloginfo('admin_email'));

    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $headers[] = 'From:' . $outgoing_email . '<' . $outgoing_email . '>';

    wp_mail($to, $subject, $body, $headers);
}

/**
 * Assign organization membership to person in the MDP.
 *
 * @param string $person_uuid Owner person UUID.
 * @param string $org_id Organization UUID.
 * @param string $membership_id Membership tier UUID.
 * @param string $starts_at Optional. ISO 8601 start date. Defaults to now.
 * @param string $ends_at Optional. ISO 8601 end date. Defaults to one year out.
 * @param int $max_seats Optional. Maximum seat assignments.
 * @param int $grace_period_days Optional. Grace period in days.
 * @param string $previous_membership_uuid Optional. Prior organization_membership UUID.
 * @param bool $grant_owner_assignment Optional. Whether to grant owner assignment.
 * @param bool $copy_previous_assignments Optional. Whether to copy assignments from prior membership.
 * @return array|\WP_Error MDP API response array or WP_Error on failure.
 */
function wicket_assign_organization_membership(
    $person_uuid,
    $org_id,
    $membership_id,
    $starts_at = '',
    $ends_at = '',
    $max_seats = 0,
    $grace_period_days = 0,
    $previous_membership_uuid = '',
    $grant_owner_assignment = false,
    $copy_previous_assignments = true
) {
    $override = apply_filters(
        'wicket_pre_assign_organization_membership',
        null,
        $person_uuid,
        $org_id,
        $membership_id,
        $starts_at,
        $ends_at,
        $max_seats,
        $grace_period_days,
        $previous_membership_uuid,
        $grant_owner_assignment,
        $copy_previous_assignments
    );

    if ($override !== null) {
        return $override;
    }

    $client = wicket_api_client();

    if (empty($starts_at)) {
        $starts_at = wicket_time_format_iso8601_utc(wicket_time_get_utc_datetime());
    }
    if (empty($ends_at)) {
        $ends_at = wicket_time_format_iso8601_utc(wicket_time_get_utc_datetime('+1 year'));
    }

    // build membership payload
    $payload = [
        'data' => [
            'type' => 'organization_memberships',
            'attributes' => [
                'starts_at' => $starts_at,
                'ends_at' => $ends_at,
                'max_assignments' => $max_seats,
                'grace_period_days' => $grace_period_days,
            ],
            'relationships' => [
                'owner' => [
                    'data' => [
                        'id' => $person_uuid,
                        'type' => 'people',
                    ],
                ],
                'membership' => [
                    'data' => [
                        'id' => $membership_id,
                        'type' => 'memberships',
                    ],
                ],
                'organization' => [
                    'data' => [
                        'id' => $org_id,
                        'type' => 'organizations',
                    ],
                ],
            ],
        ],
    ];

    if (!empty($grant_owner_assignment)) {
        $payload['data']['attributes']['grant_owner_assignment'] = true;
    }

    if (!empty($previous_membership_uuid)) {
        $payload['data']['attributes']['copy_previous_assignments'] = (bool) $copy_previous_assignments;
        $payload['data']['relationships']['previous_membership_entry']['data'] = [
            'type' => 'organization_memberships',
            'id' => $previous_membership_uuid,
        ];
    }

    try {
        $response = $client->post('organization_memberships', ['json' => $payload]);
    } catch (Exception $e) {
        $response = new WP_Error('wicket_api_error', $e->getMessage());

        // Flag seat-count overflow (max_assignments) so callers can retry without
        // carrying assignments over. Carried only in error_data under the existing
        // code; never a new error code (the call site reads 'wicket_api_error').
        // ConnectException (network) has no response; guard before reading the body.
        $overflow = false;
        $log_context = ['source' => 'wicket_assign_organization_membership'];
        if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->getResponse()) {
            $log_context['status'] = $e->getResponse()->getStatusCode();
            $body = json_decode((string) $e->getResponse()->getBody(), true);
            if (is_array($body) && !empty($body['errors'])) {
                foreach ($body['errors'] as $err) {
                    if (($err['meta']['field'] ?? '') === 'max_assignments') {
                        $overflow = true;
                        break;
                    }
                }
                // Defensive: record the fields the MDP actually reported so QA can
                // see WHY a 422 did not classify as overflow (e.g. a different
                // validation error bundled into the same response).
                $log_context['mdp_error_fields'] = array_values(array_filter(array_map(function ($err) {
                    return $err['meta']['field'] ?? null;
                }, $body['errors'])));
            } else {
                // Body present but malformed / no errors[]: the MDP returned a
                // non-JSON:API error shape. Log so QA catches protocol drift.
                $log_context['body_shape'] = is_array($body) ? 'no_errors_key' : 'json_decode_failed';
            }
        } else {
            // Not a RequestException: network failure, connect timeout, etc.
            $log_context['type'] = 'non_request_exception';
            $log_context['exception_class'] = get_class($e);
        }
        if ($overflow) {
            $response->add_data(['overflow' => true], 'wicket_api_error');
            $log_context['overflow'] = true;
        }
        // Defensive log on every failure path: surfaces overflow detection,
        // the actual MDP error fields, and non-HTTP exceptions during QA.
        Wicket()->log()->error('assign_organization_membership failed: ' . $e->getMessage(), $log_context);
    }

    return $response;
}

/**
 * Change the owner of an organization membership.
 *
 * @param string $org_membership_uuid Organization membership UUID.
 * @param string $person_uuid New owner person UUID.
 * @return array|\WP_Error API response array or WP_Error on failure.
 */
function change_organization_membership_owner($org_membership_uuid, $person_uuid)
{
    $client = wicket_api_client();

    $payload = [
        'data' => [
            'type' => 'organization_memberships',
            'id' => $org_membership_uuid,
            'relationships' => [
                'owner' => [
                    'data' => [
                        'type' => 'people',
                        'id' => $person_uuid,
                    ],
                ],
            ],
        ],
    ];

    try {
        $response = $client->patch("/organization_memberships/$org_membership_uuid", ['json' => $payload]);
    } catch (Exception $e) {
        $response = new WP_Error('wicket_api_error', $e->getMessage());
    }

    return $response;
}

/**
 * Check if a matching membership exists for a person with optional date filtering.
 *
 * @param string $person_uuid MDP person UUID.
 * @param string $membership_uuid MDP membership tier UUID.
 * @param string $starts_at Optional. Starts at date filter.
 * @param string $ends_at Optional. Ends at date filter.
 * @return string|null|\WP_Error Membership record ID if found, null otherwise, or WP_Error on failure.
 */
function wicket_get_person_membership_exists($person_uuid, $membership_uuid, $starts_at = '', $ends_at = '')
{
    $override = apply_filters('wicket_pre_get_person_membership_exists', null, $person_uuid, $membership_uuid, $starts_at, $ends_at);

    if ($override !== null) {
        return $override;
    }

    $client = wicket_api_client();
    try {
        $response = $client->get("people/$person_uuid/membership_entries?include=membership&filter[starts_at_eq]=$starts_at&filter[ends_at_eq]=$ends_at&page[size]=2000");
        foreach ($response['data'] as $record) {
            if ($record['relationships']['membership']['data']['id'] == $membership_uuid) {
                return $record['id'];
            }
        }
    } catch (Exception $e) {
        $response = new WP_Error('wicket_api_error', $e->getMessage());
    }
}

/**
 * Assign an individual membership to a person in the MDP.
 *
 * @param  string      $person_uuid                MDP person UUID.
 * @param  string      $membership_uuid             MDP membership tier UUID.
 * @param  string      $starts_at                   ISO 8601 start date; defaults to now.
 * @param  string      $ends_at                     ISO 8601 end date; defaults to one year out.
 * @param  int         $grace_period_days           Grace period in days; always sent.
 * @param  string      $previous_membership_uuid    Prior person_membership UUID to link, if any.
 * @param  bool|null   $is_autorenew                Whether this membership will auto-renew.
 *                     `null` means "not provided" and omits the field entirely, since `false`
 *                     is itself a real, meaningful value for this field (see
 *                     `wicket_update_individual_membership_dates()` for the same pattern). Typed
 *                     `?bool` so PHP rejects truthy-but-wrong values (e.g. the string `'no'`) at
 *                     the call boundary instead of forwarding them to the MDP as-is.
 * @return array|WP_Error  The MDP API response, or a WP_Error on failure.
 */
function wicket_assign_individual_membership(
    $person_uuid,
    $membership_uuid,
    $starts_at = '',
    $ends_at = '',
    $grace_period_days = 0,
    $previous_membership_uuid = '',
    ?bool $is_autorenew = null
) {
    $override = apply_filters('wicket_pre_assign_individual_membership', null, $person_uuid, $membership_uuid, $starts_at, $ends_at, $grace_period_days, $previous_membership_uuid, $is_autorenew);

    if ($override !== null) {
        return $override;
    }

    $client = wicket_api_client();

    if (empty($starts_at)) {
        $starts_at = wicket_time_format_iso8601_utc(wicket_time_get_utc_datetime());
    }
    if (empty($ends_at)) {
        $ends_at = wicket_time_format_iso8601_utc(wicket_time_get_utc_datetime('+1 year'));
    }

    // build membership payload
    $payload = [
        'data' => [
            'type' => 'person_memberships',
            'attributes' => [
                'starts_at' => $starts_at,
                'ends_at' => $ends_at,
                'grace_period_days' => $grace_period_days,
            ],
            'relationships' => [
                'person' => [
                    'data' => [
                        'id' => $person_uuid,
                        'type' => 'people',
                    ],
                ],
                'membership' => [
                    'data' => [
                        'id' => $membership_uuid,
                        'type' => 'memberships',
                    ],
                ],
            ],
        ],
    ];

    if (!empty($previous_membership_uuid)) {
        $payload['data']['relationships']['previous_membership_entry']['data'] = [
            'type' => 'person_memberships',
            'id' => $previous_membership_uuid,
        ];
    }

    if ($is_autorenew !== null) {
        $payload['data']['attributes']['is_auto_renew'] = $is_autorenew;
    }

    try {
        $response = $client->post('person_memberships', ['json' => $payload]);
    } catch (Exception $e) {
        $response = new WP_Error('wicket_api_error', $e->getMessage());
    }

    return $response;
}

/**
 * Update an individual membership's dates in the MDP.
 *
 * @param  string      $membership_uuid    MDP person_membership UUID to update.
 * @param  string      $starts_at          ISO 8601 start date; defaults to now.
 * @param  string      $ends_at            ISO 8601 end date; defaults to one year out.
 * @param  int|false   $grace_period_days  Grace period in days; `false` omits the field.
 * @param  bool|null   $is_autorenew       Whether this membership will auto-renew. `null` means
 *                     "not provided" and omits the field entirely, since `false` is itself a
 *                     real, meaningful value for this field (unlike `$grace_period_days`, which
 *                     uses `false` as its own "not provided" sentinel). Typed `?bool` so PHP
 *                     rejects truthy-but-wrong values (e.g. the string `'no'`) at the call
 *                     boundary instead of forwarding them to the MDP as-is.
 * @return array|WP_Error  The MDP API response, or a WP_Error on failure.
 */
function wicket_update_individual_membership_dates($membership_uuid, $starts_at = '', $ends_at = '', $grace_period_days = false, ?bool $is_autorenew = null)
{
    $override = apply_filters('wicket_pre_update_individual_membership_dates', null, $membership_uuid, $starts_at, $ends_at, $grace_period_days, $is_autorenew);

    if ($override !== null) {
        return $override;
    }

    $client = wicket_api_client();

    if (empty($starts_at)) {
        $starts_at = wicket_time_format_iso8601_utc(wicket_time_get_utc_datetime());
    }
    if (empty($ends_at)) {
        $ends_at = wicket_time_format_iso8601_utc(wicket_time_get_utc_datetime('+1 year'));
    }

    // build membership payload
    $payload = [
        'data' => [
            'type' => 'person_memberships',
            'attributes' => [
                'starts_at' => $starts_at,
                'ends_at' => $ends_at,
            ],
        ],
    ];

    if ($grace_period_days !== false) {
        $payload['data']['attributes']['grace_period_days'] = $grace_period_days;
    }

    if ($is_autorenew !== null) {
        $payload['data']['attributes']['is_auto_renew'] = $is_autorenew;
    }

    try {
        $response = $client->patch("/person_memberships/$membership_uuid", ['json' => $payload]);
    } catch (Exception $e) {
        $response = new WP_Error('wicket_api_error', $e->getMessage());
    }

    return $response;
}

/**
 * Update organization membership dates in the MDP.
 *
 * @param string $membership_uuid MDP organization_membership UUID to update.
 * @param string $starts_at Optional. ISO 8601 start date. Defaults to now.
 * @param string $ends_at Optional. ISO 8601 end date. Defaults to one year out.
 * @param int|false $max_seats Optional. Maximum seat assignments. False omits the field.
 * @param int|false $grace_period_days Optional. Grace period in days. False omits the field.
 * @return array|\WP_Error MDP API response array or WP_Error on failure.
 */
function wicket_update_organization_membership_dates($membership_uuid, $starts_at = '', $ends_at = '', $max_seats = false, $grace_period_days = false)
{
    $override = apply_filters('wicket_pre_update_organization_membership_dates', null, $membership_uuid, $starts_at, $ends_at, $max_seats, $grace_period_days);

    if ($override !== null) {
        return $override;
    }

    $client = wicket_api_client();

    if (empty($starts_at)) {
        $starts_at = wicket_time_format_iso8601_utc(wicket_time_get_utc_datetime());
    }
    if (empty($ends_at)) {
        $ends_at = wicket_time_format_iso8601_utc(wicket_time_get_utc_datetime('+1 year'));
    }

    // build membership payload
    $payload = [
        'data' => [
            'type' => 'organization_memberships',
            'attributes' => [
                'starts_at' => $starts_at,
                'ends_at' => $ends_at,
            ],
        ],
    ];

    if ($max_seats !== false) {
        $payload['data']['attributes']['max_assignments'] = $max_seats;
    }

    if ($grace_period_days !== false) {
        $payload['data']['attributes']['grace_period_days'] = $grace_period_days;
    }

    try {
        $response = $client->patch("organization_memberships/$membership_uuid", ['json' => $payload]);
    } catch (Exception $e) {
        $response = new WP_Error('wicket_api_error', $e->getMessage());
    }

    return $response;
}

/**
 * Delete a person membership record by UUID.
 *
 * @param string $membership_uuid The person membership UUID.
 * @return array|\WP_Error MDP API response array or WP_Error on failure.
 */
function wicket_delete_person_membership($membership_uuid)
{
    $client = wicket_api_client();
    try {
        $response = $client->delete("person_memberships/$membership_uuid");
    } catch (Exception $e) {
        $response = new WP_Error('wicket_api_error', $e->getMessage());
    }

    return $response;
}

/**
 * Delete an organization membership record by UUID.
 *
 * Note: passing force_destroy=true clears all associated membership assignments.
 *
 * @param string $membership_uuid The organization membership UUID.
 * @return array|\WP_Error MDP API response array or WP_Error on failure.
 */
function wicket_delete_organization_membership($membership_uuid)
{
    $client = wicket_api_client();
    try {
        $response = $client->delete("organization_memberships/$membership_uuid?force_detroy=true");
    } catch (Exception $e) {
        $response = new WP_Error('wicket_api_error', $e->getMessage());
    }

    return $response;
}

/**
 * Set the external ID on the membership record.
 *
 * @param string $membership_uuid wicket mdp membership id
 * @param string $membership_type organization|individual
 * @param int $external_id post_id
 * @return object | WP_Error
 */
function wicket_update_membership_external_id($membership_uuid, $membership_type, $external_id)
{
    $override = apply_filters('wicket_pre_update_membership_external_id', null, $membership_uuid, $membership_type, $external_id);

    if ($override !== null) {
        return $override;
    }

    $client = wicket_api_client();

    if (!in_array($membership_type, ['organization_memberships', 'person_memberships'])) {
        new WP_Error('wicket_api_error', 'Unknown membership_type ( organization_memberships, person_memberships )');
    }

    // build membership payload
    $payload = [
        'data' => [
            'type' => $membership_type,
            'attributes' => [
                'external_id' => $external_id,
            ],
        ],
    ];

    try {
        $response = $client->patch("$membership_type/$membership_uuid", ['json' => $payload]);
    } catch (Exception $e) {
        // external_id is what makes the MDP mark this membership as externally
        // managed (WooCommerce badge + locked dates). A dropped PATCH leaves the
        // membership silently unlinked, so log every failure here so no caller
        // (importer, Woo checkout, admin transfer) can fail invisibly. The
        // WP_Error is still returned so callers can react (e.g. retry).
        Wicket()->log()->error(
            'wicket_update_membership_external_id failed: ' . $e->getMessage(),
            [
                'source'          => 'wicket-base',
                'membership_type' => $membership_type,
                'membership_uuid' => $membership_uuid,
                'external_id'     => $external_id,
            ]
        );
        $response = new WP_Error('wicket_api_error', $e->getMessage());
    }

    return $response;
}

/**
 * Find the membership (person or organization) that currently owns a given
 * external_id, if any. external_id is the WordPress membership post ID, and
 * the MDP enforces a unique index on it per membership type where deleted_at
 * IS NULL.
 *
 * Use this as a pre-flight before wicket_update_membership_external_id() so a
 * collision is detected and reported with the owning record instead of failing
 * the PATCH with an opaque 409 and leaving external_id silently unset.
 *
 * @param string|int $external_id The WordPress membership post ID.
 * @param string     $membership_type 'organization_memberships' or 'person_memberships'.
 * @return array|false|\WP_Error The owning membership record (with 'id'), false if
 *                               unowned, or WP_Error on API failure.
 */
function wicket_get_membership_by_external_id($external_id, $membership_type)
{
    $override = apply_filters('wicket_pre_get_membership_by_external_id', null, $external_id, $membership_type);

    if ($override !== null) {
        return $override;
    }

    if (!in_array($membership_type, ['organization_memberships', 'person_memberships'], true)) {
        return new WP_Error('wicket_api_error', 'Unknown membership_type ( organization_memberships, person_memberships )');
    }

    $client = wicket_api_client();

    if (!$client) {
        return new WP_Error('wicket_api_error', 'Wicket API client unavailable');
    }

    try {
        $response = $client->get($membership_type . '?filter[external_id_eq]=' . urlencode((string) $external_id) . '&page[size]=1');

        if (!empty($response['data'][0])) {
            return $response['data'][0];
        }

        return false;
    } catch (Exception $e) {
        Wicket()->log()->error(
            'wicket_get_membership_by_external_id failed: ' . $e->getMessage(),
            [
                'source'          => 'wicket-base',
                'membership_type' => $membership_type,
                'external_id'     => $external_id,
            ]
        );

        return new WP_Error('wicket_api_error', $e->getMessage());
    }
}

/**
 * Get all membership entries for a specified person UUID from the MDP API.
 *
 * @param string $uuid The person UUID.
 * @return array|false The membership entries array or false on failure.
 */
function wicket_get_person_memberships($uuid)
{
    $client = wicket_api_client();
    static $memberships = null;
    // prepare and memoize all connections from Wicket
    if (is_null($memberships)) {
        try {
            $memberships = $client->get('people/' . $uuid . '/membership_entries?include=membership,organization_membership.organization,fusebill_subscription');
        } catch (Exception $e) {

        }
    }
    if ($memberships) {
        return $memberships;
    }

    return false;
}

/**
 * Get active membership entries for a specified person UUID from the MDP API.
 *
 * Filters for active_at=now and memoizes per-person UUID.
 *
 * @param string $uuid The person UUID.
 * @return array|false The active membership entries array or false on failure.
 */
function wicket_get_person_active_memberships($uuid)
{
    static $cache = [];

    // Memoize per-person, keyed by UUID. The previous implementation used a
    // single request-global static with no key, so the first person's result
    // was returned for every subsequent UUID in the same request - unsafe for
    // batch/per-row use such as the importer's conflict pre-pass. Behavior for
    // single-UUID callers is unchanged (still one fetch per UUID per request).
    if (!isset($cache[$uuid])) {
        $client = wicket_api_client();
        try {
            $cache[$uuid] = $client->get('people/' . $uuid . '/membership_entries?include=membership,organization_membership.organization,fusebill_subscription&filter[active_at]=now');
        } catch (Throwable $e) {
            $cache[$uuid] = false;
        }
    }

    if ($cache[$uuid]) {
        return $cache[$uuid];
    }

    return false;
}

/**
 * Get the person memberships for a specified UUID using the membership entries endpoint.
 *
 * @param array $args {
 *     Optional. Arguments to pass to the API.
 *
 *     @type string $person_uuid Person UUID. Defaults to current person.
 *     @type string $include Resources to include.
 *     @type array  $filter Filters to apply.
 * }
 * @return array|false Array of memberships or false on failure.
 */
function wicket_get_current_person_memberships($args = [])
{
    $defaults = [
        'person_uuid' => wicket_current_person_uuid(),
        'include' => 'membership,organization_membership.organization,fusebill_subscription',
        'filter' => [
            'active_at' => 'now',
        ],
    ];

    $args = wp_parse_args($args, $defaults);

    $client = wicket_api_client();
    $uuid = $args['person_uuid'];

    static $memberships = null;

    // prepare and memoize all connections from Wicket
    if (is_null($memberships)) {
        try {
            $memberships = $client->get('people/' . $uuid . '/membership_entries?' . http_build_query($args));
        } catch (Exception $e) {
            return false;
        }
    }

    if ($memberships) {
        return $memberships;
    }

    return false;
}

/**
 * Get active membership entries for the current person from the MDP API.
 *
 * @return array|false Array of active memberships or false on failure.
 */
function wicket_get_current_person_active_memberships()
{
    $response = wicket_get_current_person_memberships([
        'filter' => [
            'active_at' => 'now',
        ],
    ]);

    if (is_wp_error($response) || empty($response['data'])) {
        return false;
    }

    return $response;
}

/**
 * Get active organization memberships owned by the current user.
 *
 * @return array Array of active organization membership objects.
 */
function wicket_get_active_org_memberships()
{
    $client = wicket_api_client();
    $person_id = wicket_current_person_uuid();
    if ($person_id) {
        $organization_memberships = $client->get("/organization_memberships?filter[owner_uuid_eq]=$person_id&filter[m]=or");
        $active_memberships = [];
        if (isset($organization_memberships['data'][0])) {
            foreach ($organization_memberships['data'] as $org_membership) {
                if ($org_membership['attributes']['active'] == 1) {
                    $active_memberships[] = $org_membership;
                }
            }
        }

        return $active_memberships;
    } else {
        return [];
    }
}

/**
 * Get organization memberships for a specific organization UUID.
 *
 * @param string $org_id Organization UUID.
 * @return array Array of organization memberships keyed by membership ID.
 */
function wicket_get_org_memberships($org_id)
{
    $client = wicket_api_client();
    if ($org_id) {
        try {
            $organization_memberships = $client->get("/organizations/$org_id/membership_entries?sort=-ends_at&include=membership");
        } catch (Exception $e) {
            return [];
        }
        $memberships = [];
        if (isset($organization_memberships['data'][0])) {
            foreach ($organization_memberships['data'] as $org_membership) {
                $memberships[$org_membership['id']]['membership'] = $org_membership;
                // add included attributes as well
                foreach ($organization_memberships['included'] as $included) {
                    if ($included['id'] == $org_membership['relationships']['membership']['data']['id']) {
                        $memberships[$org_membership['id']]['included'] = $included;
                    }
                }
            }
        }

        return $memberships;
    } else {
        return [];
    }
}

/**
 * Get individual membership tiers from the MDP API.
 *
 * @param string $id Optional membership tier UUID.
 * @return array|null Response array of membership tiers.
 *
 * @see https://wicketapi.docs.apiary.io/#reference/supplemental-resources/membership-tiers/fetch-membership-tiers
 */
function get_individual_memberships($id = '')
{
    $client = wicket_api_client();
    $path = 'memberships';
    if (!empty($id)) {
        $path = $path . "/$id";
    }
    try {
        $search_organizations = $client->get($path);
    } catch (Exception $e) {
    }

    return $search_organizations;
}
