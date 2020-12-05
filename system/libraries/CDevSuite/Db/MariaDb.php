<?php

/**
 * Description of MariaDb
 *
 * @author Hery
 */
use Symfony\Component\Process\Process;

abstract class CDevSuite_Db_MariaDb {

    /**
     *
     * @var CDevSuite_Filesystem
     */
    protected $files;

    /**
     *
     * @var CDevSuite_CommandLine
     */
    protected $cli;

    public function __construct() {
        $this->files = CDevSuite::filesystem();
        $this->cli = CDevSuite::commandLine();
    }
    
   /**
     * Install the configuration files for MariaDb.
     *
     * @return void
     */
    abstract public function install();

    /**
     * Forcefully uninstall MariaDb.
     *
     * @return void
     */
    abstract public function uninstall();

    /**
     * Stop the MariaDb service.
     *
     * @return void
     */
    abstract public function stop();

    /**
     * Restart the MariaDb service.
     *
     * @return void
     */
    abstract public function restart();
    
    /**
     * get ini file location
     * 
     * @return string
     */
    public function mariaDbIniFile() {
        return c::fixPath(CDevSuite::homePath()) . 'MariaDb' . DS . 'my.mariadb.ini';
    }

    public function dump($from) {
        $dbDumper = CBackup_DatabaseDumperFactory::createFromConnection($from);
        CBackup::output()->info("Dumping database {$dbDumper->getDbName()}...");
        $dbType = mb_strtolower(basename(str_replace('\\', '/', get_class($dbDumper))));
        $dbName = $dbDumper->getDbName();
        if ($dbDumper instanceof CBackup_Database_Dumper_SqliteDumper) {
            $dbName = $key . '-database';
        }
        $fileName = "{$dbType}-{$dbName}.{$this->getExtension($dbDumper)}";

        $temporaryFilePath = DOCROOT . 'temp' . DS . 'devsuite' . DS . 'db' . DS . 'db-dumps' . DS . $fileName;
        //$temporaryFilePath = DOCROOT . 'temp/devsuite/db/db-dumps/'. $fileName;

        $dbDumper->setDumpBinaryPath($this->getDumperBinaryPath());
        $this->files->ensureDirExists(dirname($temporaryFilePath));

        CDevSuite::info("Dumping database to:" . $temporaryFilePath);

        //$dbDumper->dumpToFile($temporaryFilePath);
        return $temporaryFilePath;
    }

    protected function getExtension(CBackup_Database_AbstractDumper $dbDumper) {
        return $dbDumper instanceof CBackup_Database_Dumper_MongoDbDumper ? 'archive' : 'sql';
    }

    public function restore($to, $dumpFile) {
        $command=$this->getRestoreCommand($to, $dumpFile);
        
        $process = Process::fromShellCommandline($command,null,null,null);
        $output = '';
        $process->run(function ($type, $line) use(&$output) {
            $output .= $line;
        });

        return $output;
    }

    protected function getDumperBinaryPath() {
        //echo realpath(CDevSuite::binPath() . 'mariadb') . DS . 'bin' . DS . 'mysqldump.exe';
        
        return realpath(CDevSuite::binPath() . 'mariadb') . DS . 'bin'.DS;
        
         
    }
    
    
    protected function getClientBinaryPath() {
        //echo realpath(CDevSuite::binPath() . 'mariadb') . DS . 'bin' . DS . 'mysqldump.exe';
        
        return realpath(CDevSuite::binPath() . 'mariadb') . DS . 'bin'.DS;
        
         
    }

    protected function getRestoreCommand($dbConfig, $fromFile) {
        $command = [];
        $connection = carr::get($dbConfig, 'connection');
        $driver = carr::get($connection, 'type');
        $database = carr::get($connection, 'database');
        $username = carr::get($connection, 'user');
        $password = carr::get($connection, 'pass');
        $port = carr::get($connection, 'port');
        $host = carr::first(carr::wrap(carr::get($connection, 'host', '')));

        $command[] = $this->getClientBinaryPath().'mysql';
        $command[] = '-h';
        $command[] = $host;
        $command[] = '-u';
        $command[] = $username;
        if (strlen($password) > 0) {
            $command[] = '-p';
            $command[] = $password;
        }

        $command[] = $database;
        return implode(" ", $command) . " < " . $fromFile;
    }

    /**
     * Install the Nginx configuration directory to the ~/.config/devsuite directory.
     *
     * This directory contains all site-specific Nginx servers.
     *
     * @return void
     */
    public function installMariaDbDirectory() {
        CDevSuite::info('Installing MariaDb directory...');

        if (!$this->files->isDir($mariaDbDirectory = CDevSuite::homePath() . '/MariaDb')) {
            $this->files->mkdirAsUser($mariaDbDirectory);
        }
        $this->files->putAsUser($this->mariaDbIniFile(), $this->files->get(CDevSuite::stubsPath() . 'my.mariadb.ini'));

        $this->files->putAsUser($mariaDbDirectory . '/.keep', "\n");
    }

}
