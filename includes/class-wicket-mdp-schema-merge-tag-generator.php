<?php

/**
 * Class Wicket_Mdp_Schema_Merge_Tag_Generator.
 *
 * Maps and transforms JSON Schema fields and their UI definitions
 * into a merge tag friendly and localized format.
 *
 * BE CAREFUL, THIS CLASS IS CONSIDERED AS EXPERIMENTAL AND MAY CHANGE IN FUTURE RELEASES.
 */
class Wicket_Mdp_Schema_Merge_Tag_Generator
{
    private array $schema;
    private array $uiSchema;
    private string $lang;

    /**
     * Wicket_Mdp_Schema_Merge_Tag_Generator constructor.
     *
     * @param array $schema   The JSON Schema definition.
     * @param array $uiSchema The corresponding UI Schema definition.
     * @param string $lang    Language code for localization (e.g., 'en').
     */
    public function __construct(array $schema, array $uiSchema, string $lang = 'en')
    {
        $this->schema = $schema;
        $this->uiSchema = $uiSchema;
        $this->lang = $lang;
    }

    /**
     * Returns "merge tags" with localized labels using a schema slug.
     *
     * @param string $schemaSlug Schema identifier to namespace the placeholders.
     * @return array Associative array of placeholders => labels.
     */
    public function getMergeTags(string $schemaSlug): array
    {
        $fields = $this->extractFields($this->schema, $this->uiSchema);
        $placeholders = [];

        foreach ($fields as $field) {
            $placeholders["{{$schemaSlug}.{$field['name']}}"] = $field['label'];
        }

        return $placeholders;
    }

    /**
     * Returns a single value from dataFields using exact path (including indexes).
     *
     * @param string $fieldPath   Dot-notated field path (e.g. 'maiden', 'schoolSection.vetSchool', 'repeater[0].field').
     * @param array $dataFields   Array of schema-based data entries we usually get from a person or organization.
     * @param string $schemaSlug  Slug used to match schema entry.
     *
     * @return mixed|null The field value or null if not found.
     */
    public function getFieldValue(string $fieldPath, array $dataFields, string $schemaSlug): mixed
    {
        $values = $this->getFieldValuesForRecord($dataFields, $schemaSlug);

        return $values['{' . $fieldPath . '}'] ?? null;
    }

    /**
     * Returns all values matching a wildcard field path like 'repeater[*].fieldname'.
     *
     * @param string $wildcardPath Wildcard path with [*] to match indexes.
     * @param array $dataFields    Array of schema-based data entries.
     * @param string $schemaSlug   Slug used to match schema entry.
     * @param bool $return_string  If true, returns a comma-separated string of values.
     *
     * @return array|string
     *   Array of matching values or a string if $return_string is true.
     */
    public function getValuesByWildcardPath(string $wildcardPath, array $dataFields, string $schemaSlug, bool $return_string = false)
    {
        $allValues = $this->getFieldValuesForRecord($dataFields, $schemaSlug);

        $pattern = preg_quote($wildcardPath, '/');
        $pattern = str_replace('\\[\\*\\]', '\\[(\\d+)\\]', $pattern);
        $pattern = '/^' . $pattern . '$/';

        $results = [];

        foreach ($allValues as $placeholder => $value) {
            $key = trim($placeholder, '{}');
            if (preg_match($pattern, $key)) {
                $results[] = $value;
            }
        }

        if ($return_string) {
            $results = implode(', ', $results);
        }

        return $results;
    }

    /**
     * Returns a localized label for a field path.
     *
     * @param string $fieldPath Dot-notated field path.
     * @return string Localized label or the field name if not found.
     */
    public function getFieldLabel(string $fieldPath): string
    {
        $fieldPath = str_replace(['[*]'], '', $fieldPath);
        $parts = explode('.', $fieldPath);
        $uiNode = $this->uiSchema;

        foreach ($parts as $part) {
            if (!isset($uiNode[$part])) {
                return end($parts);
            }
            $uiNode = $uiNode[$part];
        }

        return $uiNode['ui:i18n']['label'][$this->lang] ?? end($parts);
    }

    /**
     * Returns all flattened placeholder => value pairs for a given schema slug.
     *
     * @param array $dataFields  Data entries.
     * @param string $schemaSlug Schema identifier.
     * @return array Flattened placeholder-value pairs.
     */
    public function getFieldValuesForRecord(array $dataFields, string $schemaSlug): array
    {
        $placeholders = [];

        foreach ($dataFields as $entry) {
            if (($entry['schema_slug'] ?? null) === $schemaSlug && isset($entry['value'])) {
                $flat = $this->flatten($entry['value']);

                foreach ($flat as $key => $val) {
                    if (is_array($val)) {
                        $val = implode(', ', array_map('strval', $val));
                    }
                    $placeholders["{{$key}}"] = $val;
                }

                break;
            }
        }

        return $placeholders;
    }

