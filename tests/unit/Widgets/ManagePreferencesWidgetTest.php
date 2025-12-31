<?php

declare(strict_types=1);

namespace WicketWP\Tests\Widgets;

use PHPUnit\Framework\Attributes\CoversClass;
use WicketWP\Tests\AbstractTestCase;
use WicketWP\Widgets\ManagePreferences;
use WicketWP\Main;

#[CoversClass(ManagePreferences::class)]
class ManagePreferencesWidgetTest extends AbstractTestCase
{
    private ManagePreferences $widget;

    protected function setUp(): void
    {
        parent::setUp();
        $this->widget = new ManagePreferences();
    }

    public function test_widget_instantiates(): void
    {
        $this->assertInstanceOf(ManagePreferences::class, $this->widget);
    }

    public function test_widget_extends_wp_widget(): void
    {
        $this->assertInstanceOf(\WP_Widget::class, $this->widget);
    }
}
