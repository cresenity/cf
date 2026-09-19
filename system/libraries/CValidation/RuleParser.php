<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Apr 12, 2019, 8:02:12 PM
 */
class CValidation_RuleParser {
    /**
     * The data being validated.
     *
     * @var array
     */
    public $data;

    /**
     * The implicit attributes.
     *
     * @var array
     */
    public $implicitAttributes = [];

    /**
     * Create a new validation rule parser.
     *
     * @param array $data
     *
     * @return void
     */
    public function __construct(array $data) {
        $this->data = $data;
    }

    /**
     * Parse the human-friendly rules into a full rules array for the validator.
     *
     * @param array $rules
     *
     * @return \stdClass
     */
    public function explode($rules) {
        $this->implicitAttributes = [];

        $rules = $this->explodeRules($rules);

        return (object) [
            'rules' => $rules,
            'implicitAttributes' => $this->implicitAttributes,
        ];
    }

    /**
     * Explode the rules into an array of explicit rules.
     *
     * @param array $rules
     *
     * @return array
     */
    protected function explodeRules($rules) {
        foreach ($rules as $key => $rule) {
            if (cstr::contains($key, '*')) {
                $rules = $this->explodeWildcardRules($rules, $key, [$rule]);

                unset($rules[$key]);
            } else {
                $rules[$key] = $this->explodeExplicitRule($rule, $key);
            }
        }

        return $rules;
    }

    /**
     * Explode the explicit rule into an array if necessary.
     *
     * @param mixed $rule
     * @param mixed $attribute
     *
     * @return array
     */
    protected function explodeExplicitRule($rule, $attribute) {
        if (is_string($rule)) {
            return explode('|', $rule);
        }

        if (is_object($rule)) {
            if ($this->isRuleStringBuilder($rule)) {
                return explode('|', (string) $rule);
            }

            return carr::wrap($this->prepareRule($rule, $attribute));
        }

        $rules = [];

        foreach ($rule as $value) {
            if ($this->isRuleStringBuilder($value)) {
                $rules = array_merge($rules, explode('|', (string) $value));
            } else {
                $rules[] = $this->prepareRule($value, $attribute);
            }
        }

        return $rules;
    }

    /**
     * Builders that render to a `|`-separated rule string rather than acting as a rule object.
     *
     * @param mixed $rule
     *
     * @return bool
     */
    protected function isRuleStringBuilder($rule) {
        return $rule instanceof CValidation_Rule_Date
            || $rule instanceof CValidation_Rule_Numeric
            || $rule instanceof CValidation_Rule_StringRule;
    }

    /**
     * Prepare the given rule for the Validator.
     *
     * @param mixed $rule
     * @param mixed $attribute
     *
     * @return mixed
     */
    protected function prepareRule($rule, $attribute) {
        if ($rule instanceof Closure) {
            $rule = new CValidation_ClosureValidationRule($rule);
        }
        if ($rule instanceof CValidation_Contract_InvokableRuleInterface || $rule instanceof CValidation_Contract_ValidationRuleInterface) {
            $rule = CValidation_InvokableValidationRule::make($rule);
        }
        if (!is_object($rule)
            || $rule instanceof CValidation_RuleInterface
            || ($rule instanceof CValidation_Rule_Exists && $rule->queryCallbacks())
            || ($rule instanceof CValidation_Rule_Unique && $rule->queryCallbacks())
        ) {
            return $rule;
        }

        if ($rule instanceof CValidation_NestedRules) {
            return $rule->compile(
                $attribute,
                $this->data[$attribute] ?? null,
                carr::dot($this->data)
            )->rules[$attribute];
        }

        return (string) $rule;
    }

