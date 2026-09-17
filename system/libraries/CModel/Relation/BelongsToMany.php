<?php

use Illuminate\Contracts\Support\Arrayable;

/**
 * @template TRelatedModel of \CModel
 * @template TDeclaringModel of \CModel
 *
 * @extends \CModel_Relation<TRelatedModel, TDeclaringModel, \CModel_Collection<int, TRelatedModel>>
 *
 * @method mixed               value($column)                                                                                                          Get a single column's value from the first result of a query.
 * @method mixed               pluck($column)                                                                                                          Get a single column's value from the first result of a query.
 * @method void                chunk($count, callable $callback)                                                                                       Chunk the results of the query.
 * @method \CCollection        lists($column, $key = null)                                                                                             Get an array with the values of a given column.
 * @method void                onDelete(Closure $callback)                                                                                             Register a replacement for the default delete function.
 * @method CModel[]            getModels($columns = ['*'])                                                                                             Get the hydrated models without eager loading.
 * @method array               eagerLoadRelations(array $models)                                                                                       Eager load the relationships for the models.
 * @method array               loadRelation(array $models, $name, Closure $constraints)                                                                Eagerly load the relationship on a set of models.
 * @method static              CModel_Query|static            where($column, $operator = null, $value = null, $boolean = 'and')                        Add a basic where clause to the query.
 * @method static              CModel_Query|static            whereHas($relation, Closure $callback = null, $operator = '>=', $count = 1)              Add a relationship count / exists condition to the query with where clauses.
 * @method static              CModel_Query|static            orWhere($column, $operator = null, $value = null)                                        Add an "or where" clause to the query.
 * @method static              CModel_Query|static            has($relation, $operator = '>=', $count = 1, $boolean = 'and', Closure $callback = null) Add a relationship count condition to the query.
 * @method static              CDatabase_Query_Builder|static whereRaw($sql, array $bindings = [])
 * @method static              CDatabase_Query_Builder        whereBetween($column, array $values)
 * @method static              CDatabase_Query_Builder        whereNotBetween($column, array $values)
 * @method static              CDatabase_Query_Builder        whereNested(Closure $callback)
 * @method static              CDatabase_Query_Builder        addNestedWhereQuery($query)
 * @method static              CDatabase_Query_Builder        whereExists(Closure $callback)
 * @method static              CDatabase_Query_Builder        whereNotExists(Closure $callback)
 * @method static              CDatabase_Query_Builder        whereIn($column, $values)
 * @method static              CDatabase_Query_Builder        whereNotIn($column, $values)
 * @method static              CDatabase_Query_Builder        whereNull($column)
 * @method static              CDatabase_Query_Builder        whereNotNull($column)
 * @method CModel_Query|static orWhereRaw($sql, array $bindings = [])
 * @method CModel_Query|static orWhereBetween($column, array $values)
 * @method CModel_Query|static orWhereNotBetween($column, array $values)
 * @method CModel_Query|static orWhereExists(Closure $callback)
 * @method CModel_Query|static orWhereNotExists(Closure $callback)
 * @method CModel_Query|static orWhereIn($column, $values)
 * @method CModel_Query|static orWhereNotIn($column, $values)
 * @method CModel_Query|static orWhereNull($column)
 * @method CModel_Query|static orWhereNotNull($column)
 * @method CModel_Query|static whereDate($column, $operator, $value)
 * @method CModel_Query|static whereDay($column, $operator, $value)
 * @method CModel_Query|static whereMonth($column, $operator, $value)
 * @method CModel_Query|static whereYear($column, $operator, $value)
 * @method CModel_Query|static join($table, $first, $operator = null, $second = null, $type = 'inner', $where = false)
 * @method CModel_Query|static select($columns = ['*'])
 * @method CModel_Query|static groupBy(...$groups)
 * @method CModel_Query|static newQuery()
 * @method CModel_Query|static withTrashed()
 * @method CModel_Query|static from($table)
 * @method CModel_Query|static leftJoinSub($query, $as, $first, $operator = null, $second = null)
 * @method CModel_Query|static addSelect($column)
 * @method CModel_Query|static selectRaw($expression, array $bindings = [])
 * @method CModel_Query|static orderBy($column, $direction = 'asc')
 * @method CModel_Query|static orderByDesc($column)
 * @method CModel_Query|static skip($value)
 * @method CModel_Query|static offset($value)
 * @method CModel_Query|static take($value)
 * @method CModel_Query|static limit($value)
 * @method CModel_Query|static lockForUpdate()                                                                                                         Lock the selected rows in the table for updating.
 * @method bool                exists()                                                                                                                Determine if any rows exist for the current query
 * @method mixed               sum($column)                                                                                                            Retrieve the sum of the values of a given column..
 *
 * @see CModel_Query
 */
