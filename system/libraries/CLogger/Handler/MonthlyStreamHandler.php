<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
 * Driver single dengan path default: berkas pindah ke folder bulan berjalan saat bulan berganti.
 */
class CLogger_Handler_MonthlyStreamHandler extends StreamHandler {
    use CLogger_Concern_MonthlyDirectoryTrait;

    /**
     * @param string     $directory
     * @param string     $filename
     * @param int|string $level
     * @param bool       $bubble
     * @param null|int   $filePermission
     * @param bool       $useLocking
     */
    public function __construct($directory, $filename, $level = Logger::DEBUG, $bubble = true, $filePermission = null, $useLocking = false) {
        $this->monthlyDirectory = $directory;
        $this->monthlyFilename = $filename;
        parent::__construct($this->currentMonthlyPath(), $level, $bubble, $filePermission, $useLocking);
    }

    protected function write(array $record): void {
        $url = $this->currentMonthlyPath();
        if ($this->url !== $url) {
            $this->close();
            $this->url = $url;
        }

        parent::write($record);
    }
}
