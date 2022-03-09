<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Apr 14, 2019, 1:29:10 PM
 */
trait CJavascript_Validation_Trait_RuleListTrait {
    /**
     *  Rules validated with Javascript.
     *
     * @var array
     */
    protected $clientRules = [
        'Accepted', 'After', 'AfterOrEqual', 'Alpha', 'AlphaDash',
        'AlphaNum', 'Array', 'Bail', 'Before', 'BeforeOrEqual', 'Between', 'Boolean', 'Confirmed', 'Date', 'Dimensions',
        'DateFormat', 'Different', 'Digits', 'DigitsBetween', 'Distinct', 'Email', 'File', 'Filled', 'Image',
        'In', 'InArray', 'Integer', 'Ip', 'Json', 'Max', 'Mimes', 'Mimetypes', 'Min', 'NotIn', 'Nullable',
        'Numeric', 'Regex', 'Required', 'RequiredIf', 'RequiredUnless', 'RequiredWith', 'RequiredWithAll',
        'RequiredWithout', 'RequiredWithoutAll', 'Same', 'Size', 'Sometimes',
        'String', 'Timezone', 'CAppNoop',
    ];

    /**
     * Rules validated in Server-Side.
     *
     * @var array
     */
    protected $serverRules = ['ActiveUrl', 'Exists', 'Unique', 'Url'];

    /**
     * Rules applyed to files.
     *
     * @var array
     */
    protected $fileRules = ['File', 'Image', 'Mimes', 'Mimetypes'];

    /**
     * Rule used to disable validations.
     *
     * @var string
     */
    private $disableJsValidationRule = 'NoJsValidation';

    /**
     * Returns if rule is validated using Javascript.
     *
     * @param $rule
     *
     * @return bool
     */
    protected function isImplemented($rule) {
        return in_array($rule, $this->clientRules) || in_array($rule, $this->serverRules);
    }

    /**
     * Check if rule must be validated in server-side.
     *
     * @param $rule
     *
     * @return bool
     */
    protected function isRemoteRule($rule) {
        return in_array($rule, $this->serverRules)
                || !in_array($rule, $this->clientRules);
    }

    /**
     * Form request rule.
     *
     * @param string $rule
     *
     * @return bool
     */
    protected function isFormRequestRule($rule) {
        return $rule === CJavascript_Validation_RuleParser::FORM_REQUEST_RULE_NAME;
    }

    /**
     * Check if rule disables rule processing.
     *
     * @param $rule
     *
     * @return bool
     */
    protected function isDisableRule($rule) {
        return $rule === $this->disableJsValidationRule;
    }

    /**
     * Check if rules should be validated.
     *
     * @param $rules
     *
     * @return bool
     */
    protected function validationDisabled($rules) {
        $rules = (array) $rules;

        return in_array($this->disableJsValidationRule, $rules);
    }

    /**
     * Check if rules is for input file type.
     *
     * @param $rule
     *
     * @return bool
     */
    protected function isFileRule($rule) {
        return in_array($rule, $this->fileRules);
    }
}
