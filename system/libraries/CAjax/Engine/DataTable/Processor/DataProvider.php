<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @see CManager_DataProvider_SqlDataProvider
 * @see CManager_DataProvider_ModelDataProvider
 * @see CManager_DataProvider_ClosureDataProvider
 * @see CManager_DataProvider_CollectionDataProvider
 */
class CAjax_Engine_DataTable_Processor_DataProvider extends CAjax_Engine_DataTable_Processor {
    use CAjax_Engine_DataTable_Trait_ProcessorTrait;

    public function process() {
        $request = $this->input;
        $table = $this->table();
        $query = $table->getQuery();

        /** @var CManager_Contract_DataProviderInterface $query */
        $query->searchOr($this->getSearchDataOr());
        $query->searchAnd($this->getSearchDataAnd());
        $query->sort($this->getSortData());

        $pageSize = $this->parameter->pageSize();
        $collections = null;
        $totalItem = 0;
        $totalFilteredItem = 0;

        if ($pageSize && $pageSize != '-1') {
            $paginationResult = $query->paginate($this->parameter->pageSize(), ['*'], 'page', $this->parameter->page());
            $collections = $paginationResult->items();
            $totalItem = $paginationResult->total();
            $totalFilteredItem = $totalItem;
        } else {
            $collections = $query->toEnumerable();
            $totalItem = $collections->count();
            $paginationResult = $query->paginate($totalItem, ['*'], 'page', $this->parameter->page());
            $collections = $paginationResult->items();
            $totalItem = $paginationResult->total();
            $totalFilteredItem = $totalItem;
        }

        $output = [
            'sEcho' => intval(carr::get($request, 'sEcho')),
            'iTotalRecords' => $totalItem,
            'iTotalDisplayRecords' => $totalFilteredItem,
            'aaData' => $this->populateAAData($collections, $this->table(), $request, $js),
        ];

        $data = [
            'datatable' => $output,
            'js' => base64_encode($js),
        ];

        return $data;
    }

    protected function getSearchDataOr() {
        $request = $this->engine->getInput();
        $table = $this->table;

        $searchData = [];
        $columns = $this->columns();

        if (isset($request['sSearch']) && $request['sSearch'] != '') {
            $table = $this->table();
            $columns = $table->getColumns();

            for ($i = 0; $i < count($columns); $i++) {
                $column = $columns[$i];
                $i2 = 0;
                if ($table->checkbox) {
                    $i2++;
                }
                if ($this->actionLocation() == 'first') {
                    $i2++;
                }
                $fieldName = $column->getFieldname();

                if (isset($request['bSearchable_' . ($i + $i2)]) && $request['bSearchable_' . ($i + $i2)] == 'true') {
                    if ($callback = $column->getSearchCallback()) {
                        $table = $this->table();
                        $query = $table->getQuery();
                        if (!($query instanceof CManager_DataProvider_ModelDataProvider)) {
                            throw new Exception('SearchCallback only running on ModelDataProvider');
                        }
                        $keyword = $request['sSearch'];
                        $searchData[$fieldName] = $this->createSearchCallable($callback, $keyword);
                    } else {
                        $searchData[$fieldName] = $request['sSearch'];
                    }
                }
            }
        }

        return $searchData;
    }

    protected function createSortCallable($callback, $direction) {
        return function ($q) use ($direction, $callback) {
            $args = [$q, $direction];
            if (is_callable($callback)) {
                return call_user_func_array($callback, $args);
            }
            if ($callback instanceof \Opis\Closure\SerializableClosure) {
                return $callback->__invoke(...$args);
            }
            if ($callback instanceof \CFunction_SerializableClosure) {
                return $callback->__invoke(...$args);
            }

            throw new Exception('callback is not callable on ' . __CLASS__);
        };
    }

