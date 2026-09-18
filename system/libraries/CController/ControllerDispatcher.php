<?php

/**
 * Description of CController_Dispatcher.
 *
 *
 * @see CController
 */
class CController_ControllerDispatcher {
    /*implements ControllerDispatcherContract*/
    use CRouting_Concern_RouteDependencyResolverTrait;

    /**
     * Create a new controller dispatcher instance.
     *
     * @return void
     */
    public function __construct() {
    }

    /**
     * Dispatch a request to a given controller and method.
     *
     * @param CRouting_Route $route
     * @param mixed          $controller
     * @param string         $method
     *
     * @return mixed
     */
    public function dispatch(CRouting_Route $route, $controller, $method) {
        $parameters = $this->resolveClassMethodDependencies(
            $route->parametersWithoutNulls(),
            $controller,
            $method
        );

        if (method_exists($controller, 'callAction')) {
            return $controller->callAction($method, $parameters);
        }
        $response = $controller->{$method}(...array_values($parameters));

        return $response;
    }

    /**
     * Get the middleware for the controller instance.
     *
     * @param \CController $controller
     * @param string       $method
     *
     * @return array
     */
    public function getMiddleware($controller, $method) {
        if (!method_exists($controller, 'getMiddleware')) {
            return [];
        }

        return c::collect($controller->getMiddleware())->reject(function ($data) use ($method) {
            return static::methodExcludedByOptions($method, $data['options']);
        })->pluck('middleware')->all();
    }

    /**
     * Determine if the given options exclude a particular method.
     *
     * @param string $method
     * @param array  $options
     *
     * @return bool
     */
    protected static function methodExcludedByOptions($method, array $options) {
        return (isset($options['only']) && !in_array($method, (array) $options['only']))
                || (!empty($options['except']) && in_array($method, (array) $options['except']));
    }
}
