<?php

/**
 * Progress handle for a queued export the browser polls: the same CAjax temp-file blob and
 * export jobs behind CElement_Component_DataTable::createDownloadProgressAction(), usable
 * without a CElement button (API method, SPA, custom controller).
 */
class CExporter_DownloadProgress {
    const STATE_PENDING = 'PENDING';

    const STATE_DONE = 'DONE';

    const STATE_FAILED = 'FAILED';

    /**
     * @var string
     */
    protected $id;

    /**
     * @param string $id
     */
    protected function __construct($id) {
        $this->id = $id;
    }

    /**
     * Queue a DataTable export (chunked writer, per-chunk progress, DONE only once the file exists on disk).
     *
     * @param CElement_Component_DataTable $table
     * @param array                        $options filename (a bare name lands in export/<appCode>/<Ymd>/, a path is kept as is),
     *                                              disk (default local-temp), writerType (default from filename), queueConnection, queue, expiration
     *
     * @return static
     */
    public static function queueDataTable(CElement_Component_DataTable $table, array $options = []) {
        $writerType = carr::get($options, 'writerType');
        $filename = (string) carr::get($options, 'filename');
        if (strlen($filename) == 0) {
            $filename = CExporter::randomFilename($writerType ?: CExporter::XLSX);
        }
        if (basename($filename) === $filename) {
            $filename = static::defaultFolder() . '/' . $filename;
        }
        $writerType = CExporter_FileTypeDetector::detectStrict($filename, $writerType);
        $disk = carr::get($options, 'disk', 'local-temp');

        $exporterOptions = array_merge($options, [
            'action' => CExporter::ACTION_DOWNLOAD,
            'queued' => true,
            'filename' => $filename,
            'disk' => $disk,
            'writerType' => $writerType,
        ]);

        // Same blob shape createDownloadProgressAction() seeds, so DataTableTemp / AppendDataProviderToSheet / AfterExportProgress read it unchanged.
        $ajaxMethod = CAjax::createMethod();
        $ajaxMethod->setType(CAjax_Engine_DataTableExporter::class);
        $ajaxMethod->setData('table', serialize($table));
        $ajaxMethod->setData('exporter', $exporterOptions);
        $ajaxMethod->setData('progress', true);
        $ajaxMethod->setData('state', self::STATE_PENDING);
        $ajaxMethod->setData('progressValue', '0');
        $ajaxMethod->setData('progressMax', '100');
        $ajaxMethod->setData('message', null);
        $ajaxMethod->setData('writerType', $writerType);
        $ajaxMethod->setData('fileUrl', CStorage::instance()->disk($disk)->url($filename));
        $ajaxMethod->setExpiration(carr::get($options, 'expiration', c::now()->addDays(1)->getTimestamp()));
        $id = $ajaxMethod->store();

        $exportable = new CExporter_Exportable_DataTableTemp($id);
        $exportable->setDownloadId($id);

        $pending = CExporter::store($exportable, $filename, [
            'writerType' => $writerType,
            'queued' => true,
            'diskName' => $disk,
            'diskOptions' => ['ContentType' => 'application/octet-stream'],
        ]);
        $pending->chain([new CElement_Component_DataTable_TaskQueue_AfterExportProgress(['downloadId' => $id])]);
        $queueConnection = carr::get($options, 'queueConnection');
        if ($queueConnection) {
            $pending->allOnConnection($queueConnection);
        }
        $queueName = carr::get($options, 'queue');
        if ($queueName) {
            $pending->allOnQueue($queueName);
        }
        // CQueue_PendingDispatch dispatches when it goes out of scope
        unset($pending);

        return new static($id);
    }

    /**
     * Folder on the export disk for files queued without an explicit path: export/<appCode>/<Ymd>.
     *
     * @return string
     */
    public static function defaultFolder() {
        return 'export/' . CF::appCode() . '/' . date('Ymd');
    }

