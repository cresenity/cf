<?php

/**
 * @deprecated since 1.2
 */
//@codingStandardsIgnoreStart
class CFormInputSelectTag extends CFormInput {
    public function __construct($id) {
        parent::__construct($id);
    }

    public static function factory($id) {
        return new CFormInputSelectTag($id);
    }

    public function set_multiple($bool) {
        $this->multiple = $bool;

        return $this;
    }

    public function set_min_input_length($min_input_length) {
        $this->min_input_length = $min_input_length;

        return $this;
    }

    public function set_key_field($key_field) {
        $this->key_field = $key_field;

        return $this;
    }

    public function set_search_field($search_field) {
        $this->search_field = $search_field;

        return $this;
    }

    public function set_query($query) {
        $this->query = $query;

        return $this;
    }

    public function set_format_result($fmt) {
        $this->format_result = $fmt;

        return $this;
    }

    public function set_format_selection($fmt) {
        $this->format_selection = $fmt;

        return $this;
    }

    public function set_placeholder($placeholder) {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function html($indent = 0) {
        $html = new CStringBuilder();
        $html->set_indent($indent);
        $html->append($this->html_child($indent));

        $custom_css = $this->renderStyle($this->custom_css);
        $multiple = ' multiple="multiple"';
        if (strlen($custom_css) > 0) {
            $custom_css = ' style="' . $custom_css . '"';
        }

        $classes = implode(' ', $this->classes);
        if (strlen($classes) > 0) {
            $classes = ' ' . $classes;
        }

        $html->appendln('<input type="hidden"  class="' . $classes . '" name="' . $this->name . '" id="' . $this->id . '" ' . $custom_css . '/>')->br();

        return $html->text();
    }

    public function js($indent = 0) {
        $vals = $this->value;
        if (!is_array($vals)) {
            $vals = [$vals];
        }
        $vals_str = '';
        foreach ($vals as $val) {
            if (strlen($vals_str) > 0) {
                $vals_str .= ',';
            }
            $vals_str .= "'" . $val . "'";
        }

        $list = $this->list;
        if (!is_array($list)) {
            $list = [$list];
        }
        $list_str = '';
        foreach ($list as $val) {
            if (strlen($list_str) > 0) {
                $list_str .= ',';
            }
            $list_str .= "'" . $val . "'";
        }

        $str = "
			$('#" . $this->id . "').select2({
				tags: [" . $list_str . "],
				tokenSeparators: [',', ' ']
			}).change(function() {

			});

	";

        if (strlen($vals_str) > 0) {
            $str .= "
                        $('#" . $this->id . "').select2('val',[" . $vals_str . ']);
                ';
        }

        $js = new CStringBuilder();
        $js->append(parent::js($indent))->br();
        $js->set_indent($indent);
        //echo $str;
        $js->append($str)->br();

        return $js->text();
    }
}
