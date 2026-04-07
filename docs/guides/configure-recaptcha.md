---
title: "Configure reCAPTCHA on the Create Account Form"
audience: [end-user, implementer]
related: ../product/settings-general.md
---

# Configure reCAPTCHA on the Create Account Form

Adding reCAPTCHA to the Create Account form prevents automated bots from registering fake accounts. This guide walks through getting the keys from Google and enabling them in WordPress.

---

## Before You Start

- You need a Google account.
- You need admin access to the WordPress site.
- The Wicket base plugin must be installed and active.

---

## Step 1 — Register Your Site with Google

1. Go to [https://www.google.com/recaptcha/admin/create](https://www.google.com/recaptcha/admin/create) and sign in.
2. Fill in the form:
   - **Label** — anything that helps you identify this site (e.g. "ACME Member Portal")
   - **reCAPTCHA type** — choose **v2 → "I'm not a robot" Checkbox**
   - **Domains** — add your site's domain (e.g. `members.acme.org`). If you're testing locally, also add `localhost`.
3. Accept the Terms of Service and click **Submit**.
4. Google will show you two keys:
   - **Site key** — safe to expose publicly; used to render the widget
   - **Secret key** — keep this private; used to verify responses on the server

Copy both keys. You'll need them in the next step.

---

## Step 2 — Enter the Keys in WordPress

1. In the WordPress admin, go to **Wicket → General**.
2. Under the **Create Account** section:
   - Check **Google Captcha** to enable it.
   - Paste your **site key** into **Google Captcha Key**.
   - Paste your **secret key** into **Google Captcha Secret Key**.
3. Click **Save Settings**.

---

## Step 3 — Verify It Works

1. Navigate to the Create Account page on your site (the page assigned under **Wicket → General → Create Account Page**).
2. The reCAPTCHA "I'm not a robot" checkbox should appear in the form.
3. Try submitting the form without checking the box — the form should block submission and show a validation message.

---

## Troubleshooting

**The reCAPTCHA widget doesn't appear**
- Confirm the **Google Captcha** checkbox is enabled and settings are saved.
- Check the browser console for errors — a mismatch between the domain registered in Google and the domain you're visiting will prevent the widget from loading.
- If testing on `localhost`, make sure `localhost` is in the domain list in the Google reCAPTCHA admin.

**Form submits but reCAPTCHA is not being verified**
- Double-check that the **Secret Key** field contains the secret key, not the site key. They're easy to swap.

**You need to rotate the keys**
- Go back to [https://www.google.com/recaptcha/admin](https://www.google.com/recaptcha/admin), select your site, and generate new keys.
- Update both fields in **Wicket → General** and save.
- The old keys stop working immediately after rotation.
