<?php

class CServer_Browsershot_Exception_ElementNotFound extends Exception {
    public function __construct($selector) {
        parent::__construct("The given selector `{$selector} did not match any elements");
    }
}
