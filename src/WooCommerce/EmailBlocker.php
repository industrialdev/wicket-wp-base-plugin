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
        // the email-blocking hooks still require both the setting and WooCommerce.
        add_action('admin_notices', [$this, 'render_order_edit_notice'], 1);

        if (!$this->is_enabled()) {
            return;
        }

        if (!class_exists('WooCommerce')) {
            return;
        }

        // Register filters for all known emails at WooCommerce init
        add_action('woocommerce_init', [$this, 'register_email_filters']);

        // Catch custom email classes registered by third-party plugins
        add_filter('woocommerce_email_classes', [$this, 'register_filters_for_custom_emails'], PHP_INT_MAX);

        // Explicit-send allowlists
        add_action('woocommerce_before_resend_order_emails', [$this, 'allow_for_resend'], 5, 2);
        add_action('woocommerce_new_customer_note', [$this, 'allow_for_customer_note'], 5, 1);
    }

    /**
     * Register filters for all WooCommerce emails available at init.
     *
     * @return void
     */
    public function register_email_filters(): void
    {
        $mailer = WC()->mailer();
        $emails = $mailer ? $mailer->get_emails() : [];

        foreach ($emails as $email) {
            $this->hook_email($email);
        }
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
     * 1. Pre-registered allow list (resend action, customer note action)
     * 2. Manual "Email invoice / order details to customer" order action
     * 3. Customer note added via AJAX
     * 4. Refund emails (only when the allow-refund setting is ON)
     * 5. Third-party opt-in via filter
     *
     * @param WC_Email $email Email instance.
     * @return bool
     */
    private function is_explicit_send_request(WC_Email $email): bool
    {
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

        // Verify the user has order-editing capability
        $order_id = $this->get_order_id_from_object_or_request($object);
        if ($order_id) {
            return $this->current_user_can_edit_order($order_id);
        }

        return current_user_can('edit_shop_orders') || current_user_can('manage_woocommerce');
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
        if (!function_exists('wc_get_logger')) {
            return;
        }

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

        wc_get_logger()->info('Woo email blocker decision recorded.', $context);
    }
}
