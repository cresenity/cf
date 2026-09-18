<?php

/**
 * Description of RouteMatched.
 */
class CRouting_Event_RouteMatched {
    /**
     * The route instance.
     *
     * @var CRouting_Route
     */
    public $route;

    /**
     * The request instance.
     *
     * @var CHTTP_Request
     */
    public $request;

    /**
     * Create a new event instance.
     *
     * @param CRouting_Route $route
     * @param CHTTP_Request  $request
     *
     * @return void
     */
    public function __construct($route, $request) {
        $this->route = $route;
        $this->request = $request;
    }
}
