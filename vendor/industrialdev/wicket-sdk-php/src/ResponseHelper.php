<?php 

namespace Wicket;

// Helper for working with JSON API response structure https://jsonapi.org/format/1.0/#document-structure
class ResponseHelper {
  public $data;
  public $included;
  public $included_resources_by_type;
  public $response;
  
  public function __construct($response_hash) {
    $this->response = $response_hash;
    $this->data = $response_hash['data'];
    $this->included = !empty($response_hash['included']) ? (array)$response_hash['included'] : [];
    $this->included_resources_by_type = array_reduce($this->included, function ($result, $resource) {
      $result[$resource['type']][$resource['id']] = $resource;
      return $result;
    }, []); 

    if ($this->isJsonApiResourceId($this->data)) {
      $this->included_resources_by_type[$this->data['type']][$this->data['id']] = $this->data;
    } else if (is_array($this->data)) {
      foreach ($this->data as $resource) {
        $this->included_resources_by_type[$resource['type']][$resource['id']] = $resource;
      }
    }
  }

  /**
   * Finds an included resource by resource id
   *
   * @param array $resource_id resource id (type / id pair)
   * @return array|null
   */
  public function getIncludedResource($resource_id) {
    if (!$this->isJsonApiResourceId($resource_id)) return null;

    $has_included_resource = (
      !empty($this->included_resources_by_type[$resource_id['type']]) &&
      !empty($this->included_resources_by_type[$resource_id['type']][$resource_id['id']])
    );

    if ($has_included_resource) {
      return $this->included_resources_by_type[$resource_id['type']][$resource_id['id']];
    }
  }

  /**
   * Returns a raw relationship for JSONAPI resource
   *
   * @param array $resource JSON API resource hash
   * @param string $relationship_name Name of relationship
   * @return void
   */
  public function getRelationship($resource, $relationship_name) {
    if ($resource && isset($resource['relationships'][$relationship_name])) {
      return $resource['relationships'][$relationship_name];
    } else {
      return null;
    }
  }

  /**
   * Returns an array of included resource objects when the relationship has multiple resources.
   * Otherwise, the response will be a single resource of null.
   *
   * @param array $resource JSON API resource hash
   * @param string $relationship_name Name of relationship 
   * @return array|null
   */
  public function getIncludedRelationship($resource, $relationship_name) {
    $relationship = $this->getRelationship($resource, $relationship_name);

    if (isset($relationship['data']) && $this->isJsonApiResourceId($relationship['data'])) {
      return $this->getIncludedResource($relationship['data']);
    } else if (isset($relationship['data']) && is_array($relationship['data'])) {
      return array_map([$this, 'getIncludedResource'], $relationship['data']);
    }
  }

  protected function isJsonApiResourceId($resource_id) {
    return is_array($resource_id) && !empty($resource_id['type']) && !empty($resource_id['id']);
  }
}