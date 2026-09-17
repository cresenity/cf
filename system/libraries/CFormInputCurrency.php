<?php
/**
 * @deprecated 1.2
 */
//@codingStandardsIgnoreStart
class CFormInputCurrency extends CFormInput {
    use CTrait_Compat_Element_FormInput_Currency,
        CTrait_Element_Property_Placeholder;
    protected $vk;

    protected $vk_opt;

    public function __construct($id) {
        parent::__construct($id);

        $this->type = 'text';
        $this->vk = false;
        $this->placeholder = '';
        $default_option = [
            'layout' => 'qwerty',
            'restrictInput' => 'true',
            'preventPaste' => 'true',
            'autoAccept' => 'true',
        ];
        $this->value = '0';
        $this->vk_opt = $default_option;
    }

    public static function factory($id) {
        return new CFormInputCurrency($id);
    }

    public function set_vk($bool) {
        $this->vk = $bool;

        return $this;
    }

    public function set_vk_opt($option) {
        $this->vk_opt = array_merge($this->vk_opt, $option);

        return $this;
    }

    public function html($indent = 0) {
        $html = new CStringBuilder();
        $html->set_indent($indent);
        $disabled = '';
        if ($this->disabled) {
            $disabled = ' disabled="disabled"';
        }
        if ($this->readonly) {
            $disabled = ' readonly="readonly"';
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
        $addition_attribute = '';
        foreach ($this->attr as $k => $v) {
            $addition_attribute .= ' ' . $k . '="' . $v . '"';
        }
        $html->appendln('<input type="text" placeholder="' . $this->placeholder . '" name="' . $this->name . '" id="' . $this->id . '" class="input-unstyled' . $classes . $this->validation->validation_class() . '" value="' . $this->value . '"' . $disabled . $addition_attribute . $custom_css . '>')->br();

        return $html->text();
    }

    public function js($indent = 0) {
        $js = new CStringBuilder();
        $js->set_indent($indent);
        $js->append(parent::js());
        if ($this->vk) {
            $js->append("$('#" . $this->id . "').keyboard(" . json_encode($this->vk_opt) . ');')->br();
        }

        $js->append("$('#" . $this->id . "').focus( function() {
				$('#" . $this->id . "').val($.cresenity.unformat_currency($('#" . $this->id . "').val()))
			});")->br();
        $js->append("$('#" . $this->id . "').blur(function() {
				$('#" . $this->id . "').val($.cresenity.format_currency($('#" . $this->id . "').val()))
			});")->br();

        return $js->text();
    }
}
