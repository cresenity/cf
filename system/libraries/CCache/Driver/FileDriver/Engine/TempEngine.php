<?php

defined('SYSPATH') or die('No direct access allowed.');

class CCache_Driver_FileDriver_Engine_TempEngine extends CCache_Driver_FileDriver_EngineAbstract {
    protected $tempFiles;

    protected $directory;

    public function __construct($options) {
        parent::__construct($options);

        $this->directory = $this->getOption('directory', 'default');
        $this->tempFiles = [];
    }

    /**
     * @param string $key
     *
     * @return CTemporary_File
     */
    public function getTempFiles($key) {
        if (!isset($this->tempFiles[$key])) {
            $parts = array_slice(str_split($hash = sha1($key), 2), 0, 2);
            $path = 'cache/' . trim((string) $this->directory, '/') . '/' . implode('/', $parts) . '/' . $hash . '.cache';
            $this->tempFiles[$key] = CTemporary::createFile($path);
        }

        return $this->tempFiles[$key];
    }

    public function path($key) {
        return $this->getTempFiles($key)->getPath();
    }

    public function get($key, $lock = false) {
        return $this->getTempFiles($key)->get($lock);
    }

    public function put($key, $content, $lock = false) {
        return $this->getTempFiles($key)->put($content, $lock);
    }

    public function exists($key) {
        return $this->getTempFiles($key)->exists();
    }

    public function delete($key) {
        return $this->getTempFiles($key)->delete();
    }

    public function deleteDirectory() {
        $file = new CFile();
        $base = rtrim(DOCROOT, '/') . '/temp/';
        $directory = trim($this->directory, '/');
        // temp/cache/<appCode>/<directory> (where CTemporary_Directory writes) plus the shared
        // temp/cache/<directory> entries were written to before temp went per app
        $deleted = $file->deleteDirectory($base . CTemporary::appFolder('cache') . '/' . $directory);
        if (is_dir($base . 'cache/' . $directory)) {
            $deleted = $file->deleteDirectory($base . 'cache/' . $directory) || $deleted;
        }

        return $deleted;
    }
}
