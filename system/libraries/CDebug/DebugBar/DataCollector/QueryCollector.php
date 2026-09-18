<?php

defined('SYSPATH') or die('No direct access allowed.');

use DebugBar\DataCollector\Renderable;
use DebugBar\DataCollector\AssetProvider;
use DebugBar\DataCollector\PDO\PDOCollector;
use DebugBar\DataCollector\TimeDataCollector;

/**
 * Collects data about SQL statements executed with PDO.
 */
class CDebug_DebugBar_DataCollector_QueryCollector extends PDOCollector implements Renderable, AssetProvider {
    use CDebug_DebugBar_DataCollector_Trait_FileHelperTrait;

    protected $timeCollector;

    /**
     * @var CDebug_DebugBar_QueryN1Detector
     */
    protected $queryN1Detector;

    protected $queries = [];

    protected $renderSqlWithParams = false;

    protected $findSource = false;

    protected $middleware = [];

    protected $durationBackground = true;

    protected $explainQuery = false;

    protected $explainTypes = ['SELECT']; // ['SELECT', 'INSERT', 'UPDATE', 'DELETE']; for MySQL 5.6.3+

    protected $showHints = true;

    protected $showCopyButton = true;

    protected $reflection = [];

    /**
     * @param TimeDataCollector $timeCollector
     */
    public function __construct(?TimeDataCollector $timeCollector = null) {
        $this->timeCollector = $timeCollector;
        $this->queryN1Detector = new CDebug_DebugBar_QueryN1Detector();
        $this->setDataFormatter(new CDebug_DebugBar_DataFormatter_QueryFormatter());

        try {
            CEvent::dispatcher()->listen(CDatabase_Event_QueryExecuted::class, function (CDatabase_Event_QueryExecuted $query) {
                $backtrace = c::collect(debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 50));
                $this->addQuery($query, $backtrace);
            });
        } catch (\Exception $e) {
            CDebug::bar()->addThrowable(
                new Exception(
                    'Cannot add listen to Queries for Debugbar: ' . $e->getMessage(),
                    $e->getCode(),
                    $e
                )
            );
        }
    }

    /**
     * Renders the SQL of traced statements with params embedded.
     *
     * @param bool   $enabled
     * @param string $quotationChar NOT USED
     */
    public function setRenderSqlWithParams($enabled = true, $quotationChar = "'") {
        $this->renderSqlWithParams = $enabled;
    }

    /**
     * Show or hide the hints in the parameters.
     *
     * @param bool $enabled
     */
    public function setShowHints($enabled = true) {
        $this->showHints = $enabled;
    }

    /**
     * Enable/disable finding the source.
     *
     * @param bool  $value
     * @param array $middleware
     */
    public function setFindSource($value, array $middleware) {
        $this->findSource = (bool) $value;
        $this->middleware = $middleware;
    }

    /**
     * Enable/disable the EXPLAIN queries.
     *
     * @param bool       $enabled
     * @param null|array $types   Array of types to explain queries (select/insert/update/delete)
     */
    public function setExplainSource($enabled, $types) {
        $this->explainQuery = $enabled;
        if ($types) {
            $this->explainTypes = $types;
        }
    }

    /**
     * @param CDatabase_Event_QueryExecuted $query
     * @param CCollection                   $backtrace
     */
    public function addQuery(CDatabase_Event_QueryExecuted $query, $backtrace) {
        $bindings = $query->bindings;
        $time = $query->time;
        $connection = $query->connection;
        $sql = $query->sql;

        $explainResults = [];
        $time = $time / 1000;
        $endTime = microtime(true);
        $startTime = $endTime - $time;
        $hints = $this->performQueryAnalysis($sql);
        $pdo = null;

        try {
            $pdo = $connection->getPdo();
        } catch (\Throwable $e) {
            // ignore error for non-pdo laravel drivers
        }
        $bindings = $connection->prepareBindings($bindings);
        // Run EXPLAIN on this query (if needed)
        $explainQuery = $this->explainQuery || c::request()->cookie('capp-debugbar-explain-query');
        if ($explainQuery && $pdo && preg_match('/^(' . implode($this->explainTypes) . ') /i', $sql)) {
            $statement = $pdo->prepare('EXPLAIN ' . $sql);
            $statement->execute($bindings);
            $explainResults = $statement->fetchAll(\PDO::FETCH_CLASS);
        }
        $bindings = $this->getDataFormatter()->checkBindings($bindings);
        if (!empty($bindings) && $this->renderSqlWithParams) {
            foreach ($bindings as $key => $binding) {
                // This regex matches placeholders only, not the question marks,
                // nested in quotes, while we iterate through the bindings
                // and substitute placeholders by suitable values.
                $regex = is_numeric($key)
                    ? "/\?(?=(?:[^'\\\']*'[^'\\\']*')*[^'\\\']*$)/"
                    : "/:{$key}(?=(?:[^'\\\']*'[^'\\\']*')*[^'\\\']*$)/";
                // Mimic bindValue and only quote non-integer and non-float data types
                if (!is_int($binding) && !is_float($binding)) {
                    if ($pdo) {
                        try {
                            $binding = $pdo->quote((string) $binding);
                        } catch (\Exception $e) {
                            $binding = $this->emulateQuote($binding);
                        }
                    } else {
                        $binding = $this->emulateQuote($binding);
                    }
                }

                $sql = preg_replace($regex, addcslashes($binding, '$'), $sql, 1);
            }
        }
        $source = [];

        try {
            $source = $this->findSource();
        } catch (\Exception $e) {
        }
        if ($this->queryN1Detector) {
            $additionalHint = $this->queryN1Detector->logQuery($query, $backtrace, $source);
            if ($additionalHint) {
                $hints[] = $additionalHint;
                if (CDebug::bar()->hasCollector('messages')) {
                    $messagesCollector = CDebug::bar()->getCollector('messages');
                    /** @var \DebugBar\DataCollector\MessagesCollector $messagesCollector */
                    $messagesCollector->addMessage($additionalHint, 'warning');
                }
            }
        }

        $this->queries[] = [
            'query' => $sql,
            'type' => 'query',
            'bindings' => $this->getDataFormatter()->escapeBindings($bindings),
            'time' => $time,
            'source' => $source,
            'explain' => $explainResults,
            'connection' => $connection->getDatabaseName(),
            'driver' => $connection->getConfig('driver'),
            'hints' => $this->showHints ? $hints : null,
            'show_copy' => $this->showCopyButton,
        ];

        if ($this->timeCollector !== null) {
            $plainQuery = trim($sql);
            $plainQuery = str_replace("\n", '', $plainQuery);
            $plainQuery = str_replace("\r", '', $plainQuery);
            $this->timeCollector->addMeasure(cstr::limit($plainQuery, 100), $startTime, $endTime);
        }
    }

    /**
     * Mimic mysql_real_escape_string.
     *
     * @param string $value
     *
     * @return string
     */
    protected function emulateQuote($value) {
        $search = ['\\',  "\x00", "\n",  "\r",  "'",  '"', "\x1a"];
        $replace = ['\\\\', '\\0', '\\n', '\\r', "\'", '\"', '\\Z'];

        return "'" . str_replace($search, $replace, (string) $value) . "'";
    }

    /**
     * Explainer::performQueryAnalysis().
     *
     * Perform simple regex analysis on the code
     *
     * @param string $query
     *
     *
     * @version $Id$
     *
     * @return string
     */
    protected function performQueryAnalysis($query) {
        // @codingStandardsIgnoreStart
        $hints = [];
        if (preg_match('/^\\s*SELECT\\s*`?[a-zA-Z0-9]*`?\\.?\\*/i', $query)) {
            $hints[] = 'Use <code>SELECT *</code> only if you need all columns from table';
        }
        if (preg_match('/ORDER BY RAND()/i', $query)) {
            $hints[] = '<code>ORDER BY RAND()</code> is slow, try to avoid if you can.
                You can <a href="http://stackoverflow.com/questions/2663710/how-does-mysqls-order-by-rand-work" target="_blank">read this</a>
                or <a href="http://stackoverflow.com/questions/1244555/how-can-i-optimize-mysqls-order-by-rand-function" target="_blank">this</a>';
        }
        if (strpos($query, '!=') !== false) {
            $hints[] = 'The <code>!=</code> operator is not standard. Use the <code>&lt;&gt;</code> operator to test for inequality instead.';
        }
        if (stripos($query, 'WHERE') === false && preg_match('/^(SELECT) /i', $query)) {
            $hints[] = 'The <code>SELECT</code> statement has no <code>WHERE</code> clause and could examine many more rows than intended';
        }
        if (preg_match('/LIMIT\\s/i', $query) && stripos($query, 'ORDER BY') === false) {
            $hints[] = '<code>LIMIT</code> without <code>ORDER BY</code> causes non-deterministic results, depending on the query execution plan';
        }
        if (preg_match('/LIKE\\s[\'"](%.*?)[\'"]/i', $query, $matches)) {
            $hints[] = 'An argument has a leading wildcard character: <code>' . $matches[1] . '</code>.
                The predicate with this argument is not sargable and cannot use an index if one exists.';
        }

        return $hints;

        // @codingStandardsIgnoreEnd
    }

    /**
     * Use a backtrace to search for the origins of the query.
     *
     * @return array
     */
    protected function findSource() {
        $stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS | DEBUG_BACKTRACE_PROVIDE_OBJECT, 50);

        $sources = [];

        foreach ($stack as $index => $trace) {
            $sources[] = $this->parseTrace($index, $trace);
        }

        return array_filter($sources);
    }

    /**
     * Parse a trace element from the backtrace stack.
     *
     * @param int   $index
     * @param array $trace
     *
     * @return object|bool
     */
    protected function parseTrace($index, array $trace) {
        $frame = (object) [
            'index' => $index,
            'namespace' => null,
            'name' => null,
            'line' => isset($trace['line']) ? $trace['line'] : '?',
        ];
        if (isset($trace['function']) && $trace['function'] == 'substituteBindings') {
            $frame->name = 'Route binding';

            return $frame;
        }
        if (isset($trace['class'], $trace['file'])
            && !$this->fileIsInExcludedPath($trace['file'])
        ) {
            $file = $trace['file'];

            $frame->name = $this->normalizeFilename($file);

            return $frame;
        }

        return false;
    }

    /**
     * Collect a database transaction event.
     *
     * @param string     $event
     * @param \CDatabase $connection
     *
     * @return array
     */
    public function collectTransactionEvent($event, $connection) {
        $source = [];

        if ($this->findSource) {
            try {
                $source = $this->findSource();
            } catch (\Exception $e) {
            }
        }

        $this->queries[] = [
            'query' => $event,
            'type' => 'transaction',
            'bindings' => [],
            'time' => 0,
            'source' => $source,
            'explain' => [],
            'connection' => $connection->getDatabaseName(),
            'hints' => null,
            'show_copy' => false,
        ];
    }

    /**
     * Reset the queries.
     */
    public function reset() {
        $this->queries = [];
    }

    /**
     * @inheritDoc
     */
    public function collect() {
        $totalTime = 0;

        $queries = $this->queries;
        $statements = [];
        foreach ($queries as $query) {
            $totalTime += $query['time'];
            $statements[] = [
                'sql' => $this->getDataFormatter()->formatSql($query['query']),
                'type' => $query['type'],
                'params' => [],
                'bindings' => $query['bindings'],
                'hints' => $query['hints'],
                'show_copy' => $query['show_copy'],
                'backtrace' => array_values($query['source']),
                'duration' => $query['time'],
                'duration_str' => ($query['type'] == 'transaction') ? '' : $this->formatDuration($query['time']),
                'stmt_id' => $this->getDataFormatter()->formatSource(reset($query['source'])),
                'connection' => $query['connection'],
            ];
            //Add the results from the explain as new rows
            // Add the results from the explain as new rows
            if ($query['driver'] === 'pgsql') {
                $explainer = trim(implode("\n", array_map(function ($explain) {
                    return $explain->{'QUERY PLAN'};
                }, $query['explain'])));

                if ($explainer) {
                    $statements[] = [
                        'sql' => " - EXPLAIN: {$explainer}",
                        'type' => 'explain',
                    ];
                }
            } elseif ($query['driver'] === 'sqlite') {
                $vmi = '<table style="margin:-5px -11px !important;width: 100% !important">';
                $vmi .= '<thead><tr>
                    <td>Address</td>
                    <td>Opcode</td>
                    <td>P1</td>
                    <td>P2</td>
                    <td>P3</td>
                    <td>P4</td>
                    <td>P5</td>
                    <td>Comment</td>
                    </tr></thead>';

                foreach ($query['explain'] as $explain) {
                    $vmi .= "<tr>
                        <td>{$explain->addr}</td>
                        <td>{$explain->opcode}</td>
                        <td>{$explain->p1}</td>
                        <td>{$explain->p2}</td>
                        <td>{$explain->p3}</td>
                        <td>{$explain->p4}</td>
                        <td>{$explain->p5}</td>
                        <td>{$explain->comment}</td>
                        </tr>";
                }

                $vmi .= '</table>';

                $statements[] = [
                    'sql' => ' - EXPLAIN:',
                    'type' => 'explain',
                    'params' => [
                        'Virtual Machine Instructions' => $vmi,
                    ]
                ];
            } else {
                foreach ($query['explain'] as $explain) {
                    $statements[] = [
                        'sql' => " - EXPLAIN # {$explain->id}: `{$explain->table}` ({$explain->select_type})",
                        'type' => 'explain',
                        'params' => $explain,
                        'row_count' => $explain->rows,
                        'stmt_id' => $explain->id,
                    ];
                }
            }
        }

        if ($this->durationBackground) {
            if ($totalTime > 0) {
                // For showing background measure on Queries tab
                $start_percent = 0;

                foreach ($statements as $i => $statement) {
                    if (!isset($statement['duration'])) {
                        continue;
                    }

                    $width_percent = $statement['duration'] / $totalTime * 100;

                    $statements[$i] = array_merge($statement, [
                        'start_percent' => round($start_percent, 3),
                        'width_percent' => round($width_percent, 3),
                    ]);

                    $start_percent += $width_percent;
                }
            }
        }
        $nb_statements = array_filter($queries, function ($query) {
            return $query['type'] == 'query';
        });
        $data = [
            'nb_statements' => count($nb_statements),
            'nb_failed_statements' => 0,
            'accumulated_duration' => $totalTime,
            'accumulated_duration_str' => $this->getDataFormatter()->formatDuration($totalTime),
            'statements' => $statements
        ];

        return $data;
    }

    /**
     * @inheritDoc
     */
    public function getName() {
        return 'queries';
    }

    /**
     * @inheritDoc
     */
    public function getWidgets() {
        return [
            'queries' => [
                'icon' => 'database',
                'widget' => 'PhpDebugBar.Widgets.CFSQLQueriesWidget',
                'map' => 'queries',
                'default' => '[]'
            ],
            'queries:badge' => [
                'map' => 'queries.nb_statements',
                'default' => 0
            ]
        ];
    }

    public function getAssets() {
        return [
            'css' => ['debug/debugbar/widgets/sqlqueries/widget.css'],
            'js' => ['debug/debugbar/widgets/cfsqlqueries/widget.js']
        ];
    }

    /**
     * Get data formatter
     * Dont remove this method
     * IDE must know return of this object must CDebug_DebugBar_DataFormatter_QueryFormatter object.
     *
     * @return CDebug_DebugBar_DataFormatter_QueryFormatter;
     */
    public function getDataFormatter() {
        return parent::getDataFormatter();
    }
}
