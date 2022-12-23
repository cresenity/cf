<?php

class CExporter_Validator_RowValidator {
    private static $instance;

    public static function instance() {
        if (static::$instance == null) {
            static::$instance = new CExporter_Validator_RowValidator();
        }

        return static::$instance;
    }

    private function __construct() {
        $this->validator = CValidation::factory();
    }

    /**
     * @param array                            $rows
     * @param CExporter_Concern_WithValidation $import
     *
     * @throws CValidation_Exception
     * @throws CExporter_Exception_RowSkippedException
     */
    public function validate(array $rows, CExporter_Concern_WithValidation $import) {
        $rules = $this->rules($import);
        $messages = $this->messages($import);
        $attributes = $this->attributes($import);

        try {
            $validator = $this->validator->make($rows, $rules, $messages, $attributes);
            if (method_exists($import, 'withValidator')) {
                $import->withValidator($validator);
            }

            $validator->validate();
        } catch (CValidation_Exception $e) {
            $failures = [];
            foreach ($e->errors() as $attribute => $messages) {
                $row = strtok($attribute, '.');
                $attributeName = strtok('');
                $attributeName = isset($attributes['*.' . $attributeName]) ? $attributes['*.' . $attributeName] : $attributeName;

                $failures[] = new CExporter_Validator_Failure(
                    $row,
                    $attributeName,
                    str_replace($attribute, $attributeName, $messages),
                    carr::get($rows, $row, [])
                );
            }

            if ($import instanceof CExporter_Concern_SkipsOnFailure) {
                $import->onFailure(...$failures);

                throw new CExporter_Exception_RowSkippedException(...$failures);
            }

            throw new CExporter_Validator_ValidationException(
                $e,
                $failures
            );
        }
    }

    /**
     * @param CExporter_Concern_WithValidation $import
     *
     * @return array
     */
    private function messages(CExporter_Concern_WithValidation $import) {
        return method_exists($import, 'customValidationMessages')
            ? $this->formatKey($import->customValidationMessages())
            : [];
    }

    /**
     * @param CExporter_Concern_WithValidation $import
     *
     * @return array
     */
    private function attributes(CExporter_Concern_WithValidation $import) {
        return method_exists($import, 'customValidationAttributes')
            ? $this->formatKey($import->customValidationAttributes())
            : [];
    }

    /**
     * @param CExporter_Concern_WithValidation $import
     *
     * @return array
     */
    private function rules(CExporter_Concern_WithValidation $import) {
        return $this->formatKey($import->rules());
    }

    /**
     * @param array $elements
     *
     * @return array
     */
    private function formatKey(array $elements) {
        return c::collect($elements)->mapWithKeys(function ($rule, $attribute) {
            $attribute = cstr::startsWith($attribute, '*.') ? $attribute : '*.' . $attribute;

            return [$attribute => $this->formatRule($rule)];
        })->all();
    }

    /**
     * @param string|object|callable|array $rules
     *
     * @return string|array
     */
    private function formatRule($rules) {
        if (is_array($rules)) {
            foreach ($rules as $rule) {
                $formatted[] = $this->formatRule($rule);
            }

            return isset($formatted) ? $formatted : [];
        }

        if (is_object($rules) || is_callable($rules)) {
            return $rules;
        }

        if (cstr::contains($rules, 'required_') && preg_match('/(.*):(.*),(.*)/', $rules, $matches)) {
            $column = cstr::startsWith($matches[2], '*.') ? $matches[2] : '*.' . $matches[2];

            return $matches[1] . ':' . $column . ',' . $matches[3];
        }

        return $rules;
    }
}
