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

| | |
|---|---|
| Option key | `wicket_admin_settings_tp_woo_order` |
| PHP access | `get_option('wicket_admin_settings_tp_woo_order')` |
| Default | `On` |

### Event Tickets — Attendee Registered for an Event

When enabled, a touchpoint is written to an attendee's Wicket profile when they register for an event. The touchpoint is recorded at the point the order is marked complete. Requires the Event Tickets plugin.

| | |
|---|---|
| Option key | `wicket_admin_settings_tp_event_ticket_attendees` |
| PHP access | `get_option('wicket_admin_settings_tp_event_ticket_attendees')` |
| Default | `On` |

### Event Tickets — Attendee Check-in for an Event

When enabled, a touchpoint is written to an attendee's Wicket profile when they are checked in at an event. Requires the Event Tickets plugin.

| | |
|---|---|
| Option key | `wicket_admin_settings_tp_event_ticket_attendees_checkin` |
| PHP access | `get_option('wicket_admin_settings_tp_event_ticket_attendees_checkin')` |
| Default | `On` |

### Event Tickets — Attendee RSVP for an Event

When enabled, a touchpoint is written to an attendee's Wicket profile when they RSVP for an event. Requires the Event Tickets plugin.

| | |
|---|---|
| Option key | `wicket_admin_settings_tp_event_ticket_attendees_rsvp` |
| PHP access | `get_option('wicket_admin_settings_tp_event_ticket_attendees_rsvp')` |
| Default | `On` |

### Event Tickets: Attendee Added by an Admin or CSV Import

When enabled, a touchpoint is written when an attendee is added from the WordPress admin **Attendees** screen, or imported with the Attendee CSV Importer extension. Requires the Event Tickets plugin.

These are recorded with the same `Registered for an event` action as a purchased registration, so existing reporting keeps counting them. Which path an attendee came from is shown as `Added by:` in the touchpoint details, and stored as `registration_source` (`admin` or `import`) in the touchpoint data.

Leave this off if a client only wants registrations that came through checkout.

Two things to know:

- **Imported attendees carry no registration form answers.** The CSV importer only records a name and email, so there is nothing to send. Attendees added from the admin screen do carry their answers.
- Adding an attendee by either route creates its own WooCommerce order behind the scenes. With **WooCommerce Order** touchpoints also enabled, those orders produce order touchpoints of their own, separate from the registration touchpoint described here.

| | |
|---|---|
| Option key | `wicket_admin_settings_tp_event_ticket_attendees_added` |
| PHP access | `get_option('wicket_admin_settings_tp_event_ticket_attendees_added')` |
| Default | `Off` |

### Event Tickets: Attendee Removed from an Event

When enabled, a touchpoint is written when an attendee is removed from an event, recorded with the `Removed from an event` action. Requires the Event Tickets plugin.

Written once per attendee, whether they are moved to the trash or deleted outright. Event Tickets trashes attendees by default, so the touchpoint is normally written at that point, and emptying the trash later does not write a second one.

Note that restoring an attendee from the trash does not write anything to cancel the removal out, so the profile will still show that they were removed.

| | |
|---|---|
| Option key | `wicket_admin_settings_tp_event_ticket_attendees_removed` |
| PHP access | `get_option('wicket_admin_settings_tp_event_ticket_attendees_removed')` |
| Default | `Off` |

### Event Tickets: Include Registration Form Answers

When enabled, an attendee's registration form answers (the extra fields configured on a ticket, such as meal choice or t-shirt size) are included in their event touchpoints, as label and value pairs.

Every answered field is sent. Turn this off for clients whose registration forms collect anything they would rather not have copied into Wicket. Individual fields can be left out with the `wicket_tec_registration_answers` filter instead of disabling the setting entirely.

Answers are only available where Event Tickets Plus collected them: purchases and attendees added from the admin screen have them, CSV-imported attendees do not.

| | |
|---|---|
| Option key | `wicket_admin_settings_tp_event_ticket_attendees_answers` |
| PHP access | `get_option('wicket_admin_settings_tp_event_ticket_attendees_answers')` |
| Default | `Off` |

---

## Custom Touchpoints

This section is reserved for custom touchpoints added by developers. Out of the box it has no options — additional touchpoints specific to your site can be registered here by a developer using the plugin's filter hooks.
