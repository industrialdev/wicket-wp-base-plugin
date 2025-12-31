<?php

declare(strict_types=1);

namespace WicketWP\Tests\Widgets;

use PHPUnit\Framework\Attributes\CoversClass;
use WicketWP\Tests\AbstractTestCase;
use WicketWP\Widgets\UpdatePassword;
use WicketWP\Main;

#[CoversClass(UpdatePassword::class)]
class UpdatePasswordWidgetTest extends AbstractTestCase
{
    private UpdatePassword $widget;

    protected function setUp(): void
    {
        parent::setUp();
        $this->widget = new UpdatePassword();
    }

    public function test_widget_instantiates(): void
    {
        $this->assertInstanceOf(UpdatePassword::class, $this->widget);
    }

    public function test_widget_extends_wp_widget(): void
    {
        $this->assertInstanceOf(\WP_Widget::class, $this->widget);
    }
}
