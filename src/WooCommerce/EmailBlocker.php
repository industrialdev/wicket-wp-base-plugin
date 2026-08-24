<?php

declare(strict_types=1);

namespace WicketWP\WooCommerce;

// No direct access
defined('ABSPATH') || exit;

use WC_Email;
use WC_Order;

/**
 * WooCommerce email blocker for admin-triggered order emails.
 *
 * Blocks ALL order emails — customer-facing AND admin-facing (including custom
 * status emails) — when an admin changes order status from wp-admin, AJAX,
 * REST, or bulk actions. Explicit sends (resend, customer notes, manual
 * invoice) always pass through.
 */
class EmailBlocker
{
    public const OPTION_ENABLED = 'wicket_admin_settings_woo_email_blocker_enabled';
    public const OPTION_ALLOW_REFUNDS = 'wicket_admin_settings_woo_email_blocker_allow_refund_emails';

    /**
     * Order meta key: per-order email block flag ('yes' blocks every email tied to the order).
     */
    public const META_BLOCK_ORDER_EMAILS = '_wicket_block_order_emails';

    /**
     * Order meta key: JSON record of an admin-initiated order status change made
     * while the global blocker setting was active: {"from":"...","to":"...","until":<utc ts>}.
     * AutomateWoo validates order-status workflows in a separate Action Scheduler
     * request, where the admin request context is gone, so the transition is
     * captured on the order at status-change time and consumed at validation.
     * Scoped to the transition (not just the order) so later customer-initiated
     * changes on the same order are not blocked.
     *
     * @see mark_admin_updated_order_for_automatewoo()
     */
    public const META_AW_ADMIN_BLOCK_TRANSITION = '_wicket_aw_admin_block_transition';

    /**
     * How long the AutomateWoo admin-update marker stays valid, in seconds.
     * Action Scheduler normally validates within minutes; the window bounds the
     * worst case where a later customer-triggered change must not stay blocked.
     */
    private const AW_ADMIN_BLOCK_WINDOW_SECONDS = 900;

    /**
     * Email IDs explicitly allowed for this request (resend, customer notes).
     *
     * @var array<string, bool>
     */
    private array $allowed_email_ids = [];

    /**
     * Track email IDs we have already hooked to avoid duplicate filters.
     *
     * @var array<string, bool>
     */
    private array $hooked_email_ids = [];

    /**
     * Register hooks.
     *
     * @return void
     */
    public function init(): void
    {
        // Admin notice is registered unconditionally so it can self-check;
        // the remaining hooks require only WooCommerce. Per-order blocking works
        // even when the global blocker setting is off; admin-update blocking
        // inside maybe_block_email() is gated by the setting itself.
        add_action('admin_notices', [$this, 'render_order_edit_notice'], 1);

        if (!class_exists('WooCommerce')) {
            return;
        }

        // Per-order blocking works independently of the global setting.
        add_action('add_meta_boxes', [$this, 'register_order_metabox']);
        add_action('woocommerce_process_shop_order_meta', [$this, 'save_order_metabox'], 10, 2);
        add_filter('automatewoo_custom_validate_workflow', [$this, 'maybe_block_automatewoo_workflow'], 20, 2);

        // AutomateWoo defers order-status triggers to an async Action Scheduler
        // request, so the admin context must be captured on the order now, while
        // the admin request is still running (see META_AW_ADMIN_BLOCK_TRANSITION).
        add_action('woocommerce_order_status_changed', [$this, 'mark_admin_updated_order_for_automatewoo'], 5, 4);

        // Hook every email via the woocommerce_email_classes filter, which fires
        // when WC_Emails initialises naturally — after all plugins (including
        // Event Tickets) have registered their email classes. We deliberately do
        // NOT call WC()->mailer() on woocommerce_init: forcing WC_Emails to build
        // early caches its email list before late-registered emails (e.g. the
        // Event Tickets "wootickets" email) are added, permanently dropping them
        // and silently breaking ticket delivery.
        add_filter('woocommerce_email_classes', [$this, 'register_filters_for_custom_emails'], PHP_INT_MAX);

        // Explicit-send allowlists
        add_action('woocommerce_before_resend_order_emails', [$this, 'allow_for_resend'], 5, 2);
        add_action('woocommerce_new_customer_note', [$this, 'allow_for_customer_note'], 5, 1);
    }

