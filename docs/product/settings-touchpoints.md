---
title: "Settings — Touchpoints"
audience: [implementer, support]
wp_admin_path: "Wicket → Touchpoints"
php_class: Wicket_Settings
db_option_prefix: wicket_admin_settings_tp_
---

# Wicket Settings — Touchpoints

Found under **Wicket → Touchpoints** in the WordPress admin.

Touchpoints are records that get written back to a person's profile in Wicket whenever they take a meaningful action on the WordPress site. This tab lets you control which of those actions automatically trigger a touchpoint.

---

## Default Touchpoints

### WooCommerce Order

When enabled, a touchpoint is written to the customer's Wicket profile each time a WooCommerce order changes status (e.g. pending, processing, completed, refunded, cancelled). The touchpoint includes the order ID, total amount, currency, products purchased, and any associated organization information.

### Event Tickets — Attendee Registered for an Event

When enabled, a touchpoint is written to an attendee's Wicket profile when they register for an event. The touchpoint is recorded at the point the order is marked complete. Requires the Event Tickets plugin.

### Event Tickets — Attendee Check-in for an Event

When enabled, a touchpoint is written to an attendee's Wicket profile when they are checked in at an event. Requires the Event Tickets plugin.

### Event Tickets — Attendee RSVP for an Event

When enabled, a touchpoint is written to an attendee's Wicket profile when they RSVP for an event. Requires the Event Tickets plugin.

---

## Custom Touchpoints

This section is reserved for custom touchpoints added by developers. Out of the box it has no options — additional touchpoints specific to your site can be registered here by a developer using the plugin's filter hooks.