    /**
     * Start tracking a custom (non-DataTable) job that reports through update()/done()/fail() itself.
     *
     * @param array $extra extra keys stored alongside state/progress, e.g. ['exporter' => ['filename' => ..., 'disk' => ...]]
     *
     * @return static
     */
    public static function create(array $extra = []) {
        $ajaxMethod = CAjax::createMethod();
        $ajaxMethod->setData('progress', true);
        $ajaxMethod->setData('state', self::STATE_PENDING);
        $ajaxMethod->setData('progressValue', '0');
        $ajaxMethod->setData('progressMax', '100');
        $ajaxMethod->setData('message', null);
        foreach (carr::except($extra, ['expiration']) as $key => $value) {
            $ajaxMethod->setData($key, $value);
        }
        $ajaxMethod->setExpiration(carr::get($extra, 'expiration', c::now()->addDays(1)->getTimestamp()));

        return new static($ajaxMethod->store());
    }

    /**
     * @param string $id usually straight from the request, so it is checked before becoming part of a temp path
     *
     * @throws InvalidArgumentException
     *
     * @return static
     */
    public static function find($id) {
        $id = (string) $id;
        if ($id === '' || $id !== basename($id) || strpos($id, '..') !== false) {
            throw new InvalidArgumentException('Invalid download id');
        }

        return new static($id);
    }

    /**
     * @return string
     */
    public function getId() {
        return $this->id;
    }

    /**
     * The 'data' part of the blob; empty array when the blob is gone (expired/cleaned).
     *
     * @return array
     */
    public function getData() {
        try {
            $stored = CAjax::getData($this->id);
        } catch (Exception $e) {
            return [];
        }

        return carr::get($stored, 'data', []);
    }

    /**
     * @return bool
     */
    public function exists() {
        return count($this->getData()) > 0;
    }

    /**
     * @return string
     */
    public function getState() {
        return carr::get($this->getData(), 'state', self::STATE_PENDING);
    }

    /**
     * @return bool
     */
    public function isDone() {
        return $this->getState() === self::STATE_DONE;
    }

    /**
     * @return bool
     */
    public function isFailed() {
        return $this->getState() === self::STATE_FAILED;
    }

    /**
     * @param int|float $value
     * @param null|int  $max
     *
     * @return $this
     */
    public function update($value, $max = null) {
        return $this->write(function (array $data) use ($value, $max) {
            $data['progressValue'] = $value;
            if ($max !== null) {
                $data['progressMax'] = $max;
            }
            $data['state'] = self::STATE_PENDING;

            return $data;
        });
    }

    /**
     * @return $this
     */
    public function done() {
        return $this->write(function (array $data) {
            $data['state'] = self::STATE_DONE;
            $data['progressValue'] = carr::get($data, 'progressMax', 100);

            return $data;
        });
    }

    /**
     * @param null|string $message
     *
     * @return $this
     */
    public function fail($message = null) {
        return $this->write(function (array $data) use ($message) {
            $data['state'] = self::STATE_FAILED;
            $data['message'] = $message;

            return $data;
        });
    }

    /**
     * Shape the pollers expect (DownloadProgress.js and API clients): state, progressValue, progressMax, message, fileUrl, downloadId.
     *
     * @return array
     */
    public function toResponseData() {
        $data = $this->getData();
        $state = carr::get($data, 'state', self::STATE_PENDING);

        $fileUrl = null;
        if ($state === self::STATE_DONE) {
            $fileUrl = carr::get($data, 'fileUrl');
            if (!$fileUrl) {
                $disk = carr::get($data, 'exporter.disk');
                $filename = carr::get($data, 'exporter.filename');
                if ($disk && $filename) {
                    $fileUrl = CStorage::instance()->disk($disk)->url($filename);
                }
            }
        }

        return [
            'state' => $state,
            'progressValue' => (float) carr::get($data, 'progressValue', 0),
            'progressMax' => (float) carr::get($data, 'progressMax', 100),
            'message' => carr::get($data, 'message'),
            'fileUrl' => $fileUrl,
            'downloadId' => $this->id,
        ];
    }

    /**
     * Read-modify-write of the 'data' part, keeping the rest of the method blob (type, expiration, auth) intact.
     *
     * @param callable $mutator
     *
     * @return $this
     */
    protected function write(callable $mutator) {
        try {
            $stored = CAjax::getData($this->id);
        } catch (Exception $e) {
            $stored = ['data' => []];
        }
        $stored['data'] = $mutator(carr::get($stored, 'data', []));
        CAjax::setData($this->id, $stored);

        return $this;
    }
}
