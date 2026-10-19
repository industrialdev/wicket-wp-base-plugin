# Option Behavior: Subscription Date Changes and Group Membership

The Group Subscription feature uses WooCommerce subscription dates to drive all group membership date management in Wicket. There is no separate date field to fill in — the subscription's own dates are the single source of truth. Every time a relevant date changes (whether automatically by the system or manually by an admin), Wicket is updated to match.

The behaviors below apply to any subscription whose products belong to the configured group product category. All changes are logged as order notes on the subscription for audit reference.

Each subscription records the group membership it created or found in Wicket. Renewals, date changes, and cancellations change only that membership, even when the person has other memberships in the same group.

---

## Subscription Becomes Active

**Trigger:** Automatic — fires when a subscription transitions to Active status after purchase.

**What happens:**

The plugin creates a new group membership in Wicket for the subscriber. The membership is created with:

- **Start date** — the subscription's start date
- **End date** — the subscription's next scheduled payment date

If the subscription has no next payment date (for example, a lifetime or fixed-end subscription), the subscription's end date is used instead.

If the person is already an active member of the same group with the same role, the plugin skips creation to avoid duplicates and records that the membership already exists.

If the subscription already has a recorded membership for the group, the plugin does not create another one. This applies when a subscription is reactivated, and when it becomes active again after a renewal payment.

If the plugin cannot check Wicket for an existing membership, it does not create one. The order note reads **"Failed group subscription for [Group Name]"**.

**Subscriptions created before this behavior:** these have no recorded membership. At the next activation, renewal, date change, or cancellation, the plugin finds the person's membership in the group, with the same role, that starts on the subscription's start date. It records that membership and uses it from then on. The order note reads **"Group subscription linked to the existing group membership for [Group Name]"**.

---

## Subscription Renews (Automatic Renewal Payment Completes)

**Trigger:** Automatic — fires when a renewal payment is successfully processed by WooCommerce Subscriptions.

**What happens:**

The plugin updates the end date of the subscription's own Wicket group membership to the subscription's new next payment date. This extends the membership by one billing cycle, keeping the person's access in Wicket current with their paid period.

---

## Next Payment Date Changed Manually

**Trigger:** Manual — fires when an admin edits the subscription and changes the "Next Payment Date" field directly on the subscription detail screen in WooCommerce.

**What happens:**

The plugin immediately pushes the new date to Wicket as the end date of the subscription's own group membership. This lets an admin grant a short extension or bring a date forward without waiting for a renewal to occur.

**Special recovery behavior:** If this fires on a subscription where the group membership was never successfully created (for example, it failed at purchase), the plugin will attempt to create the group membership from scratch before updating the date. This provides a manual recovery path: an admin can reactivate or adjust the date on a problem subscription to trigger membership creation without needing to re-process the order.

---

## Subscription is Cancelled

**Trigger:** Automatic or manual — fires whether a customer self-cancels or an admin cancels the subscription from the backend.

**What happens:**

The plugin sets the end date of the subscription's own Wicket group membership to the subscription's recorded end date, which is the moment the cancellation was processed. This effectively ends the person's group membership in Wicket immediately rather than waiting for a natural expiry.
