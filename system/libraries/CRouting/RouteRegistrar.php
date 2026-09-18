<?php

/**
 * @method CRouting_Route          get(string $uri, null|\Closure|array|string $action = null)
 * @method CRouting_Route          post(string $uri, null|\Closure|array|string $action = null)
 * @method CRouting_Route          put(string $uri, null|\Closure|array|string $action = null)
 * @method CRouting_Route          delete(string $uri, null|\Closure|array|string $action = null)
 * @method CRouting_Route          patch(string $uri, null|\Closure|array|string $action = null)
 * @method CRouting_Route          options(string $uri, null|\Closure|array|string $action = null)
 * @method CRouting_Route          any(string $uri, null|\Closure|array|string $action = null)
 * @method CRouting_RouteRegistrar as(string $value)
 * @method CRouting_RouteRegistrar domain(string $value)
 * @method CRouting_RouteRegistrar middleware(null|array|string $middleware)
 * @method CRouting_RouteRegistrar name(string $value)
 * @method CRouting_RouteRegistrar namespace(null|string $value)
 * @method CRouting_RouteRegistrar prefix(string  $prefix)
 * @method CRouting_RouteRegistrar where(array  $where)
 * @method CRouting_RouteRegistrar withoutMiddleware(array|string $middleware)
 */
class CRouting_RouteRegistrar {
    use CRouting_Concern_CreatesRegularExpressionRouteConstraints;

    /**
     * The router instance.
     *
     * @var CRouting_Router
     */
    protected $router;

    /**
     * The attributes to pass on to the router.
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * The methods to dynamically pass through to the router.
     *
     * @var string[]
     */
    protected $passthru = [
        'get', 'post', 'put', 'patch', 'delete', 'options', 'any',
    ];

    /**
     * The attributes that can be set through this class.
     *
     * @var string[]
     */
    protected $allowedAttributes = [
        'as', 'domain', 'middleware', 'name', 'namespace', 'prefix', 'where', 'withoutMiddleware',
    ];

    /**
     * The attributes that are aliased.
     *
     * @var array
     */
    protected $aliases = [
        'name' => 'as',
        'withoutMiddleware' => 'excluded_middleware',
    ];

    /**
     * Create a new route registrar instance.
     *
     * @param CRouting_Router $router
     *
     * @return void
     */
    public function __construct(CRouting_Router $router) {
        $this->router = $router;
    }

    /**
     * Set the value for a given attribute.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @throws \InvalidArgumentException
     *
     * @return $this
     */
    public function attribute($key, $value) {
        if (!in_array($key, $this->allowedAttributes)) {
            throw new InvalidArgumentException("Attribute [{$key}] does not exist.");
        }

        $attributeKey = carr::get($this->aliases, $key, $key);

        if ($key === 'withoutMiddleware') {
            $value = array_merge((array) carr::get($this->attributes, $attributeKey, []), carr::wrap($value));
        }

        $this->attributes[$attributeKey] = $value;

        return $this;
    }

    /**
     * Route a resource to a controller.
     *
     * @param string $name
     * @param string $controller
     * @param array  $options
     *
     * @return CRouting_PendingResourceRegistration
     */
    public function resource($name, $controller, array $options = []) {
        return $this->router->resource($name, $controller, $this->attributes + $options);
    }

    /**
     * Route an API resource to a controller.
     *
     * @param string $name
     * @param string $controller
     * @param array  $options
     *
     * @return CRouting_PendingResourceRegistration
     */
    public function apiResource($name, $controller, array $options = []) {
        return $this->router->apiResource($name, $controller, $this->attributes + $options);
    }

    /**
     * Create a route group with shared attributes.
     *
     * @param \Closure|string $callback
     *
     * @return void
     */
    public function group($callback) {
        $this->router->group($this->attributes, $callback);
    }

    /**
     * Register a new route with the given verbs.
     *
     * @param array|string               $methods
     * @param string                     $uri
     * @param null|\Closure|array|string $action
     *
     * @return CRouting_Route
     */
    public function match($methods, $uri, $action = null) {
        return $this->router->match($methods, $uri, $this->compileAction($action));
    }

    /**
     * Register a new route with the router.
     *
     * @param string                     $method
     * @param string                     $uri
     * @param null|\Closure|array|string $action
     *
     * @return CRouting_Route
     */
    protected function registerRoute($method, $uri, $action = null) {
        if (!is_array($action)) {
            $action = array_merge($this->attributes, $action ? ['uses' => $action] : []);
        }

        return $this->router->{$method}($uri, $this->compileAction($action));
    }

    /**
     * Compile the action into an array including the attributes.
     *
     * @param null|\Closure|array|string $action
     *
     * @return array
     */
    protected function compileAction($action) {
        if (is_null($action)) {
            return $this->attributes;
        }

        if (is_string($action) || $action instanceof Closure) {
            $action = ['uses' => $action];
        }

        if (is_array($action)
            && !carr::isAssoc($action)
            && CBase_Reflector::isCallable($action, true)
        ) {
            if (strncmp($action[0], '\\', 1)) {
                $action[0] = '\\' . $action[0];
            }
            $action = [
                'uses' => $action[0] . '@' . $action[1],
                'controller' => $action[0] . '@' . $action[1],
            ];
        }

        return array_merge($this->attributes, $action);
    }

    /**
     * Dynamically handle calls into the route registrar.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @throws \BadMethodCallException
     *
     * @return CRouting_Route|$this
     */
    public function __call($method, $parameters) {
        if (in_array($method, $this->passthru)) {
            return $this->registerRoute($method, ...$parameters);
        }

        if (in_array($method, $this->allowedAttributes)) {
            if ($method === 'middleware') {
                return $this->attribute($method, is_array($parameters[0]) ? $parameters[0] : $parameters);
            }

            return $this->attribute($method, $parameters[0]);
        }

        throw new BadMethodCallException(sprintf(
            'Method %s::%s does not exist.',
            static::class,
            $method
        ));
    }
}
