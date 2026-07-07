---
title: "Settings: WP Cassify (SSO)"
audience: [implementer, support]
wp_admin_path: "WP Cassify"
---

# Settings: WP Cassify (SSO)

Found under **Settings -> WP Cassify** in the WordPress admin sidebar.

WP Cassify is a free, third-party Single Sign-On plugin. It is documented here because it is almost always deployed alongside the Wicket Base plugin: it authenticates members against the Wicket CAS server and hands the Wicket person UUID to WordPress, which the Wicket Base plugin then uses to identify the member and sync their roles.

For a full walkthrough of installing and wiring everything together, see the guide [Set Up Wicket and SSO on a Self-Hosted WordPress](../guides/set-up-wicket-and-sso-on-self-hosted-wordpress.md).

> These are the recommended values for a Wicket integration. WP Cassify has many more options than listed here; the ones below are the settings that matter for Wicket.

---

## General Settings

### CAS Server base url

The base URL of the Wicket CAS server that authenticates your members. It differs by environment:

- Production: `https://[client]-login.wicketcloud.com/`
- Staging: `https://[client]-login.staging.wicketcloud.com/` (the same host with `.staging` inserted)

WP Cassify stores a single CAS URL, unlike **Wicket > Environments**, which holds both production and staging credentials and lets you toggle between them. If you switch the active Wicket environment, update this field to the matching CAS server as well, or SSO will authenticate against the wrong environment.

**Recommended value:** the Wicket CAS URL for the environment this site connects to, provided by Wicket.

### CAS Version protocol

The CAS protocol version to speak with the server.

**Recommended value:** `3`.

### Disable CAS Authentication

A master switch that turns CAS authentication off entirely. Leave off so SSO stays active.

**Recommended value:** unchecked.

### Enable URL bypass parameter

Allows an administrator to reach the standard WordPress login and skip SSO by adding a URL parameter (see [The bypass URL](#the-bypass-url) below). Off by default in WP Cassify; enable it so you always have an escape hatch to rescue or reconfigure the site.

**Recommended value:** checked.

### Bypass parameter name

The query parameter that triggers the SSO bypass.

**Recommended value:** `wp_cassify_bypass` (the default).

### Bypass parameter value

The value the bypass parameter must equal. Leaving the field blank uses the default value `bypass`.

**Recommended value:** blank (uses `bypass`).

### Create user if not exist

When on, WP Cassify creates a WordPress user the first time a member authenticates through CAS, using the extracted CAS user id as the username. Required so members do not need a pre-existing WordPress account.

**Recommended value:** checked.

### Log out on errors

When on, a CAS user session is silently disconnected on authentication errors with no message shown. Leave off during setup so errors are visible.

**Recommended value:** unchecked.

### Enable Gateway Mode

Enables auto-login (transparent authentication) support. Not needed for a standard Wicket setup.

**Recommended value:** unchecked.

### Enable SLO (Single Log Out)

When on, logging out of one site logs the member out centrally through CAS. Recommended so logout behaves consistently across Wicket-powered sites.

**Recommended value:** checked.

### Name of the service validate servlet

The CAS servlet WP Cassify calls to validate a service ticket. For CAS protocol 3 this is `p3/serviceValidate` (the plain default is `serviceValidate`).

**Recommended value:** `p3/serviceValidate` (paired with CAS Version protocol `3`).

## Attributes Extraction Settings

### Xpath query used to extract cas user id during parsing

The XPath WP Cassify uses to pull the user id out of the CAS validation response. This becomes the WordPress username.

**Recommended value:**

```
//cas:serviceResponse/cas:authenticationSuccess/cas:attributes/cas:personUuid
```

**Why override the default:** WP Cassify defaults to `//cas:serviceResponse/cas:authenticationSuccess/cas:user`, which reads the standard CAS principal. The Wicket CAS server instead returns the member's UUID in the `personUuid` attribute. The Wicket Base plugin identifies the logged-in member by treating the WordPress username as the Wicket person UUID, so this XPath must point at `personUuid`. With the default left in place, WordPress usernames are not Wicket UUIDs and the plugin's role/membership sync will not work.

---

## The bypass URL

With **Enable URL bypass parameter** on, an administrator can skip SSO and use the normal WordPress login:

```
https://your-site.example/wp-login.php?wp_cassify_bypass=bypass
```

Use this to rescue a site or to reconfigure SSO/Wicket settings when the CAS server is unavailable or misconfigured. Logging into a Wicket-powered site directly (without SSO) is otherwise discouraged.

---

## Related

- [Set Up Wicket and SSO on a Self-Hosted WordPress](../guides/set-up-wicket-and-sso-on-self-hosted-wordpress.md): the full install-and-configure playbook.
- [Settings: Integrations](settings-integrations.md): the Wicket Base plugin's WP Cassify role-sync options (Sync Security Roles, Sync Memberships as Roles, Sync Tags as Roles, Ignore Roles).
