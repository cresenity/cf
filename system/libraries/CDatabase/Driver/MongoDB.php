<?php

use MongoDB\Client;

class CDatabase_Driver_MongoDB extends CDatabase_Driver {
    use CTrait_Compat_Database_Driver_MongoDB;

    /**
     * Database connection link
     */
    protected $link;

    /**
     * @var CDatabase
     */
    protected $db;

    /**
     * @var \MongoDB\Database
     */
    protected $mongoDB;

    /**
     * Database configuration
     */
    protected $dbConfig;

    /**
     * Sets the config for the class.
     *
     * @param  array  database configuration
     * @param mixed $config
     */
    public function __construct(CDatabase $db, $config) {
        $this->db = $db;
        $this->dbConfig = $config;
        $this->connect();
        CF::log(CLogger::DEBUG, 'MongoDB Database Driver Initialized');
    }

    public function close() {
        unset($this->link);
        $this->link = null;
    }

    /**
     * Closes the database connection.
     */
    public function __destruct() {
    }

    /**
     * Determine if the given configuration array has a dsn string.
     *
     * @param array $config
     *
     * @return bool
     */
    protected function hasDsnString(array $config = null) {
        if ($config == null) {
            $config = $this->dbConfig;
        }

        $connection = carr::get($config, 'connection');
        return isset($connection['dsn']) && !empty($connection['dsn']);
    }

    /**
     * Get the DSN string form configuration.
     *
     * @param array $config
     *
     * @return string
     */
    protected function getDsnString(array $config = null) {
        if ($config == null) {
            $config = $this->dbConfig;
        }

        $connection = carr::get($config, 'connection');
        return carr::get($connection, 'dsn');
    }

    /**
     * Get the DSN string for a host / port configuration.
     *
     * @param array $config
     *
     * @return string
     */
    protected function getHostDsn(array $config = null) {
        if ($config == null) {
            $config = $this->dbConfig;
        }

        $configConnection = carr::get($config, 'connection');

        // Treat host option as array of hosts
        $hosts = is_array($configConnection['host']) ? $configConnection['host'] : [$configConnection['host']];
        foreach ($hosts as &$host) {
            // Check if we need to add a port to the host
            if (strpos($host, ':') === false && !empty($configConnection['port'])) {
                $host = $host . ':' . $configConnection['port'];
            }
        }
        $authString = '';
        if (isset($configConnection['user'])) {
            $authString .= $configConnection['user'];
        }
        if (isset($configConnection['pass'])) {
            $authString .= ':' . $configConnection['pass'];
        }

        if (strlen($authString) > 0) {
            $authString .= '@';
        }

        // Check if we want to authenticate against a specific database.
        $auth_database = isset($configConnection['options']) && !empty($configConnection['options']['database']) ? $configConnection['options']['database'] : null;
        return 'mongodb://' . $authString . implode(',', $hosts) . ($auth_database ? '/' . $auth_database : '');
    }

    /**
     * Create a DSN string from a configuration.
     *
     * @param array $config
     *
     * @return string
     */
    protected function getDsn(array $config = null) {
        if ($config == null) {
            $config = $this->dbConfig;
        }
        return $this->hasDsnString($config) ? $this->getDsnString($config) : $this->getHostDsn($config);
    }

    /**
     * @return \MongoDB\Client
     */
    public function getMongoClient() {
        return $this->link;
    }

    /**
     * @return \MongoDB\Driver\Manager
     */
    public function getMongoManager() {
        return $this->getMongoClient()->getManager();
    }

    /**
     * @return \MongoDB\Driver\Server
     */
    public function getMongoServer() {
        return $this->getMongoManager()->selectServer($this->getMongoManager()->getReadPreference());
    }

    /**
     * @return \MongoDB\Database
     */
    public function getMongoDatabase() {
        return $this->mongoDB;
    }

    public function connect() {
        // Check if link already exists
        if (($this->link != null)) {
            return $this->link;
        }

        $dsn = $this->getDsn();

        $options = carr::get($this->dbConfig, 'options', []);

        $this->link = $this->createConnection($dsn, $this->dbConfig, $options);
        $this->mongoDB = $this->link->selectDatabase(carr::get($this->dbConfig, 'connection.database'));
        return $this->link;
    }

    /**
     * Create a new MongoDB connection.
     *
     * @param string $dsn
     * @param array  $config
     * @param array  $options
     *
     * @return \MongoDB\Client
     */
    protected function createConnection($dsn, array $config, array $options) {
        // By default driver options is an empty array.

        $connectionData = carr::get($config, 'connection');
        $driverOptions = [];
        if (isset($config['driver_options']) && is_array($config['driver_options'])) {
            $driverOptions = $config['driver_options'];
        }
        // Check if the credentials are not already set in the options
        if (!isset($options['username']) && !empty($connectionData['username'])) {
            $options['username'] = $connectionData['username'];
        }
        if (!isset($options['password']) && !empty($connectionData['password'])) {
            $options['password'] = $connectionData['password'];
        }
        return new Client($dsn, $options, $driverOptions);
    }

    public function escapeColumn($column) {
        return $column;
    }

    public function escapeStr($str) {
        return $str;
    }

    public function escapeTable($table) {
        return $table;
    }

    public function fieldData($table) {
        return null;
    }

    public function limit($limit, $offset = 0) {
        return null;
    }

    public function listFields($table) {
    }

    public function listTables() {
        return [];
    }

    public function query($sql) {
        return new CDatabase_Driver_MongoDB_Result($this->link, $this->mongoDB, carr::get($this->dbConfig, 'object', true), $sql);
    }

    public function showError() {
    }

    public function getElapsedTime($start) {
        return round((microtime(true) - $start) * 1000, 2);
    }

    /**
     * Get a MongoDB collection.
     *
     * @param string $name
     *
     * @return Collection
     */
    public function getCollection($name) {
        return new CDatabase_Driver_MongoDB_Collection($this, $this->mongoDB->selectCollection($name));
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultPostProcessor() {
        return new CDatabase_Query_Processor_MongoDB();
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultQueryGrammar() {
        return new CDatabase_Query_Grammar_MongoDB();
    }

    /**
     * Dynamically pass methods to the connection.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters) {
        return call_user_func_array([$this->link, $method], $parameters);
    }

    /**
     * {@inheritdoc}
     *
     * @return CDatabase_Schema_Manager_MongoDB
     */
    public function getSchemaManager(CDatabase $db) {
        return new CDatabase_Schema_Manager_MongoDB($db);
    }

    /**
     * {@inheritdoc}
     *
     * @return CDatabase_Platform_MongooDB
     */
    public function getDatabasePlatform() {
        return new CDatabase_Platform_MongoDB();
    }

    /**
     * Get the name of the connected database.
     *
     * @return string
     */
    public function getDatabaseName() {
        return carr::path($this->dbConfig, 'connection.database');
    }
}
