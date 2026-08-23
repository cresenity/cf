<?php

class CTrader_Exception extends Exception {
    public static function create($returnCode) {
        return new static(CTrader_ReturnCode::getMessage($returnCode), $returnCode);
    }
}
