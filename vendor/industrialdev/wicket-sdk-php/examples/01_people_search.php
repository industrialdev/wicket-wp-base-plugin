<?php

require_once "vendor/autoload.php";
require_once 'example_helpers.php';

$client = wicket_api_client();

$current_page = 1;
$request_options = [ 
  'query' => [
    'filter' => [
      // data_fields are customized per client, the following is an example
      // filtering by a true / false value stored in the custom fields
      // These values can be found in wicket admin under "Person -> Additional Info"
      'data_fields.member-directory.value.listed' => 'true'
    ],
    'page' => [
      'number' => $current_page,
      'size' => 25
    ]
  ]
];

// NOTE: Only coordinates for addresses of type "work" are included in the search results.
// Get single page of search results
$response = $client->get('/search/people', $request_options);
$page_meta = $response['meta']['page'];

echo "Total Results: " . $page_meta['total_items'] . PHP_EOL;
echo "Total Pages: " . $page_meta['total_pages'] . PHP_EOL;
echo "Results for page " . $page_meta['number'] . PHP_EOL;

foreach ((array)$response['data'] as $search_hit) {
  echo "  " . $search_hit['attributes']['person']['full_name'] . PHP_EOL;
}

