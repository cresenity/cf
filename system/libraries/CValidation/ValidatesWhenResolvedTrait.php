<?php

/**
 * Provides default implementation of ValidatesWhenResolved contract.
 */
trait CValidation_ValidatesWhenResolvedTrait {
    /**
     * Validate the class instance.
     *
     * @return void
     */
    public function validateResolved() {
        $this->prepareForValidation();

        if (!$this->passesAuthorization()) {
            $this->failedAuthorization();
        }

        $instance = $this->getValidatorInstance();

        if (!$instance->passes()) {
            $this->failedValidation($instance);
        }

        $this->passedValidation();
    }

    /**
     * Handle a passed validation attempt.
     *
     * @return void
     */
    protected function passedValidation() {
        //
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation() {
        // no default action
    }

    /**
     * Get the validator instance for the request.
     *
     * @return CValidation_Validator
     */
    protected function getValidatorInstance() {
        return $this->validator();
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param CValidation_Validator $validator
     *
     * @return void
     *
     * @throws \CValidation_Exception
     */
    protected function failedValidation(CValidation_Validator $validator) {
        throw new CValidation_Exception($validator);
    }

    /**
     * Determine if the request passes the authorization check.
     *
     * @return bool
     */
    protected function passesAuthorization() {
        if (method_exists($this, 'authorize')) {
            return $this->authorize();
        }

        return true;
    }

    /**
     * Handle a failed authorization attempt.
     *
     * @return void
     *
     * @throws \CValidation_UnauthorizedException
     */
    protected function failedAuthorization() {
        throw new CValidation_UnauthorizedException;
    }
}
