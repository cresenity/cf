<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Apr 12, 2019, 7:56:27 PM
 */
class CValidation_Rule {
    /**
     * Create a new conditional rule set.
     *
     * @param callable|bool         $condition
     * @param array|string|\Closure $rules
     * @param array|string|\Closure $defaultRules
     *
     * @return \CValidation_ConditionalRules
     */
    public static function when($condition, $rules, $defaultRules = []) {
        return new CValidation_ConditionalRules($condition, $rules, $defaultRules);
    }

    /**
     * Create a new nested rule set.
     *
     * @param callable $callback
     *
     * @return \CValidation_NestedRules
     */
    public static function forEach($callback) {
        return new CValidation_NestedRules($callback);
    }

    /**
     * Get a unique constraint builder instance.
     *
     * @param string $table
     * @param string $column
     *
     * @return CValidation_Rule_Unique
     */
    public static function unique($table, $column = 'NULL') {
        return new CValidation_Rule_Unique($table, $column);
    }

    /**
     * Get a exists constraint builder instance.
     *
     * @param string $table
     * @param string $column
     *
     * @return CValidation_Rule_Exists
     */
    public static function exists($table, $column = 'NULL') {
        return new CValidation_Rule_Exists($table, $column);
    }

    /**
     * Get an in constraint builder instance.
     *
     * @param array|string|CCollection $values
     *
     * @return CValidation_Rule_In
     */
    public static function in($values) {
        if ($values instanceof CCollection) {
            $values = $values->toArray();
        }

        return new CValidation_Rule_In(is_array($values) ? $values : func_get_args());
    }

    /**
     * Get a not_in constraint builder instance.
     *
     * @param array|string|CCollection $values
     *
     * @return CValidation_Rule_NotIn
     */
    public static function notIn($values) {
        if ($values instanceof CCollection) {
            $values = $values->toArray();
        }

        return new CValidation_Rule_NotIn(is_array($values) ? $values : func_get_args());
    }

    /**
     * Get a required_if constraint builder instance.
     *
     * @param callable $callback
     *
     * @return CValidation_Rule_RequiredIf
     */
    public static function requiredIf($callback) {
        return new CValidation_Rule_RequiredIf($callback);
    }

    /**
     * Get a exclude_if constraint builder instance.
     *
     * @param callable|bool $callback
     *
     * @return CValidation_Rule_ExcludeIf
     */
    public static function excludeIf($callback) {
        return new CValidation_Rule_ExcludeIf($callback);
    }

    /**
     * Get a prohibited_if constraint builder instance.
     *
     * @param callable|bool $callback
     *
     * @return \CValidation_Rule_ProhibitedIf
     */
    public static function prohibitedIf($callback) {
        return new CValidation_Rule_ProhibitedIf($callback);
    }

    /**
     * Get a file constraint builder instance.
     *
     * @return \CValidation_Rule_File
     */
    public static function file() {
        return new CValidation_Rule_File();
    }

    /**
     * Get an image file constraint builder instance.
     *
     * @return \CValidation_Rule_ImageFile
     */
    public static function imageFile() {
        return new CValidation_Rule_ImageFile();
    }

    /**
     * Get a dimensions constraint builder instance.
     *
     * @param array $constraints
     *
     * @return CValidation_Rule_Dimension
     */
    public static function dimensions(array $constraints = []) {
        return new CValidation_Rule_Dimension($constraints);
    }

    /**
     * Get a unique constraint builder instance.
     *
     * @param int $min
     *
     * @return \CValidation_Rule_Password
     */
    public static function password($min = 8) {
        return new CValidation_Rule_Password($min);
    }

    /**
     * Get a unique constraint builder instance.
     *
     * @param callable $callback
     *
     * @return \CValidation_ClosureValidationRule
     */
    public static function closure($callback) {
        if ($callback instanceof Closure) {
            $callback = new CFunction_SerializableClosure($callback);
        }

        return new CValidation_ClosureValidationRule($callback);
    }
}
