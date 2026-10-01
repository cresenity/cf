<?php

/**
 * Alamat email dan nama untuk Envelope.
 */
class CEmail_Mailable_Address {
    /**
     * @var string
     */
    public $address;

    /**
     * @var null|string
     */
    public $name;

    /**
     * @param string      $address
     * @param null|string $name
     */
    public function __construct($address, $name = null) {
        $this->address = $address;
        $this->name = $name;
    }
}
