<?php

use PhpOffice\PhpSpreadsheet\IOFactory;

class CExporter {
    use CExporter_Trait_RegistersCustomConcernsTrait;

    const ACTION_STORE = 'store';

    const ACTION_DOWNLOAD = 'download';

    const XLSX = 'Xlsx';

    const CSV = 'Csv';

    const TSV = 'Csv';

    const ODS = 'Ods';

    const XLS = 'Xls';

    const SLK = 'Slk';

    const XML = 'Xml';

    const GNUMERIC = 'Gnumeric';

    const HTML = 'Html';

    const MPDF = 'Mpdf';

    const DOMPDF = 'Dompdf';

    const TCPDF = 'Tcpdf';

    const SNAPPYPDF = 'Snappypdf';

    const SNAPPYIMAGE = 'Snappyimage';

    const IS_QUEUE = false;

    const DATA_TYPE_STRING = 'string';

    const DATA_TYPE_DATETIME = 'datetime';

    const DATA_TYPE_DATE = 'date';

    const DATA_TYPE_CURRENCY = 'currency';

    const DATA_TYPE_INTEGER = 'integer';

    const DATA_TYPE_NUMBER = 'number';

    const DATA_TYPE_PERCENTAGE = 'percentage';

    const DATA_TYPE_PERCENTAGE_0 = 'percentage0';

    const DATA_TYPE_PERCENTAGE_00 = 'percentage00';

    protected static $externalWriter = [
        self::SNAPPYPDF => CExporter_PhpSpreadsheet_SnappyPdf::class,
        self::SNAPPYIMAGE => CExporter_PhpSpreadsheet_SnappyImage::class,
    ];

    public static function isExternalWriter($writerType) {
        return in_array($writerType, array_keys(self::$externalWriter));
    }

    public static function registerWriter($writerType) {
        $writerClass = carr::get(self::$externalWriter, $writerType);

        return IOFactory::registerWriter($writerType, $writerClass);
    }

    /**
     * @param object $export
     * @param string $filePath
     * @param array  $options
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     *
     * @return bool|PendingDispatch
     */
    public static function store($export, $filePath, $options = []) {
        $diskName = carr::get($options, 'diskName');
        $writerType = carr::get($options, 'writerType');
        $queued = carr::get($options, 'queued', false);
        $diskOptions = carr::get($options, 'diskOptions', []);

        $export = CExporter_ExportableDetector::toExportable($export);

        if ($queued) {
            return static::queue($export, $filePath, $diskName, $writerType, $diskOptions);
        }

        $temporaryFile = static::export($export, $filePath, $writerType);

        $exported = static::storage()->disk($diskName, $diskOptions)->copy(
            $temporaryFile,
            $filePath
        );

        $temporaryFile->delete();

        return $exported;
    }

    /**
     * @param object      $export
     * @param null|string $fileName
     * @param string      $writerType
     * @param array       $headers
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     *
     * @deprecated 1.6 use download which return response object
     *
     * @return void
     */
    public static function forceDownload($export, $fileName, $writerType = null, array $headers = []) {
        $localPath = static::export($export, $fileName, $writerType)->getLocalPath();
        cdownload::force($localPath, null, $fileName);
        unlink($localPath);
    }

    /**
     * @param object      $export
     * @param null|string $fileName
     * @param string      $writerType
     * @param array       $headers
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public static function download($export, $fileName, $writerType = null, array $headers = []) {
        return c::response()->download(
            static::export($export, $fileName, $writerType)->getLocalPath(),
            $fileName,
            $headers
        )->deleteFileAfterSend(true);
    }

    /**
     * @param object      $export
     * @param null|string $fileName
     * @param string      $writerType
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     *
     * @return CExporter_File_TemporaryFile
     */
    protected static function export($export, $fileName, $writerType = null) {
        $writerType = CExporter_FileTypeDetector::detectStrict($fileName, $writerType);

        $export = CExporter_ExportableDetector::toExportable($export);

        if (CExporter::isExternalWriter($writerType)) {
            CExporter::registerWriter($writerType);
        }

        return static::writer()->export($export, $writerType);
    }

    public static function config() {
        return CExporter_Config::instance();
    }

    /**
     * @return CExporter_Writer
     */
    public static function writer() {
        return CExporter_Writer::instance();
    }

    /**
     * @return CExporter_QueuedWriter
     */
    public static function queuedWriter() {
        return CExporter_QueuedWriter::instance();
    }

