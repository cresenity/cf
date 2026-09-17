<?php

/**
 * @deprecated 1.2
 */
//@codingStandardsIgnoreStart
class CFormInputDate extends CFormInput {
    use CTrait_Compat_Element_FormInput_Date;
    protected $date_format;

    protected $have_button;

    protected $startDate;

    protected $end_date;

    protected $disable_day;

    protected $inline;

    public function __construct($id) {
        parent::__construct($id);

        CManager::instance()->register_module('datepicker');

        $this->type = 'date';
        $this->date_format = 'yyyy-mm-dd';
        $date_format = ccfg::get('date_formatted');
        if ($date_format != null) {
            $date_format = str_replace('Y', 'yyyy', $date_format);
            $date_format = str_replace('m', 'mm', $date_format);
            $date_format = str_replace('d', 'dd', $date_format);
            $this->date_format = $date_format;
        }

        $this->have_button = false;
        $this->startDate = '';
        $this->end_date = '';
        $this->disable_day = [];
        $this->inline = false;
    }

    public static function factory($id) {
        return new CFormInputDate($id);
    }

    public function set_have_button($boolean) {
        $this->have_button = $boolean;

        return $this;
    }

    public function setStartDate($str) {
        $this->startDate = $str;

        return $this;
    }

    public function set_end_date($str) {
        $this->end_date = $str;

        return $this;
    }

    public function add_disable_day($day) {
        $day_array = explode(',', $day);
        if (count($day_array) > 1) {
            foreach ($day_array as $d) {
                $this->disable_day[] = trim($d);
            }
        } else {
            $this->disable_day[] = $day;
        }

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
        if ($this->have_button) {
            $html->appendln('<div class="input-append date" id="dp3" data-date="' . $this->value . '" data-date-format="' . $this->date_format . '">
                        <input class="input-unstyled ' . $classes . $this->validation->validation_class() . '" size="16" type="text" name="' . $this->name . '"  data-date-format="' . $this->date_format . '" id="' . $this->id . '" value="' . $this->value . '"' . $disabled . $addition_attribute . $custom_css . '>
                        <span class="add-on"><i class="icon-th"></i></span>
                    </div>')->br();
        } else {
            $html->appendln('<input type="text" name="' . $this->name . '"  data-date-format="' . $this->date_format . '" id="' . $this->id . '" class="datepicker input-unstyled' . $classes . $this->validation->validation_class() . '" value="' . $this->value . '"' . $disabled . $addition_attribute . $custom_css . '>')->br();
        }
        //		$html->appendln('<input type="text" name="'.$this->name.'"  data-date-format="'.$this->date_format.'" id="'.$this->id.'" class="datepicker input-unstyled'.$classes.$this->validation->validation_class().'" value="'.$this->value.'"'.$disabled.$custom_css.'>')->br();

        return $html->text();
    }

    public function js($indent = 0) {
        $day_map = [
            'sunday' => '0',
            'monday' => '1',
            'tuesday' => '2',
            'wednesday' => '3',
            'thursday' => '4',
            'friday' => '5',
            'saturday' => '6',
        ];

        foreach ($this->disable_day as $k => $v) {
            if (isset($day_map[strtolower($this->disable_day[$k])])) {
                $this->disable_day[$k] = $day_map[strtolower($this->disable_day[$k])];
            }
        }

        $disable_day_str = implode(',', $this->disable_day);

        $option = '';

        if (strlen($this->startDate) > 0) {
            if (strlen($option) > 0) {
                $option .= ',';
            }
            $option .= "startDate: '" . $this->startDate . "'";
        }
        if (strlen($this->end_date) > 0) {
            if (strlen($option) > 0) {
                $option .= ',';
            }
            $option .= "endDate: '" . $this->end_date . "'";
        }
        if (strlen($disable_day_str)) {
            if (strlen($option) > 0) {
                $option .= ',';
            }
            $option .= "daysOfWeekDisabled: '" . $disable_day_str . "'";
        }
        $autoclose = 'true';
        if (strlen($option) > 0) {
            $option .= ',';
        }
        $option .= 'autoclose: ' . $autoclose . '';

        if (strlen($option) > 0) {
            $option = '{' . $option . '}';
        }
        $js = new CStringBuilder();
        $js->set_indent($indent);
        $js->append(parent::js($indent))->br();

        if ($this->have_button) {
            $js->append("$('#" . $this->id . "').parent().datepicker(" . $option . ');')->br();
        } else {
            $js->append("$('#" . $this->id . "').datepicker(" . $option . ');')->br();
        }

        return $js->text();
    }
}
