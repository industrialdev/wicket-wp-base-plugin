<?php

declare(strict_types=1);

namespace WicketWP\Tests\Widgets;

use PHPUnit\Framework\Attributes\CoversClass;
use WicketWP\Tests\AbstractTestCase;
use WicketWP\Widgets\CreateAccount;
use WicketWP\Main;

#[CoversClass(CreateAccount::class)]
class CreateAccountWidgetTest extends AbstractTestCase
{
    private CreateAccount $widget;

    protected function setUp(): void
    {
        parent::setUp();
        $this->widget = new CreateAccount();
    }

    public function test_widget_instantiates(): void
    {
        $this->assertInstanceOf(CreateAccount::class, $this->widget);
    }

    public function test_widget_has_errors_property(): void
    {
        $this->assertObjectHasProperty('errors', $this->widget);
    }
}