class CModel_Relation_BelongsToMany extends CModel_Relation {
    use CModel_Relation_Trait_InteractsWithPivotTable;

    /**
     * Indicates if timestamps are available on the pivot table.
     *
     * @var bool
     */
    public $withTimestamps = false;

    /**
     * The intermediate table for the relation.
     *
     * @var string
     */
    protected $table;

    /**
     * The foreign key of the parent model.
     *
     * @var string
     */
    protected $foreignPivotKey;

    /**
     * The associated key of the relation.
     *
     * @var string
     */
    protected $relatedPivotKey;

    /**
     * The key name of the parent model.
     *
     * @var string
     */
    protected $parentKey;

    /**
     * The key name of the related model.
     *
     * @var string
     */
    protected $relatedKey;

    /**
     * The "name" of the relationship.
     *
     * @var string
     */
    protected $relationName;

    /**
     * The pivot table columns to retrieve.
     *
     * @var array
     */
    protected $pivotColumns = [];

    /**
     * Any pivot table restrictions for where clauses.
     *
     * @var array
     */
    protected $pivotWheres = [];

    /**
     * Any pivot table restrictions for whereIn clauses.
     *
     * @var array
     */
    protected $pivotWhereIns = [];

    /**
     * Any pivot table restrictions for whereNull clauses.
     *
     * @var array
     */
    protected $pivotWhereNulls = [];

    /**
     * The default values for the pivot columns.
     *
     * @var array
     */
    protected $pivotValues = [];

    /**
     * The custom pivot table column for the created_at timestamp.
     *
     * @var string
     */
    protected $pivotCreatedAt;

    /**
     * The custom pivot table column for the updated_at timestamp.
     *
     * @var string
     */
    protected $pivotUpdatedAt;

    /**
     * The class name of the custom pivot model to use for the relationship.
     *
     * @var string
     */
    protected $using;

    /**
     * The name of the accessor to use for the "pivot" relationship.
     *
     * @var string
     */
    protected $accessor = 'pivot';

    /**
     * Create a new belongs to many relationship instance.
     *
     * @param CModel_Query $query
     * @param CModel       $parent
     * @param string       $table
     * @param string       $foreignPivotKey
     * @param string       $relatedPivotKey
     * @param string       $parentKey
     * @param string       $relatedKey
     * @param string       $relationName
     *
     * @return void
     */
    public function __construct(CModel_Query $query, CModel $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName = null) {
        $this->parentKey = $parentKey;
        $this->relatedKey = $relatedKey;
        $this->relationName = $relationName;
        $this->relatedPivotKey = $relatedPivotKey;
        $this->foreignPivotKey = $foreignPivotKey;
        $this->table = $this->resolveTableName($table);

        parent::__construct($query, $parent);
    }

