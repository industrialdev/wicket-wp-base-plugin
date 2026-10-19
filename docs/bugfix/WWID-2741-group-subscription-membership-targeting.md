---
title: "WWID-2741 — Group Subscriptions Change Only the Group Membership They Own"
audience: [developer, agent]
ticket: WWID-2741
source_files: ["includes/integrations/group-subscriptions.php", "includes/helpers/helper-groups.php"]
---

# WWID-2741 — Group Subscriptions Change Only the Group Membership They Own

Plan: `atlas/plans/fix-group-subscription-membership-targeting.md`. Data damage: `atlas/audits/2026-10-group-subscription-membership-damage.md`.

## Defect

A group subscription did not record which MDP group membership row it created. Renewal, date change, and cancel looked up the person in the group and patched the first row in the response (`data[0]`). With more than one row for a person, group, and role, the wrong row changed. Found on MSA after an import added one row per person, group, and year.

The activation duplicate check read page 1 of all groups of the person, and created a row when the lookup failed.

## Fix

| Change | Where |
|---|---|
| Activation stores the row UUID in subscription meta `_group_membership_uuid_{group_uuid}_{role_slug}` | `wicket_group_membership_subscription_status_active()` |
| Renewal, date change, and cancel patch only the stored row | `wicket_group_subscription_update_end_dates()` |
| No stored UUID: link the person's row in the group, with the role, whose `start_date` equals the subscription start date (site timezone, `Y-m-d`). First match wins. The UUID is stored | `wicket_group_subscription_membership_uuid()` |
| Activation order for each group: stored UUID → skip. Start-date row → store, skip the create. Else the duplicate check | `wicket_group_membership_subscription_status_active()` |
| Duplicate check: the person's active rows in this group with this role, all pages. Lookup failure returns `WP_Error` `wicket_dup_check_failed` and creates nothing | `wicket_wicket_add_group_member()` |
| `person_uuid` arg on `wicket_get_group_members()`, new `wicket_get_group_members_all_pages()` | `helper-groups.php` |
| Activation and link decisions log at `audit` level, source `wicket-group-sync` | all of the above |

A renewal runs activation first. WooCommerce Subscriptions sets `on-hold` when the renewal is due and `active` on payment, before `wcs_subscription_renewal_payment_complete`. This is why activation links by start date before the duplicate check.

## Blast radius

What changes for working sites and existing subscriptions:

| Area | Before | After |
|---|---|---|
| One row per person, group, and role | Patched `data[0]`, the correct row | Same row when its start date matches the subscription. Otherwise as the two rows below |
| Existing subscription, several rows, a row with its start date | Patched `data[0]` | Patches the start-date row |
| Existing subscription, no row with its start date, at renewal or reactivation | Patched `data[0]` when its role matched | Stores and patches the first active row in the group with the role |
| Existing subscription, no row with its start date, at date change or cancel | Patched `data[0]` | No patch. Order note "No group membership is linked to this subscription" |
| Activation with a stored UUID (reactivation, renewal) | Created a row if no active row was on page 1 | No create |
| Duplicate-check lookup fails | Created a row | No create. Order note "Failed group subscription for {group}" |
| Order notes | — | New: "Group subscription linked to the existing group membership for {group}", and the "not updated" note above |
| `wicket_get_group_members()` callers (account centre, org roster) | — | No change. The request is the same without `person_uuid` |
| `wicket_get_group_membership_uuids()`, `wicket_get_group_memberships()`, `wicket_wicket_get_group_membership()` | Used by the handlers | No longer called. Kept for child themes |
| API calls | One lookup per event | A linked subscription needs no lookup. The first event of an older subscription reads all pages of the person's rows in the group |

Not changed: the dates sent to MDP, the `_group_assigned_uuid` and `_group_assigned_next_payment` meta, the date-change re-entry into activation, and `wicket_add_group_member()`.

## Data fix

The fix does not repair rows. For a subscription with no row on its start date, set the correct row's `start_date` to the subscription start date in MDP. If a renewal already stored the wrong UUID, set the meta to the correct row.

## Tests

Warden: `qa/tests/Unit/BasePlugin/GroupSubscriptions/GroupSubscriptionMembershipTargetingTest.php` (10 tests, plan list 1-10).

User manuals: `docs/GroupSubscription/option-index/subscription-date-behavior.md`, `docs/GroupSubscription/troubleshooting-index/group-membership-not-extended-on-renewal.md`.
