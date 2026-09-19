<?php

use Illuminate\Http\Request;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Http\Resources\CollectsResources;
use Illuminate\Pagination\AbstractCursorPaginator;

class CHTTP_Resources_Json_ResourceCollection extends CHTTP_Resources_Json_JsonResource implements Countable, IteratorAggregate {
    use CHTTP_Resources_CollectsResources;

    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects;

    /**
     * The mapped collection instance.
     *
     * @var \CCollection
     */
    public $collection;

    /**
     * Indicates if all existing request query parameters should be added to pagination links.
     *
     * @var bool
     */
    protected $preserveAllQueryParameters = false;

    /**
     * The query parameters that should be added to the pagination links.
     *
     * @var null|array
     */
    protected $queryParameters;

    /**
     * Create a new resource instance.
     *
     * @param mixed $resource
     */
    public function __construct($resource) {
        parent::__construct($resource);

        $this->resource = $this->collectResource($resource);
    }

    /**
     * Indicate that all current query parameters should be appended to pagination links.
     *
     * @return $this
     */
    public function preserveQuery() {
        $this->preserveAllQueryParameters = true;

        return $this;
    }

    /**
     * Specify the query string parameters that should be present on pagination links.
     *
     * @param array $query
     *
     * @return $this
     */
    public function withQuery(array $query) {
        $this->preserveAllQueryParameters = false;

        $this->queryParameters = $query;

        return $this;
    }

    /**
     * Return the count of items in the resource collection.
     *
     * @return int
     */
    public function count(): int {
        return $this->collection->count();
    }

    /**
     * Transform the resource into a JSON array.
     *
     * @param \CHTTP_Request $request
     *
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray(CHTTP_Request $request) {
        return $this->collection->map->toArray($request)->all();
    }

    /**
     * Create an HTTP response that represents the object.
     *
     * @param \CHTTP_Request $request
     *
     * @return \CHTTP_JsonResponse
     */
    public function toResponse($request) {
        if ($this->resource instanceof CPagination_AbstractPaginator || $this->resource instanceof CPagination_CursorPaginatorAbstract) {
            return $this->preparePaginatedResponse($request);
        }

        return parent::toResponse($request);
    }

    /**
     * Create a paginate-aware HTTP response.
     *
     * @param \CHTTP_Request $request
     *
     * @return \CHTTP_JsonResponse
     */
    protected function preparePaginatedResponse($request) {
        if ($this->preserveAllQueryParameters) {
            $this->resource->appends($request->query());
        } elseif (!is_null($this->queryParameters)) {
            $this->resource->appends($this->queryParameters);
        }

        return (new CHTTP_Resources_Json_PaginatedResourceResponse($this))->toResponse($request);
    }
}
