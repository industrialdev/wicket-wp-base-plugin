<?php
namespace Wicket;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Prophecy\Exception\Doubler\ClassNotFoundException;
use Wicket\Entities\Base;
use Wicket\Entities\Factory;

class ApiResource
{
	private $client;
	private $entity;

	/**
	 * ApiResource constructor.
	 * @param \Wicket\Client $client
	 * @param $entity
	 */
	public function __construct(Client $client, $entity)
	{
		$this->client = $client;
		$entity_class = join('\\', [__NAMESPACE__, 'Entities', Str::studly($entity)]);

		try {
			$entity_class = new $entity_class();
			$this->entity = $entity;
		} catch (Exception $e) {
			throw new ClassNotFoundException($e->getMessage(), $entity_class);
		}

		return $entity_class;
	}

	/**
	 * @return WicketCollection A WicketCollection that may be pageable.
	 */
	public function all($args = [])
	{
		$response = $this->client->get($this->entity, $args);
		$response = new WicketCollection($response, $this->client);

		return $response;
	}

	public function fetch($id)
	{
		$result = $this->client->get($this->entity . '/' . $id);
		if ($result && array_key_exists('included', $result)) {
			$included = $result['included'];
		}
		if ($result && array_key_exists('data', $result)) {
			$result = Factory::create($result['data'], true);
		}
		if (!empty($included)) {
			$result->addIncluded($included);
		}
		return $result;
	}

	public function create(Base $entity, $parent_tree = null)
	{
		$entity_create_url = '';

		if ($parent_tree) {
			if (!$parent_tree instanceof Collection) {
				if (!is_array($parent_tree)) {
					$parent_tree = [$parent_tree];
				}
				$parent_tree = collect($parent_tree);
			}

			$entity_create_url = $parent_tree->reduce(function ($url, $ent) {
				return $url . '/' . $ent->type . '/' . $ent->id;
			});
		}
		$entity_create_url .= '/' . $entity->type;
		$payload = ['json' => $entity->toJsonAPI()];
		$res = $this->client->post(ltrim($entity_create_url, '/'), $payload);

		return $res;
	}

	public function update(Base $entity, $parent_tree = null)
	{
		$entity_create_url = '';

		if ($parent_tree) {
			if (!$parent_tree instanceof Collection) {
				if (!is_array($parent_tree)) {
					$parent_tree = [$parent_tree];
				}
				$parent_tree = collect($parent_tree);
			}

			$entity_create_url = $parent_tree->reduce(function ($url, $ent) {
				return $url . '/' . $ent->type . '/' . $ent->id;
			});
		}
		$entity_create_url .= '/' . $entity->type;
		$payload = ['json' => $entity->toJsonAPI()];

		$res = $this->client->patch(ltrim($entity_create_url, '/') . '/' . $entity->id, $payload);
		return $res;
	}

	public function delete()
	{
		// TODO: Implement delete() method.
	}

}
