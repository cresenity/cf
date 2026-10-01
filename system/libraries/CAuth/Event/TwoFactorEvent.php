<?php

abstract class CAuth_Event_TwoFactorEvent {
    /**
     * The user the event is about.
     *
     * @var mixed
     */
    public $user;

    /**
     * @param mixed $user
     */
    public function __construct($user) {
        $this->user = $user;
    }
}
