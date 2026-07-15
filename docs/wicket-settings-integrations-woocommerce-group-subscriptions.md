# Wicket Settings > Integrations > WooCommerce — Group Subscription Settings

This section lives inside the main Wicket plugin settings page under **Wicket > Settings > Integrations > WooCommerce**. It contains the global on/off controls and configuration needed for the Group Subscription feature to function across the site.

---

## Assign people to groups on product purchase

A checkbox that turns the entire Group Subscription feature on or off.

When enabled, the plugin will watch for subscription product purchases and automatically create or update the buyer's group membership inside Wicket based on the subscription's dates. When disabled, none of the group-related behaviour described below takes effect and the Group Product Assignment tab will not appear on any product.

---

## Group Assignment Product Category

A dropdown that lets you pick one WooCommerce product category. Only products that belong to this category will show the **Group Product Assignment** tab on their edit screen, and only purchases of those products will trigger group membership changes in Wicket.

This acts as a filter so that only the products you intentionally set up for group management are affected — regular products in other categories are left alone.

---

## Group Role Entity Object Slug

A text field that tells the plugin which type of entity in Wicket to use when fetching the list of available group roles. The default value is `group-members` and should be left as-is unless there is a specific reason to change it (for example, a custom Wicket environment that uses a different entity code).

If the slug entered here does not match a known entity type in Wicket, an error message will appear on the product edit screen instead of the role dropdown, prompting you to correct the value.
