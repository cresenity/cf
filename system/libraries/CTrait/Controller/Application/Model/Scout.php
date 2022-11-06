<?php
use TeamTNT\TNTSearch\TNTSearch;
use TeamTNT\TNTSearch\Indexer\TNTIndexer;
use TeamTNT\TNTSearch\Exceptions\IndexNotFoundException;

trait CTrait_Controller_Application_Model_Scout {
    protected function getSearchableModels() {
        return [];
    }

    protected function getTitle() {
        return 'Scout Manager';
    }

    public function index() {
        $app = c::app();

        $app->setTitle($this->getTitle());

        $searchableModels = $this->getSearchableModels();
        $tableData = [];
        foreach ($searchableModels as $class) {
            $model = new $class();
            $tnt = $this->loadTNTEngine($model);
            $indexName = $model->searchableAs() . '.index';
            try {
                $tnt->selectIndex($indexName);
                $rowsIndexed = $tnt->totalDocumentsInCollection();
            } catch (IndexNotFoundException $e) {
                $rowsIndexed = 0;
            }

            $rowsTotal = $model->count();
            $recordsDifference = $rowsTotal - $rowsIndexed;

            $indexedColumns = $rowsTotal ? implode(',', array_keys($model->first()->toSearchableArray())) : '';

            if ($recordsDifference == 0) {
                $recordsDifference = '<fg=green>Synchronized</>';
            } else {
                $recordsDifference = "<fg=red>${recordsDifference}</>";
            }
            $tableData[] = [
                'searchable' => $class,
                'index' => $indexName,
                'columns' => $indexedColumns,
                'rows_indexed' => $rowsIndexed,
                'rows_total' => $rowsTotal,
                'difference' => $recordsDifference,

            ];
        }

        $table = $app->addTable();
        $table->setDataFromArray($tableData);
        $table->setAjax(false);
        $table->setApplyDataTable(false);
        $table->addColumn('searchable')->setLabel('Searchable');
        $table->addColumn('index')->setLabel('Index');
        $table->addColumn('columns')->setLabel('Indexed Columns')->customCss('word-break', 'break-all');
        $table->addColumn('rows_indexed')->setLabel('Indexed Records');
        $table->addColumn('rows_total')->setLabel('DB Records');
        $table->addColumn('difference')->setLabel('Records Difference');

        return $app;
    }

    /**
     * @param $model
     *
     * @return TNTSearch
     */
    private function loadTNTEngine($model) {
        $scoutManager = c::container(CModel_Scout_EngineManager::class);
        /** @var CModel_Scout_EngineManager $scoutManager */
        return $scoutManager->createTntsearchEngine();
    }
}
