<?php

/**
 * Description of RequestHandled.
 */
class CApi_Event_BeforeDispatch {
    /**
     * The method instance.
     *
     * @var CApi_MethodAbstract
     */
    public $method;

    /**
     * Create a new event instance.
     *
     * @param CApi_MethodAbstract $method
     *
     * @return void
     */
    public function __construct($method) {
        $this->method = $method;
    }
}
