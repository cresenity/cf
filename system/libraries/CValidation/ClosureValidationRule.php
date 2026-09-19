<?php

class CValidation_ClosureValidationRule implements CValidation_RuleInterface, CValidation_Contract_ValidatorAwareRuleInterface {
    use CValidation_Trait_CreatesPotentiallyTranslatedStringsTrait;

    /**
     * The callback that validates the attribute.
     *
     * @var \Closure
     */
    public $callback;

    /**
     * Indicates if the validation callback failed.
     *
     * @var bool
     */
    public $failed = false;

    /**
     * The last validation error message, kept for callers that read it directly.
     *
     * @var null|string
     */
    public $message;

    /**
     * The validation error messages.
     *
     * @var array
     */
    public $messages = [];

    /**
     * The current validator.
     *
     * @var \CValidation_Validator
     */
    protected $validator;

    /**
     * Create a new Closure based validation rule.
     *
     * @param \Closure $callback
     *
     * @return void
     */
    public function __construct($callback) {
        $this->callback = $callback;
    }

    /**
     * Determine if the validation rule passes.
     *
     * The closure receives `($attribute, $value, $fail, $validator)`; `$fail($message)`
     * returns a potentially translated string, so `$fail('validation.key')->translate()` works.
     *
     * @param string $attribute
     * @param mixed  $value
     *
     * @return bool
     */
    public function passes($attribute, $value) {
        $this->failed = false;
        $this->messages = [];

        $this->callback->__invoke($attribute, $value, function ($attribute, $message = null) {
            $this->failed = true;

            return $this->pendingPotentiallyTranslatedString($attribute, $message);
        }, $this->validator);

        // pesan sudah tertulis di sini: string tertunda dilepas begitu pernyataan $fail() selesai
        $this->message = count($this->messages) > 0 ? end($this->messages) : null;

        return !$this->failed;
    }

    /**
     * Get the validation error messages.
     *
     * @return array
     */
    public function message() {
        return $this->messages;
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
