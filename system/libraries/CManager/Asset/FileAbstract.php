<?php

abstract class CManager_Asset_FileAbstract {
    protected $script;

    protected $type;

    /**
     * @var string
     */
    protected $pos;

    /**
     * @var array
     */
    protected $mediaPaths = [];

    /**
     * @var bool
     */
    protected $isRemote = false;

    protected $compiled = false;

    protected $compiledFrom;

    public function __construct(array $options) {
        $this->script = carr::get($options, 'script');
        $this->type = carr::get($options, 'type', 'js');
        $this->compiled = carr::get($options, 'compiled', false);
        $this->compiledFrom = carr::get($options, 'compiledFrom');
        $this->mediaPaths = carr::get($options, 'mediaPaths', []);
        if ((cstr::startsWith($this->script, 'http') && strpos($this->script, '//') !== false)
            || cstr::startsWith($this->script, '//')
        ) {
            $this->isRemote = true;
        }
    }

    public function getUrl() {
        $file = $this->script;
        $dir_file = $this->script;
        $js_version = '';

        if (!cstr::startsWith($file, 'http')) {
            if (strpos($file, '?') !== false) {
                $dir_file = substr($file, 0, strpos($file, '?'));
                $js_version = substr($file, strpos($file, '?'), strlen($file) - 1);
            }
        }
        $js_file = $this->fullpath($dir_file);
        if (strpos($dir_file, 'http') !== false) {
            $js_file = $dir_file;
        } else {
            $js_file = $this->fullpath($dir_file);
            if (!file_exists($js_file)) {
                throw new Exception('JS File not exists, ' . $file);
            }
            if (strlen($js_version) > 0) {
                $js_file .= $js_version;
            }
        }

        return $js_file;
    }

    public function getPath() {
        if ($this->compiled) {
            return $this->script;
        }
        $file = $this->script;
        $dirFile = $this->script;
        $fileVersion = '';

        if (!cstr::startsWith($file, 'http')) {
            if (strpos($file, '?') !== false) {
                $dirFile = substr($file, 0, strpos($file, '?'));
                $fileVersion = substr($file, strpos($file, '?'), strlen($file) - 1);
            }
        }
        $assetFile = $this->fullpath($dirFile);

        if (strpos($dirFile, 'http') !== false) {
            $assetFile = $dirFile;
        } else {
            $assetFile = $this->fullpath($dirFile);
            if (!file_exists($assetFile)) {
                throw new Exception('File not exists, ' . $file);
            }
            if (strlen($fileVersion) > 0) {
                $assetFile .= $fileVersion;
            }
        }

        return $assetFile;
    }

    /**
     * The file as it was registered (relative name or remote url), before path resolution.
     *
     * @return string
     */
    public function getScript() {
        return $this->script;
    }

    abstract protected function fullpath($file);

    abstract protected function render();

    public function getMediaPaths() {
        $dirs = CF::getDirs('media');

        $dirs = array_merge($this->mediaPaths, $dirs);

        return $dirs;
    }

    #[\ReturnTypeWillChange]
    public function __toString() {
        return $this->getPath();
    }

    /**
     * @return bool
     */
    public function isRemote() {
        return $this->isRemote;
    }
}
