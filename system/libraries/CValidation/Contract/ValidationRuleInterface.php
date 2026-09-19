<?php

interface CValidation_Contract_ValidationRuleInterface {
    /**
     * Run the validation rule.
     *
     * @param string $attribute
     * @param mixed  $value
     * @param  \Closure(string): \CTranslator_PotentiallyTranslatedString  $fail
     *
     * @return void
     */
    public function validate(string $attribute, $value, Closure $fail): void;
}
