<?php

/**
 * Description of RouteCollection.
 */
class CRouting_RouteCollection extends CRouting_RouteCollectionAbstract {
    /**
     * An array of the routes keyed by method.
     *
     * @var array
     */
    protected $routes = [];

    /**
     * A flattened array of all of the routes.
     *
     * @var CRouting_Route[]
     */
    protected $allRoutes = [];

    /**
     * A look-up table of routes by their names.
     *
     * @var CRouting_Route[]
     */
    protected $nameList = [];

    /**
     * A look-up table of routes by controller action.
     *
     * @var CRouting_Route[]
     */
    protected $actionList = [];

    /**
     * Add a Route instance to the collection.
     *
     * @param CRouting_Route $route
     *
     * @return CRouting_Route
     */
    public function add(CRouting_Route $route) {
        $this->addToCollections($route);

        $this->addLookups($route);

        return $route;
    }

    /**
     * Add the given route to the arrays of routes.
     *
     * @param CRouting_Route $route
     *
     * @return void
     */
    protected function addToCollections($route) {
        $domainAndUri = $route->getDomain() . $route->uri();

        foreach ($route->methods() as $method) {
            $this->routes[$method][$domainAndUri] = $route;
        }

        $this->allRoutes[$method . $domainAndUri] = $route;
    }

    /**
     * Add the route to any look-up tables if necessary.
     *
     * @param CRouting_Route $route
     *
     * @return void
     */
    protected function addLookups($route) {
        // If the route has a name, we will add it to the name look-up table so that we
        // will quickly be able to find any route associate with a name and not have
        // to iterate through every route every time we need to perform a look-up.
        if ($name = $route->getName()) {
            $this->nameList[$name] = $route;
        }

        // When the route is routing to a controller we will also store the action that
        // is used by the route. This will let us reverse route to controllers while
        // processing a request and easily generate URLs to the given controllers.
        $action = $route->getAction();

        if (isset($action['controller'])) {
            $this->addToActionList($action, $route);
        }
    }

    /**
     * Add a route to the controller action dictionary.
     *
     * @param array          $action
     * @param CRouting_Route $route
     *
     * @return void
     */
    protected function addToActionList($action, $route) {
        $this->actionList[trim($action['controller'], '\\')] = $route;
    }

    /**
     * Refresh the name look-up table.
     *
     * This is done in case any names are fluently defined or if routes are overwritten.
     *
     * @return void
     */
    public function refreshNameLookups() {
        $this->nameList = [];

        foreach ($this->allRoutes as $route) {
            if ($route->getName()) {
                $this->nameList[$route->getName()] = $route;
            }
        }
    }

    /**
     * Refresh the action look-up table.
     *
     * This is done in case any actions are overwritten with new controllers.
     *
     * @return void
     */
    public function refreshActionLookups() {
        $this->actionList = [];

        foreach ($this->allRoutes as $route) {
            if (isset($route->getAction()['controller'])) {
                $this->addToActionList($route->getAction(), $route);
            }
        }
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
        $routes = $this->get($request->getMethod());

        // First, we will see if we can find a matching route for this current request
        // method. If we can, great, we can just return it so that it can be called
        // by the consumer. Otherwise we will check for routes with another verb.
        $route = $this->matchAgainstRoutes($routes, $request);

        //if route still null we will search new route that match with controller
        if ($route == null) {
            $uri = ltrim($request->path(), '/');
            if (PHP_SAPI == 'cli') {
                $uri = CRouting_UrlFinder::getUri();
            }
            $route = CRouting_RouteFinder::find($uri);
        }

        return $this->handleMatchedRoute($request, $route);
    }

    /**
     * Get routes from the collection by method.
     *
     * @param null|string $method
     *
     * @return CRouting_Route[]
     */
    public function get($method = null) {
        return is_null($method) ? $this->getRoutes() : carr::get($this->routes, $method, []);
    }

    /**
     * Determine if the route collection contains a given named route.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasNamedRoute($name) {
        return !is_null($this->getByName($name));
    }

    /**
     * Get a route instance by its name.
     *
     * @param string $name
     *
     * @return null|CRouting_Route
     */
    public function getByName($name) {
        return carr::get($this->nameList, $name);
    }

    /**
     * Get a route instance by its controller action.
     *
     * @param string $action
     *
     * @return null|CRouting_Route
     */
    public function getByAction($action) {
        return carr::get($this->actionList, $action);
    }

    /**
     * Get all of the routes in the collection.
     *
     * @return CRouting_Route[]
     */
    public function getRoutes() {
        return array_values($this->allRoutes);
    }

    /**
     * Get all of the routes keyed by their HTTP verb / method.
     *
     * @return array
     */
    public function getRoutesByMethod() {
        return $this->routes;
    }

    /**
     * Get all of the routes keyed by their name.
     *
     * @return CRouting_Route[]
     */
    public function getRoutesByName() {
        return $this->nameList;
    }

    /**
     * Convert the collection to a Symfony RouteCollection instance.
     *
     * @return \Symfony\Component\Routing\RouteCollection
     */
    public function toSymfonyRouteCollection() {
        $symfonyRoutes = parent::toSymfonyRouteCollection();

        $this->refreshNameLookups();

        return $symfonyRoutes;
    }

    /**
     * Convert the collection to a CompiledRouteCollection instance.
     *
     * @param CRouting_Router      $router
     * @param CContainer_Container $container
     *
     * @return CRouting_CompiledRouteCollection
     */
    public function toCompiledRouteCollection(CRouting_Router $router, CContainer_Container $container) {
        $result = $this->compile();
        $compiled = carr::get($result, 'compiled');
        $attributes = carr::get($result, 'attributes');

        return (new CRouting_CompiledRouteCollection($compiled, $attributes))
            ->setRouter($router)
            ->setContainer($container);
    }
}
