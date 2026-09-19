<?php

/**
 * Passes when the value satisfies at least one of the given rule sets, e.g.
 * `Rule::anyOf(['email', 'digits:10'])` for "an email or a phone number".
 */
class CValidation_Rule_AnyOf implements CValidation_RuleInterface, CValidation_Contract_ValidatorAwareRuleInterface {
    /**
     * @var array
     */
    protected $rules = [];

    /**
     * @var \CValidation_Validator
     */
    protected $validator;

    /**
     * @param array $rules each entry is a rule string/array, or an associative array of rules for an array value
     */
    public function __construct($rules) {
        if (!is_array($rules)) {
            throw new InvalidArgumentException('The provided value must be an array of validation rules.');
        }

        $this->rules = $rules;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed  $value
     *
     * @return bool
     */
    public function passes($attribute, $value) {
        foreach ($this->rules as $rule) {
            $validator = CValidation::createValidator(
                carr::isAssoc(carr::wrap($value)) ? $value : [$value],
                carr::isAssoc(carr::wrap($rule)) ? $rule : [$rule],
                $this->validator->customMessages,
                $this->validator->customAttributes
            );

            if ($validator->passes()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the validation error message.
     *
     * @return array|string
     */
    public function message() {
        $message = $this->validator->getTranslator()->trans('validation.any_of');

        return $message === 'validation.any_of'
            ? ['The :attribute field is invalid.']
            : $message;
    }

    /**
     * @param \CValidation_Validator $validator
     *
     * @return $this
     */
    public function setValidator($validator) {
        $this->validator = $validator;

        return $this;
    }
}
