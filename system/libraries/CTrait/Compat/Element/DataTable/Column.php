<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @see CElement_Component_DataTable_Column
 *
 *
 * @since May 12, 2018, 10:13:58 PM
 */
//@codingStandardsIgnoreStart
trait CTrait_Compat_Element_DataTable_Column {
    /**
     * @param string     $text
     * @param bool|array $lang
     *
     * @return $this
     *
     * @deprecated since version 1.2
     */
    public function set_label($text, $lang = true) {
        /** @var CElement_Component_DataTable_Column $this */
        return $this->setLabel($text, $lang);
    }

    /**
     * @return string
     *
     * @deprecated since version 1.2
     */
    public function get_label() {
        /** @var CElement_Component_DataTable_Column $this */
        return $this->getLabel();
    }

    /**
     * @return string
     *
     * @deprecated since version 1.2
     */
    public function get_fieldname() {
        /** @var CElement_Component_DataTable_Column $this */
        return $this->getFieldname();
    }

    /**
     * @return string
     *
     * @deprecated since version 1.2
     */
    public function get_align() {
        /** @var CElement_Component_DataTable_Column $this */
        return $this->getAlign();
    }

    /**
     * @param bool $bool
     *
     * @return $this
     *
     * @deprecated since version 1.2
     */
    public function set_hidden_phone($bool = true) {
        /** @var CElement_Component_DataTable_Column $this */
        return $this->setHiddenPhone($bool);
    }

    /**
     * @param bool $bool
     *
     * @return $this
     *
     * @deprecated since version 1.2
     */
    public function set_hidden_tablet($bool = true) {
        /** @var CElement_Component_DataTable_Column $this */
        return $this->setHiddenTablet($bool);
    }

    /**
     * @param bool $bool
     *
     * @return $this
     *
     * @deprecated since version 1.2
     */
    public function set_hidden_desktop($bool = true) {
        /** @var CElement_Component_DataTable_Column $this */
        return $this->setHiddenDesktop($bool);
    }

    /**
     * @param string $type
     *
     * @return $this
     *
     * @deprecated since version 1.2, please use setInputType
     */
    public function set_input_type($type) {
        /** @var CElement_Component_DataTable_Column $this */
        return $this->setInputType($type);
    }

    /**
     * @return bool
     *
     * @deprecated since version 1.2
     */
    public function get_no_line_break() {
        /** @var CElement_Component_DataTable_Column $this */
        return $this->getNoLineBreak();
    }

    /**
     * @param bool $bool
     *
     * @return $this
     *
     * @deprecated since version 1.2
     */
    public function set_no_line_break($bool) {
        /** @var CElement_Component_DataTable_Column $this */
        return $this->setNoLineBreak($bool);
    }

    /**
     * @param bool $bool
     *
     * @return $this
     *
     * @deprecated since version 1.2
     */
    public function set_visible($bool) {
        /** @var CElement_Component_DataTable_Column $this */
        return $this->setVisible($bool);
    }

    /**
     * @param bool $bool
     *
     * @return $this
     *
     * @deprecated since version 1.2
     */
    public function set_sortable($bool) {
        /** @var CElement_Component_DataTable_Column $this */
        return $this->setSortable($bool);
    }

    /**
     * @param bool $bool
     *
     * @return $this
     *
     * @deprecated since version 1.2
     */
    public function set_searchable($bool) {
        /** @var CElement_Component_DataTable_Column $this */
        return $this->setSearchable($bool);
    }

    /**
     * @param bool $bool
     *
     * @return $this
     *
     * @deprecated since version 1.2
     */
    public function set_editable($bool) {
        /** @var CElement_Component_DataTable_Column $this */
        return $this->setEditable($bool);
    }

    /**
     * @param string $w
     *
     * @return $this
     *
     * @deprecated since version 1.2, please use setWidth
     */
    public function set_width($w) {
        /** @var CElement_Component_DataTable_Column $this */
        return $this->setWidth($w);
    }

    /**
     * @param string $align
     *
     * @return $this
     *
     * @deprecated since version 1.2
     */
    public function set_align($align) {
        /** @var CElement_Component_DataTable_Column $this */
        return $this->setAlign($align);
    }

    /**
     * @param string $name
     * @param array  $args
     *
     * @return $this
     *
     * @deprecated since version 1.2
     */
    public function add_transform($name, $args = []) {
        /** @var CElement_Component_DataTable_Column $this */
        return $this->addTransform($name, $args);
    }

    /**
     * @param string $s
     *
     * @return $this
     *
     * @deprecated since version 1.2, please use setFormat
     */
    public function set_format($s) {
        /** @var CElement_Component_DataTable_Column $this */
        return $this->setFormat($s);
    }

    /**
     * @param bool   $export_pdf
     * @param string $th_class
     * @param int    $indent
     *
     * @return string
     *
     * @deprecated since version 1.2, please use renderHeaderHtml
     */
    public function render_header_html($export_pdf, $th_class = '', $indent = 0) {
        /** @var CElement_Component_DataTable_Column $this */
        return $this->renderHeaderHtml($export_pdf, $th_class, $indent);
    }
}
