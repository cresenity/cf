<?php

defined('SYSPATH') or die('No direct access allowed.');

class CDatabase_Query_Builder {
    use CDatabase_Trait_Builder,
        CDatabase_Trait_ExplainQueries,
        CTrait_ForwardsCalls,
        CDatabase_Query_Concern_BuilderWhereTrait;
    use CTrait_Macroable {
        __call as macroCall;
    }

    /**
     * The database connection instance.
     *
     * @var \CDatabase_ConnectionInterface
     */
    public $connection;

    /**
     * The database query grammar instance.
     *
     * @var CDatabase_Query_Grammar
     */
    public $grammar;

    /**
     * The database query post processor instance.
     *
     * @var CDatabase_Query_Processor
     */
    public $processor;

    /**
     * The current query value bindings.
     *
     * @var array
     */
    public $bindings = [
        'select' => [],
        'from' => [],
        'join' => [],
        'where' => [],
        'groupBy' => [],
        'having' => [],
        'order' => [],
        'union' => [],
        'unionOrder' => [],
    ];

    /**
     * An aggregate function and column to be run.
     *
     * @var array
     */
    public $aggregate;

    /**
     * The columns that should be returned.
     *
     * @var array
     */
    public $columns;

    /**
     * Indicates if the query returns distinct results.
     *
     * @var bool
     */
    public $distinct = false;

    /**
     * The table which the query is targeting.
     *
     * @var string
     */
    public $from;

    /**
     * Indicates whether use index for spesific index on table.
     *
     * @var string|bool
     */
    public $useIndex;

    /**
     * The table joins for the query.
     *
     * @var array
     */
    public $joins;

    /**
     * The where constraints for the query.
     *
     * @var array
     */
    public $wheres = [];

    /**
     * The groupings for the query.
     *
     * @var array
     */
    public $groups;

    /**
     * The having constraints for the query.
     *
     * @var array
     */
    public $havings;

    /**
     * The orderings for the query.
     *
     * @var array
     */
    public $orders;

    /**
     * The maximum number of records to return per group.
     *
     * @var array
     */
    public $groupLimit;

    /**
     * The maximum number of records to return.
     *
     * @var int
     */
    public $limit;

    /**
     * The number of records to skip.
     *
     * @var int
     */
    public $offset;

    /**
     * The query union statements.
     *
     * @var array
     */
    public $unions;

    /**
     * The maximum number of union records to return.
     *
     * @var int
     */
    public $unionLimit;

    /**
     * The number of union records to skip.
     *
     * @var int
     */
    public $unionOffset;

    /**
     * The orderings for the union query.
     *
     * @var array
     */
    public $unionOrders;

    /**
     * Indicates whether row locking is being used.
     *
     * @var string|bool
     */
    public $lock;

    /**
     * The callbacks that should be invoked before the query is executed.
     *
     * @var array
     */
    public $beforeQueryCallbacks = [];

