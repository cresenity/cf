<?php

class CHTTP_Exception_MalformedUrlException extends CHTTP_Exception_HttpException {
    public function __construct() {
        parent::__construct(400, 'Malformed URL.');
    }
}
