<?php

// No direct access
defined('ABSPATH') || exit;

/**
 * Gets the name of a resource type by slug.
 *
 * @param string $slug The slug of the resource type
 *
 * @return string|false The name of the resource type, or false if not found
 */
function wicket_get_resource_type_name_by_slug(string $slug): string|false
{
    $client = wicket_api_client();
    $resource_types = $client->get('/resource_types');
    $lang = wicket_get_current_language();

    if (!isset($resource_types['data']) || !is_array($resource_types['data'])) {
        return false;
    }

    foreach ($resource_types['data'] as $resource_type) {
        if (
            isset($resource_type['attributes']['slug'])
            && $resource_type['attributes']['slug'] === $slug
            && isset($resource_type['attributes']['name'])
        ) {
            // Check for a localized if $lang != 'en'
            if ($lang !== 'en') {
                return $resource_type['attributes']['name_' . $lang] ?? $resource_type['attributes']['name'];
            }

            return $resource_type['attributes']['name'];
        }
    }

    return false;
}

/**
 * Get all JSON Schemas from the MDP API.
 *
 * @return array|null Response array of JSON schemas or null if unavailable.
 */
function wicket_get_schemas()
{
    $client = wicket_api_client();
    static $schemas = null;
    // prepare and memoize all schemas from Wicket
    if (is_null($schemas)) {
        $schemas = $client->get('json_schemas');
    }
    if ($schemas) {
        return $schemas;
    }
}

/**
 * Load options from a schema based on a schema entry.
 *
 * @param array $schema The schema array from the MDP API.
 * @param string $field The field key within the schema.
 * @param string $sub_field Optional. Sub-field key for repeater or nested fields.
 * @return array Array of option items with key and optional label value.
 */
function wicket_get_schemas_options($schema, $field, $sub_field)
{
    $language = strtok(get_bloginfo('language'), '-');
    $return = [];

    // -----------------------------
    // GET VALUES
    // -----------------------------

    // single value
    if (isset($schema['attributes']['schema']['properties'][$field]['enum'])) {
        $counter = 0;
        foreach ($schema['attributes']['schema']['properties'][$field]['enum'] as $key => $value) {
            $return[$counter]['key'] = $value;
            $counter++;
        }
    }
    // multi-value
    if (isset($schema['attributes']['schema']['properties'][$field]['items']['enum'])) {
        $counter = 0;
        foreach ($schema['attributes']['schema']['properties'][$field]['items']['enum'] as $key => $value) {
            $return[$counter]['key'] = $value;
            $counter++;
        }
    }
    // if field is using ui_schema, get keys
    if (isset($schema['attributes']['schema']['oneOf'][0]['properties'][$field]['items']['enum'])) {
        $counter = 0;
        foreach ($schema['attributes']['schema']['oneOf'][0]['properties'][$field]['items']['enum'] as $key => $value) {
            $return[$counter]['key'] = $value;
            $counter++;
        }
    }
    // if field is using a repeater type field with 'move up/down and remove rows', get keys
    if (isset($schema['attributes']['schema']['properties'][$field]['items']['properties'][$sub_field]['enum'])) {
        $counter = 0;
        foreach ($schema['attributes']['schema']['properties'][$field]['items']['properties'][$sub_field]['enum'] as $key => $value) {
            $return[$counter]['key'] = $value;
            $counter++;
        }
    }
    // if field is using a repeater type field with repeater field inside, get keys
    if (isset($schema['attributes']['schema']['properties'][$field]['items']['properties'][$sub_field]['items']['enum'])) {
        $counter = 0;
        foreach ($schema['attributes']['schema']['properties'][$field]['items']['properties'][$sub_field]['items']['enum'] as $key => $value) {
            $return[$counter]['key'] = $value;
            $counter++;
        }
    }
    // if field is using an object type field, get keys
    if (isset($schema['attributes']['schema']['properties'][$field]['oneOf'][0]['properties'][$sub_field]['enum'])) {
        $counter = 0;
        foreach ($schema['attributes']['schema']['properties'][$field]['oneOf'] as $key => $value) {
            $return[$counter]['key'] = $value['properties'][$sub_field]['enum'][0];
            $counter++;
        }
    }
    // if field is using an object type field with values depending on another, get keys (these are buried deeper)
    if (isset($schema['attributes']['schema']['properties'][$field]['items']['oneOf'][0])) {
        $counter = 0;
        foreach ($schema['attributes']['schema']['properties'][$field]['items']['oneOf'] as $key => $value) {
            if (array_key_exists($sub_field, $value['properties'])) {
                foreach ($value['properties'][$sub_field]['items']['enum'] as $sub_value) {
                    $return[$counter]['key'] = $sub_value;
                    $counter++;
                }
            }
        }
    }

    // -----------------------------
    // GET LABELS
    // -----------------------------

    // get label values from ui_schema
    if (isset($schema['attributes']['ui_schema'][$field]['ui:i18n']['enumNames'][$language])) {
        $counter = 0;
        foreach ($schema['attributes']['ui_schema'][$field]['ui:i18n']['enumNames'][$language] as $key => $value) {
            $return[$counter]['value'] = $value;
            $counter++;
        }
    }
    // get label values from ui_schema
    if (isset($schema['attributes']['ui_schema'][$field]['items'][$sub_field]['ui:i18n']['enumNames'][$language])) {
        $counter = 0;
        foreach ($schema['attributes']['ui_schema'][$field]['items'][$sub_field]['ui:i18n']['enumNames'][$language] as $key => $value) {
            $return[$counter]['value'] = $value;
            $counter++;
        }
    }
    // if field is using a repeater type field with 'move up/down and remove rows', get labels
    if (isset($schema['attributes']['schema']['properties'][$field]['items']['properties'][$sub_field]['enumNames'])) {
        $counter = 0;
        foreach ($schema['attributes']['schema']['properties'][$field]['items']['properties'][$sub_field]['enumNames'] as $key => $value) {
            $return[$counter]['value'] = $value;
            $counter++;
        }
    }

    return $return;
}

