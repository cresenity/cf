<?php

use Monolog\Utils;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

/**
 * Driver daily dengan path default: tiap rotasi harian folder bulan dihitung ulang.
 * Retensi (days) tetap dihitung di dalam folder bulan berjalan, sama seperti sebelumnya.
 */
class CLogger_Handler_MonthlyRotatingFileHandler extends RotatingFileHandler {
    use CLogger_Concern_MonthlyDirectoryTrait;

    /**
     * @param string     $directory
     * @param string     $filename
     * @param int        $maxFiles
     * @param int|string $level
     * @param bool       $bubble
     * @param null|int   $filePermission
     * @param bool       $useLocking
     */
    public function __construct($directory, $filename, $maxFiles = 0, $level = Logger::DEBUG, $bubble = true, $filePermission = null, $useLocking = false) {
        $this->monthlyDirectory = $directory;
        $this->monthlyFilename = $filename;
        parent::__construct($this->currentMonthlyPath(), $maxFiles, $level, $bubble, $filePermission, $useLocking);
    }

    protected function rotate(): void {
        $this->filename = Utils::canonicalizePath($this->currentMonthlyPath());

        parent::rotate();
    }
}
