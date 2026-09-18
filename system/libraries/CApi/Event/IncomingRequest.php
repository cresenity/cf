<?php

/**
 * Description of RequestHandled.
 */
class CApi_Event_IncomingRequest {
    /**
     * The request instance.
     *
     * @var CHTTP_Request
     */
    public $request;

    /**
     * Create a new event instance.
     *
     * @param CHTTP_Request $request
     *
     * @return void
     */
    public function __construct($request) {
        $this->request = $request;
    }
}
