<?php

class CElement_Component_DataTable extends CElement_Component {
    use CTrait_Compat_Element_DataTable,
        CTrait_Element_ActionList_Row,
        CTrait_Element_ActionList_Header,
        CTrait_Element_ActionList_Footer,
        CTrait_Element_Property_Title,
        CTrait_Element_Property_Icon,
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

    /**
     * @var array
     */
    public $defaultPagingList = [
        '10' => '10',
        '25' => '25',
        '50' => '50',
        '100' => '100',
        '-1' => 'ALL',
    ];

    /**
     * @var int
     */
    public $current_row = 1;

    /**
     * Database connection name.
     *
     * @var string
     */
    public $dbName;

    /**
     * Database connection config, used when dbName is empty.
     *
     * @var null|array
     */
    public $dbConfig;

    /**
     * Columns of table.
     *
     * @var CElement_Component_DataTable_Column[]
     */
    public $columns;

    /**
     * @var array
     */
    public $requires = [];

    /**
     * @var array|CModel_Collection
     */
    public $data;

    /**
     * @var string
     */
    public $keyField;

    /**
     * @var bool
     */
    public $numbering;

    /**
     * @var string|CModel_Query|CManager_Contract_DataProviderInterface|Closure
     */
    public $query;

    /**
     * @var string
     */
    public $customColumnHeader;

    /**
     * @var bool
     */
    public $headerSortable;

    /**
     * @var string|Closure|CFunction_SerializableClosure
     */
    public $cellCallbackFunc;

    /**
     * @var string|callable
     */
    public $filterActionCallbackFunc;

    /**
     * @var string|int
     */
    public $displayLength;

    /**
     * @var array
     */
    public $paging_list;

    /**
     * @var bool
     */
    public $responsive;

    /**
     * @var null|CElement_Component_DataTable_Options
     */
    public $options;

    /**
     * @var bool
     */
    public $applyDataTable;

    /**
     * @var string
     */
    public $group_by;

    /**
     * @var bool
     */
    public $ajax;

    /**
     * @var string
     */
    public $ajax_method;

    /**
     * @var null|string
     */
    public $editable_form;

    /**
     * @var bool
     */
    public $headerNoLineBreak;

    /**
     * @var int
     */
    public $pdf_font_size;

    /**
     * @var string
     */
    public $pdf_orientation;

    /**
     * @var bool
     */
    public $show_header;

    /**
     * @var bool
     */
    public $isElastic = false;

    /**
     * @var bool
     */
    public $isCallback = false;

    /**
     * @var null|string
     */
    public $callbackRequire = null;

    /**
     * @var null|array
     */
    public $callbackOptions = null;

    /**
     * @var string
     */
    public $infoText = '';

    /**
     * @var bool
     */
    protected $isModelQuery = false;

    /**
     * @var string
     */
    protected $actionLocation = 'last';

    /**
     * @var bool
     */
    protected $haveRowSelection = false;

    /**
     * @var bool
     */
    protected $tableStriped;

    /**
     * @var bool
     */
    protected $tableBordered;

    /**
     * @var string
     */
    protected $tbodyId;

    /**
     * @var string
     */
    protected $js_cell;

    /**
     * @var null|string
     */
    protected $dom = null;

    /**
     * @var bool
     */
    protected $widget_title;

    /**
     * @var null|int
     */
    protected $fixedColumn;

    /**
     * @var null|bool
     */
    protected $fixedHeader;

    /**
     * @var null|bool
     */
    protected $colReorder;

    /**
     * @var bool
     */
    protected $scrollX;

    /**
     * @var bool
     */
    protected $scrollY;

    /**
     * @var CDatabase_Contract_ConnectionResolverInterface
     */
    protected $dbResolver;

    /**
     * @var string
     */
    protected $actionHeaderLabel = 'Actions';

    /**
     * @var array
     */
    protected $labels = [];

    /**
     * @var array
     */
    protected $buttons = [];

    /**
     * @var array
     */
    protected $domElements = [];

    /**
     * @var null|Closure|CFunction_SerializableClosure
     */
    protected $rowClassCallbackFunction = null;

    /**
     * @var int
     */
    protected $autoRefreshInterval = 0;

    /**
     * @var string
     */
    protected $themeType = 'table';

    /**
     * @var null|string
     */
    protected $domain;

