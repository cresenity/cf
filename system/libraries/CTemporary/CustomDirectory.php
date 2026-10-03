<?php

class CTemporary_CustomDirectory {
    use CTemporary_Trait_FilePathTrait;

    /**
     * @var string
     */
    protected $location;

    /**
     * @var string
     */
    protected $name = '';

    /**
     * @var bool
     */
    protected $forceCreate = false;

    /**
     * @param string $location
     */
    public function __construct($location = '') {
        $this->location = $this->sanitizePath($location);
    }

    /**
     * @param string $location
     *
     * @return self
     */
    public static function make($location = '') {
        return (new self($location))->create();
    }

    /**
     * @return self
     */
    public function create() {
        if (empty($this->location)) {
            $this->location = $this->getSystemTemporaryDirectory();
        }

        if (empty($this->name)) {
            $this->name = mt_rand() . '-' . str_replace([' ', '.'], '', microtime());
        }

        if ($this->forceCreate && file_exists($this->getFullPath())) {
            $this->deleteDirectory($this->getFullPath());
        }

        if (file_exists($this->getFullPath())) {
            throw CTemporary_Exception_PathAlreadyExistsException::create($this->getFullPath());
        }

        mkdir($this->getFullPath(), 0777, true);

        return $this;
    }

    /**
     * @return self
     */
    public function force() {
        $this->forceCreate = true;

        return $this;
    }

    /**
     * @param string $name
     *
     * @return self
     */
    public function name($name) {
        $this->name = $this->sanitizeName($name);

        return $this;
    }

    /**
     * @param string $location
     *
     * @return self
     */
    public function location($location) {
        $this->location = $this->sanitizePath($location);

        return $this;
    }

    /**
     * @param string $pathOrFilename
     *
     * @return string
     */
    public function path($pathOrFilename = '') {
        if (empty($pathOrFilename)) {
            return $this->getFullPath();
        }

        $path = $this->getFullPath() . DIRECTORY_SEPARATOR . trim($pathOrFilename, '/');

        $directoryPath = $this->removeFilenameFromPath($path);

        if (!file_exists($directoryPath)) {
            mkdir($directoryPath, 0777, true);
        }

        return $path;
    }

    /**
     * @return self
     */
    public function empty() {
        $this->deleteDirectory($this->getFullPath());

        mkdir($this->getFullPath(), 0777, true);

        return $this;
    }

    /**
     * @return bool
     */
    public function delete() {
        return $this->deleteDirectory($this->getFullPath());
    }

    /**
     * @return string
     */
    protected function getFullPath() {
        return $this->location . (!empty($this->name) ? DIRECTORY_SEPARATOR . $this->name : '');
    }

    /**
     * @param string $directoryName
     *
     * @return bool
     */
    protected function isValidDirectoryName($directoryName) {
        return strpbrk($directoryName, '\\/?%*:|"<>') === false;
    }

    /**
     * @return string
     */
    protected function getSystemTemporaryDirectory() {
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
    }

    /**
     * @param string $path
     *
     * @return string
     */
    protected function sanitizePath($path) {
        $path = rtrim($path);

        return rtrim($path, DIRECTORY_SEPARATOR);
    }

    /**
     * @param string $name
     *
     * @return string
     */
    protected function sanitizeName($name) {
        if (!$this->isValidDirectoryName($name)) {
            throw CTemporary_Exception_InvalidDirectoryNameException::create($name);
        }

        return trim($name);
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    protected function deleteDirectory($path) {
        if (is_link($path)) {
            return unlink($path);
        }

        if (!file_exists($path)) {
            return true;
        }

        if (!is_dir($path)) {
            return unlink($path);
        }

        foreach (new FilesystemIterator($path) as $item) {
            if (!$this->deleteDirectory($item)) {
                return false;
            }
        }

        /*
         * By forcing a php garbage collection cycle using gc_collect_cycles() we can ensure
         * that the rmdir does not fail due to files still being reserved in memory.
         */
        gc_collect_cycles();

        return rmdir($path);
    }
}
