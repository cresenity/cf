<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

use Symfony\Component\Process\Process;

abstract class CBackup_Database_AbstractDumper {
    /**
     * @var string
     */
    protected $dbName;

    /**
     * @var string
     */
    protected $userName;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var string
     */
    protected $host = 'localhost';

    /**
     * @var int
     */
    protected $port = 5432;

    /**
     * @var string
     */
    protected $socket = '';

    /**
     * @var int
     */
    protected $timeout = 0;

    /**
     * @var string
     */
    protected $dumpBinaryPath = '';

    /**
     * @var array
     */
    protected $includeTables = [];

    /**
     * @var array
     */
    protected $excludeTables = [];

    /**
     * @var array
     */
    protected $extraOptions = [];

    /**
     * @var null|CBackup_AbstractCompressor
     */
    protected $compressor = null;

    /**
     * Saat diisi, dumpToFile() dijalankan di server ini lewat SSH alih-alih
     * di mesin lokal - dipakai untuk database yang hostnya tidak reachable
     * langsung tapi SSH ke server tempatnya berada bisa.
     *
     * @var null|CRemote_SSH
     */
    protected $ssh = null;

    public static function create() {
        return new static();
    }

    public function getDbName() {
        return $this->dbName;
    }

    /**
     * @param string $dbName
     *
     * @return $this
     */
    public function setDbName($dbName) {
        $this->dbName = $dbName;

        return $this;
    }

    /**
     * @param string $userName
     *
     * @return $this
     */
    public function setUserName($userName) {
        $this->userName = $userName;

        return $this;
    }

    /**
     * @param string $password
     *
     * @return $this
     */
    public function setPassword($password) {
        $this->password = $password;

        return $this;
    }

    /**
     * @param string $host
     *
     * @return $this
     */
    public function setHost($host) {
        $this->host = $host;

        return $this;
    }

    public function getHost() {
        return $this->host;
    }

    /**
     * @param int $port
     *
     * @return $this
     */
    public function setPort($port) {
        $this->port = $port;

        return $this;
    }

    /**
     * @param string $socket
     *
     * @return $this
     */
    public function setSocket($socket) {
        $this->socket = $socket;

        return $this;
    }

    /**
     * @param int $timeout
     *
     * @return $this
     */
    public function setTimeout($timeout) {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * @param null|CRemote_SSH $ssh
     *
     * @return $this
     */
    public function setSsh($ssh) {
        $this->ssh = $ssh;

        return $this;
    }

    /**
     * @return null|CRemote_SSH
     */
    public function getSsh() {
        return $this->ssh;
    }

    public function setDumpBinaryPath($dumpBinaryPath) {
        if ($dumpBinaryPath !== '' && substr($dumpBinaryPath, -1) !== '/') {
            $dumpBinaryPath .= '/';
        }
        $this->dumpBinaryPath = $dumpBinaryPath;

        return $this;
    }

    /**
     * @deprecated
     *
     * @return $this
     */
    public function enableCompression() {
        $this->compressor = new CBackup_Compressor_GzipCompressor();

        return $this;
    }

    public function getCompressorExtension() {
        return $this->compressor->useExtension();
    }

    public function useCompressor(CBackup_AbstractCompressor $compressor) {
        $this->compressor = $compressor;

        return $this;
    }

    /**
     * @param string|array $includeTables
     *
     * @throws CBackup_Database_Exception_CannotSetParameterException
     *
     * @return $this
     */
    public function includeTables($includeTables) {
        if (!empty($this->excludeTables)) {
            throw CBackup_Database_Exception_CannotSetParameterException::conflictingParameters('includeTables', 'excludeTables');
        }
        if (!is_array($includeTables)) {
            $includeTables = explode(', ', $includeTables);
        }
        $this->includeTables = $includeTables;

        return $this;
    }

    /**
     * @param string|array $excludeTables
     *
     * @throws CBackup_Database_Exception_CannotSetParameterException
     *
     * @return $this
     */
    public function excludeTables($excludeTables) {
        if (!empty($this->includeTables)) {
            throw CBackup_Database_Exception_CannotSetParameterException::conflictingParameters('excludeTables', 'includeTables');
        }
        if (!is_array($excludeTables)) {
            $excludeTables = explode(', ', $excludeTables);
        }
        $this->excludeTables = $excludeTables;

        return $this;
    }

    /**
     * @param string $extraOption
     *
     * @return $this
     */
    public function addExtraOption($extraOption) {
        if (!empty($extraOption)) {
            $this->extraOptions[] = $extraOption;
        }

        return $this;
    }

    abstract public function dumpToFile($dumpFile);

    protected function checkIfDumpWasSuccessFul(Process $process, $outputFile) {
        if (!$process->isSuccessful()) {
            throw CBackup_Database_Exception_DumpFailedException::processDidNotEndSuccessfully($process);
        }
        if (!file_exists($outputFile)) {
            throw CBackup_Database_Exception_DumpFailedException::dumpfileWasNotCreated($process);
        }
        if (filesize($outputFile) === 0) {
            throw CBackup_Database_Exception_DumpFailedException::dumpfileWasEmpty($process);
        }
    }

    protected function echoToFile($command, $dumpFile) {
        if (!CServer::isWindows()) {
            $dumpFile = '"' . addcslashes($dumpFile, '\\"') . '"';
        } else {
            $dumpFile = '"' . addcslashes($dumpFile, '"') . '"';
        }
        if ($this->compressor) {
            $compressCommand = $this->compressor->useCommand();

            return "(((({$command}; echo \$? >&3) | {$compressCommand} > {$dumpFile}) 3>&1) | (read x; exit \$x))";
        }

        return $command . ' > ' . $dumpFile;
    }

    protected function determineQuote() {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? '"' : "'";
    }
}
