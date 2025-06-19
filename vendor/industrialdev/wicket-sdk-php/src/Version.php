<?php
namespace Wicket;

class Version {
	const VALUE = '1.1';

	public static $custom_value = '';

	public static function get() {
		return self::VALUE . static::$custom_value;
	}
}