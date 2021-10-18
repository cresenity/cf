<?php

class CElement_Component_DataTable extends CElement_Component {
    use CTrait_Compat_Element_DataTable,
        CTrait_Element_ActionList_Row,
        CTrait_Element_ActionList_Header,
        CTrait_Element_ActionList_Footer,
        CElement_Component_DataTable_Trait_GridViewTrait,
        CElement_Component_DataTable_Trait_ExportTrait,
        CElement_Component_DataTable_Trait_JavascriptTrait,
        CElement_Component_DataTable_Trait_HtmlTrait,
        CElement_Component_DataTable_Trait_ActionCreationTrait,
        CElement_Component_DataTable_Trait_CheckboxTrait,
        CElement_Component_DataTable_Trait_SearchTrait,
        CElement_Component_DataTable_Trait_FooterTrait;

    const ACTION_LOCATION_FIRST = 'first';

    const ACTION_LOCATION_LAST = 'last';

    public $defaultPagingList = [
        '10' => '10',
        '25' => '25',
        '50' => '50',
        '100' => '100',
        '-1' => 'ALL',
    ];

    public $current_row = 1;

    public $dbName;

    public $dbConfig;

    /**
     * Columns of table.
     *
     * @var CElement_Component_DataTable_Column[]
     */
    public $columns;

    public $requires = [];

    public $data;

    public $keyField;

    public $numbering;

    public $query;

    public $customColumnHeader;

    public $header_sortable;

    public $cellCallbackFunc;

    public $filterActionCallbackFunc;

    public $display_length;

    public $paging_list;

    public $responsive;

    public $options;

    public $applyDataTable;

    public $group_by;

    public $title;

    public $ajax;

    public $ajax_method;

    public $icon;

    public $editable_form;

    public $can_edit;

    public $can_add;

    public $can_delete;

    public $can_view;

    public $headerNoLineBreak;

    public $pdf_font_size;

    public $pdf_orientation;

    public $show_header;

    public $isElastic = false;

    public $isCallback = false;

    public $callbackRequire = null;

    public $callbackOptions = null;

    public $infoText = '';

    protected $isModelQuery = false;

    protected $actionLocation = 'last';

    protected $haveRowSelection = false;

    protected $tableStriped;

    protected $tableBordered;

    protected $tbodyId;

    protected $js_cell;

    protected $dom = null;

    protected $widget_title;

    protected $fixedColumn;

    protected $scrollX;

    protected $scrollY;

    protected $dbResolver;

    protected $actionHeaderLabel = 'Actions';

    protected $labels = [
        'noData' => 'No data available in table',
        'first' => 'First',
        'last' => 'Last',
        'previous' => 'Previous',
        'next' => 'Next',
        'processing' => 'Processing'
    ];

