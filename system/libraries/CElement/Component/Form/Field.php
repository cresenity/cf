<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * Renders a form field wrapper: label + control(s) + optional info text, wrapped
 * in a `.control-group.form-group` div, around whatever child control element(s)
 * are added to it.
 */
class CElement_Component_Form_Field extends CElement_Component {
    use CTrait_Compat_Element_Form_Field;
    use CTrait_Element_Property_Label;
    use CTrait_Element_Property_Tooltip;

    /**
     * Extra CSS classes added to the outer group div.
     *
     * @var array
     */
    protected $groupClasses = [];

    /**
     * @var string
     */
    protected $groupId = '';

    /**
     * Custom CSS style declarations for the outer group div, keyed by property name.
     *
     * @var array
     */
    protected $groupCustomCss = [];

    /**
     * @var bool
     */
    protected $showLabel = [];

    /**
     * One of 'small', 'medium', 'large', 'none', or a numeric value.
     *
     * @var int|string
     */
    protected $labelSize = [];

    /**
     * @var bool
     */
    protected $fullwidth = [];

    /**
     * @var string
     */
    protected $infoText = '';

    /**
     * Extra CSS classes added to the label element.
     *
     * @var array
     */
    protected $labelClass = [];

    /**
     * Extra CSS classes added to the control wrapper.
     *
     * @var array
     */
    protected $controlClass = [];

    /**
     * @var null|string
     */
    protected $styleFormGroup;

    /**
     * @var string
     */
    protected $inlineWithoutDefault;

    /**
     * @var bool
     */
    protected $labelRequired = false;

    /**
     * @param string $id
     *
     * @return void
     */
    public function __construct($id = '') {
        parent::__construct($id);
        $this->tag = 'div';
        $this->label = '';
        $this->showLabel = true;
        $this->labelSize = 'medium';
        $this->infoText = '';
        $this->fullwidth = false;
        $this->groupId = '';
        $this->groupClasses = [];
        $this->groupCustomCss = [];
        $this->styleFormGroup = null;
        $this->inlineWithoutDefault = 'inline_without_default';
        $this->labelRequired = false;
    }

    /**
     * @param null|string $id
     *
     * @return static
     */
    public static function factory($id = null) {
        // @phpstan-ignore-next-line
        return new static($id);
    }

    /**
     * @param bool $bool
     *
     * @return $this
     */
    public function setLabelRequired($bool = true) {
        $this->labelRequired = $bool;

        return $this;
    }

    /**
     * @return array
     */
    public function toArray() {
        $data = [];
        $control_data = array_merge_recursive($data, parent::toArray());
        $data['attr']['class'] = 'control-group';
        $control_label = [];
        $control_label['tag'] = 'label';
        $control_label['attr']['class'] = 'control-label';
        $control_label['attr']['id'] = $this->id . '-label';
        $control_label['text'] = $this->label;

        $control_wrapper = [];
        if (isset($control_data['children'])) {
            $control_wrapper['children'] = $control_data['children'];
        }
        $control_wrapper['tag'] = 'div';

        $data['children'][] = $control_label;
        $data['children'][] = $control_wrapper;
        $data['tag'] = $this->tag;

        return $data;
    }

