<?php
namespace Wicket;


class ClientTest extends \PHPUnit_Framework_TestCase
{
	private $wicket;

	public function setUp()
	{
		$this->wicket = new Client('app_key', 'api_key');
	}

	public function testClientCanBeCreated()
	{
		$this->assertNotEmpty($this->wicket);
	}

	public function testClientContainsExposedResources()
	{
		$this->assertEquals('Wicket\ApiResource', get_class($this->wicket->organizations));
		$this->assertEquals('Wicket\ApiResource', get_class($this->wicket->people));
	}

}
