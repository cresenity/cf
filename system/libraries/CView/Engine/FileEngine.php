<?php

/**
 * Description of FileEngine.
 */
class CView_Engine_FileEngine extends CView_EngineAbstract {
    /**
     * Create a new file engine instance.
     *
     * @return void
     */
    public function __construct() {
    }

    /**
     * Get the evaluated contents of the view.
     *
     * @param string $path
     * @param array  $data
     *
     * @return string
     */
    public function get($path, array $data = []) {
        return CFile::get($path);
    }
}
