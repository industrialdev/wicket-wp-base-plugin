<?php
/**
 * Created by IntelliJ IDEA.
 * User: scott
 * Date: 15/04/16
 * Time: 9:29 AM
 */

namespace Wicket;


class VersionTest extends \PHPUnit_Framework_TestCase
{

	public function testSemver()
	{
		$this->assertEquals(1.1, Version::get());
	}
}
