<?php

class CElement_Component_DataTable_TaskQueue_AfterExportProgress extends CQueue_AbstractTask {
    /**
     * @var array
     */
    protected $params;

    /**
     * @param array $params
     *
     * @return void
     */
    public function __construct($params) {
        $this->params = $params;
    }

    /**
     * @return void
     */
    public function execute() {
        $params = $this->params;

        $downloadId = carr::get($params, 'downloadId');
        // Set by the request that queued this export (CF::appCode() there) - this task can run in
        // a queue worker whose own appCode differs (e.g. it serves several apps), so the CAjax
        // progress blob has to be read/written under the export's origin app, not the worker's.
        $appCode = carr::get($params, 'appCode');

        $run = function () use ($downloadId) {
            $data = CAjax::getData($downloadId);

            //check file exists

            $filename = carr::get($data, 'data.exporter.filename');

            $disk = CStorage::instance()->disk(carr::get($data, 'data.exporter.disk'));
            $isReady = $disk->exists($filename);

            if (carr::get($data, 'data.state') === CExporter_DownloadProgress::STATE_CANCELED) {
                // canceled after the rows were already appended: the file got stored anyway, drop it and keep CANCELED
                if ($isReady) {
                    $disk->delete($filename);
                }
                $this->logDaemon('AfterExportProgress | canceled, downloadId:' . $downloadId);

                return;
            }

            if ($isReady) {
                $data['data']['progressValue'] = carr::get($data, 'data.progressMax');
                $data['data']['state'] = 'DONE';

                CAjax::setData($downloadId, $data);
            } else {
                $this->logDaemon('AfterExportProgress | warning not ready on disk:' . carr::get($data, 'data.exporter.disk') . ' with filename:' . $filename);
            }

            $this->logDaemon('AfterExportProgress | downloadId:' . $downloadId);
        };

        if ($appCode) {
            CF::asAppCode($appCode, $run);
        } else {
            $run();
        }
    }
}
