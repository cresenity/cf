<?php

defined('SYSPATH') OR die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @since Jun 30, 2019, 4:29:58 PM
 * @license Ittron Global Teknologi <ittron.co.id>
 */
class CValidation_Factory implements CValidation_FactoryInterface {

    /**
     *
     * @var CValidation_Factory 
     */
    private static $instance;

    /**
     * The Translator implementation.
     *
     * @var CTranslation_Translator
     */
    protected $translator;

    /**
     * The Presence Verifier implementation.
     *
     * @var CValidation_PresenceVerifierInterface
     */
    protected $verifier;

    /**
     * The IoC container instance.
     *
     * @var CContainer
     */
    protected $container;

    /**
     * All of the custom validator extensions.
     *
     * @var array
     */
    protected $extensions = [];

    /**
     * All of the custom implicit validator extensions.
     *
     * @var array
     */
    protected $implicitExtensions = [];

    /**
     * All of the custom dependent validator extensions.
     *
     * @var array
     */
    protected $dependentExtensions = [];

    /**
     * All of the custom validator message replacers.
     *
     * @var array
     */
    protected $replacers = [];

    /**
     * All of the fallback messages for custom rules.
     *
     * @var array
     */
    protected $fallbackMessages = [];

    /**
     * The Validator resolver instance.
     *
     * @var \Closure
     */
    protected $resolver;

    public static function instance() {
        if (static::$instance == null) {
            static::$instance = new CValidation_Factory();
        }
        return static::$instance;
    }

    /**
     * Create a new Validator factory instance.
     *
     * @return void
     */
    private function __construct() {
        $this->container = CContainer::getInstance();
        $this->translator = CTranslation::translator();
    }

    /**
     * Create a new Validator instance.
     *
     * @param  array  $data
     * @param  array  $rules
     * @param  array  $messages
     * @param  array  $customAttributes
     * @return CValidation_Validator
     */
    public function make(array $data, array $rules, array $messages = [], array $customAttributes = []) {
        $validator = $this->resolve(
                $data, $rules, $messages, $customAttributes
        );
        // The presence verifier is responsible for checking the unique and exists data
        // for the validator. It is behind an interface so that multiple versions of
        // it may be written besides database. We'll inject it into the validator.
        if (!is_null($this->verifier)) {
            $validator->setPresenceVerifier($this->verifier);
        }
        // Next we'll set the IoC container instance of the validator, which is used to
        // resolve out class based validator extensions. If it is not set then these
        // types of extensions will not be possible on these validation instances.
        if (!is_null($this->container)) {
            $validator->setContainer($this->container);
        }
        $this->addExtensions($validator);
        return $validator;
    }

    /**
     * Validate the given data against the provided rules.
     *
     * @param  array  $data
     * @param  array  $rules
     * @param  array  $messages
     * @param  array  $customAttributes
     * @return array
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validate(array $data, array $rules, array $messages = [], array $customAttributes = []) {
        return $this->make($data, $rules, $messages, $customAttributes)->validate();
    }

    /**
     * Resolve a new Validator instance.
     *
     * @param  array  $data
     * @param  array  $rules
     * @param  array  $messages
     * @param  array  $customAttributes
     * @return CValidation_Validator
     */
    protected function resolve(array $data, array $rules, array $messages, array $customAttributes) {
        if (is_null($this->resolver)) {
            $validator = new CValidation_Validator($data, $rules, $messages, $customAttributes);
            $validator->setTranslator($this->translator);
            return $validator;
        }
        return call_user_func($this->resolver, $data, $rules, $messages, $customAttributes);
    }

    /**
     * Add the extensions to a validator instance.
     *
     * @param  CValidation_Validator  $validator
     * @return void
     */
    protected function addExtensions(CValidation_Validator $validator) {
        $validator->addExtensions($this->extensions);
        // Next, we will add the implicit extensions, which are similar to the required
        // and accepted rule in that they are run even if the attributes is not in a
        // array of data that is given to a validator instances via instantiation.
        $validator->addImplicitExtensions($this->implicitExtensions);
        $validator->addDependentExtensions($this->dependentExtensions);
        $validator->addReplacers($this->replacers);
        $validator->setFallbackMessages($this->fallbackMessages);
    }

    /**
     * Register a custom validator extension.
     *
     * @param  string  $rule
     * @param  \Closure|string  $extension
     * @param  string|null  $message
     * @return void
     */
    public function extend($rule, $extension, $message = null) {
        $this->extensions[$rule] = $extension;
        if ($message) {
            $this->fallbackMessages[Str::snake($rule)] = $message;
        }
    }

    /**
     * Register a custom implicit validator extension.
     *
     * @param  string  $rule
     * @param  \Closure|string  $extension
     * @param  string|null  $message
     * @return void
     */
    public function extendImplicit($rule, $extension, $message = null) {
        $this->implicitExtensions[$rule] = $extension;
        if ($message) {
            $this->fallbackMessages[cstr::snake($rule)] = $message;
        }
    }

    /**
     * Register a custom dependent validator extension.
     *
     * @param  string  $rule
     * @param  \Closure|string  $extension
     * @param  string|null  $message
     * @return void
     */
    public function extendDependent($rule, $extension, $message = null) {
        $this->dependentExtensions[$rule] = $extension;
        if ($message) {
            $this->fallbackMessages[cstr::snake($rule)] = $message;
        }
    }

    /**
     * Register a custom validator message replacer.
     *
     * @param  string  $rule
     * @param  \Closure|string  $replacer
     * @return void
     */
    public function replacer($rule, $replacer) {
        $this->replacers[$rule] = $replacer;
    }

    /**
     * Set the Validator instance resolver.
     *
     * @param  \Closure  $resolver
     * @return void
     */
    public function resolver(Closure $resolver) {
        $this->resolver = $resolver;
    }

    /**
     * Get the Translator implementation.
     *
     * @return CTranslation_Translator
     */
    public function getTranslator() {
        return $this->translator;
    }

    /**
     * Get the Presence Verifier implementation.
     *
     * @return CValidation_PresenceVerifierInterface
     */
    public function getPresenceVerifier() {
        return $this->verifier;
    }

    /**
     * Set the Presence Verifier implementation.
     *
     * @param  CValidation_PresenceVerifierInterface  $presenceVerifier
     * @return void
     */
    public function setPresenceVerifier(CValidation_PresenceVerifierInterface $presenceVerifier) {
        $this->verifier = $presenceVerifier;
    }

}
