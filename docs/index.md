---
title: "Wicket Base Plugin Documentation Index"
audience: [implementer, support, developer, end-user]
---

# Documentation Index — wicket-wp-base-plugin

Three directories, three audiences. See [AGENTS.md](AGENTS.md) for authoring rules.

---

## Product Docs — Operators & Support

Configuration and settings reference. One file per WP admin screen.

- [Settings — Environments](product/settings-environments.md) — connect to Wicket staging/production environments
- [Settings — General](product/settings-general.md) — account creation pages, reCAPTCHA, default styles
- [Settings — Integrations](product/settings-integrations.md) — WooCommerce, WP-Cassify SSO, Mailtrap
- [Settings — Memberships](product/settings-memberships.md) — membership tier mapping, membership product categories
- [Settings — Touchpoints](product/settings-touchpoints.md) — WooCommerce order and Event Tickets touchpoint toggles
- [Settings — WP Cassify (SSO)](product/settings-wp-cassify.md) — recommended WP Cassify field values for a Wicket SSO integration

---

## Engineering Docs — Developers & Agents

Technical reference for hooks, filters, architecture, and source-level contracts.

- [Centralized Logging](engineering/logging.md) — `WicketWP\Log` usage, log levels, per-plugin wrapper pattern
- [WooCommerce Email Blocker](engineering/woocommerce-email-blocker.md) — admin email suppression logic, hooks, extension point
- [Release Automation](engineering/release-automation.md): auto version bump and tag on merge to main, GitHub App setup

---

## Guides — End Users

- [Configure reCAPTCHA on the Create Account Form](guides/configure-recaptcha.md) — get Google keys and enable bot protection on sign-up
- [Set Up Wicket and SSO on a Self-Hosted WordPress](guides/set-up-wicket-and-sso-on-self-hosted-wordpress.md) — full install-and-configure playbook for a self-hosted client IT team
