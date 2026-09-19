<?php

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Container\Container;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\DelegatesToResource;
use Illuminate\Database\Eloquent\JsonEncodingException;
use Illuminate\Http\Resources\ConditionallyLoadsAttributes;

class CHTTP_Resources_Json_JsonResource implements ArrayAccess, JsonSerializable, CInterface_Responsable, CRouting_UrlRoutableInterface {
    use CHTTP_Resources_ConditionallyLoadsAttributes, CHTTP_Resources_DelegatesToResource;

    /**
     * The resource instance.
     *
     * @var mixed
     */
    public $resource;

    /**
     * The additional data that should be added to the top-level resource array.
     *
     * @var array
     */
    public $with = [];

    /**
     * The additional meta data that should be added to the resource response.
     *
     * Added during response construction by the developer.
     *
     * @var array
     */
    public $additional = [];

    /**
     * The "data" wrapper that should be applied.
     *
     * @var null|string
     */
    public static $wrap = 'data';

    /**
     * Create a new resource instance.
     *
     * @param mixed $resource
     */
    public function __construct($resource) {
        $this->resource = $resource;
    }

    /**
     * Create a new resource instance.
     *
     * @param mixed ...$parameters
     *
     * @return static
     */
    public static function make(...$parameters) {
        return new static(...$parameters);
    }

    /**
     * Create a new anonymous resource collection.
     *
     * @param mixed $resource
     *
     * @return \CHTTP_Resources_Json_AnonymousResourceCollection
     */
    public static function collection($resource) {
        return c::tap(static::newCollection($resource), function ($collection) {
            if (property_exists(static::class, 'preserveKeys')) {
                $collection->preserveKeys = (new static([]))->preserveKeys === true;
            }
        });
    }

    /**
     * Create a new resource collection instance.
     *
     * @param mixed $resource
     *
     * @return \CHTTP_Resources_Json_AnonymousResourceCollection
     */
    protected static function newCollection($resource) {
        return new CHTTP_Resources_Json_AnonymousResourceCollection($resource, static::class);
    }

    /**
     * Resolve the resource to an array.
     *
     * @param null|CHTTP_Request $request
     *
     * @return array
     */
    public function resolve($request = null) {
        $data = $this->toArray(
            $request ?: CContainer::getInstance()->make('request')
        );

        if ($data instanceof Arrayable) {
            $data = $data->toArray();
        } elseif ($data instanceof JsonSerializable) {
            $data = $data->jsonSerialize();
        }

        return $this->filter((array) $data);
    }

    /**
     * Transform the resource into an array.
     *
     * @param \CHTTP_Request $request
     *
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray(CHTTP_Request $request) {
        if (is_null($this->resource)) {
            return [];
        }

        return is_array($this->resource)
            ? $this->resource
            : $this->resource->toArray();
    }

    /**
     * Convert the model instance to JSON.
     *
     * @param int $options
     *
     * @throws \CModel_Exception_JsonEncodingException
     *
     * @return string
     */
    public function toJson($options = 0) {
        try {
            $json = json_encode($this->jsonSerialize(), $options | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw CModel_Exception_JsonEncodingException::forResource($this, $e->getMessage());
        }

        return $json;
    }

    /**
     * Get any additional data that should be returned with the resource array.
     *
     * @param \CHTTP_Request $request
     *
     * @return array
     */
    public function with(CHTTP_Request $request) {
        return $this->with;
    }

    /**
     * Add additional meta data to the resource response.
     *
     * @param array $data
     *
     * @return $this
     */
    public function additional(array $data) {
        $this->additional = $data;

        return $this;
    }

    /**
     * Get the JSON serialization options that should be applied to the resource response.
     *
     * @return int
     */
    public function jsonOptions() {
        return 0;
    }

    /**
     * Customize the response for a request.
     *
     * @param \CHTTP_Request      $request
     * @param \CHTTP_JsonResponse $response
     *
     * @return void
     */
    public function withResponse(CHTTP_Request $request, CHTTP_JsonResponse $response) {
    }

    /**
     * Set the string that should wrap the outer-most resource array.
     *
     * @param string $value
     *
     * @return void
     */
    public static function wrap($value) {
        static::$wrap = $value;
    }

    /**
     * Disable wrapping of the outer-most resource array.
     *
     * @return void
     */
    public static function withoutWrapping() {
        static::$wrap = null;
    }

    /**
     * Transform the resource into an HTTP response.
     *
     * @param null|\CHTTP_Request $request
     *
     * @return \CHTTP_JsonResponse
     */
    public function response($request = null) {
        return $this->toResponse(
            $request ?: CContainer::getInstance()->make('request')
        );
    }

    /**
     * Create an HTTP response that represents the object.
     *
     * @param \CHTTP_Request $request
     *
     * @return \CHTTP_JsonResponse
     */
    public function toResponse($request) {
        return (new CHTTP_Resources_Json_ResourceResponse($this))->toResponse($request);
    }

    /**
     * Prepare the resource for JSON serialization.
     *
     * @return array
     */
    public function jsonSerialize(): array {
        return $this->resolve(CContainer::getInstance()->make('request'));
    }
}
