---
title: "Settings — Integrations"
audience: [implementer, support]
wp_admin_path: "Wicket → Integrations"
php_class: Wicket_Settings
db_option_prefix: wicket_admin_settings_woo_, wicket_admin_settings_wpcassify_, wicket_admin_settings_mailtrap_
---

# Wicket Settings — Integrations

Found under **Wicket → Integrations** in the WordPress admin.

This tab controls how the Wicket plugin interacts with other plugins installed on the site — primarily WooCommerce, WP Cassify, and Mailtrap.

---

## WooCommerce

### Sync Checkout Addresses

When enabled, the billing and shipping address fields in the WooCommerce checkout are automatically pre-filled with the address stored on the customer's Wicket person record. This saves the customer from having to re-enter their address on every purchase.

| | |
|---|---|
| Option key | `wicket_admin_settings_woo_sync_addresses` |
| PHP access | `get_option('wicket_admin_settings_woo_sync_addresses')` |
| Default | `Off` |

### Remove Product Links from Cart & Checkout

When enabled, product names in the cart and checkout pages are displayed as plain text rather than clickable links. This prevents customers from navigating away from the checkout flow.

| | |
|---|---|
| Option key | `wicket_admin_settings_woo_remove_cart_links` |
| PHP access | `get_option('wicket_admin_settings_woo_remove_cart_links')` |
| Default | `Off` |

### Hide Membership Categories

When enabled, the product categories marked as Membership Categories (configured in the Memberships tab) are hidden from the WooCommerce shop and excluded from search results. Membership products can still be added to the cart through other means such as the onboarding flow.

| | |
|---|---|
| Option key | `wicket_admin_settings_woo_remove_membership_categories` |
| PHP access | `get_option('wicket_admin_settings_woo_remove_membership_categories')` |
| Default | `Off` |

### Hide Membership Product Single Pages

When enabled, visiting the individual product page of any product in a membership category will redirect the user to the homepage instead of showing the product. Products can still be added to the cart through other means such as the onboarding form.

| | |
|---|---|
| Option key | `wicket_admin_settings_woo_remove_membership_product_single` |
| PHP access | `get_option('wicket_admin_settings_woo_remove_membership_product_single')` |
| Default | `Off` |

### Remove Product Added to Cart Message

When a customer is sent directly to checkout (for example, after clicking a buy link), WooCommerce normally shows a notice saying "X has been added to your cart." This option hides that message so the checkout page appears clean without that extra notification.

| | |
|---|---|
| Option key | `wicket_admin_settings_woo_remove_added_to_cart_message` |
| PHP access | `get_option('wicket_admin_settings_woo_remove_added_to_cart_message')` |
| Default | `Off` |

### Block Customer Emails on Admin Updates

When enabled, customers do not receive automated WooCommerce email notifications when an admin makes changes to their order. Emails will only be sent when an admin intentionally triggers them manually. This is useful to prevent customers from receiving confusing or unwanted notifications during administrative operations.

| | |
|---|---|
| Option key | `wicket_admin_settings_woo_email_blocker_enabled` |
| PHP access | `get_option('wicket_admin_settings_woo_email_blocker_enabled')` |
| Default | `Off` |

### Allow Refund Emails from Admin

Works alongside the **Block Customer Emails** option above. When enabled, refund notification emails are still sent to customers even when other customer emails are blocked. This ensures customers are always informed when they receive a refund.

| | |
|---|---|
| Option key | `wicket_admin_settings_woo_email_blocker_allow_refund_emails` |
| PHP access | `get_option('wicket_admin_settings_woo_email_blocker_allow_refund_emails')` |
| Default | `Off` |

### Draft Order Retention (days)

Controls how long draft (checkout-draft) orders are kept before being automatically deleted. The default is 60 days, which overrides WooCommerce's built-in behaviour of deleting draft orders after just 1 day. Set to `0` to keep draft orders indefinitely and disable automatic deletion entirely.

| | |
|---|---|
| Option key | `wicket_admin_settings_woo_draft_order_retention_days` |
| PHP access | `get_option('wicket_admin_settings_woo_draft_order_retention_days')` |
| Default | `60` |

### Person to Organization Relationship Types

A multi-select that lets you identify which Wicket relationship types (connecting a person to an organization) are relevant for use on this site. One use case is associating a WooCommerce order with the organization the customer is purchasing on behalf of. This field only appears when the plugin can successfully connect to the Wicket API.

| | |
|---|---|
| Option key | `wicket_admin_settings_woo_person_to_org_types` |
| PHP access | `get_option('wicket_admin_settings_woo_person_to_org_types')` |
| Default | _(none)_ |

### Assign People to Groups on Product Purchase

When enabled, purchasing certain subscription products will automatically manage that customer's group memberships in Wicket based on their subscription dates. This automates the process of adding or removing people from Wicket groups when they buy or renew a subscription.

