<?php
namespace Wicket\Entities;


use Illuminate\Support\Str;

class Factory
{
	/**
	 * Factory to create Wicket\Entities from JSON:API style response.
	 *
	 * @param array $data JSON:API data block.
	 * @param bool $related Traverse base `relationships`?
	 * @return \Wicket\Entities\Base
	 */
	public static function create($data, $related = false)
	{
		if (!is_array($data)) return new Base;

		$json_api_keys = array_intersect_key($data, array_flip(['type', 'id']));

		if (!count($json_api_keys) >= 2) {
			throw new \InvalidArgumentException('Are you sure this data is JSON:API: ' . print_r($data, 1));
		}

		$className = join('\\', [__NAMESPACE__, Str::studly($data['type'])]);

		if (!class_exists($className)) {
			$className = join('\\', [__NAMESPACE__, 'Base']);
		}

		$attrs = array_key_exists('attributes', $data) ? $data['attributes'] : null;

		/** @var Base $entity */
		$entity = new $className($attrs, $data['type'], $data['id']);

		if ($related && array_key_exists('relationships', $data)) {
			foreach ($data['relationships'] as $k => $related) {
				if (array_key_exists('data', $related) && !empty($related['data'])) {
					$reldata = $related['data'];
					if (!is_array($reldata)) $reldata = [$reldata];
					
					// if this is only for an array with a single value set, don't loop over it
					if (isset($reldata['id'])) {
						$entity->addRelationship($k, Factory::create($reldata));
					}else {
						foreach ($reldata as $relation) {
							$entity->addRelationship($k, Factory::create($relation));
						}
					}

				}
			}
		}

		return $entity;
	}

}
