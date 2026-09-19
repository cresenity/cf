<?php

class CServer_Browsershot_Exception_UnsuccessfulResponse extends Exception {
    public function __construct($url, $code) {
        parent::__construct("The given url `{$url}` responds with code {$code}");
    }
}
