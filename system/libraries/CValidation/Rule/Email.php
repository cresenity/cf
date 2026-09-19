<?php

/**
 * Fluent builder for the `email` rule, run through a child validator so the
 * standard `email` message applies. `Email::defaults()` sets an app-wide default
 * the same way `Password::defaults()` does.
 */
class CValidation_Rule_Email implements CValidation_RuleInterface, CValidation_Contract_DataAwareRuleInterface, CValidation_Contract_ValidatorAwareRuleInterface {
    use CTrait_Conditionable;
    use CTrait_Macroable;

    /**
     * @var bool
     */
    public $validateMxRecord = false;

    /**
     * @var bool
     */
    public $preventSpoofing = false;

    /**
     * @var bool
     */
    public $nativeValidation = false;

    /**
     * @var bool
     */
    public $nativeValidationWithUnicodeAllowed = false;

    /**
     * @var bool
     */
    public $rfcCompliant = false;

    /**
     * @var bool
     */
    public $strictRfcCompliant = false;

    /**
     * @var \CValidation_Validator
     */
    protected $validator;

    /**
     * @var array
     */
    protected $data;

    /**
     * @var array
     */
    protected $customRules = [];

    /**
     * @var array
     */
    protected $messages = [];

    /**
     * The callback that will generate the "default" version of the email rule.
     *
     * @var null|array|callable|string
     */
    public static $defaultCallback;

    /**
     * Set the default callback to be used for determining the email default rules.
     *
     * If no arguments are passed, the default email rule configuration will be returned.
     *
     * @param null|callable|static $callback
     *
     * @return null|static
     */
    public static function defaults($callback = null) {
        if (is_null($callback)) {
            return static::default();
        }

        if (!is_callable($callback) && !$callback instanceof static) {
            throw new InvalidArgumentException('The given callback should be callable or an instance of ' . static::class);
        }

        static::$defaultCallback = $callback;
    }

    /**
     * Get the default configuration of the email rule.
     *
     * @return static
     */
    public static function default() {
        $email = is_callable(static::$defaultCallback)
            ? call_user_func(static::$defaultCallback)
            : static::$defaultCallback;

        return $email instanceof static ? $email : new static();
    }

    /**
     * Ensure the email is RFC compliant.
     *
     * @param bool $strict
     *
     * @return $this
     */
    public function rfcCompliant($strict = false) {
        if ($strict) {
            $this->strictRfcCompliant = true;
        } else {
            $this->rfcCompliant = true;
        }

        return $this;
    }

    /**
     * Ensure the email is strictly RFC compliant.
     *
     * @return $this
     */
    public function strict() {
        return $this->rfcCompliant(true);
    }

    /**
     * Ensure the email has a valid MX record.
     *
     * @return $this
     */
    public function validateMxRecord() {
        $this->validateMxRecord = true;

        return $this;
    }

    /**
     * Ensure the email is not attempting to spoof another address.
     *
     * @return $this
     */
    public function preventSpoofing() {
        $this->preventSpoofing = true;

        return $this;
    }

    /**
     * Validate with PHP's native filter (`filter` / `filter_unicode`).
     *
     * @param bool $allowUnicode
     *
     * @return $this
     */
    public function withNativeValidation($allowUnicode = false) {
        if ($allowUnicode) {
            $this->nativeValidationWithUnicodeAllowed = true;
        } else {
            $this->nativeValidation = true;
        }

        return $this;
    }

    /**
     * Specify additional validation rules.
     *
     * @param array|string $rules
     *
     * @return $this
     */
    public function rules($rules) {
        $this->customRules = array_merge($this->customRules, carr::wrap($rules));

        return $this;
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
        $this->messages = [];

        $validator = CValidation::createValidator(
            $this->data,
            [$attribute => $this->buildValidationRules()],
            $this->validator->customMessages,
            $this->validator->customAttributes
        );

        if ($validator->fails()) {
            $this->messages = array_merge($this->messages, $validator->messages()->all());

            return false;
        }

        return true;
    }

    /**
     * Build the array of underlying validation rules based on the current state.
     *
     * @return array
     */
    protected function buildValidationRules() {
        $rules = [];

        if ($this->rfcCompliant) {
            $rules[] = 'rfc';
        }

        if ($this->strictRfcCompliant) {
            $rules[] = 'strict';
        }

        if ($this->validateMxRecord) {
            $rules[] = 'dns';
        }

        if ($this->preventSpoofing) {
            $rules[] = 'spoof';
        }

        if ($this->nativeValidation) {
            $rules[] = 'filter';
        }

        if ($this->nativeValidationWithUnicodeAllowed) {
            $rules[] = 'filter_unicode';
        }

        $rules = count($rules) > 0 ? ['email:' . implode(',', $rules)] : ['email'];

        return array_merge($rules, $this->customRules);
    }

    /**
     * Get the validation error message.
     *
     * @return array
     */
    public function message() {
        return $this->messages;
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

    /**
     * @param array $data
     *
     * @return $this
     */
    public function setData($data) {
        $this->data = $data;

        return $this;
    }
}
