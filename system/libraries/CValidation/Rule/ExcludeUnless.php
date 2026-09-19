<?php

/**
 * Renders as `exclude` unless the condition holds (the mirror image of ExcludeIf).
 */
class CValidation_Rule_ExcludeUnless {
    /**
     * The condition that validates the attribute.
     *
     * @var bool|callable
     */
    public $condition;

    /**
     * @param bool|callable $condition
     */
    public function __construct($condition) {
        if (is_string($condition)) {
            throw new InvalidArgumentException('The provided condition must be a callable or boolean.');
        }

        $this->condition = $condition;
    }

    /**
     * Convert the rule to a validation string.
     *
     * @return string
     */
    #[\ReturnTypeWillChange]
    public function __toString() {
        if (is_callable($this->condition)) {
            return call_user_func($this->condition) ? '' : 'exclude';
        }

        return $this->condition ? '' : 'exclude';
    }
}
