<?php

class CExporter_TaskQueue_AppendDataProviderToSheet extends CQueue_AbstractTask {
    use CExporter_Trait_ProxyFailures;
    /**
     * @var CManager_Contract_DataProviderInterface
     */
    public $dataProvider = null;

    /**
     * @var CExporter_File_TemporaryFile
     */
    public $temporaryFile;

    /**
     * @var string
     */
    public $writerType;

    /**
     * @var int
     */
    public $sheetIndex;

    /**
     * @var object
     */
    public $sheetExport;

    /**
     * @var int
     */
    public $perPage;

    /**
     * @param object                                  $sheetExport
     * @param CExporter_File_TemporaryFile            $temporaryFile
     * @param string                                  $writerType
     * @param int                                     $sheetIndex
     * @param CManager_Contract_DataProviderInterface $dataProvider
     * @param int                                     $perPage
     */
    public function __construct($sheetExport, CExporter_File_TemporaryFile $temporaryFile, $writerType, $sheetIndex, CManager_Contract_DataProviderInterface $dataProvider, $perPage) {
        $this->sheetExport = $sheetExport;
        $this->dataProvider = $dataProvider;
        $this->temporaryFile = $temporaryFile;
        $this->writerType = $writerType;
        $this->sheetIndex = $sheetIndex;
        $this->perPage = $perPage;
    }

    /**
     * Get the middleware the job should be dispatched through.
     *
     * @return array
     */
    public function middleware() {
        return (method_exists($this->sheetExport, 'middleware')) ? $this->sheetExport->middleware() : [];
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public function execute() {
        ini_set('memory_limit', -1);
        $downloadId = null;
        if ($this->sheetExport instanceof CExporter_Exportable_DataTableTemp) {
            $downloadId = $this->sheetExport->getDownloadId();
        }

        CDaemon::log('CExporter_TaskQueue_AppendDataProviderToSheet started, Memory Limit:' . ini_get('memory_limit'));
        $perPage = $this->perPage;
        $page = 1;
        $writer = CExporter::writer();
        $writer = $writer->reopen($this->temporaryFile, $this->writerType);
        $sheet = $writer->getSheetByIndex($this->sheetIndex);
        CDaemon::log('start append row from data provider');
        //CDaemon::log(json_encode($this->data));
        $paginationResult = $this->dataProvider->paginate($perPage, ['*'], 'page', $page);
        CDaemon::log('append row from data provider page:' . $page . ', perPage:' . $perPage . ' with total data:' . $paginationResult->total());
        //CDaemon::log('row:' . json_encode($paginationResult->items()));
        $sheet->appendRows($paginationResult->items(), $this->sheetExport);
        CDaemon::log('Memory Usage:' . memory_get_usage());
        $total = $paginationResult->total();
        $offset = 0;
        $this->setProgress($downloadId, $offset, $total);
        unset($paginationResult);
        if ($perPage >= 0) {
            $offset += $perPage;
            while ($offset < $total) {
                $page++;
                $paginationResult = $this->dataProvider->paginate($perPage, ['*'], 'page', $page);
                CDaemon::log('append row from data provider page:' . $page . ', perPage:' . $perPage . ' with total data:' . $paginationResult->total());
                $sheet->appendRows($paginationResult->items(), $this->sheetExport);
                CDaemon::log('Memory Usage:' . memory_get_usage());
                $offset += $perPage;
                $this->setProgress($downloadId, $offset, $total);
                unset($paginationResult);
            }
        }

        CDaemon::log('end append row from data provider');

        CDaemon::log('write excel');
        $writer->write($this->sheetExport, $this->temporaryFile, $this->writerType);
        CDaemon::log('end write excel');
        CDaemon::log('Memory Usage:' . memory_get_usage());
        unset($writer, $this->sheetExport, $this->temporaryFile, $this->writerType);
    }

    protected function setProgress($downloadId, $offset, $total) {
        if ($downloadId) {
            $data = CAjax::getData($downloadId);

            $progressMax = carr::get($data, 'progressMax', 100);
            $progressValue = $total ? ($offset * $progressMax / $total) : 0;
            $data['data']['progressValue'] = $progressValue;
            // Reaching progressMax here only means every row has been appended to the
            // in-memory sheet - the file still has to be closed and copied to its final
            // path by the jobs chained after this one. Leave 'state' as PENDING even at
            // 100%; only CElement_Component_DataTable_TaskQueue_AfterExportProgress (which
            // verifies the file actually exists at its destination) may set it to DONE,
            // otherwise a poller can be shown a download link before the file is there.
            $data['data']['state'] = 'PENDING';

            CAjax::setData($downloadId, $data);
            CDaemon::log('set progress to ' . $progressValue);
        }
    }
}
