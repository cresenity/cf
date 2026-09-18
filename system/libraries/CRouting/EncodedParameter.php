<?php

/**
 * A route parameter value that is already URL encoded and must be used as-is.
 */
class CRouting_EncodedParameter {
    /**
     * @var string
     */
    protected $value;

    /**
     * @param string $value
     */
    public function __construct($value) {
        $this->value = (string) $value;
    }

    /**
     * Get the encoded parameter value.
     *
     * @return string
     */
    public function value() {
        return $this->value;
    }

    /**
     * @return string
     */
    public function __toString() {
        return $this->value;
    }
}
