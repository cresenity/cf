<?php

/**
 * @deprecated see CValidation_Contract_ValidationRuleInterface
 */
interface CValidation_Contract_InvokableRuleInterface {
    /**
     * Run the validation rule.
     *
     * @param string $attribute
     * @param mixed  $value
     * @param  \Closure(string): \CTranslation_PotentiallyTranslatedString $fail
     *
     * @return void
     */
    public function __invoke(string $attribute, $value, Closure $fail);
}