    /**
     * Returns a human-readable version of a raw field value using schema/UI hints.
     *
     * @param string $fieldPath Field path (dot-notated).
     * @param mixed $rawValue   Raw value from data.
     * @return string Human-readable (e.g. localized enum label).
     */
    public function getHumanReadableValueFromSchema(string $fieldPath, mixed $rawValue): string
    {
        $fieldPath = str_replace(['[*]'], '', $fieldPath);
        $schemaNode = $this->schema;
        $uiNode = $this->uiSchema;
        $parentUiNode = null;
        $lastPart = null;
        $parts = explode('.', $fieldPath);

        foreach ($parts as $part) {
            $lastPart = $part;
            $parentUiNode = $uiNode;

            if (isset($schemaNode['properties'][$part])) {
                $schemaNode = $schemaNode['properties'][$part];
            } elseif (isset($schemaNode['items']['properties'][$part])) {
                $schemaNode = $schemaNode['items']['properties'][$part];
            } elseif (isset($schemaNode['oneOf'])) {
                foreach ($schemaNode['oneOf'] as $variant) {
                    if (isset($variant['properties'][$part])) {
                        $schemaNode = $variant['properties'][$part];
                        break;
                    }
                }
            }

            if (isset($uiNode[$part])) {
                $uiNode = $uiNode[$part];
            } elseif (isset($uiNode['items'][$part])) {
                $uiNode = $uiNode['items'][$part];
            } elseif (isset($uiNode['oneOf'])) {
                foreach ($uiNode['oneOf'] as $variantUi) {
                    if (isset($variantUi[$part])) {
                        $uiNode = $variantUi[$part];
                        break;
                    }
                }
            } else {
                $uiNode = [];
            }
        }

        if (($schemaNode['type'] ?? null) === 'array' && isset($schemaNode['items']['enum'])) {
            $enum = $schemaNode['items']['enum'];
            $enumNames = $uiNode['ui:i18n']['enumNames'][$this->lang] ?? [];
            $values = is_array($rawValue) ? $rawValue : [$rawValue];

            $labels = array_map(function ($val) use ($enum, $enumNames) {
                $index = array_search($val, $enum);

                return $enumNames[$index] ?? $val;
            }, $values);

            return implode(', ', $labels);
        }

        if (isset($schemaNode['enum'])) {
            $enum = $schemaNode['enum'];
            $enumNames = $uiNode['ui:i18n']['enumNames'][$this->lang] ?? [];
            $index = array_search($rawValue, $enum);
            if ($index !== false && isset($enumNames[$index])) {
                return $enumNames[$index];
            }
        }

        if (
            isset($parentUiNode['ui:options']['enumField'])
            && $parentUiNode['ui:options']['enumField'] === $lastPart
            && isset($parentUiNode['ui:options']['enumFieldOptions'])
            && isset($parentUiNode['ui:i18n']['enumFieldOptionsNames'][$this->lang])
        ) {
            $enum = $parentUiNode['ui:options']['enumFieldOptions'];
            $enumNames = $parentUiNode['ui:i18n']['enumFieldOptionsNames'][$this->lang];
            $index = array_search($rawValue, $enum);
            if ($index !== false && isset($enumNames[$index])) {
                return $enumNames[$index];
            }
        }

        return is_array($rawValue) ? implode(', ', $rawValue) : (string) $rawValue;
    }

    /**
     * Recursively extracts all field paths and labels from a schema+UI subtree.
     *
     * @param array $schemaNode Current JSON Schema node.
     * @param array $uiNode     Corresponding UI Schema node.
     * @param string $prefix    Current field prefix path.
     * @return array Array of ['name' => path, 'label' => label].
     */
    private function extractFields(array $schemaNode, array $uiNode, string $prefix = ''): array
    {
        $fields = [];

        if (isset($schemaNode['oneOf'])) {
            foreach ($schemaNode['oneOf'] as $variant) {
                $fields = array_merge($fields, $this->extractFields($variant, $uiNode, $prefix));
            }

            return $fields;
        }

        if (!isset($schemaNode['properties'])) {
            return $fields;
        }

        foreach ($schemaNode['properties'] as $fieldName => $fieldSchema) {
            $fullName = $prefix ? "$prefix.$fieldName" : $fieldName;
            $label = $uiNode[$fieldName]['ui:i18n']['label'][$this->lang] ?? $fieldName;
            $fields[] = ['name' => $fullName, 'label' => $label];

            $fieldType = $fieldSchema['type'] ?? null;

            if ($fieldType === 'object') {
                $childUi = $uiNode[$fieldName] ?? [];
                $fields = array_merge($fields, $this->extractFields($fieldSchema, $childUi, $fullName));
            }

            if ($fieldType === 'array' && isset($fieldSchema['items']['type'])) {
                if ($fieldSchema['items']['type'] === 'object') {
                    $childUi = $uiNode[$fieldName] ?? [];
                    $fields = array_merge($fields, $this->extractFields($fieldSchema['items'], $childUi, $fullName . '[*]'));
                }
            }

            if (isset($fieldSchema['oneOf'])) {
                $childUi = $uiNode[$fieldName] ?? [];
                foreach ($fieldSchema['oneOf'] as $variant) {
                    $fields = array_merge($fields, $this->extractFields($variant, $childUi, $fullName));
                }
            }
        }

        return $fields;
    }

