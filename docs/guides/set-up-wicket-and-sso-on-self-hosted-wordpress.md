---
title: "Set Up Wicket and SSO on a Self-Hosted WordPress"
audience: [implementer]
related: ../product/settings-wp-cassify.md, ../product/settings-environments.md, ../product/settings-integrations.md, ../product/settings-general.md
---

# Set Up Wicket and SSO on a Self-Hosted WordPress

This is a start-to-finish playbook for an IT team standing up the **Wicket Base** plugin and **WP Cassify** (Single Sign-On) on their own self-hosted WordPress site. It covers installing both plugins, connecting to Wicket, wiring up SSO, verifying it works, and keeping it up to date.

> This guide is written for a technical audience (a client IT team or implementer). Unlike the other guides in this folder, it includes command-line and Composer steps where they are required.


## Overview

Two plugins work together:

- **Wicket Base plugin** connects WordPress to the Wicket Member Data Platform (MDP). It authenticates to the Wicket API and provides the account, membership, and role features.
- **WP Cassify** is a free, third-party Single Sign-On plugin. It logs users into WordPress against the Wicket CAS server so members use one set of credentials across your Wicket-powered sites.

How a login flows once both are configured:

```
User clicks Log In (or opens a page that requires login)
      -> WP Cassify redirects to the Wicket CAS server to authenticate
      -> CAS returns the user's Wicket person UUID
      -> WP Cassify creates/finds the WordPress user (username = person UUID)
      -> Wicket Base plugin syncs the person's Wicket roles/memberships
         into WordPress roles (only if you enable role sync; see Step 4)
```

The critical link between the two plugins: **the WordPress username must equal the Wicket person UUID.** Step 3 explains how to make WP Cassify deliver that.



## Before You Start

**Environment requirements**

- WordPress **6.6** or newer
- PHP **8.2** or newer
- **Advanced Custom Fields (ACF)** installed and active. The free version from the WordPress.org plugin repository is sufficient (ACF Pro also satisfies the requirement if you already use it). The Wicket Base plugin declares ACF as a required plugin, so WordPress will not let it activate until ACF is installed and active. ACF powers the Wicket blocks and widgets.
- Administrator access to the WordPress site (and, for the Composer install option, shell access to the server)

**Credentials to request from Wicket**

Ask your Wicket contact for the following, for **both** your production and staging environments:

- API Endpoint (e.g. `https://[client]-api.wicketcloud.com`)
- JWT Secret Key
- Person ID (the service account UUID used for background API calls)
- Parent Org (the "alternate name" of your top-level organization in Wicket)
- Wicket Admin URL (e.g. `https://[client]-admin.wicketcloud.com`)
- CAS Server base URL (e.g. `https://[client]-login.wicketcloud.com/`)

The examples above are production URLs. The staging equivalents use the same hostnames with `.staging` inserted before `wicketcloud.com` (for example `https://[client]-api.staging.wicketcloud.com` and `https://[client]-login.staging.wicketcloud.com/`).

Keep the JWT Secret Keys private.

**Whitelist your site domains with Wicket**

The Wicket CAS server only accepts logins from site URLs it recognizes. Give the Wicket team the full hostname of **every** environment that will use SSO and have them whitelist each one before you test. A domain that is not whitelisted cannot complete SSO login, so request this early; a new or changed hostname needs its own entry. For example:

- `https://mysite.com` (production)
- `https://mysite.staging.com` (staging)
- `https://mydevsite.staging.com` (an additional staging or dev site)


## Step 1: Install the Prerequisites

1. Install and activate **Advanced Custom Fields (ACF)**. The free version is enough: install it from **Plugins > Add New** by searching for "Advanced Custom Fields". If you already license ACF Pro, that satisfies the requirement too (upload it via **Plugins > Add New > Upload Plugin**).
2. Install and activate **WP Cassify**. It is free on the WordPress.org repository, so you can install it directly from **Plugins > Add New** by searching for "WP Cassify". Leave it configured later in Step 3.



## Step 2: Install the Wicket Base Plugin

