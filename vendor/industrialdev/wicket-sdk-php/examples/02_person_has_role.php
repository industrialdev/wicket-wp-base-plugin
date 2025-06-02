<?php

require_once "vendor/autoload.php";
require_once 'example_helpers.php';

function wicket_person_has_organization_role($person_roles, $role_name, $role_resource_id) {
  $has_branch_member_role = false;

  foreach ($person_roles as $role) {
    if ($role['attributes']['name'] == $role_name && $role['relationships']['resource']['data'] == $role_resource_id) {
      $has_branch_member_role = true;
      break;
    }
  }

  return $has_branch_member_role;
}

$person_uuid = '98ebe775-0f80-4b7b-8dd8-394086c537e1';
$branch_organization_id = 'ed1e648f-5475-495b-af1d-6fb05fad35fe';

$client = wicket_api_client();
// Talk to the wicket API on behalf of the current user
$client->authorize($person_uuid);

try {
  $person_response = $client->get('/people/' . $person_uuid, [
    'query' => [
      'include' => 'emails,addresses,roles'
    ]
  ]);
} catch (\GuzzleHttp\Exception\ClientException $e) {
  $response_code = $e->getResponse()->getStatusCode();

  // $response_code can be be used to handle various error responses
  // for this example we will just handle errors as if we did not find the person.
  $person_response = ['data' => null];
}

$response_helper = new \Wicket\ResponseHelper($person_response);
$person_roles = (array)$response_helper->getIncludedRelationship($response_helper->data, 'roles');

if (wicket_person_has_organization_role($person_roles, 'member', ['type' => 'organizations', 'id' => $branch_organization_id])) {
  $full_name = $response_helper->data['attributes']['full_name'];
  echo "Person ${full_name} is a member of ${branch_organization_id}";
} else if (!empty($response_helper->data)) {
  $full_name = $response_helper->data['attributes']['full_name'];
  echo "Person ${full_name} is NOT a member of ${branch_organization_id}";
} else {
  echo "Person with id=${person_uuid} was not found";
}
