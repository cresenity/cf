<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Feb 17, 2018, 1:55:05 AM
 * @see CElement_Component_DataTable
 */
//@codingStandardsIgnoreStart
trait CTrait_Compat_Element_DataTable {
    /**
     * @param string $fieldname
     *
     * @deprecated since version 1.2
     *
     * @return CElement_Component_DataTable_Column
     */
    public function add_column($fieldname) {
        return $this->addColumn($fieldname);
    }

    /**
     * @deprecated since version 1.2, please use setDataFromQuery
     *
     * @return CElement_Component_DataTable
     *
     * @param mixed $q
     */
    public function set_data_from_query($q) {
        return $this->setDataFromQuery($q);
    }

    /**
     * @deprecated since version 1.2, please use setAjax
     *
     * @return CElement_Component_DataTable
     *
     * @param mixed $bool
     */
    public function set_ajax($bool) {
        return $this->setAjax($bool);
    }

    /**
     * @deprecated since version 1.2, please use rowActionCount
     *
     * @return int
     */
    public function action_count() {
        return $this->rowActionCount();
    }

    /**
     * @deprecated since version 1.2, please use haveRowAction
     *
     * @return bool
     */
    public function have_action() {
        return $this->haveRowAction();
    }

    /**
     * @deprecated since version 1.2, please use addRowAction
     *
     * @return CElement_Component_Action
     *
     * @param mixed $id
     */
    public function add_row_action($id = '') {
        return $this->addRowAction($id);
    }

    /**
     * @deprecated since version 1.2, please use setRowActionStyle
     *
     * @return CElement_Component_DataTable
     *
     * @param mixed $style
     */
    public function set_action_style($style) {
        return $this->setRowActionStyle($style);
    }

    /**
     * @deprecated since version 1.2
     *
     * @return $this
     *
     * @param mixed $filename
     */
    public function set_export_filename($filename) {
        return $this->setExportFilename($filename);
    }

    public function set_export_sheetname($sheetname) {
        return $this->setExportSheetname($sheetname);
    }

    public function set_domain($domain) {
        return $this->setDomain($domain);
    }

    public function set_database($db) {
        return $this->setDatabase($db);
    }

    public function set_table_striped($table_striped) {
        return $this->setTableStriped($table_striped);
    }

    public function set_widget_title($bool) {
        return $this->setWidgetTitle($bool);
    }

    public static function action_download_excel($data) {
        return static::actionDownloadExcel($data);
    }

    public function add_footer_action($id = '') {
        return $this->addFooterAction($id);
    }

    private static function export_excelxml_static($filename, $sheet_name = null, $table) {
        return static::exportExcelxmlStatic($filename, $sheet_name = null, $table);
    }

    public function have_footer_action() {
        return $this->haveFooterAction();
    }

    public function is_exported() {
        return $this->isExported();
    }

    public function set_title($title, $lang = true) {
        return $this->setTitle($title, $lang);
    }

    public function set_dom($dom) {
        return $this->setDom($dom);
    }

    public function set_custom_column_header($html) {
        return $this->setCustomColumnHeader($html);
    }

    public function set_footer($bool) {
        return $this->setFooter($bool);
    }

    public function set_responsive($bool) {
        return $this->setResponsive($bool);
    }

    public function set_show_header($bool) {
        return $this->setShowHeader($bool);
    }

    /**
     * @param bool $quick_search
     *
     * @return $this
     *
     * @deprecated since 1.2 use setQuickSearch
     */
    public function set_quick_search($quick_search) {
        return $this->setQuickSearch($quick_search);
    }

    public function set_tbody_id($id) {
        return $this->setTbodyId($id);
    }

    public function add_footer_field($label, $value, $align = 'left', $labelcolspan = 0) {
        return $this->addFooterField($label, $value, $align, $labelcolspan);
    }

    public function set_header_no_line_break($bool) {
        return $this->setHeaderNoLineBreak($bool);
    }

    public function have_header_action() {
        return $this->haveHeaderAction();
    }

