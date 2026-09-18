<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Apr 14, 2019, 10:33:35 AM
 */
interface CValidation_RuleInterface {
    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed  $value
     *
     * @return bool
     */
    public function passes($attribute, $value);

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message();
}
