<?php

require_once "vendor/autoload.php";
require_once 'example_helpers.php';

$client = wicket_api_client();

$request_options = [ 
  'query' => [
    'filter' => [
      // data_fields are customized per client, the following is an example
      // filtering by a true / false value stored in the custom fields
      // These values can be found in wicket admin under "Person -> Additional Info"
      'data_fields.member-directory.value.listed' => 'true'
    ],
    'page' => [
      'size' => 25
    ]
  ]
];

$pager = new \Wicket\ResponsePager(
  $client,
  $client->get('/search/people', $request_options)
);

while ($pager->hasData()) {
  foreach ($pager->responseHelper->data as $search_result) {
    echo "Person Name: " . $search_result['attributes']['person']['full_name'] . PHP_EOL;

    // NOTE: When using coordinates these must be displayed only on a google map
    // and used according to "Google Maps Platform Terms of Service"
    echo "Work Coordinates: " . json_encode($search_result['attributes']['person']['location']) . PHP_EOL;

    $listed_in_directory = (bool)$search_result['attributes']['person']['data_fields']['member-directory']['value']['listed'];
    echo "Listed in member directory: " . json_encode($listed_in_directory) . PHP_EOL;
    echo PHP_EOL;
  }

  $pager->fetchNextPage();
}
