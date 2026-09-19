<?php

/**
 * Fluent builder for a numeric rule string, e.g. `numeric|min:1|max:10`.
 */
class CValidation_Rule_Numeric {
    use CTrait_Conditionable;

    /**
     * @var array
     */
    protected $constraints = ['numeric'];

    /**
     * @param int|float $min
     * @param int|float $max
     *
     * @return $this
     */
    public function between($min, $max) {
        return $this->addRule('between:' . $min . ',' . $max);
    }

    /**
     * @param int      $min
     * @param null|int $max
     *
     * @return $this
     */
    public function decimal($min, $max = null) {
        $rule = 'decimal:' . $min;

        if ($max !== null) {
            $rule .= ',' . $max;
        }

        return $this->addRule($rule);
    }

    /**
     * @param string $field
     *
     * @return $this
     */
    public function different($field) {
        return $this->addRule('different:' . $field);
    }

    /**
     * @param int $length
     *
     * @return $this
     */
    public function digits($length) {
        return $this->integer()->addRule('digits:' . $length);
    }

    /**
     * @param int $min
     * @param int $max
     *
     * @return $this
     */
    public function digitsBetween($min, $max) {
        return $this->integer()->addRule('digits_between:' . $min . ',' . $max);
    }

    /**
     * @param string $field
     *
     * @return $this
     */
    public function greaterThan($field) {
        return $this->addRule('gt:' . $field);
    }

    /**
     * @param string $field
     *
     * @return $this
     */
    public function greaterThanOrEqualTo($field) {
        return $this->addRule('gte:' . $field);
    }

    /**
     * @param bool $strict only a real int passes, not a numeric string
     *
     * @return $this
     */
    public function integer($strict = false) {
        return $this->addRule($strict ? 'integer:strict' : 'integer');
    }

    /**
     * @param string $field
     *
     * @return $this
     */
    public function lessThan($field) {
        return $this->addRule('lt:' . $field);
    }

    /**
     * @param string $field
     *
     * @return $this
     */
    public function lessThanOrEqualTo($field) {
        return $this->addRule('lte:' . $field);
    }

    /**
     * @param int|float $value
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
    public function maxDigits($value) {
        return $this->addRule('max_digits:' . $value);
    }

    /**
     * @param int|float $value
     *
     * @return $this
     */
    public function min($value) {
        return $this->addRule('min:' . $value);
    }

    /**
     * @param int $value
     *
     * @return $this
     */
    public function minDigits($value) {
        return $this->addRule('min_digits:' . $value);
    }

    /**
     * @param int|float $value
     *
     * @return $this
     */
    public function multipleOf($value) {
        return $this->addRule('multiple_of:' . $value);
    }

    /**
     * @param string $field
     *
     * @return $this
     */
    public function same($field) {
        return $this->addRule('same:' . $field);
    }

    /**
     * @param int $value
     *
     * @return $this
     */
    public function exactly($value) {
        return $this->integer()->addRule('size:' . $value);
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
