<?php

/**
 * @deprecated since 1.2
 */
class CFormValidation {
    use  CTrait_Compat_Element_Form_FieldValidation;

    private $validation = null;

    public function __construct() {
        $this->validation = [];
    }

    public static function factory() {
        return new static();
    }

    public function addValidation($name, $param) {
        $this->validation[$name] = $param;
    }

    public function required() {
        $this->validation['required'] = 'required';

        return $this;
    }

    public function condrequired($input) {
        $this->validation['condrequired'] = $input;

        return $this;
    }

    public function min($n) {
        $this->validation['min'] = $n;

        return $this;
    }

    public function max($n) {
        $this->validation['max'] = $n;

        return $this;
    }

    public function equals($n) {
        $this->validation['equals'] = $n;

        return $this;
    }

    public function notequals($n) {
        $this->validation['notequals'] = $n;

        return $this;
    }

    public function custom($n) {
        $this->validation['custom'] = $n;

        return $this;
    }

    public function validationClass() {
        return ' validate[' . $this->renderClass() . ']';
    }

    protected function renderClass() {
        $validation_class = '';
        foreach ($this->validation as $k => $v) {
            if ($v != false) {
                if (strlen($validation_class) > 0) {
                    $validation_class .= ', ';
                }
                switch (strtolower($k)) {
                    case 'required':
                        $validation_class .= 'required';

                        break;
                    case 'condrequired':
                        $validation_class .= 'condRequired[' . $v . ']';

                        break;
                    case 'min':
                        $validation_class .= 'min[' . $v . ']';

                        break;
                    case 'max':
                        $validation_class .= 'max[' . $v . ']';

                        break;
                    case 'equals':
                        $validation_class .= 'equals[' . $v . ']';

                        break;
                    case 'notequals':
                        $validation_class .= 'notequals[' . $v . ']';

                        break;
                    case 'custom':
                        $validation_class .= 'custom[' . $v . ']';

                        break;
                    default:
                        $validation_class .= '' . $k . '[' . $v . ']';

                        break;
                }
            }
        }

        return $validation_class;
    }
}
