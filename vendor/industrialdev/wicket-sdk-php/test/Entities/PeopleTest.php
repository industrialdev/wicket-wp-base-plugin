<?php

namespace Entities;

use Wicket\Entities\People;

class PeopleTest extends \PHPUnit_Framework_TestCase
{
	/** @var  People */
	private $person;

	protected function setUp()
	{
		$this->person = new People([
			'given_name'  => 'alice',
			'family_name' => 'smith',
		]);
	}

	public function testCanCreatePerson()
	{
		$this->assertNotEmpty($this->person);
		$this->assertEquals('people', $this->person->type);
	}

	public function testEntityAttributeGetters()
	{
		$this->assertEquals('alice', $this->person->given_name);
		$this->assertEquals('alice', $this->person->getAttribute('given_name'));
		$this->assertEquals('smith', $this->person->family_name);
		$this->assertEquals('smith', $this->person->getAttribute('family_name'));
	}
}
