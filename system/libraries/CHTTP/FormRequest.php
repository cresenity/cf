<?php

class CHTTP_FormRequest extends CHTTP_Request implements CValidation_ValidatesWhenResolvedInterface {
    use CValidation_ValidatesWhenResolvedTrait;

    /**
     * The container instance.
     *
     * @var CContainer_Container
     */
    protected $container;

    /**
     * The redirector instance.
     *
     * @var CHTTP_Redirector
     */
    protected $redirector;

    /**
     * The URI to redirect to if validation fails.
     *
     * @var string
     */
    protected $redirect;

    /**
     * The route to redirect to if validation fails.
     *
     * @var string
     */
    protected $redirectRoute;

    /**
     * The controller action to redirect to if validation fails.
     *
     * @var string
     */
    protected $redirectAction;

    /**
     * The key to be used for the view error bag.
     *
     * @var string
     */
    protected $errorBag = 'default';

    /**
     * The validator instance.
     *
     * @var null|CValidation_Validator
     */
    protected $validator;

    /**
     * Get the validator instance for the request.
     *
     * @return CValidation_Validator
     */
    protected function getValidatorInstance() {
        if ($this->validator) {
            return $this->validator;
        }

        $factory = CValidation_Factory::instance();

        if (method_exists($this, 'validator')) {
            $validator = $this->container->call([$this, 'validator'], compact('factory'));
        } else {
            $validator = $this->createDefaultValidator($factory);
        }

        if (method_exists($this, 'withValidator')) {
            $this->withValidator($validator);
        }

        $this->validator = $validator;

        return $this->validator;
    }

    /**
     * Create the default validator instance.
     *
     * @param CValidation_Factory $factory
     *
     * @return CValidation_Validator
     */
    protected function createDefaultValidator(CValidation_Factory $factory) {
        return $factory->make(
            $this->validationData(),
            $this->validationRules(),
            $this->messages(),
            $this->attributes()
        );
    }

    /**
     * Get the validation rules, empty when the request defines none.
     *
     * @return array
     */
    protected function validationRules() {
        return method_exists($this, 'rules') ? $this->container->call([$this, 'rules']) : [];
    }

    /**
     * Get data to be validated from the request.
     *
     * @return array
     */
    protected function validationData() {
        return $this->all();
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param CValidation_Validator $validator
     *
     * @throws \CValidation_Exception
     *
     * @return void
     */
    protected function failedValidation(CValidation_Validator $validator) {
        throw (new CValidation_Exception($validator))
            ->errorBag($this->errorBag)
            ->redirectTo($this->getRedirectUrl());
    }

    /**
     * Get the URL to redirect to on a validation error.
     *
     * @return string
     */
    protected function getRedirectUrl() {
        $url = $this->redirector->getUrlGenerator();

        if ($this->redirect) {
            return $url->to($this->redirect);
        } elseif ($this->redirectRoute) {
            return $url->route($this->redirectRoute);
        } elseif ($this->redirectAction) {
            return $url->action($this->redirectAction);
        }

        return $url->previous();
    }

    /**
     * Determine if the request passes the authorization check.
     *
     * @return bool
     */
    protected function passesAuthorization() {
        if (method_exists($this, 'authorize')) {
            return $this->container->call([$this, 'authorize']);
        }

        return false;
    }

    /**
     * Handle a failed authorization attempt.
     *
     * @throws CAuth_Exception_AuthorizationException
     *
     * @return void
     */
    protected function failedAuthorization() {
        throw new CAuth_Exception_AuthorizationException('This action is unauthorized.');
    }

    /**
     * Get the validated data from the request.
     *
     * @throws CValidation_Exception
     *
     * @return array
     */
    public function validated() {
        return $this->getValidatorInstance()->validated();
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages() {
        return [];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes() {
        return [];
    }

    /**
     * Set the Redirector instance.
     *
     * @param CHTTP_Redirector $redirector
     *
     * @return $this
     */
    public function setRedirector(CHTTP_Redirector $redirector) {
        $this->redirector = $redirector;

        return $this;
    }

    /**
     * Set the container implementation.
     *
     * @param CContainer_Container $container
     *
     * @return $this
     */
    public function setContainer(CContainer_Container $container) {
        $this->container = $container;

        return $this;
    }
}
