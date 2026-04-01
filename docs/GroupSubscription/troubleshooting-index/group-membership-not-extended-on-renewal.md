# Troubleshooting: Group Membership Did Not Get Extended When the Subscription Renewed

## Question

My group membership did not get extended when the subscription renewed.

## Answer

When a subscription renews successfully, the plugin is supposed to automatically update the group membership's end date in Wicket to the subscription's new next payment date. If that did not happen, work through the following checks in order.

---

### 1. Confirm the global feature toggle is enabled

Go to **Wicket > Settings > Integrations > WooCommerce** and check that **Assign people to groups on product purchase** is turned on.

This is the master switch for all group membership automation. If it is off, the renewal hook runs but immediately exits without making any changes to Wicket. Every automated behavior — including renewal date extension — is gated behind this setting.

---

### 2. Confirm the subscription's product is in the group product category

In the same settings screen, note the value selected for **Group Assignment Product Category**.

The renewal logic only runs for subscriptions whose products belong to this category. If the product was moved to a different category at any point, or if the category setting was changed after the subscription was created, the renewal handler will find no matching group products and will silently do nothing.

To check: open the product in WooCommerce and confirm it belongs to the category shown in the setting.

---

### 3. Check whether the original group membership was ever created

The renewal extension works by looking up the person's existing group membership in Wicket and updating its end date. If no group membership was created when the subscription first became active, there is nothing to extend.

Open the subscription in WooCommerce and read the order notes. Look for a note that says **"Group subscription added or exists for [Group Name]"** dated around the time the subscription was originally purchased. If no such note exists, the initial membership creation failed.

**To recover:** In the subscription detail screen, manually change the **Next Payment Date** to any future date and save. This triggers the same code path that fires on renewal and, if no membership is found, it will attempt to create one from scratch before updating the date. Once the membership exists in Wicket, future renewals will extend it normally.

---

### 4. Check the WooCommerce logs for API errors

Go to **WooCommerce > Status > Logs** and look for a log source named **wicket-group-sync**. Filter to the date of the renewal and look for any error entries. API errors from Wicket (such as authentication failures or invalid group/role references) will appear here and will explain precisely why the update did not go through.

---

### 5. Confirm the renewal payment actually completed

The extension only fires after a renewal payment is successfully processed — not when a renewal is attempted but fails or is pending. Go to the subscription's renewal orders and confirm the most recent renewal order has a status of **Completed** or **Processing**. If the payment failed or is on-hold, the membership will not be extended until the payment succeeds.
