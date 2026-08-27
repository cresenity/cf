<?php

class CFunction_SerializableClosure_Exception_MissingClosureException extends Exception {
    /**
     * Create a new exception instance.
     *
     * @param string $message
     *
     * @return void
     */
    public function __construct($message = 'Cannot reflect a serializable closure that was not restored on unserialize.') {
        parent::__construct($message);
    }
}
