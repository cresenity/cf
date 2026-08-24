<?php
class CDatabase_ConnectionFactory {
    protected $connectors = [];

    /**
     * Singleton Instance.
     *
     * @var CDatabase_ConnectionFactory
     */
    private static $instance;

    public static function instance() {
        if (static::$instance == null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Create a new connection factory instance.
     *
     * @return void
     */
    public function __construct() {
    }

    /**
     * Establish a PDO connection based on the configuration.
     *
     * @param array       $config
     * @param null|string $name
     *
     * @return CDatabase_Connection
     */
    public function make(array $config, $name = null) {
        $config = CDatabase_Config::flattenFormat($config);
        $config = $this->parseConfig($config, $name);

        if (isset($config['read'])) {
            return $this->createReadWriteConnection($config);
        }

        return $this->createSingleConnection($config);
    }

    /**
     * Parse and prepare the database configuration.
     *
     * @param array  $config
     * @param string $name
     *
     * @return array
     */
    protected function parseConfig(array $config, $name) {
        return carr::add(carr::add($config, 'prefix', ''), 'name', $name);
    }

    /**
     * Create a single database connection instance.
     *
     * @param array $config
     *
     * @return CDatabase_Connection
     */
    protected function createSingleConnection(array $config) {
        $driver = $this->createPdoResolver($config);

        return $this->createConnection(
            $config['driver'],
            $driver,
            $config['database'],
            $config['prefix'],
            $config
        );
    }

    /**
     * Create a read / write database connection instance.
     *
     * @param array $config
     *
     * @return CDatabase_Connection
     */
    protected function createReadWriteConnection(array $config) {
        $connection = $this->createSingleConnection($this->getWriteConfig($config));

        return $connection->setReadPdo($this->createReadPdo($config));
    }

    /**
     * Create a new PDO instance for reading.
     *
     * @param array $config
     *
     * @return \Closure
     */
    protected function createReadPdo(array $config) {
        return $this->createPdoResolver($this->getReadConfig($config));
    }

    /**
     * Get the read configuration for a read / write connection.
     *
     * @param array $config
     *
     * @return array
     */
    protected function getReadConfig(array $config) {
        return $this->mergeReadWriteConfig(
            $config,
            $this->getReadWriteConfig($config, 'read')
        );
    }

    /**
     * Get the write configuration for a read / write connection.
     *
     * @param array $config
     *
     * @return array
     */
    protected function getWriteConfig(array $config) {
        return $this->mergeReadWriteConfig(
            $config,
            $this->getReadWriteConfig($config, 'write')
        );
    }

    /**
     * Get a read / write level configuration.
     *
     * @param array  $config
     * @param string $type
     *
     * @return array
     */
    protected function getReadWriteConfig(array $config, $type) {
        return isset($config[$type][0])
                        ? carr::random($config[$type])
                        : $config[$type];
    }

    /**
     * Merge a configuration for a read / write connection.
     *
     * @param array $config
     * @param array $merge
     *
     * @return array
     */
    protected function mergeReadWriteConfig(array $config, array $merge) {
        return carr::except(array_merge($config, $merge), ['read', 'write']);
    }

    /**
     * Create a new Closure that resolves to a PDO instance.
     *
     * @param array $config
     *
     * @return \Closure
     */
    protected function createPdoResolver(array $config) {
        $host = carr::get($config, 'host');

        return $host
            ? $this->createPdoResolverWithHosts($config)
            : $this->createPdoResolverWithoutHosts($config);
    }

    /**
     * Create a new Closure that resolves to a PDO instance with a specific host or an array of hosts.
     *
     * @param array $config
     *
     * @throws \PDOException
     *
     * @return \Closure
     */
    protected function createPdoResolverWithHosts(array $config) {
        return function () use ($config) {
            $e = null;
            foreach (carr::shuffle($hosts = $this->parseHosts($config)) as $key => $host) {
                $config['host'] = $host;

                try {
                    return $this->createConnector($config)->connect($config);
                } catch (PDOException $e) {
                    continue;
                }
            }

            throw $e;
        };
    }

    /**
     * Parse the hosts configuration item into an array.
     *
     * @param array $config
     *
     * @throws \InvalidArgumentException
     *
     * @return array
     */
    protected function parseHosts(array $config) {
        $hosts = carr::wrap(carr::get($config, 'host'));
        if (empty($hosts)) {
            throw new InvalidArgumentException('Database hosts array is empty.');
        }

        return $hosts;
    }

    /**
     * Create a new Closure that resolves to a PDO instance where there is no configured host.
     *
     * @param array $config
     *
     * @return \Closure
     */
    protected function createPdoResolverWithoutHosts(array $config) {
        return function () use ($config) {
            return $this->createConnector($config)->connect($config);
        };
    }

    /**
     * Create a connector instance based on the configuration.
     *
     * @param array $config
     *
     * @throws \InvalidArgumentException
     *
     * @return \CDatabase_Connector
     */
    public function createConnector(array $config) {
        if (!isset($config['driver'])) {
            throw new InvalidArgumentException('A driver must be specified.');
        }

        $driver = carr::get($config, 'driver');
        $driver = carr::get($this->getDriverMap(), $driver, $driver);
        if (c::container()->bound($key = "db.connector.{$driver}")) {
            return c::container()->make($key);
        }
        switch ($driver) {
            case 'mysql':
                return new CDatabase_Connector_Pdo_MySqlConnector();
            case 'sqlite':
                return new CDatabase_Connector_Pdo_SqliteConnector();
            case 'sqlsrv':
                return new CDatabase_Connector_Pdo_SqlServerConnector();
            case 'pgsql':
                return new CDatabase_Connector_Pdo_PostgresConnector();
        }

        throw new InvalidArgumentException("Unsupported driver [{$config['driver']}].");
    }

    /**
     * Create a new connection instance.
     *
     * @param string        $driver
     * @param \PDO|\Closure $connection
     * @param string        $database
     * @param string        $prefix
     * @param array         $config
     *
     * @throws \InvalidArgumentException
     *
     * @return \CDatabase_Connection
     */
    protected function createConnection($driver, $connection, $database, $prefix = '', array $config = []) {
        if ($resolver = CDatabase_Connection::getResolver($driver)) {
            return $resolver($connection, $database, $prefix, $config);
        }

        $driver = carr::get($this->getDriverMap(), $driver, $driver);
        switch ($driver) {
            case 'mysql':
                return new CDatabase_Connection_Pdo_MySqlConnection($connection, $database, $prefix, $config);
            case 'sqlite':
                return new CDatabase_Connection_Pdo_SqliteConnection($connection, $database, $prefix, $config);
            case 'pgsql':
                return new CDatabase_Connection_Pdo_PostgresConnection($connection, $database, $prefix, $config);
            case 'sqlsrv':
                return new CDatabase_Connection_Pdo_SqlServerConnection($connection, $database, $prefix, $config);
        }

        throw new InvalidArgumentException("Unsupported driver [{$driver}].");
    }

    private function getDriverMap() {
        return [
            'mysqli' => 'mysql',
            'pdo.mysql' => 'mysql',
            'mariadb' => 'mysql',
            'postgre' => 'pgsql',
            'postgres' => 'pgsql',
        ];
    }
}
