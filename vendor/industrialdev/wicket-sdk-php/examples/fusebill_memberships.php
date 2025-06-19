<?php

require_once "vendor/autoload.php";
require_once 'example_helpers.php';

/*
  The following snippet shows an examples of using the Wicket API to generate a basic membership tier selection wizard for use with the Wicket Javascript widget (https://wicket-core.s3.ca-central-1.amazonaws.com/wicket-widgets-readme.html#createsubscriptiononboarding)

  The sample uses both the membership endpoints and Fusebill plan detail endpoints to find all the current active / billable memberships in Wicket.

  Note: Fusebill must be setup and enabled before using this sample, Wicket support (http://support.wicket.io/) can provide you with the necessary details.

*/

function wicket_fetch_fusebill_plans($client, $fusebill_service_id) {
  $fusebill_plan_response = $client->get(
    "/fusebill/$fusebill_service_id/plans", 
    [
      'query' => []
    ]
  );
  
  $fusebill_plans_by_id = [];
  
  foreach($fusebill_plan_response['data'] as $plan) {
    $fusebill_plans_by_id[$plan['id']] = $plan;
  }

  return $fusebill_plans_by_id;
}

function wicket_fetch_billable_memberships($client) {
  $memberships_response = $client->get(
    '/memberships', 
    [
      'query' => [
        'include' => 'fusebill_products',
        'filter' => [
          'active_eq' => true
        ]
      ]
    ]
  );

  return new \Wicket\ResponseHelper($memberships_response);
}

function wicket_generate_billing_product_mappings($memberships_response, $fusebill_plans_by_id, $plan_interval = 'Yearly') {
  $billing_product_mapping = [];

  foreach ($memberships_response->data as $membership) {
    $fusebill_products = array_filter((array)$memberships_response->getIncludedRelationship($membership, 'fusebill_products'));

    // Only include memberships where it has been linked to a fusebill product / plan.
    if (!empty($fusebill_products)) {
      $product = $fusebill_products[0];
      $fusebill_plan = $fusebill_plans_by_id[$product['attributes']['plan_id']] ?? null;
      
      if (!$fusebill_plan) continue;

      $yearly_plan_frequency_id = null;

      // Limit mappings to just a single interval, most clients are using Yearly subscriptions
      foreach($fusebill_plan['attributes']['plan_frequencies'] as $plan_frequency) {
        if ($plan_frequency['interval'] == $plan_interval and $plan_frequency['status'] == 'Active') {
          $yearly_plan_frequency_id = $plan_frequency['id'];
          break;
        }
      }
      
      if (!$yearly_plan_frequency_id) continue;

      // Track fields by membership slug which are needed to initialize the widget.
      $billing_product_mapping[$membership['attributes']['slug']] = [
        'membership' => $membership,
        'billing_product' => $product,
        'yearly_plan_frequency_id' => $yearly_plan_frequency_id 
      ];
    }
  }

  return $billing_product_mapping;
}


// Example specific configuration, these details will changed based on the environment
$fusebill_service_id = '';
$current_person_uuid = '';
$wicket_admin_root = 'https://<tenant-admin-url>';

$client = wicket_api_client();

// Step 1: Fetch raw fusebill plan data for the main fusebill service id.
$fusebill_plans_by_id = wicket_fetch_fusebill_plans($client, $fusebill_service_id);

// Step 2: Fetch all wicket memberships and include linked fusebill products
$billable_memberships = wicket_fetch_billable_memberships($client);

// Step 3: Merge the fusebill plans + memberships to simplify the mapping logic for widget
$billing_product_mappings = wicket_generate_billing_product_mappings($billable_memberships, $fusebill_plans_by_id);

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Wicket <> Fusebill widget example</title>
  <link href="https://unpkg.com/tailwindcss@^2/dist/tailwind.min.css" rel="stylesheet">
  <style>
    .intro a {
      text-decoration: underline;
    }
  </style>
</head>
<body class="bg-gray-50 font-sans p-4">
  <? if (!empty($_GET['membership']) && array_key_exists($_GET['membership'], $billing_product_mappings)): ?>
    <script type="text/javascript">
      window.Wicket=function(doc,tag,id,script){
        var w=window.Wicket||{};if(doc.getElementById(id))return w;var ref=doc.getElementsByTagName(tag)[0];var js=doc.createElement(tag);js.id=id;js.src=script;ref.parentNode.insertBefore(js,ref);w._q=[];w.ready=function(f){w._q.push(f)};return w
      }(document,"script","wicket-widgets","<?= $wicket_admin_root ?>/dist/widgets.js");  
    </script>
    <div id="wicket-root" class="border bg-white w-1/2 p-4 mx-auto"></div>
    <?php
      // Authorize API client as the current user:
      $client->authorize($current_person_uuid);

      // Fetch selected membership for use with widget initialize:
      $membership_data = $billing_product_mappings[$_GET['membership']];
    ?>
    <script>
      Wicket.ready(function () {
        var widgetRoot = document.getElementById('wicket-root');

        Wicket.widgets.createSubscriptionOnboarding({
          apiRoot: <?= json_encode($client->getApiEndpoint()) ?>,
          accessToken: <?= json_encode($client->getAccessToken()) ?>,
          personId: <?= json_encode($current_person_uuid) ?>,
          lang: "en",
          billingProduct: {
            type: <?= json_encode($membership_data['billing_product']['type']) ?>,
            id: <?= json_encode($membership_data['billing_product']['id']) ?>
          },
          fusebillPlanFrequencyId: <?= json_encode($membership_data['yearly_plan_frequency_id']) ?>,
          rootEl: widgetRoot
        }).then(function (widget) {
          widget.listen(widget.eventTypes.STEP_CHANGED, function (payload) {
            // console.log(`Step changed from ${payload.from} to ${payload.to}`);
          });

          widget.listenOnce(widget.eventTypes.COMPLETED, function (payload) {
            // completedSubscriptionId is a JSON API type / id which can be used with the wicket API
            var completedSubscriptionId = payload.completedSubscriptionId;
            // window.location = "/account-centre/order-thank-you?subscription_id=" + completedSubscriptionId.id;
            // To render a thank you message client side, the widget can be fully cleared from the page by using:
            // widget.clear();
          });
        });
      });
    </script>
  <? elseif (!empty($_GET['membership'])): ?>
    <div class="border bg-white w-1/2 p-4 mx-auto">
      Invalid membership
    </div>
  <? else: ?>
    <div class="border bg-white w-1/2 p-4 mx-auto intro">
      <h1 class="text-lg font-semibold mb-4">Select a Membership Tier</h1>
      <ul>
        <?php
          foreach($billing_product_mappings as $slug => $data) {
            $query = http_build_query(['membership' => $slug]);
            echo '<li><a href="/fusebill_memberships.php?'. $query .'">' .  $data['membership']['attributes']['name_en'] . '</a></li>';
          }
        ?>
      </ul>
    </div> 
  <? endif; ?>
</body>
</html>



