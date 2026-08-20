---
title: "Event Tickets Touchpoints"
audience: [developer, agent]
php_class: null
source_files:
  - "includes/helpers/helper-touchpoints-events.php"
  - "includes/touchpoints/event_ticket_attendees_added_removed.php"
  - "includes/touchpoints/woocommerce_payment_complete_event_ticket_attendees.php"
  - "includes/touchpoints/event_ticket_attendees_rsvp.php"
  - "includes/touchpoints/event_ticket_attendees_checkin.php"
  - "includes/helpers/helper-persons.php"
---

# Event Tickets Touchpoints

Writes Wicket touchpoints for The Events Calendar / Event Tickets attendee activity. Settings
reference: [Settings: Touchpoints](../product/settings-touchpoints.md).

---

## Which writer handles which path

Every WooCommerce attendee path fires the same `event_ticket_woo_attendee_created` action, so the
writers are separated by the origin of the attendee, not by the hook.

| Origin | Hook | Writer | Action written |
|---|---|---|---|
| Front-end purchase | `woocommerce_order_status_completed` | `woocommerce_payment_complete_event_ticket_attendees()` | `Registered for an event` |
| Added by an admin | `event_ticket_woo_attendee_created` | `wicket_tec_attendee_added_touchpoint()` | `Registered for an event` |
| CSV import | `event_ticket_woo_attendee_created` | `wicket_tec_attendee_added_touchpoint()` | `Registered for an event` |
| RSVP | `event_tickets_rsvp_ticket_created` | `wicket_touchpoint_write_attendee_rsvp()` | `RSVP to event` |
| Check-in | `wootickets_checkin` and siblings | `wicket_touchpoint_write_attendee()` | `Attended an event` |
| Removed | `wp_trash_post`, `before_delete_post` | `wicket_tec_maybe_write_removal_touchpoint()` | `Removed from an event` |

Purchases deliberately stay on the order hook. Event Tickets Plus generates WooCommerce attendees as
early as the `pending` status, so writing them from `event_ticket_woo_attendee_created` would record
unpaid and abandoned orders.