    public function __construct($id = '') {
        parent::__construct($id);
        $this->defaultPagingList['-1'] = c::__('ALL');
        $this->tag = 'table';
        $this->responsive = false;

        $db = CDatabase::instance();

        $this->dbConfig = $db->config();
        $this->dbName = $db->getName();
        $this->display_length = '10';
        $this->paging_list = $this->defaultPagingList;
        $this->options = new CElement_Component_DataTable_Options();
        $this->data = [];
        $this->keyField = '';
        $this->columns = [];
        $this->rowActionList = CElement_Factory::createList('ActionList');
        $this->rowActionList->setStyle('btn-icon-group')->addClass('btn-table-action');
        $this->headerActionList = CElement_Factory::createList('ActionList');
        $this->headerActionList->setStyle('widget-action');
        $this->footerActionList = CElement_Factory::createList('ActionList');
        $this->footerActionList->setStyle('btn-list');
        $this->checkbox = false;
        $this->checkboxValue = [];
        $this->numbering = false;
        $this->query = '';
        $this->header_sortable = true;
        $this->footerTitle = '';
        $this->footer = false;
        $this->footerField = [];
        $this->cellCallbackFunc = '';
        $this->filterActionCallbackFunc = '';
        $this->display_length = '10';
        $this->ajax = false;
        $this->ajax_method = 'get';
        $this->title = '';
        $this->editable_form = null;
        $this->can_edit = false;
        $this->can_add = false;
        $this->can_delete = false;
        $this->can_view = false;
        $this->export_pdf = false;
        $this->export_excelxml = false;
        $this->export_excelcsv = false;
        $this->export_xml = false;
        $this->export_excel = false;
        $this->headerNoLineBreak = false;

        $this->customColumnHeader = '';
        $this->show_header = true;
        $this->applyDataTable = true;
        $this->group_by = '';
        $this->icon = '';
        $this->pdf_font_size = 8;
        $this->pdf_orientation = 'P';
        $this->requires = [];
        $this->js_cell = '';
        $this->quickSearch = false;
        $this->haveQuickSearchPlaceholder = true;
        $this->tbodyId = '';

        $this->report_header = [];

        $this->widget_title = true;

        $this->export_filename = $this->id;
        $this->export_sheetname = $this->id;
        $this->tableStriped = true;
        $this->tableBordered = true;
        $this->haveDataTableViewAction = false;
        $this->dataTableView = CConstant::TABLE_VIEW_ROW;
        $this->dataTableViewColCount = 5;
        $this->fixedColumn = false;
        $this->scrollX = false;
        $this->scrollY = false;

        $this->infoText = clang::__('Showing') . ' _START_ ' . clang::__('to') . ' _END_ ' . clang::__('of') . ' _TOTAL_ ' . clang::__('entries') . '';
        CClientModules::instance()->registerModule('jquery.datatable');

        //read theme data

        $this->dom = c::theme('datatable.dom', c::theme('table.dom'));
        $this->actionLocation = c::theme('datatable.actionLocation', c::theme('table.actionLocation', static::ACTION_LOCATION_LAST));
        $this->haveRowSelection = c::theme('datatable.haveRowSelection', c::theme('table.haveRowSelection', false));
        $this->classes = CElement_Helper::getClasses(c::theme('datatable.class'));

        $this->checkboxRenderer = CManager::theme()->getData('datatable.renderer.checkbox', [CElement_Component_DataTable_Renderer::class, 'checkboxCell']);
        $this->labels['noData'] = CManager::theme()->getData('datatable.label.noData', 'No data available in table');
        $this->labels['first'] = CManager::theme()->getData('datatable.label.first', 'First');
    }

    public static function factory($id = '') {
        return new static($id);
    }

    public function setLabelNoData($label) {
        $this->labels['noData'] = $label;

        return $this;
    }

    public function setDatabaseResolver($dbResolver) {
        $this->dbResolver = $dbResolver;

        return $this;
    }

    public function setActionHeaderLabel($label) {
        $this->actionHeaderLabel = $label;

        return $this;
    }

    /**
     * @param bool $bool
     *
     * @return \CElement_Component_DataTable
     */
    public function setScrollX($bool = true) {
        $this->scrollX = $bool;

        return $this;
    }

    /**
     * @param bool $bool
     *
     * @return \CElement_Component_DataTable
     */
    public function setScrollY($bool = true) {
        $this->scrollY = $bool;

        return $this;
    }

    /**
     * @param string $actionLocation
     *
     * @throws Exception
     *
     * @return $this
     */
    public function setActionLocation($actionLocation) {
        if (!in_array($actionLocation, ['first', 'last'])) {
            throw new Exception('action location parameter must be first or last');
        }
        $this->actionLocation = $actionLocation;

        return $this;
    }

    /**
     * @return string
     */
    public function getActionLocation() {
        return $this->actionLocation;
    }

    public function setDomain($domain) {
        $this->setDatabase(CDatabase::instance(null, null, $domain));

        return $this;
    }

    /**
     * @param CDatabase|string $db
     * @param array            $dbConfig
     *
     * @return CElement_Component_DataTable
     */
    public function setDatabase($db, $dbConfig = null) {
        if ($db instanceof CDatabase) {
            $this->dbName = $db->getName();
            $this->dbConfig = $db->config();
        } else {
            $this->dbName = $db;
            $this->dbConfig = $dbConfig;
        }

        return $this;
    }

    public function setInfoText($infoText) {
        $this->infoText = $infoText;

        return $this;
    }

    /**
     * @param bool $bool
     *
     * @return \CElement_Component_DataTable
     */
    public function setFixedColumn($bool = true) {
        $this->fixedColumn = $bool;

        return $this;
    }

    /**
     * @param bool $tableStriped
     *
     * @return \CElement_Component_DataTable
     */
    public function setTableStriped($tableStriped) {
        $this->tableStriped = $tableStriped;

        return $this;
    }

    /**
     * @param bool $bool
     *
     * @return \CElement_Component_DataTable
     */
    public function setTableBordered($bool) {
        $this->tableBordered = $bool;

        return $this;
    }

