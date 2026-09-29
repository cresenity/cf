<?php

class CExporter_Exportable_DataTableTemp extends CExporter_Exportable_DataTable {
    protected $file;

    protected $downloadId;

    /**
     * CF::appCode() at construction time, so a queue worker with a different ambient appCode
     * can still read/write this export's CAjax blob under the app that actually created it.
     *
     * @var null|string
     */
    protected $appCode;

    public function __construct($file) {
        $this->file = $file;
        $this->appCode = CF::appCode();
        $data = CAjax::getData($this->file);

        $table = unserialize(carr::get($data, 'data.table'));
        $this->table = $table;
        $this->columnFormats = [];
    }

    public function setDownloadId($downloadId) {
        $this->downloadId = $downloadId;

        return $this;
    }

    public function getDownloadId() {
        return $this->downloadId;
    }

    /**
     * @return null|string
     */
    public function getAppCode() {
        return $this->appCode;
    }

    /**
     * Run a callback with CF::appCode() forced back to the app that queued this export, when known.
     *
     * @param callable $callback
     *
     * @return mixed
     */
    public function runAsOriginAppCode(callable $callback) {
        if ($this->appCode) {
            return CF::asAppCode($this->appCode, $callback);
        }

        return $callback();
    }

    /**
     * Called by the export jobs (CExporter_Trait_ProxyFailures) when one of them fails, so the poller sees FAILED instead of PENDING forever.
     *
     * @param Throwable $e
     *
     * @return void
     */
    public function failed(Throwable $e) {
        if ($this->downloadId) {
            $this->runAsOriginAppCode(function () use ($e) {
                CExporter_DownloadProgress::find($this->downloadId)->fail($e->getMessage());
            });
        }
    }
}
