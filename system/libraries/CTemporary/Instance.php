<?php

class CTemporary_Instance {
    protected static $instance;

    /**
     * @var string
     */
    protected $disk;

    public static function instance($disk = null) {
        if ($disk == null) {
            $disk = CF::config('storage.temp');
        }

        if (static::$instance == null) {
            static::$instance = [];
        }
        if (!isset(static::$instance[$disk])) {
            static::$instance[$disk] = new CTemporary_Instance($disk);
        }

        return static::$instance[$disk];
    }

    public function __construct($disk) {
        $this->disk = $disk;
    }

    public function disk() {
        return CStorage::instance()->disk($this->disk);
    }

    public function __call($name, $arguments) {
        return $this->disk()->$name(...$arguments);
    }

    /**
     * @param string $folder
     * @param type   $filename
     * @param mixed  $content
     *
     * @return string
     */
    public function put($content, $folder = null, $filename = null) {
        if ($folder == null) {
            $folder = 'default';
        }
        if ($filename == null) {
            $filename = date('Ymd') . cutils::randmd5();
        }

        $file = CTemporary::getPath($folder, $filename);
        $this->disk()->put($file, $content);

        return $file;
    }

    public function getDirectory($folder = null) {
        return CTemporary::getDirectory($folder);
    }
}
