<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * CF Controller class. The controller class must be extended to work
 * properly, so this class is defined as abstract.
 */
abstract class CController {
    // Allow all controllers to run in production by default
    const ALLOW_PRODUCTION = true;

    protected $baseUri;

    /**
     * @var CController_Input
     */
    protected $input;

    /**
     * The middleware registered on the controller.
     *
     * @var array
     */
    protected $middleware = [];

    /**
     * Loads URI, and Input into this controller.
     *
     * @return void
     */
    public function __construct() {
        if (CF::$instance == null) {
            // Set the instance to the first controller loaded
            CF::$instance = $this;
        }

        // Input should always be available
        $this->input = CController_Input::instance();

        $this->baseUri = CFRouter::controllerUri();
    }

    /**
     * Register middleware on the controller.
     *
     * @param \Closure|array|string $middleware
     * @param array                 $options
     *
     * @return \CController_MiddlewareOptions
     */
    public function middleware($middleware, array $options = []) {
        foreach ((array) $middleware as $m) {
            $this->middleware[] = [
                'middleware' => $m,
                'options' => &$options,
            ];
        }

        return new CController_MiddlewareOptions($options);
    }

    /**
     * Get the middleware assigned to the controller.
     *
     * @return array
     */
    public function getMiddleware() {
        return $this->middleware;
    }

    /**
     * Execute an action on the controller.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function callAction($method, $parameters) {
        return $this->{$method}(...array_values($parameters));
    }

    /**
     * Handles methods that do not exist.
     *
     * @param string $method method name
     * @param array  $args   arguments
     *
     * @return void
     */
    public function __call($method, $args) {
        // Default to showing a 404 page
        CF::show404();
    }

    public static function controllerUrl() {
        $class = get_called_class();
        $classExplode = explode('_', $class);
        $classExplode = array_map(function ($item) {
            return cstr::camel($item);
        }, $classExplode);
        $url = curl::base() . implode('/', array_slice($classExplode, 1)) . '/';

        return $url;
    }
}

// End Controller Class
