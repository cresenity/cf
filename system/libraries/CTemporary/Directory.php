<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @see CTemporary
 */
class CTemporary_Directory {
    use CTemporary_Trait_FilePathTrait;

    protected $path;

    /**
     * `temp/<folder>/<appCode>/<rest>`: the app code sits right after the first segment of the given path.
     *
     * @param string $path
     */
    public function __construct($path) {
        $segments = explode(DS, trim($path, DS));
        $folder = CTemporary::appFolder(array_shift($segments));
        $this->path = 'temp' . DS . rtrim($folder . DS . implode(DS, $segments), DS);
        CFile::makeDirectory($this->getPath(), 0777, true, true);
    }

    protected function getBasePath() {
        return rtrim(DOCROOT, DS) . DS . rtrim($this->path, DS) . DS;
    }

    public function getPath($pathOrFilename = '') {
        if (empty($pathOrFilename)) {
            return $this->getBasePath();
        }

        $path = rtrim($this->getBasePath(), DS) . DS . trim($pathOrFilename, DS);
        $directoryPath = $this->removeFilenameFromPath($path);
        if (!file_exists($directoryPath)) {
            mkdir($directoryPath, 0777, true);
        }

        return $path;
    }

    public function getUrl() {
        return rtrim(curl::base(), '/') . '/' . rtrim($this->path, '/');
    }

    public function createFile($filename) {
        return new CTemporary_File($this, $filename);
    }

    public function delete() {
        CFile::deleteDirectory($this->getPath());
    }
}
