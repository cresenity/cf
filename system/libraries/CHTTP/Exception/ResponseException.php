<?php

use Symfony\Component\HttpFoundation\Response;

class CHTTP_Exception_ResponseException extends RuntimeException {
    /**
     * The underlying response instance.
     *
     * @var \Symfony\Component\HttpFoundation\Response
     */
    protected $response;

    /**
     * Create a new HTTP response exception instance.
     *
     * @param \Symfony\Component\HttpFoundation\Response $response
     * @param null|Throwable                            $previous
     *
     * @return void
     */
    public function __construct(Response $response, ?Throwable $previous = null) {
        $this->response = $response;

        parent::__construct($previous ? $previous->getMessage() : '', $previous ? $previous->getCode() : 0, $previous);
    }

    /**
     * Get the underlying response instance.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getResponse() {
        return $this->response;
    }
}