    /**
     * All of the available clause operators.
     *
     * @var array
     */
    public $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
        'like', 'like binary', 'not like', 'ilike',
        '&', '|', '^', '<<', '>>',
        'rlike', 'not rlike', 'regexp', 'not regexp',
        '~', '~*', '!~', '!~*', 'similar to',
        'not similar to', 'not ilike', '~~*', '!~~*',
    ];

    /**
     * All of the available bitwise operators.
     *
     * @var string[]
     */
    public $bitwiseOperators = [
        '&', '|', '^', '<<', '>>', '&~',
    ];

    /**
     * Whether use write pdo for select.
     *
     * @var bool
     */
    public $useWritePdo = false;

    public function __construct(?CDatabase_Connection $connection = null, ?CDatabase_Query_Grammar $grammar = null, ?CDatabase_Query_Processor $processor = null) {
        $connection = $connection ?: CDatabase::manager()->connection();

        $this->connection = $connection;

        $this->grammar = $connection->getQueryGrammar();

        $this->processor = $connection->getPostProcessor();
    }

    /**
     * Set the columns to be selected.
     *
     * @param array|mixed $columns
     *
     * @return $this
     */
    public function select($columns = ['*']) {
        $this->columns = [];
        $this->bindings['select'] = [];
        $columns = is_array($columns) ? $columns : func_get_args();
        foreach ($columns as $as => $column) {
            if (is_string($as) && $this->isQueryable($column)) {
                $this->selectSub($column, $as);
            } else {
                $this->columns[] = $column;
            }
        }

        return $this;
    }

    /**
     * Add a subselect expression to the query.
     *
     * @param \Closure|\CDatabase_Query_Builder|string $query
     * @param string                                   $as
     *
     * @throws \InvalidArgumentException
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function selectSub($query, $as) {
        list($query, $bindings) = $this->createSub($query);

        return $this->selectRaw(
            '(' . $query . ') as ' . $this->grammar->wrap($as),
            $bindings
        );
    }

    /**
     * Add a new "raw" select expression to the query.
     *
     * @param string $expression
     * @param array  $bindings
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function selectRaw($expression, array $bindings = []) {
        $this->addSelect(new CDatabase_Query_Expression($expression));

        if ($bindings) {
            $this->addBinding($bindings, 'select');
        }

        return $this;
    }

    /**
     * Makes "from" fetch from a subquery.
     *
     * @param \Closure|\CDatabase_Query_Builder|string $query
     * @param string                                   $as
     *
     * @throws \InvalidArgumentException
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function fromSub($query, $as) {
        list($query, $bindings) = $this->createSub($query);

        return $this->fromRaw('(' . $query . ') as ' . $this->grammar->wrapTable($as), $bindings);
    }

    /**
     * Add a raw from clause to the query.
     *
     * @param string $expression
     * @param mixed  $bindings
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function fromRaw($expression, $bindings = []) {
        $this->from = new CDatabase_Query_Expression($expression);
        $this->addBinding($bindings, 'from');

        return $this;
    }

    /**
     * Creates a subquery and parse it.
     *
     * @param \Closure|\CDatabase_Query_Builder|string $query
     *
     * @return array
     */
    protected function createSub($query) {
        // If the given query is a Closure, we will execute it while passing in a new
        // query instance to the Closure. This will give the developer a chance to
        // format and work with the query before we cast it to a raw SQL string.
        if ($query instanceof Closure) {
            $callback = $query;
            $callback($query = $this->forSubQuery());
        }

        return $this->parseSub($query);
    }

    /**
     * Parse the subquery into SQL and bindings.
     *
     * @param mixed $query
     *
     * @return array
     */
    protected function parseSub($query) {
        if ($query instanceof self || $query instanceof CModel_Query || $query instanceof CModel_Relation) {
            $query = $this->prependDatabaseNameIfCrossDatabaseQuery($query);

            return [$query->toSql(), $query->getBindings()];
        } elseif (is_string($query)) {
            return [$query, []];
        } else {
            throw new InvalidArgumentException(
                'A subquery must be a query builder instance, a Closure, or a string.'
            );
        }
    }

    /**
     * Prepend the database name if the given query is on another database.
     *
     * @param mixed $query
     *
     * @return mixed
     */
    protected function prependDatabaseNameIfCrossDatabaseQuery($query) {
        if ($query->getConnection()->getDatabaseName() !== $this->getConnection()->getDatabaseName()) {
            $databaseName = $query->getConnection()->getDatabaseName();

            // if (strpos($query->from, $databaseName) !== 0 && strpos($query->from, '.') === false) {
            if (!cstr::startsWith($query->from, $databaseName) && !cstr::contains($query->from, '.')) {
                $query->from($databaseName . '.' . $query->from);
            }
        }

        return $query;
    }

    /**
     * Add a new select column to the query.
     *
     * @param array|mixed $column
     *
     * @return $this
     */
    public function addSelect($column) {
        $columns = is_array($column) ? $column : func_get_args();

        foreach ($columns as $as => $col) {
            if (is_string($as) && $this->isQueryable($col)) {
                if (is_null($this->columns)) {
                    $this->select($this->from . '.*');
                }

                $this->selectSub($col, $as);
            } else {
                $this->columns[] = $col;
            }
        }

        return $this;
    }

    /**
     * Force the query to only return distinct results.
     *
     * @return $this
     */
    public function distinct() {
        $columns = func_get_args();

        if (count($columns) > 0) {
            $this->distinct = is_array($columns[0]) || is_bool($columns[0]) ? $columns[0] : $columns;
        } else {
            $this->distinct = true;
        }

        return $this;
    }

    /**
     * Set the table which the query is targeting.
     *
     * @param \Closure|\CDatabase_Query_Builder|string $table
     * @param null|string                              $as
     *
     * @return $this
     */
    public function from($table, $as = null) {
        if ($this->isQueryable($table)) {
            return $this->fromSub($table, $as);
        }

        $this->from = $as ? "{$table} as {$as}" : $table;

        return $this;
    }

    /**
     * @param string $index
     *
     * @return $this
     */
    public function useIndex($index) {
        $this->useIndex = $index;

        return $this;
    }

    /**
     * @return $this
     */
    public function getUseIndex() {
        return $this->useIndex;
    }

    /**
     * Add a join clause to the query.
     *
     * @param string          $table
     * @param \Closure|string $first
     * @param null|string     $operator
     * @param null|string     $second
     * @param string          $type
     * @param bool            $where
     *
     * @return $this
     */
    public function join($table, $first, $operator = null, $second = null, $type = 'inner', $where = false) {
        $join = $this->newJoinClause($this, $type, $table);

        // If the first "column" of the join is really a Closure instance the developer
        // is trying to build a join with a complex "on" clause containing more than
        // one condition, so we'll add the join and call a Closure with the query.
        if ($first instanceof Closure) {
            $first($join);

            $this->joins[] = $join;

            $this->addBinding($join->getBindings(), 'join');
        } else {
            // If the column is simply a string, we can assume the join simply has a basic
            // "on" clause with a single condition. So we will just build the join with
            // this simple join clauses attached to it. There is not a join callback.
            $method = $where ? 'where' : 'on';

            $this->joins[] = $join->$method($first, $operator, $second);

            $this->addBinding($join->getBindings(), 'join');
        }

        return $this;
    }

    /**
     * Add a "join where" clause to the query.
     *
     * @param string $table
     * @param string $first
     * @param string $operator
     * @param string $second
     * @param string $type
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function joinWhere($table, $first, $operator, $second, $type = 'inner') {
        return $this->join($table, $first, $operator, $second, $type, true);
    }

    /**
     * Add a subquery join clause to the query.
     *
     * @param \Closure|\CDatabase_Query_Builder|\CModel_Query|string $query
     * @param string                                                 $as
     * @param string                                                 $first
     * @param null|string                                            $operator
     * @param null|string                                            $second
     * @param string                                                 $type
     * @param bool                                                   $where
     *
     * @throws \InvalidArgumentException
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function joinSub($query, $as, $first, $operator = null, $second = null, $type = 'inner', $where = false) {
        list($query, $bindings) = $this->createSub($query);
        $expression = '(' . $query . ') as ' . $this->grammar->wrapTable($as);
        $this->addBinding($bindings, 'join');

        return $this->join(new CDatabase_Query_Expression($expression), $first, $operator, $second, $type, $where);
    }

    /**
     * Add a left join to the query.
     *
     * @param string      $table
     * @param string      $first
     * @param null|string $operator
     * @param null|string $second
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function leftJoin($table, $first, $operator = null, $second = null) {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    /**
     * Add a "join where" clause to the query.
     *
     * @param string $table
     * @param string $first
     * @param string $operator
     * @param string $second
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function leftJoinWhere($table, $first, $operator, $second) {
        return $this->joinWhere($table, $first, $operator, $second, 'left');
    }

    /**
     * Add a subquery left join to the query.
     *
     * @param \Closure|\CDatabase_Query_Builder|\CModel_Query|string $query
     * @param string                                                 $as
     * @param string                                                 $first
     * @param null|string                                            $operator
     * @param null|string                                            $second
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function leftJoinSub($query, $as, $first, $operator = null, $second = null) {
        return $this->joinSub($query, $as, $first, $operator, $second, 'left');
    }

    /**
     * Add a right join to the query.
     *
     * @param string      $table
     * @param string      $first
     * @param null|string $operator
     * @param null|string $second
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function rightJoin($table, $first, $operator = null, $second = null) {
        return $this->join($table, $first, $operator, $second, 'right');
    }

    /**
     * Add a "right join where" clause to the query.
     *
     * @param string $table
     * @param string $first
     * @param string $operator
     * @param string $second
     *
     * @return $this
     */
    public function rightJoinWhere($table, $first, $operator, $second) {
        return $this->joinWhere($table, $first, $operator, $second, 'right');
    }

    /**
     * Add a subquery right join to the query.
     *
     * @param \Closure|\CDatabase_Query_Builder|\CModel_Query|string $query
     * @param string                                                 $as
     * @param string                                                 $first
     * @param null|string                                            $operator
     * @param null|string                                            $second
     *
     * @return $this
     */
    public function rightJoinSub($query, $as, $first, $operator = null, $second = null) {
        return $this->joinSub($query, $as, $first, $operator, $second, 'right');
    }

    /**
     * Add a "cross join" clause to the query.
     *
     * @param string      $table
     * @param null|string $first
     * @param null|string $operator
     * @param null|string $second
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function crossJoin($table, $first = null, $operator = null, $second = null) {
        if ($first) {
            return $this->join($table, $first, $operator, $second, 'cross');
        }

        $this->joins[] = $this->newJoinClause($this, 'cross', $table);

        return $this;
    }

    /**
     * Add a subquery cross join to the query.
     *
     * @param \Closure|\CDatabase_Query_Builder|string $query
     * @param string                                   $as
     *
     * @return $this
     */
    public function crossJoinSub($query, $as) {
        list($query, $bindings) = $this->createSub($query);

        $expression = '(' . $query . ') as ' . $this->grammar->wrapTable($as);

        $this->addBinding($bindings, 'join');

        $this->joins[] = $this->newJoinClause($this, 'cross', new CDatabase_Query_Expression($expression));

        return $this;
    }

    /**
     * Get a new join clause.
     *
     * @param CDatabase_Query_Builder $parentQuery
     * @param string                  $type
     * @param string                  $table
     *
     * @return CDatabase_Query_JoinClause
     */
    protected function newJoinClause(self $parentQuery, $type, $table) {
        return new CDatabase_Query_JoinClause($parentQuery, $type, $table);
    }

    /**
     * Merge an array of where clauses and bindings.
     *
     * @param array $wheres
     * @param array $bindings
     *
     * @return void
     */
    public function mergeWheres($wheres, $bindings) {
        $this->wheres = array_merge($this->wheres, (array) $wheres);

        $this->bindings['where'] = array_values(
            array_merge($this->bindings['where'], (array) $bindings)
        );
    }

    /**
     * Add a "where fulltext" clause to the query.
     *
     * @param string|string[] $columns
     * @param string          $value
     * @param string          $boolean
     *
     * @return $this
     */
    public function whereFullText($columns, $value, array $options = [], $boolean = 'and') {
        $type = 'Fulltext';

        $columns = (array) $columns;

        $this->wheres[] = compact('type', 'columns', 'value', 'options', 'boolean');

        $this->addBinding($value);

        return $this;
    }

    /**
     * Add a "or where fulltext" clause to the query.
     *
     * @param string|string[] $columns
     * @param string          $value
     *
     * @return $this
     */
    public function orWhereFullText($columns, $value, array $options = []) {
        return $this->whereFulltext($columns, $value, $options, 'or');
    }

    /**
     * Add a "group by" clause to the query.
     *
     * @param array|string ...$groups
     *
     * @return $this
     */
    public function groupBy(...$groups) {
        foreach ($groups as $group) {
            $this->groups = array_merge(
                (array) $this->groups,
                carr::wrap($group)
            );
        }

        return $this;
    }

    /**
     * Add a raw groupBy clause to the query.
     *
     * @param string $sql
     * @param array  $bindings
     *
     * @return $this
     */
    public function groupByRaw($sql, array $bindings = []) {
        $this->groups[] = new CDatabase_Query_Expression($sql);

        $this->addBinding($bindings, 'groupBy');

        return $this;
    }

    /**
     * Add a "having" clause to the query.
     *
     * @param string      $column
     * @param null|string $operator
     * @param null|string $value
     * @param string      $boolean
     *
     * @return $this
     */
    public function having($column, $operator = null, $value = null, $boolean = 'and') {
        $type = 'Basic';

        // Here we will make some assumptions about the operator. If only 2 values are
        // passed to the method, we will assume that the operator is an equals sign
        // and keep going. Otherwise, we'll require the operator to be passed in.
        list($value, $operator) = $this->prepareValueAndOperator(
            $value,
            $operator,
            func_num_args() == 2
        );

        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ($this->invalidOperator($operator)) {
            list($value, $operator) = [$operator, '='];
        }

        $this->havings[] = compact('type', 'column', 'operator', 'value', 'boolean');

        if (!$value instanceof CDatabase_Contract_Query_ExpressionInterface) {
            $this->addBinding($this->flattenValue($value), 'having');
        }

        return $this;
    }

    /**
     * Add a "or having" clause to the query.
     *
     * @param string      $column
     * @param null|string $operator
     * @param null|string $value
     *
     * @return $this
     */
    public function orHaving($column, $operator = null, $value = null) {
        return $this->having($column, $operator, $value, 'or');
    }

    /**
     * Add a "having between " clause to the query.
     *
     * @param string $column
     * @param array  $values
     * @param string $boolean
     * @param bool   $not
     *
     * @return $this
     */
    public function havingBetween($column, array $values, $boolean = 'and', $not = false) {
        $type = 'between';

        $this->havings[] = compact('type', 'column', 'values', 'boolean', 'not');

        $this->addBinding(array_slice($this->cleanBindings(carr::flatten($values)), 0, 2), 'having');

        return $this;
    }

    /**
     * Add a raw having clause to the query.
     *
     * @param string $sql
     * @param array  $bindings
     * @param string $boolean
     *
     * @return $this
     */
    public function havingRaw($sql, array $bindings = [], $boolean = 'and') {
        $type = 'Raw';

        $this->havings[] = compact('type', 'sql', 'boolean');

        $this->addBinding($bindings, 'having');

        return $this;
    }

    /**
     * Add a raw or having clause to the query.
     *
     * @param string $sql
     * @param array  $bindings
     *
     * @return $this
     */
    public function orHavingRaw($sql, array $bindings = []) {
        return $this->havingRaw($sql, $bindings, 'or');
    }

    /**
     * Add an "order by" clause to the query.
     *
     * @param string $column
     * @param string $direction
     *
     * @return $this
     */
    public function orderBy($column, $direction = 'asc') {
        if ($this->isQueryable($column)) {
            list($query, $bindings) = $this->createSub($column);

            $column = new CDatabase_Query_Expression('(' . $query . ')');

            $this->addBinding($bindings, $this->unions ? 'unionOrder' : 'order');
        }

        $direction = strtolower($direction);

        if (!in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('Order direction must be "asc" or "desc".');
        }

        $this->{$this->unions ? 'unionOrders' : 'orders'}[] = [
            'column' => $column,
            'direction' => $direction,
        ];

        return $this;
    }

    /**
     * Add a descending "order by" clause to the query.
     *
     * @param string $column
     *
     * @return $this
     */
    public function orderByDesc($column) {
        return $this->orderBy($column, 'desc');
    }

    /**
     * Add an "order by" clause for a timestamp to the query.
     *
     * @param string $column
     *
     * @return $this
     */
    public function latest($column = 'created') {
        return $this->orderBy($column, 'desc');
    }

    /**
     * Add an "order by" clause for a timestamp to the query.
     *
     * @param string $column
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function oldest($column = 'created') {
        return $this->orderBy($column, 'asc');
    }

    /**
     * Put the query's results in random order.
     *
     * @param string $seed
     *
     * @return $this
     */
    public function inRandomOrder($seed = '') {
        return $this->orderByRaw($this->grammar->compileRandom($seed));
    }

    /**
     * Add a raw "order by" clause to the query.
     *
     * @param string $sql
     * @param array  $bindings
     *
     * @return $this
     */
    public function orderByRaw($sql, $bindings = []) {
        $type = 'Raw';

        $this->{$this->unions ? 'unionOrders' : 'orders'}[] = compact('type', 'sql');

        $this->addBinding($bindings, $this->unions ? 'unionOrder' : 'order');

        return $this;
    }

    /**
     * Alias to set the "offset" value of the query.
     *
     * @param int $value
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function skip($value) {
        return $this->offset($value);
    }

    /**
     * Set the "offset" value of the query.
     *
     * @param int $value
     *
     * @return $this
     */
    public function offset($value) {
        $property = $this->unions ? 'unionOffset' : 'offset';

        $this->$property = max(0, $value);

        return $this;
    }

    /**
     * Alias to set the "limit" value of the query.
     *
     * @param int $value
     *
     * @return CDatabase_Query_Builder|static
     */
    public function take($value) {
        return $this->limit($value);
    }

    /**
     * Set the "limit" value of the query.
     *
     * @param int $value
     *
     * @return $this
     */
    public function limit($value) {
        $property = $this->unions ? 'unionLimit' : 'limit';

        if ($value >= 0) {
            $this->$property = $value;
        }

        return $this;
    }

    /**
     * Add a "group limit" clause to the query.
     *
     * @param int    $value
     * @param string $column
     *
     * @return $this
     */
    public function groupLimit($value, $column) {
        if ($value >= 0) {
            $this->groupLimit = compact('value', 'column');
        }

        return $this;
    }

    /**
     * Set the limit and offset for a given page.
     *
     * @param int $page
     * @param int $perPage
     *
     * @return $this
     */
    public function forPage($page, $perPage = 15) {
        if ($perPage <= 0) {
            return $this;
        }

        return $this->offset(($page - 1) * $perPage)->limit($perPage);
    }

    /**
     * Constrain the query to the previous "page" of results before a given ID.
     *
     * @param int      $perPage
     * @param null|int $lastId
     * @param string   $column
     *
     * @return $this
     */
    public function forPageBeforeId($perPage = 15, $lastId = 0, $column = 'id') {
        $this->orders = $this->removeExistingOrdersFor($column);

        if (!is_null($lastId)) {
            $this->where($column, '<', $lastId);
        }

        return $this->orderBy($column, 'desc')
            ->limit($perPage);
    }

    /**
     * Constrain the query to the next "page" of results after a given ID.
     *
     * @param int      $perPage
     * @param null|int $lastId
     * @param string   $column
     *
     * @return $this
     */
    public function forPageAfterId($perPage = 15, $lastId = 0, $column = 'id') {
        $this->orders = $this->removeExistingOrdersFor($column);

        if (!is_null($lastId)) {
            $this->where($column, '>', $lastId);
        }

        return $this->orderBy($column, 'asc')
            ->limit($perPage);
    }

    /**
     * Remove all existing orders and optionally add a new order.
     *
     * @param null|string $column
     * @param string      $direction
     *
     * @return $this
     */
    public function reorder($column = null, $direction = 'asc') {
        $this->orders = null;
        $this->unionOrders = null;
        $this->bindings['order'] = [];
        $this->bindings['unionOrder'] = [];

        if ($column) {
            return $this->orderBy($column, $direction);
        }

        return $this;
    }

    /**
     * Get an array with all orders with a given column removed.
     *
     * @param string $column
     *
     * @return array
     */
    protected function removeExistingOrdersFor($column) {
        return CCollection::make($this->orders)
            ->reject(function ($order) use ($column) {
                return isset($order['column'])
                        ? $order['column'] === $column : false;
            })->values()->all();
    }

    /**
     * Add a union statement to the query.
     *
     * @param \CDatabase_Query_Builder|\Closure $query
     * @param bool                              $all
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function union($query, $all = false) {
        if ($query instanceof Closure) {
            call_user_func($query, $query = $this->newQuery());
        }

        $this->unions[] = compact('query', 'all');

        $this->addBinding($query->getBindings(), 'union');

        return $this;
    }

    /**
     * Add a union all statement to the query.
     *
     * @param \CDatabase_Query_Builder|\Closure $query
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function unionAll($query) {
        return $this->union($query, true);
    }

    /**
     * Lock the selected rows in the table.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function lock($value = true) {
        $this->lock = $value;

        if (!is_null($this->lock)) {
            $this->useWritePdo();
        }

        return $this;
    }

    /**
     * Lock the selected rows in the table for updating.
     *
     * @return \CDatabase_Query_Builder
     */
    public function lockForUpdate() {
        return $this->lock(true);
    }

    /**
     * Share lock the selected rows in the table.
     *
     * @return \CDatabase_Query_Builder
     */
    public function sharedLock() {
        return $this->lock(false);
    }

    /**
     * Register a closure to be invoked before the query is executed.
     *
     * @param callable $callback
     *
     * @return $this
     */
    public function beforeQuery(callable $callback) {
        $this->beforeQueryCallbacks[] = $callback;

        return $this;
    }

    /**
     * Invoke the "before query" modification callbacks.
     *
     * @return void
     */
    public function applyBeforeQueryCallbacks() {
        foreach ($this->beforeQueryCallbacks as $callback) {
            $callback($this);
        }

        $this->beforeQueryCallbacks = [];
    }

    /**
     * Get the SQL representation of the query.
     *
     * @return string
     */
    public function toSql() {
        $this->applyBeforeQueryCallbacks();

        return $this->grammar->compileSelect($this);
    }

    /**
     * Get the SQL representation of the query with bindings.
     *
     * @return string
     */
    public function toCompiledSql() {
        return $this->connection->compileBinds($this->toSql(), $this->getBindings());
    }

    /**
     * Execute a query for a single record by ID.
     *
     * @param int   $id
     * @param array $columns
     *
     * @return mixed|static
     */
    public function find($id, $columns = ['*']) {
        return $this->where($this->from . '_id', '=', $id)->first($columns);
    }

    /**
     * Get a single column's value from the first result of a query.
     *
     * @param string $column
     *
     * @return mixed
     */
    public function value($column) {
        $result = (array) $this->first([$column]);

        return count($result) > 0 ? reset($result) : null;
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param array|string $columns
     *
     * @return \CCollection
     */
    public function get($columns = ['*']) {
        $items = c::collect($this->onceWithColumns(carr::wrap($columns), function () {
            return $this->processor->processSelect($this, $this->runSelect());
        }));

        return isset($this->groupLimit)
            ? $this->withoutGroupLimitKeys($items)
            : $items;
    }

    /**
     * Run the query as a "select" statement against the connection.
     *
     * @return array
     */
    protected function runSelect() {
        return $this->connection->select(
            $this->toSql(),
            $this->getBindings(),
            !$this->useWritePdo
        );
    }

    /**
     * Remove the group limit keys from the results in the collection.
     *
     * @param \CCollection $items
     *
     * @return \CCollection
     */
    protected function withoutGroupLimitKeys($items) {
        $keysToRemove = ['cf_row'];

        if (is_string($this->groupLimit['column'])) {
            $column = c::last(explode('.', $this->groupLimit['column']));

            $keysToRemove[] = '@cf_group := ' . $this->grammar->wrap($column);
            $keysToRemove[] = '@cf_group := ' . $this->grammar->wrap('pivot_' . $column);
        }

        $items->each(function ($item) use ($keysToRemove) {
            foreach ($keysToRemove as $key) {
                unset($item->$key);
            }
        });

        return $items;
    }

    /**
     * Paginate the given query into a simple paginator.
     *
     * @param int      $perPage
     * @param array    $columns
     * @param string   $pageName
     * @param null|int $page
     * @param null|int|\Closure $total
     *
     * @return \CPagination_LengthAwarePaginator
     */
    public function paginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null, $total = null) {
        $page = $page ?: CPagination_Paginator::resolveCurrentPage($pageName);

        $total = c::value($total) ?? $this->getCountForPagination();

        $results = $total ? $this->forPage($page, $perPage)->get($columns) : c::collect();

        return $this->paginator($results, $total, $perPage, $page, [
            'path' => CPagination_Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ]);
    }

    /**
     * Get a paginator only supporting simple next and previous links.
     *
     * This is more efficient on larger data-sets, etc.
     *
     * @param int      $perPage
     * @param array    $columns
     * @param string   $pageName
     * @param null|int $page
     *
     * @return CPagination_Paginator
     */
    public function simplePaginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null) {
        $page = $page ?: CPagination_Paginator::resolveCurrentPage($pageName);

        $this->offset(($page - 1) * $perPage)->limit($perPage + 1);

        return $this->simplePaginator($this->get($columns), $perPage, $page, [
            'path' => CPagination_Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ]);
    }

    /**
     * Get a paginator only supporting simple next and previous links.
     *
     * This is more efficient on larger data-sets, etc.
     *
     * @param null|int                        $perPage
     * @param array|string                    $columns
     * @param string                          $cursorName
     * @param null|\CPagination_Cursor|string $cursor
     *
     * @return \CPagination_CursorPaginatorInterface
     */
    public function cursorPaginate($perPage = 15, $columns = ['*'], $cursorName = 'cursor', $cursor = null) {
        return $this->paginateUsingCursor($perPage, $columns, $cursorName, $cursor);
    }

    /**
     * Ensure the proper order by required for cursor pagination.
     *
     * @param bool $shouldReverse
     *
     * @return \CCollection
     */
    protected function ensureOrderForCursorPagination($shouldReverse = false) {
        $this->enforceOrderBy();

        return c::collect($this->orders ?? $this->unionOrders ?? [])->filter(function ($order) {
            return carr::has($order, 'direction');
        })->when($shouldReverse, function (CCollection $orders) {
            return $orders->map(function ($order) {
                $order['direction'] = $order['direction'] === 'asc' ? 'desc' : 'asc';

                return $order;
            });
        })->values();
    }

    /**
     * Get the count of the total records for the paginator.
     *
     * @param array $columns
     *
     * @return int
     */
    public function getCountForPagination($columns = ['*']) {
        $results = $this->runPaginationCountQuery($columns);
        // Once we have run the pagination count query, we will get the resulting count and
        // take into account what type of query it was. When there is a group by we will
        // just return the count of the entire results set since that will be correct.
        if (!isset($results[0])) {
            return 0;
        } elseif (is_object($results[0])) {
            return (int) $results[0]->aggregate;
        }

        return (int) array_change_key_case((array) $results[0])['aggregate'];
    }

    /**
     * Run a pagination count query.
     *
     * @param array $columns
     *
     * @return array
     */
    protected function runPaginationCountQuery($columns = ['*']) {
        if ($this->groups || $this->havings) {
            $clone = $this->cloneForPaginationCount();
            if (is_null($clone->columns) && !empty($this->joins)) {
                $clone->select($this->from . '.*');
            }

            return $this->newQuery()
                ->from(new CDatabase_Query_Expression('(' . $clone->toSql() . ') as ' . $this->grammar->wrap('aggregate_table')))
                ->mergeBindings($clone)
                ->setAggregate('count', $this->withoutSelectAliases($columns))
                ->get()->all();
        }

        $without = $this->unions ? ['unionOrders', 'unionLimit', 'unionOffset', 'useIndex'] : ['columns', 'orders', 'limit', 'offset', 'useIndex'];

        return $this->cloneWithout($without)
            ->cloneWithoutBindings($this->unions ? ['unionOrder'] : ['select', 'order'])
            ->setAggregate('count', $this->withoutSelectAliases($columns))
            ->get()->all();
    }

    /**
     * Clone the existing query instance for usage in a pagination subquery.
     *
     * @return self
     */
    protected function cloneForPaginationCount() {
        return $this->cloneWithout(['orders', 'limit', 'offset'])
            ->cloneWithoutBindings(['order']);
    }

    /**
     * Remove the column aliases since they will break count queries.
     *
     * @param array $columns
     *
     * @return array
     */
    protected function withoutSelectAliases(array $columns) {
        return array_map(function ($column) {
            return is_string($column) && ($aliasPosition = strpos(strtolower($column), ' as ')) !== false
                ? substr($column, 0, $aliasPosition)
                : $column;
        }, $columns);
    }

    /**
     * Get a lazy collection for the given query.
     *
     * @return \CCollection_LazyCollection
     */
    public function cursor() {
        if (is_null($this->columns)) {
            $this->columns = ['*'];
        }

        return new CCollection_LazyCollection(function () {
            yield from $this->connection->cursor(
                $this->toSql(),
                $this->getBindings(),
                !$this->useWritePdo
            );
        });
    }

    /**
     * Throw an exception if the query doesn't have an orderBy clause.
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    protected function enforceOrderBy() {
        if (empty($this->orders) && empty($this->unionOrders)) {
            throw new RuntimeException('You must specify an orderBy clause when using this function.');
        }
    }

    /**
     * Get an array with the values of a given column.
     *
     * @param string      $column
     * @param null|string $key
     *
     * @return \CCollection
     */
    public function pluck($column, $key = null) {
        // First, we will need to select the results of the query accounting for the
        // given columns / key. Once we have the results, we will be able to take
        // the results and get the exact data that was requested for the query.
        $queryResult = $this->onceWithColumns(
            is_null($key) ? [$column] : [$column, $key],
            function () {
                return $this->processor->processSelect(
                    $this,
                    $this->runSelect()
                );
            }
        );

        if (empty($queryResult)) {
            return c::collect();
        }

        // If the columns are qualified with a table or have an alias, we cannot use
        // those directly in the "pluck" operations since the results from the DB
        // are only keyed by the column itself. We'll strip the table out here.
        $column = $this->stripTableForPluck($column);

        $key = $this->stripTableForPluck($key);

        return is_array($queryResult[0])
            ? $this->pluckFromArrayColumn($queryResult, $column, $key)
            : $this->pluckFromObjectColumn($queryResult, $column, $key);
    }

    /**
     * Strip off the table name or alias from a column identifier.
     *
     * @param string $column
     *
     * @return null|string
     */
    protected function stripTableForPluck($column) {
        if (is_null($column)) {
            return $column;
        }

        $columnString = $column instanceof CDatabase_Contract_Query_ExpressionInterface
            ? $this->grammar->getValue($column)
            : $column;

        $separator = cstr::contains(strtolower($columnString), ' as ') ? ' as ' : '\.';

        return c::last(preg_split('~' . $separator . '~i', $columnString));
    }

    /**
     * Retrieve column values from rows represented as objects.
     *
     * @param array  $queryResult
     * @param string $column
     * @param string $key
     *
     * @return \CCollection
     */
    protected function pluckFromObjectColumn($queryResult, $column, $key) {
        $results = [];

        if (is_null($key)) {
            foreach ($queryResult as $row) {
                $results[] = $row->$column;
            }
        } else {
            foreach ($queryResult as $row) {
                $results[$row->$key] = $row->$column;
            }
        }

        return c::collect($results);
    }

    /**
     * Retrieve column values from rows represented as arrays.
     *
     * @param array  $queryResult
     * @param string $column
     * @param string $key
     *
     * @return \CCollection
     */
    protected function pluckFromArrayColumn($queryResult, $column, $key) {
        $results = [];

        if (is_null($key)) {
            foreach ($queryResult as $row) {
                $results[] = $row[$column];
            }
        } else {
            foreach ($queryResult as $row) {
                $results[$row[$key]] = $row[$column];
            }
        }

        return c::collect($results);
    }

    /**
     * Concatenate values of a given column as a string.
     *
     * @param string $column
     * @param string $glue
     *
     * @return string
     */
    public function implode($column, $glue = '') {
        return $this->pluck($column)->implode($glue);
    }

    /**
     * Determine if any rows exist for the current query.
     *
     * @return bool
     */
    public function exists() {
        $this->applyBeforeQueryCallbacks();

        $results = $this->connection->select(
            $this->grammar->compileExists($this),
            $this->getBindings(),
            !$this->useWritePdo
        );
        // If the results has rows, we will get the row and see if the exists column is a
        // boolean true. If there is no results for this query we will return false as
        // there are no rows for this query at all and we can return that info here.
        if (isset($results[0])) {
            $results = (array) $results[0];

            return (bool) $results['exists'];
        }

        return false;
    }

    /**
     * Determine if no rows exist for the current query.
     *
     * @return bool
     */
    public function doesntExist() {
        return !$this->exists();
    }

    /**
     * Execute the given callback if no rows exist for the current query.
     *
     * @param \Closure $callback
     *
     * @return mixed
     */
    public function existsOr(Closure $callback) {
        return $this->exists() ? true : $callback();
    }

    /**
     * Execute the given callback if rows exist for the current query.
     *
     * @param \Closure $callback
     *
     * @return mixed
     */
    public function doesntExistOr(Closure $callback) {
        return $this->doesntExist() ? true : $callback();
    }

    /**
     * Retrieve the "count" result of the query.
     *
     * @param string $columns
     *
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function count($columns = '*') {
        return (int) $this->aggregate(__FUNCTION__, carr::wrap($columns));
    }

    /**
     * Retrieve the minimum value of a given column.
     *
     * @param string $column
     *
     * @return mixed
     */
    public function min($column) {
        return $this->aggregate(__FUNCTION__, [$column]);
    }

    /**
     * Retrieve the maximum value of a given column.
     *
     * @param string $column
     *
     * @return mixed
     */
    public function max($column) {
        return $this->aggregate(__FUNCTION__, [$column]);
    }

    /**
     * Retrieve the sum of the values of a given column.
     *
     * @param string $column
     *
     * @return mixed
     */
    public function sum($column) {
        $result = $this->aggregate(__FUNCTION__, [$column]);

        return $result ?: 0;
    }

    /**
     * Retrieve the average of the values of a given column.
     *
     * @param string $column
     *
     * @return mixed
     */
    public function avg($column) {
        return $this->aggregate(__FUNCTION__, [$column]);
    }

    /**
     * Alias for the "avg" method.
     *
     * @param string $column
     *
     * @return mixed
     */
    public function average($column) {
        return $this->avg($column);
    }

    /**
     * Execute an aggregate function on the database.
     *
     * @param string $function
     * @param array  $columns
     *
     * @return mixed
     */
    public function aggregate($function, $columns = ['*']) {
        $results = $this->cloneWithout($this->unions || $this->havings ? [] : ['columns'])
            ->cloneWithoutBindings($this->unions || $this->havings ? [] : ['select'])
            ->setAggregate($function, $columns)
            ->get($columns);

        if (!$results->isEmpty()) {
            return array_change_key_case((array) $results[0])['aggregate'];
        }
    }

    /**
     * Execute a numeric aggregate function on the database.
     *
     * @param string $function
     * @param array  $columns
     *
     * @return float|int
     */
    public function numericAggregate($function, $columns = ['*']) {
        $result = $this->aggregate($function, $columns);

        // If there is no result, we can obviously just return 0 here. Next, we will check
        // if the result is an integer or float. If it is already one of these two data
        // types we can just return the result as-is, otherwise we will convert this.
        if (!$result) {
            return 0;
        }

        if (is_int($result) || is_float($result)) {
            return $result;
        }

        // If the result doesn't contain a decimal place, we will assume it is an int then
        // cast it to one. When it does we will cast it to a float since it needs to be
        // cast to the expected data type for the developers out of pure convenience.
        return strpos((string) $result, '.') === false ? (int) $result : (float) $result;
    }

    /**
     * Set the aggregate property without running the query.
     *
     * @param string $function
     * @param array  $columns
     *
     * @return $this
     */
    protected function setAggregate($function, $columns) {
        $this->aggregate = compact('function', 'columns');

        if (empty($this->groups)) {
            $this->orders = null;

            $this->bindings['order'] = [];
        }

        return $this;
    }

    /**
     * Execute the given callback while selecting the given columns.
     *
     * After running the callback, the columns are reset to the original value.
     *
     * @param array    $columns
     * @param callable $callback
     *
     * @return mixed
     */
    protected function onceWithColumns($columns, $callback) {
        $original = $this->columns;
        if (is_null($original)) {
            $this->columns = $columns;
        }
        $result = $callback();
        $this->columns = $original;

        return $result;
    }

    /**
     * Insert a new record into the database.
     *
     * @param array $values
     *
     * @return CDatabase_Result
     */
    public function insert(array $values) {
        // Since every insert gets treated like a batch insert, we will make sure the
        // bindings are structured in a way that is convenient when building these
        // inserts statements by verifying these elements are actually an array.
        if (empty($values)) {
            return true;
        }

        if (!is_array(reset($values))) {
            $values = [$values];
        } else {
            // Here, we will sort the insert keys for every record so that each insert is
            // in the same order for the record. We need to make sure this is the case
            // so there are not any errors or problems when inserting these records.
            foreach ($values as $key => $value) {
                ksort($value);

                $values[$key] = $value;
            }
        }

        $this->applyBeforeQueryCallbacks();

        // Finally, we will run this query against the database connection and return
        // the results. We will need to also flatten these bindings before running
        // the query so they are all in one huge, flattened array for execution.
        return $this->connection->insertWithQuery(
            $this->grammar->compileInsert($this, $values),
            $this->cleanBindings(carr::flatten($values, 1))
        );
    }

    /**
     * Insert new records into the database while ignoring errors.
     *
     * @param array $values
     *
     * @return CDatabase_Result
     */
    public function insertOrIgnore(array $values) {
        if (empty($values)) {
            return 0;
        }

        if (!is_array(reset($values))) {
            $values = [$values];
        } else {
            foreach ($values as $key => $value) {
                ksort($value);
                $values[$key] = $value;
            }
        }

        $this->applyBeforeQueryCallbacks();

        return $this->connection->affectingStatement(
            $this->grammar->compileInsertOrIgnore($this, $values),
            $this->cleanBindings(carr::flatten($values, 1))
        );
    }

    /**
     * Insert a new record and get the value of the primary key.
     *
     * @param array       $values
     * @param null|string $sequence
     *
     * @return int
     */
    public function insertGetId(array $values, $sequence = null) {
        $this->applyBeforeQueryCallbacks();

        $sql = $this->grammar->compileInsertGetId($this, $values, $sequence);

        $values = $this->cleanBindings($values);

        return $this->processor->processInsertGetId($this, $sql, $values, $sequence);
    }

    /**
     * Insert new records into the table using a subquery.
     *
     * @param array                                    $columns
     * @param \Closure|\CDatabase_Query_Builder|string $query
     *
     * @return int
     */
    public function insertUsing(array $columns, $query) {
        $this->applyBeforeQueryCallbacks();

        list($sql, $bindings) = $this->createSub($query);

        return $this->connection->affectingStatement(
            $this->grammar->compileInsertUsing($this, $columns, $sql),
            $this->cleanBindings($bindings)
        );
    }

    /**
     * Update a record in the database.
     *
     * @param array $values
     *
     * @return int
     */
    public function update(array $values) {
        $this->applyBeforeQueryCallbacks();
        $values = c::collect($values)->map(function ($value) {
            if (!$value instanceof CDatabase_Query_Builder && !$value instanceof CModel_Query && !$value instanceof CModel_Relation) {
                return ['value' => $value, 'bindings' => $value];
            }

            list($query, $bindings) = $this->parseSub($value);

            return ['value' => new CDatabase_Query_Expression("({$query})"), 'bindings' => function () use ($bindings) {
                return $bindings;
            }];
        });

        $sql = $this->grammar->compileUpdate($this, $values->map(function ($value) {
            return $value['value'];
        })->all());

        return $this->connection->updateWithQuery($sql, $this->cleanBindings(
            $this->grammar->prepareBindingsForUpdate($this->bindings, $values->map(function ($value) {
                return $value['bindings'];
            })->all())
        ));
    }

    /**
     * Insert or update a record matching the attributes, and fill it with values.
     *
     * @param array $attributes
     * @param array $values
     *
     * @return bool
     */
    public function updateOrInsert(array $attributes, array $values = []) {
        if (!$this->where($attributes)->exists()) {
            return $this->insert(array_merge($attributes, $values));
        }

        if (empty($values)) {
            return true;
        }

        return (bool) $this->limit(1)->update($values);
    }

    /**
     * Insert new records or update the existing ones.
     *
     * @param array        $values
     * @param array|string $uniqueBy
     * @param null|array   $update
     *
     * @return int
     */
    public function upsert(array $values, $uniqueBy, $update = null) {
        if ($uniqueBy === [] || $uniqueBy === '') {
            throw new InvalidArgumentException('The unique columns must not be empty.');
        }

        if (empty($values)) {
            return 0;
        } elseif ($update === []) {
            return (int) $this->insert($values);
        }

        if (!is_array(reset($values))) {
            $values = [$values];
        } else {
            foreach ($values as $key => $value) {
                ksort($value);

                $values[$key] = $value;
            }
        }

        if (is_null($update)) {
            $update = array_keys(reset($values));
        }

        $this->applyBeforeQueryCallbacks();

        $bindings = $this->cleanBindings(array_merge(
            carr::flatten($values, 1),
            c::collect($update)->reject(function ($value, $key) {
                return is_int($key);
            })->all()
        ));

        return $this->connection->affectingStatement(
            $this->grammar->compileUpsert($this, $values, (array) $uniqueBy, $update),
            $bindings
        );
    }

    /**
     * Increment a column's value by a given amount.
     *
     * @param string $column
     * @param int    $amount
     * @param array  $extra
     *
     * @return int
     */
    public function increment($column, $amount = 1, array $extra = []) {
        if (!is_numeric($amount)) {
            throw new InvalidArgumentException('Non-numeric value passed to increment method.');
        }

        return $this->incrementEach([$column => $amount], $extra);
    }

    /**
     * Increment the given column's values by the given amounts.
     *
     * @param array<string, float|int|numeric-string> $columns
     * @param array<string, mixed>                    $extra
     *
     * @throws \InvalidArgumentException
     *
     * @return int
     */
    public function incrementEach(array $columns, array $extra = []) {
        foreach ($columns as $column => $amount) {
            if (!is_numeric($amount)) {
                throw new InvalidArgumentException("Non-numeric value passed as increment amount for column: '$column'.");
            } elseif (!is_string($column)) {
                throw new InvalidArgumentException('Non-associative array passed to incrementEach method.');
            }

            $columns[$column] = $this->raw("{$this->grammar->wrap($column)} + $amount");
        }

        return $this->update(array_merge($columns, $extra));
    }

    /**
     * Decrement a column's value by a given amount.
     *
     * @param string $column
     * @param int    $amount
     * @param array  $extra
     *
     * @return int
     */
    public function decrement($column, $amount = 1, array $extra = []) {
        if (!is_numeric($amount)) {
            throw new InvalidArgumentException('Non-numeric value passed to decrement method.');
        }

        return $this->decrementEach([$column => $amount], $extra);
    }

    /**
     * Decrement the given column's values by the given amounts.
     *
     * @param array<string, float|int|numeric-string> $columns
     * @param array<string, mixed>                    $extra
     *
     * @throws \InvalidArgumentException
     *
     * @return int
     */
    public function decrementEach(array $columns, array $extra = []) {
        foreach ($columns as $column => $amount) {
            if (!is_numeric($amount)) {
                throw new InvalidArgumentException("Non-numeric value passed as decrement amount for column: '$column'.");
            } elseif (!is_string($column)) {
                throw new InvalidArgumentException('Non-associative array passed to decrementEach method.');
            }

            $columns[$column] = $this->raw("{$this->grammar->wrap($column)} - $amount");
        }

        return $this->update(array_merge($columns, $extra));
    }

    /**
     * Delete a record from the database.
     *
     * @param mixed $id
     *
     * @return int
     */
    public function delete($id = null) {
        // If an ID is passed to the method, we will set the where clause to check the
        // ID to let developers to simply and quickly remove a single row from this
        // database without manually specifying the "where" clauses on the query.
        if (!is_null($id)) {
            $this->where($this->from . '.id', '=', $id);
        }

        $this->applyBeforeQueryCallbacks();

        return $this->connection->deleteWithQuery(
            $this->grammar->compileDelete($this),
            $this->cleanBindings(
                $this->grammar->prepareBindingsForDelete($this->bindings)
            )
        );
    }

    /**
     * Run a truncate statement on the table.
     *
     * @return void
     */
    public function truncate() {
        $this->applyBeforeQueryCallbacks();

        foreach ($this->grammar->compileTruncate($this) as $sql => $bindings) {
            $this->connection->statement($sql, $bindings);
        }
    }

    /**
     * Get a new instance of the query builder.
     *
     * @return \CDatabase_Query_Builder
     */
    public function newQuery() {
        return new static($this->connection, $this->grammar, $this->processor);
    }

    /**
     * Create a new query instance for a sub-query.
     *
     * @return CDatabase_Query_Builder
     */
    protected function forSubQuery() {
        return $this->newQuery();
    }

    /**
     * Get all of the query builder's columns in a text-only array with all expressions evaluated.
     *
     * @return array
     */
    public function getColumns() {
        return !is_null($this->columns)
                ? array_map(function ($column) {
                    return $this->grammar->getValue($column);
                }, $this->columns)
                : [];
    }

    /**
     * Create a raw database expression.
     *
     * @param mixed $value
     *
     * @return \CDatabase_Contract_Query_ExpressionInterface
     */
    public function raw($value) {
        return $this->connection->raw($value);
    }

    /**
     * Get the query builder instances that are used in the union of the query.
     *
     * @return \CCollection
     */
    protected function getUnionBuilders() {
        return isset($this->unions)
            ? (new CCollection($this->unions))->pluck('query')
            : new CCollection();
    }

    /**
     * Get the "limit" value for the query or null if it's not set.
     *
     * @return mixed
     */
    public function getLimit() {
        $value = $this->unions ? $this->unionLimit : $this->limit;

        return !is_null($value) ? (int) $value : null;
    }

    /**
     * Get the "offset" value for the query or null if it's not set.
     *
     * @return mixed
     */
    public function getOffset() {
        $value = $this->unions ? $this->unionOffset : $this->offset;

        return !is_null($value) ? (int) $value : null;
    }

    /**
     * Get the current query value bindings in a flattened array.
     *
     * @return array
     */
    public function getBindings() {
        return carr::flatten($this->bindings);
    }

    /**
     * Get the raw array of bindings.
     *
     * @return array
     */
    public function getRawBindings() {
        return $this->bindings;
    }

    /**
     * Set the bindings on the query builder.
     *
     * @param array  $bindings
     * @param string $type
     *
     * @throws \InvalidArgumentException
     *
     * @return $this
     */
    public function setBindings(array $bindings, $type = 'where') {
        if (!array_key_exists($type, $this->bindings)) {
            throw new InvalidArgumentException("Invalid binding type: {$type}.");
        }

        $this->bindings[$type] = $bindings;

        return $this;
    }

    /**
     * Add a binding to the query.
     *
     * @param mixed  $value
     * @param string $type
     *
     * @throws \InvalidArgumentException
     *
     * @return $this
     */
    public function addBinding($value, $type = 'where') {
        if (!array_key_exists($type, $this->bindings)) {
            throw new InvalidArgumentException("Invalid binding type: {$type}.");
        }

        if (is_array($value)) {
            // $this->bindings[$type] = array_values(array_merge($this->bindings[$type], $value));
            $this->bindings[$type] = array_values(array_map(
                function ($value) {
                    return $this->castBinding($value);
                },
                array_merge($this->bindings[$type], $value),
            ));
        } else {
            $this->bindings[$type][] = $this->castBinding($value);
        }

        return $this;
    }

    /**
     * Cast the given binding value.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function castBinding($value) {
        // prepare for php 8

        // if ($value instanceof UnitEnum) {
        //     return enum_value($value);
        // }

        return $value;
    }

    /**
     * Merge an array of bindings into our bindings.
     *
     * @param CDatabase_Query_Builder $query
     *
     * @return $this
     */
    public function mergeBindings(self $query) {
        $this->bindings = array_merge_recursive($this->bindings, $query->bindings);

        return $this;
    }

    /**
     * Remove all of the expressions from a list of bindings.
     *
     * @param array $bindings
     *
     * @return array
     */
    protected function cleanBindings(array $bindings) {
        return c::collect($bindings)->reject(function ($binding) {
            return $binding instanceof CDatabase_Contract_Query_ExpressionInterface;
        })->map(function ($binding) {
            return $this->castBinding($binding);
        })->values()->all();
    }

    /**
     * Get a scalar type value from an unknown type of input.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    protected function flattenValue($value) {
        return is_array($value) ? c::head(carr::flatten($value)) : $value;
    }

    /**
     * Get the default key name of the table.
     *
     * @return string
     */
    protected function defaultKeyName() {
        if ($this->from) {
            return $this->from . '_' . 'id';
        }

        return 'id';
    }

    /**
     * Get the database connection instance.
     *
     * @return CDatabase_Connection
     */
    public function getConnection() {
        return $this->connection;
    }

    /**
     * Get the database query processor instance.
     *
     * @return \CDatabase_Query_Processor
     */
    public function getProcessor() {
        return $this->processor;
    }

    /**
     * Get the query grammar instance.
     *
     * @return \CDatabase_Query_Grammar
     */
    public function getGrammar() {
        return $this->grammar;
    }

    /**
     * Use the write pdo for query.
     *
     * @return $this
     */
    public function useWritePdo() {
        $this->useWritePdo = true;

        return $this;
    }

    /**
     * Determine if the value is a query builder instance or a Closure.
     *
     * @param mixed $value
     *
     * @return bool
     */
    protected function isQueryable($value) {
        return $value instanceof self
            || $value instanceof CModel_Query
            || $value instanceof CModel_Relation
            || $value instanceof Closure;
    }

    /**
     * Clone the query.
     *
     * @return static
     */
    public function clone() {
        return clone $this;
    }

    /**
     * Clone the query without the given properties.
     *
     * @param array $except
     *
     * @return static
     */
    public function cloneWithout(array $except) {
        return c::tap($this->clone(), function ($clone) use ($except) {
            foreach ($except as $property) {
                $clone->{$property} = null;
            }
        });
    }

    /**
     * Clone the query without the given bindings.
     *
     * @param array $except
     *
     * @return static
     */
    public function cloneWithoutBindings(array $except) {
        return c::tap($this->clone(), function ($clone) use ($except) {
            foreach ($except as $type) {
                $clone->bindings[$type] = [];
            }
        });
    }

    /**
     * Dump the current SQL and bindings.
     *
     * @param mixed ...$args
     *
     * @return $this
     */
    public function dump(...$args) {
        c::dump($this->toSql(), $this->getBindings(), ...$args);

        return $this;
    }

    /**
     * Dump the raw current SQL with embedded bindings.
     *
     * @return $this
     */
    public function dumpRawSql() {
        c::dump($this->toRawSql());

        return $this;
    }

    /**
     * Die and dump the current SQL and bindings.
     *
     * @return never
     */
    public function dd() {
        cdbg::dd($this->toSql(), $this->getBindings());
    }

    /**
     * Die and dump the current SQL with embedded bindings.
     *
     * @return never
     */
    public function ddRawSql() {
        cdbg::dd($this->toRawSql());
    }

    /**
     * Handle dynamic method calls into the method.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @throws \BadMethodCallException
     *
     * @return mixed
     */
    public function __call($method, $parameters) {
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        if (cstr::startsWith($method, 'where')) {
            return $this->dynamicWhere($method, $parameters);
        }

        static::throwBadMethodCallException($method);
    }
}
