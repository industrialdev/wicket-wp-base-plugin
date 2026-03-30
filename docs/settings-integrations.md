# Wicket Settings — Integrations

Found under **Wicket → Integrations** in the WordPress admin.

This tab controls how the Wicket plugin interacts with other plugins installed on the site — primarily WooCommerce, WP Cassify, and Mailtrap.

---

## WooCommerce

### Sync Checkout Addresses

When enabled, the billing and shipping address fields in the WooCommerce checkout are automatically pre-filled with the address stored on the customer's Wicket person record. This saves the customer from having to re-enter their address on every purchase.

### Remove Product Links from Cart & Checkout

When enabled, product names in the cart and checkout pages are displayed as plain text rather than clickable links. This prevents customers from navigating away from the checkout flow.

### Hide Membership Categories

When enabled, the product categories marked as Membership Categories (configured in the Memberships tab) are hidden from the WooCommerce shop and excluded from search results. Membership products can still be added to the cart through other means such as the onboarding flow.

### Hide Membership Product Single Pages

When enabled, visiting the individual product page of any product in a membership category will redirect the user to the homepage instead of showing the product. Products can still be added to the cart through other means such as the onboarding form.

### Remove Product Added to Cart Message

When a customer is sent directly to checkout (for example, after clicking a buy link), WooCommerce normally shows a notice saying "X has been added to your cart." This option hides that message so the checkout page appears clean without that extra notification.

### Block Customer Emails on Admin Updates

When enabled, customers do not receive automated WooCommerce email notifications when an admin makes changes to their order. Emails will only be sent when an admin intentionally triggers them manually. This is useful to prevent customers from receiving confusing or unwanted notifications during administrative operations.

### Allow Refund Emails from Admin

Works alongside the **Block Customer Emails** option above. When enabled, refund notification emails are still sent to customers even when other customer emails are blocked. This ensures customers are always informed when they receive a refund.

### Draft Order Retention (days)

Controls how long draft (checkout-draft) orders are kept before being automatically deleted. The default is 60 days, which overrides WooCommerce's built-in behaviour of deleting draft orders after just 1 day. Set to `0` to keep draft orders indefinitely and disable automatic deletion entirely.

### Person to Organization Relationship Types

A multi-select that lets you identify which Wicket relationship types (connecting a person to an organization) are relevant for use on this site. One use case is associating a WooCommerce order with the organization the customer is purchasing on behalf of. This field only appears when the plugin can successfully connect to the Wicket API.

### Assign People to Groups on Product Purchase

When enabled, purchasing certain subscription products will automatically manage that customer's group memberships in Wicket based on their subscription dates. This automates the process of adding or removing people from Wicket groups when they buy or renew a subscription.

### Group Assignment Product Category

Select which WooCommerce product category identifies the products that have the group assignment behaviour described above. Products in this category will show additional group assignment settings on their product edit screen.

### Group Role Entity Object Slug

An internal slug used to fetch the available group role options from Wicket. The default value is `group-members` and should only be changed if you understand specifically why a different value is needed.

---

## WP Cassify

These settings control how the plugin integrates with WP-Cassify, a Single Sign-On (SSO) plugin. They only take effect when WP-Cassify is installed and active.

### Sync Security Roles

When enabled, every time a user logs in to WordPress via WP-Cassify, the plugin retrieves any security roles assigned to that person in the Wicket MDP (found under their profile → Security → Roles) and applies them as WordPress user roles.

### Sync Memberships as Roles

Works with the setting above. When enabled, a user's active memberships in Wicket are also synced as WordPress roles on login. For example, if a user has a "Student" membership in Wicket, a "Student" WordPress role is created automatically (if it doesn't exist yet) and assigned to that user. Requires both WP-Cassify and the **Sync Security Roles** option to be enabled.

### Sync Tags as Roles

A text field where you can specify which Wicket person tags should be synced as WordPress roles on login. Enter the exact tag names as they appear in the MDP, separated by commas. Only the tags you list here will be brought across.

### Ignore Roles

A text field where you can list specific Wicket security roles that should be excluded from the sync. Enter role names exactly as they appear in the MDP, separated by commas. Note: this only applies to security roles from the MDP profile; it does not affect derived roles such as those synced from memberships or tags.

---

## Mailtrap

Mailtrap is an email testing service that captures outgoing emails instead of delivering them to real recipients. These settings route all WordPress emails through Mailtrap, which is useful for testing email flows on staging or local environments without accidentally emailing real users.

> **Important:** These settings only take effect when the Wicket Environment is set to **Staging**. Disable any SMTP plugin while using Mailtrap, otherwise that plugin's settings will take precedence.

### Host

The SMTP hostname for your Mailtrap inbox. Found in your Mailtrap inbox under **Show Credentials → SMTP → Host**.

### Port

The SMTP port for your Mailtrap inbox. This is typically `2525`.

### Username

The SMTP username for your Mailtrap inbox. Found in your Mailtrap inbox under **Show Credentials → SMTP → Username**.

### Password

The SMTP password for your Mailtrap inbox. Found in your Mailtrap inbox under **Show Credentials → SMTP → Password**.
