---
title: "Wicket Base Plugin Documentation Index"
audience: [implementer, support, developer, end-user]
---

# Documentation Index — wicket-wp-base-plugin

Three directories, three audiences. See [CLAUDE.md](CLAUDE.md) for authoring rules.

---

## Product Docs — Operators & Support

Configuration and settings reference. One file per WP admin screen.

- [Settings — Environments](product/settings-environments.md) — connect to Wicket staging/production environments
- [Settings — General](product/settings-general.md) — account creation pages, reCAPTCHA, default styles
- [Settings — Integrations](product/settings-integrations.md) — WooCommerce, WP-Cassify SSO, Mailtrap
- [Settings — Memberships](product/settings-memberships.md) — membership tier mapping, membership product categories
- [Settings — Touchpoints](product/settings-touchpoints.md) — WooCommerce order and Event Tickets touchpoint toggles

---

## Engineering Docs — Developers & Agents

Technical reference for hooks, filters, architecture, and source-level contracts.

- [Centralized Logging](engineering/logging.md) — `WicketWP\Log` usage, log levels, per-plugin wrapper pattern
- [WooCommerce Email Blocker](engineering/woocommerce-email-blocker.md) — admin email suppression logic, hooks, extension point

---

## Guides — End Users

- [Configure reCAPTCHA on the Create Account Form](guides/configure-recaptcha.md) — get Google keys and enable bot protection on sign-up
