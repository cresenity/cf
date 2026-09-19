<?php

/**
 * Renders as `contains:...` with each value quoted, so commas inside values survive.
 */
class CValidation_Rule_Contains {
    /**
     * @var array
     */
    protected $values;

    /**
     * @param array|string|\CCollection $values
     */
    public function __construct($values) {
        if ($values instanceof CCollection) {
            $values = $values->toArray();
        }

        $this->values = is_array($values) ? $values : func_get_args();
    }

    /**
     * Convert the rule to a validation string.
     *
     * @return string
     */
    #[\ReturnTypeWillChange]
    public function __toString() {
        $values = array_map(function ($value) {
            return '"' . str_replace('"', '""', $value) . '"';
        }, $this->values);

        return 'contains:' . implode(',', $values);
    }
}
