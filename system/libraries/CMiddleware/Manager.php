<?php

/**
 * Description of Manager.
 */
class CMiddleware_Manager {
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [
        // \App\Http\Middleware\TrustHosts::class,
        //\App\Http\Middleware\TrustProxies::class,
        //\Fruitcake\Cors\HandleCors::class,
        //\App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        //\Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        //\App\Http\Middleware\TrimStrings::class,
        //\Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            //\App\Http\Middleware\EncryptCookies::class,
            //\Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            //\Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            //\Illuminate\View\Middleware\ShareErrorsFromSession::class,
            //\App\Http\Middleware\VerifyCsrfToken::class,
            //\Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
        'api' => [
            //'throttle:api',
            //\Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * @var CMiddleware_Manager
     */
    private static $instance;

    /**
     * @return CMiddleware_Manager
     */
    public static function instance() {
        if (static::$instance == null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Get the application's route middleware groups.
     *
     * @return array
     */
    public function getMiddlewareGroups() {
        return $this->middlewareGroups;
    }

    /**
     * Get the application's route middleware.
     *
     * @return array
     */
    public function getRouteMiddleware() {
        return $this->routeMiddleware;
    }

    /**
     * Gather the route middleware for the given request.
     *
     * @param \CHTTP_Request $request
     *
     * @return array
     */
    protected function gatherRouteMiddleware($request) {
        if ($route = $request->route()) {
            return $this->router->gatherRouteMiddleware($route);
        }

        return [];
    }

    /**
     * Parse a middleware string to get the name and parameters.
     *
     * @param string $middleware
     *
     * @return array
     */
    protected function parseMiddleware($middleware) {
        list($name, $parameters) = array_pad(explode(':', $middleware, 2), 2, []);

        if (is_string($parameters)) {
            $parameters = explode(',', $parameters);
        }

        return [$name, $parameters];
    }

    /**
     * Determine if the kernel has a given middleware.
     *
     * @param string $middleware
     *
     * @return bool
     */
    public function hasMiddleware($middleware) {
        return in_array($middleware, $this->middleware);
    }

    /**
     * Add a new middleware to beginning of the stack if it does not already exist.
     *
     * @param string $middleware
     *
     * @return $this
     */
    public function prependMiddleware($middleware) {
        if (array_search($middleware, $this->middleware) === false) {
            array_unshift($this->middleware, $middleware);
        }

        return $this;
    }

    /**
     * Add a new middleware to end of the stack if it does not already exist.
     *
     * @param string $middleware
     *
     * @return $this
     */
    public function pushMiddleware($middleware) {
        if (array_search($middleware, $this->middleware) === false) {
            $this->middleware[] = $middleware;
        }

        return $this;
    }

    /**
     * Prepend the given middleware to the given middleware group.
     *
     * @param string $group
     * @param string $middleware
     *
     * @throws \InvalidArgumentException
     *
     * @return $this
     */
    public function prependMiddlewareToGroup($group, $middleware) {
        if (!isset($this->middlewareGroups[$group])) {
            throw new InvalidArgumentException("The [{$group}] middleware group has not been defined.");
        }

        if (array_search($middleware, $this->middlewareGroups[$group]) === false) {
            array_unshift($this->middlewareGroups[$group], $middleware);
        }

        $this->syncMiddlewareToRouter();

        return $this;
    }

    /**
     * Append the given middleware to the given middleware group.
     *
     * @param string $group
     * @param string $middleware
     *
     * @throws \InvalidArgumentException
     *
     * @return $this
     */
    public function appendMiddlewareToGroup($group, $middleware) {
        if (!isset($this->middlewareGroups[$group])) {
            throw new InvalidArgumentException("The [{$group}] middleware group has not been defined.");
        }

        if (array_search($middleware, $this->middlewareGroups[$group]) === false) {
            $this->middlewareGroups[$group][] = $middleware;
        }

        $this->syncMiddlewareToRouter();

        return $this;
    }

    /**
     * Prepend the given middleware to the middleware priority list.
     *
     * @param string $middleware
     *
     * @return $this
     */
    public function prependToMiddlewarePriority($middleware) {
        if (!in_array($middleware, $this->middlewarePriority)) {
            array_unshift($this->middlewarePriority, $middleware);
        }

        $this->syncMiddlewareToRouter();

        return $this;
    }

    /**
     * Append the given middleware to the middleware priority list.
     *
     * @param string $middleware
     *
     * @return $this
     */
    public function appendToMiddlewarePriority($middleware) {
        if (!in_array($middleware, $this->middlewarePriority)) {
            $this->middlewarePriority[] = $middleware;
        }

        $this->syncMiddlewareToRouter();

        return $this;
    }

    /**
     * Sync the current state of the middleware to the router.
     *
     * @return void
     */
    protected function syncMiddlewareToRouter() {
        $router = c::router();
        $router->middlewarePriority = $this->middlewarePriority;

        foreach ($this->middlewareGroups as $key => $middleware) {
            $router->middlewareGroup($key, $middleware);
        }

        foreach ($this->routeMiddleware as $key => $middleware) {
            $router->aliasMiddleware($key, $middleware);
        }
    }

    public function getMiddleware() {
        return $this->middleware;
    }
}