The Wicket Base plugin is **not** on the WordPress.org repository, so you install it from its GitHub repository: [industrialdev/wicket-wp-base-plugin](https://github.com/industrialdev/wicket-wp-base-plugin). The repository **commits its Composer `vendor/` folder** (including the `wicket-sdk-php` package), so a downloaded copy runs as-is with no build step. Choose one of the options below.

### Option A: Download the ZIP from GitHub (recommended)

1. Open the [repository](https://github.com/industrialdev/wicket-wp-base-plugin). The `main` branch is always tagged and kept up to date, and includes the `vendor/` folder.
2. Click **Code > Download ZIP** to get the latest, or download a specific tag from the **Tags** page if you need to pin a version.
3. In WordPress, go to **Plugins > Add New > Upload Plugin**, choose the ZIP, and click **Install Now**.
4. Activate **Wicket Base**.

Because `vendor/` is committed, this requires no Composer or command-line tooling on your server.

> GitHub wraps the files in a top-level folder such as `wicket-wp-base-plugin-main/`. WordPress handles that fine on upload. If you ever need the folder named exactly `wicket-wp-base-plugin` (for example when copying it in by hand), rename it after extracting.

### Option B: Deployer for Git for ongoing updates

[Deployer for Git](https://wordpress.org/plugins/deployer-for-git/) is a free plugin on the WordPress.org repository that deploys a plugin or theme straight from its Git repository into your site. There is nothing to download and re-upload by hand, and no command line. Because the Wicket repository already commits its `vendor/` folder, the deployed copy runs with no build step.

1. In **Plugins > Add New**, search for "Deployer for Git", then install and activate it.
2. In the Deployer for Git settings, add a deployment for the repository `https://github.com/industrialdev/wicket-wp-base-plugin` and choose the branch to track (`main`). The repository is public, so the free version is enough; the paid tier is only for private repositories.
3. To update later, open the Deployer for Git screen and run the deployment for Wicket Base. It pulls the latest code from the tracked branch on demand, so the site owner updates on their own schedule. This guide does not set up webhooks or automatic deployment; updating is a manual action.

> **Important:** Deployer for Git tracks a **branch** and deploys its latest commit; it does not pin to a tagged release. Pointing it at `main` deploys every change merged to `main`, which may be ahead of the latest tagged version. For a controlled, specific version, use Option A and download that tag's ZIP. Also confirm your host allows writes to the plugin directory, as some locked-down managed hosts do not.

### Option C: Require it via Composer (Bedrock and similar)

If your site is managed with Composer, for example a Bedrock project where plugins are dependencies in the site's root `composer.json` and installed into `web/app/plugins/`, require the plugin as a package rather than downloading or cloning it. This is also the cleanest way to lock an exact version.

The plugin is not on Packagist, and its `composer.json` requires the Wicket PHP SDK (`industrialdev/wicket-sdk-php`), which is also Git-hosted. You do **not** re-declare the SDK in your own `require`: the plugin pulls it in transitively, so listing it again would be redundant. What you do have to supply is **where** Composer can find it. Repository definitions are not inherited from dependencies (Composer's docs: "the repositories defined in your dependencies will not be loaded"), so the SDK repository the plugin declares in its own `composer.json` is ignored once the plugin is consumed as a dependency. List both VCS repositories in your **root** `composer.json`, or `composer require` knows it needs `industrialdev/wicket-sdk-php` but cannot locate it and fails. (The plugin's committed `vendor/` covers the SDK at runtime, which is why Options A and B need no Composer, but it has no effect on Composer's resolution here.)

1. In your site's root `composer.json`, add both VCS repositories:
   ```json
   "repositories": [
     { "type": "vcs", "url": "https://github.com/industrialdev/wicket-wp-base-plugin" },
     { "type": "vcs", "url": "https://github.com/industrialdev/wicket-sdk-php" }
   ]
   ```
2. Require the plugin, pinning to the version you want:
   ```bash
   composer require industrialdev/wicket-wp-base-plugin:^2.4
   ```
   Use an exact tag such as `2.4.10` to lock a specific release, or a range such as `^2.4` to allow updates within a major version.
3. Because the package `type` is `wordpress-plugin`, `composer/installers` (already present in Bedrock) installs it into the plugins directory (`web/app/plugins/` on Bedrock, `wp-content/plugins/` elsewhere), and Composer resolves the Wicket SDK and other dependencies into your project. There is no manual build or clone step.
4. Activate **Wicket Base** in WordPress.


## Step 3: Configure WP Cassify (SSO)

Go to **Settings -> WP Cassify** in the WordPress admin sidebar. See [Settings: WP Cassify](../product/settings-wp-cassify.md) for a full field reference. The recommended values are:

**General Settings**

- **CAS Server base url**: your Wicket CAS URL for the environment this site connects to. Production uses `https://[client]-login.wicketcloud.com/`; staging uses `https://[client]-login.staging.wicketcloud.com/`. This must match the environment selected under **Wicket > Environments**; if you later switch environments, update this field too.
- **CAS Version protocol**: `3`
- **Disable CAS Authentication**: unchecked
- **Enable URL bypass parameter**: checked (see the bypass note below)
- **Bypass parameter name**: `wp_cassify_bypass`
- **Bypass parameter value**: leave blank to use the default (`bypass`)
- **Create user if not exist**: checked (so members get a WordPress account on first login)
- **Log out on errors**: unchecked
- **Enable Gateway Mode**: unchecked
- **Enable SLO (Single Log Out)**: checked
- **Name of the service validate servlet**: `p3/serviceValidate` (matches CAS protocol 3)

**Attributes Extraction Settings**

- **Xpath query used to extract cas user id during parsing**:
  ```
  //cas:serviceResponse/cas:authenticationSuccess/cas:attributes/cas:personUuid
  ```

  **Why this matters:** WP Cassify defaults to reading the standard `<cas:user>` value, but the Wicket CAS server returns the person UUID in the `personUuid` attribute instead. The Wicket Base plugin identifies the logged-in member by treating the WordPress username as the Wicket person UUID, so you **must** override this XPath to point at `personUuid`. If you leave the default, WordPress usernames will not be Wicket UUIDs and role/membership sync will not work.

Click **Save options**.

### The bypass URL (admin escape hatch)

With **Enable URL bypass parameter** on, you can log into WordPress directly, skipping SSO, using:

```
https://your-site.example/wp-login.php?wp_cassify_bypass=bypass
```

Keep this in your back pocket. It is how you rescue a site or reconfigure SSO/Wicket settings when the CAS server is unavailable or misconfigured. Direct (non-SSO) logins are otherwise discouraged on a Wicket-powered site.


## Step 4: Configure the Wicket Base Plugin

All settings live under **Wicket** in the WordPress admin sidebar.

### Environments (connect to Wicket)

Go to **Wicket > Environments**. Enter the credentials from Wicket into the **Wicket Production** and **Wicket Staging** sections (API Endpoint, JWT Secret Key, Person ID, Parent Org, Wicket Admin). Then set **Wicket Environment** to the one you want active. When the credentials are correct, the **Status** indicator turns green and reads **CONNECTED** (it performs a live test call to the API). Full reference: [Settings: Environments](../product/settings-environments.md).

### General

Go to **Wicket > General** and set the **Create Account Page** and **New Account Redirect** pages, and configure reCAPTCHA if you use the Create Account form. Reference: [Settings: General](../product/settings-general.md). To set up reCAPTCHA, follow [Configure reCAPTCHA on the Create Account Form](configure-recaptcha.md).

### Integrations (SSO role sync)

Role sync is **off by default and optional.** With it off, SSO still logs members in; it just does not touch their WordPress roles. Turn it on only if you want Wicket roles reflected as WordPress roles. If you manage WordPress roles by hand, skip this section.

Go to **Wicket > Integrations** and, under **WP Cassify**, enable what you need:

- **Sync Security Roles**: the master switch. When on, each login rebuilds the user's WordPress roles from their Wicket security roles. While it is off, none of the options below do anything.
- **Sync Memberships as Roles**: also apply the member's active Wicket memberships as roles. Requires Sync Security Roles.
- **Sync Tags as Roles**: comma-separated Wicket tags to also bring across as roles. Requires Sync Security Roles.
- **Ignore Roles**: comma-separated Wicket security roles to exclude from the sync.

> **Heads up:** with Sync Security Roles on, each login first clears the user's existing WordPress roles and then reapplies them from Wicket. Roles you assign manually in WordPress will not survive the member's next login.

Full reference: [Settings: Integrations](../product/settings-integrations.md).



## Step 5: Add a Login Link

SSO is now wired up, but WordPress will not show members a way to start it. WP Cassify redirects a visitor to the Wicket CAS login automatically only when they reach a page that requires login. To give members an explicit **Log In** control, your site has to provide the link. Wicket's own theme adds one automatically; if you run your own theme, add it yourself.

The link points at the CAS server's login endpoint with a `service` parameter that tells CAS where to return the member after they authenticate:

```
https://[client]-login.wicketcloud.com/login?service=RETURN_URL
```

Use the same CAS base URL you entered in WP Cassify. `RETURN_URL` is where the member lands after logging in (for example your account page or the home page). Logout uses the standard WordPress logout URL. Pick whichever approach fits how you manage the site.

### Option 1: A menu link (no code)

1. Go to **Appearance > Menus** (or the navigation block in the site editor).
2. Add a **Custom Link**:
   - URL: `https://[client]-login.wicketcloud.com/login?service=https://your-site.example/` (point `service` wherever members should land)
   - Link text: `Log In`
3. Save the menu.

WP Cassify also provides shortcodes for placing a link inside page content: `[wp_cassify_login_with_redirect service_redirect_url='https://your-site.example/']` and `[wp_cassify_logout_with_redirect service_redirect_url='https://your-site.example/']`.

### Option 2: A theme link (code)

To return members to the exact page they were on, build the link in your theme from the CAS base URL WP Cassify already stores:

```php
// Log in, then return the member to the page they are currently viewing.
$return_url = home_url(add_query_arg(null, null));
$login_url  = get_option('wp_cassify_base_url') . 'login?service=' . urlencode($return_url);

// Log out via the standard WordPress logout URL.
$logout_url = wp_logout_url(home_url('/'));
```

`wp_cassify_base_url` is the option WP Cassify saves for its **CAS Server base url** field, so the link always matches the environment WP Cassify points at.



## Step 6: Verify

1. On **Wicket > Environments**, confirm the **Status** shows **CONNECTED** for the active environment.
2. In a private/incognito window, click your login link (or open a page that requires login) and confirm you are redirected to the Wicket CAS login. Sign in as a test member. (If the CAS page shows an error or bounces back without signing you in, this site's domain may not be whitelisted with Wicket yet; see Before You Start.)
3. Back in the WordPress admin (**Users**), confirm the new user's **username is the Wicket person UUID** (a long hyphenated ID), not an email or name. This proves the personUuid XPath is working.
4. If you enabled role sync (Step 4), confirm the test user received the expected WordPress roles based on their Wicket roles/memberships.
5. Confirm the bypass URL (`?wp_cassify_bypass=bypass`) lets an administrator log in without SSO.



## Maintaining and Updating

- **How updates arrive**, by install method:
  - **Download ZIP (Option A):** download the newer ZIP from GitHub and re-upload it via **Plugins > Add New > Upload Plugin**. WordPress replaces the existing copy.
  - **Deployer for Git (Option B):** open its settings screen and run the deployment to pull the latest code from the tracked branch. This follows the branch, not tagged releases.
  - **Composer (Option C):** raise the version constraint if needed, then run `composer update industrialdev/wicket-wp-base-plugin` from your project root.
- **Versioning:** releases are tagged with bare version numbers (e.g. `2.4.10`). Watch the [GitHub repository](https://github.com/industrialdev/wicket-wp-base-plugin) for new versions.
- **ACF and WP Cassify update independently** through their own normal update channels (free ACF updates from the **Plugins** screen like any repository plugin). Keep all three current.
- After any Wicket Base update, revisit **Wicket > Environments** to confirm the Status is still **CONNECTED**.



## Troubleshooting

**Locked out / need to reconfigure SSO**
- Use the bypass URL: `https://your-site.example/wp-login.php?wp_cassify_bypass=bypass`. This requires **Enable URL bypass parameter** to be on in WP Cassify.

**Redirected to the Wicket CAS login, but it errors or bounces back without signing you in**
- The site's domain is probably not whitelisted on the Wicket CAS server. CAS refuses logins from any URL it does not recognize. Send the Wicket team the exact hostname of this environment and have them whitelist it; each environment (production, staging, dev) needs its own entry. See "Whitelist your site domains with Wicket" under Before You Start.

**The username is an email or name instead of a UUID**
- The WP Cassify **Xpath query used to extract cas user id** is almost certainly still the default. Set it to `//cas:serviceResponse/cas:authenticationSuccess/cas:attributes/cas:personUuid` (Step 3). WordPress usernames must be the Wicket person UUID for role sync to work.

**Members log in and the username is a correct UUID, but their roles never change**
- Role sync is opt-in and is probably off. Enable **Sync Security Roles** under **Wicket > Integrations** (Step 4). Sync Memberships as Roles and Sync Tags as Roles do nothing unless Sync Security Roles is also on.

**Status shows NOT CONNECTED**
- Double-check the API Endpoint, JWT Secret Key, and Person ID for the **active** environment, and confirm the **Wicket Environment** toggle points at the environment whose credentials you filled in.

**Features / blocks do not appear**
- Confirm **ACF** is installed and active. The Wicket blocks and widgets do not register without it. (WordPress should also refuse to activate Wicket Base while ACF is inactive.)

**The plugin activates but errors about missing classes**
- Its autoloaded dependencies are not available. With a ZIP or Deployer install (Options A and B), the committed `vendor/` folder was stripped during copying or deploy; re-download the ZIP or redeploy so `vendor/` is present. With a Composer-managed install (Option C), run `composer install` from your project root so Composer resolves the plugin's dependencies.

**Login errors on Pantheon**
- When using WP Cassify on Pantheon, delete the mu-plugin `wp-native-php-sessions.php` and the `wp-native-php-sessions` folder. They cause login errors.
