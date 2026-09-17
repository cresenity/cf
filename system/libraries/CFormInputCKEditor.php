<?php
/**
 * @deprecated 1.2
 */
//@codingStandardsIgnoreStart
class CFormInputCKEditor extends CFormInputTextarea {
    protected $toolbar_item = [];

    protected $col;

    protected $row;

    protected $toolbar;

    public function __construct($id) {
        parent::__construct($id);

        $this->type = 'ckeditor';
        $this->toolbar = 'standard';
        $this->col = 60;
        $this->row = 10;
        $this->toolbar_item = [];

        // CManager::instance()->register_module('ckeditor');
        CManager::instance()->registerModule('ckeditor-4');
    }

    public static function factory($id) {
        return new CFormInputCKEditor($id);
    }

    public function toolbar_full() {
        $this->toolbar = 'full';
        return $this;
    }

    public function toolbar_basic() {
        $this->toolbar = 'basic';
        return $this;
    }

    public function toolbar_standard() {
        $this->toolbar = 'standard';
        return $this;
    }

    public function html($indent = 0) {
        $html = new CStringBuilder();
        $html->setIndent($indent);
        $readonly = '';
        if ($this->readonly) {
            $readonly = ' readonly="readonly"';
        }
        $disabled = '';
        if ($this->disabled) {
            $disabled = ' disabled="disabled"';
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
        $disabled = '';
        if ($this->disabled) {
            $disabled = ' disabled="disabled"';
        }
        $html->appendln('<textarea cols="' . $this->col . '" rows="' . $this->row . '" name="' . $this->name . '" id="' . $this->id . '" class="wysiwyg' . $this->validation->validation_class() . $classes . '" ' . $disabled . $readonly . $custom_css . '>' . $this->value . '</textarea>')->br();
        //$html->appendln('<input type="text" name="'.$this->name.'" id="'.$this->id.'" class="input-unstyled'.$this->validation->validation_class().'" value="'.$this->value.'"'.$disabled.'>')->br();
        return $html->text();
    }

    public function js($indent = 0) {
        $js = new CStringBuilder();
        $js->setIndent($indent);
        $js->appendln("
                    CKEDITOR.replace('" . $this->id . "',{
                        extraPlugins: 'markline'
                        });
                ");
        //		$js->append("
        //			CKEDITOR.replace('".$this->name."',{
        //				toolbar: '".$this->toolbar."'
        //			});")->br();

        return $js->text();
    }
}
