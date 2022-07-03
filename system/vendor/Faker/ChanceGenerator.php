<?php

namespace Faker;

use Faker\Extension\Extension;

/**
 * This generator returns a default value for all called properties
 * and methods. It works with Faker\Generator::optional().
 *
 * @mixin Generator
 */
class ChanceGenerator {
    protected $default;

    private $generator;

    private $weight;

    /**
     * @param Extension|Generator $generator
     * @param null|mixed          $default
     */
    public function __construct($generator, float $weight, $default = null) {
        $this->default = $default;
        $this->generator = $generator;
        $this->weight = $weight;
    }

    public function ext(string $id) {
        return new self($this->generator->ext($id), $this->weight, $this->default);
    }

    /**
     * Catch and proxy all generator calls but return only valid values.
     *
     * @param string $attribute
     *
     * @deprecated use a method instead
     */
    public function __get($attribute) {
        trigger_error('fakerphp/faker', '1.14', 'Accessing property "%s" is deprecated, use "%s()" instead.', $attribute, $attribute);

        return $this->__call($attribute, []);
    }

    /**
     * @param string $name
     * @param array  $arguments
     */
    public function __call($name, $arguments) {
        if (mt_rand(1, 100) <= (100 * $this->weight)) {
            return call_user_func_array([$this->generator, $name], $arguments);
        }

        return $this->default;
    }
}
