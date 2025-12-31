<?php

declare(strict_types=1);

namespace WicketWP\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use WicketWP\Main;

#[CoversClass(Main::class)]
class MainTest extends AbstractTestCase
{
    public function test_get_instance_returns_singleton(): void
    {
        $instance1 = Main::get_instance();
        $instance2 = Main::get_instance();

        $this->assertSame($instance1, $instance2, 'Main::get_instance() should return the same instance');
    }

    public function test_init_creates_assets_instance(): void
    {
        $main = Main::get_instance();

        $plugin = new class {
            public $plugin_url = 'http://example.com';
            public $plugin_path = '/path/to/plugin';
        };

        $main->init($plugin);

        $this->assertInstanceOf(\WicketWP\Assets::class, $main->assets);
    }

    public function test_init_creates_includes_instance(): void
    {
        $main = Main::get_instance();

        $plugin = new class {
            public $plugin_url = 'http://example.com';
            public $plugin_path = '/path/to/plugin';
        };

        $main->init($plugin);

        $this->assertInstanceOf(\WicketWP\Includes::class, $main->includes);
    }

    public function test_init_creates_rest_instance(): void
    {
        $main = Main::get_instance();

        $plugin = new class {
            public $plugin_url = 'http://example.com';
            public $plugin_path = '/path/to/plugin';
        };

        $main->init($plugin);

        $this->assertInstanceOf(\WicketWP\Rest::class, $main->rest);
    }

    public function test_init_creates_blocks_instance(): void
    {
        $main = Main::get_instance();

        $plugin = new class {
            public $plugin_url = 'http://example.com';
            public $plugin_path = '/path/to/plugin';
        };

        $main->init($plugin);

        $this->assertInstanceOf(\WicketWP\Blocks::class, $main->blocks);
    }

    public function test_init_creates_widgets_instance(): void
    {
        $main = Main::get_instance();

        $plugin = new class {
            public $plugin_url = 'http://example.com';
            public $plugin_path = '/path/to/plugin';
        };

        $main->init($plugin);

        $this->assertInstanceOf(\WicketWP\Widgets::class, $main->widgets);
    }
}