    /**
     * Catch email classes added after the initial registration.
     *
     * Runs at PHP_INT_MAX so every other plugin has already added its classes.
     *
     * @param array $email_classes Email classes array.
     * @return array Unchanged — we only observe.
     */
    public function register_filters_for_custom_emails(array $email_classes): array
    {
        foreach ($email_classes as $email) {
            $this->hook_email($email);
        }

        return $email_classes;
    }

    /**
     * Hook a single email instance if not already hooked.
     *
     * @param mixed $email Email instance.
     * @return void
     */
    private function hook_email($email): void
    {
        if (!$email instanceof WC_Email) {
            return;
        }

        if (isset($this->hooked_email_ids[$email->id])) {
            return;
        }

        add_filter('woocommerce_email_enabled_' . $email->id, [$this, 'maybe_block_email'], 20, 3);
        $this->hooked_email_ids[$email->id] = true;
    }

    /**
     * Register the per-order "block all emails" checkbox on order edit screens (HPOS + legacy).
     *
     * @return void
     */
    public function register_order_metabox(): void
    {
        if (!function_exists('wc_get_page_screen_id')) {
            return;
        }

        $screens = array_filter([
            wc_get_page_screen_id('shop-order'), // HPOS edit + new order screens.
            'shop_order',                          // Legacy CPT edit screen.
        ]);

        foreach (array_unique($screens) as $screen) {
            add_meta_box(
                'wicket-order-email-block',
                __('Wicket Email Blocking', 'wicket'),
                [$this, 'render_order_metabox'],
                $screen,
                'side',
                'default'
            );
        }
    }

    /**
     * Render the per-order email block checkbox.
     *
     * @param mixed $post_or_order WP_Post or WC_Order passed by the metabox renderer.
     * @return void
     */
    public function render_order_metabox($post_or_order = null): void
    {
        $order = $this->get_order_being_edited($post_or_order);
        $checked = $order ? $this->order_blocks_emails($order) : false;

        wp_nonce_field('wicket_block_order_emails', 'wicket_block_order_emails_nonce');
        ?>
        <p>
            <label for="wicket_block_order_emails">
                <input type="checkbox" name="wicket_block_order_emails" id="wicket_block_order_emails" value="1"<?php checked($checked); ?> />
                <?php esc_html_e('Block all emails for this order', 'wicket'); ?>
            </label>
        </p>
        <p class="description">
            <?php esc_html_e('Suppresses WooCommerce order emails and AutomateWoo workflows tied to this order. Resends from Order actions still send.', 'wicket'); ?>
        </p>
        <?php
    }

