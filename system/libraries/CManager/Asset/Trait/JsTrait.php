<?php

defined('SYSPATH') or die('No direct access allowed.');

trait CManager_Asset_Trait_JsTrait {
    public function fullpathJsFile($file) {
        foreach ($this->mediaPaths as $dir) {
            $path = $dir . 'js' . DS . $file;
            if (file_exists($path)) {
                return $path;
            }
        }
        $dirs = CF::getDirs('media');
        $dirs = array_merge($this->mediaPaths, $dirs);
        foreach ($dirs as $dir) {
            $path = $dir . 'js' . DS . $file;

            if (file_exists($path)) {
                return $path;
            }
        }

        $path = DOCROOT . 'media' . DS . 'js' . DS;

        return $path . $file;
    }

    public function getAllJsFileUrl() {
        $files = $this->jsFiles();

        $urls = [];
        foreach ($files as $f) {
            if ($f instanceof CManager_Asset_FileAbstract) {
                $urls[] = $f->getUrl();
            } else {
                $urls[] = CManager_Asset_Helper::urlJsFile($f);
            }
        }

        return $urls;
    }

    public function registerJsFiles($files, $pos = 'end') {
        $files = $files !== null ? (is_array($files) ? $files : [$files]) : [];
        foreach ($files as $file) {
            $this->registerJsFile($file, $pos);
        }

        return $this;
    }

    /**
     * @param array|string $file
     * @param string       $pos
     *
     * @return CManager_Asset_Container
     */
    public function registerJsFile($file, $pos = CManager_Asset::POS_END) {
        $fileOptions = $file;

        if (!is_array($fileOptions)) {
            $fileOptions = [
                'script' => $file,
            ];
        }
        $fileOptions['type'] = 'js';
        $fileOptions['pos'] = $pos;
        $fileOptions['mediaPaths'] = $this->mediaPaths;

        $this->scripts[$pos]['js_file'][] = new CManager_Asset_File_JsFile($fileOptions);

        return $this;
    }

    public function unregisterJsFiles($files, $pos = null) {
        if (!is_array($files)) {
            $files = [$files];
        }
        foreach ($files as $file) {
            $this->unregisterJsFile($file, $pos);
        }
    }

    public function unregisterJsFile($file, $pos = null) {
        $fullpathFile = $this->fullpathJsFile($file);
        //we will locate all pos for this pos if pos =null;
        if ($pos == null) {
            $pos = CManager_Asset::allAvailablePos();
        }
        if (!is_array($pos)) {
            $pos = [$pos];
        }
        foreach ($pos as $p) {
            if (!isset($this->scripts[$p]['js_file'])) {
                continue;
            }
            $jsFiles = &$this->scripts[$p]['js_file'];
            foreach ($jsFiles as $k => $jsFile) {
                // registered files are CManager_Asset_File_* objects; match on the registered name or the resolved path
                $registered = $jsFile instanceof CManager_Asset_FileAbstract ? $jsFile->getScript() : $jsFile;
                if ($registered == $file || $registered == $fullpathFile || $jsFile == $fullpathFile) {
                    unset($jsFiles[$k]);
                }
            }
        }
    }

    public function jsFiles() {
        $js_file_array = [];
        foreach ($this->scripts as $script) {
            foreach ($script['js_file'] as $k) {
                $js_file_array[] = $k;
            }
        }

        return $js_file_array;
    }
}
