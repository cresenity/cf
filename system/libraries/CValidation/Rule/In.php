<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Apr 15, 2019, 1:01:44 PM
 */
class CValidation_Rule_In {
    /**
     * The name of the rule.
     */
    protected $rule = 'in';

    /**
     * The accepted values.
     *
     * @var array
     */
    protected $values;

    /**
     * Create a new in rule instance.
     *
     * @param array $values
     *
     * @return void
     */
    public function __construct(array $values) {
        $this->values = $values;
    }

    /**
     * Convert the rule to a validation string.
     *
     * @return string
     *
     * @see \CValidation_RuleParser::parseParameters
     */
    #[\ReturnTypeWillChange]
    public function __toString() {
        $values = array_map(function ($value) {
            return '"' . str_replace('"', '""', $value) . '"';
        }, $this->values);

        return $this->rule . ':' . implode(',', $values);
    }
}
