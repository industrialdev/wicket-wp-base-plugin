<?php

declare(strict_types=1);

namespace WicketWP\WooCommerce;

// No direct access
defined('ABSPATH') || exit;

use WC_Email;
use WC_Order;

/**
 * WooCommerce email blocker for admin-triggered customer emails.
 */
class EmailBlocker
{
    public const OPTION_ENABLED = 'wicket_admin_settings_woo_email_blocker_enabled';
    public const OPTION_ALLOW_REFUNDS = 'wicket_admin_settings_woo_email_blocker_allow_refund_emails';

    private const LEGACY_OPTION_NAME = 'wicket_woo_tweaks';
    private const LEGACY_OPTION_ENABLED = 'wicket_woo_email_blocker_enabled';
    private const LEGACY_OPTION_ALLOW_REFUNDS = 'wicket_woo_email_blocker_allow_refund_emails';

    /**
     * Allowed email ids for this request.
     *
     * @var array<string, bool>
     */
    private array $allowed_email_ids = [];

    /**
     * Register hooks.
     *
     * @return void
     */
    public function init(): void
    {
        if (!$this->is_enabled()) {
            return;
        }

        if (!class_exists('WooCommerce')) {
            return;
        }

        add_action('woocommerce_init', [$this, 'register_email_filters']);
        add_action('woocommerce_before_resend_order_emails', [$this, 'allow_for_resend'], 5, 2);
        add_action('woocommerce_new_customer_note', [$this, 'allow_for_customer_note'], 5, 1);
    }

    /**
     * Register filters for all WooCommerce emails.
     *
     * @return void
     */
    public function register_email_filters(): void
    {
        $mailer = WC()->mailer();
        $emails = $mailer ? $mailer->get_emails() : [];

        foreach ($emails as $email) {
            if (!$email instanceof WC_Email) {
                continue;
            }

            add_filter('woocommerce_email_enabled_' . $email->id, [$this, 'maybe_block_email'], 20, 3);
        }
    }

    /**
     * Allow resend actions.
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
     * Allow customer note email.
     *
     * @param array $args Note args.
     * @return void
     */
    public function allow_for_customer_note(array $args): void
    {
        $this->allowed_email_ids['customer_note'] = true;
    }

    /**
     * Block customer emails during admin order updates unless explicit.
     *
     * @param bool $enabled Email enabled.
     * @param mixed $object Email object context.
     * @param WC_Email $email Email instance.
     * @return bool
     */
    public function maybe_block_email(bool $enabled, $object, $email): bool
    {
        if (!$enabled || !$email instanceof WC_Email) {
            return $enabled;
        }

        if (!$email->is_customer_email()) {
            return $enabled;
        }

        if ($this->is_explicit_send_request($email)) {
            $this->log_decision('allow', 'explicit', $email, $object);
            return $enabled;
        }

        if (!$this->is_admin_order_update_request($object)) {
            return $enabled;
        }

        $this->log_decision('block', 'admin_update', $email, $object);
        return false;
    }

    /**
     * Check if a manual send was requested.
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

        if ($this->is_refund_email($email->id) && $this->allow_refund_emails()) {
            return true;
        }

        return (bool) apply_filters('wicket_woo_email_blocker_allow_send', false, $email);
    }

    /**
     * Determine if the current request is an admin order update.
     *
     * @return bool
     */
    private function is_admin_order_update_request($object = null): bool
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

        if (!current_user_can('edit_post', $post_id)) {
            return false;
        }

        return true;
    }

    /**
     * Ensure the request originated from wp-admin.
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
     * Determine if the current request is an HPOS order edit save.
     *
     * @param int $order_id Order id.
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

        if (!current_user_can('edit_post', $order_id)) {
            return false;
        }

        return true;
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
            return current_user_can('edit_post', $order_id) || current_user_can('manage_woocommerce');
        }

        return current_user_can('edit_shop_orders') || current_user_can('manage_woocommerce');
    }

    /**
     * Resolve order id from the email object or request data.
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
     * Identify relevant admin-side AJAX order actions.
     *
     * @return bool
     */
    private function is_admin_order_ajax_action(): bool
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

        $order_id = $this->get_order_id_from_object_or_request();
        if ($order_id) {
            return current_user_can('edit_post', $order_id);
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
        if ($order_id && !current_user_can('edit_post', $order_id)) {
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
     * Detect customer note requests.
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
     * Detect manual "send to customer" order action.
     *
     * @param WC_Email $email Email instance.
     * @return bool
     */
    private function is_manual_order_action(WC_Email $email): bool
    {
        return 'send_order_details' === $this->get_order_action() && 'customer_invoice' === $email->id;
    }

    /**
     * Get the current order action.
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
     * Check if the email id is a refund email.
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
        return $this->get_setting_bool(self::OPTION_ENABLED, self::LEGACY_OPTION_ENABLED, false);
    }

    /**
     * Allow refund emails when admin triggers a refund.
     *
     * @return bool
     */
    private function allow_refund_emails(): bool
    {
        return $this->get_setting_bool(self::OPTION_ALLOW_REFUNDS, self::LEGACY_OPTION_ALLOW_REFUNDS, false);
    }

    /**
     * Get a settings value from WPSettings or legacy option storage.
     *
     * @param string $key New option key.
     * @param string $legacy_key Legacy option key.
     * @param bool $default Default value.
     * @return bool
     */
    private function get_setting_bool(string $key, string $legacy_key, bool $default): bool
    {
        $settings = get_option('wicket_settings', []);
        if (is_array($settings) && array_key_exists($key, $settings)) {
            return $this->normalize_bool($settings[$key]);
        }

        $legacy = get_option(self::LEGACY_OPTION_NAME, []);
        if (is_array($legacy) && array_key_exists($legacy_key, $legacy)) {
            return $this->normalize_bool($legacy[$legacy_key]);
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
