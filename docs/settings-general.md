---
title: "Settings — General"
audience: [implementer, support]
wp_admin_path: "Wicket → General"
php_class: Wicket_Settings
db_option_prefix: wicket_admin_settings_create_account_page, wicket_admin_settings_person_creation_redirect, wicket_admin_settings_google_captcha_, wicket_admin_settings_disable_default_styling
---

# Wicket Settings — General

Found under **Wicket → General** in the WordPress admin.

This tab handles the core page configuration for account creation and controls whether Wicket's built-in styles are loaded on the site.

---

## Create Account

### Create Account Page

Select which WordPress page is used as the account creation page. The selected page must have the **Create Account Form** block placed on it. This is where new users land when they want to sign up.

| | |
|---|---|
| Option key | `wicket_admin_settings_create_account_page` |
| PHP access | `get_option('wicket_admin_settings_create_account_page')` |
| Default | _(none)_ |

### New Account Redirect

Select which WordPress page users are sent to after they successfully submit the create account form. By default, users are directed to a `/verify-account` page where they confirm their email address before their account is fully active.

| | |
|---|---|
| Option key | `wicket_admin_settings_person_creation_redirect` |
| PHP access | `get_option('wicket_admin_settings_person_creation_redirect')` |
| Default | _(none)_ |

### Google Captcha

A checkbox to turn Google reCAPTCHA on or off for the create account form. When enabled, users must pass the CAPTCHA challenge before their account submission is processed — this helps prevent automated bot registrations. Typically the "v2 - I am not a robot" challenge is best for this here when setting up the recaptcha version over on google.

| | |
|---|---|
| Option key | `wicket_admin_settings_google_captcha_enable` |
| PHP access | `get_option('wicket_admin_settings_google_captcha_enable')` |
| Default | `Off` |

### Google Captcha Key

The **site key** provided by Google when you register your site for reCAPTCHA. This key is used to render the CAPTCHA widget visually on the page. Keys can be obtained at [https://www.google.com/recaptcha](https://www.google.com/recaptcha).

| | |
|---|---|
| Option key | `wicket_admin_settings_google_captcha_key` |
| PHP access | `get_option('wicket_admin_settings_google_captcha_key')` |
| Default | _(none)_ |

### Google Captcha Secret Key

The **secret key** provided by Google for reCAPTCHA. This key is used on the server side to verify the user's CAPTCHA response. It should be kept private. Keys can be obtained at [https://www.google.com/recaptcha](https://www.google.com/recaptcha).

| | |
|---|---|
| Option key | `wicket_admin_settings_google_captcha_secret_key` |
| PHP access | `get_option('wicket_admin_settings_google_captcha_secret_key')` |
| Default | _(none)_ |

---

## Styles

### Disable Default Styling

A checkbox that, when enabled, prevents Wicket from loading any of its built-in CSS on the site. This is intended for advanced users who want to supply their own custom styles for all Wicket blocks and components. If this is turned on without replacement styles in place, Wicket elements will appear unstyled.

| | |
|---|---|
| Option key | `wicket_admin_settings_disable_default_styling` |
| PHP access | `get_option('wicket_admin_settings_disable_default_styling')` |
| Default | `Off` |
