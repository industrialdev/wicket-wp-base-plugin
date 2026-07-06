# WooCommerce Product Edit — Group Product Assignment Tab

This tab appears on a WooCommerce product's edit screen when:

1. The **Assign people to groups on product purchase** setting is enabled in Wicket Settings.
2. The product belongs to the category selected in the **Group Assignment Product Category** setting.
3. The product type is set to **Subscription** or **Variable Subscription** (the tab is hidden for all other product types).

When all three conditions are met, an editor will see a **Group Product Assignment** tab alongside the standard WooCommerce product data tabs (General, Inventory, etc.).

---

## Group Assigned

A dropdown listing all active groups from Wicket. Selecting a group here means that when a customer purchases this subscription product, they will be placed into that group as a member.

Only one group can be assigned per product.

---

## Role Assigned

A dropdown listing the available roles for the Wicket group entity type configured in the plugin settings. Selecting a role here determines what role the customer will hold within the group once their subscription is active.

If the **Group Role Entity Object Slug** set in the plugin settings does not match anything in Wicket, this dropdown will not appear — instead, a warning message is shown explaining that the slug needs to be corrected before the feature can be used.

---

## How Subscription Lifecycle Events Affect Group Membership

The tab also displays informational text explaining what happens to a member's group status as their subscription changes:

- **When a subscription is purchased and becomes active** — A group membership is created in Wicket for the buyer, using the start date of the subscription and the next payment date as the membership end date.
- **When a subscription renews** — The group membership's end date is extended to the new next payment date.
- **When the next payment date is changed manually** — The group membership end date is updated to match the new date.
- **When a subscription is cancelled** — The group membership end date is set to the current date, effectively ending the membership immediately.
