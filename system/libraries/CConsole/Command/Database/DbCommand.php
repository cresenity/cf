<?php

use Symfony\Component\Process\Process;

class CConsole_Command_Database_DbCommand extends CConsole_Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db {connection? : The database connection that should be used}
               {--read : Connect to the read connection}
               {--write : Connect to the write connection}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start a new database CLI session';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle() {
        if (!CServer::os()->onWindows() && !Process::isTtySupported()) {
            $this->error('Perintah ini butuh terminal interaktif (TTY), tidak bisa dijalankan lewat skrip/sesi non-interaktif.');

            return 1;
        }

        $connection = $this->getConnection();
        $processCommand = array_merge([$this->getCommand($connection)], $this->commandArguments($connection));
        (new Process(
            $processCommand,
            null,
            $this->commandEnvironment($connection)
        ))->setTimeout(null)->setTty(!CServer::os()->onWindows())->mustRun(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        return 0;
    }

    /**
     * Get the database connection configuration.
     *
     * @throws \UnexpectedValueException
     *
     * @return array
     */
    public function getConnection() {
        $connectionName = $this->argument('connection') ?: 'default';
        $connection = CDatabase_Config::resolve($connectionName);
        if (empty($connection)) {
            throw new UnexpectedValueException("Invalid database connection [{$connectionName}].");
        }

        if (!empty($connection['url'])) {
            $connection = (new CDatabase_ConfigurationUrlParser())->parseConfiguration($connection);
        }

        if ($this->option('read')) {
            if (is_array($connection['read']['host'])) {
                $connection['read']['host'] = $connection['read']['host'][0];
            }

            $connection = array_merge($connection, $connection['read']);
        } elseif ($this->option('write')) {
            if (is_array($connection['write']['host'])) {
                $connection['write']['host'] = $connection['write']['host'][0];
            }

            $connection = array_merge($connection, $connection['write']);
        }

        return $connection;
    }

    /**
     * Get the arguments for the database client command.
     *
     * @param array $connection
     *
     * @return array
     */
    public function commandArguments(array $connection) {
        $driver = ucfirst($this->normalizeDriver($connection['driver']));

        return $this->{"get{$driver}Arguments"}($connection);
    }

    /**
     * Get the environment variables for the database client command.
     *
     * @param array $connection
     *
     * @return null|array
     */
    public function commandEnvironment(array $connection) {
        $driver = ucfirst($this->normalizeDriver($connection['driver']));

        if (method_exists($this, "get{$driver}Environment")) {
            return $this->{"get{$driver}Environment"}($connection);
        }

        return null;
    }

    /**
     * Get the database client command to run.
     *
     * @param array $connection
     *
     * @return string
     */
    public function getCommand(array $connection) {
        return [
            'mysql' => 'mysql',
            'pgsql' => 'psql',
            'sqlite' => 'sqlite3',
            'sqlsrv' => 'sqlcmd',
        ][$this->normalizeDriver($connection['driver'])];
    }

    private function normalizeDriver($driver) {
        $mappedDriver = [
            'mysqli' => 'mysql'
        ];

        return carr::get($mappedDriver, $driver, $driver);
    }

    /**
     * Get the arguments for the MySQL CLI.
     *
     * @param array $connection
     *
     * @return array
     */
    protected function getMysqlArguments(array $connection) {
        return array_merge([
            '--host=' . $connection['host'],
            '--port=' . ($connection['port']),
            '--user=' . $connection['username'],
        ], $this->getOptionalArguments([
            'password' => '--password=' . $connection['password'],
            'unix_socket' => '--socket=' . ($connection['unix_socket'] ?? ''),
            'charset' => '--default-character-set=' . ($connection['charset'] ?? ''),
        ], $connection), [$connection['database']]);
    }

    /**
     * Get the arguments for the Postgres CLI.
     *
     * @param array $connection
     *
     * @return array
     */
    protected function getPgsqlArguments(array $connection) {
        return [$connection['database']];
    }

    /**
     * Get the arguments for the SQLite CLI.
     *
     * @param array $connection
     *
     * @return array
     */
    protected function getSqliteArguments(array $connection) {
        return [$connection['database']];
    }

    /**
     * Get the arguments for the SQL Server CLI.
     *
     * @param array $connection
     *
     * @return array
     */
    protected function getSqlsrvArguments(array $connection) {
        return array_merge(...$this->getOptionalArguments([
            'database' => ['-d', $connection['database']],
            'username' => ['-U', $connection['username']],
            'password' => ['-P', $connection['password']],
            'host' => ['-S', 'tcp:' . $connection['host']
                        . ($connection['port'] ? ',' . $connection['port'] : ''), ],
        ], $connection));
    }

    /**
     * Get the environment variables for the Postgres CLI.
     *
     * @param array $connection
     *
     * @return null|array
     */
    protected function getPgsqlEnvironment(array $connection) {
        return array_merge(...$this->getOptionalArguments([
            'username' => ['PGUSER' => $connection['username']],
            'host' => ['PGHOST' => $connection['host']],
            'port' => ['PGPORT' => $connection['port']],
            'password' => ['PGPASSWORD' => $connection['password']],
        ], $connection));
    }

    /**
     * Get the optional arguments based on the connection configuration.
     *
     * @param array $args
     * @param array $connection
     *
     * @return array
     */
    protected function getOptionalArguments(array $args, array $connection) {
        return array_values(array_filter($args, function ($key) use ($connection) {
            return !empty($connection[$key]);
        }, ARRAY_FILTER_USE_KEY));
    }
}