    protected function createSearchCallable($callback, $keyword) {
        return function ($q) use ($keyword, $callback) {
            $args = [$q, $keyword];
            if (is_callable($callback)) {
                return call_user_func_array($callback, $args);
            }
            if ($callback instanceof \Opis\Closure\SerializableClosure) {
                return $callback->__invoke(...$args);
            }
            if ($callback instanceof \CFunction_SerializableClosure) {
                return $callback->__invoke(...$args);
            }

            throw new Exception('callback is not callable on ' . __CLASS__);
        };
    }

    private function getColumnsByFieldName($columns, $fieldName) {
        foreach ($columns as $column) {
            if ($column->getFieldname() == $fieldName) {
                return $column;
            }
        }

        return null;
    }

    protected function getSearchDataAnd() {
        $request = $this->engine->getInput();
        $table = $this->table;

        $searchData = [];
        $columns = $this->columns();

        // Quick Search
        $qsCondition = [];
        if (isset($request['dttable_quick_search'])) {
            $qsCondition = json_decode($request['dttable_quick_search'], true);
        }
        if (is_array($qsCondition) && count($qsCondition) > 0) {
            foreach ($qsCondition as $qsConditionKey => $qsConditionValue) {
                $value = $qsConditionValue['value'];
                $transforms = carr::get($qsConditionValue, 'transforms');
                if (strlen($transforms) > 0) {
                    $transforms = json_decode($transforms, true);
                    if (is_array($transforms)) {
                        foreach ($transforms as $transforms_k => $transforms_v) {
                            $value = CManager::transform()->call($transforms_v['func'], $value);
                        }
                    }
                }

                $fieldName = str_replace('dt_table_qs-', '', $qsConditionValue['name']);
                $column = $this->getColumnsByFieldName($columns, $fieldName);
                if ($column && ($callback = $column->getSearchCallback())) {
                    $table = $this->table();
                    $query = $table->getQuery();
                    if (!($query instanceof CManager_DataProvider_ModelDataProvider)) {
                        throw new Exception('SearchCallback only running on ModelDataProvider');
                    }
                    $searchData[$fieldName] = $this->createSearchCallable($callback, $value);
                } else {
                    $searchData[$fieldName] = $value;
                }
            }
        }

        return $searchData;
    }

    protected function getSortData() {
        $columns = $this->columns();
        $table = $this->table;
        $request = $this->engine->getInput();
        $sortData = [];
        if (isset($request['iSortCol_0'])) {
            for ($i = 0; $i < intval($request['iSortingCols']); $i++) {
                $i2 = 0;
                if ($table->checkbox) {
                    $i2++;
                }
                if ($this->actionLocation() == 'first' && $this->haveRowAction()) {
                    $i2++;
                }
                if (carr::get($request, 'bSortable_' . intval($request['iSortCol_' . $i]), 'true') == 'true') {
                    $column = carr::get($columns, intval($request['iSortCol_' . $i]) - $i2);
                    $sortDirection = $request['sSortDir_' . $i];
                    if ($column) {
                        /** @var CElement_Component_DataTable_Column $column */
                        $fieldName = $column->getFieldname();
                        if ($callback = $column->getSortCallback()) {
                            $table = $this->table();
                            $query = $table->getQuery();
                            if (!($query instanceof CManager_DataProvider_ModelDataProvider)) {
                                throw new Exception('SortCallback only running on ModelDataProvider');
                            }
                            $sortData[$fieldName] = $this->createSortCallable($callback, $sortDirection);
                        } else {
                            $sortData[$fieldName] = $sortDirection;
                        }
                    }
                }
            }
        }

        return $sortData;
    }

    protected function applyDataProvider(CManager_Contract_DataProviderInterface $dataProvider) {
        $request = $this->engine->getInput();
        $table = $this->table;

        $columns = $this->columns();
        $searchDataAnd = $this->getSearchDataAnd();
        $searchDataOr = $this->getSearchDataOr();
        $sortData = $this->getSortData();
        $dataProvider->searchAnd($searchDataAnd);
        $dataProvider->searchOr($searchDataOr);
        $dataProvider->sort($sortData);
    }
}
