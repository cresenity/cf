<?php

use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Matcher\CompiledUrlMatcher;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

class CRouting_CompiledRouteCollection extends CRouting_RouteCollectionAbstract {
    /**
     * The compiled routes collection.
     *
     * @var array
     */
    protected $compiled = [];

    /**
     * An array of the route attributes keyed by name.
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * The dynamically added routes that were added after loading the cached, compiled routes.
     *
     * @var null|\CRouting_RouteCollection
     */
    protected $routes;

    /**
     * The router instance used by the route.
     *
     * @var \CRouting_Router
     */
    protected $router;

    /**
     * The container instance used by the route.
     *
     * @var \CContainer_Container
     */
    protected $container;

    /**
     * Create a new CompiledRouteCollection instance.
     *
     * @param array $compiled
     * @param array $attributes
     *
     * @return void
     */
    public function __construct(array $compiled, array $attributes) {
        $this->compiled = $compiled;
        $this->attributes = $attributes;
        $this->routes = new CRouting_RouteCollection();
    }

    /**
     * Add a Route instance to the collection.
     *
     * @param CRouting_Route $route
     *
     * @return CRouting_Route
     */
    public function add(CRouting_Route $route) {
        return $this->routes->add($route);
    }

    /**
     * Refresh the name look-up table.
     *
     * This is done in case any names are fluently defined or if routes are overwritten.
     *
     * @return void
     */
    public function refreshNameLookups() {
    }

    /**
     * Refresh the action look-up table.
     *
     * This is done in case any actions are overwritten with new controllers.
     *
     * @return void
     */
    public function refreshActionLookups() {
    }

    /**
     * Find the first route matching a given request.
     *
     * @param CHTTP_Request $request
     *
     * @throws \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     *
     * @return CRouting_Route
     */
    public function match(CHTTP_Request $request) {
        $matcher = new CompiledUrlMatcher(
            $this->compiled,
            (new RequestContext())->fromRequest(
                $trimmedRequest = $this->requestWithoutTrailingSlash($request)
            )
        );

        $route = null;

        try {
            if ($result = $matcher->matchRequest($trimmedRequest)) {
                $route = $this->getByName($result['_route']);
            }
        } catch (ResourceNotFoundException|MethodNotAllowedException $e) {
            try {
                return $this->routes->match($request);
            } catch (NotFoundHttpException $e) {
            }
        }

        if ($route && $route->isFallback) {
            try {
                $dynamicRoute = $this->routes->match($request);

                if (!$dynamicRoute->isFallback) {
                    $route = $dynamicRoute;
                }
            } catch (NotFoundHttpException|MethodNotAllowedHttpException $e) {
            }
        }

        return $this->handleMatchedRoute($request, $route);
    }

    /**
     * Get a cloned instance of the given request without any trailing slash on the URI.
     *
     * @param CHTTP_Request $request
     *
     * @return CHTTP_Request
     */
    protected function requestWithoutTrailingSlash(CHTTP_Request $request) {
        $trimmedRequest = CHTTP_Request::createFromBase($request);

        $parts = explode('?', $request->server->get('REQUEST_URI'), 2);

        $trimmedRequest->server->set(
            'REQUEST_URI',
            rtrim($parts[0], '/') . (isset($parts[1]) ? '?' . $parts[1] : '')
        );

        return $trimmedRequest;
    }

    /**
     * Get routes from the collection by method.
     *
     * @param null|string $method
     *
     * @return \CRouting_Route[]
     */
    public function get($method = null) {
        return $this->getRoutesByMethod()[$method] ?? [];
    }

    /**
     * Determine if the route collection contains a given named route.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasNamedRoute($name) {
        return isset($this->attributes[$name]) || $this->routes->hasNamedRoute($name);
    }

    /**
     * Get a route instance by its name.
     *
     * @param string $name
     *
     * @return null|\CRouting_Route
     */
    public function getByName($name) {
        if (isset($this->attributes[$name])) {
            return $this->newRoute($this->attributes[$name]);
        }

        return $this->routes->getByName($name);
    }

    /**
     * Get a route instance by its controller action.
     *
     * @param string $action
     *
     * @return null|\CRouting_Route
     */
    public function getByAction($action) {
        $attributes = c::collect($this->attributes)->first(function (array $attributes) use ($action) {
            if (isset($attributes['action']['controller'])) {
                return trim($attributes['action']['controller'], '\\') === $action;
            }

            return $attributes['action']['uses'] === $action;
        });

        if ($attributes) {
            return $this->newRoute($attributes);
        }

        return $this->routes->getByAction($action);
    }

    /**
     * Get all of the routes in the collection.
     *
     * @return \CRouting_Route[]
     */
    public function getRoutes() {
        return c::collect($this->attributes)
            ->map(function (array $attributes) {
                return $this->newRoute($attributes);
            })
            ->merge($this->routes->getRoutes())
            ->values()
            ->all();
    }

    /**
     * Get all of the routes keyed by their HTTP verb / method.
     *
     * @return array
     */
    public function getRoutesByMethod() {
        return c::collect($this->getRoutes())
            ->groupBy(function (CRouting_Route $route) {
                return $route->methods();
            })
            ->map(function (CCollection $routes) {
                return $routes->mapWithKeys(function (CRouting_Route $route) {
                    return [$route->uri => $route];
                })->all();
            })
            ->all();
    }

    /**
     * Get all of the routes keyed by their name.
     *
     * @return CRouting_Route[]
     */
    public function getRoutesByName() {
        return c::collect($this->getRoutes())
            ->keyBy(function (CRouting_Route $route) {
                return $route->getName();
            })
            ->all();
    }

    /**
     * Resolve an array of attributes to a Route instance.
     *
     * @param array $attributes
     *
     * @return \CRouting_Route
     */
    protected function newRoute(array $attributes) {
        if (empty($attributes['action']['prefix'] ?? '')) {
            $baseUri = $attributes['uri'];
        } else {
            $prefix = trim($attributes['action']['prefix'], '/');

            $baseUri = trim(implode(
                '/',
                array_slice(
                    explode('/', trim($attributes['uri'], '/')),
                    count($prefix !== '' ? explode('/', $prefix) : [])
                )
            ), '/');
        }

        return $this->router->newRoute($attributes['methods'], $baseUri == '' ? '/' : $baseUri, $attributes['action'])
            ->setFallback($attributes['fallback'])
            ->setDefaults($attributes['defaults'])
            ->setWheres($attributes['wheres'])
            ->setBindingFields($attributes['bindingFields'])
            ->block($attributes['lockSeconds'] ?? null, $attributes['waitSeconds'] ?? null);
    }

    /**
     * Set the router instance on the route.
     *
     * @param CRouting_Router $router
     *
     * @return $this
     */
    public function setRouter(CRouting_Router $router) {
        $this->router = $router;

        return $this;
    }

    /**
     * Set the container instance on the route.
     *
     * @param CContainer_Container $container
     *
     * @return $this
     */
    public function setContainer(CContainer_Container $container) {
        $this->container = $container;

        return $this;
    }
}
