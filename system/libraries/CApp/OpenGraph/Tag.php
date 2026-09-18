<?php

/**
 * Description of Tag.
 */
class CApp_OpenGraph_Tag {
    /**
     * The name of the tag.
     *
     * @var string
     */
    protected $name;

    /**
     * The value of the tag.
     *
     * @var mixed
     */
    protected $value;

    /**
     * Add the "og"-prefix?
     *
     * @var bool
     */
    protected $prefixed;

    /**
     * Constructor call.
     *
     * @param string $name     The name of the tag
     * @param mixed  $value    The value of the tag
     * @param bool   $prefixed Add the "og"-prefix?
     */
    public function __construct($name, $value, $prefixed = true) {
        $this->setAttribute('name', $name);
        $this->setAttribute('value', $value);
        $this->setAttribute('prefixed', $prefixed);
    }

    /**
     * Magic getter for the tag's attributes.
     *
     * @param string $name The name of the object attribute
     *
     * @return mixed
     */
    public function __get($name) {
        return $this->$name;
    }

    /**
     * Magic setter for the tag's attributes.
     *
     * @param string $name  The name of the object attribute
     * @param mixed  $value The value of the object attribute
     *
     * @return void
     */
    public function __set($name, $value) {
        $this->setAttribute($name, $value);
    }

    /**
     * Sets an object attribute to a value.
     *
     * @param string $name  The name of the object attribute
     * @param mixed  $value The value of the object attribute
     *
     * @return void
     */
    protected function setAttribute($name, $value) {
        // Convert values
        switch ($name) {
            case 'name':
            case 'value':
                $value = (string) $value;

                break;
            case 'prefixed':
                $value = (boolean) $value;

                break;
        }
        $this->$name = $value;
    }
}
