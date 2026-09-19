<?php


class CValidation_Rule_Enum implements CValidation_RuleInterface, CValidation_Contract_ValidatorAwareRuleInterface {
    use CTrait_Conditionable;

    /**
     * The type of the enum.
     *
     * @var string
     */
    protected $type;

    /**
     * The current validator instance.
     *
     * @var \CValidation_Validator
     */
    protected $validator;

    /**
     * The cases that should be considered valid.
     *
     * @var array
     */
    protected $only = [];

    /**
     * The cases that should be considered invalid.
     *
     * @var array
     */
    protected $except = [];

    /**
     * Create a new rule instance.
     *
     * @param string $type
     *
     * @return void
     */
    public function __construct($type) {
        $this->type = $type;
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
        if ($value instanceof $this->type) {
            return $this->isDesirable($value);
        }

        if (is_null($value) || !enum_exists($this->type) || !method_exists($this->type, 'tryFrom')) {
            return false;
        }

        try {
            $value = $this->type::tryFrom($value);

            return !is_null($value) && $this->isDesirable($value);
        } catch (TypeError $ex) {
            return false;
        }
    }

    /**
     * Specify the cases that should be considered valid.
     *
     * @param \UnitEnum[]|\UnitEnum $values
     *
     * @return $this
     */
    public function only($values) {
        $this->only = carr::wrap($values);

        return $this;
    }

    /**
     * Specify the cases that should be considered invalid.
     *
     * @param \UnitEnum[]|\UnitEnum $values
     *
     * @return $this
     */
    public function except($values) {
        $this->except = carr::wrap($values);

        return $this;
    }

    /**
     * Determine if the given case is a valid case based on the only / except values.
     *
     * @param mixed $value
     *
     * @return bool
     */
    protected function isDesirable($value) {
        if (!empty($this->only)) {
            return in_array($value, $this->only, true);
        }
        if (!empty($this->except)) {
            return !in_array($value, $this->except, true);
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return array
     */
    public function message() {
        $message = $this->validator->getTranslator()->get('validation.enum');

        return $message === 'validation.enum'
            ? ['The selected :attribute is invalid.']
            : $message;
    }

    /**
     * Set the current validator.
     *
     * @param \CValidation_Validator $validator
     *
     * @return $this
     */
    public function setValidator($validator) {
        $this->validator = $validator;

        return $this;
    }
}