    /**
     * Attempt to resolve the intermediate table name from the given string.
     *
     * @param string $table
     *
     * @return string
     */
    protected function resolveTableName($table) {
        if (!cstr::contains($table, '\\') || !class_exists($table)) {
            return $table;
        }

        $model = new $table();

        if (!$model instanceof CModel) {
            return $table;
        }

        if (in_array(CModel_Relation_Trait_AsPivot::class, c::classUsesRecursive($model))) {
            $this->using($table);
        }

        return $model->getTable();
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints() {
        $this->performJoin();

        if (static::$constraints) {
            $this->addWhereConstraints();
        }
    }

    /**
     * Set the join clause for the relation query.
     *
     * @param null|CModel_Query $query
     *
     * @return $this
     */
    protected function performJoin($query = null) {
        $query = $query ?: $this->query;

        // We need to join to the intermediate table on the related model's primary
        // key column with the intermediate table's foreign key for the related
        // model instance. Then we can set the "where" for the parent models.
        $query->join(
            $this->table,
            $this->getQualifiedRelatedKeyName(),
            '=',
            $this->getQualifiedRelatedPivotKeyName()
        );

        return $this;
    }

    /**
     * Set the where clause for the relation query.
     *
     * @return $this
     */
    protected function addWhereConstraints() {
        $this->query->where(
            $this->getQualifiedForeignPivotKeyName(),
            '=',
            $this->parent->{$this->parentKey}
        );

        return $this;
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param array $models
     *
     * @return void
     */
    public function addEagerConstraints(array $models) {
        $whereIn = $this->whereInMethod($this->parent, $this->parentKey);

        $this->query->{$whereIn}(
            $this->getQualifiedForeignPivotKeyName(),
            $this->getKeys($models, $this->parentKey)
        );
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param array  $models
     * @param string $relation
     *
     * @return array
     */
    public function initRelation(array $models, $relation) {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->related->newCollection());
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param array             $models
     * @param CModel_Collection $results
     * @param string            $relation
     *
     * @return array
     */
    public function match(array $models, CModel_Collection $results, $relation) {
        $dictionary = $this->buildDictionary($results);

        // Once we have an array dictionary of child objects we can easily match the
        // children back to their parent using the dictionary and the keys on the
        // the parent models. Then we will return the hydrated models back out.
        foreach ($models as $model) {
            if (isset($dictionary[$key = $model->{$this->parentKey}])) {
                $model->setRelation(
                    $relation,
                    $this->related->newCollection($dictionary[$key])
                );
            }
        }

        return $models;
    }

    /**
     * Build model dictionary keyed by the relation's foreign key.
     *
     * @param CModel_Collection $results
     *
     * @return array
     */
    protected function buildDictionary(CModel_Collection $results) {
        // First we will build a dictionary of child models keyed by the foreign key
        // of the relation so that we will easily and quickly match them to their
        // parents without having a possibly slow inner loops for every models.
        $dictionary = [];

        foreach ($results as $result) {
            $dictionary[$result->{$this->accessor}->{$this->foreignPivotKey}][] = $result;
        }

        return $dictionary;
    }

    /**
     * Get the class being used for pivot models.
     *
     * @return string
     */
    public function getPivotClass() {
        return $this->using != null ? $this->using : CModel_Relation_Pivot::class;
    }

    /**
     * Specify the custom pivot model to use for the relationship.
     *
     * @param string $class
     *
     * @return CModel_Relation_BelongsToMany
     */
    public function using($class) {
        $this->using = $class;

        return $this;
    }

    /**
     * Specify the custom pivot accessor to use for the relationship.
     *
     * @param string $accessor
     *
     * @return $this
     */
    public function alias($accessor) {
        $this->accessor = $accessor;

        return $this;
    }

    /**
     * Set a where clause for a pivot table column.
     *
     * @param string $column
     * @param mixed  $operator
     * @param mixed  $value
     * @param string $boolean
     *
     * @return $this
     */
    public function wherePivot($column, $operator = null, $value = null, $boolean = 'and') {
        $this->pivotWheres[] = func_get_args();

        return $this->where($this->qualifyPivotColumn($column), $operator, $value, $boolean);
    }

    /**
     * Set a "where between" clause for a pivot table column.
     *
     * @param string $column
     * @param array  $values
     * @param string $boolean
     * @param bool   $not
     *
     * @return $this
     */
    public function wherePivotBetween($column, array $values, $boolean = 'and', $not = false) {
        return $this->whereBetween($this->qualifyPivotColumn($column), $values, $boolean, $not);
    }

    /**
     * Set a "or where between" clause for a pivot table column.
     *
     * @param string $column
     * @param array  $values
     *
     * @return $this
     */
    public function orWherePivotBetween($column, array $values) {
        return $this->wherePivotBetween($column, $values, 'or');
    }

    /**
     * Set a "where pivot not between" clause for a pivot table column.
     *
     * @param string $column
     * @param array  $values
     * @param string $boolean
     *
     * @return $this
     */
    public function wherePivotNotBetween($column, array $values, $boolean = 'and') {
        return $this->wherePivotBetween($column, $values, $boolean, true);
    }

    /**
     * Set a "or where not between" clause for a pivot table column.
     *
     * @param string $column
     * @param array  $values
     *
     * @return $this
     */
    public function orWherePivotNotBetween($column, array $values) {
        return $this->wherePivotBetween($column, $values, 'or', true);
    }

    /**
     * Set a "where in" clause for a pivot table column.
     *
     * @param string $column
     * @param mixed  $values
     * @param string $boolean
     * @param bool   $not
     *
     * @return $this
     */
    public function wherePivotIn($column, $values, $boolean = 'and', $not = false) {
        $this->pivotWhereIns[] = func_get_args();

        return $this->whereIn($this->qualifyPivotColumn($column), $values, $boolean, $not);
    }

    /**
     * Set an "or where" clause for a pivot table column.
     *
     * @param string $column
     * @param mixed  $operator
     * @param mixed  $value
     *
     * @return $this
     */
    public function orWherePivot($column, $operator = null, $value = null) {
        return $this->wherePivot($column, $operator, $value, 'or');
    }

    /**
     * Set a where clause for a pivot table column.
     *
     * In addition, new pivot records will receive this value.
     *
     * @param string|array $column
     * @param mixed        $value
     *
     * @throws \InvalidArgumentException
     *
     * @return $this
     */
    public function withPivotValue($column, $value = null) {
        if (is_array($column)) {
            foreach ($column as $name => $value) {
                $this->withPivotValue($name, $value);
            }

            return $this;
        }

        if (is_null($value)) {
            throw new InvalidArgumentException('The provided value may not be null.');
        }

        $this->pivotValues[] = compact('column', 'value');

        return $this->wherePivot($column, '=', $value);
    }

    /**
     * Set an "or where in" clause for a pivot table column.
     *
     * @param string $column
     * @param mixed  $values
     *
     * @return CModel_Relation_BelongsToMany
     */
    public function orWherePivotIn($column, $values) {
        return $this->wherePivotIn($column, $values, 'or');
    }

    /**
     * Set a "where not in" clause for a pivot table column.
     *
     * @param string $column
     * @param mixed  $values
     * @param string $boolean
     *
     * @return $this
     */
    public function wherePivotNotIn($column, $values, $boolean = 'and') {
        return $this->wherePivotIn($column, $values, $boolean, true);
    }

    /**
     * Set an "or where not in" clause for a pivot table column.
     *
     * @param string $column
     * @param mixed  $values
     *
     * @return $this
     */
    public function orWherePivotNotIn($column, $values) {
        return $this->wherePivotNotIn($column, $values, 'or');
    }

    /**
     * Set a "where null" clause for a pivot table column.
     *
     * @param string $column
     * @param string $boolean
     * @param bool   $not
     *
     * @return $this
     */
    public function wherePivotNull($column, $boolean = 'and', $not = false) {
        $this->pivotWhereNulls[] = func_get_args();

        return $this->whereNull($this->qualifyPivotColumn($column), $boolean, $not);
    }

    /**
     * Set a "where not null" clause for a pivot table column.
     *
     * @param string $column
     * @param string $boolean
     *
     * @return $this
     */
    public function wherePivotNotNull($column, $boolean = 'and') {
        return $this->wherePivotNull($column, $boolean, true);
    }

    /**
     * Set a "or where null" clause for a pivot table column.
     *
     * @param string $column
     * @param bool   $not
     *
     * @return $this
     */
    public function orWherePivotNull($column, $not = false) {
        return $this->wherePivotNull($column, 'or', $not);
    }

    /**
     * Set a "or where not null" clause for a pivot table column.
     *
     * @param string $column
     *
     * @return $this
     */
    public function orWherePivotNotNull($column) {
        return $this->orWherePivotNull($column, true);
    }

    /**
     * Add an "order by" clause for a pivot table column.
     *
     * @param string $column
     * @param string $direction
     *
     * @return $this
     */
    public function orderByPivot($column, $direction = 'asc') {
        return $this->orderBy($this->qualifyPivotColumn($column), $direction);
    }

    /**
     * Find a related model by its primary key or return new instance of the related model.
     *
     * @param mixed $id
     * @param array $columns
     *
     * @return CCollection|\CModel
     */
    public function findOrNew($id, $columns = ['*']) {
        if (is_null($instance = $this->find($id, $columns))) {
            $instance = $this->related->newInstance();
        }

        return $instance;
    }

    /**
     * Get the first related model record matching the attributes or instantiate it.
     *
     * @param array $attributes
     *
     * @return CModel
     */
    public function firstOrNew(array $attributes) {
        if (is_null($instance = $this->where($attributes)->first())) {
            $instance = $this->related->newInstance($attributes);
        }

        return $instance;
    }

    /**
     * Get the first related record matching the attributes or create it.
     *
     * @param array $attributes
     * @param array $joining
     * @param bool  $touch
     *
     * @return CModel
     */
    public function firstOrCreate(array $attributes, array $joining = [], $touch = true) {
        if (is_null($instance = $this->where($attributes)->first())) {
            $instance = $this->create($attributes, $joining, $touch);
        }

        return $instance;
    }

    /**
     * Create or update a related record matching the attributes, and fill it with values.
     *
     * @param array $attributes
     * @param array $values
     * @param array $joining
     * @param bool  $touch
     *
     * @return CModel
     */
    public function updateOrCreate(array $attributes, array $values = [], array $joining = [], $touch = true) {
        if (is_null($instance = $this->where($attributes)->first())) {
            return $this->create($values, $joining, $touch);
        }

        $instance->fill($values);

        $instance->save(['touch' => false]);

        return $instance;
    }

    /**
     * Find a related model by its primary key.
     *
     * @param mixed $id
     * @param array $columns
     *
     * @return null|CModel|CModel_Collection
     */
    public function find($id, $columns = ['*']) {
        if (!$id instanceof CModel && (is_array($id) || $id instanceof Arrayable)) {
            return $this->findMany($id, $columns);
        }

        return $this->where(
            $this->getRelated()->getQualifiedKeyName(),
            '=',
            $this->parseId($id)
        )->first($columns);
    }

    /**
     * Find multiple related models by their primary keys.
     *
     * @param mixed $ids
     * @param array $columns
     *
     * @return CModel_Collection
     */
    public function findMany($ids, $columns = ['*']) {
        $ids = $ids instanceof Arrayable ? $ids->toArray() : $ids;

        if (empty($ids)) {
            return $this->getRelated()->newCollection();
        }

        return $this->whereIn(
            $this->getRelated()->getQualifiedKeyName(),
            $this->parseIds($ids)
        )->get($columns);
    }

    /**
     * Find a related model by its primary key or throw an exception.
     *
     * @param mixed $id
     * @param array $columns
     *
     * @throws CModel_Exception_ModelNotFoundException
     *
     * @return CModel|CModel_Collection
     */
    public function findOrFail($id, $columns = ['*']) {
        $result = $this->find($id, $columns);

        $id = $id instanceof Arrayable ? $id->toArray() : $id;

        if (is_array($id)) {
            if (count($result) == count(array_unique($id))) {
                return $result;
            }
        } elseif (!is_null($result)) {
            return $result;
        }

        throw (new CModel_Exception_ModelNotFoundException())->setModel(get_class($this->related), $id);
    }

    /**
     * Add a basic where clause to the query, and return the first result.
     *
     * @param \Closure|string|array $column
     * @param mixed                 $operator
     * @param mixed                 $value
     * @param string                $boolean
     *
     * @return \CModel|static
     */
    public function firstWhere($column, $operator = null, $value = null, $boolean = 'and') {
        return $this->where($column, $operator, $value, $boolean)->first();
    }

    /**
     * Execute the query and get the first result.
     *
     * @param array $columns
     *
     * @return mixed
     */
    public function first($columns = ['*']) {
        $results = $this->take(1)->get($columns);

        return count($results) > 0 ? $results->first() : null;
    }

    /**
     * Execute the query and get the first result or throw an exception.
     *
     * @param array $columns
     *
     * @throws \CModel_Exception_ModelNotFoundException
     *
     * @return \CModel|static
     */
    public function firstOrFail($columns = ['*']) {
        if (!is_null($model = $this->first($columns))) {
            return $model;
        }

        throw (new CModel_Exception_ModelNotFoundException())->setModel(get_class($this->related));
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults() {
        return !is_null($this->parent->{$this->parentKey})
            ? $this->get()
            : $this->related->newCollection();
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param array $columns
     *
     * @return CModel_Collection
     */
    public function get($columns = ['*']) {
        // First we'll add the proper select columns onto the query so it is run with
        // the proper columns. Then, we will get the results and hydrate out pivot
        // models with the result of those columns as a separate model relation.
        $builder = $this->query->applyScopes();

        $columns = $builder->getQuery()->columns ? [] : $columns;

        $models = $builder->addSelect(
            $this->shouldSelect($columns)
        )->getModels();

        $this->hydratePivotRelation($models);

        // If we actually found models we will also eager load any relationships that
        // have been specified as needing to be eager loaded. This will solve the
        // n + 1 query problem for the developer and also increase performance.
        if (count($models) > 0) {
            $models = $builder->eagerLoadRelations($models);
        }

        return $this->related->newCollection($models);
    }

    /**
     * Get the select columns for the relation query.
     *
     * @param array $columns
     *
     * @return array
     */
    protected function shouldSelect(array $columns = ['*']) {
        if ($columns == ['*']) {
            $columns = [$this->related->getTable() . '.*'];
        }

        return array_merge($columns, $this->aliasedPivotColumns());
    }

    /**
     * Get the pivot columns for the relation.
     *
     * "pivot_" is prefixed ot each column for easy removal later.
     *
     * @return array
     */
    protected function aliasedPivotColumns() {
        $defaults = [$this->foreignPivotKey, $this->relatedPivotKey];

        return c::collect(array_merge($defaults, $this->pivotColumns))->map(function ($column) {
            return $this->qualifyPivotColumn($column) . ' as pivot_' . $column;
        })->unique()->all();
    }

    /**
     * Get a paginator for the "select" statement.
     *
     * @param int      $perPage
     * @param array    $columns
     * @param string   $pageName
     * @param null|int $page
     *
     * @return CPagination_LengthAwarePaginator
     */
    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null) {
        $this->query->addSelect($this->shouldSelect($columns));

        return c::tap($this->query->paginate($perPage, $columns, $pageName, $page), function ($paginator) {
            $this->hydratePivotRelation($paginator->items());
        });
    }

    /**
     * Paginate the given query into a simple paginator.
     *
     * @param int      $perPage
     * @param array    $columns
     * @param string   $pageName
     * @param null|int $page
     *
     * @return CPagination_Paginator
     */
    public function simplePaginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null) {
        $this->query->addSelect($this->shouldSelect($columns));

        return c::tap($this->query->simplePaginate($perPage, $columns, $pageName, $page), function ($paginator) {
            $this->hydratePivotRelation($paginator->items());
        });
    }

    /**
     * Paginate the given query into a cursor paginator.
     *
     * @param null|int    $perPage
     * @param array       $columns
     * @param string      $cursorName
     * @param null|string $cursor
     *
     * @return \CPagination_CursorPaginatorInterface
     */
    public function cursorPaginate($perPage = null, $columns = ['*'], $cursorName = 'cursor', $cursor = null) {
        $this->query->addSelect($this->shouldSelect($columns));

        return c::tap($this->query->cursorPaginate($perPage, $columns, $cursorName, $cursor), function ($paginator) {
            $this->hydratePivotRelation($paginator->items());
        });
    }

    /**
     * Chunk the results of the query.
     *
     * @param int      $count
     * @param callable $callback
     *
     * @return bool
     */
    public function chunk($count, callable $callback) {
        $this->query->addSelect($this->shouldSelect());

        return $this->query->chunk($count, function ($results) use ($callback) {
            $this->hydratePivotRelation($results->all());

            return $callback($results);
        });
    }

    /**
     * Chunk the results of a query by comparing numeric IDs.
     *
     * @param int         $count
     * @param callable    $callback
     * @param null|string $column
     * @param null|string $alias
     *
     * @return bool
     */
    public function chunkById($count, callable $callback, $column = null, $alias = null) {
        $this->query->addSelect($this->shouldSelect());

        if ($column == null) {
            $column = $this->getRelated()->qualifyColumn(
                $this->getRelatedKeyName()
            );
        }

        if ($alias == null) {
            $alias = $this->getRelatedKeyName();
        }

        return $this->query->chunkById($count, function ($results) use ($callback) {
            $this->hydratePivotRelation($results->all());

            return $callback($results);
        }, $column, $alias);
    }

    /**
     * Execute a callback over each item while chunking.
     *
     * @param callable $callback
     * @param int      $count
     *
     * @return bool
     */
    public function each(callable $callback, $count = 1000) {
        return $this->chunk($count, function ($results) use ($callback) {
            foreach ($results as $key => $value) {
                if ($callback($value, $key) === false) {
                    return false;
                }
            }
        });
    }

    /**
     * Query lazily, by chunks of the given size.
     *
     * @param int $chunkSize
     *
     * @return \CCollection_LazyCollection
     */
    public function lazy($chunkSize = 1000) {
        return $this->prepareQueryBuilder()->lazy($chunkSize)->map(function ($model) {
            $this->hydratePivotRelation([$model]);

            return $model;
        });
    }

    /**
     * Query lazily, by chunking the results of a query by comparing IDs.
     *
     * @param int         $chunkSize
     * @param null|string $column
     * @param null|string $alias
     *
     * @return \CCollection_LazyCollection
     */
    public function lazyById($chunkSize = 1000, $column = null, $alias = null) {
        $column ??= $this->getRelated()->qualifyColumn(
            $this->getRelatedKeyName()
        );

        $alias ??= $this->getRelatedKeyName();

        return $this->prepareQueryBuilder()->lazyById($chunkSize, $column, $alias)->map(function ($model) {
            $this->hydratePivotRelation([$model]);

            return $model;
        });
    }

    /**
     * Get a lazy collection for the given query.
     *
     * @return CCollection_LazyCollection
     */
    public function cursor() {
        $this->query->addSelect($this->shouldSelect());

        return $this->query->cursor()->map(function ($model) {
            $this->hydratePivotRelation([$model]);

            return $model;
        });
    }

    /**
     * Prepare the query builder for query execution.
     *
     * @return \CModel_Query
     */
    protected function prepareQueryBuilder() {
        return $this->query->addSelect($this->shouldSelect());
    }

    /**
     * Hydrate the pivot table relationship on the models.
     *
     * @param array $models
     *
     * @return void
     */
    protected function hydratePivotRelation(array $models) {
        // To hydrate the pivot relationship, we will just gather the pivot attributes
        // and create a new Pivot model, which is basically a dynamic model that we
        // will set the attributes, table, and connections on it so it will work.
        foreach ($models as $model) {
            $model->setRelation($this->accessor, $this->newExistingPivot(
                $this->migratePivotAttributes($model)
            ));
        }
    }

    /**
     * Get the pivot attributes from a model.
     *
     * @param CModel $model
     *
     * @return array
     */
    protected function migratePivotAttributes(CModel $model) {
        $values = [];

        foreach ($model->getAttributes() as $key => $value) {
            // To get the pivots attributes we will just take any of the attributes which
            // begin with "pivot_" and add those to this arrays, as well as unsetting
            // them from the parent's models since they exist in a different table.
            if (strpos($key, 'pivot_') === 0) {
                $values[substr($key, 6)] = $value;

                unset($model->$key);
            }
        }

        return $values;
    }

    /**
     * If we're touching the parent model, touch.
     *
     * @return void
     */
    public function touchIfTouching() {
        if ($this->touchingParent()) {
            $this->getParent()->touch();
        }

        if ($this->getParent()->touches($this->relationName)) {
            $this->touch();
        }
    }

    /**
     * Determine if we should touch the parent on sync.
     *
     * @return bool
     */
    protected function touchingParent() {
        return $this->getRelated()->touches($this->guessInverseRelation());
    }

    /**
     * Attempt to guess the name of the inverse of the relation.
     *
     * @return string
     */
    protected function guessInverseRelation() {
        return cstr::camel(cstr::pluralStudly(c::classBasename($this->getParent())));
    }

    /**
     * Touch all of the related models for the relationship.
     *
     * E.g.: Touch all roles associated with this user.
     *
     * @return void
     */
    public function touch() {
        $key = $this->getRelated()->getKeyName();

        $columns = [
            $this->related->getUpdatedAtColumn() => $this->related->freshTimestampString(),
        ];

        // If we actually have IDs for the relation, we will run the query to update all
        // the related model's timestamps, to make sure these all reflect the changes
        // to the parent models. This will help us keep any caching synced up here.
        if (count($ids = $this->allRelatedIds()) > 0) {
            $this->getRelated()->newQueryWithoutRelationships()->whereIn($key, $ids)->update($columns);
        }
    }

    /**
     * Get all of the IDs for the related models.
     *
     * @return CCollection
     */
    public function allRelatedIds() {
        return $this->newPivotQuery()->pluck($this->relatedPivotKey);
    }

    /**
     * Save a new model and attach it to the parent model.
     *
     * @param CModel $model
     * @param array  $pivotAttributes
     * @param bool   $touch
     *
     * @return CModel
     */
    public function save(CModel $model, array $pivotAttributes = [], $touch = true) {
        $model->save(['touch' => false]);

        $this->attach($model, $pivotAttributes, $touch);

        return $model;
    }

    /**
     * Save an array of new models and attach them to the parent model.
     *
     * @param CCollection|array $models
     * @param array             $pivotAttributes
     *
     * @return array
     */
    public function saveMany($models, array $pivotAttributes = []) {
        foreach ($models as $key => $model) {
            $pivotAttributesValue = isset($pivotAttributes[$key]) ? $pivotAttributes[$key] : [];
            $this->save($model, (array) $pivotAttributesValue, false);
        }

        $this->touchIfTouching();

        return $models;
    }

    /**
     * Create a new instance of the related model.
     *
     * @param array $attributes
     * @param array $joining
     * @param bool  $touch
     *
     * @return \CModel
     */
    public function create(array $attributes = [], array $joining = [], $touch = true) {
        $instance = $this->related->newInstance($attributes);

        // Once we save the related model, we need to attach it to the base model via
        // through intermediate table so we'll use the existing "attach" method to
        // accomplish this which will insert the record and any more attributes.
        $instance->save(['touch' => false]);

        $this->attach($instance, $joining, $touch);

        return $instance;
    }

    /**
     * Create an array of new instances of the related models.
     *
     * @param array $records
     * @param array $joinings
     *
     * @return array
     */
    public function createMany(array $records, array $joinings = []) {
        $instances = [];

        foreach ($records as $key => $record) {
            $joiningsValue = isset($joinings[$key]) ? $joinings[$key] : [];
            $instances[] = $this->create($record, (array) ($joiningsValue), false);
        }

        $this->touchIfTouching();

        return $instances;
    }

    /**
     * Add the constraints for a relationship query.
     *
     * @param CModel_Query $query
     * @param CModel_Query $parentQuery
     * @param array|mixed  $columns
     *
     * @return CModel_Query
     */
    public function getRelationExistenceQuery(CModel_Query $query, CModel_Query $parentQuery, $columns = ['*']) {
        if ($parentQuery->getQuery()->from == $query->getQuery()->from) {
            return $this->getRelationExistenceQueryForSelfJoin($query, $parentQuery, $columns);
        }

        $this->performJoin($query);

        return parent::getRelationExistenceQuery($query, $parentQuery, $columns);
    }

    /**
     * Add the constraints for a relationship query on the same table.
     *
     * @param CModel_Query $query
     * @param CModel_Query $parentQuery
     * @param array|mixed  $columns
     *
     * @return CModel_Query
     */
    public function getRelationExistenceQueryForSelfJoin(CModel_Query $query, CModel_Query $parentQuery, $columns = ['*']) {
        $query->select($columns);

        $query->from($this->related->getTable() . ' as ' . $hash = $this->getRelationCountHash());

        $this->related->setTable($hash);

        $this->performJoin($query);

        return parent::getRelationExistenceQuery($query, $parentQuery, $columns);
    }

    /**
     * Get the key for comparing against the parent key in "has" query.
     *
     * @return string
     */
    public function getExistenceCompareKey() {
        return $this->getQualifiedForeignPivotKeyName();
    }

    /**
     * Specify that the pivot table has creation and update timestamps.
     *
     * @param mixed $createdAt
     * @param mixed $updatedAt
     *
     * @return $this
     */
    public function withTimestamps($createdAt = null, $updatedAt = null) {
        $this->withTimestamps = true;

        $this->pivotCreatedAt = $createdAt;
        $this->pivotUpdatedAt = $updatedAt;

        return $this->withPivot($this->createdAt(), $this->updatedAt());
    }

    /**
     * Get the name of the "created at" column.
     *
     * @return string
     */
    public function createdAt() {
        return $this->pivotCreatedAt ?: $this->parent->getCreatedAtColumn();
    }

    /**
     * Get the name of the "updated at" column.
     *
     * @return string
     */
    public function updatedAt() {
        return $this->pivotUpdatedAt ?: $this->parent->getUpdatedAtColumn();
    }

    /**
     * Get the foreign key for the relation.
     *
     * @return string
     */
    public function getForeignPivotKeyName() {
        return $this->foreignPivotKey;
    }

    /**
     * Get the fully qualified foreign key for the relation.
     *
     * @return string
     */
    public function getQualifiedForeignPivotKeyName() {
        return $this->qualifyPivotColumn($this->foreignPivotKey);
    }

    /**
     * Get the "related key" for the relation.
     *
     * @return string
     */
    public function getRelatedPivotKeyName() {
        return $this->relatedPivotKey;
    }

    /**
     * Get the fully qualified "related key" for the relation.
     *
     * @return string
     */
    public function getQualifiedRelatedPivotKeyName() {
        return $this->qualifyPivotColumn($this->relatedPivotKey);
    }

    /**
     * Get the parent key for the relationship.
     *
     * @return string
     */
    public function getParentKeyName() {
        return $this->parentKey;
    }

    /**
     * Get the fully qualified parent key name for the relation.
     *
     * @return string
     */
    public function getQualifiedParentKeyName() {
        return $this->parent->qualifyColumn($this->parentKey);
    }

    /**
     * Get the related key for the relationship.
     *
     * @return string
     */
    public function getRelatedKeyName() {
        return $this->relatedKey;
    }

    /**
     * Get the fully qualified related key name for the relation.
     *
     * @return string
     */
    public function getQualifiedRelatedKeyName() {
        return $this->related->qualifyColumn($this->relatedKey);
    }

    /**
     * Get the intermediate table for the relationship.
     *
     * @return string
     */
    public function getTable() {
        return $this->table;
    }

    /**
     * Get the relationship name for the relationship.
     *
     * @return string
     */
    public function getRelationName() {
        return $this->relationName;
    }

    /**
     * Get the name of the pivot accessor for this relationship.
     *
     * @return string
     */
    public function getPivotAccessor() {
        return $this->accessor;
    }

    /**
     * Get the pivot columns for this relationship.
     *
     * @return array
     */
    public function getPivotColumns() {
        return $this->pivotColumns;
    }

    /**
     * Qualify the given column name by the pivot table.
     *
     * @param string $column
     *
     * @return string
     */
    public function qualifyPivotColumn($column) {
        return cstr::contains($column, '.')
            ? $column
            : $this->table . '.' . $column;
    }
}
