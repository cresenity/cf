<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Apr 14, 2019, 10:42:23 AM
 */
trait CTrait_Compat_Validation {
    /**
     * @param array $data
     * @param array $rules
     * @param array $messages
     * @param array $customAttributes
     *
     * @return \CValidation_Validator
     *
     * @deprecated 1.2 use createValidator
     */
    public static function factory(array $data, array $rules = [], array $messages = [], array $customAttributes = []) {
        return static::createValidator($data, $rules, $messages, $customAttributes);
    }
}