    /**
     * @param bool $bool
     *
     * @return \CElement_Component_DataTable
     */
    public function setWidgetTitle($bool) {
        $this->widget_title = $bool;

        return $this;
    }

    public static function actionDownloadExcel($data) {
        $table = $data->table;
        $table = unserialize($table);
        $export_filename = $table->export_filename;
        if (substr($table->export_filename, 3) != 'xls') {
            $export_filename .= '.xls';
        }
        self::exportExcelxmlStatic($export_filename, $table->export_sheetname, $table);
    }

    public function setTitle($title, $lang = true) {
        if ($lang) {
            $title = clang::__($title);
        }
        $this->title = $title;

        return $this;
    }

    public function setDom($dom) {
        $this->dom = $dom;

        return $this;
    }

    public function setCustomColumnHeader($html) {
        $this->customColumnHeader = $html;

        return $this;
    }

    public function setResponsive($bool) {
        $this->responsive = $bool;

        return $this;
    }

    public function setShowHeader($bool) {
        $this->show_header = $bool;

        return $this;
    }

    public function setTbodyId($id) {
        $this->tbodyId = $id;

        return $this;
    }

    public function setHeaderNoLineBreak($bool) {
        $this->headerNoLineBreak = $bool;

        return $this;
    }

    public function setOption($key, $val) {
        $this->options->setOption($key, $val);

        return $this;
    }

    public function getOption($key) {
        return $this->options->getOption($key);
    }

    public function setAjax($bool = true) {
        $this->ajax = $bool;
        $this->requery();

        return $this;
    }

    public function setAjaxMethod($value) {
        $this->ajax_method = $value;

        return $this;
    }

    /**
     * @param bool $bool
     *
     * @return CElement_Component_DataTable
     */
    public function setApplyDataTable($bool) {
        $this->applyDataTable = $bool;
        if ($this->applyDataTable == false) {
            $this->setAjax(false);
        }

        return $this;
    }

    public function setDisplayLength($length) {
        $this->display_length = $length;

        return $this;
    }

    /**
     * Set callback for table cell render.
     *
     * @param callable $func    parameter: $table,$col,$row,$value
     * @param string   $require File location of callable function to require
     *
     * @return $this
     */
    public function cellCallbackFunc($func, $require = '') {
        $this->cellCallbackFunc = $func;
        if (strlen($require) > 0) {
            $this->requires[] = $require;
        }

        return $this;
    }

    public function filterActionCallbackFunc($func, $require = '') {
        $this->filterActionCallbackFunc = $func;
        if (strlen($require) > 0) {
            $this->requires[] = $require;
        }

        return $this;
    }

    public function setKey($fieldname) {
        $this->keyField = $fieldname;

        return $this;
    }

    /**
     * @param string $fieldname
     *
     * @return CElement_Component_DataTable_Column
     */
    public function addColumn($fieldname) {
        $col = CElement_Component_DataTable_Column::factory($fieldname);
        $this->columns[] = $col;

        return $col;
    }

    /**
     * @param string $group_by
     *
     * @return $this
     */
    public function setGroupBy($group_by) {
        $this->group_by = $group_by;

        return $this;
    }

    /**
     * @param bool $bool
     *
     * @return $this
     */
    public function setHeaderSortable($bool = true) {
        $this->header_sortable = $bool;

        return $this;
    }

    /**
     * @param bool $bool
     *
     * @return $this
     */
    public function setNumbering($bool = true) {
        $this->numbering = $bool;

        return $this;
    }

    /**
     * Alias for setNumbering(true).
     *
     * @return $this
     */
    public function enableNumbering() {
        $this->numbering = true;

        return $this;
    }

    /**
     * Alias for setNumbering(false).
     *
     * @return $this
     */
    public function disableNumbering() {
        $this->numbering = false;

        return $this;
    }

    public function setQuery($q) {
        $this->query = $q;

        return $this;
    }

    /**
     * @return $this
     */
    public function requery() {
        if (!$this->isElastic && !$this->isCallback) {
            if ($this->ajax == false) {
                if (is_string($this->query) && strlen($this->query) > 0) {
                    $r = $this->db()->query($this->query);
                    $this->data = $r->result(false);
                }
            } else {
                $this->data = [];
            }
        }

        return $this;
    }

    /**
     * @param string $q
     *
     * @return $this
     */
    public function setDataFromQuery($q) {
        if ($this->ajax == false) {
            $r = $this->db()->query($q);
            $this->data = $r->result(false);
        }
        $this->query = $q;

        return $this;
    }

