# Wicket Settings — General

Found under **Wicket → General** in the WordPress admin.

This tab handles the core page configuration for account creation and controls whether Wicket's built-in styles are loaded on the site.

---

## Create Account

### Create Account Page

Select which WordPress page is used as the account creation page. The selected page must have the **Create Account Form** block placed on it. This is where new users land when they want to sign up.

### New Account Redirect

Select which WordPress page users are sent to after they successfully submit the create account form. By default, users are directed to a `/verify-account` page where they confirm their email address before their account is fully active.

### Google Captcha

A checkbox to turn Google reCAPTCHA on or off for the create account form. When enabled, users must pass the CAPTCHA challenge before their account submission is processed — this helps prevent automated bot registrations. Typically the "v2 - I am not a robot" challenge is best for this here when setting up the recaptcha version over on google.

### Google Captcha Key

The **site key** provided by Google when you register your site for reCAPTCHA. This key is used to render the CAPTCHA widget visually on the page. Keys can be obtained at [https://www.google.com/recaptcha](https://www.google.com/recaptcha).

### Google Captcha Secret Key

The **secret key** provided by Google for reCAPTCHA. This key is used on the server side to verify the user's CAPTCHA response. It should be kept private. Keys can be obtained at [https://www.google.com/recaptcha](https://www.google.com/recaptcha).

---

## Styles

### Disable Default Styling

A checkbox that, when enabled, prevents Wicket from loading any of its built-in CSS on the site. This is intended for advanced users who want to supply their own custom styles for all Wicket blocks and components. If this is turned on without replacement styles in place, Wicket elements will appear unstyled.