    /**
     * Save the per-order email block flag on order save (HPOS + legacy).
     *
     * @param int $order_id Order ID.
     * @param mixed $order WC_Order or WP_Post depending on the storage engine.
     * @return void
     */
    public function save_order_metabox($order_id, $order = null): void
    {
        $order = $order instanceof WC_Order ? $order : (function_exists('wc_get_order') ? wc_get_order((int) $order_id) : false);

        if (!$order instanceof WC_Order) {
            return;
        }

        if (empty($_POST['wicket_block_order_emails_nonce'])
            || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['wicket_block_order_emails_nonce'])), 'wicket_block_order_emails')) {
            return;
        }

        if (!$this->current_user_can_edit_order((int) $order->get_id())) {
            return;
        }

        $block = !empty($_POST['wicket_block_order_emails']);
        $order->update_meta_data(self::META_BLOCK_ORDER_EMAILS, $block ? 'yes' : 'no');
        $order->save();
    }

    /**
     * Resolve the order being edited on an order edit screen.
     *
     * @param mixed $post_or_order WP_Post or WC_Order passed by the metabox renderer.
     * @return WC_Order|null
     */
    private function get_order_being_edited($post_or_order): ?WC_Order
    {
        if ($post_or_order instanceof WC_Order) {
            return $post_or_order;
        }

        $order_id = 0;

        if ($post_or_order instanceof WP_Post) {
            $order_id = (int) $post_or_order->ID;
        } elseif (!empty($_GET['id'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $order_id = absint($_GET['id']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        } elseif (!empty($_GET['post'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $order_id = absint($_GET['post']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }

        if (!$order_id || !function_exists('wc_get_order')) {
            return null;
        }

        $order = wc_get_order($order_id);

        return $order instanceof WC_Order ? $order : null;
    }

    /**
     * Check if the order carries the per-order email block flag.
     *
     * @param WC_Order $order Order object.
     * @return bool
     */
    private function order_blocks_emails(WC_Order $order): bool
    {
        return 'yes' === $order->get_meta(self::META_BLOCK_ORDER_EMAILS);
    }

    /**
     * Record on the order that this status change came from an admin update
     * while the global blocker setting is active.
     *
     * AutomateWoo order-status triggers run their validation in a later Action
     * Scheduler request that has no admin context. This runs synchronously in
     * the admin request (priority 5, before AutomateWoo schedules its async
     * event at 50) and writes a short-lived marker the AutomateWoo validation
     * callback consumes later.
     *
     * @param int $order_id Order ID.
     * @param string $from_status Old status.
     * @param string $to_status New status.
     * @param mixed $order WC_Order when the hook passes it, null otherwise.
     * @return void
     */
    public function mark_admin_updated_order_for_automatewoo(int $order_id, string $from_status, string $to_status, $order = null): void
    {
        if (!$this->is_enabled()) {
            return;
        }

        $order = $order instanceof WC_Order ? $order : (function_exists('wc_get_order') ? wc_get_order($order_id) : false);

        if (!$order instanceof WC_Order) {
            return;
        }

        if (!$this->is_admin_order_context($order)) {
            return;
        }

        $order->update_meta_data(
            self::META_AW_ADMIN_BLOCK_TRANSITION,
            wp_json_encode([
                'from' => $from_status,
                'to' => $to_status,
                'until' => time() + $this->automatewoo_admin_block_window(),
            ])
        );
        // Safe inside the status-changed hook: WC_Order::save() clears the pending
        // status transition before woocommerce_order_status_changed fires, so this
        // nested save cannot re-enter the transition or this hook.
        $order->save();
    }

    /**
     * Whether an admin-update marker on this order blocks the given workflow.
     *
     * The marker is blocked only while unexpired AND the workflow's trigger
     * targets the same status the admin changed the order to. That keeps a
     * customer-initiated transition on the same order (for example a later
     * payment webhook firing an order-paid workflow) sending normally.
     * Triggers without a target status (generic "any status change" or
     * order-paid triggers) are not blocked by the marker.
     *
     * @param WC_Order $order Order object.
     * @param mixed $workflow AutomateWoo\Workflow instance.
     * @return bool
     */
    private function admin_update_marker_blocks(WC_Order $order, $workflow): bool
    {
        $marker = json_decode((string) $order->get_meta(self::META_AW_ADMIN_BLOCK_TRANSITION), true);

        if (!is_array($marker) || (int) ($marker['until'] ?? 0) <= time()) {
            return false;
        }

        if (!is_object($workflow) || !method_exists($workflow, 'get_trigger')) {
            return false;
        }

        $trigger = $workflow->get_trigger();
        $target_status = is_object($trigger) && isset($trigger->target_status) && is_string($trigger->target_status)
            ? $trigger->target_status
            : '';

        return $target_status !== '' && $target_status === ($marker['to'] ?? '');
    }

    /**
     * AutomateWoo admin-update marker lifetime in seconds.
     *
     * @return int
     */
    private function automatewoo_admin_block_window(): int
    {
        return (int) apply_filters('wicket_woo_email_blocker_aw_admin_block_window', self::AW_ADMIN_BLOCK_WINDOW_SECONDS);
    }

    /**
     * Allow resend actions initiated by admin.
     *
     * @param WC_Order $order Order object.
     * @param string $email_id Email id.
     * @return void
     */
    public function allow_for_resend($order, string $email_id): void
    {
        $this->allowed_email_ids[$email_id] = true;
    }

    /**
     * Allow customer note email when admin adds a customer-visible note.
     *
     * @param array $args Note args.
     * @return void
     */
    public function allow_for_customer_note(array $args): void
    {
        $this->allowed_email_ids['customer_note'] = true;
    }

    /**
     * Show a warning notice on order edit screens when the blocker is active.
     *
     * @return void
     */
    public function render_order_edit_notice(): void
    {
        if (!$this->is_enabled() || !$this->is_order_edit_screen()) {
            return;
        }

        $refund_note = $this->allow_refund_emails()
            ? ' ' . __('Refund emails are allowed.', 'wicket')
            : '';

        printf(
            '<div class="notice notice-warning"><p><strong>%s</strong> %s%s</p></div>',
            esc_html__('Email Blocker Active:', 'wicket'),
            esc_html__('No order emails will be sent when changing order status. Use Order actions or add a customer note to send emails explicitly.', 'wicket'),
            esc_html($refund_note)
        );
    }

    /**
     * Check if the current admin screen is a WooCommerce order edit page.
     *
     * Supports both HPOS (wc-orders) and legacy (post.php with shop_order).
     * Uses $_GET params for HPOS detection since the custom admin page may
     * have varying screen IDs depending on menu registration.
     *
     * @return bool
     */
    private function is_order_edit_screen(): bool
    {
        if (!is_admin()) {
            return false;
        }

        $page = sanitize_key($_GET['page'] ?? '');   // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $action = sanitize_key($_GET['action'] ?? ''); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        // HPOS: admin.php?page=wc-orders&action=edit (or wc-orders--{type})
        if ($page && str_starts_with($page, 'wc-orders') && in_array($action, ['edit', 'new'], true)) {
            return true;
        }

        // Legacy CPT: post.php?post=X&action=edit with shop_order type
        if ('edit' === $action && !empty($_GET['post'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $post_type = get_post_type(absint($_GET['post']));
            if ($post_type && in_array($post_type, wc_get_order_types(), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Block all order emails during admin order updates unless explicitly sent.
     *
     * @param bool $enabled Email enabled.
     * @param mixed $object Email object context (usually a WC_Order).
     * @param WC_Email $email Email instance.
     * @return bool
     */
    public function maybe_block_email(bool $enabled, $object, $email): bool
    {
        if (!$enabled || !$email instanceof WC_Email) {
            return $enabled;
        }

        // Explicit sends always pass through
        if ($this->is_explicit_send_request($email)) {
            $this->log_decision('allow', 'explicit', $email, $object);

            return $enabled;
        }

        // Per-order flag: block every non-explicit email tied to this order
        $order = $this->resolve_order($object);
        if ($order && $this->order_blocks_emails($order)) {
            $this->log_decision('block', 'order_email_block_flag', $email, $object);

            return false;
        }

        // Global setting gates the admin-update block
        if (!$this->is_enabled()) {
            return $enabled;
        }

        // Only block when the trigger is an admin action
        if (!$this->is_admin_order_context($object)) {
            return $enabled;
        }

        $this->log_decision('block', 'admin_update', $email, $object);

        return false;
    }

    /**
     * Check if the email should be explicitly allowed through.
     *
     * Order of checks:
     * 1. Always-allowed fulfilment emails (e.g. Event Tickets ticket emails)
     * 2. Pre-registered allow list (resend action, customer note action)
     * 3. Manual "Email invoice / order details to customer" order action
     * 4. Customer note added via AJAX
     * 5. Refund emails (only when the allow-refund setting is ON)
     * 6. Third-party opt-in via filter
     *
     * @param WC_Email $email Email instance.
     * @return bool
     */
    private function is_explicit_send_request(WC_Email $email): bool
    {
        // Fulfilment emails are part of a front-end purchase and must always
        // reach the customer, regardless of who triggered the order update.
        if (in_array($email->id, $this->always_allowed_email_ids(), true)) {
            return true;
        }

        if (isset($this->allowed_email_ids[$email->id])) {
            return true;
        }

        if ($this->is_manual_order_action($email)) {
            return true;
        }

        if ($this->is_customer_note_request() && 'customer_note' === $email->id) {
            return true;
        }

        // Refund emails: respect the allow-refund setting
        if ($this->is_refund_email($email->id) && $this->allow_refund_emails()) {
            return true;
        }

        return (bool) apply_filters('wicket_woo_email_blocker_allow_send', false, $email);
    }

    /**
     * WooCommerce email IDs that must always send, even during admin order updates.
     *
     * These are customer fulfilment emails delivered as part of a front-end
     * purchase (e.g. Event Tickets ticket emails), not admin-edit notifications,
     * so blocking them would withhold something the customer paid for.
     *
     * @return array<int, string>
     */
    private function always_allowed_email_ids(): array
    {
        $ids = [
            'wootickets', // Event Tickets Plus — WooCommerce ticket email.
        ];

        /*
         * Filters the WooCommerce email IDs the blocker always allows through.
         *
         * @param array<int, string> $ids Email IDs that bypass the admin-update block.
         */
        return (array) apply_filters('wicket_woo_email_blocker_always_allowed_email_ids', $ids);
    }

    /**
     * Determine if the current request is an admin order context.
     *
     * Simplified check: if the request originates from wp-admin (page load,
     * AJAX, or REST with admin referer) and the user can manage orders, then
     * any email triggered during this request is admin-initiated. This catches
     * every admin path: HPOS edit, legacy post edit, AJAX mark-status, bulk
     * actions, REST updates, and custom status transitions.
     *
     * @param mixed $object Email object context.
     * @return bool
     */
    private function is_admin_order_context($object = null): bool
    {
        if (!$this->is_wp_admin_context()) {
            return false;
        }

        if (wp_doing_ajax()) {
            return $this->is_admin_order_ajax_action($object);
        }

        if ($this->is_rest_admin_order_request($object)) {
            return true;
        }

        if (!is_admin()) {
            return false;
        }

        if ($this->is_admin_bulk_order_status_request($object)) {
            return true;
        }

        $order_id = $this->get_order_id_from_object_or_request($object);
        if ($order_id && $this->is_hpos_edit_order_request($order_id)) {
            return true;
        }

        if (empty($_POST['post_ID']) || empty($_POST['post_type']) || empty($_POST['woocommerce_meta_nonce'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return false;
        }

        $post_id = absint($_POST['post_ID']);
        $post_type = sanitize_key(wp_unslash($_POST['post_type']));

        if (!$post_id || !in_array($post_type, wc_get_order_types('order-meta-boxes'), true)) {
            return false;
        }

        return $this->current_user_can_edit_order($post_id);
    }

    /**
     * Ensure the request originated from wp-admin.
     *
     * For regular page loads: is_admin().
     * For AJAX / REST: check HTTP_REFERER for /wp-admin/.
     *
     * @return bool
     */
    private function is_wp_admin_context(): bool
    {
        if (!wp_doing_ajax() && (!defined('REST_REQUEST') || !REST_REQUEST)) {
            return is_admin();
        }

        $referer = wp_get_referer();
        if (!$referer && !empty($_SERVER['HTTP_REFERER'])) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $referer = sanitize_text_field(wp_unslash($_SERVER['HTTP_REFERER']));
        }

        if (!$referer) {
            return false;
        }

        return false !== strpos($referer, '/wp-admin/');
    }

    /**
     * Resolve order ID from the email object or request data.
     *
     * @param mixed $object Email object context.
     * @return int
     */
    private function get_order_id_from_object_or_request($object = null): int
    {
        if (is_object($object) && method_exists($object, 'get_id')) {
            return (int) $object->get_id();
        }

        $keys = ['order_id', 'id', 'post_ID'];
        foreach ($keys as $key) {
            if (!empty($_REQUEST[$key])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
                return absint(wp_unslash($_REQUEST[$key]));
            }
        }

        return 0;
    }

    /**
     * Determine if the current request is an HPOS order edit save.
     *
     * @param int $order_id Order ID.
     * @return bool
     */
    private function is_hpos_edit_order_request(int $order_id): bool
    {
        if (empty($_POST['action']) || empty($_POST['_wpnonce'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return false;
        }

        $action = sanitize_key(wp_unslash($_POST['action']));
        if ('edit_order' !== $action) {
            return false;
        }

        $nonce = wp_unslash($_POST['_wpnonce']);
        if (!wp_verify_nonce($nonce, 'update-order_' . $order_id)) {
            return false;
        }

        return $this->current_user_can_edit_order($order_id);
    }

    /**
     * Determine if the current REST request is an admin order update.
     *
     * @param mixed $object Email object context.
     * @return bool
     */
    private function is_rest_admin_order_request($object = null): bool
    {
        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            return false;
        }

        if (!is_user_logged_in()) {
            return false;
        }

        $order_id = $this->get_order_id_from_object_or_request($object);
        if ($order_id) {
            return $this->current_user_can_edit_order($order_id);
        }

        return current_user_can('edit_shop_orders') || current_user_can('manage_woocommerce');
    }

    /**
     * Identify relevant admin-side AJAX order actions.
     *
     * @param mixed $object Email object context.
     * @return bool
     */
    private function is_admin_order_ajax_action($object = null): bool
    {
        if (empty($_REQUEST['action'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return false;
        }

        $action = sanitize_key(wp_unslash($_REQUEST['action']));

        if (!in_array(
            $action,
            [
                'woocommerce_refund_line_items',
                'woocommerce_mark_order_status',
            ],
            true
        )) {
            return false;
        }

        $order_id = $this->get_order_id_from_object_or_request($object);
        if ($order_id) {
            return $this->current_user_can_edit_order($order_id);
        }

        return current_user_can('edit_shop_orders') || current_user_can('manage_woocommerce');
    }

    /**
     * Detect admin bulk order status changes.
     *
     * @param mixed $object Email object context.
     * @return bool
     */
    private function is_admin_bulk_order_status_request($object = null): bool
    {
        $action = $this->get_bulk_action();
        if (!$action || 0 !== strpos($action, 'mark_')) {
            return false;
        }

        $order_ids = $this->get_bulk_order_ids();
        if (empty($order_ids)) {
            return false;
        }

        if (!$this->verify_bulk_nonce()) {
            return false;
        }

        $order_id = $this->get_order_id_from_object_or_request($object);
        if ($order_id && !$this->current_user_can_edit_order($order_id)) {
            return false;
        }

        return current_user_can('edit_shop_orders') || current_user_can('manage_woocommerce');
    }

    /**
     * Resolve bulk action name from request.
     *
     * @return string
     */
    private function get_bulk_action(): string
    {
        $action = '';

        if (!empty($_REQUEST['action']) && '-1' !== $_REQUEST['action']) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $action = sanitize_key(wp_unslash($_REQUEST['action']));
        } elseif (!empty($_REQUEST['action2']) && '-1' !== $_REQUEST['action2']) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $action = sanitize_key(wp_unslash($_REQUEST['action2']));
        }

        return $action;
    }

    /**
     * Get order IDs from bulk request.
     *
     * @return int[]
     */
    private function get_bulk_order_ids(): array
    {
        if (!empty($_REQUEST['id'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return array_values(array_filter(array_map('absint', (array) wp_unslash($_REQUEST['id']))));
        }

        if (!empty($_REQUEST['post'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return array_values(array_filter(array_map('absint', (array) wp_unslash($_REQUEST['post']))));
        }

        return [];
    }

    /**
     * Verify bulk action nonce for order list tables.
     *
     * @return bool
     */
    private function verify_bulk_nonce(): bool
    {
        if (empty($_REQUEST['_wpnonce'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return false;
        }

        $nonce = wp_unslash($_REQUEST['_wpnonce']);

        return wp_verify_nonce($nonce, 'bulk-orders') || wp_verify_nonce($nonce, 'bulk-posts');
    }

    /**
     * Check if the current user can edit a given order.
     *
     * HPOS-compatible: order IDs may not exist in wp_posts when custom tables
     * are used with sync off, causing edit_post to always fail.
     *
     * @param int $order_id Order ID.
     * @return bool
     */
    private function current_user_can_edit_order(int $order_id): bool
    {
        if ($order_id && function_exists('wc_get_order')) {
            $order = wc_get_order($order_id);
            if ($order) {
                return current_user_can('edit_shop_orders');
            }
        }

        return current_user_can('edit_post', $order_id);
    }

    /**
     * Detect customer note requests via AJAX.
     *
     * @return bool
     */
    private function is_customer_note_request(): bool
    {
        if (!wp_doing_ajax()) {
            return false;
        }

        if (empty($_POST['action']) || empty($_POST['note_type'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return false;
        }

        $action = sanitize_key(wp_unslash($_POST['action']));
        $note_type = sanitize_key(wp_unslash($_POST['note_type']));

        return 'woocommerce_add_order_note' === $action && 'customer' === $note_type;
    }

    /**
     * Detect manual "Email invoice / order details to customer" order action.
     *
     * @param WC_Email $email Email instance.
     * @return bool
     */
    private function is_manual_order_action(WC_Email $email): bool
    {
        return 'send_order_details' === $this->get_order_action() && 'customer_invoice' === $email->id;
    }

    /**
     * Get the current order action from the order edit form.
     *
     * @return string
     */
    private function get_order_action(): string
    {
        if (empty($_POST['wc_order_action'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return '';
        }

        return sanitize_key(wp_unslash($_POST['wc_order_action']));
    }

    /**
     * Check if the email ID is a refund email.
     *
     * @param string $email_id Email id.
     * @return bool
     */
    private function is_refund_email(string $email_id): bool
    {
        return in_array(
            $email_id,
            [
                'customer_refunded_order',
                'customer_partially_refunded_order',
            ],
            true
        );
    }

    /**
     * Check if the blocker is enabled.
     *
     * @return bool
     */
    private function is_enabled(): bool
    {
        return $this->get_setting_bool(self::OPTION_ENABLED, false);
    }

    /**
     * Whether refund emails should pass through when admin triggers a refund.
     *
     * @return bool
     */
    private function allow_refund_emails(): bool
    {
        return $this->get_setting_bool(self::OPTION_ALLOW_REFUNDS, false);
    }

    /**
     * Get a settings value from WPSettings.
     *
     * @param string $key Option key.
     * @param bool $default Default value.
     * @return bool
     */
    private function get_setting_bool(string $key, bool $default): bool
    {
        $settings = get_option('wicket_settings', []);
        if (is_array($settings) && array_key_exists($key, $settings)) {
            return $this->normalize_bool($settings[$key]);
        }

        return $default;
    }

    /**
     * Normalize a value into a boolean.
     *
     * @param mixed $value Raw value.
     * @return bool
     */
    private function normalize_bool($value): bool
    {
        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    /**
     * Block AutomateWoo workflows tied to an order when emails must not send.
     *
     * Runs at trigger-time validation, before a workflow is queued, so blocked
     * workflows never enter the queue. Three block conditions:
     *
     * 1. The workflow's order carries the per-order email block flag.
     * 2. The order carries an unexpired admin-update marker for the same target
     *    status as the workflow's trigger: the status change came from an admin
     *    update while the blocker setting was active, and AutomateWoo validates
     *    asynchronously (see mark_admin_updated_order_for_automatewoo()).
     * 3. The blocker setting is enabled and validation happens synchronously
     *    inside the admin order action itself (non-deferred triggers).
     *
     * @param bool $valid Current validation result.
     * @param mixed $workflow AutomateWoo\Workflow instance.
     * @return bool
     */
    public function maybe_block_automatewoo_workflow(bool $valid, $workflow): bool
    {
        if (!$valid || !is_object($workflow) || !method_exists($workflow, 'data_layer')) {
            return $valid;
        }

        $order = null;

        try {
            $data_layer = $workflow->data_layer();
            $order = $data_layer ? $data_layer->get_item('order') : null;
        } catch (\Throwable $e) {
            return $valid;
        }

        if (!$order instanceof WC_Order) {
            return $valid;
        }

        if ($this->order_blocks_emails($order)) {
            $this->log_automatewoo_decision('block', 'order_email_block_flag', $workflow, $order);

            return false;
        }

        if ($this->admin_update_marker_blocks($order, $workflow)) {
            $this->log_automatewoo_decision('block', 'admin_update_async', $workflow, $order);

            return false;
        }

        if ($this->is_enabled() && $this->is_admin_order_context($order)) {
            $this->log_automatewoo_decision('block', 'admin_update', $workflow, $order);

            return false;
        }

        return $valid;
    }

    /**
     * Resolve an order object from the email context object or request data.
     *
     * @param mixed $object Email object context.
     * @return WC_Order|null
     */
    private function resolve_order($object): ?WC_Order
    {
        if ($object instanceof WC_Order) {
            return $object;
        }

        $order_id = $this->get_order_id_from_object_or_request($object);

        if ($order_id && function_exists('wc_get_order')) {
            $order = wc_get_order($order_id);

            return $order instanceof WC_Order ? $order : null;
        }

        return null;
    }

    /**
     * Log an AutomateWoo block decision.
     *
     * @param string $decision allow|block.
     * @param string $reason Reason key.
     * @param mixed $workflow AutomateWoo\Workflow instance.
     * @param WC_Order $order Order object.
     * @return void
     */
    private function log_automatewoo_decision(string $decision, string $reason, $workflow, WC_Order $order): void
    {
        $context = [
            'decision' => $decision,
            'reason' => $reason,
            'order_id' => $order->get_id(),
            'workflow_id' => is_object($workflow) && method_exists($workflow, 'get_id') ? $workflow->get_id() : null,
            'workflow_title' => is_object($workflow) && method_exists($workflow, '__get') ? $workflow->title : null,
            'source' => 'wicket-woo-email-blocker',
        ];

        \Wicket()->log()->info('AutomateWoo workflow block decision recorded.', $context);
    }

    /**
     * Log allow/block decisions for UAT visibility.
     *
     * @param string $decision allow|block.
     * @param string $reason Reason key.
     * @param WC_Email $email Email instance.
     * @param mixed $object Email object context.
     * @return void
     */
    private function log_decision(string $decision, string $reason, WC_Email $email, $object): void
    {
        $order_id = null;
        if (is_object($object) && method_exists($object, 'get_id')) {
            $order_id = $object->get_id();
        }

        $context = [
            'email_id' => $email->id,
            'decision' => $decision,
            'reason' => $reason,
            'order_id' => $order_id,
            'order_action' => $this->get_order_action(),
            'source' => 'wicket-woo-email-blocker',
        ];

        \Wicket()->log()->info('Woo email blocker decision recorded.', $context);
    }
}