    /**
     * @param string $id
     */
    public function __construct($id = '') {
        parent::__construct($id);
        $this->defaultPagingList['-1'] = c::__('ALL');
        $this->tag = 'table';
        $this->responsive = false;
        $this->labels = [];

        $db = c::db();

        $this->dbName = $db->getName();
        $this->dbConfig = strlen($db->getName()) == 0 ? $db->getConfig() : [];
        $this->displayLength = '10';
        $this->paging_list = $this->defaultPagingList;
        $this->options = new CElement_Component_DataTable_Options();
        $this->data = [];
        $this->keyField = '';
        $this->columns = [];
        $this->rowActionList = null;
        $this->headerActionList = null;
        $this->footerActionList = null;
        $this->checkbox = false;
        $this->checkboxValue = [];
        $this->numbering = false;
        $this->query = '';
        $this->headerSortable = true;
        $this->footerTitle = '';
        $this->footer = false;
        $this->footerFields = [];
        $this->cellCallbackFunc = '';
        $this->filterActionCallbackFunc = '';
        $this->displayLength = '10';
        $this->ajax = false;
        $this->ajax_method = 'get';
        $this->title = '';
        $this->editable_form = null;
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
        $this->fixedColumn = null;
        $this->fixedHeader = null;
        $this->scrollX = false;
        $this->scrollY = false;

        $this->infoText = c::__('Showing') . ' _START_ ' . c::__('to') . ' _END_ ' . c::__('of') . ' _TOTAL_ ' . c::__('entries') . '';
        c::manager()->registerModule('jquery.datatable');

        //read theme data

        $this->dom = c::theme('datatable.dom', c::theme('table.dom'));
        $this->actionLocation = c::theme('datatable.actionLocation', c::theme('table.actionLocation', static::ACTION_LOCATION_LAST));
        $this->haveRowSelection = c::theme('datatable.haveRowSelection', c::theme('table.haveRowSelection', false));
        $this->classes = CElement_Helper::getClasses(c::theme('datatable.class'));

        $this->checkboxRenderer = CManager::theme()->getData('datatable.renderer.checkbox', [CElement_Component_DataTable_Renderer::class, 'checkboxCell']);
        $this->labels['emptyTable'] = CManager::theme()->getData('datatable.label.emptyTable', c::__('element/datatable.emptyTable'));
        $this->labels['first'] = CManager::theme()->getData('datatable.label.first', c::__('element/datatable.paginate.first'));
        $this->labels['last'] = CManager::theme()->getData('datatable.label.last', c::__('element/datatable.paginate.last'));
        $this->labels['previous'] = CManager::theme()->getData('datatable.label.previous', c::__('element/datatable.paginate.previous'));
        $this->labels['next'] = CManager::theme()->getData('datatable.label.next', c::__('element/datatable.paginate.next'));
        $this->labels['processing'] = CManager::theme()->getData('datatable.label.processing', c::__('element/datatable.processing'));
        $this->labels['search'] = CManager::theme()->getData('datatable.label.search', c::__('element/datatable.search'));
        $this->labels['show'] = CManager::theme()->getData('datatable.label.show', c::__('element/datatable.show'));
        $this->labels['entries'] = CManager::theme()->getData('datatable.label.entries', c::__('element/datatable.entries'));
        $this->loadTranslation();
        $this->actionHeaderLabel = carr::get($this->labels, 'actionHeaderLabel', $this->actionHeaderLabel);
    }

    /**
     * @return void
     */
    protected function loadTranslation() {
        $translator = CTranslation::translator();
        $translation = $translator->getLoader()->load($translator->getLocale(), 'element/datatable');
        $dots = carr::dot($translation);
        foreach ($dots as $key => $value) {
            carr::set($this->labels, $key, $value);
        }
    }

    /**
     * @return array
     */
    public function getLabels() {
        $labels = $this->labels;

        $labels['searchPlaceholder'] = $this->searchPlaceholder;

        return $labels;
    }

    /**
     * @param array $buttons
     *
     * @return $this
     */
    public function setButtons(array $buttons) {
        $this->buttons = $buttons;

        return $this;
    }

