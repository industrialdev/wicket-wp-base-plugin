# WooCommerce Email Blocker

**Developer reference** — blocks customer emails on admin-initiated order updates.

**Location:** `Wicket → Integrations → WooCommerce`

## Settings

| Setting | Option Key | Default |
|---------|------------|---------|
| Block customer emails on admin updates | `wicket_admin_settings_woo_email_blocker_enabled` | Off |
| Allow refund emails from admin | `wicket_admin_settings_woo_email_blocker_allow_refund_emails` | Off |

## Entry Point

```
src/Main.php → new WooCommerce\EmailBlocker() → EmailBlocker::init()
```

## How It Works

`EmailBlocker::init()` registers hooks only when the setting is enabled and WooCommerce is active:

| Hook | Callback | Priority |
|------|----------|----------|
| `woocommerce_init` | `register_email_filters()` | 10 |
| `woocommerce_before_resend_order_emails` | `allow_for_resend()` | 5 |
| `woocommerce_new_customer_note` | `allow_for_customer_note()` | 5 |

`register_email_filters()` attaches `maybe_block_email()` to all registered WooCommerce emails:

```php
add_filter('woocommerce_email_enabled_{email_id}', [$this, 'maybe_block_email'], 20, 3);
```

### Decision Flow (`maybe_block_email()`)

1. Exit early if email is disabled or not a `WC_Email` instance.
2. Exit early if not a customer email (`$email->is_customer_email()` returns false).
3. Allow if any explicit send condition is true (see below).
4. Block if the request is an admin order update.

### Explicit Send Conditions

Email is allowed when any of these are true:

| Condition | Detection |
|-----------|-----------|
| Resend action | Email ID marked via `allow_for_resend()` hook |
| Manual "Send order details" | `wc_order_action=send_order_details` + `customer_invoice` email |
| Admin adds customer note | AJAX action `woocommerce_add_order_note` with `note_type=customer` |
| Refund email (when allowed) | Email ID is `customer_refunded_order` or `customer_partially_refunded_order` |
| Custom filter override | `wicket_woo_email_blocker_allow_send` returns `true` |

### Admin Order Update Detection

`is_admin_order_update_request()` detects admin updates across all paths:

1. **Admin AJAX** — `wp_doing_ajax()` + `woocommerce_refund_line_items`/`woocommerce_mark_order_status` + capability check
2. **Admin REST** — `REST_REQUEST` constant + logged-in user + capability check
3. **Bulk status update** — `mark_*` action + `bulk-orders`/`bulk-posts` nonce + capability check
4. **HPOS edit save** — `$_POST['action'] === 'edit_order'` + `update-order_{order_id}` nonce
5. **Classic order edit** — `post_ID` + `post_type` + `woocommerce_meta_nonce`

Admin context guard: standard requests require `is_admin()` true; AJAX/REST require HTTP referer containing `/wp-admin/`.

## Extension Point

```php
// Allow a specific email type to bypass the blocker.
add_filter('wicket_woo_email_blocker_allow_send', function (bool $allow, WC_Email $email): bool {
    if ('customer_on_hold_order' === $email->id) {
        return true;
    }
    return $allow;
}, 10, 2);
```

## Logging

Every block/allow decision writes to the WooCommerce log at `info` level:

| Field | Description |
|-------|-------------|
| `source` | `wicket-woo-email-blocker` |
| `email_id` | WooCommerce email ID |
| `decision` | `allow` or `block` |
| `reason` | `explicit` or `admin_update` |
| `order_id` | Order ID (if available) |

Log location: **WooCommerce → Status → Logs → `wicket-woo-email-blocker-*`**

## Troubleshooting

**Emails still being blocked when they shouldn't be:**
1. Check WooCommerce logs for `wicket-woo-email-blocker` source
2. Look for `decision: allow, reason: explicit`
3. Add the `wicket_woo_email_blocker_allow_send` filter for custom flows

**Emails still sending when they should be blocked:**
1. Confirm the setting is enabled in `Wicket → Integrations → WooCommerce`
2. Check request originates from `/wp-admin/` (referer check may fail on custom admin pages)
3. Verify user has `edit_shop_orders` or `manage_woocommerce` capability

**Refund emails not sending:** Enable **Allow refund emails from admin**. Affected email IDs: `customer_refunded_order`, `customer_partially_refunded_order`.

## Source Files

| File | Purpose |
|------|---------|
| `src/WooCommerce/EmailBlocker.php` | Core blocker logic |
| `src/Main.php` | Instantiation and initialization |
| `includes/admin/settings/class-wicket-settings.php` | Settings registration |
