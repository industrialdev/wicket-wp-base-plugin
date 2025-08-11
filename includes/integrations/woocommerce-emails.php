<?php

declare(strict_types=1);

// No direct access
defined('ABSPATH') || exit;

use Jeffreyvr\WPSettings\WPSettings;

/**
 * WooCommerce Email Customizations
 *
 * @package Wicket\Integrations
 */

/**
 * Wicket WooCommerce Emails class
 */
class Wicket_WooCommerce_Emails
{
  /**
   * Constructor of class.
   */
  public function __construct()
  {
    // Register admin menu
    add_action('admin_menu', [$this, 'register_options_page'], 5);
    // Reorder submenu to bottom (priority 999 to run after WPSettings)
    add_action('admin_menu', [$this, 'reorder_submenu'], PHP_INT_MAX);
    // Register WooCommerce email headers filter
    add_filter('woocommerce_email_headers', [$this, 'change_reply_to_email_address'], 10, 4);
  }

  /**
   * Create an options page
   */
  public static function register_options_page()
  {
    // Build settings page using WPSettings
    $settings = new WPSettings(__('Wicket Settings', 'wicket'), 'wicket-woocommerce');
    $settings->set_menu_parent_slug('woocommerce');
    $settings->set_capability('manage_woocommerce');

    // Tab for WooCommerce Emails
    $tab = $settings->add_tab(__('Emails', 'wicket'));

    // Section and options
    $section = $tab->add_section(__('WooCommerce Emails', 'wicket'));
    $section->add_option('text', [
      'name' => 'wicket_woocommerce_reply_to_name',
      'label' => __('Reply To Name', 'wicket'),
      'description' => sprintf(__('Set the name for WooCommerce email reply-to addresses. If left blank, the default WooCommerce value "%s" will be used.', 'wicket'), get_option('woocommerce_email_from_name', '')),
      'css' => [
        'input_class' => 'regular-text',
      ],
    ]);
    $section->add_option('text', [
      'name' => 'wicket_woocommerce_reply_to_email',
      'label' => __('Reply To Email', 'wicket'),
      'description' => sprintf(__('Set the email address for WooCommerce email reply-to addresses. If left blank, the default WooCommerce value "%s" will be used.', 'wicket'), get_option('woocommerce_email_from_address', '')),
      'css' => [
        'input_class' => 'regular-text',
      ],
      'validate' => [
        [
          'feedback' => __('<span style="color: var(--wc-red, red);">Please enter a valid email address.</span>', 'wicket'),
          'callback' => function($value) {
            return empty($value) || is_email($value);
          }
        ]
      ]
    ]);

    // Register and render page
    $settings->make();
  }

  /**
   * Reorder WooCommerce submenu to place Wicket Settings at the end
   */
  public function reorder_submenu()
  {
    global $submenu;
    if (isset($submenu['woocommerce'])) {
      foreach ($submenu['woocommerce'] as $idx => $item) {
        if (isset($item[2]) && $item[2] === 'wicket-woocommerce') {
          $wicket_item = $submenu['woocommerce'][$idx];
          unset($submenu['woocommerce'][$idx]);
          $submenu['woocommerce'][] = $wicket_item;
          break;
        }
      }
    }
  }

  /**
   * Change the "Reply to" email address in all WooCommerce email notifications
   *
   * @param string $header The email header
   * @param string $email_id The email ID
   * @param WC_Order|null $order The order object
   * @param WC_Email|null $email The email object
   * @return string Modified email header
   */
  public function change_reply_to_email_address($header, $email_id, $order, $email)
  {
    // Get settings
    $reply_to_name = get_option('wicket_woocommerce_reply_to_name');
    $reply_to_email = get_option('wicket_woocommerce_reply_to_email');

    // If no settings, return original header
    if (empty($reply_to_name) || empty($reply_to_email)) {
      return $header;
    }

    // Set the reply-to header with UTF-8 encoding and sanitization
    $header = 'Content-Type: ' . $email->get_content_type() . "\r\n";

    // Convert UTF-8 to ISO-8859-1 (replacement for deprecated utf8_decode)
    $converted_name = mb_convert_encoding($reply_to_name, 'ISO-8859-1', 'UTF-8');
    $header .= 'Reply-to: ' . $converted_name . ' <' . sanitize_email($reply_to_email) . ">\r\n";

    return $header;
  }
}

// Initialize the class
new Wicket_WooCommerce_Emails();
