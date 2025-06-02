<?php
namespace Wicket\Entities;


class WebAddresses extends Base
{
	public function __construct($attributes = [], $type = null, $id = null)
	{
		parent::__construct($attributes, $type, $id);
    $this->type = $type ?: 'web_addresses';
	}
}
