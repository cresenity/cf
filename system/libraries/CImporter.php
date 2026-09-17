<?php

class CImporter {
    use CExporter_Trait_RegistersCustomConcernsTrait;

    const ACTION_IMPORT = 'import';

    const ACTION_TO_ARRAY = 'toArray';

    const ACTION_TO_COLLECTION = 'toCollection';

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

    const IS_QUEUE = false;

    /**
     * @param object              $import
     * @param string|UploadedFile $filePath
     * @param null|string         $disk
     * @param null|string         $readerType
     *
     * @return CExporter_Reader|CQueue_PendingDispatch
     */
    public static function import($import, $filePath, string $disk = null, string $readerType = null) {
        $readerType = CExporter_FileTypeDetector::detectStrict($filePath, $readerType);
        $response = static::reader()->read($import, $filePath, $readerType, $disk);

        if ($response instanceof CQueue_PendingDispatch) {
            return $response;
        }

        return $response;
    }

    public static function config() {
        return CExporter_Config::instance();
    }

    /**
     * @return CExporter_Reader
     */
    public static function reader() {
        return CExporter_Reader::instance();
    }

    /**
     * @param object              $import
     * @param string|UploadedFile $filePath
     * @param null|string         $disk
     * @param null|string         $readerType
     *
     * @return array
     */
    public static function toArray($import, $filePath, string $disk = null, string $readerType = null) {
        $readerType = CExporter_FileTypeDetector::detectStrict($filePath, $readerType);

        return static::reader()->toArray($import, $filePath, $readerType, $disk);
    }

    /**
     * @param object              $import
     * @param string|UploadedFile $filePath
     * @param null|string         $disk
     * @param null|string         $readerType
     *
     * @return Collection
     */
    public static function toCollection($import, $filePath, string $disk = null, string $readerType = null) {
        $readerType = CExporter_FileTypeDetector::detectStrict($filePath, $readerType);

        return static::reader()->toCollection($import, $filePath, $readerType, $disk);
    }

    /**
     * @param ShouldQueue         $import
     * @param string|UploadedFile $filePath
     * @param null|string         $disk
     * @param string              $readerType
     *
     * @return PendingDispatch
     */
    public static function queueImport(ShouldQueue $import, $filePath, string $disk = null, string $readerType = null) {
        return static::import($import, $filePath, $disk, $readerType);
    }

    public static function queueAjax($ajaxMethod, $filePath, $disk = null, $writerType = null, $diskOptions = []) {
        $filename = $ajaxMethod . '.tmp';
        $file = CTemporary::getPath('ajax', $filename);
        $disk = CTemporary::disk();
        if (!$disk->exists($file)) {
            throw new Exception(c::__('failed to get temporary file :filename', [':filename' => $file]));
        }
        $json = $disk->get($file);

        $data = json_decode($json, true);

        $ajaxMethod = CAjax::createMethod($json)->setArgs($args);
        $response = $ajaxMethod->executeEngine();

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
}
