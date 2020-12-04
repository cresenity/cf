<?php

/**
 * Description of CompilerAbstract
 *
 * @author Hery
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
     * @param  string  $cachePath
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    public function __construct() {
        $this->cachePath = DOCROOT . 'temp' . DS . 'views' . DS . 'blade';
        if (!CFile::isDirectory($this->cachePath)) {
            CFile::makeDirectory($this->cachePath, $mode = 0755, $recursive = true);
        }
    }

    /**
     * Get the path to the compiled version of a view.
     *
     * @param  string  $path
     * @return string
     */
    public function getCompiledPath($path) {
        return $this->cachePath . '/' . sha1($path) . '.php';
    }

    /**
     * Determine if the view at the given path is expired.
     *
     * @param  string  $path
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

}
