<?php

use Illuminate\Contracts\Support\Arrayable;

trait CDatabase_Query_Concern_BuilderWhereTrait {
    /**
     * Add a basic where clause to the query.
     *
     * @param string|array|\Closure $column
     * @param null|string           $operator
     * @param mixed                 $value
     * @param string                $boolean
     *
     * @return $this
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and') {
        /** @var CDatabase_Query_Builder $this */
        // If the column is an array, we will assume it is an array of key-value pairs
        // and can add them each as a where clause. We will maintain the boolean we
        // received when the method was called and pass it into the nested where.
        if (is_array($column)) {
            return $this->addArrayOfWheres($column, $boolean);
        }

        // Here we will make some assumptions about the operator. If only 2 values are
        // passed to the method, we will assume that the operator is an equals sign
        // and keep going. Otherwise, we'll require the operator to be passed in.
        list($value, $operator) = $this->prepareValueAndOperator(
            $value,
            $operator,
            func_num_args() === 2
        );

        // If the columns is actually a Closure instance, we will assume the developer
        // wants to begin a nested where statement which is wrapped in parenthesis.
        // We'll add that Closure to the query then return back out immediately.
        if ($column instanceof Closure && is_null($operator)) {
            return $this->whereNested($column, $boolean);
        }

        // If the column is a Closure instance and there is an operator value, we will
        // assume the developer wants to run a subquery and then compare the result
        // of that subquery with the given value that was provided to the method.
        if ($this->isQueryable($column) && !is_null($operator)) {
            list($sub, $bindings) = $this->createSub($column);

            return $this->addBinding($bindings, 'where')
                ->where(new CDatabase_Query_Expression('(' . $sub . ')'), $operator, $value, $boolean);
        }

        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ($this->invalidOperator($operator)) {
            list($value, $operator) = [$operator, '='];
        }

        // If the value is a Closure, it means the developer is performing an entire
        // sub-select within the query and we will need to compile the sub-select
        // within the where clause to get the appropriate query record results.
        if ($value instanceof Closure) {
            return $this->whereSub($column, $operator, $value, $boolean);
        }

        // If the value is "null", we will just assume the developer wants to add a
        // where null clause to the query. So, we will allow a short-cut here to
        // that method for convenience so the developer doesn't have to check.
        if (is_null($value)) {
            return $this->whereNull($column, $boolean, $operator !== '=');
        }

        $type = 'Basic';

        $columnString = ($column instanceof CDatabase_Contract_Query_ExpressionInterface)
            ? $this->grammar->getValue($column)
            : $column;
        // If the column is making a JSON reference we'll check to see if the value
        // is a boolean. If it is, we'll add the raw boolean string as an actual
        // value to the query to ensure this is properly handled by the query.
        if (cstr::contains($columnString, '->') && is_bool($value)) {
            $value = new CDatabase_Query_Expression($value ? 'true' : 'false');
            if (is_string($column)) {
                $type = 'JsonBoolean';
            }
        }
        if ($this->isBitwiseOperator($operator)) {
            $type = 'Bitwise';
        }

        // Now that we are working with just a simple query we can put the elements
        // in our array and add the query binding to our array of bindings that
        // will be bound to each SQL statements when it is finally executed.

        $this->wheres[] = compact(
            'type',
            'column',
            'operator',
            'value',
            'boolean'
        );

        if (!$value instanceof CDatabase_Contract_Query_ExpressionInterface) {
            $this->addBinding($value, 'where');
        }