    /**
     * @param int $indent
     *
     * @return string
     */
    public function html($indent = 0) {
        $html = new CStringBuilder();
        $html->setIndent($indent);
        $classes = $this->classes;
        $classes = implode(' ', $classes);
        if (strlen($classes) > 0) {
            $classes = ' ' . $classes;
        }
        $customCss = $this->custom_css;
        $customCss = $this->renderStyle($customCss);
        if (strlen($customCss) > 0) {
            $customCss = ' style="' . $customCss . '"';
        }
        $additionAttribute = '';
        foreach ($this->attr as $k => $v) {
            $additionAttribute .= ' ' . $k . '="' . $v . '"';
        }

        $labelRequiredHtml = '';
        if ($this->labelRequired) {
            $labelRequiredHtml = '<span style="color: red;">*</span> ';
        }

        $classFormField = 'control-group form-group';
        $labelClass = '';
        $controlClass = '';

        $groupIdAttr = '';
        if (strlen($this->groupId) > 0) {
            $groupIdAttr = 'id="' . $this->groupId . '" ';
        }

        $labelClass .= ' ' . implode(' ', $this->labelClass);
        $controlClass .= ' ' . implode(' ', $this->controlClass);
        $html->appendln('<div ' . $groupIdAttr . ' class="' . $classFormField . ' ' . $classes . '" ' . $customCss . $additionAttribute . '>')->incIndent();
        if ($this->showLabel) {
            if ($this->tooltip) {
                $this->tooltip->addClass('ml-1');
            }
            $tooltipHtml = $this->tooltip ? $this->tooltip->html() : '';
            $html->appendln('<label id="' . $this->id . '" class="form-label ' . $labelClass . ' control-label">' . $labelRequiredHtml . $this->label . $tooltipHtml . '</label>')->br();
        }

        $html->appendln('<div class="controls">')->incIndent()->br();

        $html->appendln($this->htmlChild($html->getIndent()))->br();
        if (strlen($this->infoText) > 0) {
            $html->appendln('<small class="help-block">' . $this->infoText . '</small>')->incIndent()->br();
        }

        $html->decIndent()->appendln('</div>')->incIndent()->br();

        $html->appendln('<div style="clear:both"></div>')->incIndent()->br();
        $html->decIndent()->appendln('</div>');

        return $html->text();
    }

    /**
     * @param int $indent
     *
     * @return string
     */
    public function js($indent = 0) {
        $js = CStringBuilder::factory()->setIndent($indent);

        $js->setIndent($indent);
        $tooltipJs = $this->tooltip ? $this->tooltip->js() : '';

        if ($tooltipJs) {
            $js->appendln($tooltipJs);
        }

        $js->appendln(parent::js($js->getIndent()))->br();

        return $js->text();
    }

    /**
     * @param null|string $styleFormGroup
     *
     * @return $this
     */
    public function setStyleFormGroup($styleFormGroup) {
        $this->styleFormGroup = $styleFormGroup;

        return $this;
    }

    /**
     * @param string $id
     *
     * @return $this
     */
    public function setGroupId($id) {
        $this->groupId = $id;

        return $this;
    }

    /**
     * @param string $class
     *
     * @return $this
     */
    public function addGroupClass($class) {
        $this->groupClasses[] = $class;

        return $this;
    }

    /**
     * @param string $key
     * @param string $val
     *
     * @return $this
     */
    public function groupCustomCss($key, $val) {
        $this->groupCustomCss[$key] = $val;

        return $this;
    }

    /**
     * @param int|string $size one of 'small', 'medium', 'large', 'none', or a numeric value
     *
     * @return $this
     */
    public function setLabelSize($size) {
        if (in_array($size, ['small', 'medium', 'large', 'none']) || is_numeric($size)) {
            $this->labelSize = $size;
        }

        return $this;
    }

    /**
     * @param string $infoText
     *
     * @return $this
     */
    public function setInfoText($infoText) {
        $this->infoText = $infoText;

        return $this;
    }

    /**
     * Alias setInfoText().
     *
     * @param string $helpText
     *
     * @return $this
     */
    public function setHelpText($helpText) {
        return $this->setInfoText($helpText);
    }

    /**
     * @return $this
     */
    public function showLabel() {
        $this->showLabel = true;

        return $this;
    }

    /**
     * @return $this
     */
    public function hideLabel() {
        $this->showLabel = false;

        return $this;
    }

    /**
     * @return $this
     */
    public function styleFormInline() {
        $this->styleFormGroup = 'inline';

        return $this;
    }

    /**
     * @param string $labelClass
     *
     * @return $this
     */
    public function addLabelClass($labelClass) {
        $this->labelClass[] = $labelClass;

        return $this;
    }

    /**
     * @param string $controlClass
     *
     * @return $this
     */
    public function addControlClass($controlClass) {
        $this->controlClass[] = $controlClass;

        return $this;
    }

    /**
     * @return string
     */
    public function getInlineWithoutDefault() {
        return $this->inlineWithoutDefault;
    }

    /**
     * @param string $inlineWithoutDefault
     *
     * @return $this
     */
    public function setInlineWithoutDefault($inlineWithoutDefault) {
        $this->inlineWithoutDefault = $inlineWithoutDefault;

        return $this;
    }
}
