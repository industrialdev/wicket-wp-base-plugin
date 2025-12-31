<?php

declare(strict_types=1);

namespace WicketWP\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Brain\Monkey;

abstract class AbstractTestCase extends PHPUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // Set up global $wpdb mock
        global $wpdb;
        if (!isset($wpdb)) {
            $wpdb = new class {
                public $prefix = 'wp_';
                public $options = 'wp_options';
                public function get_col($query) { return []; }
                public function prepare($query, ...$args) { return $query; }
                public function esc_like($text) { return addcslashes($text, '_%\\'); }
            };
            $GLOBALS['wpdb'] = $wpdb;
        }

        // Default WordPress mocks
        Monkey\Functions\stubs([
            'add_action' => null,
            'add_filter' => null,
            '__',
            'esc_html__',
            'esc_attr__',
            'esc_html',
            'esc_attr',
            'esc_url',
            'esc_url_raw',
            'is_email' => true,
            'sanitize_text_field',
            'wp_unslash',
            'wp_safe_redirect',
            'home_url',
            'get_current_user_id' => 0,
            'get_user_meta',
            'wp_set_current_user',
            'update_user_meta',
            'delete_user_meta',
            'is_ssl' => false,
            'wp_doing_ajax' => false,
            'get_userdata',
            'add_query_arg',
            'wp_date',
            'get_option',
            'get_bloginfo',
            'get_theme_mod',
            'admin_url',
            'wp_mail',
            'COOKIEPATH' => '/',
            'COOKIE_DOMAIN' => 'localhost',
            'DAY_IN_SECONDS' => 86400,
            'MINUTE_IN_SECONDS' => 60,
            'HOUR_IN_SECONDS' => 3600,
            'wp_kses_post',
        ]);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }
}
