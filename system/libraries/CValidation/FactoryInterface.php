<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 30, 2019, 4:30:24 PM
 */
interface CValidation_FactoryInterface {
    /**
     * Create a new Validator instance.
     *
     * @param array $data
     * @param array $rules
     * @param array $messages
     * @param array $customAttributes
     *
     * @return CValidation_Validator
     */
    public function make(array $data, array $rules, array $messages = [], array $customAttributes = []);

    /**
     * Register a custom validator extension.
     *
     * @param string          $rule
     * @param \Closure|string $extension
     * @param string|null     $message
     *
     * @return void
     */
    public function extend($rule, $extension, $message = null);

    /**
     * Register a custom implicit validator extension.
     *
     * @param string          $rule
     * @param \Closure|string $extension
     * @param string|null     $message
     *
     * @return void
     */
    public function extendImplicit($rule, $extension, $message = null);

    /**
     * Register a custom implicit validator message replacer.
     *
     * @param string          $rule
     * @param \Closure|string $replacer
     *
     * @return void
     */
    public function replacer($rule, $replacer);
}