/**
 * Get all options for a field within a JSON schema.
 *
 * @param string $parent_field The parent schema key or accordion identifier.
 * @param string $field The field key within the schema.
 * @param string $sub_field Optional. Sub-field key for repeater or nested fields.
 * @return array|null The options array or null if unavailable.
 */
function wicket_get_schema_field_values($parent_field, $field, $sub_field = '')
{
    $schemas = wicket_get_schemas();
    if ($schemas) {
        foreach ($schemas['data'] as $key => $schema) {
            if ($schema['attributes']['key'] == $parent_field) {
                $schema = $schemas['data'][$key];
                break;
            }
        }
        $options = wicket_get_schemas_options($schema, $field, $sub_field);
        if ($options) {
            return $options;
        }
    }
}

/**
 * Build the data_fields array during form submission.
 *
 * Appends formatted fields to the passed-by-reference array.
 *
 * @param array &$data_fields Array reference being built for API payload submission.
 * @param string $field Field name within the schema.
 * @param string $schema Schema ID / accordion identifier.
 * @param string $type Field type: string, array, int, boolean, object, or readonly.
 * @param object|string $entity Optional. Preloaded entity object from the API.
 * @return false|void False if field should not be appended, void otherwise.
 */
function wicket_add_data_field(&$data_fields, $field, $schema, $type, $entity = '')
{
    if (isset($_POST[$field])) {
        $value = $_POST[$field];

        // remove empty arrays (likely select fields with the "choose option" set)
        if ($type == 'array' && empty(array_filter($value))) {
            return false;
        }

        // remove empty strings (likely select fields with the "choose option" set)
        if ($type == 'string' && $value == '') {
            return false;
        }

        // add conversion for booleans
        if ($type == 'boolean' && $_POST[$field] == '1') {
            $value = true;
        }
        if ($type == 'boolean' && $_POST[$field] == '0') {
            $value = false;
        }
        // if boolean is posted but no value, ignore it
        if ($type == 'boolean' && $_POST[$field] == '') {
            return false;
        }
        // cast ints for the API (like year values)
        if ($type == 'int' && $value) {
            $value = (int) $value;
        } elseif ($type == 'int' && !$value) {
            // dont include int fields if we want to blank them out
            return false;
        }

        // convert object to arrays, replacing passed-in values looping over by reference
        if ($type == 'object' && $value) {
            foreach ($value as $key => &$index) {
                $index = (array) json_decode(stripslashes($index));
            }
        }

        // keep the fields for each schema together by keying the data_fields array by the schema id
        // It still seems to work through the API this way, even though the wicket admin uses zero based array indexes
        $data_fields[$schema]['value'][$field] = $value;
        $data_fields[$schema]['$schema'] = $schema;
    } else {
        // pass empty array for multi-value fields to clear them out if no options are present
        if ($type == 'array' || $type == 'object') {
            $value = [];
        }
        // unset empty string if no value set. Sometimes happens to radio buttons with no value
        if ($type == 'string') {
            return false;
        }

        // unset empty boolean if no value set. Sometimes happens to radio buttons with no value
        if ($type == 'boolean') {
            return false;
        }

        // don't return a field if array is being used using "oneOf" to clear them out if no options are present
        // these are typically used in Wicket for initial yes/no radios followed by a field if choose "yes"
        if ($type == 'array_oneof') {
            return false;
        }

        // if this field is being used as a "readonly" value on the edit form page,
        // pass on the original value(s) within the schema otherwise they'll be emptied if not passed on PATCH
        if ($type == 'readonly') {
            // make sure, usually on new accounts, that there is even AI fields to read from
            // data_fields will likely be completely empty on new accounts
            if (!empty((array) $entity->data_fields) && array_search($schema, array_column((array) $entity->data_fields, '$schema'))) {
                foreach ($entity->data_fields as $df) {
                    if ($df['$schema'] == $schema) {
                        // look for existing value, if there is one, else ignore this field
                        if (isset($df['value'][$field])) {
                            $value = $df['value'][$field];
                        } else {
                            return false;
                        }
                    }
                }
            } else {
                return false;
            }
        }

        $data_fields[$schema]['value'][$field] = $value ?? '';
        $data_fields[$schema]['$schema'] = $schema;
    }
}

