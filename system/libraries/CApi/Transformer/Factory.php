<?php

class CApi_Transformer_Factory {
    /**
     * Array of registered transformer bindings.
     *
     * @var array
     */
    protected $bindings = [];

    /**
     * Transformation layer adapter being used to transform responses.
     *
     * @var \Dingo\Api\Contract\Transformer\Adapter
     */
    protected $adapter;

    /**
     * Create a new transformer factory instance.
     *
     * @param \CApi_Contract_Transformer_AdapterInterface $adapter
     *
     * @return void
     */
    public function __construct(CApi_Contract_Transformer_AdapterInterface $adapter) {
        $this->adapter = $adapter;
    }

    /**
     * @return CContainer_Container
     */
    public function contaniner() {
        return c::container();
    }

    /**
     * Register a transformer binding resolver for a class.
     *
     * @param               $class
     * @param               $resolver
     * @param array         $parameters
     * @param null|\Closure $after
     *
     * @return \Dingo\Api\Transformer\Binding
     */
    public function register($class, $resolver, array $parameters = [], ?Closure $after = null) {
        return $this->bindings[$class] = $this->createBinding($resolver, $parameters, $after);
    }

    /**
     * Transform a response.
     *
     * @param string|object $response
     *
     * @return mixed
     */
    public function transform($response) {
        $binding = $this->getBinding($response);

        return $this->adapter->transform($response, $binding->resolveTransformer(), $binding, $this->getRequest());
    }

    /**
     * Determine if a response is transformable.
     *
     * @param mixed $response
     *
     * @return bool
     */
    public function transformableResponse($response) {
        return $this->transformableType($response) && $this->hasBinding($response);
    }

    /**
     * Determine if a value is of a transformable type.
     *
     * @param mixed $value
     *
     * @return bool
     */
    public function transformableType($value) {
        return is_object($value) || is_string($value);
    }

    /**
     * Get a registered transformer binding.
     *
     * @param string|object $class
     *
     * @throws \RuntimeException
     *
     * @return \Dingo\Api\Transformer\Binding
     */
    public function getBinding($class) {
        if ($this->isCollection($class) && !$class->isEmpty()) {
            return $this->getBindingFromCollection($class);
        }

        $class = is_object($class) ? get_class($class) : $class;

        if (!$this->hasBinding($class)) {
            throw new RuntimeException('Unable to find bound transformer for "' . $class . '" class.');
        }

        return $this->bindings[$class];
    }

    /**
     * Create a new binding instance.
     *
     * @param string|callable|object $resolver
     * @param array                  $parameters
     * @param \Closure               $callback
     *
     * @return \CApi_Transformer_Binding
     */
    protected function createBinding($resolver, array $parameters = [], ?Closure $callback = null) {
        return new CApi_Transformer_Binding($resolver, $parameters, $callback);
    }

    /**
     * Get a registered transformer binding from a collection of items.
     *
     * @param \CCollection $collection
     *
     * @return null|string|callable
     */
    protected function getBindingFromCollection($collection) {
        return $this->getBinding($collection->first());
    }

    /**
     * Determine if a class has a transformer binding.
     *
     * @param string|object $class
     *
     * @return bool
     */
    protected function hasBinding($class) {
        if ($this->isCollection($class) && !$class->isEmpty()) {
            $class = $class->first();
        }

        $class = is_object($class) ? get_class($class) : $class;

        return isset($this->bindings[$class]);
    }

    /**
     * Determine if the instance is a collection.
     *
     * @param object $instance
     *
     * @return bool
     */
    protected function isCollection($instance) {
        return $instance instanceof CCollection || $instance instanceof CPagination_PaginatorInterface;
    }

    /**
     * Get the array of registered transformer bindings.
     *
     * @return array
     */
    public function getTransformerBindings() {
        return $this->bindings;
    }

    /**
     * Set the transformation layer at runtime.
     *
     * @param \Closure|\Dingo\Api\Contract\Transformer\Adapter $adapter
     *
     * @return void
     */
    public function setAdapter($adapter) {
        if (is_callable($adapter)) {
            $adapter = call_user_func($adapter, $this->contaniner());
        }

        $this->adapter = $adapter;
    }

    /**
     * Get the transformation layer adapter.
     *
     * @return \Dingo\Api\Contract\Transformer\Adapter
     */
    public function getAdapter() {
        return $this->adapter;
    }

    /**
     * Get the current API request, falling back to the framework request.
     *
     * @return \CApi_HTTP_Request
     */
    public function getRequest() {
        $request = CApi::request() ?: c::request();

        if ($request instanceof CHTTP_Request && !$request instanceof CApi_HTTP_Request) {
            $request = CApi_HTTP_Request::createFromBaseHttp($request);
        }

        return $request;
    }

    /**
     * Pass unknown method calls through to the adapter.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters) {
        return call_user_func_array([$this->adapter, $method], $parameters);
    }
}