    /**
     * @return array
     */
    protected function getLegacyLabels() {
        $legacy = [];

        $legacyDotsMaps = [
            'sSearch' => 'search',
            'sProcessing' => 'processing',
            'sLengthMenu' => 'lengthMenu',
            'oPaginate.sFirst' => 'paginate.first',
            'oPaginate.sLast' => 'paginate.last',
            'oPaginate.sNext' => 'paginate.next',
            'oPaginate.sPrevious' => 'paginate.previous',
            'sInfo' => 'info',
            'sInfoEmpty' => 'infoEmpty',
            'sEmptyTable' => 'emptyTable',
            'sInfoThousands' => 'thousands',
        ];
        foreach ($legacyDotsMaps as $keyLegacy => $key) {
            carr::set($legacy, $keyLegacy, carr::get($this->labels, $key, $keyLegacy));
        }
        $legacy['sSearchPlaceholder'] = $this->searchPlaceholder;

        return $legacy;
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
     * @param array $list
     *
     * @return $this
     */
    public function setPagingList(array $list) {
        $this->paging_list = $list;

        return $this;
    }

    /**
     * @param string $label
     *
     * @return $this
     */
    public function setLabelNoData($label) {
        $this->labels['noData'] = $label;

        return $this;
    }

    /**
     * @param CDatabase_Contract_ConnectionResolverInterface $dbResolver
     *
     * @return $this
     */
    public function setDatabaseResolver($dbResolver) {
        $this->dbResolver = $dbResolver;

        return $this;
    }

    /**
     * @param string $label
     *
     * @return $this
     */
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

    /**
     * @param string $domain
     *
     * @return $this
     */
    public function setDomain($domain) {
        $this->domain = $domain;

        return $this;
    }

    /**
     * @param CDatabase_Connection|string $db
     * @param array                       $dbConfig
     *
     * @return CElement_Component_DataTable
     */
    public function setDatabase($db, $dbConfig = null) {
        if ($db instanceof CDatabase_Connection) {
            $this->dbName = $db->getName();
            $this->dbConfig = strlen($this->dbName) == 0 ? $db->getConfig() : [];
        } else {
            $this->dbName = $db;
            $this->dbConfig = $dbConfig;
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
     * @param bool $bool
     *
     * @return $this
     */
    public function setColReorder($bool = true) {
        $this->colReorder = $bool;

        return $this;
    }

    /**
     * @param int|bool $column
     *
     * @return \CElement_Component_DataTable
     */
    public function setFixedColumn($column = 1) {
        if (is_bool($column)) {
            $column = $column ? 1 : null;
        }
        $this->fixedColumn = $column;

        return $this;
    }

    /**
     * @param mixed $fixedHeader
     *
     * @return \CElement_Component_DataTable
     */
    public function setFixedHeader($fixedHeader = true) {
        $this->fixedHeader = $fixedHeader;

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

    /**
     * @param object $data
     *
     * @return void
     */
    public static function actionDownloadExcel($data) {
        $table = $data->table;
        $table = unserialize($table);
        $export_filename = $table->export_filename;
        if (substr($table->export_filename, 3) != 'xls') {
            $export_filename .= '.xls';
        }
        self::exportExcelxmlStatic($export_filename, $table->export_sheetname, $table);
    }

    /**
     * @param string $dom
     *
     * @return $this
     */
    public function setDom($dom) {
        $this->dom = $dom;

        return $this;
    }

    /**
     * @param string $html
     *
     * @return $this
     */
    public function setCustomColumnHeader($html) {
        $this->customColumnHeader = $html;

        return $this;
    }

    /**
     * @param bool $bool
     *
     * @return $this
     */
    public function setResponsive($bool = true) {
        $this->responsive = $bool;

        return $this;
    }

    /**
     * @param bool $bool
     *
     * @return $this
     */
    public function setShowHeader($bool) {
        $this->show_header = $bool;

        return $this;
    }

    /**
     * @param string $id
     *
     * @return $this
     */
    public function setTbodyId($id) {
        $this->tbodyId = $id;

        return $this;
    }

    /**
     * @param bool $bool
     *
     * @return $this
     */
    public function setHeaderNoLineBreak($bool) {
        $this->headerNoLineBreak = $bool;

        return $this;
    }

    /**
     * @param string $key
     * @param mixed  $val
     *
     * @return $this
     */
    public function setOption($key, $val) {
        $this->options->setOption($key, $val);

        return $this;
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function getOption($key) {
        return $this->options->getOption($key);
    }

    /**
     * @param bool $bool
     *
     * @return $this
     */
    public function setAjax($bool = true) {
        $this->ajax = $bool;
        $this->requery();

        return $this;
    }

    /**
     * @param string $value
     *
     * @return $this
     */
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
        $this->applyDataTable = (bool) $bool;
        if ($this->applyDataTable === false) {
            $this->setAjax(false);
        }

        return $this;
    }

    /**
     * @param string|int $length
     *
     * @return $this
     */
    public function setDisplayLength($length) {
        $this->displayLength = $length;

        return $this;
    }

    /**
     * Set callback for table cell render.
     *
     * @param callable|Closure $func    parameter: $table,$col,$row,$value
     * @param string           $require File location of callable function to require
     *
     * @return $this
     */
    public function cellCallbackFunc($func, $require = '') {
        $this->cellCallbackFunc = c::toSerializableClosure($func);
        if (strlen($require) > 0) {
            $this->requires[] = $require;
        }

        return $this;
    }

    /**
     * @param callable|Closure $func
     * @param string           $require
     *
     * @return $this
     */
    public function filterActionCallbackFunc($func, $require = '') {
        $this->filterActionCallbackFunc = $func;
        if (strlen($require) > 0) {
            $this->requires[] = $require;
        }

        return $this;
    }

    /**
     * @param string $fieldname
     *
     * @return $this
     */
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
        $this->headerSortable = $bool;

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

    /**
     * @param string $q
     *
     * @return $this
     */
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
        $this->query = CManager::createSqlDataProvider($q);

        $dbResolver = $this->dbResolver;
        $dbName = $this->dbName;
        $dbConfig = $this->dbConfig;
        $elementId = $this->id;

        $this->query->setConnection(function () use ($dbResolver, $dbName, $dbConfig, $elementId) {
            if ($dbResolver != null) {
                return $dbResolver->connection($dbName);
            }

            if (strlen($dbName) > 0) {
                return c::db($dbName);
            }

            $generatedDbName = 'db-datatable-' . $elementId;
            CDatabase::manager()->addConnection($dbConfig, $generatedDbName);

            return c::db($generatedDbName);
        });

        return $this;
    }

    /**
     * @param Closure    $closure
     * @param null|mixed $requires
     *
     * @return $this
     */
    public function setDataFromClosure($closure, $requires = null) {
        $this->query = CManager::createClosureDataProvider($closure, carr::wrap($requires));

        return $this;
    }

    /**
     * @param CModel_Query $q
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
     * @param CModel|CModel_Query|string $model
     * @param null|mixed                 $queryCallback
     *
     * @return $this
     */
    public function setDataFromModel($model, $queryCallback = null) {
        if (is_string($model)) {
            $this->query = CManager::createModelDataProvider($model, $queryCallback);

            return $this;
        }
        $modelQuery = $model;
        /** @phpstan-ignore-next-line */
        if ($modelQuery instanceof CModel_Collection) {
            throw new Exception('error when calling setDataFromModel, please use CModel/CModel_Query instance (CModel_Collection passed)');
        }
        $sql = $this->db()->compileBinds($modelQuery->toSql(), $modelQuery->getBindings());

        return $this->setDataFromQuery($sql);
    }

    /**
     * @param callable|Closure $callback
     * @param array            $callbackOptions
     * @param string           $require
     *
     * @return $this
     */
    public function setDataFromCallback($callback, $callbackOptions = [], $require = null) {
        $this->query = c::toSerializableClosure($callback);
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
     * @param CCollection $collection
     *
     * @return $this
     */
    public function setDataFromCollection(CCollection $collection) {
        $this->query = CManager::createCollectionDataProvider($collection);

        return $this;
    }

    /**
     * @return CDatabase_Connection
     */
    public function db() {
        if ($this->dbResolver != null) {
            return $this->dbResolver->connection($this->dbName);
        }

        if (strlen($this->dbName) > 0) {
            return c::db($this->dbName);
        }
        $dbName = 'db-datatable-' . $this->id;
        CDatabase::manager()->addConnection($this->dbConfig, $dbName);

        return c::db($dbName);
    }

    /**
     * @return string
     */
    public function getKeyField() {
        return $this->keyField;
    }

    /**
     * @return null|string
     */
    public function getDomain() {
        return $this->domain;
    }

    /**
     * @return CElement_Component_DataTable_Column[]
     */
    public function getColumns() {
        return $this->columns;
    }

    /**
     * Get columns that are visible.
     *
     * @return CElement_Component_DataTable_Column[]
     */
    public function getVisibleColumns() {
        return c::collect($this->columns)->filter(function (CElement_Component_DataTable_Column $column) {
            return $column->isVisible();
        })->toArray();
    }

    /**
     * @param mixed $index
     *
     * @return null|CElement_Component_DataTable_Column
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
     * @return mixed
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
     * @param string $class
     *
     * @return CExporter_Exportable_DataTable
     */
    public function toExportable($class = CExporter_Exportable_DataTable::class) {
        $table = clone $this;
        $table->prepareForExportable();

        return new $class($table);
    }

    /**
     * @return $this
     */
    public function prepareForExportable() {
        $this->parent = null;
        $this->wrapper = null;
        $this->rowActionList = null;
        $this->headerActionList = null;
        $this->footerActionList = null;
        $this->options = null;
        $this->data = null;

        return $this;
    }

    /**
     * @return CElement_Component_DataTable
     */
    public function getForAjaxSerialization() {
        $table = clone $this;
        $table->prepareForAjaxSerialization();

        return $table;
    }

    /**
     * @return $this
     */
    public function prepareForAjaxSerialization() {
        $this->parent = null;
        $this->wrapper = null;

        return $this;
    }

    /**
     * @return CCollection
     */
    public function getCollection() {
        $data = [];
        if ($this->isUsingDataProvider()) {
            /** @var CManager_Contract_DataProviderInterface $dataProvider */
            $dataProvider = $this->query;

            return $dataProvider->toEnumerable();
        }
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

    /**
     * @param null|string $filename
     *
     * @return mixed
     */
    public function downloadExcel($filename = null) {
        if ($filename == null) {
            $filename = CExporter::randomFilename();
        }

        return CExporter::download($this->toExportable(), $filename);
    }

    /**
     * @param string      $filePath
     * @param null|string $disk
     * @param null|string $writerType
     * @param array       $diskOptions
     *
     * @return mixed
     */
    public function queueDownloadExcel($filePath, $disk = null, $writerType = null, $diskOptions = []) {
        return CExporter::queue($this->toExportable(), $filePath, $disk, $writerType, $diskOptions);
    }

    /**
     * @return CElement_List_ActionList
     */
    public function getHeaderActionList() {
        if ($this->headerActionList == null) {
            $this->headerActionList = new CElement_List_ActionList();
            $this->headerActionList->setStyle('widget-action');
        }

        return $this->headerActionList;
    }

    /**
     * @return void
     */
    protected function build() {
        if ($this->footerActionList != null) {
            $this->footerActionList->setStyle('btn-list');
        }

        if ($this->haveRowAction()) {
            $this->getRowActionList()->addClass('btn-table-action capp-table-action');
        }
        if ($this->ajax == false) {
            if (is_string($this->query) && $this->query) {
                $r = $this->db()->query($this->query);
                $this->data = $r->result(false);
            }
        }
        if ($this->colReorder) {
            CManager::instance()->registerModule('jquery.datatable.colreorder');
        }
        if ($this->responsive) {
            CManager::instance()->registerModule('jquery.datatable.responsive');

            // if (CManager::isRegisteredModule('bootstrap-4') || CManager::isRegisteredModule('bootstrap-4-material')) {
            //     CManager::instance()->registerModule('jquery.datatable.responsive.bs4');
            // }
        }
    }

    /**
     * @return bool
     */
    public function isUsingDataProvider() {
        return $this->query instanceof CManager_Contract_DataProviderInterface;
    }

    /**
     * @return null|CManager_Contract_DataProviderInterface
     */
    public function getDataProvider() {
        if ($this->isUsingDataProvider()) {
            return $this->query;
        }

        return null;
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return $this
     */
    public function setDomElement($key, $value) {
        if ($value instanceof Closure) {
            $value = c::toSerializableClosure($value);
        }
        $this->domElements[$key] = $value;

        return $this;
    }

    /**
     * Set callback for table row class.
     *
     * @param callable|Closure $callback parameter: $row
     * @param string           $require  File location of callable function to require
     *
     * @return $this
     */
    public function setRowClassCallback($callback, $require = '') {
        $this->rowClassCallbackFunction = c::toSerializableClosure($callback);
        if (strlen($require) > 0) {
            $this->requires[] = $require;
        }

        return $this;
    }

    /**
     * @return null|Closure|CFunction_SerializableClosure
     */
    public function getRowClassCallbackFunction() {
        return $this->rowClassCallbackFunction;
    }

    /**
     * @param int $interval interval in seconds
     *
     * @return $this
     */
    public function setAutoRefresh($interval = 5) {
        if (!$interval) {
            $this->autoRefreshInterval = 0;
        } else {
            $this->autoRefreshInterval = $interval;
        }

        return $this;
    }
}
