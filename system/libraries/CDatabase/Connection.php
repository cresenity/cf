<?php

use Carbon\Carbon;
use Carbon\CarbonInterval;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Connection as DoctrineConnection;

/**
 * @see CDatabase
 */
class CDatabase_Connection implements CDatabase_ConnectionInterface {
    use CTrait_Compat_Database;
    use CDatabase_Trait_DetectDeadlock;
    use CDatabase_Trait_DetectLostConnection;
    use CDatabase_Trait_DetectConcurrencyErrors;
    use CDatabase_Trait_ManageTransaction;
    use CTrait_Helper_InteractsWithTime;

    /**
     * The active PDO connection.
     *
     * @var \PDO|\Closure
     */
    protected $pdo;

    /**
     * The active PDO connection used for reads.
     *
     * @var \PDO|\Closure
     */
    protected $readPdo;

    /**
     * The name of the connected database.
     *
     * @var string
     */
    protected $database;

    /**
     * The type of the connection.
     *
     * @var null|string
     */
    protected $readWriteType;

    /**
     * The table prefix for the connection.
     *
     * @var string
     */
    protected $tablePrefix = '';

    /**
     * The database connection configuration options.
     *
     * @var array
     */
    protected $config = [];

    /**
     * The reconnector instance for the connection.
     *
     * @var callable
     */
    protected $reconnector;

    /**
     * The query grammar implementation.
     *
     * @var \CDatabase_Query_Grammar
     */
    protected $queryGrammar;

    /**
     * The schema grammar implementation.
     *
     * @var \CDatabase_Schema_Grammar
     */
    protected $schemaGrammar;

    /**
     * The query post processor implementation.
     *
     * @var \CDatabase_Query_Processor
     */
    protected $postProcessor;

    /**
     * The event dispatcher instance.
     *
     * @var \CEvent_DispatcherInterface
     */
    protected $events;

    /**
     * The default fetch mode of the connection.
     *
     * @var int
     */
    protected $fetchMode = PDO::FETCH_OBJ;

    /**
     * The number of active transactions.
     *
     * @var int
     */
    protected $transactions = 0;

    /**
     * The transaction manager instance.
     *
     * @var \CDatabase_TransactionManager
     */
    protected $transactionsManager;

    /**
     * Indicates if changes have been made to the database.
     *
     * @var bool
     */
    protected $recordsModified = false;

    /**
     * Indicates if the connection should use the "write" PDO connection.
     *
     * @var bool
     */
    protected $readOnWriteConnection = false;

    /**
     * All of the queries run against the connection.
     *
     * @var array
     */
    protected $queryLog = [];

    /**
     * Indicates whether queries are being logged.
     *
     * @var bool
     */
    protected $loggingQueries = false;

    /**
     * Indicates whether the query log carries a `compiled` SQL string.
     *
     * Building it runs every binding through `PDO::quote()` on the write
     * connection, so logging a read query can open a write connection for
     * nothing at all. Set false where that cost is unwanted; the key stays in
     * the log as null, and `lastQuery()` returns null with it.
     *
     * @var bool
     */
    public static $compileQueryLog = true;

    /**
     * The duration of all executed queries in milliseconds.
     *
     * @var float
     */
    protected $totalQueryDuration = 0.0;

    /**
     * All of the registered query duration handlers.
     *
     * @var array
     */
    protected $queryDurationHandlers = [];

    /**
     * Indicates if the connection is in a "dry run".
     *
     * @var bool
     */
    protected $pretending = false;

    /**
     * All of the callbacks that should be invoked before a query is executed.
     *
     * @var \Closure[]
     */
    protected $beforeExecutingCallbacks = [];

    /**
     * The instance of Doctrine connection.
     *
     * @var \Doctrine\DBAL\Connection
     */
    protected $doctrineConnection;

    /**
     * Type mappings that should be registered with new Doctrine connections.
     *
     * @var array<string, string>
     */
    protected $doctrineTypeMappings = [];

    /**
     * The connection resolvers.
     *
     * @var \Closure[]
     */
    protected static $resolvers = [];

    /**
     * The schema manager.
     *
     * @var CDatabase_Schema_Manager
     */
    protected $schemaManager;

    /**
     * @var CDatabase_Platform
     */
    protected $platform;

    /**
     * @var CDatabase_Configuration
     */
    protected $configuration;

    /**
     * Create a new database connection instance.
     *
     * @param \PDO|\Closure $pdo
     * @param string        $database
     * @param string        $tablePrefix
     * @param array         $config
     *
     * @return void
     */
    public function __construct($pdo, $database = '', $tablePrefix = '', array $config = []) {
        $this->pdo = $pdo;

        // First we will setup the default properties. We keep track of the DB
        // name we are connected to since it is needed when some reflective
        // type commands are run such as checking whether a table exists.
        $this->database = $database;

        $this->tablePrefix = $tablePrefix;

        $this->config = $config;

        $this->events = CEvent::dispatcher();

        // We need to initialize a query grammar and the query post processors
        // which are both very important parts of the database abstractions
        // so we initialize these to their default values while starting.
        $this->useDefaultQueryGrammar();

        $this->useDefaultPostProcessor();
        if (carr::get($this->config, 'benchmark')) {
            $this->enableQueryLog();
        }
        $this->configuration = new CDatabase_Configuration();

        $this->transactionsManager = CDatabase::transactionManager();
    }

    /**
     * Set the query grammar to the default implementation.
     *
     * @return void
     */
    public function useDefaultQueryGrammar() {
        $this->queryGrammar = $this->getDefaultQueryGrammar();
    }

    /**
     * Get the default query grammar instance.
     *
     * @return \CDatabase_Query_Grammar
     */
    protected function getDefaultQueryGrammar() {
        ($grammar = new CDatabase_Query_Grammar())->setConnection($this);

        return $grammar;
    }

    /**
     * Set the schema grammar to the default implementation.
     *
     * @return void
     */
    public function useDefaultSchemaGrammar() {
        $this->schemaGrammar = $this->getDefaultSchemaGrammar();
    }

    /**
     * Get the default schema grammar instance.
     *
     * @return null|\CDatabase_Schema_Grammar
     */
    protected function getDefaultSchemaGrammar() {
    }

