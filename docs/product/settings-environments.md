---
title: "Settings — Environments"
audience: [implementer, support]
wp_admin_path: "Wicket → Environments"
php_class: Wicket_Settings
db_option_prefix: wicket_admin_settings_prod_, wicket_admin_settings_stage_, wicket_admin_settings_environment
---

# Wicket Settings — Environments

Found under **Wicket → Environments** in the WordPress admin.

This tab is where you connect the plugin to your Wicket Member Data Platform (MDP). You configure separate credentials for staging and production, then choose which one is active at any given time.

---

## Connect to Wicket Environments

### Wicket Environment

A toggle that determines which set of credentials the plugin uses when talking to Wicket — **Staging** or **Production**. Only one environment is active at a time. Switch this when you want to test against staging or when you're ready to go live.

---

## Wicket Production

Credentials and addresses for your live Wicket environment.

### API Endpoint

The full URL of the Wicket API for your production instance. It follows the format `https://[client]-api.wicketcloud.com`. This is where all data requests are sent when the Production environment is active.

### JWT Secret Key

A secret key issued by Wicket that is used to authenticate requests from WordPress to the Wicket API. This value should be kept private and not shared.

### Person ID

A specific Person ID from Wicket used by the plugin to perform background operations (such as API authentication on behalf of the site). This is provided by Wicket.

### Parent Org

The **alternate name** of the top-level organization in your Wicket account, found under Organizations in the Wicket admin. This value is used when creating new people through the create account form — it tells Wicket which organization to associate new sign-ups with.

### Wicket Admin

The URL for your production Wicket admin interface, following the format `https://[client]-admin.wicketcloud.com`. This is used for building direct links to records (such as order touchpoints) within the Wicket admin.

---

## Wicket Staging

Identical settings to Production, but for the staging environment. These credentials are only used when the **Wicket Environment** toggle above is set to **Staging**.

### API Endpoint

The full URL of the Wicket API for your staging instance. Format: `https://[client]-api.staging.wicketcloud.com`.

### JWT Secret Key

The secret key for staging API authentication. Kept private and separate from production.

### Person ID

The Person ID used for staging background API operations.

### Parent Org

The alternate name of the top-level organization in your staging Wicket account, used when creating new users on staging.

### Wicket Admin

The URL for the staging Wicket admin interface. Format: `https://[client]-admin.staging.wicketcloud.com`.
