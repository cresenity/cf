<?php

use Symfony\Component\Process\Process;

class CBackup_Database_Dumper_PostgreSqlDumper extends CBackup_Database_AbstractDumper {
    /**
     * @var bool
     */
    protected $useInserts = false;

    /**
     * @var bool
     */
    protected $createTables = true;

    public function __construct() {
        $this->port = 5432;
    }

    /**
     * @return $this
     */
    public function useInserts() {
        $this->useInserts = true;
        return $this;
    }

    /**
     * Dump the contents of the database to the given file.
     *
     * @param string $dumpFile
     *
     * @throws \Spatie\DbDumper\Exceptions\CannotStartDump
     * @throws \Spatie\DbDumper\Exceptions\DumpFailed
     */
    public function dumpToFile($dumpFile) {
        $this->guardAgainstIncompleteCredentials();

        if ($this->ssh !== null) {
            $this->dumpToFileViaSsh($dumpFile);

            return;
        }

        $command = $this->getDumpCommand($dumpFile);
        $tempFileHandle = tmpfile();
        fwrite($tempFileHandle, $this->getContentsOfCredentialsFile());
        $temporaryCredentialsFile = stream_get_meta_data($tempFileHandle)['uri'];
        $envVars = $this->getEnvironmentVariablesForDumpCommand($temporaryCredentialsFile);
        $process = Process::fromShellCommandline($command, null, $envVars, null, $this->timeout);
        $process->run();
        $this->checkIfDumpWasSuccessFul($process, $dumpFile);
    }

    /**
     * Menjalankan pg_dump di server tujuan lewat SSH, bukan di mesin yang
     * menjalankan CBackup - pola yang sama dengan
     * CBackup_Database_Dumper_MySqlDumper::dumpToFileViaSsh(), lihat
     * docblock di sana untuk alasan lengkapnya (dump ditulis dulu ke berkas
     * remote, diunduh lewat SFTP, bukan ditangkap lewat exec() ke string PHP).
     *
     * Bedanya dari jalur MySQL: kredensial Postgres lewat berkas `.pgpass`
     * yang dibaca dari variabel lingkungan `PGPASSFILE`, bukan argumen CLI -
     * dan libpq **menolak diam-diam** sebuah `.pgpass` yang permission-nya
     * lebih longgar dari 0600, jadi berkas kredensial remote di-chmod 600
     * eksplisit sebelum dipakai.
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

        $remoteCredentialsFile = '/tmp/.cbackup-' . cstr::random(20) . '.pgpass';
        $remoteDumpFile = '/tmp/.cbackup-dump-' . cstr::random(20) . '.sql';
        $this->ssh->putString($remoteCredentialsFile, $this->getContentsOfCredentialsFile());

        try {
            $this->ssh->exec('chmod 600 ' . escapeshellarg($remoteCredentialsFile));

            $envPrefix = 'PGPASSFILE=' . escapeshellarg($remoteCredentialsFile)
                . ' PGDATABASE=' . escapeshellarg($this->dbName) . ' ';
            $command = $this->echoToFile(
                $envPrefix . implode(' ', $this->buildDumpCommandParts()),
                $remoteDumpFile
            );
            $output = $this->ssh->exec($command);

            $remoteSize = (int) trim((string) $this->ssh->exec('wc -c < ' . escapeshellarg($remoteDumpFile) . ' 2>/dev/null'));
            if ($remoteSize === 0) {
                $preview = mb_substr((string) $output, 0, 500);

                throw new CBackup_Database_Exception_DumpFailedException(
                    "Dump lewat SSH kosong atau gagal di server remote. Output pg_dump:\n{$preview}"
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

    /**
     * Get the command that should be performed to dump the database.
     *
     * @param string $dumpFile
     *
     * @return string
     */
    public function getDumpCommand($dumpFile) {
        return $this->echoToFile(implode(' ', $this->buildDumpCommandParts()), $dumpFile);
    }

    /**
     * Bagian command pg_dump tanpa redirect ke file - dipakai getDumpCommand()
     * (redirect ke $dumpFile lokal) dan dumpToFileViaSsh() (redirect ke
     * berkas di server remote lewat SSH exec).
     *
     * @return string[]
     */
    protected function buildDumpCommandParts() {
        $quote = $this->determineQuote();
        $command = [
            "{$quote}{$this->dumpBinaryPath}pg_dump{$quote}",
            "-U {$this->userName}",
            '-h ' . ($this->socket === '' ? $this->host : $this->socket),
            "-p {$this->port}",
        ];
        if ($this->useInserts) {
            $command[] = '--inserts';
        }
        if (!$this->createTables) {
            $command[] = '--data-only';
        }
        foreach ($this->extraOptions as $extraOption) {
            $command[] = $extraOption;
        }
        if (!empty($this->includeTables)) {
            $command[] = '-t ' . implode(' -t ', $this->includeTables);
        }
        if (!empty($this->excludeTables)) {
            $command[] = '-T ' . implode(' -T ', $this->excludeTables);
        }
        return $command;
    }

    public function getContentsOfCredentialsFile() {
        $contents = [
            $this->host,
            $this->port,
            $this->dbName,
            $this->userName,
            $this->password,
        ];
        return implode(':', $contents);
    }

    protected function guardAgainstIncompleteCredentials() {
        foreach (['userName', 'dbName', 'host'] as $requiredProperty) {
            if (empty($this->$requiredProperty)) {
                throw CBackup_Database_Exception_CannotStartDumpException::emptyParameter($requiredProperty);
            }
        }
    }

    protected function getEnvironmentVariablesForDumpCommand($temporaryCredentialsFile) {
        return [
            'PGPASSFILE' => $temporaryCredentialsFile,
            'PGDATABASE' => $this->dbName,
        ];
    }

    /**
     * @return $this
     */
    public function doNotCreateTables() {
        $this->createTables = false;
        return $this;
    }
}
