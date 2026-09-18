<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Apr 12, 2019, 8:00:01 PM
 */
trait CValidation_Rule_Trait_DatabaseTrait {
    /**
     * The table to run the query against.
     *
     * @var string
     */
    protected $table;

    /**
     * The column to check on.
     *
     * @var string
     */
    protected $column;

    /**
     * The extra where clauses for the query.
     *
     * @var array
     */
    protected $wheres = [];

    /**
     * The array of custom query callbacks.
     *
     * @var array
     */
    protected $using = [];

    /**
     * Create a new rule instance.
     *
     * @param string $table
     * @param string $column
     *
     * @return void
     */
    public function __construct($table, $column = 'NULL') {
        $this->column = $column;

        $this->table = $this->resolveTableName($table);
    }

    /**
     * Resolves the name of the table from the given string.
     *
     * @param string $table
     *
     * @return string
     */
    public function resolveTableName($table) {
        if (!str_contains($table, '\\') || !class_exists($table)) {
            return $table;
        }

        if (is_subclass_of($table, CModel::class)) {
            $model = new $table();

            if (str_contains($model->getTable(), '.')) {
                return $table;
            }

            return implode('.', array_map(function ($part) {
                return trim($part, '.');
            }, array_filter([$model->getConnectionName(), $model->getTable()])));
        }

        return $table;
    }

    /**
     * Set a "where" constraint on the query.
     *
     * @param string|\Closure   $column
     * @param null|array|string $value
     *
     * @return $this
     */
    public function where($column, $value = null) {
        if (is_array($value)) {
            return $this->whereIn($column, $value);
        }

        if ($column instanceof Closure) {
            return $this->using($column);
        }

        $this->wheres[] = compact('column', 'value');

        return $this;
    }

    /**
     * Set a "where not" constraint on the query.
     *
     * @param string       $column
     * @param array|string $value
     *
     * @return $this
     */
    public function whereNot($column, $value) {
        if (is_array($value)) {
            return $this->whereNotIn($column, $value);
        }

        return $this->where($column, '!' . $value);
    }

    /**
     * Set a "where null" constraint on the query.
     *
     * @param string $column
     *
     * @return $this
     */
    public function whereNull($column) {
        return $this->where($column, 'NULL');
    }

    /**
     * Set a "where not null" constraint on the query.
     *
     * @param string $column
     *
     * @return $this
     */
    public function whereNotNull($column) {
        return $this->where($column, 'NOT_NULL');
    }

    /**
     * Set a "where in" constraint on the query.
     *
     * @param string $column
     * @param array  $values
     *
     * @return $this
     */
    public function whereIn($column, array $values) {
        return $this->where(function ($query) use ($column, $values) {
            $query->whereIn($column, $values);
        });
    }

    /**
     * Set a "where not in" constraint on the query.
     *
     * @param string $column
     * @param array  $values
     *
     * @return $this
     */
    public function whereNotIn($column, array $values) {
        return $this->where(function ($query) use ($column, $values) {
            $query->whereNotIn($column, $values);
        });
    }

    /**
     * Ignore soft deleted models during the existence check.
     *
     * @param string $statusColumn
     *
     * @return $this
     */
    public function withoutTrashed($statusColumn = 'status') {
        $this->where($statusColumn, 1);

        return $this;
    }

    /**
     * Only include soft deleted models during the existence check.
     *
     * @param string $statusColumn
     *
     * @return $this
     */
    public function onlyTrashed($statusColumn = 'status') {
        $this->where($statusColumn, 0);

        return $this;
    }

    /**
     * Register a custom query callback.
     *
     * @param \Closure $callback
     *
     * @return $this
     */
    public function using(Closure $callback) {
        $this->using[] = $callback;

        return $this;
    }

    /**
     * Get the custom query callbacks for the rule.
     *
     * @return array
     */
    public function queryCallbacks() {
        return $this->using;
    }

    /**
     * Format the where clauses.
     *
     * @return string
     */
    protected function formatWheres() {
        return c::collect($this->wheres)->map(function ($where) {
            return $where['column'] . ',' . $where['value'];
        })->implode(',');
    }
}
