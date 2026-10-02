<?php

/**
 * A named server-sent event for CHTTP_ResponseFactory::eventStream().
 */
class CHTTP_StreamedEvent {
    /**
     * @var string
     */
    public $event;

    /**
     * @var mixed
     */
    public $data;

    /**
     * @param string $event
     * @param mixed  $data
     */
    public function __construct($event, $data) {
        $this->event = $event;
        $this->data = $data;
    }
}
