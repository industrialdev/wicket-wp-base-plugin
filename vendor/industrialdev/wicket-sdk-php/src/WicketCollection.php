<?php
namespace Wicket;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Presenter;
use Illuminate\Support\Collection;
use Wicket\Entities\Base;

/*
 * Merge collection and pagination by extracting the data as a collection,
 * adding pagesble methods for paginated API responses.
 *
 * https://laravel.com/docs/5.2/pagination
 * https://developers.facebook.com/docs/php/howto/example_pagination_basic
 */

class WicketCollection
	extends Collection
{
	protected $self;        // url
	protected $next;        // url
	protected $prev;        // url
	protected $last;        // url
	protected $first;       // url
	protected $total_items; // int
	protected $total_pages; // int
	protected $number;      // 1-based idx of the current page
	protected $size;        // size of page (int)
	/**
	 * @var \Wicket\Client
	 */
	private $client;

	/**
	 * WicketCollection constructor.
	 * @param array|false $response
	 * @param \Wicket\Client|null $client
	 */
	public function __construct($response, Client $client = null)
	{
		$ent_list = array_map(function ($ent) {
			return Base::fromJsonAPI($ent);
		}, data_get($response, 'data', []));

		parent::__construct($ent_list);

		$this->self = data_get($response, 'links.self', null);
		$this->next = data_get($response, 'links.next', null);
		$this->prev = data_get($response, 'links.prev', null);
		$this->last = data_get($response, 'links.last', null);
		$this->first = data_get($response, 'links.first', null);
		$this->total_items = data_get($response, 'meta.page.total_items', null);
		$this->total_pages = data_get($response, 'meta.page.total_pages', null);
		$this->number = data_get($response, 'meta.page.number', null);
		$this->size = data_get($response, 'meta.page.size', null);
		$this->client = $client;
	}

	/**
	 * @param $url
	 * @return array|false|\Wicket\WicketCollection If the response has a `data` block, else the reponse.
	 */
	public function getPage($url)
	{
		if (empty($url)) {
			return null;
		}

		$response = $this->client->get($url);

		if (array_key_exists('meta', $response)
			&& array_key_exists('links', $response)
			&& !empty($response['meta'])
			&& !empty($response['links'])
		) {
			$response = new WicketCollection($response, $this->client);
		}

		return $response;
	}

	public function nextPage()
	{
		return $this->getPage($this->nextPageUrl());
	}

	public function prevPage()
	{
		return $this->getPage($this->previousPageUrl());
	}

	/**
	 * Determine the total number of items in the data store.
	 *
	 * @return int
	 */
	public function total()
	{
		return $this->total_items;
	}

	/**
	 * Get the page number of the last available page.
	 *
	 * @return int
	 */
	public function lastPage()
	{
		return $this->total_pages;
	}

	/**
	 * The the URL for the next page, or null.
	 *
	 * @return string|null
	 */
	public function nextPageUrl()
	{
		return $this->next;
	}

	/**
	 * Get the URL for the previous page, or null.
	 *
	 * @return string|null
	 */
	public function previousPageUrl()
	{
		return $this->prev;
	}

	/**
	 * Get all of the items being paginated.
	 *
	 * @return array
	 */
	public function items()
	{
		return $this;
	}

	/**
	 * Get the "entity" of the first item being paginated.
	 *
	 * @return int
	 */
	public function firstItem()
	{
		return $this->first;
	}

	/**
	 * Get the "entity" of the last item being paginated.
	 *
	 * @return int
	 */
	public function lastItem()
	{
		return $this->last;
	}

	/**
	 * Determine how many items are being shown per page.
	 *
	 * @return int
	 */
	public function perPage()
	{
		return $this->size;
	}

	/**
	 * Determine the current page being paginated.
	 *
	 * @return int
	 */
	public function currentPage()
	{
		return $this->number;
	}

	/**
	 * Determine if there are enough items to split into multiple pages.
	 *
	 * @return bool
	 */
	public function hasPages()
	{
		return $this->next || $this->prev;
	}

	/**
	 * Determine if there is more items in the data store.
	 *
	 * @return bool
	 */
	public function hasMorePages()
	{
		return !!$this->next;
	}

	/**
	 * Render the paginator using a given Presenter.
	 *
	 * @param  string|null $view
	 * @return string
	 */
	public function render($view = null)
	{
		return $view ? $view->render() : $this;
	}

	/**
	 * Get the URL for a given page.
	 *
	 * @param  int $page
	 * @return string
	 */
	public function url($page)
	{
		if (1 > $page || $page > $this->total_pages) {
			return null;
		}

		$parsed = parse_url($this->self);
		parse_str($parsed['query'], $params);
		$params['page']['number'] = $page;

		return $parsed['scheme'] . '://'
		. $parsed['host']
		. $parsed['path'] . '?'
		. http_build_query($params);
	}

	/**
	 * Add a set of query string values to the paginator.
	 *
	 * @param  array|string $key
	 * @param  string|null $value
	 * @return $this
	 */
	public function appends($key, $value = null)
	{
		// TODO: Implement appends() method.
	}

	/**
	 * Get / set the URL fragment to be appended to URLs.
	 *
	 * @param  string|null $fragment
	 * @return $this|string
	 */
	public function fragment($fragment = null)
	{
		// TODO: Implement fragment() method.
	}
}
