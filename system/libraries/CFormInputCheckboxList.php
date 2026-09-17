<?php

/**
 * @deprecated 1.2
 */
//@codingStandardsIgnoreStart
class CFormInputCheckboxList extends CFormInput {
    protected $group_list = [];

    protected $applyjs;

    public function __construct($id) {
        parent::__construct($id);

        $this->applyjs = '';
    }

    public static function factory($id) {
        return new CFormInputCheckboxList($id);
    }

    public function set_applyjs($applyjs) {
        $this->applyjs = $applyjs;

        return $this;
    }

    public function set_lookup($query) {
    }

    public function add_group_list($group, $list) {
        $this->group_list[$group] = $list;

        return $this;
    }

    public function html($indent = 0) {
        $html = new CStringBuilder();
        $html->setIndent($indent);
        $disabled = '';
        if ($this->disabled) {
            $disabled = ' disabled="disabled"';
        }

        $name = $this->name;
        $name = $name . '[]';
        $classes = $this->classes;
        $classes = implode(' ', $classes);
        if (strlen($classes) > 0) {
            $classes = ' ' . $classes;
        }

        $custom_css = $this->custom_css;
        $custom_css = $this->renderStyle($custom_css);
        if (strlen($custom_css) > 0) {
            $custom_css = ' style="' . $custom_css . '"';
        }

        if (count($this->group_list) > 0) {
            foreach ($this->group_list as $g => $list) {
                if (strlen($g) > 0) {
                    $html->appendln('<optgroup label="' . $g . '">')->br();
                }
                foreach ($list as $k => $v) {
                    $selected = '';
                    if (is_array($this->value)) {
                        if (in_array($k, $this->value)) {
                            $selected = ' selected="selected"';
                        }
                    } else {
                        if ($this->value == (string) $k) {
                            $selected = ' selected="selected"';
                        }
                    }
                    $html->appendln('<option value="' . $k . '"' . $selected . '>' . $v . '</option>')->br();
                }
                if (strlen($g) > 0) {
                    $html->appendln('</optgroup>')->br();
                }
            }
        }

        if ($this->list != null) {
            $i = 0;
            foreach ($this->list as $k => $v) {
                $i++;
                $checked = '';
                if (is_array($this->value)) {
                    if (in_array($k, $this->value)) {
                        $checked = ' checked="checked"';
                    }
                } else {
                    if ($this->value == (string) $k) {
                        $checked = ' checked="checked"';
                    }
                }

                $html->appendln('<div class="checkbox"><label><input type="checkbox" name="' . $name . '" id="' . $this->id . '_' . $i . '" class="input-unstyled checkbox' . $classes . '" value="' . $k . '"' . $checked . '> ' . $v . '</label></div>')->br();
                //$html->appendln('<option value="'.$k.'"'.$checked.'>'.$v.'</option>')->br();
            }
        }
        //$html->dec_indent()->appendln('</select>')->br();
        //$html->appendln('<input type="text" name="'.$this->name.'" id="'.$this->id.'" class="input-unstyled'.$this->validation->validation_class().'" value="'.$this->value.'"'.$disabled.'>')->br();
        return $html->text();
    }

    public function js($indent = 0) {
        $js = new CStringBuilder();
        $js->setIndent($indent);
        $js->append(parent::js($indent))->br();
        if ($this->applyjs == 'select2') {
            $js->append("$('#" . $this->id . "').select2();")->br();
        }
        if ($this->applyjs == 'chosen') {
            $js->append("$('#" . $this->id . "').chosen();")->br();
        }
        if ($this->applyjs == 'dualselect') {
            $js->append("$('#" . $this->id . "').multiSelect();")->br();
        }

        return $js->text();
    }
}
