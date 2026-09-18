<?php

/**
 * Description of CompilerAbstract.
 */
abstract class CView_CompilerAbstract {
    /**
     * Get the cache path for the compiled views.
     *
     * @var string
     */
    protected $cachePath;

    /**
     * Create a new compiler instance.
     *
     * @throws \InvalidArgumentException
     *
     * @return void
     */
    public function __construct() {
        $this->cachePath = CView_Factory::compiledPath();

        if (!CFile::isDirectory($this->cachePath)) {
            CFile::makeDirectory($this->cachePath, $mode = 0755, $recursive = true);
        }
    }

    /**
     * Get the path to the compiled version of a view.
     *
     * @param string $path
     *
     * @return string
     */
    public function getCompiledPath($path) {
        return $this->cachePath . '/' . sha1($path) . '.php';
    }

    /**
     * Determine if the view at the given path is expired.
     *
     * @param string $path
     *
     * @return bool
     */
    public function isExpired($path) {
        $compiled = $this->getCompiledPath($path);

        // If the compiled file doesn't exist we will indicate that the view is expired
        // so that it can be re-compiled. Else, we will verify the last modification
        // of the views is less than the modification times of the compiled views.
        if (!CFile::exists($compiled)) {
            return true;
        }

        return CFile::lastModified($path) >= CFile::lastModified($compiled);
    }

    /**
     * Create the compiled file directory if necessary.
     *
     * @param string $path
     *
     * @return void
     */
    protected function ensureCompiledDirectoryExists($path) {
        if (!CFile::exists(dirname($path))) {
            CFile::makeDirectory(dirname($path), 0777, true, true);
        }
    }
}