    public function set_header_action_style($style) {
        return $this->setHeaderActionStyle($style);
    }

    public function header_action_count() {
        return $this->headerActionCount();
    }

    public function set_option($key, $val) {
        return $this->setOption($key, $val);
    }

    public function get_option($key) {
        return $this->getOption($key);
    }

    public function set_ajax_method($value) {
        return $this->setAjaxMethod($value);
    }

    public function set_apply_data_table($bool) {
        return $this->setApplyDataTable($bool);
    }

    public function set_display_length($length) {
        return $this->setDisplayLength($length);
    }

    public function cell_callback_func($func, $require = '') {
        return $this->cellCallbackFunc($func, $require);
    }

    public function filter_action_callback_func($func, $require = '') {
        return $this->filterActionCallbackFunc($func, $require);
    }

    /**
     * @deprecated since version 1.2
     *
     * @return $this
     *
     * @param mixed $fieldname
     */
    public function set_key($fieldname) {
        return $this->setKey($fieldname);
    }

    /**
     * @deprecated since version 1.2
     *
     * @return $this
     *
     * @param mixed $group_by
     */
    public function set_group_by($group_by) {
        return $this->setGroupBy($group_by);
    }

    /**
     * @deprecated since version 1.2
     *
     * @return $this
     *
     * @param mixed $id
     */
    public function add_header_action($id = '') {
        return $this->addHeaderAction($id);
    }

    /**
     * @deprecated since version 1.2
     *
     * @return $this
     *
     * @param mixed $bool
     */
    public function set_checkbox($bool) {
        return $this->setCheckbox($bool);
    }

    /**
     * @deprecated since version 1.2
     *
     * @return $this
     *
     * @param mixed $val
     */
    public function set_checkbox_value($val) {
        return $this->setCheckboxValue($val);
    }

    /**
     * @deprecated since version 1.2
     *
     * @return $this
     *
     * @param mixed $bool
     */
    public function set_header_sortable($bool) {
        return $this->setHeaderSortabel($bool);
    }

    /**
     * @deprecated since version 1.2
     *
     * @return $this
     *
     * @param mixed $bool
     */
    public function set_numbering($bool) {
        return $this->setNumbering($bool);
    }

    /**
     * @deprecated since version 1.2
     *
     * @return $this
     */
    public function enable_numbering() {
        return $this->enableNumbering();
    }

    /**
     * @deprecated since version 1.2
     *
     * @return $this
     */
    public function disable_numbering() {
        return $this->disableNumbering();
    }

    /**
     * @deprecated since version 1.2
     *
     * @return $this
     */
    public function enable_checkbox() {
        return $this->enableCheckbox();
    }

    /**
     * @deprecated since version 1.2
     *
     * @return $this
     */
    public function disable_checkbox() {
        return $this->disableCheckbox();
    }

    /**
     * @deprecated since version 1.2
     *
     * @return $this
     *
     * @param mixed $q
     */
    public function set_query($q) {
        return $this->setQuery($q);
    }

    /**
     * @deprecated since version 1.2
     *
     * @return $this
     *
     * @param mixed $el
     */
    public function set_data_from_elastic($el) {
        return $this->setDataFromElastic($el);
    }

    /**
     * @deprecated since version 1.2
     *
     * @return $this
     *
     * @param mixed $a
     */
    public function set_data_from_array($a) {
        return $this->setDataFromArray($a);
    }

    public function set_pdf_font_size($size) {
        return $this->setPdfFontSize($size);
    }

    public function set_pdf_orientation($orientation) {
        return $this->setPdfOrientation($orientation);
    }

    public function export_pdf($filename) {
        return $this->exportPdf($filename);
    }

    public function export_excelcsv($filename) {
        return $this->exportExcelcsv($filename);
    }

    public function export_excelxml($filename, $sheet_name = null) {
        return $this->exportExcelxml($filename, $sheet_name);
    }

    public function add_report_header($line) {
        return $this->addReportHeader($line);
    }

    public function export_excel($filename, $sheet_name) {
        return $this->exportExcel($filename, $sheet_name);
    }
}
