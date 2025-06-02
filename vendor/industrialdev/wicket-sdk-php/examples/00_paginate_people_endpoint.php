<?php

require_once "vendor/autoload.php";
require_once 'example_helpers.php';

$client = wicket_api_client();

$request_options = [ 
  'query' => [
    'include' => 'addresses',
    'filter' => [
    ],
    'page' => [
      'number' => 1,
      'size' => 50,
    ],
    'fields' => [
      // Improve performance of the requests by fetching only the fields you need.
      // these fields can be specified by resource type.
      'people' => 'given_name,family_name,full_name,data_fields,addresses',
      'addresses' => 'type,latitude,longitude'
    ]
  ]
];

$pager = new \Wicket\ResponsePager(
  $client,
  $client->get('/people', $request_options)
);

while ($pager->hasData()) {
  $response_helper = $pager->responseHelper;

  foreach ($response_helper->data as $person) {
    $work_coordinates = [];
    $address_resources = (array)$response_helper->getIncludedRelationship($person, 'addresses');

    foreach ($address_resources as $address) {
      $has_location = $address['attributes']['latitude'] && $address['attributes']['longitude'];

      if ($address['attributes']['type'] == 'work' && $has_location) {
        $work_coordinates[] = [
          'lat' => $address['attributes']['latitude'], 
          'lon' => $address['attributes']['longitude']
        ];
      }
    }

    $listed_in_directory = false;

    foreach ($person['attributes']['data_fields'] as $data_field) {
      if ($data_field['key'] == 'member-directory') {
        $listed_in_directory = !empty($data_field['value']) && $data_field['value']['listed'] == true;
      }
    }

    echo "Person Name: " . $person['attributes']['full_name'] . PHP_EOL;

    // NOTE: When using coordinates these must be displayed only on a google map
    // and used according to "Google Maps Platform Terms of Service"
    echo "Work Coordinates: " . json_encode($work_coordinates) . PHP_EOL;      
    echo "Listed in member directory: " . json_encode($listed_in_directory) . PHP_EOL;
    echo PHP_EOL;
  }

  $pager->fetchNextPage();
}