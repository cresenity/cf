<?php

/**
 * Fluent builder for a string rule string, e.g. `string|min:3|max:255`.
 */
class CValidation_Rule_StringRule {
    use CTrait_Conditionable;

    /**
     * @var array
     */
    protected $constraints = ['string'];

    /**
     * @param bool $ascii
     *
     * @return $this
     */
    public function alpha($ascii = false) {
        return $this->addRule($ascii ? 'alpha:ascii' : 'alpha');
    }

    /**
     * @param bool $ascii
     *
     * @return $this
     */
    public function alphaDash($ascii = false) {
        return $this->addRule($ascii ? 'alpha_dash:ascii' : 'alpha_dash');
    }

    /**
     * @param bool $ascii
     *
     * @return $this
     */
    public function alphaNumeric($ascii = false) {
        return $this->addRule($ascii ? 'alpha_num:ascii' : 'alpha_num');
    }

    /**
     * @return $this
     */
    public function ascii() {
        return $this->addRule('ascii');
    }

    /**
     * @param int $min
     * @param int $max
     *
     * @return $this
     */
    public function between($min, $max) {
        return $this->addRule('between:' . $min . ',' . $max);
    }

    /**
     * @param string ...$values
     *
     * @return $this
     */
    public function doesntEndWith(...$values) {
        return $this->addRule('doesnt_end_with:' . implode(',', $values));
    }

    /**
     * @param string ...$values
     *
     * @return $this
     */
    public function doesntStartWith(...$values) {
        return $this->addRule('doesnt_start_with:' . implode(',', $values));
    }

    /**
     * @param string ...$values
     *
     * @return $this
     */
    public function endsWith(...$values) {
        return $this->addRule('ends_with:' . implode(',', $values));
    }

    /**
     * @param int $value
     *
     * @return $this
     */
    public function exactly($value) {
        return $this->addRule('size:' . $value);
    }

    /**
     * @return $this
     */
    public function lowercase() {
        return $this->addRule('lowercase');
    }

    /**
     * @param int $value
     *
     * @return $this
     */
    public function max($value) {
        return $this->addRule('max:' . $value);
    }

    /**
     * @param int $value
     *
     * @return $this
     */
    public function min($value) {
        return $this->addRule('min:' . $value);
    }

    /**
     * @param string ...$values
     *
     * @return $this
     */
    public function startsWith(...$values) {
        return $this->addRule('starts_with:' . implode(',', $values));
    }

    /**
     * @return $this
     */
    public function uppercase() {
        return $this->addRule('uppercase');
    }

    /**
     * @return string
     */
    public function __toString() {
        return implode('|', array_unique($this->constraints));
    }

    /**
     * @param array|string $rules
     *
     * @return $this
     */
    protected function addRule($rules) {
        $this->constraints = array_merge($this->constraints, carr::wrap($rules));

        return $this;
    }
}
