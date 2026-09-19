<?php

use Google\Analytics\Data\V1beta\Filter\StringFilter\MatchType;

class CAnalytics_Google_AnalyticGA4_Filter_StringFilter {
    /**
     * Exact match of the string value.
     *
     * Generated from protobuf enum <code>EXACT = 1;</code>
     */
    const EXACT = 1;

    /**
     * Begins with the string value.
     *
     * Generated from protobuf enum <code>BEGINS_WITH = 2;</code>
     */
    const BEGINS_WITH = 2;

    /**
     * Ends with the string value.
     *
     * Generated from protobuf enum <code>ENDS_WITH = 3;</code>
     */
    const ENDS_WITH = 3;

    /**
     * Contains the string value.
     *
     * Generated from protobuf enum <code>CONTAINS = 4;</code>
     */
    const CONTAINS = 4;

    /**
     * Full regular expression match with the string value.
     *
     * Generated from protobuf enum <code>FULL_REGEXP = 5;</code>
     */
    const FULL_REGEXP = 5;

    /**
     * Partial regular expression match with the string value.
     *
     * Generated from protobuf enum <code>PARTIAL_REGEXP = 6;</code>
     */
    const PARTIAL_REGEXP = 6;

    protected $operator;

    protected $value;

    public function exact($value) {
        $this->operator = MatchType::EXACT;
        $this->value = $value;

        return $this;
    }

    public function beginsWith($value) {
        $this->operator = MatchType::BEGINS_WITH;
        $this->value = $value;

        return $this;
    }

    public function endsWith($value) {
    }

    public function contains($value) {
    }

    public function fullRegexp($value) {
    }

    public function partialRegexp($value) {
    }
}
