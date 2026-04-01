# Option Behavior: Product Edit — Group Product Assignment Tab Options

These two options appear on a WooCommerce subscription product's edit screen inside the **Group Product Assignment** tab. They are saved per-product and control exactly which group and role a customer is assigned to in Wicket when they purchase that product.

---

## Group Assigned

**What it does:**

Selecting a group here tells the plugin which Wicket group to place the purchasing customer into. When the customer's subscription becomes active, the plugin calls the Wicket API to create a group membership linking that person to the selected group, with a role as defined by the **Role Assigned** option below.

This selection is stored on the product and does not change automatically. If you reassign the group on the product after subscriptions have already been sold, existing subscribers will retain their membership in the originally assigned group. Only new subscriptions created after the change will point to the new group.

If no group is selected (left as "None"), the subscription will not trigger any group membership creation.

The dropdown lists all currently active groups from Wicket, fetched live at the time the product edit screen is loaded.

---

## Role Assigned

**What it does:**

Selecting a role here determines what role the customer will hold within the group in Wicket. Roles define the type of membership a person has — for example, whether they are a standard member, a manager, or another custom type configured in Wicket.

This selection works together with the **Group Assigned** option. Both must be set for the group membership to be created correctly. If no role is selected (left as "None"), the plugin will still attempt to create a group membership, but without a valid role the Wicket API may reject the request.

The dropdown is populated by querying Wicket for resource types that belong to the entity type identified by the **Group Role Entity Object Slug** setting in the plugin's global configuration. If that slug is misconfigured, this dropdown will not appear and an error message will be shown instead.

Like the **Group Assigned** field, changing this value on a product only affects future subscription purchases. Customers who already have an active subscription will keep the role they were originally assigned.
