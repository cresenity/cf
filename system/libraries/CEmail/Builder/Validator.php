<?php

class CEmail_Builder_Validator {
    const LEVEL_SKIP = 'skip';

    const LEVEL_SOFT = 'soft';

    const LEVEL_STRICT = 'strict';

    /**
     * Atribut yang selalu diterima di semua komponen.
     *
     * @var array
     */
    protected static $universalAttributes = ['css-class', 'c-class'];

    /**
     * Pesan yang sudah pernah dicatat di proses ini.
     *
     * @var array
     */
    protected static $reported = [];

    protected $options;

    protected $element;

    /**
     * @param CEmail_Builder_Node $element
     * @param array               $options
     */
    public function __construct(CEmail_Builder_Node $element, $options = []) {
        $this->element = $element;
        $this->options = $options;
    }

    public function getOption($key, $defaultValue = null) {
        return carr::get($this->options, $key, $defaultValue);
    }

    /**
     * Daftar pesan untuk atribut di luar daftar atribut komponen.
     *
     * @return string[]
     */
    public function validate() {
        $errors = [];
        $this->walk($this->element, $errors);

        return $errors;
    }

    /**
     * 'skip' tidak memeriksa, 'soft' mencatat peringatan sekali per pesan, 'strict' melempar exception.
     *
     * @throws CEmail_Builder_Exception
     *
     * @return void
     */
    public function enforce() {
        $level = $this->getOption('level', static::LEVEL_SOFT);
        if ($level === static::LEVEL_SKIP) {
            return;
        }
        $errors = $this->validate();
        if (count($errors) == 0) {
            return;
        }
        if ($level === static::LEVEL_STRICT) {
            throw new CEmail_Builder_Exception(implode('; ', $errors));
        }
        foreach ($errors as $error) {
            if (!isset(static::$reported[$error])) {
                static::$reported[$error] = true;
                CLogger::warning('CEmail_Builder: ' . $error);
            }
        }
    }

    /**
     * @param CEmail_Builder_Node $node
     * @param array               $errors
     *
     * @return void
     */
    protected function walk(CEmail_Builder_Node $node, array &$errors) {
        $tagName = $node->getTagName();
        if ($tagName === 'c-attributes') {
            return;
        }
        $components = CEmail::builder()->components();
        $componentClass = carr::get($components, $node->getComponentName());
        if ($tagName !== 'cml' && $componentClass) {
            $known = array_merge(
                array_keys(static::defaultProperty($componentClass, 'allowedAttributes')),
                array_keys(static::defaultProperty($componentClass, 'defaultAttributes')),
                static::$universalAttributes
            );
            foreach (array_keys($node->getAttributes()) as $attribute) {
                if (!in_array($attribute, $known, true)) {
                    $errors[] = 'atribut "' . $attribute . '" tidak dikenal pada <' . $tagName . '> dan diabaikan';
                }
            }
        }
        foreach ($node->getChildren() as $child) {
            $this->walk($child, $errors);
        }
    }

    /**
     * @param string $class
     * @param string $property
     *
     * @return array
     */
    protected static function defaultProperty($class, $property) {
        $defaults = (new ReflectionClass($class))->getDefaultProperties();

        return carr::get($defaults, $property, []);
    }
}
