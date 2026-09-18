<?php

/**
 * Description of RequestHandled.
 */
class CApi_Event_RequestHandled {
    /**
     * The request instance.
     *
     * @var CHTTP_Request
     */
    public $request;

    /**
     * The response instance.
     *
     * @var CHTTP_Response
     */
    public $response;

    /**
     * Create a new event instance.
     *
     * @param CHTTP_Request  $request
     * @param CHTTP_Response $response
     *
     * @return void
     */
    public function __construct($request, $response) {
        $this->request = $request;
        $this->response = $response;
    }
}
