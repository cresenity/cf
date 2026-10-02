<?php

class CHTTP_Client_Event_ConnectionFailed {
    /**
     * @var CHTTP_Client_Request
     */
    public $request;

    /**
     * @var null|CHTTP_Client_Exception_ConnectionException
     */
    public $exception;

    /**
     * @param CHTTP_Client_Request                             $request
     * @param null|CHTTP_Client_Exception_ConnectionException $exception
     */
    public function __construct(CHTTP_Client_Request $request, $exception = null) {
        $this->request = $request;
        $this->exception = $exception;
    }
}