    /**
     * @return CExporter_Storage
     */
    public static function storage() {
        return CExporter_Storage::instance();
    }

    public static function generateExtension($writerType = self::XLSX) {
        switch ($writerType) {
            case static::XLSX:
                return 'xlsx';
            case static::XLS:
                return 'xls';
            case static::ODS:
                return 'ods';
            case static::XLS:
                return 'xls';
            case static::SLK:
                return 'slk';
            case static::XML:
                return 'xml';
            case static::GNUMERIC:
                return 'gnumeric';
            case static::HTML:
                return 'html';
            case static::CSV:
                return 'csv';
            case static::TSV:
                return 'tsv';
            case static::MPDF:
            case static::TCPDF:
            case static::DOMPDF:
            case static::SNAPPYPDF:
                return 'pdf';
            case static::SNAPPYIMAGE:
                return 'jpg';
        }

        return 'xlsx';
    }

    public static function randomFilename($writerType = self::XLS) {
        return 'export-' . cstr::random(32) . '.' . static::generateExtension($writerType);
    }

    /**
     * @param object $export
     * @param string $writerType
     *
     * @return string
     */
    public static function raw($export, $writerType) {
        $temporaryFile = static::writer()->export($export, $writerType);

        $contents = $temporaryFile->contents();
        $temporaryFile->delete();

        return $contents;
    }

    /**
     * @param object      $export
     * @param string      $filePath
     * @param null|string $disk
     * @param string      $writerType
     * @param mixed       $diskOptions
     *
     * @return CQueue_PendingDispatch
     */
    public static function queue($export, $filePath, $disk = null, $writerType = null, $diskOptions = []) {
        $writerType = CExporter_FileTypeDetector::detectStrict($filePath, $writerType);
        $export = CExporter_ExportableDetector::toExportable($export);

        return static::queuedWriter()->store(
            $export,
            $filePath,
            $disk,
            $writerType,
            $diskOptions
        );
    }

    public static function queueAjax($ajaxMethod, $filePath, $disk = null, $writerType = null, $diskOptions = []) {
        $file = CAjax::temporaryFile($ajaxMethod);
        $disk = CTemporary::disk();
        if (!$disk->exists($file)) {
            throw new Exception(c::__('failed to get temporary file :filename', ['filename' => $file]));
        }
        $json = $disk->get($file);

        $data = json_decode($json, true);

        $args = [];
        $ajaxMethod = CAjax::createMethod($json)->setArgs($args);
        $response = $ajaxMethod->executeEngine();

        $writerType = CExporter_FileTypeDetector::detectStrict($filePath, $writerType);
        $export = CExporter_ExportableDetector::toExportable($data);

        return static::queuedWriter()->store(
            $export,
            $filePath,
            $disk,
            $writerType,
            $diskOptions
        );
    }

    public static function makePath($folder, $filename) {
        $depth = 5;
        $path = self::getDirectory();
        $path = self::makefolder($path, $folder);
        $basefile = basename($filename);
        for ($i = 0; $i < $depth; $i++) {
            $c = '_';
            if (strlen($basefile) > ($i + 1)) {
                $c = substr($basefile, $i, 1);
                if (strlen($c) == 0) {
                    $c = '_';
                }
                $path = self::makefolder($path, $c);
            }
        }

        return $path . $filename;
    }

    public static function getDirectory() {
        $path = DOCROOT . 'export' . DIRECTORY_SEPARATOR;
        if (!is_dir($path)) {
            mkdir($path);
        }

        return $path;
    }

    public static function makefolder($path, $folder) {
        $path = $path . $folder . DIRECTORY_SEPARATOR;
        if (!is_dir($path)) {
            mkdir($path);
        }

        return $path;
    }

    /**
     * CExporter_Transaction_TransactionManager.
     *
     * @return CExporter_Transaction_TransactionManager
     */
    public static function transactionManager() {
        return CExporter_Transaction_TransactionManager::instance();
    }

    /**
     * @return PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder
     */
    public static function defaultValueBinder() {
        $defaultValueBinderClass = static::config()->get('value_binder.default', CExporter_DefaultValueBinder::class);

        return new $defaultValueBinderClass();
    }

    /**
     * @return CExporter_Import_ModelManager
     */
    public static function modelManager() {
        return CExporter_Import_ModelManager::instance();
    }

    /**
     * @return CExporter_Import_ModelImporter
     */
    public static function modelImporter() {
        return CExporter_Import_ModelImporter::instance();
    }

    public static function dispatcher() {
        return CEvent::dispatcher();
    }
}
