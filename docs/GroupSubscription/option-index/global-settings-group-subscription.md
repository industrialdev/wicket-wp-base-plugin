# Option Behavior: Wicket Settings > Integrations > WooCommerce — Group Subscription Options

These three options are the global controls for the entire Group Subscription feature. They live under **Wicket > Settings > Integrations > WooCommerce** and must be configured before any per-product or per-subscription behavior can function.

---

## Assign People to Groups on Product Purchase

**What it does:**

This is the master on/off switch for the entire feature. Every group-related action in the plugin checks this setting first. If it is off, the plugin does nothing — no group memberships are created, updated, or ended, and the Group Product Assignment tab does not appear on any product.

When it is turned on, the plugin activates four automated behaviors tied to WooCommerce subscription events:

- Creating a group membership when a subscription first becomes active
- Extending the membership end date when a subscription renews
- Updating the membership end date when the next payment date is changed
- Setting the membership end date to today when a subscription is cancelled

Turning this off after the feature has been in use does not remove or alter any existing group memberships in Wicket. It only stops future changes from being made automatically.

---

## Group Assignment Product Category

**What it does:**

This setting determines which WooCommerce product category is considered a "group product" category. Only products assigned to this category will:

- Show the **Group Product Assignment** tab on their product edit screen
- Trigger group membership creation or updates when their subscriptions change status

Products in any other category are completely ignored by the group subscription logic, even if they are subscription-type products. If this field is left empty, no products will show the tab and no group memberships will be managed, regardless of whether the master toggle above is enabled.

Changing this setting mid-use will immediately affect which products display the tab. Products previously in the old category will lose the tab; products in the new category will gain it.

---

## Group Role Entity Object Slug

**What it does:**

This setting controls which type of entity in Wicket is queried to populate the **Role Assigned** dropdown on the product edit screen. The plugin calls the Wicket API, fetches all entity types, and finds the one whose code matches this slug. It then uses that entity type's UUID to retrieve the list of available group role resource types.

The default value is `group-members` and will work for the vast majority of Wicket environments. If the slug entered here does not match any entity type in Wicket, the Role Assigned dropdown will not appear on the product tab. Instead, a red error message is shown on the product edit screen with a link back to this settings page, telling the editor to correct the value before using the feature.
