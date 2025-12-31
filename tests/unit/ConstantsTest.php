<?php

declare(strict_types=1);

namespace WicketWP\Tests;

use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass('WicketWP\Constants')]
class ConstantsTest extends AbstractTestCase
{
    public function test_wicket_url_is_defined(): void
    {
        $this->assertTrue(defined('WICKET_URL'), 'WICKET_URL constant should be defined');
    }

    public function test_wicket_url_is_string(): void
    {
        $this->assertIsString(WICKET_URL, 'WICKET_URL should be a string');
    }

    public function test_wicket_basename_is_defined(): void
    {
        $this->assertTrue(defined('WICKET_BASENAME'), 'WICKET_BASENAME constant should be defined');
    }

    public function test_wicket_basename_is_string(): void
    {
        $this->assertIsString(WICKET_BASENAME, 'WICKET_BASENAME should be a string');
    }

    public function test_wicket_plugin_dir_is_defined(): void
    {
        $this->assertTrue(defined('WICKET_PLUGIN_DIR'), 'WICKET_PLUGIN_DIR constant should be defined');
    }

    public function test_wicket_plugin_dir_is_string(): void
    {
        $this->assertIsString(WICKET_PLUGIN_DIR, 'WICKET_PLUGIN_DIR should be a string');
    }

    public function test_wicket_plugin_dir_exists(): void
    {
        $this->assertTrue(is_dir(WICKET_PLUGIN_DIR), 'WICKET_PLUGIN_DIR should be a valid directory');
    }
}
