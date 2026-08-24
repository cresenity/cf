<?php

use Symfony\Component\Process\Process;

class CBackup_Database_Dumper_MySqlDumper extends CBackup_Database_AbstractDumper {
    /**
     * @var bool
     */
    protected $skipComments = true;

    /**
     * @var bool
     */
    protected $useExtendedInserts = true;

    /**
     * @var bool
     */
    protected $useSingleTransaction = false;

    /**
     * @var bool
     */
    protected $skipLockTables = false;

    /**
     * @var bool
     */
    protected $useQuick = false;

    /**
     * @var string
     */
    protected $defaultCharacterSet = '';

    /**
     * Nama binary yang dijalankan - MariaDB 10.6+ menamainya mariadb-dump,
     * mysqldump hanya symlink kompatibilitas yang tidak selalu ada di semua
     * instalasi.
     *
     * @var string
     */
    protected $dumpBinaryName = 'mysqldump';

    /**
     * @var bool
     */
    protected $dbNameWasSetAsExtraOption = false;

    /**
     * @var bool
     */
    protected $allDatabasesWasSetAsExtraOption = false;

    /**
     * @var string
     */
    protected $setGtidPurged = 'AUTO';

    /**
     * @var bool
     */
    protected $createTables = true;

    public function __construct() {
        $this->port = 3306;
    }

    /**
     * @return $this
     */
    public function skipComments() {
        $this->skipComments = true;
        return $this;
    }

    /**
     * @return $this
     */
    public function dontSkipComments() {
        $this->skipComments = false;
        return $this;
    }

    /**
     * @return $this
     */
    public function useExtendedInserts() {
        $this->useExtendedInserts = true;
        return $this;
    }

    /**
     * @return $this
     */
    public function dontUseExtendedInserts() {
        $this->useExtendedInserts = false;
        return $this;
    }

    /**
     * @return $this
     */
    public function useSingleTransaction() {
        $this->useSingleTransaction = true;
        return $this;
    }

    /**
     * @return $this
     */
    public function dontUseSingleTransaction() {
        $this->useSingleTransaction = false;
        return $this;
    }

    /**
     * @return $this
     */
    public function skipLockTables() {
        $this->skipLockTables = true;
        return $this;
    }

    /**
     * @return $this
     */
    public function dontSkipLockTables() {
        $this->skipLockTables = false;
        return $this;
    }

    /**
     * @return $this
     */
    public function useQuick() {
        $this->useQuick = true;
        return $this;
    }

    /**
     * @return $this
     */
    public function dontUseQuick() {
        $this->useQuick = false;
        return $this;
    }

    /**
     * @param string $dumpBinaryName
     *
     * @return $this
     */
    public function setDumpBinaryName($dumpBinaryName) {
        $this->dumpBinaryName = $dumpBinaryName;
        return $this;
    }

    /**
     * @param string $characterSet
     *
     * @return $this
     */
    public function setDefaultCharacterSet($characterSet) {
        $this->defaultCharacterSet = $characterSet;
        return $this;
    }

    /**
     * @param mixed $setGtidPurged
     *
     * @return $this
     */
    public function setGtidPurged($setGtidPurged) {
        $this->setGtidPurged = $setGtidPurged;
        return $this;
    }

    /**
     * Dump the contents of the database to the given file.
     *
     * @param string $dumpFile
     *
     * @throws \CBackup_Database_Exception_CannotStartDumpException
     * @throws \CBackup_Database_Exception_DumpFailedException
     */
    public function dumpToFile($dumpFile) {
        $this->guardAgainstIncompleteCredentials();

        if ($this->ssh !== null) {
            $this->dumpToFileViaSsh($dumpFile);
            return;
        }

        $tempFileHandle = tmpfile();
        fwrite($tempFileHandle, $this->getContentsOfCredentialsFile());
        $temporaryCredentialsFile = stream_get_meta_data($tempFileHandle)['uri'];
        $command = $this->getDumpCommand($dumpFile, $temporaryCredentialsFile);

        $process = Process::fromShellCommandline($command, null, null, null, $this->timeout);
        $process->run();
        $this->checkIfDumpWasSuccessFul($process, $dumpFile);
    }