/**
 * Get all entity types from the MDP API.
 *
 * @return array|false The entity types response array or false on failure.
 */
function wicket_get_entity_types()
{
    try {
        $client = wicket_api_client();
    } catch (Exception $e) {
        Wicket()->log()->error($e->getMessage(), ['source' => 'wicket-base']);

        return false;
    }

    try {
        $entity_types = $client->get('entity_types?page%5Bnumber%5D=1&page%5Bsize%5D=9999999');
        if (isset($entity_types['data'])) {
            return $entity_types;
        } else {
            return false;
        }
    } catch (Exception $e) {
        Wicket()->log()->error($e->getMessage(), ['source' => 'wicket-base']);

        return false;
    }
}

/**
 * Get available resource types from the MDP API.
 *
 * If an entity type slug is provided, results are filtered by entity type UUID.
 * If omitted or not found, all resource types are returned.
 *
 * @param string $entity_type_slug Optional. The entity type code/slug to filter by.
 * @return array|false The resource types response array or false on failure.
 */
function wicket_get_resource_types($entity_type_slug = '')
{
    try {
        $client = wicket_api_client();
    } catch (Exception $e) {
        Wicket()->log()->error($e->getMessage(), ['source' => 'wicket-base']);

        return false;
    }

    $entity_types = wicket_get_entity_types();

    $entity_type_uuid = '';
    if (isset($entity_types['data'])) {
        foreach ($entity_types['data'] as $entity) {
            if (isset($entity['attributes'])) {
                if (isset($entity['attributes']['code'])) {
                    if ($entity['attributes']['code'] == $entity_type_slug) {
                        $entity_type_uuid = $entity['attributes']['uuid'];
                    }
                }
            }
        }
    }
    // If no $entity_type_slug is provided or the $entity_type_uuid is not found, all recource_types will be returned

    try {
        $resource_types = $client->get("resource_types?filter%5Bentity_type_uuid_eq%5D=$entity_type_uuid");

        return $resource_types;
    } catch (Exception $e) {
        Wicket()->log()->error($e->getMessage(), ['source' => 'wicket-base']);

        return false;
    }

    return false;
}

