<?php

defined('SYSPATH') or die('No direct access allowed.');

use Carbon\Carbon;

/**
 * @method string                       lastQuery()                                                  Returns the last query run.
 * @method array                        config()                                                     Return current config
 * @method mixed                        getConfig($key, $default = null)
 * @method CDatabase_Schema_Grammar     getSchemaGrammar()
 * @method CDatabase_TransactionManager getTransactionManager()
 * @method CDatabase_Schema_Builder     getSchemaBuilder()
 * @method CDatabase_Schema_Column      getColumn($table, $column)
 * @method string                       getTablePrefix()
 * @method array                        fetchAll($sql, array $params = [])
 * @method bool                         ping()
 * @method CDatabase_Query_Processor    getPostProcessor()
 * @method CDatabase_Query_Builder      newQuery()
 * @method CDatabase_Query_Builder      createQueryBuilder()
 * @method array                        getList($query)
 * @method array                        getArray($query)
 * @method mixed                        getValue($query)
 * @method object                       getRow($query)
 * @method string                       getDatabaseName()                                            Get the name of the connected database.
 * @method array                        getQueryLog()
 * @method void                         enableQueryLog()
 * @method void                         logQuery($query, $bindings, $time = null, $rowsCount = null) Log a query in the connection's query log.
 * @method bool                         isLogQuery()
 * @method string                       getDatabase()                                                Gets the name of the database this Connection is connected to.
 * @method CDatabase_Platform           getDatabasePlatform()
 * @method CDatabase_Schema_Manager     getSchemaManager()
 * @method string                       getName()
 * @method CDatabase_Query_Grammar      getQueryGrammar()
 * @method CEvent_Dispatcher            getEventDispatcher()
 * @method void                         setEventDispatcher(CEvent_Dispatcher $events)                Set the event dispatcher instance on the connection.
 * @method void                         listen($event, Closure $callback)
 * @method void                         reconnect()
 * @method CDatabase_Query_Builder      table($table)                                                Get Query Builder from table.
 * @method string                       driverName()
 * @method int                          countLastQuery()                                             Count the number of records in the last query, without LIMIT or OFFSET applied.
 * @method void                         connect()                                                    Simple connect method to get the database queries up and running.
 * @method void                         close()
 * @method CDatabase_Result             query($sql = '', $bindings = [], $useReadPdo = true)
 * @method array                        prepareBindings(array $bindings)                             Prepare the query bindings for execution.
 * @method CDatabase_Result             insert($table, $set)                                         Compiles an insert string and runs the query.
 * @method CDatabase_Result             update($table = '', $set = null, $where = null)              Compiles an update string and runs the query.
 * @method CDatabase_Result             delete($table = '', $where = [])                             Compiles a delete string and runs the query.
 * @method array                        listTables()
 * @method bool                         tableExists($table_name, $prefix = true)
 * @method string                       compileBinds($sql, $binds)                                   Combine a SQL statement with the bind values. Used for safe queries.
 * @method array                        fieldData($table)
 * @method string                       escape($value)
 * @method string                       escapeStr($str)
 * @method string                       escapeTable($table)
 * @method string                       escapeColumn($column)
 * @method string                       escapeLike($str)
 * @method CDatabase_Configuration      getConfiguration()
 * @method array                        listFields($table)
 *
 * @mixin CDatabase_Connection
 *
 * @see CDatabase_Connection
 */
class CDatabase {
    use CTrait_Compat_Database;
    use CTrait_ForwardsCalls;

    /**
     * Database instances.
     *
     * @var array
     */
    public static $instances = [];

    /**
     * Global benchmark.
     *
     * @var array
     */
    public static $benchmarks = [];

    protected static $isBenchmarkQuery;

    /**
     * Default Database.
     *
     * @var string
     */
    protected static $defaultConnection = 'default';

    /**
     * @var CDatabase_TransactionManager
     */
    protected static $transactionManager;

    protected $name;

    /**
     * Returns a singleton instance of Database.
     *
     * @param null|mixed $name
     * @param null|mixed $config
     * @param null|mixed $domain
     *
     * @return CDatabase
     *
     * @deprecated 1.6 use c::db()
     */
    public static function &instance($name = null, $config = null, $domain = null) {
        if ($name == null) {
            $name = static::$defaultConnection;
        }

        if (strlen($domain) == 0) {
            //get current domain
            $domain = CF::domain();
        }
        if ($name == null) {
            $name = 'default';
        }

        if (!isset(CDatabase::$instances[$domain])) {
            CDatabase::$instances[$domain] = [];
        }
        if (!isset(CDatabase::$instances[$domain][$name])) {
            // Create a new instance

            CDatabase::$instances[$domain][$name] = new CDatabase($config === null ? $name : $config, $domain);
        }

        return CDatabase::$instances[$domain][$name];
    }

    /**
     * Sets up the database configuration, loads the CDatabase_Driver.
     *
     * @param string|array $name
     * @param null|mixed   $domain deprecated params
     */
    public function __construct($name = null, $domain = null) {
        $this->name = $name;
    }

    /**
     * Returns the name of a given database instance.
     *
     * @param   CDatabase  instance of CDatabase
     * @param null|mixed $domain
     *
     * @return string
     */
    public static function instanceName(CDatabase $db, $domain = null) {
        if (strlen($domain) == 0) {
            //get current domain
            $domain = CF::domain();
        }

        return array_search($db, CDatabase::$instances[$domain], true);
    }

    /**
     * @param string|array $config
     */
    public static function resolveConfig($config) {
        if ($config == null) {
            $config = 'default';
        }

        return CDatabase_Config::resolve($config);
    }

    public static function enableBenchmark() {
        self::$isBenchmarkQuery = true;
    }

    public static function disableBenchmark() {
        self::$isBenchmarkQuery = false;
    }

    public function isBenchmarkQuery() {
        return self::$isBenchmarkQuery;
    }

    public static function benchmarkQuery($query, $time = null, $rowsCount = null) {
        if (self::isBenchmarkQuery()) {
            // Benchmark the query
            static::$benchmarks[] = ['query' => $query, 'time' => $time, 'rows' => $rowsCount, 'caller' => cdbg::callerInfo()];
        }
    }

    /**
     * Get a new raw query expression.
     *
     * @param mixed $value
     *
     * @return CDatabase_Query_Expression
     */
    public static function raw($value) {
        return new CDatabase_Query_Expression($value);
    }

    public static function resetBenchmarks() {
        static::$benchmarks = [];
    }

    public static function getBenchmarks() {
        return static::$benchmarks;
    }

    /**
     * @return CDatabase_Manager
     */
    public static function manager() {
        return CDatabase_Manager::instance();
    }

    public function connection($name = null) {
        return $this->manager()->connection($name ?: $this->name);
    }

    public function getConnection() {
        return $this->connection();
    }

    /**
     * @return CDatabase_ConnectionFactory
     */
    public static function connectionFactory() {
        return CDatabase_ConnectionFactory::instance();
    }

    /**
     * @return CDatabase_Analysis
     */
    public static function analysis() {
        return CDatabase_Analysis::instance();
    }

    public function __call($method, $arguments) {
        return $this->manager()->connection($this->name)->$method(...$arguments);
    }

    public static function transactionManager() {
        if (self::$transactionManager == null) {
            self::$transactionManager = new CDatabase_TransactionManager();
        }

        return self::$transactionManager;
    }
}