    /**
     * @param string $q
     *
     * @return $this
     */
    public function setDataFromModelQuery(CModel_Query $q) {
        if ($this->ajax == false) {
            $r = $q->get();
            $this->data = $r;
        }
        $this->query = $q;

        return $this;
    }

    /**
     * @param CModel|CModel_Query $model
     *
     * @return $this
     */
    public function setDataFromModel($model) {
        $modelQuery = $model;
        if ($modelQuery instanceof CModel_Collection) {
            throw new CException('error when calling setDataFromModel, please use CModel/CModel_Query instance (CModel_Collection passed)');
        }
        $sql = $this->db()->compileBinds($modelQuery->toSql(), $modelQuery->getBindings());

        return $this->setDataFromQuery($sql);
    }

    /**
     * @param CElastic_Search $el
     * @param string          $require
     *
     * @return $this
     */
    public function setDataFromElastic($el, $require = null) {
        $this->query = $el;
        $this->isElastic = true;
        if ($el instanceof CElastic_Search) {
            $this->query = $el->ajax_data();
        }

        return $this;
    }

    /**
     * @param callable $callback
     * @param array    $callbackOptions
     * @param string   $require
     *
     * @return $this
     */
    public function setDataFromCallback($callback, $callbackOptions = [], $require = null) {
        $this->query = CHelper::closure()->serializeClosure($callback);
        $this->isCallback = true;
        $this->callbackOptions = $callbackOptions;
        $this->callbackRequire = $require;

        return $this;
    }

    /**
     * @param array $arr
     *
     * @return $this
     */
    public function setDataFromArray($arr) {
        $this->data = $arr;

        return $this;
    }

    /**
     * @return CDatabase
     */
    public function db() {
        if ($this->dbResolver != null) {
            return $this->dbResolver->connection($this->dbName);
        }

        if (strlen($this->dbName) > 0) {
            return CDatabase::instance($this->dbName);
        }

        return CDatabase::instance($this->dbName, $this->dbConfig);
    }

    /**
     * @return string
     */
    public function getKeyField() {
        return $this->keyField;
    }

    /**
     * @return string
     */
    public function getDomain() {
        return $this->domain;
    }

    /**
     * @return array
     */
    public function getColumns() {
        return $this->columns;
    }

    /**
     * @param mixed $index
     *
     * @return CElement_Component_DataTable_Column
     */
    public function getColumn($index) {
        return carr::get($this->columns, $index);
    }

    /**
     * @return int
     */
    public function getColumnOffset() {
        return $this->getColumnLeftOffset();
    }

    /**
     * @return int
     */
    public function getColumnLeftOffset() {
        $offset = 0;
        if ($this->checkbox) {
            $offset++;
        }
        if ($this->numbering) {
            $offset++;
        }
        if ($this->getActionLocation() == static::ACTION_LOCATION_FIRST) {
            if ($this->rowActionCount() > 0) {
                $offset++;
            }
        }

        return $offset;
    }

    /**
     * @return int
     */
    public function getColumnRightOffset() {
        $offset = 0;
        if ($this->getActionLocation() == static::ACTION_LOCATION_LAST) {
            if ($this->rowActionCount() > 0) {
                $offset++;
            }
        }

        return $offset;
    }

    /**
     * @return string
     */
    public function getQuery() {
        return $this->query;
    }

    /**
     * @return bool
     */
    public function haveRowSelection() {
        return $this->haveRowSelection;
    }

    /**
     * @return CExporter_Exportable_DataTable
     */
    public function toExportable() {
        return new CExporter_Exportable_DataTable($this);
    }

    /**
     * @return CCollection
     */
    public function getCollection() {
        $data = [];
        if ($this->isCallback) {
            $callbackData = CFunction::factory($this->query)
                ->addArg($this->callbackOptions)
                ->setRequire($this->callbackRequire)
                ->execute();
            $data = carr::get($callbackData, 'data');
        } else {
            $this->setAjax(false);
            $data = $this->data;
        }

        return c::collect($data);
    }

    public function downloadExcel($filename = null) {
        if ($filename == null) {
            $filename = CExporter::randomFilename();
        }

        return CExporter::download($this->toExportable(), $filename);
    }

    public function queueDownloadExcel($filePath, $disk = null, $writerType = null, $diskOptions = []) {
        return CExporter::queue($this->toExportable(), $filePath, $disk, $writerType, $diskOptions);
    }
}
