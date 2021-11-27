<?php

/**
 * Description of CElement_FormInput.
 *
 * @author Hery
 */
class CElement_FormInput extends CElement_Element {
    use CTrait_Compat_Element_FormInput;

    protected $transforms = [];

    protected $name;

    protected $type;

    protected $submit_onchange;

    /**
     * @var string|array
     */
    protected $value;

    protected $size;

    protected $ajax;

    /**
     * @var array
     */
    protected $list;

    protected $validation;

    protected $disabled;

    protected $readonly;

    public function __construct($id = '') {
        parent::__construct($id);

        $this->type = 'text';
        $this->tag = 'input';
        $this->name = $id;

        //sanitize name
        $this->id = str_replace('[', '', $this->id);
        $this->id = str_replace(']', '', $this->id);

        $this->submit_onchange = false;
        $this->ajax = false;
        $this->size = 'medium';
        $this->value = '';
        $this->disabled = '';
        $this->list = [];
        $this->validation = new CElement_Component_Form_FieldValidation();
    }

    public function setSubmitOnChange($bool = true) {
        $this->submit_onchange = $bool;

        return $this;
    }

    public function setAjax($bool = true) {
        $this->ajax = $bool;

        return $this;
    }

    public function setDisabled($bool = true) {
        $this->disabled = $bool;

        return $this;
    }

    public function setSize($size) {
        $this->size = $size;

        return $this;
    }

    public function setReadonly($bool = true) {
        $this->readonly = $bool;

        return $this;
    }

    public function getFieldId() {
        return $this->id;
    }

    public function addTransform($name, $args = []) {
        $func = CDynFunction::factory($name);
        if (!is_array($args)) {
            $args = [$args];
        }
        foreach ($args as $arg) {
            $func->add_param($arg);
        }

        $this->transforms[] = $func;

        return $this;
    }

    public function setValue($val) {
        $this->value = $val;

        return $this;
    }

    public function setList($list) {
        $this->list = $list;

        return $this;
    }

    public function setName($val) {
        $this->name = $val;

        return $this;
    }

    public function addValidation($name, $value = '') {
        if (strlen($value) == 0) {
            $value = $name;
        }
        $this->validation->addValidation($name, $value);

        return $this;
    }

    public function setType($type) {
        $this->type = $type;

        return $this;
    }

    public function setOnText($text) {
        $this->on_text = $text;

        return $this;
    }

    public function setOffText($text) {
        $this->off_text = $text;

        return $this;
    }

    public function setChecked($bool) {
        $this->checked = $bool;

        return $this;
    }

    public function showUpdown() {
        $this->showupdown = true;

        return $this;
    }

    public function hideUpdown() {
        $this->showupdown = false;

        return $this;
    }

    public function toArray() {
        $data = [];
        if ($this->disabled) {
            $data['attr']['disabled'] = 'disabled';
        }
        if ($this->readonly) {
            $data['attr']['readonly'] = 'readonly';
        }
        if (strlen($this->name) > 0) {
            $data['attr']['name'] = $this->name;
        }
        $data = array_merge_recursive($data, parent::toArray());

        return $data;
    }

    protected function build() {
        parent::build();
        $this->setAttr('value', $this->value);
        if ($this->readonly) {
            $this->setAttr('readonly', 'readonly');
        }
        if ($this->disabled) {
            $this->setAttr('disabled', 'disabled');
        }
    }

    public function js($indent = 0) {
        $js = '';
        if ($this->submit_onchange) {
            if ($this->type == 'date') {
                $js .= "
						$('#" . $this->id . "').on('changeDate',function() {
							$(this).closest('form').submit();
						});

					";
            }
            $js .= "
					$('#" . $this->id . "').on('change',function() {
						$(this).closest('form').submit();
					});

				";
        }
        $js .= $this->jsChild($indent);

        return $js;
    }

    protected function htmlAttr() {
        $htmlAttr = parent::htmlAttr();
        $nameAttr = ' name="' . $this->name . '"';

        return $htmlAttr . $nameAttr;
    }
}