    /**
     * Menjalankan mysqldump di server tujuan lewat SSH, bukan di mesin yang
     * menjalankan CBackup - dipakai saat host database tidak reachable
     * langsung (mis. di belakang NAT) tapi SSH ke servernya sendiri bisa.
     *
     * Dump ditulis ke berkas sementara **di server remote** (redirect shell
     * biasa, sama seperti jalur lokal), baru diunduh ke $dumpFile lewat
     * `get()` (SFTP) - bukan ditangkap lewat `exec()` ke string PHP seperti
     * versi sebelumnya. Versi lama membuffer seluruh isi dump di memori
     * sebelum menulisnya ke berkas, sehingga database yang dump-nya lebih
     * besar dari `memory_limit` PHP (terlihat pada database 242MB, limit CLI
     * 128M) gagal dengan "Allowed memory size exhausted" - bukan galat
     * mysqldump sama sekali. `get()` menulis langsung ke berkas lokal saat
     * mengunduh, jadi ukurannya tidak lagi dibatasi memori PHP.
     *
     * Berkas dump remote-nya sendiri tidak pernah tertinggal - dihapus di
     * `finally` yang sama dengan berkas kredensial.
     *
     * Kompresi belum didukung di jalur ini - $this->compressor mengandalkan
     * pipe shell lokal yang tidak berlaku untuk berkas yang sudah diunduh.
     *
     * @param string $dumpFile
     *
     * @throws \CBackup_Database_Exception_CannotStartDumpException
     * @throws \CBackup_Database_Exception_DumpFailedException
     */
    protected function dumpToFileViaSsh($dumpFile) {
        if ($this->compressor) {
            throw new CBackup_Database_Exception_CannotStartDumpException(
                'Compressor tidak didukung untuk dump lewat SSH - kompres dumpFile setelah dumpToFile() selesai.'
            );
        }

        $remoteCredentialsFile = '/tmp/.cbackup-' . cstr::random(20) . '.cnf';
        $remoteDumpFile = '/tmp/.cbackup-dump-' . cstr::random(20) . '.sql';
        $this->ssh->putString($remoteCredentialsFile, $this->getContentsOfCredentialsFile());

        try {
            $command = $this->echoToFile(
                implode(' ', $this->buildDumpCommandParts($remoteCredentialsFile)),
                $remoteDumpFile
            );
            $output = $this->ssh->exec($command);

            $remoteSize = (int) trim((string) $this->ssh->exec('wc -c < ' . escapeshellarg($remoteDumpFile) . ' 2>/dev/null'));
            if ($remoteSize === 0) {
                $preview = mb_substr((string) $output, 0, 500);

                throw new CBackup_Database_Exception_DumpFailedException(
                    "Dump lewat SSH kosong atau gagal di server remote. Output mysqldump:\n{$preview}"
                );
            }

            $this->ssh->get($remoteDumpFile, $dumpFile);
        } finally {
            $this->ssh->exec('rm -f ' . escapeshellarg($remoteCredentialsFile) . ' ' . escapeshellarg($remoteDumpFile));
        }

        if (!file_exists($dumpFile) || filesize($dumpFile) === 0) {
            throw new CBackup_Database_Exception_DumpFailedException(
                'Dump lewat SSH gagal diunduh - berkas remote ada, tetapi salinan lokal kosong.'
            );
        }
    }

    public function addExtraOption($extraOption) {
        if (strpos($extraOption, '--all-databases') !== false) {
            $this->dbNameWasSetAsExtraOption = true;
            $this->allDatabasesWasSetAsExtraOption = true;
        }
        if (preg_match('/^--databases (\S+)/', $extraOption, $matches) === 1) {
            $this->setDbName($matches[1]);
            $this->dbNameWasSetAsExtraOption = true;
        }
        return parent::addExtraOption($extraOption);
    }

    /**
     * @return $this
     */
    public function doNotCreateTables() {
        $this->createTables = false;
        return $this;
    }

    /**
     * Get the command that should be performed to dump the database.
     *
     * @param string $dumpFile
     * @param string $temporaryCredentialsFile
     *
     * @return string
     */
    public function getDumpCommand($dumpFile, $temporaryCredentialsFile) {
        $command = $this->buildDumpCommandParts($temporaryCredentialsFile);
        return $this->echoToFile(implode(' ', $command), $dumpFile);
    }

    /**
     * Bagian command mysqldump tanpa redirect ke file - dipakai getDumpCommand()
     * (redirect ke $dumpFile lokal) dan dumpToFileViaSsh() (output ditangkap
     * dari exec SSH, bukan redirect shell).
     *
     * @param string $temporaryCredentialsFile
     *
     * @return string[]
     */
    protected function buildDumpCommandParts($temporaryCredentialsFile) {
        $quote = $this->determineQuote();
        $command = [
            "{$quote}{$this->dumpBinaryPath}{$this->dumpBinaryName}{$quote}",
            "--defaults-extra-file=\"{$temporaryCredentialsFile}\"",
        ];
        if (!$this->createTables) {
            $command[] = '--no-create-info';
        }
        if ($this->skipComments) {
            $command[] = '--skip-comments';
        }
        $command[] = $this->useExtendedInserts ? '--extended-insert' : '--skip-extended-insert';
        if ($this->useSingleTransaction) {
            $command[] = '--single-transaction';
        }
        if ($this->skipLockTables) {
            $command[] = '--skip-lock-tables';
        }
        if ($this->useQuick) {
            $command[] = '--quick';
        }
        if ($this->socket !== '') {
            $command[] = "--socket={$this->socket}";
        }
        foreach ($this->excludeTables as $tableName) {
            $command[] = "--ignore-table={$this->dbName}.{$tableName}";
        }
        if (!empty($this->defaultCharacterSet)) {
            $command[] = '--default-character-set=' . $this->defaultCharacterSet;
        }
        foreach ($this->extraOptions as $extraOption) {
            $command[] = $extraOption;
        }
        if ($this->setGtidPurged !== 'AUTO') {
            $command[] = '--set-gtid-purged=' . $this->setGtidPurged;
        }
        if (!$this->dbNameWasSetAsExtraOption) {
            $command[] = $this->dbName;
        }
        if (!empty($this->includeTables)) {
            $includeTables = implode(' ', $this->includeTables);
            $command[] = "--tables {$includeTables}";
        }
        return $command;
    }

    public function getContentsOfCredentialsFile() {
        $contents = [
            '[client]',
            "user = '{$this->userName}'",
            "password = '{$this->password}'",
            "host = '{$this->host}'",
            "port = '{$this->port}'",
        ];
        return implode(PHP_EOL, $contents);
    }

    protected function guardAgainstIncompleteCredentials() {
        foreach (['userName', 'host'] as $requiredProperty) {
            if (strlen($this->$requiredProperty) === 0) {
                throw CBackup_Database_Exception_CannotStartDumpException::emptyParameter($requiredProperty);
            }
        }
        if (strlen('dbName') === 0 && !$this->allDatabasesWasSetAsExtraOption) {
            throw CBackup_Database_Exception_CannotStartDumpException::emptyParameter($requiredProperty);
        }
    }
}
