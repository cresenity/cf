<?php

/**
 * Path log default: <directory>/<Y>/<m>/<filename>, dihitung ulang supaya proses
 * yang hidup melewati pergantian bulan (daemon/worker) pindah ke folder bulan baru.
 */
trait CLogger_Concern_MonthlyDirectoryTrait {
    /**
     * @var string
     */
    protected $monthlyDirectory;

    /**
     * @var string
     */
    protected $monthlyFilename;

    /**
     * @param string   $directory
     * @param string   $filename
     * @param null|int $time
     *
     * @return string
     */
    public static function monthlyPath($directory, $filename, $time = null) {
        $time = $time === null ? time() : $time;

        return rtrim($directory, '/\\') . DS . date('Y', $time) . DS . date('m', $time) . DS . $filename;
    }

    /**
     * @return string
     */
    protected function currentMonthlyPath() {
        return static::monthlyPath($this->monthlyDirectory, $this->monthlyFilename);
    }
}
