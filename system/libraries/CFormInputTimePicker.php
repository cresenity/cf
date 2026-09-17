<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @deprecated since 1.2
 */
//@codingStandardsIgnoreStart
class CFormInputTimePicker extends CFormInput {
    use CTrait_Compat_Element_FormInput_Time,
        CTrait_Element_Property_Placeholder;
    protected $show_second;

    protected $template;

    protected $show_meridian;

    protected $minute_step;

    public function __construct($id) {
        parent::__construct($id);

        $this->type = 'timepicker';
        $this->show_second = false;
        $this->template = 'dropdown';
        $this->show_meridian = false;
        $this->minute_step = 1;

        $this->placeholder = '';
        CManager::instance()->register_module('timepicker');
    }

    public static function factory($id) {
        return new CFormInputTimePicker($id);
    }

    public function set_show_meridian($bool) {
        $this->show_meridian = $bool;

        return $this;
    }

    public function set_show_second($bool) {
        $this->show_second = $bool;

        return $this;
    }

    public function set_minute_step($step) {
        $this->minute_step = $step;

        return $this;
    }

    public function html($indent = 0) {
        $html = new CStringBuilder();
        $html->set_indent($indent);
        $disabled = '';
        if ($this->disabled) {
            $disabled = ' disabled="disabled"';
        }

        $addition_attribute = '';
        foreach ($this->attr as $k => $v) {
            $addition_attribute .= ' ' . $k . '="' . $v . '"';
        }

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
        $placeholder = '';
        if (strlen($this->placeholder) > 0) {
            $placeholder = ' placeholder="' . $this->placeholder . '"';
        }

        $html->appendln('<div class="bootstrap-timepicker">');
        $html->appendln('<input type="text" name="' . $this->name . '" id="' . $this->id . '" class="input-unstyled ' . $classes . $this->validation->validation_class() . '" value="' . $this->value . '"' . $disabled . $custom_css . $addition_attribute . $placeholder . '>')->br();
        $html->appendln('</div>');

        return $html->text();
    }

    public function js($indent = 0) {
        $js = new CStringBuilder();
        $js->set_indent($indent);

        $js->appendln("$('#" . $this->id . "').timepicker({");
        if (strlen($this->value) > 0) {
            $js->appendln("	defaultTime: '" . $this->value . "',");
        } else {
            $js->appendln("	defaultTime: 'false',");
        }
        if (strlen($this->minute_step) > 0) {
            $js->appendln('	minuteStep: ' . $this->minute_step . ',');
        } else {
            $js->appendln('	minuteStep: 1,');
        }
        if (strlen($this->template) > 0) {
            $js->appendln("	template: '" . $this->template . "',");
        } else {
            $js->appendln("	template: 'dropdown',");
        }
        if ($this->show_second) {
            $js->appendln('	showSeconds: true,');
        } else {
            $js->appendln('	showSeconds: false,');
        }
        if ($this->show_meridian) {
            $js->appendln('	showMeridian: true,');
        } else {
            $js->appendln('	showMeridian: false,');
        }

        $js->appendln("	template: 'dropdown',");
        $js->appendln('	disableFocus: true');
        $js->appendln('});');

        return $js->text();
    }
}
