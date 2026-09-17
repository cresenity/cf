<?php

/**
 * @deprecated since 1.2
 */
//@codingStandardsIgnoreStart
class CFormInputTime {
    public $field_id = '';

    public $field_name = '';

    public $field_value = '';

    public $value_pick = true;

    public $step = 30;

    public $addition_attribute = '';

    public $validation = null; //CInputValidation type

    public function __construct($id) {
        $this->field_id = $id;
        $this->field_name = $id;
        CManager::instance()->register_module('timepicker');
    }

    public static function factory($id) {
        return new CFormInputTime($id);
    }

    public function get_field_id() {
        return $this->field_id;
    }

    public function set_label($text) {
        $this->field_label = $text;
        return $this;
    }

    public function set_value($val) {
        $this->field_value = $val;
        return $this;
    }

    public function set_name($val) {
        $this->field_name = $val;
        return $this;
    }

    public function set_value_pick($bool) {
        $this->field_value_pick = $bool;
        return $this;
    }

    public function set_step($step) {
        $this->step = $step;
        return $this;
    }

    public function set_validation($validation) {
        $this->validation = $validation;
        return $this;
    }

    public function set_addition_attribute($attr) {
        $this->addition_attribute = $attr;
        return $this;
    }

    public function html($indent = 0) {
        $html = new CStringBuilder();
        $html->set_indent($indent);

        $val = explode(' ', $this->field_value);
        if (count($val) > 1) {
            $val = $val[1];
        } else {
            $val = $val[0];
        }
        $time_list = [];
        //in minutes
        $step = $this->step;
        $total_time = 60 * 24;
        $is_value_exists = false;
        $val_arr = explode(':', $val);
        $val_in_minute = false;
        if (count($val_arr) == 3) {
            $val_in_minute = $val_arr[0] * 60 + $val_arr[1] + $val_arr[2] / 60;
        }
        for ($i = 0; $i < $total_time; $i += $step) {
            $hour = floor($i / 60);
            $minute = ($i % 60);
            $hour = $hour < 10 ? '0' . $hour : $hour;
            $minute = $minute < 10 ? '0' . $minute : $minute;
            $time = $hour . ':' . $minute . ':' . '00';
            $time_list[$time] = $time;
            if ($this->value_pick) {
                $next_val = $i + $step;
                if ($val_in_minute != false && $val_in_minute > $i && $val_in_minute < $next_val) {
                    $time_list[$val] = $val;
                }
            }
        }

        $field_name = $this->field_name;
        $field_id = $this->field_id;

        $html->appendln('<select name="' . $field_name . '" id="' . $field_id . '" class="select ' . $this->addition_attribute . '">')->inc_indent()->br();
        if ($time_list != null) {
            foreach ($time_list as $k => $v) {
                $selected = '';
                if ($val == $k) {
                    $selected = ' selected="selected"';
                }
                $html->appendln('<option value="' . $k . '"' . $selected . '>' . $v . '</option>')->br();
            }
        }
        $html->dec_indent()->appendln('</select>')->br();
        return $html->text();
    }
}
