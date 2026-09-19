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
     * Apply the given rules unless the condition holds (the mirror image of `when()`).
     *
     * @param bool|callable        $condition
     * @param array|string|Closure $rules
     * @param array|string|Closure $defaultRules
     *
     * @return \CValidation_ConditionalRules
     */
    public static function unless($condition, $rules, $defaultRules = []) {
        return new CValidation_ConditionalRules($condition, $defaultRules, $rules);
    }

    /**
     * Get a required_unless constraint builder instance.
     *
     * @param bool|callable $callback
     *
     * @return \CValidation_Rule_RequiredUnless
     */
    public static function requiredUnless($callback) {
        return new CValidation_Rule_RequiredUnless($callback);
    }

    /**
     * Get an exclude_unless constraint builder instance.
     *
     * @param bool|callable $callback
     *
     * @return \CValidation_Rule_ExcludeUnless
     */
    public static function excludeUnless($callback) {
        return new CValidation_Rule_ExcludeUnless($callback);
    }

    /**
     * Get a prohibited_unless constraint builder instance.
     *
     * @param bool|callable $callback
     *
     * @return \CValidation_Rule_ProhibitedUnless
     */
    public static function prohibitedUnless($callback) {
        return new CValidation_Rule_ProhibitedUnless($callback);
    }

    /**
     * Get a contains constraint builder instance.
     *
     * @param array|string|\CCollection $values
     *
     * @return \CValidation_Rule_Contains
     */
    public static function contains($values) {
        return new CValidation_Rule_Contains(is_array($values) || $values instanceof CCollection ? $values : func_get_args());
    }

    /**
     * Get a doesnt_contain constraint builder instance.
     *
     * @param array|string|\CCollection $values
     *
     * @return \CValidation_Rule_DoesntContain
     */
    public static function doesntContain($values) {
        return new CValidation_Rule_DoesntContain(is_array($values) || $values instanceof CCollection ? $values : func_get_args());
    }

    /**
     * Get an array_keys constraint builder instance.
     *
     * @param array|string|\CCollection $keys
     *
     * @return \CValidation_Rule_ArrayKeys
     */
    public static function arrayKeys($keys) {
        return new CValidation_Rule_ArrayKeys(is_array($keys) || $keys instanceof CCollection ? $keys : func_get_args());
    }

    /**
     * Get an array rule builder instance.
     *
     * @param null|array $keys
     *
     * @return \CValidation_Rule_ArrayRule
     */
    public static function array($keys = null) {
        return new CValidation_Rule_ArrayRule(...func_get_args());
    }

    /**
     * Get a can constraint builder instance.
     *
     * @param string $ability
     * @param mixed  ...$arguments
     *
     * @return \CValidation_Rule_Can
     */
    public static function can($ability, ...$arguments) {
        return new CValidation_Rule_Can($ability, $arguments);
    }

    /**
     * Get an enum constraint builder instance.
     *
     * @param class-string $type
     *
     * @return \CValidation_Rule_Enum
     */
    public static function enum($type) {
        return new CValidation_Rule_Enum($type);
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
     * Get a numeric rule builder instance.
     *
     * @return \CValidation_Rule_Numeric
     */
    public static function numeric() {
        return new CValidation_Rule_Numeric();
    }

    /**
     * Get a string rule builder instance.
     *
     * @return \CValidation_Rule_StringRule
     */
    public static function string() {
        return new CValidation_Rule_StringRule();
    }

    /**
     * Get a date rule builder instance.
     *
     * @return \CValidation_Rule_Date
     */
    public static function date() {
        return new CValidation_Rule_Date();
    }

    /**
     * Get a date rule builder instance for `Y-m-d H:i:s` values.
     *
     * @return \CValidation_Rule_Date
     */
    public static function dateTime() {
        return (new CValidation_Rule_Date())->format('Y-m-d H:i:s');
    }

    /**
     * Get an email rule builder instance.
     *
     * @return \CValidation_Rule_Email
     */
    public static function email() {
        return new CValidation_Rule_Email();
    }

    /**
     * Get an "any of" rule builder instance.
     *
     * @param array $rules
     *
     * @return \CValidation_Rule_AnyOf
     */
    public static function anyOf($rules) {
        return new CValidation_Rule_AnyOf($rules);
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
