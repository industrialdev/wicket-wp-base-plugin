<?php
namespace Entities;

use Wicket\Entities\Base;

class BaseTest extends \PHPUnit_Framework_TestCase
{
	/** @var  Base */
	private $person;

	protected function setUp()
	{
		$this->person = new Base([], 'base', rand(1000, 9999));
		$this->person->neo = 'matrix';
	}

	public function testCanCreateBase()
	{
		$this->assertNotEmpty($this->person);
		$this->assertObjectHasAttribute('type', $this->person);
		$this->assertEquals('base', $this->person->type);
	}

	public function testUseAttributes()
	{
		$this->assertEquals('matrix', $this->person->neo);
		$this->assertEquals($this->person->getAttribute('neo'), $this->person->neo);
	}

}
