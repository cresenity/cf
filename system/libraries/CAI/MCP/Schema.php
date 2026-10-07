<?php

/**
 * Validasi argumen tool terhadap subset JSON Schema: type, required, properties, enum, items, minimum/maximum,
 * minLength/maxLength. Kata kunci lain diabaikan.
 */
class CAI_MCP_Schema {
    /**
     * @param array $schema
     * @param mixed $data
     * @param string $path
     *
     * @return array daftar pesan galat; kosong bila valid
     */
    public static function validate(array $schema, $data, $path = '') {
        $errors = [];
        $label = $path === '' ? 'arguments' : $path;

        if (isset($schema['type']) && !static::typeMatches((array) $schema['type'], $data)) {
            return [$label . ' harus bertipe ' . implode('|', (array) $schema['type'])];
        }
        if (isset($schema['enum']) && is_array($schema['enum']) && !in_array($data, $schema['enum'], true)) {
            $errors[] = $label . ' harus salah satu dari: ' . implode(', ', array_map('strval', $schema['enum']));
        }
        if (is_string($data)) {
            if (isset($schema['minLength']) && static::length($data) < (int) $schema['minLength']) {
                $errors[] = $label . ' minimal ' . (int) $schema['minLength'] . ' karakter';
            }
            if (isset($schema['maxLength']) && static::length($data) > (int) $schema['maxLength']) {
                $errors[] = $label . ' maksimal ' . (int) $schema['maxLength'] . ' karakter';
            }
        }
        if ((is_int($data) || is_float($data)) && !is_bool($data)) {
            if (isset($schema['minimum']) && $data < $schema['minimum']) {
                $errors[] = $label . ' minimal ' . $schema['minimum'];
            }
            if (isset($schema['maximum']) && $data > $schema['maximum']) {
                $errors[] = $label . ' maksimal ' . $schema['maximum'];
            }
        }
        if (is_array($data) && static::isList($data) && isset($schema['items']) && is_array($schema['items'])) {
            foreach ($data as $index => $item) {
                $errors = array_merge($errors, static::validate($schema['items'], $item, $label . '[' . $index . ']'));
            }
        }
        if (is_array($data) && !static::isList($data) || $data === []) {
            foreach ((array) (isset($schema['required']) ? $schema['required'] : []) as $key) {
                if (!is_array($data) || !array_key_exists($key, $data)) {
                    $errors[] = ($path === '' ? '' : $path . '.') . $key . ' wajib diisi';
                }
            }
            foreach ((array) (isset($schema['properties']) ? $schema['properties'] : []) as $key => $propertySchema) {
                if (is_array($data) && array_key_exists($key, $data) && is_array($propertySchema)) {
                    $errors = array_merge($errors, static::validate($propertySchema, $data[$key], ($path === '' ? '' : $path . '.') . $key));
                }
            }
        }

        return $errors;
    }

    /**
     * @param array $types
     * @param mixed $data
     *
     * @return bool
     */
    protected static function typeMatches(array $types, $data) {
        foreach ($types as $type) {
            switch ($type) {
                case 'string':
                    $ok = is_string($data);

                    break;
                case 'integer':
                    $ok = is_int($data) || (is_float($data) && floor($data) === $data);

                    break;
                case 'number':
                    $ok = is_int($data) || is_float($data);

                    break;
                case 'boolean':
                    $ok = is_bool($data);

                    break;
                case 'null':
                    $ok = $data === null;

                    break;
                case 'array':
                    $ok = is_array($data) && static::isList($data);

                    break;
                case 'object':
                    $ok = is_array($data) && ($data === [] || !static::isList($data));

                    break;
                default:
                    $ok = true;
            }
            if ($ok) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array $data
     *
     * @return bool
     */
    protected static function isList(array $data) {
        return $data === [] || array_keys($data) === range(0, count($data) - 1);
    }

    /**
     * @param string $text
     *
     * @return int
     */
    protected static function length($text) {
        return function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    }
}