    /**
     * Flattens a nested array into dot-notated path => value pairs.
     *
     * @param array $array  The nested input array.
     * @param string $prefix Optional current path prefix.
     * @return array Flattened associative array.
     */
    private function flatten(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $fullKey = $prefix !== '' ? "{$prefix}.{$key}" : $key;

            if (is_array($value)) {
                if ($this->isAssoc($value)) {
                    $result = array_merge($result, $this->flatten($value, $fullKey));
                } else {
                    foreach ($value as $idx => $item) {
                        $indexedKey = "{$fullKey}[{$idx}]";
                        if (is_array($item)) {
                            $result = array_merge($result, $this->flatten($item, $indexedKey));
                        } else {
                            $result[$indexedKey] = $item;
                        }
                    }
                }
            } else {
                $result[$fullKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Determines if an array is associative (i.e. not a simple list).
     *
     * @param array $arr The array to test.
     * @return bool True if associative, false otherwise.
     */
    private function isAssoc(array $arr): bool
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}

/************ EXAMPLE OF SHOWING ALL SCHEMA VALUES FOR THE MEMBER **********/

// $client = wicket_api_client();

// // Get the schema by its UUID
// $response = $client->get('/json_schemas/5f9a0eac-cd9a-4f07-9dad-be2f0c237a04?include=json_schema_resources');

// // Get the schema and UI schema from the response
// $schema = $response['data']['attributes']['schema_raw'];
// $uiSchema = $response['data']['attributes']['ui_schema_raw'];

// // Create a mapper instance and pass the schema and UI schema
// $mapper = new Wicket_Mdp_Schema_Merge_Tag_Generator($schema, $uiSchema, 'en');

// // Define the schema slug for which we want to get merge-tags
// $wicketSchemaSlug = 'hivebrite-test-details';

// // Get all merge tags (placeholders) for the specified schema slug
// $mergeTags = $mapper->getMergeTags($wicketSchemaSlug);

// $person = wicket_current_person();

// // Get the AI fields for the person
// $dataFields = $person->getAttribute('data_fields');

// foreach ($mergeTags as $mergeTag => $label) {
//     // Extract the schema slug and field path from the merge tag: {schema_slug.field.subfield}
//     $cleaned = trim($mergeTag, '{}');
//     $parts = explode('.', $cleaned, 2);

//     if (count($parts) !== 2) continue; // Skip if the merge tag is invalid

//     [$schemaSlug, $fieldPath] = $parts;

//     // If the path contains a wildcard, use the wildcard method
//     if (str_contains($fieldPath, '*')) {
//         $rawValue = $mapper->getValuesByWildcardPath($fieldPath, $dataFields, $schemaSlug, true);
//         $prettyValue = '';
//     } else {
//         $rawValue = $mapper->getFieldValue($fieldPath, $dataFields, $schemaSlug);
//         $prettyValue = $mapper->getHumanReadableValueFromSchema($fieldPath, $rawValue);
//     }

//     // Output block (no table used)
//     echo '<div style="margin-bottom: 20px;">';
//     echo '<div><strong><code>' . esc_html($label) . '</code></strong></div>';
//     echo '<div><code>' . esc_html($mergeTag) . '</code></div>';
//     echo '<div><code>' . esc_html($prettyValue ?: '—') . '</code></div>';

//     // Output the raw value below (if available)
//     if ($rawValue !== null) {
//         $rawOut = is_scalar($rawValue) ? $rawValue : json_encode($rawValue, JSON_UNESCAPED_UNICODE);
//         echo '<div><code>' . esc_html($rawOut) . '</code></div>';
//     }

//     echo '</div>';
// }