| | |
|---|---|
| Option key | `wicket_admin_settings_group_assignment_subscription_products` |
| PHP access | `get_option('wicket_admin_settings_group_assignment_subscription_products')` |
| Default | `Off` |

### Group Assignment Product Category

Select which WooCommerce product category identifies the products that have the group assignment behaviour described above. Products in this category will show additional group assignment settings on their product edit screen.

| | |
|---|---|
| Option key | `wicket_admin_settings_group_assignment_product_category` |
| PHP access | `get_option('wicket_admin_settings_group_assignment_product_category')` |
| Default | _(none)_ |

### Group Role Entity Object Slug

An internal slug used to fetch the available group role options from Wicket. The default value is `group-members` and should only be changed if you understand specifically why a different value is needed.

| | |
|---|---|
| Option key | `wicket_admin_settings_group_assignment_role_entity_object` |
| PHP access | `get_option('wicket_admin_settings_group_assignment_role_entity_object')` |
| Default | `group-members` |

---

## WP Cassify

These settings control how the plugin integrates with WP-Cassify, a Single Sign-On (SSO) plugin. They only take effect when WP-Cassify is installed and active.

### Sync Security Roles

When enabled, every time a user logs in to WordPress via WP-Cassify, the plugin retrieves any security roles assigned to that person in the Wicket MDP (found under their profile → Security → Roles) and applies them as WordPress user roles.

| | |
|---|---|
| Option key | `wicket_admin_settings_wpcassify_sync_roles` |
| PHP access | `get_option('wicket_admin_settings_wpcassify_sync_roles')` |
| Default | `Off` |

### Sync Memberships as Roles

Works with the setting above. When enabled, a user's active memberships in Wicket are also synced as WordPress roles on login. For example, if a user has a "Student" membership in Wicket, a "Student" WordPress role is created automatically (if it doesn't exist yet) and assigned to that user. Requires both WP-Cassify and the **Sync Security Roles** option to be enabled.

| | |
|---|---|
| Option key | `wicket_admin_settings_wpcassify_sync_memberships_as_roles` |
| PHP access | `get_option('wicket_admin_settings_wpcassify_sync_memberships_as_roles')` |
| Default | `Off` |

### Sync Tags as Roles

A text field where you can specify which Wicket person tags should be synced as WordPress roles on login. Enter the exact tag names as they appear in the MDP, separated by commas. Only the tags you list here will be brought across.

| | |
|---|---|
| Option key | `wicket_admin_settings_wpcassify_sync_tags_as_roles` |
| PHP access | `get_option('wicket_admin_settings_wpcassify_sync_tags_as_roles')` |
| Default | _(none)_ |

### Ignore Roles

A text field where you can list specific Wicket security roles that should be excluded from the sync. Enter role names exactly as they appear in the MDP, separated by commas. Note: this only applies to security roles from the MDP profile; it does not affect derived roles such as those synced from memberships or tags.

| | |
|---|---|
| Option key | `wicket_admin_settings_wpcassify_ignore_roles` |
| PHP access | `get_option('wicket_admin_settings_wpcassify_ignore_roles')` |
| Default | _(none)_ |

---

## Mailtrap

Mailtrap is an email testing service that captures outgoing emails instead of delivering them to real recipients. These settings route all WordPress emails through Mailtrap, which is useful for testing email flows on staging or local environments without accidentally emailing real users.

> **Important:** These settings only take effect when the Wicket Environment is set to **Staging**. Disable any SMTP plugin while using Mailtrap, otherwise that plugin's settings will take precedence.

### Host

The SMTP hostname for your Mailtrap inbox. Found in your Mailtrap inbox under **Show Credentials → SMTP → Host**.

| | |
|---|---|
| Option key | `wicket_admin_settings_mailtrap_host` |
| PHP access | `get_option('wicket_admin_settings_mailtrap_host')` |
| Default | _(none)_ |

### Port

The SMTP port for your Mailtrap inbox. This is typically `2525`.

| | |
|---|---|
| Option key | `wicket_admin_settings_mailtrap_port` |
| PHP access | `get_option('wicket_admin_settings_mailtrap_port')` |
| Default | `2525` |

### Username

The SMTP username for your Mailtrap inbox. Found in your Mailtrap inbox under **Show Credentials → SMTP → Username**.

| | |
|---|---|
| Option key | `wicket_admin_settings_mailtrap_username` |
| PHP access | `get_option('wicket_admin_settings_mailtrap_username')` |
| Default | _(none)_ |

### Password

The SMTP password for your Mailtrap inbox. Found in your Mailtrap inbox under **Show Credentials → SMTP → Password**.

| | |
|---|---|
| Option key | `wicket_admin_settings_mailtrap_password` |
| PHP access | `get_option('wicket_admin_settings_mailtrap_password')` |
| Default | _(none)_ |
