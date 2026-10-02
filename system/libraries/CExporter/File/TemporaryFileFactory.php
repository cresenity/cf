<?php

class CExporter_File_TemporaryFileFactory {
    /**
     * @var CExporter_File_TemporaryFileFactory
     */
    private static $instance;

    /**
     * @var null|string
     */
    private $temporaryPath;

    /**
     * @var null|string
     */
    private $temporaryDisk;

    public static function instance() {
        if (static::$instance == null) {
            static::$instance = new CExporter_File_TemporaryFileFactory();
        }

        return static::$instance;
    }

    private function __construct() {
        $this->temporaryPath = CExporter::config()->get('temporary.path', DOCROOT . 'temp');
        $this->temporaryDisk = CExporter::config()->get('temporary.disk', 'local');
    }

    /**
     * @param null|string $fileExtension
     *
     * @return CExporter_File_TemporaryFile
     */
    public function make($fileExtension = null) {
        if (null !== $this->temporaryDisk) {
            return $this->makeRemote();
        }

        return $this->makeLocal(null, $fileExtension);
    }

    /**
     * @param null|string $fileName
     * @param null|string $fileExtension
     *
     * @return CExporter_File_LocalTemporaryFile
     */
    public function makeLocal($fileName = null, $fileExtension = null) {
        $temporaryPath = CExporter::config()->get('temporary.local_path', DOCROOT . 'temp');
        //temp/exporter/<appCode>/<Ymd>; tanpa app berjalan memakai `common`, seperti CTemporary
        $temporaryPath .= DS . CTemporary::appFolder('exporter');
        $temporaryPath .= DS . date('Ymd');
        if (!CFile::isDirectory($temporaryPath)) {
            CFile::makeDirectory($temporaryPath, 0755, true);
        }
        if (!CFile::isDirectory($temporaryPath)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $temporaryPath));
        }

        return new CExporter_File_LocalTemporaryFile(
            $temporaryPath . DIRECTORY_SEPARATOR . ($fileName ?: $this->generateFilename($fileExtension))
        );
    }

    /**
     * @return CExporter_File_RemoteTemporaryFile
     */
    private function makeRemote() {
        $filename = $this->generateFilename();

        return new CExporter_File_RemoteTemporaryFile(
            $this->temporaryDisk,
            CExporter::config()->get('temporary.remote_prefix') . $filename,
            $this->makeLocal($filename)
        );
    }

    /**
     * @param null|string $fileExtension
     *
     * @return string
     */
    private function generateFilename($fileExtension = null) {
        return 'capp-exporter-' . cstr::random(32) . ($fileExtension ? '.' . $fileExtension : '');
    }
}