/**
 * DEPRECATED - Use wicket_update_schema_by_slug() instead as it supports MDP slugs and more than updating persons.
 *
 * @param string  $schema_slug     The schema slug to identify the schema.
 * @param string  $key             The key to update within the schema. Pass null to update multiple values.
 * @param mixed   $value           The value to set for the specified key, or the custom payload if $pass_raw_value is true.
 * @param bool    $pass_raw_value  Set to true to pass a custom payload in $value. Default is false.
 * @param string  $person_uuid     The UUID of the person to update. Default is 0, which means the current user.
 * @return array                   Returns an array with a boolean indicating success, and an error message if failed.
 *
 * @deprecated 1.0.0 Use wicket_update_schema_by_slug() instead. This function will be removed in a future release.
 */
function wicket_update_schema_single_value($schema_slug, $key, $value, $pass_raw_value = false, $person_uuid = 0)
{
    $client = wicket_api_client();
    $schema = wicket_get_schema($schema_slug);
    if ($person_uuid == 0) {
        $wicket_person = wicket_current_person();
        $person_uuid = $wicket_person->id;
    } else {
        $wicket_person = wicket_get_person_by_id($person_uuid);
    }

    if (empty($client) || empty($schema) || empty($wicket_person)) {
        return false;
    }

    $schema_uuid = $schema['id'];
    $data_fields = $wicket_person->data_fields ?? null;
    if (!is_array($data_fields)) {
        Wicket()->log()->warning('[wicket-base-helper] Person data_fields missing or not array in wicket_update_schema_single_value; schema_slug=' . $schema_slug . '; person_uuid=' . $person_uuid . '; received_type=' . gettype($data_fields), ['source' => 'wicket-base']);
        $schema_values = [];
    } else {
        $schema_values = wicket_get_field_from_data_fields($data_fields, $schema_slug)['value'];
    }
    $sub_payload = [];
    if (!$pass_raw_value) {
        $schema_values[$key] = $value;
        $sub_payload = $schema_values;
    } else {
        $sub_payload = $value;
    }

    // Cleaning up values
    // TODO: Potentially include more cleanup conditions found in wicket_add_data_field(),
    //  or reference it directly
    foreach ($sub_payload as $key => $value) {
        // remove empty arrays (likely select fields with the "choose option" set)
        if (is_array($value) && empty($value)) {
            unset($sub_payload[$key]);
        }
    }

    try {
        $payload = [
            'data' => [
                'type' => 'people',
                'id' => "$person_uuid",
                'attributes' => [
                    'data_fields' => [[
                        '$schema' => "urn:uuid:$schema_uuid",
                        'value' => $sub_payload,
                    ]],
                ],
            ],
        ];

        $client->patch("people/$person_uuid", ['json' => $payload]);

        return [true];
    } catch (Exception $e) {
        return [false, $e->getMessage()];
    }
}

/**
 * Enables writing a single AI value for a person based on a single key/value pair.
 *
 * Is essentially a v2 of wicket_update_schema_single_value() but uses *actual MDP-supported slugs and not
 * the old fake ones that would get converted into IDs via an API call, and makes some improvements based on that.
 * This updated version also allows updating both person and organization record types.
 *
 * Example usage:
 * ```php
 * wicket_update_schema_by_slug('orgadvocacy', 'fedRiding', "2");
 * wicket_update_schema_by_slug('orginterests', 'interests', ['advocacy'], false, '4b4e4594-70d3-4402-9b33-a528bca82e26', 'org');
 * ```
 *
 * @param string $schema_slug      The MDP slug for that schema.
 * @param string $key              The key to update within the schema's value array. Pass null to update multiple values. using $pass_raw_value.
 * @param mixed  $value            The value to set for the specified key, or the custom payload if $pass_raw_value is true.
 * @param bool   $pass_raw_value   (Optional) Set to true to pass a custom payload in $value. Default is false.
 * @param string $target_uuid      (Optional) UUID of the person or org to update. If not passed, will default to current user.
 * @param string $type             (Optional) Type of record to update. Can be set to 'person' or 'org'.
 *
 * @return array                   Returns an array with a boolean indicating success, and an error message if failed.
 */
