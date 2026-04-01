The group subscription feature offers:
- the ability to purchase a product that provides the user with group membership in the MDP
- the dates of that membrship are managed by the woocommerce subscription dates

The group subscription feature is supported by the following code:
- the group subsciption functionality is controlled by the code found in includes/integrations/group-subscriptions.php
- it injects a new woocommerce products page tab for individual subscription product configuration options 
- it provides specialized methods for this functionality 
- it makes api requests to the MDP using the sdk client 
- it uses helper methods found in includes/helpers/helper-groups.php
- it has its main global configuration options in the main wicket base-plugin menu's integrations tab.

Phase 1: ✅ COMPLETE
Investigate the Group Subscriptions plugin interface
- use the directives in docs/GroupSubscription/AgentPrompts/DocAgent.md  as a priority
- describe each page or tab or section for functionality related to this feature.
- create your result files in docs/GroupSubscription/pages-index folder 

Results:
- docs/GroupSubscription/pages-index/wicket-settings-integrations-woocommerce-group-subscriptions.md
  Documents the 3 global settings under Wicket > Settings > Integrations > WooCommerce that control the feature.
- docs/GroupSubscription/pages-index/product-group-product-assignment-tab.md
  Documents the per-product "Group Product Assignment" tab that appears on subscription products in the configured category, including its two dropdowns and the subscription lifecycle behaviour summary.

Phase 2: ✅ COMPLETE
Investigate the Group Subscriptions plugin option behavior and functionality
- use the directives in docs/GroupSubscription/AgentPrompts/DocAgent.md  as a priority
- read and understand the code to guide responses to the directives in this phase.
- analyse each option and describe the functionality provided by it.
- describe specifically what manually or automatically chaging the subscription dates controls
- create your result files in docs/GroupSubscription/option-index folder 

Results:
- docs/GroupSubscription/option-index/global-settings-group-subscription.md
  Describes the functional behavior of the three global settings (master toggle, product category filter, role entity slug) — what each one actually does when set or changed.
- docs/GroupSubscription/option-index/product-tab-group-assignment-options.md
  Describes the functional behavior of the Group Assigned and Role Assigned per-product dropdowns — how they drive membership creation and what happens if values are changed after subscriptions are sold.
- docs/GroupSubscription/option-index/subscription-date-behavior.md
  Describes all four subscription lifecycle events in detail: activation (membership creation), renewal (automatic end date extension), manual next-payment-date change (immediate Wicket update + recovery path), and cancellation (immediate membership end).

Phase 3: ✅ COMPLETE
Put this in  a file in docs/GroupSubscription/troubleshooting-index with the original question and the answer.
Using the information in docs/option-index answer the following question.
Q) My group membership did not get extended when the subscription renewed.

Results:
- docs/GroupSubscription/troubleshooting-index/group-membership-not-extended-on-renewal.md
  Answers the renewal question with 5 ordered checks: master toggle, product category membership, whether the original membership was ever created (with manual recovery steps), WooCommerce log inspection, and confirming the renewal payment actually completed.