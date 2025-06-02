<?php

require_once "vendor/autoload.php";
require_once 'example_helpers.php';

/*
  The following snippet shows an example of how to search for people using the autocomplete endpoint and ensure these people still have a member 
  role to a specific organization (eg. branch)

  The script is broken down into two separate API calls:

  1. Use the autocomplete endpoint in order to find people matching a specific term, this query will match on names (partial supported), membership number, primary email and phone numbers.
  2. A follow-up request using the results from step one in order to fetch more details (roles) about the people.

*/

$client = wicket_api_client();

// Autocomplete is limited to 100 results total.
$max_results = 25;
$branch_organization_id = 'fa957ab0-89bf-484b-91b4-9d8cdd7fbf7c';

$autocomplete_results = $client->get('/search/autocomplete', [
  'query' => [
    // Autocomplete lookup query, can filter based on name, membership number, email etc.
    'query' => 'Laker',
    'filter' => [
      // Limit autocomplete results to only people resources
      'resource_type' => 'people'
    ],
    'page' => [
      'size' => $max_results
    ]
  ]
]);

$autocomplete_person_uuids = array_map(function ($result) {
  return $result['relationships']['resource']['data']['id'];
}, (array)$autocomplete_results['data']);

$matching_people = [];

if (!empty($autocomplete_person_uuids)) {
  // Work around PHP limitation when serializing array query parameters. Wicket expects
  // arrays parameters to be formatted without numbers eg. param[]=1
  $people_query = preg_replace('/\%5B\d+\%5D/', '%5B%5D', http_build_query([
    'include' => 'emails,roles',
    'filter' => [
      'uuid_in' => $autocomplete_person_uuids
    ],
    'page' => [
      'size' => $max_results
    ]
  ]));

  $people_lookup_results = $client->get('/people', ['query' => $people_query]);
  $response_helper = new \Wicket\ResponseHelper($people_lookup_results);
  $matching_people = array_filter($response_helper->data, function ($person) use ($response_helper, $branch_organization_id) {    
    $has_branch_member_role = false;
    $branch_resource_id = ['type' => 'organizations', 'id' => $branch_organization_id];

    $roles = (array)$response_helper->getIncludedRelationship($person, 'roles');

    foreach ($roles as $role) {
      if ($role['attributes']['name'] == 'member' && $role['relationships']['resource']['data'] == $branch_resource_id) {
        $has_branch_member_role = true;
        break;
      }
    }
    
    return $has_branch_member_role;
  });
}

foreach ($matching_people as $person) {
  echo "Person Name: " . $person['attributes']['full_name'] . PHP_EOL;
}