function wicket_update_schema_by_slug($schema_slug, $key, $value, $pass_raw_value = false, $target_uuid = '', $type = 'person')
{
    $client = wicket_api_client();
    if (empty($target_uuid) && $type == 'person') {
        $wicket_person = wicket_current_person();
        $target_uuid = $wicket_person->id;
    } elseif ($type == 'person') {
        $wicket_person = wicket_get_person_by_id($target_uuid);
    } elseif ($type == 'org') {
        $wicket_org = wicket_get_organization($target_uuid);
    } else {
        return [false, 'Please provide all parameters.'];
    }

    if (empty($client)) {
        return [false, 'Could not obtain client.'];
    }

    // Set schema values depending on the type of entity we're working with
    $schema_values = [];
    if ($type == 'person') {
        $data_fields = $wicket_person->data_fields ?? null;
        if (!is_array($data_fields)) {
            Wicket()->log()->warning('[wicket-base-helper] Person data_fields missing or not array when updating schema by slug; schema_slug=' . $schema_slug . '; target_uuid=' . $target_uuid . '; received_type=' . gettype($data_fields), ['source' => 'wicket-base']);
            $schema_values = [];
        } else {
            $schema_values = wicket_get_field_from_data_fields($data_fields, $schema_slug)['value'];
        }
    } elseif ($type == 'org') {
        $data_fields = $wicket_org['data']['attributes']['data_fields'] ?? null;
        if (!is_array($data_fields)) {
            Wicket()->log()->warning('[wicket-base-helper] Org data_fields missing or not array when updating schema by slug; schema_slug=' . $schema_slug . '; target_uuid=' . $target_uuid . '; received_type=' . gettype($data_fields), ['source' => 'wicket-base']);
            $schema_values = [];
        } else {
            $schema_values = wicket_get_field_from_data_fields($data_fields, $schema_slug)['value'];
        }
    }

    // --------------------------------------------------------------------
    // Do the setting, cleanup, and API calls, which apply to both entities:
    // --------------------------------------------------------------------

    // Set new value
    $sub_payload = [];
    if (!$pass_raw_value) {
        $schema_values[$key] = $value;
        $sub_payload = $schema_values;
    } else {
        $sub_payload = $value;
    }

    // Cleaning up values
    // TODO: Potentially include more cleanup conditions found in wicket_add_data_field(),
    //  or reference it directly
    foreach ($sub_payload as $key => $value) {
        // remove empty arrays (likely select fields with the "choose option" set)
        if (is_array($value) && empty($value)) {
            unset($sub_payload[$key]);
        }
    }

    // Make the API call
    $api_path = $type == 'org' ? 'organizations' : 'people';
    try {
        $payload = [
            'data' => [
                'type' => "$api_path",
                'id' => "$target_uuid",
                'attributes' => [
                    'data_fields' => [[
                        'schema_slug' => "$schema_slug",
                        'value' => $sub_payload,
                    ]],
                ],
            ],
        ];

        $output = $client->patch("$api_path/$target_uuid", ['json' => $payload]);

        return [true, $output];
    } catch (Exception $e) {
        return [false, $e->getMessage()];
    }

    return [false, 'Something went wrong.'];
}

/**
 * Helper function for wicket_update_schema_single_value.
 *
 * @param array $data_fields
 * @param string $key
 *
 * @return array
 */
function wicket_get_field_from_data_fields($data_fields, $key)
{
    // Ensure we are working with an array to avoid fatal errors
    $isArray = is_array($data_fields);
    if (!$isArray) {
        Wicket()->log()->warning('[wicket-base-helper] wicket_get_field_from_data_fields received non-array data_fields; key=' . $key . '; type=' . gettype($data_fields), ['source' => 'wicket-base']);

        // Return an empty value container to maintain expected shape
        return ['value' => []];
    }

    // get matches
    $matches = array_filter($data_fields, function ($field) use ($key) {
        return isset($field['key']) && $field['key'] == $key;
    });

    if (empty($matches)) {
        Wicket()->log()->debug('[wicket-base-helper] No matching data_field found for key=' . $key, ['source' => 'wicket-base']);

        return ['value' => []];
    }

    // return first match
    return reset($matches);
}

/**
 * Gets a schema by slug.
 *
 * @param string $schema_slug The schema slug to search for.
 *
 * @return array The schema if found, otherwise an empty array.
 */
function wicket_get_schema($schema_slug)
{
    $schemas = wicket_get_schemas();
    if ($schemas) {
        $result = array_filter($schemas['data'], function ($schema) use ($schema_slug) {
            return $schema['attributes']['key'] == $schema_slug;
        });

        return reset($result);
    }
}
