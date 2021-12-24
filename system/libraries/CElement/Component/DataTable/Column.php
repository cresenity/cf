<?php

use SuperClosure\SerializableClosure;

class CElement_Component_DataTable_Column extends CObject {
    use CTrait_Compat_Element_DataTable_Column,
        CTrait_Element_Property_Label,
        CTrait_Element_Responsive;
    public $transforms = [];

    public $fieldname;

    public $width;

    public $align;

    public $format;

    public $sortable;

    public $searchable;

    public $editable;

    public $visible;

    public $input_type;

    public $noLineBreak;

    public $callback;

    public $callbackRequire;

    public $class;

    public $searchType = 'text';

    public $searchOptions = [];

    protected $exportLabel;

    protected $exportCallback;

    protected $exportCallbackRequire;

    public function __construct($fieldname) {
        parent::__construct();

        $this->fieldname = $fieldname;
        $this->align = 'left';
        $this->label = $fieldname;
        $this->width = '';
        $this->transforms = [];
        $this->format = '';
        $this->sortable = true;
        $this->searchable = true;
        $this->visible = true;
        $this->input_type = 'text';
        $this->editable = true;
        $this->noLineBreak = false;
        $this->hiddenPhone = false;
        $this->hiddenTablet = false;
        $this->hiddenDesktop = false;
        $this->callback = null;
        $this->callbackRequire = null;
        $this->class = [];
    }

    public static function factory($fieldname) {
        return new CElement_Component_DataTable_Column($fieldname);
    }

    public function getFieldname() {
        return $this->fieldname;
    }

    public function getAlign() {
        return $this->align;
    }

    public function setInputType($type) {
        $this->input_type = $type;

        return $this;
    }

    public function getNoLineBreak() {
        return $this->noLineBreak;
    }

    public function setNoLineBreak($bool = true) {
        return $this->setNoWrap($bool);
    }

    public function setNoWrap($bool = true) {
        $this->noLineBreak = $bool;

        return $this;
    }

    public function setVisible($bool) {
        $this->visible = $bool;

        return $this;
    }

    /**
     * Set sortable of column.
     *
     * @param bool $bool
     *
     * @return $this
     */
    public function setSortable($bool) {
        $this->sortable = $bool;

        return $this;
    }

    public function setSearchable($bool) {
        $this->searchable = $bool;

        return $this;
    }

    public function setSearchType($type) {
        $this->searchType = $type;

        return $this;
    }

    public function setSearchOptions($option) {
        $this->searchOptions = $option;

        return $this;
    }

    public function setEditable($bool) {
        $this->editable = $bool;

        return $this;
    }

    /**
     * Set width of column.
     *
     * @param int $w
     *
     * @return $this
     */
    public function setWidth($w) {
        $this->width = $w;

        return $this;
    }

    /**
     * Set align of column (left,right,center).
     *
     * @param string $align
     *
     * @return $this
     */
    public function setAlign($align) {
        $this->align = $align;

        return $this;
    }

    public function setCallback($callback, $require = '') {
        $this->callback = CHelper::closure()->serializeClosure($callback);
        $this->callbackRequire = $require;

        return $this;
    }

    public function setExportCallback($callback, $require = '') {
        $this->exportCallback = CHelper::closure()->serializeClosure($callback);
        $this->exportCallbackRequire = $require;

        return $this;
    }

    public function setExportLabel($label) {
        $this->exportLabel = $label;

        return $this;
    }

    public function addTransform($name, $args = []) {
        $func = CFunction::factory($name);
        if (!is_array($args)) {
            $args = [$args];
        }
        foreach ($args as $arg) {
            $func->addArg($arg);
        }

        $this->transforms[] = $func;

        return $this;
    }

    public function setFormat($s) {
        $this->format = $s;

        return $this;
    }

    public function getFormat() {
        return $this->format;
    }

    public function renderHeaderHtml($exportPdf, $thClass = '', $indent = 0) {
        $pdfTHeadTdAttr = '';
        if ($exportPdf) {
            $pdfTHeadTdAttr = ' bgcolor="#9f9f9f" color="#000"  ';
        }
        $html = new CStringBuilder();
        $html->setIndent($indent);
        $addition_attr = '';
        if (strlen($this->width) > 0) {
            $addition_attr .= ' width="' . $this->width . '"';
        }
        $class = $this->getClassAttribute();
        $dataClass = $class;
        $dataAlign = '';
        switch ($this->getAlign()) {
            case 'left':
                $dataAlign .= 'align-left';

                break;
            case 'right':
                $dataAlign .= 'align-right';

                break;
            case 'center':
                $dataAlign .= 'align-center';

                break;
        }
        $dataNoLineBreak = '';
        if ($this->getNoLineBreak()) {
            $dataNoLineBreak = 'no-line-break';
        }
        if ($exportPdf) {
            switch ($this->getAlign()) {
                case 'left':
                    $pdfTHeadTdAttr .= ' align="left"';

                    break;
                case 'right':
                    $pdfTHeadTdAttr .= ' align="right"';

                    break;
                case 'center':
                    $pdfTHeadTdAttr .= ' align="center"';

                    break;
            }
        }

        if ($this->sortable) {
            $class .= ' sortable';
        }
        if ($this->hiddenPhone) {
            $class .= ' hidden-phone';
        }
        if ($this->hiddenTablet) {
            $class .= ' hidden-tablet';
        }
        if ($this->hiddenDesktop) {
            $class .= ' hidden-desktop';
        }
        if ($exportPdf) {
            $html->appendln('<th ' . $pdfTHeadTdAttr . ' field_name = "' . $this->fieldname . '" align="center" class="thead ' . $thClass . $class . '" scope="col"' . $addition_attr . '>' . $this->label . '</th>');
        } else {
            $html->appendln('<th ' . $pdfTHeadTdAttr . ' field_name = "' . $this->fieldname . '" data-no-line-break="' . $dataNoLineBreak . '" data-align="' . $dataAlign . '" data-class="' . $dataClass . '" class="thead ' . $thClass . $class . '" scope="col"' . $addition_attr . '>' . $this->label . '</th>');
        }

        return $html->text();
    }

    public function addClass($class) {
        $this->class[] = $class;

        return $this;
    }

    public function determineExportCallback() {
        return $this->exportCallback ?: $this->callback;
    }

    public function determineExportCallbackRequire() {
        return $this->exportCallbackRequire ?: $this->callbackRequire;
    }

    public function determineExportLabel() {
        return $this->exportLabel ?: $this->label;
    }

    public function getClassAttribute() {
        return implode(' ', $this->class);
    }
}