        return $this;
    }

    /**
     * Add an array of where clauses to the query.
     *
     * @param array  $column
     * @param string $boolean
     * @param string $method
     *
     * @return $this
     */
    protected function addArrayOfWheres($column, $boolean, $method = 'where') {
        return $this->whereNested(function ($query) use ($column, $method, $boolean) {
            foreach ($column as $key => $value) {
                if (is_numeric($key) && is_array($value)) {
                    $query->{$method}(...array_values($value));
                } else {
                    $query->$method($key, '=', $value, $boolean);
                }
            }
        }, $boolean);
    }

    /**
     * Prepare the value and operator for a where clause.
     *
     * @param string $value
     * @param string $operator
     * @param bool   $useDefault
     *
     * @throws \InvalidArgumentException
     *
     * @return array
     */
    public function prepareValueAndOperator($value, $operator, $useDefault = false) {
        if ($useDefault) {
            return [$operator, '='];
        } elseif ($this->invalidOperatorAndValue($operator, $value)) {
            throw new InvalidArgumentException('Illegal operator and value combination.');
        }

        return [$value, $operator];
    }

    /**
     * Determine if the given operator and value combination is legal.
     *
     * Prevents using Null values with invalid operators.
     *
     * @param string $operator
     * @param mixed  $value
     *
     * @return bool
     */
    protected function invalidOperatorAndValue($operator, $value) {
        return is_null($value) && in_array($operator, $this->operators)
                && !in_array($operator, ['=', '<>', '!=']);
    }

    /**
     * Determine if the given operator is supported.
     *
     * @param string $operator
     *
     * @return bool
     */
    protected function invalidOperator($operator) {
        // A where() built from raw request input (e.g. a scanner sending an
        // array where a scalar operator is expected) must not crash strtolower() -
        // an array is never a valid operator, so it's treated the same as any
        // other unrecognized one.
        $operator = is_array($operator) ? '' : $operator;

        return !in_array(strtolower($operator), $this->operators, true)
                && !in_array(strtolower($operator), $this->grammar->getOperators(), true);
    }

    /**
     * Determine if the operator is a bitwise operator.
     *
     * @param string $operator
     *
     * @return bool
     */
    protected function isBitwiseOperator($operator) {
        $operator = is_array($operator) ? '' : $operator;

        return in_array(strtolower($operator), $this->bitwiseOperators, true)
               || in_array(strtolower($operator), $this->grammar->getBitwiseOperators(), true);
    }

    /**
     * Add an "or where" clause to the query.
     *
     * @param string|array|\Closure $column
     * @param null|string           $operator
     * @param mixed                 $value
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function orWhere($column, $operator = null, $value = null) {
        list($value, $operator) = $this->prepareValueAndOperator(
            $value,
            $operator,
            func_num_args() === 2
        );

        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * Add a "where" clause comparing two columns to the query.
     *
     * @param string|array $first
     * @param null|string  $operator
     * @param null|string  $second
     * @param null|string  $boolean
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function whereColumn($first, $operator = null, $second = null, $boolean = 'and') {
        // If the column is an array, we will assume it is an array of key-value pairs
        // and can add them each as a where clause. We will maintain the boolean we
        // received when the method was called and pass it into the nested where.
        if (is_array($first)) {
            return $this->addArrayOfWheres($first, $boolean, 'whereColumn');
        }

        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ($this->invalidOperator($operator)) {
            list($second, $operator) = [$operator, '='];
        }

        // Finally, we will add this where clause into this array of clauses that we
        // are building for the query. All of them will be compiled via a grammar
        // once the query is about to be executed and run against the database.
        $type = 'Column';

        $this->wheres[] = compact(
            'type',
            'first',
            'operator',
            'second',
            'boolean'
        );

        return $this;
    }

    /**
     * Add an "or where" clause comparing two columns to the query.
     *
     * @param string|array $first
     * @param null|string  $operator
     * @param null|string  $second
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function orWhereColumn($first, $operator = null, $second = null) {
        return $this->whereColumn($first, $operator, $second, 'or');
    }

    /**
     * Add a raw where clause to the query.
     *
     * @param string $sql
     * @param mixed  $bindings
     * @param string $boolean
     *
     * @return $this
     */
    public function whereRaw($sql, $bindings = [], $boolean = 'and') {
        $this->wheres[] = ['type' => 'raw', 'sql' => $sql, 'boolean' => $boolean];

        $this->addBinding((array) $bindings, 'where');

        return $this;
    }

    /**
     * Add a raw or where clause to the query.
     *
     * @param string $sql
     * @param mixed  $bindings
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function orWhereRaw($sql, $bindings = []) {
        return $this->whereRaw($sql, $bindings, 'or');
    }

    /**
     * Add a "where in" clause to the query.
     *
     * @param string $column
     * @param mixed  $values
     * @param string $boolean
     * @param bool   $not
     *
     * @return $this
     */
    public function whereIn($column, $values, $boolean = 'and', $not = false) {
        $type = $not ? 'NotIn' : 'In';

        // If the value is a query builder instance we will assume the developer wants to
        // look for any values that exists within this given query. So we will add the
        // query accordingly so that this query is properly executed when it is run.
        if ($this->isQueryable($values)) {
            list($query, $bindings) = $this->createSub($values);

            $values = [new CDatabase_Query_Expression($query)];

            $this->addBinding($bindings, 'where');
        }

        // If the value is a query builder instance we will assume the developer wants to
        // look for any values that exists within this given query. So we will add the
        // query accordingly so that this query is properly executed when it is run.
        if ($values instanceof self) {
            return $this->whereInExistingQuery(
                $column,
                $values,
                $boolean,
                $not
            );
        }

        // Next, if the value is Arrayable we need to cast it to its raw array form so we
        // have the underlying array value instead of an Arrayable object which is not
        // able to be added as a binding, etc. We will then add to the wheres array.
        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        $this->wheres[] = compact('type', 'column', 'values', 'boolean');

        // Finally we'll add a binding for each values unless that value is an expression
        // in which case we will just skip over it since it will be the query as a raw
        // string and not as a parameterized place-holder to be replaced by the PDO.
        $this->addBinding($this->cleanBindings($values), 'where');

        return $this;
    }

    /**
     * Add an "or where in" clause to the query.
     *
     * @param string $column
     * @param mixed  $values
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function orWhereIn($column, $values) {
        return $this->whereIn($column, $values, 'or');
    }

    /**
     * Add a "where not in" clause to the query.
     *
     * @param string $column
     * @param mixed  $values
     * @param string $boolean
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function whereNotIn($column, $values, $boolean = 'and') {
        return $this->whereIn($column, $values, $boolean, true);
    }

    /**
     * Add an "or where not in" clause to the query.
     *
     * @param string $column
     * @param mixed  $values
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function orWhereNotIn($column, $values) {
        return $this->whereNotIn($column, $values, 'or');
    }

    /**
     * Add a "where in raw" clause for integer values to the query.
     *
     * @param string                                        $column
     * @param \Illuminate\Contracts\Support\Arrayable|array $values
     * @param string                                        $boolean
     * @param bool                                          $not
     *
     * @return $this
     */
    public function whereIntegerInRaw($column, $values, $boolean = 'and', $not = false) {
        $type = $not ? 'NotInRaw' : 'InRaw';

        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        foreach ($values as &$value) {
            $value = (int) $value;
        }

        $this->wheres[] = compact('type', 'column', 'values', 'boolean');

        return $this;
    }

    /**
     * Add an "or where in raw" clause for integer values to the query.
     *
     * @param string                                        $column
     * @param \Illuminate\Contracts\Support\Arrayable|array $values
     *
     * @return $this
     */
    public function orWhereIntegerInRaw($column, $values) {
        return $this->whereIntegerInRaw($column, $values, 'or');
    }

    /**
     * Add a "where not in raw" clause for integer values to the query.
     *
     * @param string                                        $column
     * @param \Illuminate\Contracts\Support\Arrayable|array $values
     * @param string                                        $boolean
     *
     * @return $this
     */
    public function whereIntegerNotInRaw($column, $values, $boolean = 'and') {
        return $this->whereIntegerInRaw($column, $values, $boolean, true);
    }

    /**
     * Add an "or where not in raw" clause for integer values to the query.
     *
     * @param string                                        $column
     * @param \Illuminate\Contracts\Support\Arrayable|array $values
     *
     * @return $this
     */
    public function orWhereIntegerNotInRaw($column, $values) {
        return $this->whereIntegerNotInRaw($column, $values, 'or');
    }

    /**
     * Add a "where null" clause to the query.
     *
     * @param string|array $columns
     * @param string       $boolean
     * @param bool         $not
     *
     * @return $this
     */
    public function whereNull($columns, $boolean = 'and', $not = false) {
        $type = $not ? 'NotNull' : 'Null';

        foreach (carr::wrap($columns) as $column) {
            $this->wheres[] = compact('type', 'column', 'boolean');
        }

        return $this;
    }

    /**
     * Add an "or where null" clause to the query.
     *
     * @param string $column
     *
     * @return $this
     */
    public function orWhereNull($column) {
        return $this->whereNull($column, 'or');
    }

    /**
     * Add a "where not null" clause to the query.
     *
     * @param string $column
     * @param string $boolean
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function whereNotNull($column, $boolean = 'and') {
        return $this->whereNull($column, $boolean, true);
    }

    /**
     * Add a where between statement to the query.
     *
     * @param string $column
     * @param array  $values
     * @param string $boolean
     * @param bool   $not
     *
     * @return $this
     */
    public function whereBetween($column, array $values, $boolean = 'and', $not = false) {
        $type = 'between';

        $this->wheres[] = compact('type', 'column', 'values', 'boolean', 'not');

        $this->addBinding(array_slice($this->cleanBindings(carr::flatten($values)), 0, 2), 'where');

        return $this;
    }

    /**
     * Add a where between statement using columns to the query.
     *
     * @param string $column
     * @param array  $values
     * @param string $boolean
     * @param bool   $not
     *
     * @return $this
     */
    public function whereBetweenColumns($column, array $values, $boolean = 'and', $not = false) {
        $type = 'betweenColumns';

        $this->wheres[] = compact('type', 'column', 'values', 'boolean', 'not');

        return $this;
    }

    /**
     * Add an or where between statement to the query.
     *
     * @param string $column
     * @param array  $values
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function orWhereBetween($column, array $values) {
        return $this->whereBetween($column, $values, 'or');
    }

    /**
     * Add an or where between statement using columns to the query.
     *
     * @param string $column
     * @param array  $values
     *
     * @return $this
     */
    public function orWhereBetweenColumns($column, array $values) {
        return $this->whereBetweenColumns($column, $values, 'or');
    }

    /**
     * Add a where not between statement to the query.
     *
     * @param string $column
     * @param array  $values
     * @param string $boolean
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function whereNotBetween($column, array $values, $boolean = 'and') {
        return $this->whereBetween($column, $values, $boolean, true);
    }

    /**
     * Add a where not between statement using columns to the query.
     *
     * @param string $column
     * @param array  $values
     * @param string $boolean
     *
     * @return $this
     */
    public function whereNotBetweenColumns($column, array $values, $boolean = 'and') {
        return $this->whereBetweenColumns($column, $values, $boolean, true);
    }

    /**
     * Add an or where not between statement to the query.
     *
     * @param string $column
     * @param array  $values
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function orWhereNotBetween($column, array $values) {
        return $this->whereNotBetween($column, $values, 'or');
    }

    /**
     * Add an or where not between statement using columns to the query.
     *
     * @param string $column
     * @param array  $values
     *
     * @return $this
     */
    public function orWhereNotBetweenColumns($column, array $values) {
        return $this->whereNotBetweenColumns($column, $values, 'or');
    }

    /**
     * Add an "or where not null" clause to the query.
     *
     * @param string $column
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function orWhereNotNull($column) {
        return $this->whereNotNull($column, 'or');
    }

    /**
     * Add a "where date" statement to the query.
     *
     * @param string $column
     * @param string $operator
     * @param mixed  $value
     * @param string $boolean
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function whereDate($column, $operator, $value = null, $boolean = 'and') {
        list($value, $operator) = $this->prepareValueAndOperator(
            $value,
            $operator,
            func_num_args() === 2
        );

        $value = $this->flattenValue($value);

        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d');
        }

        return $this->addDateBasedWhere('Date', $column, $operator, $value, $boolean);
    }

    /**
     * Add an "or where date" statement to the query.
     *
     * @param string $column
     * @param string $operator
     * @param string $value
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function orWhereDate($column, $operator, $value) {
        list($value, $operator) = $this->prepareValueAndOperator(
            $value,
            $operator,
            func_num_args() === 2
        );

        return $this->whereDate($column, $operator, $value, 'or');
    }

    /**
     * Add a "where time" statement to the query.
     *
     * @param string $column
     * @param string $operator
     * @param string $value
     * @param string $boolean
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function whereTime($column, $operator, $value = null, $boolean = 'and') {
        list($value, $operator) = $this->prepareValueAndOperator(
            $value,
            $operator,
            func_num_args() == 2
        );

        $value = $this->flattenValue($value);

        if ($value instanceof DateTimeInterface) {
            $value = $value->format('H:i:s');
        }

        return $this->addDateBasedWhere('Time', $column, $operator, $value, $boolean);
    }

    /**
     * Add an "or where time" statement to the query.
     *
     * @param string $column
     * @param string $operator
     * @param int    $value
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function orWhereTime($column, $operator, $value) {
        list($value, $operator) = $this->prepareValueAndOperator(
            $value,
            $operator,
            func_num_args() == 2
        );

        return $this->whereTime($column, $operator, $value, 'or');
    }

    /**
     * Add a "where day" statement to the query.
     *
     * @param string $column
     * @param string $operator
     * @param mixed  $value
     * @param string $boolean
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function whereDay($column, $operator, $value = null, $boolean = 'and') {
        list($value, $operator) = $this->prepareValueAndOperator(
            $value,
            $operator,
            func_num_args() == 2
        );

        $value = $this->flattenValue($value);

        if ($value instanceof DateTimeInterface) {
            $value = $value->format('d');
        }

        if (!$value instanceof CDatabase_Query_Expression) {
            $value = str_pad($value, 2, '0', STR_PAD_LEFT);
        }

        return $this->addDateBasedWhere('Day', $column, $operator, $value, $boolean);
    }

    /**
     * Add an "or where day" statement to the query.
     *
     * @param string                         $column
     * @param string                         $operator
     * @param null|\DateTimeInterface|string $value
     *
     * @return $this
     */
    public function orWhereDay($column, $operator, $value = null) {
        list($value, $operator) = $this->prepareValueAndOperator(
            $value,
            $operator,
            func_num_args() == 2
        );

        return $this->whereDay($column, $operator, $value, 'or');
    }

    /**
     * Add a "where month" statement to the query.
     *
     * @param string $column
     * @param string $operator
     * @param mixed  $value
     * @param string $boolean
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function whereMonth($column, $operator, $value = null, $boolean = 'and') {
        list($value, $operator) = $this->prepareValueAndOperator(
            $value,
            $operator,
            func_num_args() == 2
        );

        $value = $this->flattenValue($value);

        if ($value instanceof DateTimeInterface) {
            $value = $value->format('m');
        }

        if (!$value instanceof CDatabase_Query_Expression) {
            $value = str_pad($value, 2, '0', STR_PAD_LEFT);
        }

        return $this->addDateBasedWhere('Month', $column, $operator, $value, $boolean);
    }

    /**
     * Add an "or where month" statement to the query.
     *
     * @param string                         $column
     * @param string                         $operator
     * @param null|\DateTimeInterface|string $value
     *
     * @return $this
     */
    public function orWhereMonth($column, $operator, $value = null) {
        list($value, $operator) = $this->prepareValueAndOperator(
            $value,
            $operator,
            func_num_args() === 2
        );

        return $this->whereMonth($column, $operator, $value, 'or');
    }

    /**
     * Add a "where year" statement to the query.
     *
     * @param string $column
     * @param string $operator
     * @param mixed  $value
     * @param string $boolean
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function whereYear($column, $operator, $value = null, $boolean = 'and') {
        list($value, $operator) = $this->prepareValueAndOperator(
            $value,
            $operator,
            func_num_args() == 2
        );

        $value = $this->flattenValue($value);

        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y');
        }

        return $this->addDateBasedWhere('Year', $column, $operator, $value, $boolean);
    }

    /**
     * Add an "or where year" statement to the query.
     *
     * @param string                             $column
     * @param string                             $operator
     * @param null|\DateTimeInterface|string|int $value
     *
     * @return $this
     */
    public function orWhereYear($column, $operator, $value = null) {
        list($value, $operator) = $this->prepareValueAndOperator(
            $value,
            $operator,
            func_num_args() === 2
        );

        return $this->whereYear($column, $operator, $value, 'or');
    }

    /**
     * Add a date based (year, month, day, time) statement to the query.
     *
     * @param string $type
     * @param string $column
     * @param string $operator
     * @param int    $value
     * @param string $boolean
     *
     * @return $this
     */
    protected function addDateBasedWhere($type, $column, $operator, $value, $boolean = 'and') {
        $this->wheres[] = compact('column', 'type', 'boolean', 'operator', 'value');

        if (!$value instanceof CDatabase_Query_Expression) {
            $this->addBinding($value, 'where');
        }

        return $this;
    }

    /**
     * Add a nested where statement to the query.
     *
     * @param \Closure $callback
     * @param string   $boolean
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function whereNested(Closure $callback, $boolean = 'and') {
        call_user_func($callback, $query = $this->forNestedWhere());

        return $this->addNestedWhereQuery($query, $boolean);
    }

    /**
     * Create a new query instance for nested where condition.
     *
     * @return \CDatabase_Query_Builder
     */
    public function forNestedWhere() {
        return $this->newQuery()->from($this->from);
    }

    /**
     * Add another query builder as a nested where to the query builder.
     *
     * @param \CDatabase_Query_Builder|static $query
     * @param string                          $boolean
     *
     * @return $this
     */
    public function addNestedWhereQuery($query, $boolean = 'and') {
        if (count($query->wheres)) {
            $type = 'Nested';

            $this->wheres[] = compact('type', 'query', 'boolean');

            $this->addBinding($query->getRawBindings()['where'], 'where');
        }

        return $this;
    }

    /**
     * Add a full sub-select to the query.
     *
     * @param string   $column
     * @param string   $operator
     * @param \Closure $callback
     * @param string   $boolean
     *
     * @return $this
     */
    protected function whereSub($column, $operator, Closure $callback, $boolean) {
        $type = 'Sub';

        // Once we have the query instance we can simply execute it so it can add all
        // of the sub-select's conditions to itself, and then we can cache it off
        // in the array of where clauses for the "main" parent query instance.
        call_user_func($callback, $query = $this->forSubQuery());

        $this->wheres[] = compact(
            'type',
            'column',
            'operator',
            'query',
            'boolean'
        );

        $this->addBinding($query->getBindings(), 'where');

        return $this;
    }

    /**
     * Add an exists clause to the query.
     *
     * @param \Closure $callback
     * @param string   $boolean
     * @param bool     $not
     *
     * @return $this
     */
    public function whereExists(Closure $callback, $boolean = 'and', $not = false) {
        $query = $this->forSubQuery();

        // Similar to the sub-select clause, we will create a new query instance so
        // the developer may cleanly specify the entire exists query and we will
        // compile the whole thing in the grammar and insert it into the SQL.
        call_user_func($callback, $query);

        return $this->addWhereExistsQuery($query, $boolean, $not);
    }

    /**
     * Add an or exists clause to the query.
     *
     * @param \Closure $callback
     * @param bool     $not
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function orWhereExists(Closure $callback, $not = false) {
        return $this->whereExists($callback, 'or', $not);
    }

    /**
     * Add a where not exists clause to the query.
     *
     * @param \Closure $callback
     * @param string   $boolean
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function whereNotExists(Closure $callback, $boolean = 'and') {
        return $this->whereExists($callback, $boolean, true);
    }

    /**
     * Add a where not exists clause to the query.
     *
     * @param \Closure $callback
     *
     * @return \CDatabase_Query_Builder|static
     */
    public function orWhereNotExists(Closure $callback) {
        return $this->orWhereExists($callback, true);
    }

    /**
     * Add an exists clause to the query.
     *
     * @param \CDatabase_Query_Builder $query
     * @param string                   $boolean
     * @param bool                     $not
     *
     * @return $this
     */
    public function addWhereExistsQuery(self $query, $boolean = 'and', $not = false) {
        $type = $not ? 'NotExists' : 'Exists';

        $this->wheres[] = compact('type', 'query', 'boolean');

        $this->addBinding($query->getBindings(), 'where');

        return $this;
    }

    /**
     * Adds a where condition using row values.
     *
     * @param array  $columns
     * @param string $operator
     * @param array  $values
     * @param string $boolean
     *
     * @throws \InvalidArgumentException
     *
     * @return $this
     */
    public function whereRowValues($columns, $operator, $values, $boolean = 'and') {
        if (count($columns) !== count($values)) {
            throw new InvalidArgumentException('The number of columns must match the number of values');
        }

        $type = 'RowValues';

        $this->wheres[] = compact('type', 'columns', 'operator', 'values', 'boolean');

        $this->addBinding($this->cleanBindings($values));

        return $this;
    }

    /**
     * Adds an or where condition using row values.
     *
     * @param array  $columns
     * @param string $operator
     * @param array  $values
     *
     * @return $this
     */
    public function orWhereRowValues($columns, $operator, $values) {
        return $this->whereRowValues($columns, $operator, $values, 'or');
    }

    /**
     * Add a "where JSON contains" clause to the query.
     *
     * @param string $column
     * @param mixed  $value
     * @param string $boolean
     * @param bool   $not
     *
     * @return $this
     */
    public function whereJsonContains($column, $value, $boolean = 'and', $not = false) {
        $type = 'JsonContains';

        $this->wheres[] = compact('type', 'column', 'value', 'boolean', 'not');

        if (!$value instanceof CDatabase_Query_Expression) {
            $this->addBinding($this->grammar->prepareBindingForJsonContains($value));
        }

        return $this;
    }

    /**
     * Add an "or where JSON contains" clause to the query.
     *
     * @param string $column
     * @param mixed  $value
     *
     * @return $this
     */
    public function orWhereJsonContains($column, $value) {
        return $this->whereJsonContains($column, $value, 'or');
    }

    /**
     * Add a "where JSON not contains" clause to the query.
     *
     * @param string $column
     * @param mixed  $value
     * @param string $boolean
     *
     * @return $this
     */
    public function whereJsonDoesntContain($column, $value, $boolean = 'and') {
        return $this->whereJsonContains($column, $value, $boolean, true);
    }

    /**
     * Add an "or where JSON not contains" clause to the query.
     *
     * @param string $column
     * @param mixed  $value
     *
     * @return $this
     */
    public function orWhereJsonDoesntContain($column, $value) {
        return $this->whereJsonDoesntContain($column, $value, 'or');
    }

    /**
     * Add a "where JSON length" clause to the query.
     *
     * @param string $column
     * @param mixed  $operator
     * @param mixed  $value
     * @param string $boolean
     *
     * @return $this
     */
    public function whereJsonLength($column, $operator, $value = null, $boolean = 'and') {
        $type = 'JsonLength';

        list($value, $operator) = $this->prepareValueAndOperator(
            $value,
            $operator,
            func_num_args() === 2
        );

        $this->wheres[] = compact('type', 'column', 'operator', 'value', 'boolean');

        if (!$value instanceof CDatabase_Query_Expression) {
            $this->addBinding((int) $this->flattenValue($value));
        }

        return $this;
    }

    /**
     * Add an "or where JSON length" clause to the query.
     *
     * @param string $column
     * @param mixed  $operator
     * @param mixed  $value
     *
     * @return $this
     */
    public function orWhereJsonLength($column, $operator, $value = null) {
        list($value, $operator) = $this->prepareValueAndOperator(
            $value,
            $operator,
            func_num_args() === 2
        );

        return $this->whereJsonLength($column, $operator, $value, 'or');
    }

    /**
     * Handles dynamic "where" clauses to the query.
     *
     * @param string $method
     * @param string $parameters
     *
     * @return $this
     */
    public function dynamicWhere($method, $parameters) {
        $finder = substr($method, 5);

        $segments = preg_split(
            '/(And|Or)(?=[A-Z])/',
            $finder,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        // The connector variable will determine which connector will be used for the
        // query condition. We will change it as we come across new boolean values
        // in the dynamic method strings, which could contain a number of these.
        $connector = 'and';

        $index = 0;

        foreach ($segments as $segment) {
            // If the segment is not a boolean connector, we can assume it is a column's name
            // and we will add it to the query as a new constraint as a where clause, then
            // we can keep iterating through the dynamic method string's segments again.
            if ($segment !== 'And' && $segment !== 'Or') {
                $this->addDynamic($segment, $connector, $parameters, $index);

                $index++;
            } else {
                // Otherwise, we will store the connector so we know how the next where clause we
                // find in the query should be connected to the previous ones, meaning we will
                // have the proper boolean connector to connect the next where clause found.
                $connector = $segment;
            }
        }

        return $this;
    }

    /**
     * Add a single dynamic where clause statement to the query.
     *
     * @param string $segment
     * @param string $connector
     * @param array  $parameters
     * @param int    $index
     *
     * @return void
     */
    protected function addDynamic($segment, $connector, $parameters, $index) {
        // Once we have parsed out the columns and formatted the boolean operators we
        // are ready to add it to this query as a where clause just like any other
        // clause on the query. Then we'll increment the parameter index values.
        $bool = strtolower($connector);

        $this->where(cstr::snake($segment), '=', $parameters[$index], $bool);
    }
}