All three registration writers take their attendees from **attendee posts**, never from the order's
`_tribe_tickets_meta`. See [Why not the order's `_tribe_tickets_meta`](#why-not-the-orders-_tribe_tickets_meta).

### Origin detection

`wicket_tec_attendee_origin( int $attendee_id, $order = null, string $hint = '' )` returns
`purchase`, `admin`, `import` or `rsvp`. Two independent signals back each other up:

| Origin | Signals |
|---|---|
| `import` | request flag set by the importer's per-row filter; `WC_Order::get_created_via() === 'import'` |
| `admin` | `_tribe_attendee_source` postmeta of `admin`; `get_created_via() === 'admin'` |

The request flag exists because the Attendee CSV Importer bypasses the attendee repository (raw
`wp_insert_post()` plus a hand-fired provider action) and writes no provenance meta at all. It also
covers RSVP imports, which have no order to inspect.

Adding an attendee from the admin screen, and importing one, each create their own WooCommerce order.
A front-end checkout creates one order covering every attendee in the basket.

### Deduplication

| Meta key | On | Purpose |
|---|---|---|
| `_wicket_touchpoint_registered` | attendee | unix timestamp; blocks a second registration touchpoint |
| `_wicket_touchpoint_registered_origin` | attendee | `purchase`, `admin` or `import`; carried onto the removal touchpoint |
| `_wicket_touchpoint_removed` | attendee | unix timestamp; makes trash then delete write once |
| `_wicket_touchpoint_removal_skipped` | attendee | reason code; stops the same failure logging twice |

Markers are written only after `write_touchpoint()` succeeds, so a transient Wicket failure can be
retried.

`external_event_id` for every attendee-based writer is `tec_{kind}_{site}_{attendee_id}`, derived only
from the attendee ID so a repeat write produces the same identifier. Only the ticket-buyer touchpoint
still uses the old `{order_id}_{status}_{sha256(payload)}` scheme, because the buyer has no attendee
post to key on.

Note for sites upgrading: registrations purchased before this change were written under the old
payload-hash scheme and carry no `_wicket_touchpoint_registered` marker. Re-completing one of those
old orders will therefore write one duplicate, once. Everything written since is protected by the
marker.

Removal is **not** conditional on a registration touchpoint existing. Attendees created before this
feature shipped have no marker, and requiring one would ignore removals across the whole back
catalogue.

### Why not the order's `_tribe_tickets_meta`

The purchase writer used to build its attendee list from the order's `_tribe_tickets_meta`, the copy
Event Tickets Plus parks on the order. That field is a record of the answers that survived to
checkout, not a list of who is on the order, and it loses entries three different ways:

1. ETP holds in-progress answers in a transient that expires after **24 hours**
   (`Tribe__Tickets_Plus__Meta__Storage::$ticket_meta_expire_time`, not filterable), keyed by a hash in
   a browser-session cookie. A WooCommerce cart outlives both, indefinitely for a logged-in customer,
   and abandoned-cart tooling actively invites people back days later. A cart assembled over more than
   a day reaches checkout with only the most recently added ticket's answers.
2. A ticket with **no attendee-information fields** configured never populates the field at all, so
   those events never recorded a registration.
3. `save_attendee_meta_to_order()` is hooked to *every* `woocommerce_order_status_changed`, rebuilding
   the field from that transient, so it can go from complete to partial after the attendees exist.

In all three cases the skipped registration was silent. Attendee posts have none of these problems:
Event Tickets creates exactly one per ticket sold, whether or not any answers were collected.

Two consequences worth knowing:

- When answers were never collected, Event Tickets stamps the **purchaser's billing name and email**
  onto the attendee. A ticket bought on behalf of someone else whose answers were lost is therefore
  attributed to the purchaser: the attendee's own identity was never stored, so there is nothing
  better to use.
- `wicket_tec_order_registrations()` logs an `attendee_count_mismatch` error when an order sold more
  tickets than it has attendee posts, so a shortfall surfaces instead of quietly writing fewer
  touchpoints than tickets sold.

### Who did it

The Wicket timeline renders a touchpoint's `details` string but not its `data`, so anything a person
reading a profile needs to see has to be in `details`.

| Touchpoint | Details line | Data keys |
|---|---|---|
| added by admin or import | `Added by: Jane Smith` or `Jane Smith (CSV import)` | `added_by`, `added_by_name`, `registration_source` |
| removed | `Removed by: Jane Smith` | `removed_by`, `removed_by_name`, `removal_trigger` |

The acting user comes from `_tribe_attendee_added_by` where Event Tickets Plus recorded it, and from
`get_current_user_id()` otherwise. When no user can be resolved, which happens for imports run over
WP-CLI or cron, the added-by line falls back to the mechanism alone and the removed-by line is
omitted. A purchased registration's `details` gains neither line, so its payload is unchanged.

---

## Filters and actions

### `wicket_tec_event_data`

```php
apply_filters( 'wicket_tec_event_data', array $data, int $event_id )
```

The canonical event data behind every payload: `start`, `end`, `timezone`, `event_name`, `event_id`,
`url`, `event_type`, `location`, `format`, `event_additional_fields`.

### `wicket_tec_attendee_origin`

```php
apply_filters( 'wicket_tec_attendee_origin', string $origin, int $attendee_id, $order )
```

Override origin detection. Useful for a provider or import tool this plugin does not know about.

### `wicket_tec_attendee_post_types`

```php
apply_filters( 'wicket_tec_attendee_post_types', array $post_types )
```

Which post types count as attendees for the removal hooks. Defaults to `tribe_wooticket`,
`tribe_rsvp_attendees`, `tribe_eddticket`, `tec_tc_attendee`, `tribe_tpp_attendees`.

### `wicket_tec_registration_answers`

```php
apply_filters( 'wicket_tec_registration_answers', array $answers, int $attendee_id, int $ticket_id, int $event_id )
```

Label to value pairs about to be sent. Every answered field is included by default. `unset()` a
label to leave one out, or return `[]` for none. Every registration writer now resolves answers from
a saved attendee, so `$attendee_id` is always a real attendee post ID.

`wicket_tec_registration_answers_from_raw()` still exists for callers that hold raw slug/value
answers rather than an attendee post, but no writer uses it any more.

### `wicket_tec_ambiguous_person_strategy`

```php
apply_filters( 'wicket_tec_ambiguous_person_strategy', string $strategy, string $email, array $args )
```

`error` (default) skips the touchpoint and logs it when an address matches several Wicket people and
none holds it as their primary. `first` restores the older behaviour of taking whichever record came
back first.

```php
// Opt a site back into the previous behaviour.
add_filter( 'wicket_tec_ambiguous_person_strategy', fn() => 'first' );
```

### `wicket_tec_touchpoint_order_hook_origins`

```php
apply_filters( 'wicket_tec_touchpoint_order_hook_origins', array $origins, string $created_via, WC_Order $order )
```

Which `created_via` values the order-level purchase writer handles. Defaults to
`['checkout', '']`. Admin and import orders are excluded so they are not written twice, once as a
registration and once as the order's ticket buyer.

### `wicket_include_tec_touchpoint_for_ticket_buyer`

```php
apply_filters( 'wicket_include_tec_touchpoint_for_ticket_buyer', bool $include )
```

Pre-existing. When true, the purchaser also gets a registration touchpoint, on top of any attendees.
Return false where the purchaser should only appear if they added themselves as an attendee.

### `wicket_tec_touchpoint_person_ambiguous`

```php
do_action( 'wicket_tec_touchpoint_person_ambiguous', string $email, array $uuids, array $context )
```

Fires when an address cannot be resolved to a single person. `$context` carries `attendee_id`,
`event_id` and `action`. Use it to route these to email, Slack or a review queue.

---

## Person resolution

`wicket_resolve_person_by_email( string $email, array $args = [] ): array` in
`includes/helpers/helper-persons.php`.

Returns `['status', 'uuid', 'source', 'matches', 'total', 'code']`, where `status` is `found`,
`created`, `ambiguous` or `error`.

1. Primary address only. Unique in Wicket, so any hit wins outright. `source` is `primary`.
2. All addresses, catching secondary and alias entries. `source` is `any`.
3. Create.

`$args`: `first_name`, `last_name`, `email_type` (all used only when creating), `create` (default
true), `match_all_emails` (default true; false keeps step 1 only), `on_ambiguous` (`error` or
`first`), `page_size` (default 25).

Counting is deliberately conservative. `meta.page.total_items` counts matched rows rather than
distinct people, and one person can match on several of their own addresses, so rows are
deduplicated by UUID. Uniqueness is claimed only when the reported total fits inside the page that
was read; a total that overruns the page means an unseen match may exist, which is `ambiguous`.
`wicket_person_match_verdict()` holds that decision on its own so it can be tested without an API
client.

Both lookups are wrapped in `try/catch`, so a Wicket outage cannot fail a checkout or an admin save.

`wicket_create_or_get_person()` is unchanged in behaviour: it delegates with `on_ambiguous` set to
`first`.

---

## Gotchas

- `Tribe__Events__Timezones::get_event_timezone_string()` is the event timezone method.
  `Tribe__Timezones` exists but does not define it, so guard with `method_exists()`, not
  `class_exists()`.
- `Tribe__Tickets_Plus__Meta::get_attendee_meta_fields()` is static;
  `get_meta_fields_by_ticket()` and `get_attendee_meta_values()` are not, and must come off
  `tribe( 'tickets-plus.meta' )`.
- Never read `attendee_meta` from `tribe_tickets_get_attendees()`. Legacy providers return
  `[slug => ['slug', 'label', 'value']]` while Tickets Commerce returns a flat `[slug => value]`.
  Use the Event Tickets Plus meta helpers instead.
- Registration answers only cover fields in the ticket's configured fieldset. A field injected at
  render time, as `event_ticket_attendees_field_hooks.php` does for last name, will not appear.
- Reading a property on null is a warning in PHP 8, not a fatal, which is why the unguarded venue
  lookup produced a `', , ,  '` location rather than an error.
- Option gates compare against the string `'1'`. Setting one to integer `1`, which
  `wp option patch update` will do, silently leaves the feature off.