    /**
     * Set the query post processor to the default implementation.
     *
     * @return void
     */
    public function useDefaultPostProcessor() {
        $this->postProcessor = $this->getDefaultPostProcessor();
    }

    /**
     * Get the default post processor instance.
     *
     * @return \CDatabase_Query_Processor
     */
    protected function getDefaultPostProcessor() {
        return new CDatabase_Query_Processor();
    }

    /**
     * Get a schema builder instance for the connection.
     *
     * @return \CDatabase_Schema_Builder
     */
    public function getSchemaBuilder() {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new CDatabase_Schema_Builder($this);
    }

    /**
     * Gets the SchemaManager that can be used to inspect or change the
     * database schema through the connection.
     *
     * @return CDatabase_Schema_Manager
     */
    public function getSchemaManager() {
        throw new Exception('This connection doesnt have schema manager');
    }

    /**
     * Begin a fluent query against a database table.
     *
     * @param \Closure|\CDatabase_Query_Builder|\CDatabase_Query_Expression|string $table
     * @param null|string                                                          $as
     *
     * @return \CDatabase_Query_Builder
     */
    public function table($table, $as = null) {
        return $this->newQuery()->from($table, $as);
    }

    /**
     * Get a new query builder instance.
     *
     * @param null|string $query
     * @param array       $bindings
     * @param mixed       $useReadPdo
     *
     * @return \CDatabase_Query_Builder|CDatabase_ResultData
     */
    public function query($query = null, $bindings = [], $useReadPdo = true) {
        if ($query != null) {
            return new CDatabase_ResultData($this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo) {
                if ($this->pretending()) {
                    return [];
                }

                // First we will create a statement for the query. Then, we will set the fetch
                // mode and prepare the bindings for the query. Once that's done we will be
                // ready to execute the query against the database and return the cursor.
                $statement = $this->prepared($this->getPdoForSelect($useReadPdo)
                    ->prepare($query));

                $this->bindValues(
                    $statement,
                    $this->prepareBindings($bindings)
                );

                // Next, we'll execute the query against the database and return the statement
                // so we can return the cursor. The cursor will use a PHP generator to give
                // back one row at a time without using a bunch of memory to render them.
                $statement->execute();

                return $statement;
            }, $useReadPdo));
        }

        return $this->newQuery();
    }

    /**
     * Get a new query builder instance.
     *
     * @return \CDatabase_Query_Builder
     */
    public function newQuery() {
        return new CDatabase_Query_Builder(
            $this,
            $this->getQueryGrammar(),
            $this->getPostProcessor()
        );
    }

    /**
     * Get a new query builder instance.
     *
     * @deprecated since 1.6, use newQuery()
     *
     * @return \CDatabase_Query_Builder
     */
    public function createQueryBuilder() {
        return $this->newQuery();
    }

    /**
     * Run a select statement and return a single result.
     *
     * @param string $query
     * @param array  $bindings
     * @param bool   $useReadPdo
     *
     * @return mixed
     */
    public function selectOne($query, $bindings = [], $useReadPdo = true) {
        $records = $this->select($query, $bindings, $useReadPdo);

        return array_shift($records);
    }

    /**
     * Run a select statement and return the first column of the first row.
     *
     * @param string $query
     * @param array  $bindings
     * @param bool   $useReadPdo
     *
     * @throws \CDatabase_Exception_MultipleColumnsSelectedException
     *
     * @return mixed
     */
    public function scalar($query, $bindings = [], $useReadPdo = true) {
        $record = $this->selectOne($query, $bindings, $useReadPdo);

        if (is_null($record)) {
            return null;
        }

        $record = (array) $record;

        if (count($record) > 1) {
            throw new CDatabase_Exception_MultipleColumnsSelectedException();
        }

        return reset($record);
    }

    /**
     * Run a select statement against the database.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return array
     */
    public function selectFromWriteConnection($query, $bindings = []) {
        return $this->select($query, $bindings, false);
    }

    /**
     * Run a select statement against the database.
     *
     * @param string $query
     * @param array  $bindings
     * @param bool   $useReadPdo
     *
     * @return array
     */
    public function select($query, $bindings = [], $useReadPdo = true) {
        return $this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo) {
            if ($this->pretending()) {
                return [];
            }

            // For select statements, we'll simply execute the query and return an array
            // of the database result set. Each element in the array will be a single
            // row from the database table, and will either be an array or objects.
            $statement = $this->prepared(
                $this->getPdoForSelect($useReadPdo)->prepare($query)
            );

            $this->bindValues($statement, $this->prepareBindings($bindings));

            $statement->execute();

            return $statement->fetchAll();
        }, $useReadPdo);
    }

    /**
     * Run a select statement against the database and returns all of the result sets.
     *
     * @param string $query
     * @param array  $bindings
     * @param bool   $useReadPdo
     *
     * @return array
     */
    public function selectResultSets($query, $bindings = [], $useReadPdo = true) {
        return $this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo) {
            if ($this->pretending()) {
                return [];
            }

            $statement = $this->prepared(
                $this->getPdoForSelect($useReadPdo)->prepare($query)
            );

            $this->bindValues($statement, $this->prepareBindings($bindings));

            $statement->execute();

            $sets = [];

            do {
                $sets[] = $statement->fetchAll();
            } while ($statement->nextRowset());

            return $sets;
        }, $useReadPdo);
    }

    /**
     * Run a select statement against the database and returns a generator.
     *
     * @param string $query
     * @param array  $bindings
     * @param bool   $useReadPdo
     *
     * @return \Generator
     */
    public function cursor($query, $bindings = [], $useReadPdo = true) {
        $statement = $this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo) {
            if ($this->pretending()) {
                return [];
            }

            // First we will create a statement for the query. Then, we will set the fetch
            // mode and prepare the bindings for the query. Once that's done we will be
            // ready to execute the query against the database and return the cursor.
            $statement = $this->prepared($this->getPdoForSelect($useReadPdo)
                ->prepare($query));

            $this->bindValues(
                $statement,
                $this->prepareBindings($bindings)
            );

            // Next, we'll execute the query against the database and return the statement
            // so we can return the cursor. The cursor will use a PHP generator to give
            // back one row at a time without using a bunch of memory to render them.
            $statement->execute();

            return $statement;
        }, $useReadPdo);

        while ($record = $statement->fetch()) {
            yield $record;
        }
    }

    /**
     * Configure the PDO prepared statement.
     *
     * @param \PDOStatement $statement
     *
     * @return \PDOStatement
     */
    protected function prepared(PDOStatement $statement) {
        $statement->setFetchMode($this->fetchMode);

        $this->event(new CDatabase_Event_StatementPrepared($this, $statement));

        return $statement;
    }

    /**
     * Get the PDO connection to use for a select query.
     *
     * @param bool $useReadPdo
     *
     * @return \PDO
     */
    protected function getPdoForSelect($useReadPdo = true) {
        return $useReadPdo ? $this->getReadPdo() : $this->getPdo();
    }

    /**
     * Run an insert statement against the database.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return bool
     */
    public function insertWithQuery($query, $bindings = []) {
        return $this->statement($query, $bindings);
    }

    /**
     * Compiles an insert string and runs the query.
     *
     * @param string $table table name
     * @param array  $set   array of key/value pairs to insert
     *
     * @return CDatabase_Result Query result
     */
    public function insert($table, $set) {
        return $this->table($table)->insert($set);
    }

    /**
     * Insert a new record and get the value of the primary key.
     *
     * @param string $table table name
     * @param array  $set   array of key/value pairs to insert
     *
     * @return int
     */
    public function insertGetId($table, $set) {
        return $this->table($table)->insertGetId($set);
    }

    /**
     * Run an update statement against the database.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return int
     */
    public function updateWithQuery($query, $bindings = []) {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Compiles an update string and runs the query.
     *
     * @param string $table table name
     * @param array  $set   associative array of update values
     * @param array  $where where clause
     *
     * @return int
     */
    public function update($table = '', $set = null, $where = null) {
        return $this->table($table)->where($where)->update($set);
    }

    /**
     * Run a delete statement against the database.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return int
     */
    public function deleteWithQuery($query, $bindings = []) {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Compiles a delete string and runs the query.
     *
     * @param string $table table name
     * @param array  $where where clause
     *
     * @return int
     */
    public function delete($table = '', $where = []) {
        if ($where == null || count($where) < 1) {
            throw new CDatabase_Exception(c::__('database.must_use_where'));
        }
        $builder = $this->table($table);

        return $builder->where($where)->delete();
    }

    /**
     * Execute an SQL statement and return the boolean result.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return bool
     */
    public function statement($query, $bindings = []) {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return true;
            }

            $statement = $this->getPdo()->prepare($query);

            $this->bindValues($statement, $this->prepareBindings($bindings));

            $this->recordsHaveBeenModified();

            return $statement->execute();
        });
    }

    /**
     * Run an SQL statement and get the number of rows affected.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return int
     */
    public function affectingStatement($query, $bindings = []) {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return 0;
            }

            // For update or delete statements, we want to get the number of rows affected
            // by the statement and return that back to the developer. We'll first need
            // to execute the statement and then we'll use PDO to fetch the affected.
            $statement = $this->getPdo()->prepare($query);

            $this->bindValues($statement, $this->prepareBindings($bindings));

            $statement->execute();

            $this->recordsHaveBeenModified(
                ($count = $statement->rowCount()) > 0
            );

            return $count;
        });
    }

    /**
     * Run a raw, unprepared query against the PDO connection.
     *
     * @param string $query
     *
     * @return bool
     */
    public function unprepared($query) {
        return $this->run($query, [], function ($query) {
            if ($this->pretending()) {
                return true;
            }

            $this->recordsHaveBeenModified(
                $change = $this->getPdo()->exec($query) !== false
            );

            return $change;
        });
    }

    /**
     * Execute the given callback in "dry run" mode.
     *
     * @param \Closure $callback
     *
     * @return array
     */
    public function pretend(Closure $callback) {
        return $this->withFreshQueryLog(function () use ($callback) {
            $this->pretending = true;

            // Basically to make the database connection "pretend", we will just return
            // the default values for all the query methods, then we will return an
            // array of queries that were "executed" within the Closure callback.
            $callback($this);

            $this->pretending = false;

            return $this->queryLog;
        });
    }

    /**
     * Execute the given callback in "dry run" mode.
     *
     * @param \Closure $callback
     *
     * @return array
     */
    protected function withFreshQueryLog($callback) {
        $loggingQueries = $this->loggingQueries;

        // First we will back up the value of the logging queries property and then
        // we'll be ready to run callbacks. This query log will also get cleared
        // so we will have a new log of all the queries that are executed now.
        $this->enableQueryLog();

        $this->queryLog = [];

        // Now we'll execute this callback and capture the result. Once it has been
        // executed we will restore the value of query logging and give back the
        // value of the callback so the original callers can have the results.
        $result = $callback();

        $this->loggingQueries = $loggingQueries;

        return $result;
    }

    /**
     * Bind values to their parameters in the given statement.
     *
     * @param \PDOStatement $statement
     * @param array         $bindings
     *
     * @return void
     */
    public function bindValues($statement, $bindings) {
        foreach ($bindings as $key => $value) {
            $statement->bindValue(
                is_string($key) ? $key : $key + 1,
                $value,
                is_int($value) ? PDO::PARAM_INT : (is_resource($value) ? PDO::PARAM_LOB : PDO::PARAM_STR)
            );
        }
    }

    /**
     * Prepare the query bindings for execution.
     *
     * @param array $bindings
     *
     * @return array
     */
    public function prepareBindings(array $bindings) {
        $grammar = $this->getQueryGrammar();

        foreach ($bindings as $key => $value) {
            // We need to transform all instances of DateTimeInterface into the actual
            // date string. Each query grammar maintains its own date string format
            // so we'll just ask the grammar for the format to get from the date.
            if ($value instanceof DateTimeInterface) {
                $bindings[$key] = $value->format($grammar->getDateFormat());
            } elseif (is_bool($value)) {
                $bindings[$key] = (int) $value;
            }
        }

        return $bindings;
    }

    /**
     * Run a SQL statement and log its execution context.
     *
     * @param string   $query
     * @param array    $bindings
     * @param \Closure $callback
     * @param bool     $useReadPdo the callback ran $query on the read connection - passed
     *                             through to logQuery() so the query log doesn't open a
     *                             write connection just to quote a read query's bindings
     *
     * @throws \CDatabase_Exception_QueryException
     *
     * @return mixed
     */
    protected function run($query, $bindings, Closure $callback, $useReadPdo = false) {
        foreach ($this->beforeExecutingCallbacks as $beforeExecutingCallback) {
            $beforeExecutingCallback($query, $bindings, $this);
        }

        $this->reconnectIfMissingConnection();

        $start = microtime(true);

        // Here we will run this query. If an exception occurs we'll determine if it was
        // caused by a connection that has been lost. If that is the cause, we'll try
        // to re-establish connection and re-run the query with a fresh connection.
        try {
            $result = $this->runQueryCallback($query, $bindings, $callback);
        } catch (CDatabase_Exception_QueryException $e) {
            $result = $this->handleQueryException(
                $e,
                $query,
                $bindings,
                $callback
            );
        }

        // Once we have run the query we will calculate the time that it took to run and
        // then log the query, bindings, and execution time so we will report them on
        // the event that the developer needs them. We'll log time in milliseconds.
        $this->logQuery(
            $query,
            $bindings,
            $this->getElapsedTime($start),
            $useReadPdo
        );

        return $result;
    }

    /**
     * Run a SQL statement.
     *
     * @param string   $query
     * @param array    $bindings
     * @param \Closure $callback
     *
     * @throws \CDatabase_Exception_QueryException
     *
     * @return mixed
     */
    protected function runQueryCallback($query, $bindings, Closure $callback) {
        // To execute the statement, we'll simply call the callback, which will actually
        // run the SQL against the PDO connection. Then we can calculate the time it
        // took to execute and log the query SQL, bindings and time in our memory.
        try {
            return $callback($query, $bindings);
        } catch (Exception $e) {
            // If an exception occurs when attempting to run a query, we'll format the error
            // message to include the bindings with SQL, which will make this exception a
            // lot more helpful to the developer instead of just the database's errors.
            throw new CDatabase_Exception_QueryException(
                $this->getName(),
                $query,
                $this->prepareBindings($bindings),
                $e
            );
        }
    }

    /**
     * Log a query in the connection's query log.
     *
     * @param string     $query
     * @param array      $bindings
     * @param null|float $time
     * @param bool       $useReadPdo the query ran on the read connection, so compile the
     *                               'compiled' log entry against that connection too instead
     *                               of opening the write one just to quote it
     *
     * @return void
     */
    public function logQuery($query, $bindings, $time = null, $useReadPdo = false) {
        $this->totalQueryDuration += $time ?? 0.0;

        $this->event(new CDatabase_Event_QueryExecuted($query, $bindings, $time, $this));

        if ($this->loggingQueries) {
            $this->queryLog[] = [
                'query' => $query,
                'bindings' => $bindings,
                'time' => $time,
                'compiled' => static::$compileQueryLog ? $this->compileBinds($query, $bindings, $useReadPdo) : null,
            ];
        }
    }

    /**
     * Get the elapsed time since a given starting point.
     *
     * @param int $start
     *
     * @return float
     */
    protected function getElapsedTime($start) {
        return round((microtime(true) - $start) * 1000, 2);
    }

    /**
     * Register a callback to be invoked when the connection queries for longer than a given amount of time.
     *
     * @param \DateTimeInterface|\Carbon\CarbonInterval|float|int $threshold
     * @param callable                                            $handler
     *
     * @return void
     */
    public function whenQueryingForLongerThan($threshold, $handler) {
        $threshold = $threshold instanceof DateTimeInterface
            ? $this->secondsUntil($threshold) * 1000
            : $threshold;

        $threshold = $threshold instanceof CarbonInterval
            ? $threshold->totalMilliseconds
            : $threshold;

        $this->queryDurationHandlers[] = [
            'has_run' => false,
            'handler' => $handler,
        ];

        $key = count($this->queryDurationHandlers) - 1;

        $this->listen(function ($event) use ($threshold, $handler, $key) {
            if (!$this->queryDurationHandlers[$key]['has_run'] && $this->totalQueryDuration() > $threshold) {
                $handler($this, $event);

                $this->queryDurationHandlers[$key]['has_run'] = true;
            }
        });
    }

    /**
     * Allow all the query duration handlers to run again, even if they have already run.
     *
     * @return void
     */
    public function allowQueryDurationHandlersToRunAgain() {
        foreach ($this->queryDurationHandlers as $key => $queryDurationHandler) {
            $this->queryDurationHandlers[$key]['has_run'] = false;
        }
    }

    /**
     * Get the duration of all run queries in milliseconds.
     *
     * @return float
     */
    public function totalQueryDuration() {
        return $this->totalQueryDuration;
    }

    /**
     * Reset the duration of all run queries.
     *
     * @return void
     */
    public function resetTotalQueryDuration() {
        $this->totalQueryDuration = 0.0;
    }

    /**
     * Handle a query exception.
     *
     * @param \CDatabase_Exception_QueryException $e
     * @param string                              $query
     * @param array                               $bindings
     * @param \Closure                            $callback
     *
     * @throws \CDatabase_Exception_QueryException
     *
     * @return mixed
     */
    protected function handleQueryException(CDatabase_Exception_QueryException $e, $query, $bindings, Closure $callback) {
        if ($this->transactions >= 1) {
            throw $e;
        }

        return $this->tryAgainIfCausedByLostConnection(
            $e,
            $query,
            $bindings,
            $callback
        );
    }

    /**
     * Handle a query exception that occurred during query execution.
     *
     * @param \CDatabase_Exception_QueryException $e
     * @param string                              $query
     * @param array                               $bindings
     * @param \Closure                            $callback
     *
     * @throws \CDatabase_Exception_QueryException
     *
     * @return mixed
     */
    protected function tryAgainIfCausedByLostConnection(CDatabase_Exception_QueryException $e, $query, $bindings, Closure $callback) {
        if ($this->causedByLostConnection($e->getPrevious())) {
            $this->reconnect();

            return $this->runQueryCallback($query, $bindings, $callback);
        }

        throw $e;
    }

    /**
     * Reconnect to the database.
     *
     * @throws \CDatabase_Exception_LostConnectionException
     *
     * @return mixed|false
     */
    public function reconnect() {
        if (is_callable($this->reconnector)) {
            $this->doctrineConnection = null;

            return call_user_func($this->reconnector, $this);
        }

        throw new CDatabase_Exception_LostConnectionException('Lost connection and no reconnector available.');
    }

    /**
     * Reconnect to the database if a PDO connection is missing.
     *
     * @return void
     */
    public function reconnectIfMissingConnection() {
        if (is_null($this->pdo)) {
            $this->reconnect();
        }
    }

    /**
     * Disconnect from the underlying PDO connection.
     *
     * @return void
     */
    public function disconnect() {
        $this->setPdo(null)->setReadPdo(null);

        $this->doctrineConnection = null;
    }

    /**
     * Register a hook to be run just before a database query is executed.
     *
     * @param \Closure $callback
     *
     * @return $this
     */
    public function beforeExecuting(Closure $callback) {
        $this->beforeExecutingCallbacks[] = $callback;

        return $this;
    }

    /**
     * Register a database query listener with the connection.
     *
     * @param \Closure $callback
     *
     * @return void
     */
    public function listen(Closure $callback) {
        $this->events->listen(CDatabase_Event_QueryExecuted::class, $callback);
    }

    /**
     * Fire an event for this connection.
     *
     * @param string $event
     *
     * @return null|array
     */
    protected function fireConnectionEvent($event) {
        if (!isset($this->events)) {
            return;
        }
        switch ($event) {
            case 'beganTransaction':
                return $this->events->dispatch(new CDatabase_Event_Transaction_Beginning($this));
            case 'committed':
                return $this->events->dispatch(new CDatabase_Event_Transaction_Committed($this));
            case 'committing':
                return $this->events->dispatch(new CDatabase_Event_Transaction_Committing($this));
            case 'rollingBack':
                return $this->events->dispatch(new CDatabase_Event_Transaction_RolledBack($this));
        }
    }

    /**
     * Fire the given event if possible.
     *
     * @param mixed $event
     *
     * @return void
     */
    protected function event($event) {
        $this->events->dispatch($event);
    }

    /**
     * Get a new raw query expression.
     *
     * @param mixed $value
     *
     * @return \CDatabase_Query_Expression
     */
    public function raw($value) {
        return new CDatabase_Query_Expression($value);
    }

    /**
     * Escape a value for safe SQL embedding.
     *
     * A DateTimeInterface (Carbon included) is converted to its string form before escaping -
     * Carbon via its own __toString(), same conversion compileBinds() already applies to Carbon
     * bind values; plain DateTime/DateTimeImmutable (no __toString()) via format('Y-m-d H:i:s')
     * instead, so passing one directly no longer fatals with "could not be converted to string".
     *
     * @param null|string|float|int|bool|DateTimeInterface $value
     * @param bool                                         $binary
     * @param bool                                         $useReadPdo quote against the read connection instead of
     *                                                                 the write one (only compileBinds() sets this)
     *
     * @return string
     */
    public function escape($value, $binary = false, $useReadPdo = false) {
        if ($value instanceof DateTimeInterface) {
            // Carbon defines __toString() (same conversion compileBinds() already applies to
            // Carbon bind values); plain DateTime/DateTimeImmutable don't, so casting those
            // would fatal with "could not be converted to string" instead of escaping them.
            $value = method_exists($value, '__toString') ? (string) $value : $value->format('Y-m-d H:i:s');
        }

        if ($value === null) {
            return 'null';
        } elseif ($binary) {
            return $this->escapeBinary($value);
        } elseif (is_int($value) || is_float($value)) {
            return (string) $value;
        } elseif (is_bool($value)) {
            return $this->escapeBool($value);
        } else {
            if (cstr::contains($value, "\00")) {
                throw new RuntimeException('Strings with null bytes cannot be escaped. Use the binary escape option.');
            }

            if (preg_match('//u', $value) === false) {
                throw new RuntimeException('Strings with invalid UTF-8 byte sequences cannot be escaped.');
            }

            return $this->escapeString($value, $useReadPdo);
        }
    }

    /**
     * Escape a string value for safe SQL embedding.
     *
     * @param string $value
     *
     * @return string
     */
    protected function escapeString($value, $useReadPdo = false) {
        return $this->getPdoForSelect($useReadPdo)->quote($value);
    }

    /**
     * Escape a boolean value for safe SQL embedding.
     *
     * @param bool $value
     *
     * @return string
     */
    protected function escapeBool($value) {
        return $value ? '1' : '0';
    }

    /**
     * Escape a binary value for safe SQL embedding.
     *
     * @param string $value
     *
     * @return string
     */
    protected function escapeBinary($value) {
        throw new RuntimeException('The database connection does not support escaping binary values.');
    }

    /**
     * Determine if the database connection has modified any database records.
     *
     * @return bool
     */
    public function hasModifiedRecords() {
        return $this->recordsModified;
    }

    /**
     * Indicate if any records have been modified.
     *
     * @param bool $value
     *
     * @return void
     */
    public function recordsHaveBeenModified($value = true) {
        if (!$this->recordsModified) {
            $this->recordsModified = $value;
        }
    }

    /**
     * Set the record modification state.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setRecordModificationState(bool $value) {
        $this->recordsModified = $value;

        return $this;
    }

    /**
     * Reset the record modification state.
     *
     * @return void
     */
    public function forgetRecordModificationState() {
        $this->recordsModified = false;
    }

    /**
     * Indicate that the connection should use the write PDO connection for reads.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function useWriteConnectionWhenReading($value = true) {
        $this->readOnWriteConnection = $value;

        return $this;
    }

    /**
     * Is Doctrine available?
     *
     * @return bool
     */
    public function isDoctrineAvailable() {
        return class_exists('Doctrine\DBAL\Connection');
    }

    /**
     * Indicates whether native alter operations will be used when dropping, renaming, or modifying columns, even if Doctrine DBAL is installed.
     *
     * @return bool
     */
    public function usingNativeSchemaOperations() {
        return !$this->isDoctrineAvailable() || CDatabase_Schema_Builder::$alwaysUsesNativeSchemaOperationsIfPossible;
    }

    /**
     * Get a Doctrine Schema Column instance.
     *
     * @param string $table
     * @param string $column
     *
     * @return \Doctrine\DBAL\Schema\Column
     */
    public function getDoctrineColumn($table, $column) {
        $schema = $this->getDoctrineSchemaManager();

        return $schema->introspectTable($table)->getColumn($column);
    }

    /**
     * Get the Doctrine DBAL schema manager for the connection.
     *
     * @return \Doctrine\DBAL\Schema\AbstractSchemaManager
     */
    public function getDoctrineSchemaManager() {
        $connection = $this->getDoctrineConnection();

        return $connection->createSchemaManager();
    }

    /**
     * Get the Doctrine DBAL database connection instance.
     *
     * @return \Doctrine\DBAL\Connection
     */
    public function getDoctrineConnection() {
        if (is_null($this->doctrineConnection)) {
            $driver = $this->getDoctrineDriver();

            $this->doctrineConnection = new DoctrineConnection(array_filter([
                'pdo' => $this->getPdo(),
                'dbname' => $this->getDatabaseName(),
                'driver' => $driver->getName(),
                'serverVersion' => $this->getConfig('server_version'),
            ]), $driver);

            foreach ($this->doctrineTypeMappings as $name => $type) {
                $this->doctrineConnection
                    ->getDatabasePlatform()
                    ->registerDoctrineTypeMapping($type, $name);
            }
        }

        return $this->doctrineConnection;
    }

    /**
     * Register a custom Doctrine mapping type.
     *
     * @param Type|class-string<Type> $class
     * @param string                  $name
     * @param string                  $type
     *
     * @throws \Doctrine\DBAL\Exception
     * @throws \RuntimeException
     *
     * @return void
     */
    public function registerDoctrineType($class, string $name, string $type): void {
        if (!$this->isDoctrineAvailable()) {
            throw new RuntimeException(
                'Registering a custom Doctrine type requires Doctrine DBAL (doctrine/dbal).'
            );
        }

        if (!Type::hasType($name)) {
            Type::getTypeRegistry()
                ->register($name, is_string($class) ? new $class() : $class);
        }

        $this->doctrineTypeMappings[$name] = $type;
    }

    /**
     * Get the current PDO connection.
     *
     * @return \PDO
     */
    public function getPdo() {
        if ($this->pdo instanceof Closure) {
            return $this->pdo = call_user_func($this->pdo);
        }

        return $this->pdo;
    }

    /**
     * Get the current PDO connection parameter without executing any reconnect logic.
     *
     * @return null|\PDO|\Closure
     */
    public function getRawPdo() {
        return $this->pdo;
    }

    /**
     * Get the current PDO connection used for reading.
     *
     * @return \PDO
     */
    public function getReadPdo() {
        if ($this->transactions > 0) {
            return $this->getPdo();
        }

        if ($this->readOnWriteConnection
            || ($this->recordsModified && $this->getConfig('sticky'))
        ) {
            return $this->getPdo();
        }

        if ($this->readPdo instanceof Closure) {
            return $this->readPdo = call_user_func($this->readPdo);
        }

        return $this->readPdo ?: $this->getPdo();
    }

    /**
     * Get the current read PDO connection parameter without executing any reconnect logic.
     *
     * @return null|\PDO|\Closure
     */
    public function getRawReadPdo() {
        return $this->readPdo;
    }

    /**
     * Set the PDO connection.
     *
     * @param null|\PDO|\Closure $pdo
     *
     * @return $this
     */
    public function setPdo($pdo) {
        $this->transactions = 0;

        $this->pdo = $pdo;

        return $this;
    }

    /**
     * Set the PDO connection used for reading.
     *
     * @param null|\PDO|\Closure $pdo
     *
     * @return $this
     */
    public function setReadPdo($pdo) {
        $this->readPdo = $pdo;

        return $this;
    }

    /**
     * Set the reconnect instance on the connection.
     *
     * @param callable $reconnector
     *
     * @return $this
     */
    public function setReconnector(callable $reconnector) {
        $this->reconnector = $reconnector;

        return $this;
    }

    /**
     * Get the database connection name.
     *
     * @return null|string
     */
    public function getName() {
        return $this->getConfig('name');
    }

    /**
     * Get the database connection full name.
     *
     * @return null|string
     */
    public function getNameWithReadWriteType() {
        return $this->getName() . ($this->readWriteType ? '::' . $this->readWriteType : '');
    }

    /**
     * Get an option from the configuration options.
     *
     * @param null|string $option
     *
     * @return mixed
     */
    public function getConfig($option = null) {
        return carr::get($this->config, $option);
    }

    /**
     * Get all configuration options.
     *
     * @return mixed
     */
    public function getConfigs() {
        return $this->config;
    }

    /**
     * Get the PDO driver name.
     *
     * @return string
     */
    public function getDriverName() {
        return $this->getConfig('driver');
    }

    /**
     * Get the query grammar used by the connection.
     *
     * @return \CDatabase_Query_Grammar
     */
    public function getQueryGrammar() {
        return $this->queryGrammar;
    }

    /**
     * Set the query grammar used by the connection.
     *
     * @param \CDatabase_Query_Grammar $grammar
     *
     * @return $this
     */
    public function setQueryGrammar(CDatabase_Query_Grammar $grammar) {
        $this->queryGrammar = $grammar;

        return $this;
    }

    /**
     * Get the schema grammar used by the connection.
     *
     * @return \CDatabase_Schema_Grammar
     */
    public function getSchemaGrammar() {
        return $this->schemaGrammar;
    }

    /**
     * Set the schema grammar used by the connection.
     *
     * @param \CDatabase_Schema_Grammar $grammar
     *
     * @return $this
     */
    public function setSchemaGrammar(CDatabase_Schema_Grammar $grammar) {
        $this->schemaGrammar = $grammar;

        return $this;
    }

    /**
     * Get the query post processor used by the connection.
     *
     * @return \CDatabase_Query_Processor
     */
    public function getPostProcessor() {
        return $this->postProcessor;
    }

    /**
     * Set the query post processor used by the connection.
     *
     * @param \CDatabase_Query_Processor $processor
     *
     * @return $this
     */
    public function setPostProcessor(CDatabase_Query_Processor $processor) {
        $this->postProcessor = $processor;

        return $this;
    }

    /**
     * Get the event dispatcher used by the connection.
     *
     * @return \CEvent_Dispatcher
     */
    public function getEventDispatcher() {
        return $this->events;
    }

    /**
     * Set the event dispatcher instance on the connection.
     *
     * @param \CEvent_Dispatcher $events
     *
     * @return $this
     */
    public function setEventDispatcher(CEvent_Dispatcher $events) {
        $this->events = $events;

        return $this;
    }

    /**
     * Unset the event dispatcher for this connection.
     *
     * @return void
     */
    public function unsetEventDispatcher() {
        $this->events = null;
    }

    /**
     * Set the transaction manager instance on the connection.
     *
     * @param \CDatabase_TransactionManager $manager
     *
     * @return $this
     */
    public function setTransactionManager($manager) {
        $this->transactionsManager = $manager;

        return $this;
    }

    /**
     * Get  current transaction manager instance on the connection.
     *
     * @return CDatabase_TransactionManager
     */
    public function getTransactionManager() {
        return $this->transactionsManager;
    }

    /**
     * Unset the transaction manager for this connection.
     *
     * @return void
     */
    public function unsetTransactionManager() {
        $this->transactionsManager = null;
    }

    /**
     * Determine if the connection is in a "dry run".
     *
     * @return bool
     */
    public function pretending() {
        return $this->pretending === true;
    }

    /**
     * Get the connection query log.
     *
     * @return array
     */
    public function getQueryLog() {
        return $this->queryLog;
    }

    /**
     * Clear the query log.
     *
     * @return void
     */
    public function flushQueryLog() {
        $this->queryLog = [];
    }

    /**
     * Enable the query log on the connection.
     *
     * @return void
     */
    public function enableQueryLog() {
        $this->loggingQueries = true;
    }

    /**
     * Disable the query log on the connection.
     *
     * @return void
     */
    public function disableQueryLog() {
        $this->loggingQueries = false;
    }

    /**
     * Determine whether we're logging queries.
     *
     * @return bool
     */
    public function logging() {
        return $this->loggingQueries;
    }

    /**
     * Get the name of the connected database.
     *
     * @return string
     */
    public function getDatabaseName() {
        return $this->database;
    }

    /**
     * Set the name of the connected database.
     *
     * @param string $database
     *
     * @return $this
     */
    public function setDatabaseName($database) {
        $this->database = $database;

        return $this;
    }

    /**
     * Set the read / write type of the connection.
     *
     * @param null|string $readWriteType
     *
     * @return $this
     */
    public function setReadWriteType($readWriteType) {
        $this->readWriteType = $readWriteType;

        return $this;
    }

    /**
     * Get the table prefix for the connection.
     *
     * @return string
     */
    public function getTablePrefix() {
        return $this->tablePrefix;
    }

    /**
     * Set the table prefix in use by the connection.
     *
     * @param string $prefix
     *
     * @return $this
     */
    public function setTablePrefix($prefix) {
        $this->tablePrefix = $prefix;

        $this->getQueryGrammar()->setTablePrefix($prefix);

        return $this;
    }

    /**
     * Set the table prefix and return the grammar.
     *
     * @param \CDatabase_Grammar $grammar
     *
     * @return \CDatabase_Grammar
     */
    public function withTablePrefix(CDatabase_Grammar $grammar) {
        $grammar->setTablePrefix($this->tablePrefix);

        return $grammar;
    }

    /**
     * Register a connection resolver.
     *
     * @param string   $driver
     * @param \Closure $callback
     *
     * @return void
     */
    public static function resolverFor($driver, Closure $callback) {
        static::$resolvers[$driver] = $callback;
    }

    /**
     * Get the connection resolver for the given driver.
     *
     * @param string $driver
     *
     * @return mixed
     */
    public static function getResolver($driver) {
        return static::$resolvers[$driver] ?? null;
    }

    /**
     * Combine a SQL statement with the bind values. Used for safe queries.
     *
     * @param string $sql        query to bind to the values
     * @param array  $binds      array of values to bind to the query
     * @param bool   $useReadPdo quote against the read connection instead of the write one -
     *                           logQuery() sets this for a query that ran on the read
     *                           connection, so merely logging it doesn't open a write one
     *
     * @return string
     */
    public function compileBinds($sql, array $binds = [], $useReadPdo = false) {
        foreach ((array) $binds as $val) {
            // If the SQL contains no more bind marks ("?"), we're done.
            if (($nextBindPos = strpos($sql, '?')) === false) {
                break;
            }
            if ($val instanceof Carbon) {
                $val = (string) $val;
            }
            // Properly escape the bind value.
            $val = $this->escape($val, false, $useReadPdo);

            // Temporarily replace possible bind marks ("?"), in the bind value itself, with a placeholder.
            $val = str_replace('?', '{%B%}', $val);

            // Replace the first bind mark ("?") with its corresponding value.
            $sql = substr($sql, 0, $nextBindPos) . $val . substr($sql, $nextBindPos + 1);
        }

        // Restore placeholders.
        return str_replace('{%B%}', '?', $sql);
    }

    /**
     * Escapes a column name for a query.
     *
     * @param string $column string to escape
     *
     * @return string
     */
    public function escapeColumn($column) {
        if ($column == '*') {
            return $column;
        }

        // This matches any functions we support to SELECT.
        if (preg_match('/(avg|count|sum|max|min)\(\s*(.*)\s*\)(\s*as\s*(.+)?)?/i', $column, $matches)) {
            if (count($matches) == 3) {
                return $matches[1] . '(' . $this->escapeColumn($matches[2]) . ')';
            } elseif (count($matches) == 5) {
                return $matches[1] . '(' . $this->escapeColumn($matches[2]) . ') AS ' . $this->escapeColumn($matches[2]);
            }
        }

        // This matches any modifiers we support to SELECT.
        if (!preg_match('/\b(?:rand|all|distinct(?:row)?|high_priority|sql_(?:small_result|b(?:ig_result|uffer_result)|no_cache|ca(?:che|lc_found_rows)))\s/i', $column)) {
            if (stripos($column, ' AS ') !== false) {
                // Force 'AS' to uppercase
                $column = str_ireplace(' AS ', ' AS ', $column);

                // Runs escape_column on both sides of an AS statement
                $column = array_map([$this, __FUNCTION__], explode(' AS ', $column));

                // Re-create the AS statement
                return implode(' AS ', $column);
            }

            return preg_replace('/[^.*]+/', '`$0`', $column);
        }

        $parts = explode(' ', $column);
        $column = '';

        for ($i = 0, $c = count($parts); $i < $c; $i++) {
            // The column is always last
            if ($i == ($c - 1)) {
                $column .= preg_replace('/[^.*]+/', '`$0`', $parts[$i]);
            } else { // otherwise, it's a modifier
                $column .= $parts[$i] . ' ';
            }
        }

        return $column;
    }

    /**
     * @param string $table
     *
     * @return string
     */
    public function escapeTable($table) {
        if (stripos($table, ' AS ') !== false) {
            // Force 'AS' to uppercase
            $table = str_ireplace(' AS ', ' AS ', $table);

            // Runs escape_table on both sides of an AS statement
            $table = array_map([$this, __FUNCTION__], explode(' AS ', $table));

            // Re-create the AS statement
            return implode(' AS ', $table);
        }

        return '`' . str_replace('.', '`.`', $table) . '`';
    }

    /**
     * @param string $str
     *
     * @return string
     */
    public function escapeLike($str) {
        return $this->escapeStr($str);
    }

    /**
     * @param string $str
     *
     * @return string
     */
    public function escapeStr($str) {
        if (!empty($str) && is_string($str)) {
            return str_replace(['\\', "\0", "\n", "\r", "'", '"', "\x1a"], ['\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'], $str);
        }

        return $str;
    }

    /**
     * @return void
     */
    public function disableBenchmark() {
        return $this->disableQueryLog();
    }

    /**
     * @return void
     */
    public function enableBenchmark() {
        return $this->enableQueryLog();
    }

    /**
     * @return array
     */
    public function getBenchmarks() {
        return $this->getQueryLog();
    }

    /**
     * @return void
     */
    public function clearBenchmarks() {
        return $this->flushQueryLog();
    }

    /**
     * @return null|string
     */
    public function lastQuery() {
        return carr::get(carr::last($this->getQueryLog()), 'compiled');
    }

    /**
     * @param string $query
     * @param array  $bindings
     * @param bool   $useReadPdo
     *
     * @return null|mixed
     */
    public function getRow($query, $bindings = [], $useReadPdo = true) {
        $r = $this->select($query, $bindings, $useReadPdo);
        if (is_array($r) && count($r) > 0) {
            return $r[0];
        }

        return null;
    }

    /**
     * @param string $query
     * @param array  $bindings
     *
     * @return null|mixed
     */
    public function getValue($query, $bindings = []) {
        $row = $this->getRow($query, $bindings);
        $value = null;
        if ($row) {
            $row = (array) $row;

            return carr::first($row);
        }

        return $value;
    }

    /**
     * @param string $query
     * @param array  $bindings
     *
     * @return array
     */
    public function getArray($query, $bindings = []) {
        $r = $this->select($query, $bindings);
        $res = [];
        foreach ($r as $row) {
            $res[] = carr::first((array) $row);
        }

        return $res;
    }

    /**
     * @param string $query
     * @param array  $bindings
     *
     * @return array
     */
    public function getList($query, $bindings = []) {
        $r = $this->select($query, $bindings);
        $res = [];
        foreach ($r as $row) {
            $row = array_values((array) $row);
            $res[carr::get($row, 0)] = carr::get($row, 1);
        }

        return $res;
    }

    /**
     * @return never
     *
     * @throws Exception
     */
    protected function getDoctrineDriver() {
        throw new Exception('Doctrine Driver is not implemented on this connection');
    }

    /**
     * @return bool
     */
    public function ping() {
        $pdo = $this->getPdoForSelect();
        if (!$pdo) {
            return false;
        }

        try {
            $pdo->query('SELECT 1');
        } catch (PDOException $e) {
            return false;
        }

        return true;
    }

    /**
     * Gets the DatabasePlatform for the connection.
     *
     * @throws CDatabase_Exception
     *
     * @return CDatabase_Platform
     */
    public function getDatabasePlatform() {
        if (null === $this->platform) {
            $this->detectDatabasePlatform();
        }

        return $this->platform;
    }

    /**
     * Gets the DatabasePlatform for the connection.
     *
     * @throws CDatabase_Exception
     *
     * @return CDatabase_Platform
     */
    public function getDefaultDatabasePlatform() {
        throw new Exception('Default Database platform is not implemented on this connection');
    }

    /**
     * Detects and sets the database platform.
     *
     * Evaluates custom platform class and version in order to set the correct platform.
     *
     * @throws CDatabase_Exception if an invalid platform was specified for this connection
     */
    private function detectDatabasePlatform() {
        $version = $this->getDatabasePlatformVersion();

        if ($version !== null) {
            assert($this instanceof CDatabase_Contract_VersionAwarePlatformInterface);

            $this->platform = $this->createDatabasePlatformForVersion($version);
        } else {
            $this->platform = $this->getDefaultDatabasePlatform();
        }

        $this->platform->setEventManager($this->events);
    }

    /**
     * Returns the version of the related platform if applicable.
     *
     * Returns null if either the driver is not capable to create version
     * specific platform instances, no explicit server version was specified
     * or the underlying driver connection cannot determine the platform
     * version without having to query it (performance reasons).
     *
     * @throws Exception
     *
     * @return null|string
     */
    private function getDatabasePlatformVersion() {
        // Driver does not support version specific platforms.

        if (!($this instanceof CDatabase_Contract_VersionAwarePlatformInterface)) {
            return null;
        }

        // Explicit platform version requested (supersedes auto-detection).
        if (isset($this->config['serverVersion'])) {
            return $this->config['serverVersion'];
        }
        if ($this instanceof CDatabase_Driver_ServerInfoAwareInterface && !$this->requiresQueryForServerVersion()) {
            return $this->getServerVersion();
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function getServerVersion() {
        return $this->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION);
    }

    /**
     * @param string $query
     * @param array  $bindings
     *
     * @return array
     */
    public function fetchAll($query, $bindings = []) {
        //dont change this to select because this method should return PDO::FETCH_ASSOC
        return $this->query($query, $bindings)->fetchAll();
    }

    /**
     * Gets the Configuration used by the Connection.
     *
     * @return CDatabase_Configuration
     */
    public function getConfiguration() {
        return $this->configuration;
    }

    /**
     * @return string
     */
    public function driverName() {
        return carr::get($this->config, 'driver');
    }
}