    /**
     * Define a set of rules that apply to each element in an array attribute.
     *
     * @param array        $results
     * @param string       $attribute
     * @param string|array $rules
     *
     * @return array
     */
    protected function explodeWildcardRules($results, $attribute, $rules) {
        $pattern = str_replace('\*', '[^\.]*', preg_quote($attribute));

        $data = CValidation_Data::initializeAndGatherData($attribute, $this->data);

        foreach ($data as $key => $value) {
            if (cstr::startsWith($key, $attribute) || (bool) preg_match('/^' . $pattern . '\z/', $key)) {
                foreach ((array) $rules as $rule) {
                    if ($rule instanceof CValidation_NestedRules) {
                        $compiled = $rule->compile($key, $value, $data);

                        $this->implicitAttributes = array_merge_recursive(
                            $compiled->implicitAttributes,
                            $this->implicitAttributes,
                            [$attribute => [$key]]
                        );

                        $results = $this->mergeRules($results, $compiled->rules);
                    } else {
                        $this->implicitAttributes[$attribute][] = $key;

                        $results = $this->mergeRules($results, $key, $rule);
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Merge additional rules into a given attribute(s).
     *
     * @param array        $results
     * @param string|array $attribute
     * @param string|array $rules
     *
     * @return array
     */
    public function mergeRules($results, $attribute, $rules = []) {
        if (is_array($attribute)) {
            foreach ((array) $attribute as $innerAttribute => $innerRules) {
                $results = $this->mergeRulesForAttribute($results, $innerAttribute, $innerRules);
            }

            return $results;
        }

        return $this->mergeRulesForAttribute(
            $results,
            $attribute,
            $rules
        );
    }

    /**
     * Merge additional rules into a given attribute.
     *
     * @param array        $results
     * @param string       $attribute
     * @param string|array $rules
     *
     * @return array
     */
    protected function mergeRulesForAttribute($results, $attribute, $rules) {
        $merge = c::head($this->explodeRules([$rules]));

        $results[$attribute] = array_merge(
            isset($results[$attribute]) ? $this->explodeExplicitRule($results[$attribute], $attribute) : [],
            $merge
        );

        return $results;
    }

    /**
     * Extract the rule name and parameters from a rule.
     *
     * @param array|string $rules
     *
     * @return array
     */
    public static function parse($rules) {
        if ($rules instanceof CValidation_RuleInterface || $rules instanceof CValidation_NestedRules) {
            return [$rules, []];
        }

        if (is_array($rules)) {
            $rules = static::parseArrayRule($rules);
        } else {
            $rules = static::parseStringRule($rules);
        }

        $rules[0] = static::normalizeRule($rules[0]);

        return $rules;
    }

    /**
     * Parse an array based rule.
     *
     * @param array $rules
     *
     * @return array
     */
    protected static function parseArrayRule(array $rule) {
        return [cstr::studly(trim(carr::get($rule, 0, ''))), array_slice($rule, 1)];
    }

    /**
     * Parse a string based rule.
     *
     * @param string $rules
     *
     * @return array
     */
    protected static function parseStringRule($rules) {
        $parameters = [];

        // The format for specifying validation rules and parameters follows an
        // easy {rule}:{parameters} formatting convention. For instance the
        // rule "Max:3" states that the value may only be three letters.
        if (strpos($rules, ':') !== false) {
            list($rules, $parameter) = explode(':', $rules, 2);

            $parameters = static::parseParameters($rules, $parameter);
        }

        return [cstr::studly(trim($rules)), $parameters];
    }

    /**
     * Parse a parameter list.
     *
     * @param string $rule
     * @param string $parameter
     *
     * @return array
     */
    protected static function parseParameters($rule, $parameter) {
        return static::ruleIsRegex($rule) ? [$parameter] : str_getcsv($parameter);
    }

    /**
     * Determine if the rule is a regular expression.
     *
     * @param string $rule
     *
     * @return bool
     */
    protected static function ruleIsRegex($rule) {
        return in_array(strtolower($rule), ['regex', 'not_regex', 'notregex'], true);
    }

    /**
     * Normalizes a rule so that we can accept short types.
     *
     * @param string $rule
     *
     * @return string
     */
    protected static function normalizeRule($rule) {
        switch ($rule) {
            case 'Int':
                return 'Integer';
            case 'Bool':
                return 'Boolean';
            default:
                return $rule;
        }
    }

    /**
     * Expand and conditional rules in the given array of rules.
     *
     * @param array $rules
     * @param array $data
     *
     * @return array
     */
    public static function filterConditionalRules($rules, array $data = []) {
        return c::collect($rules)->mapWithKeys(function ($attributeRules, $attribute) use ($data) {
            if (!is_array($attributeRules)
                && !$attributeRules instanceof CValidation_ConditionalRules
            ) {
                return [$attribute => $attributeRules];
            }

            if ($attributeRules instanceof CValidation_ConditionalRules) {
                return [$attribute => $attributeRules->passes($data)
                                ? array_filter($attributeRules->rules($data))
                                : array_filter($attributeRules->defaultRules($data)), ];
            }

            return [$attribute => c::collect($attributeRules)->map(function ($rule) use ($data) {
                if (!$rule instanceof CValidation_ConditionalRules) {
                    return [$rule];
                }

                return $rule->passes($data) ? $rule->rules($data) : $rule->defaultRules($data);
            })->filter()->flatten(1)->values()->all()];
        })->all();
    }
}
