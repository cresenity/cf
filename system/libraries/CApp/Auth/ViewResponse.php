<?php

class CApp_Auth_ViewResponse {
    /**
     * The name of the view or the callable used to generate the view.
     *
     * @var callable|string
     */
    protected $view;

    /**
     * Create a new response instance.
     *
     * @param callable|string $view
     *
     * @return void
     */
    public function __construct($view) {
        $this->view = $view;
    }

    /**
     * Create an HTTP response that represents the object.
     *
     * @param CHTTP_Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request = null) {
        if ($request == null) {
            $request = CHTTP::request();
        }
        if (!is_callable($this->view) || is_string($this->view)) {
            return c::view($this->view, ['request' => $request]);
        }

        $response = call_user_func($this->view, $request);

        if ($response instanceof CInterface_Responsable) {
            return $response->toResponse($request);
        }

        return $response;
    }
}
