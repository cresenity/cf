<?php

use Illuminate\Contracts\Support\Arrayable;

defined('SYSPATH') or die('No direct access allowed.');

/**
 * Class CModel_Query.
 *
 * @template TModel of CModel
 *
 * @property-read CModel_HigherOrderBuilderProxy $orWhere
 * @property-read CModel_HigherOrderBuilderProxy $whereNot
 * @property-read CModel_HigherOrderBuilderProxy $orWhereNot
 *
 * @mixin CDatabase_Query_Builder
 *
 * @method static              TModel         create($attributes = [])                                                  Find a model by its primary key.
 * @method mixed               value($column)                                                                           Get a single column's value from the first result of a query.
 * @method mixed               pluck($column, $key = null)                                                                           Get a single column's value from the first result of a query.
 * @method void                chunk($count, callable $callback)                                                        Chunk the results of the query.
 * @method \CCollection        lists($column, $key = null)                                                              Get an array with the values of a given column.
 * @method void                onDelete(Closure $callback)                                                              Register a replacement for the default delete function.
 * @method CModel[]            getModels($columns = null)                                                               Get the hydrated models without eager loading.
 * @method array               eagerLoadRelations(array $models)                                                        Eager load the relationships for the models.
 * @method array               loadRelation(array $models, $name, Closure $constraints)                                 Eagerly load the relationship on a set of models.
 * @method static              where($column, $operator = null, $value = null, $boolean = 'and')                        Add a basic where clause to the query.
 * @method static              whereHas($relation, Closure $callback = null, $operator = '>=', $count = 1)              Add a relationship count / exists condition to the query with where clauses.
 * @method static              orWhere($column, $operator = null, $value = null)                                        Add an "or where" clause to the query.
 * @method static              has($relation, $operator = '>=', $count = 1, $boolean = 'and', Closure $callback = null) Add a relationship count condition to the query.
 * @method static              whereRaw($sql, array $bindings = [], $boolean = 'and')
 * @method static              whereBetween($column, array $values, $boolean = 'and', $not = false)
 * @method static              whereNotBetween($column, array $values, $boolean = 'and')
 * @method static              whereNested(Closure $callback, $boolean = 'and')
 * @method static              addNestedWhereQuery($query, $boolean = 'and')
 * @method static              whereExists(Closure $callback, $boolean = 'and', $not = false)
 * @method static              whereNotExists(Closure $callback, $boolean = 'and')
 * @method static              whereIn($column, $values, $boolean = 'and', $not = false)
 * @method static              whereNotIn($column, $values, $boolean = 'and')
 * @method static              whereNull($columns, $boolean = 'and', $not = false)
 * @method static              whereNotNull($column, $boolean = 'and')
 * @method static              orWhereRaw($sql, array $bindings = [])
 * @method static              orWhereBetween($column, array $values)
 * @method static              orWhereNotBetween($column, array $values)
 * @method static              orWhereExists(Closure $callback, $not = false)
 * @method static              orWhereNotExists(Closure $callback)
 * @method static              orWhereIn($column, $values)
 * @method static              orWhereNotIn($column, $values)
 * @method static              orWhereNull($column)
 * @method static              orWhereNotNull($column)
 * @method static              whereDate($column, $operator, $value = null, $boolean = 'and')
 * @method static              whereDay($column, $operator, $value = null, $boolean = 'and')
 * @method static              whereMonth($column, $operator, $value = null, $boolean = 'and')
 * @method static              whereYear($column, $operator, $value = null, $boolean = 'and')
 * @method static              join($table, $first, $operator = null, $second = null, $type = 'inner', $where = false)
 * @method static              select($columns = null, ...$args)
 * @method static              groupBy(...$groups)
 * @method static              newQuery()
 * @method static              withTrashed()
 * @method static              from($table, $as = null)
 * @method static              leftJoinSub($query, $as, $first, $operator = null, $second = null)
 * @method static              addSelect($column)
 * @method static              selectRaw($expression, array $bindings = [])
 * @method static              orderBy($column, $direction = 'asc')
 * @method static              orderByDesc($column)
 * @method static              skip($value)
 * @method static              offset($value)
 * @method static              take($value)
 * @method static              limit($value)
 * @method static              lockForUpdate()                                                                          Lock the selected rows in the table for updating.
 * @method bool                exists()                                                                                 Determine if any rows exist for the current query
 * @method mixed               sum($column)                                                                             Retrieve the sum of the values of a given column..
 * @method void                truncate()                                                                               Run a truncate statement on the table.
 * @method CDatabase_Result    insert(array $values)                                                                     Insert new records into the table.
 *
 * @property-read CModel_HigherOrderBuilderProxy $orWhere
 * @property-read CModel_HigherOrderBuilderProxy $whereNot
 * @property-read CModel_HigherOrderBuilderProxy $orWhereNot
 *
 * @see CModel
 * @see CDatabase_Query_Builder
 */
class CModel_Query {
    use CDatabase_Trait_Builder,
        CModel_Trait_QueriesRelationships,
        CTrait_ForwardsCalls;

    /**
     * The attributes that should be added to new models created by this builder.
     *
     * @var array
     */
    public $pendingAttributes = [];

    /**
     * The base query builder instance.
     *
     * @var CDatabase_Query_Builder
     */
    protected $query;

    /**
     * The model being queried.
     *
     * @var CModel
     */
    protected $model;

    /**
     * The relationships that should be eager loaded.
     *
     * @var array
     */
    protected $eagerLoad = [];

    /**
     * All of the globally registered builder macros.
     *
     * @var array
     */
    protected static $macros = [];

    /**
     * All of the locally registered builder macros.
     *
     * @var array
     */
    protected $localMacros = [];

    /**
     * A replacement for the typical delete function.
     *
     * @var \Closure
     */
    protected $onDelete;

    /**
     * The methods that should be returned from query builder.
     *
     * @var string[]
     */
    protected $passthru = [
        'aggregate',
        'average',
        'avg',
        'count',
        'dd',
        'ddRawSql',
        'doesntExist',
        'doesntExistOr',
        'dump',
        'dumpRawSql',
        'exists',
        'existsOr',
        'explain',
        'getBindings',
        'getConnection',
        'getGrammar',
        'implode',
        'insert',
        'insertGetId',
        'insertOrIgnore',
        'insertUsing',
        'max',
        'min',
        'raw',
        'rawValue',
        'sum',
        'toSql',
        'toRawSql',
    ];

    /**
     * Applied global scopes.
     *
     * @var array
     */
    protected $scopes = [];

    /**
     * Removed global scopes.
     *
     * @var array
     */
    protected $removedScopes = [];

    /**
     * Create a new Eloquent query builder instance.
     *
     * @param CDatabase_Query_Builder $query
     *
     * @return void
     */
    public function __construct(CDatabase_Query_Builder $query) {
        $this->query = $query;
    }

    /**
     * Create and return an un-saved model instance.
     *
     * @param array $attributes
     *
     * @return CModel
     */
    public function make(array $attributes = []) {
        return $this->newModelInstance($attributes);
    }

    /**
     * Register a new global scope.
     *
     * @param string                          $identifier
     * @param CModel_Interface_Scope|\Closure $scope
     *
     * @return $this
     */
    public function withGlobalScope($identifier, $scope) {
        $this->scopes[$identifier] = $scope;

        if (method_exists($scope, 'extend')) {
            //pass extend to variable to surpress intelephense vscode error warning
            $method = 'extend';
            $scope->$method($this);
        }

        return $this;
    }

    /**
     * Remove a registered global scope.
     *
     * @param \CModel_Interface_Scope|string $scope
     *
     * @return $this
     */
    public function withoutGlobalScope($scope) {
        if (!is_string($scope)) {
            $scope = get_class($scope);
        }

        unset($this->scopes[$scope]);

        $this->removedScopes[] = $scope;

        return $this;
    }

    /**
     * Remove all or passed registered global scopes.
     *
     * @param null|array $scopes
     *
     * @return $this
     */
    public function withoutGlobalScopes(?array $scopes = null) {
        if (is_array($scopes)) {
            foreach ($scopes as $scope) {
                $this->withoutGlobalScope($scope);
            }
        } else {
            $this->scopes = [];
        }

        return $this;
    }

    /**
     * Get an array of global scopes that were removed from the query.
     *
     * @return array
     */
    public function removedScopes() {
        return $this->removedScopes;
    }

    /**
     * Add a where clause on the primary key to the query.
     *
     * @param mixed $id
     *
     * @return $this
     */
    public function whereKey($id) {
        if (is_array($id) || $id instanceof Arrayable) {
            $this->query->whereIn($this->model->getQualifiedKeyName(), $id);

            return $this;
        }

        return $this->where($this->model->getQualifiedKeyName(), '=', $id);
    }

    /**
     * Add a where clause on the primary key to the query.
     *
     * @param mixed $id
     *
     * @return $this
     */
    public function whereKeyNot($id) {
        if (is_array($id) || $id instanceof Arrayable) {
            $this->query->whereNotIn($this->model->getQualifiedKeyName(), $id);

            return $this;
        }

        return $this->where($this->model->getQualifiedKeyName(), '!=', $id);
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param string|array|\Closure $column
     * @param string                $operator
     * @param mixed                 $value
     * @param string                $boolean
     *
     * @return $this
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and') {
        if ($column instanceof Closure) {
            $query = $this->model->newQueryWithoutScopes();

            $column($query);

            $this->query->addNestedWhereQuery($query->getQuery(), $boolean);
        } else {
            $this->query->where($column, $operator, $value, $boolean);
        }

        return $this;
    }

    /**
     * Add a basic where clause to the query, and return the first result.
     *
     * @param \Closure|string|array|\CDatabase_Query_Expression $column
     * @param mixed                                             $operator
     * @param mixed                                             $value
     * @param string                                            $boolean
     *
     * @return null|\CModel|static
     */
    public function firstWhere($column, $operator = null, $value = null, $boolean = 'and') {
        return $this->where(...func_get_args())->first();
    }

    /**
     * Add an "or where" clause to the query.
     *
     * @param \Closure|array|string|\CDatabase_Query_Expression $column
     * @param string                                            $operator
     * @param mixed                                             $value
     *
     * @return static
     */
    public function orWhere($column, $operator = null, $value = null) {
        list($value, $operator) = $this->query->prepareValueAndOperator(
            $value,
            $operator,
            func_num_args() === 2
        );

        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * Create a collection of models from plain arrays.
     *
     * @param array|CDatabase_Result $items
     *
     * @return CModel_Collection
     */
    public function hydrate($items) {
        $instance = $this->newModelInstance();

        return $instance->newCollection(array_map(function ($item) use ($instance) {
            return $instance->newFromBuilder($item);
        }, $items));
    }

    /**
     * Create a collection of models from a raw query.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return CModel_Collection
     */
    public function fromQuery($query, $bindings = []) {
        return $this->hydrate(
            $this->query->getConnection()->query($query, $bindings)
        );
    }

    /**
     * Find a model by its primary key.
     *
     * @param mixed $id
     * @param array $columns
     *
     * @return null|static|CModel|CModel_Collection|static[]
     *
     * @phpstan-return ($id is (\Illuminate\Contracts\Support\Arrayable<array-key, mixed>|array<mixed>) ? \CModel_Collection<int, TModel> : TModel)|null
     */
    public function find($id, $columns = ['*']) {
        if (is_array($id) || $id instanceof Arrayable) {
            return $this->findMany($id, $columns);
        }

        return $this->whereKey($id)->first($columns);
    }

    /**
     * Find multiple models by their primary keys.
     *
     * @param \Illuminate\Contracts\Support\Arrayable|array $ids
     * @param array                                         $columns
     *
     * @return CModel_Collection
     */
    public function findMany($ids, $columns = ['*']) {
        if (empty($ids)) {
            return $this->model->newCollection();
        }

        return $this->whereKey($ids)->get($columns);
    }

    /**
     * Find a model by its primary key or throw an exception.
     *
     * @param mixed $id
     * @param array $columns
     *
     * @throws \CModel_Exception_ModelNotFoundException
     *
     * @return CModel|CModel_Collection
     *
     * @phpstan-return ($id is (\Illuminate\Contracts\Support\Arrayable<array-key, mixed>|array<mixed>) ? \CModel_Collection<int, TModel> : TModel)
     */
    public function findOrFail($id, $columns = ['*']) {
        $result = $this->find($id, $columns);

        if (is_array($id)) {
            if (count($result) == count(array_unique($id))) {
                return $result;
            }
        } elseif (!is_null($result)) {
            return $result;
        }

        throw (new CModel_Exception_ModelNotFoundException())->setModel(
            get_class($this->model),
            $id
        );
    }

    /**
     * Find a model by its primary key or call a callback.
     *
     * @param mixed         $id
     * @param array|Closure $columns
     * @param null|Closure  $callback
     *
     * @return null|CModel|CModel_Collection|mixed
     */
    public function findOr($id, $columns = ['*'], ?Closure $callback = null) {
        if ($columns instanceof Closure) {
            $callback = $columns;
            $columns = ['*'];
        }
        $result = $this->find($id, $columns);
        if (is_array($id) || $id instanceof CInterface_Arrayable) {
            if (count($result) === count(array_unique(is_array($id) ? $id : $id->toArray()))) {
                return $result;
            }
        } elseif (!is_null($result)) {
            return $result;
        }

        return $callback ? $callback() : null;
    }

    /**
     * Find a model by its primary key or return fresh model instance.
     *
     * @param mixed $id
     * @param array $columns
     *
     * @return CModel
     *
     * @phpstan-return TModel
     */
    public function findOrNew($id, $columns = ['*']) {
        if (!is_null($model = $this->find($id, $columns))) {
            return $model;
        }

        return $this->newModelInstance();
    }

    /**
     * Get the first record matching the attributes or instantiate it.
     *
     * @param array $attributes
     * @param array $values
     *
     * @return CModel
     *
     * @phpstan-return TModel
     */
    public function firstOrNew(array $attributes, array $values = []) {
        if (!is_null($instance = $this->where($attributes)->first())) {
            return $instance;
        }

        return $this->newModelInstance($attributes + $values);
    }

    /**
     * Get the first record matching the attributes or create it.
     *
     * @param array $attributes
     * @param array $values
     *
     * @return CModel
     */
    public function firstOrCreate(array $attributes, array $values = []) {
        if (!is_null($instance = (clone $this)->where($attributes)->first())) {
            return $instance;
        }

        return c::tap($this->newModelInstance($attributes + $values), function ($instance) {
            $instance->save();
        });
    }

    /**
     * Create or update a record matching the attributes, and fill it with values.
     *
     * @param array $attributes
     * @param array $values
     *
     * @return CModel
     */
    public function updateOrCreate(array $attributes, array $values = []) {
        return c::tap($this->firstOrNew($attributes), function ($instance) use ($values) {
            $instance->fill($values)->save();
        });
    }

    /**
     * Execute the query and get the first result or throw an exception.
     *
     * @param array $columns
     *
     * @throws CModel_Exception_ModelNotFoundException
     *
     * @return CModel|static
     */
    public function firstOrFail($columns = ['*']) {
        if (!is_null($model = $this->first($columns))) {
            return $model;
        }

        throw (new CModel_Exception_ModelNotFoundException())->setModel(get_class($this->model));
    }

    /**
     * Execute the query and get the first result or call a callback.
     *
     * @param \Closure|array $columns
     * @param null|\Closure  $callback
     *
     * @return CModel|static|mixed
     */
    public function firstOr($columns = ['*'], ?Closure $callback = null) {
        if ($columns instanceof Closure) {
            $callback = $columns;

            $columns = ['*'];
        }

        if (!is_null($model = $this->first($columns))) {
            return $model;
        }

        return call_user_func($callback);
    }

    /**
     * Get a single column's value from the first result of a query.
     *
     * @param string $column
     *
     * @return mixed
     */
    public function value($column) {
        if ($result = $this->first([$column])) {
            $column = $column instanceof CDatabase_Contract_Query_ExpressionInterface ? $column->getValue($this->getGrammar()) : $column;

            return $result->{cstr::afterLast($column, '.')};
        }
    }

    /**
     * Get a single column's value from the first result of a query if it's the sole matching record.
     *
     * @param string|\CDatabase_Contract_Query_ExpressionInterface $column
     *
     * @throws \CModel_Exception_ModelNotFoundException<\CModel>
     * @throws \CDatabase_Exception_MultipleRecordsFoundException
     *
     * @return mixed
     */
    public function soleValue($column) {
        $column = $column instanceof CDatabase_Contract_Query_ExpressionInterface ? $column->getValue($this->getGrammar()) : $column;

        return $this->sole([$column])->{cstr::afterLast($column, '.')};
    }

    /**
     * Get a single column's value from the first result of the query or throw an exception.
     *
     * @param string|\CDatabase_Contract_Query_ExpressionInterface $column
     *
     * @throws \CModel_Exception_ModelNotFoundException<\CModel>
     *
     * @return mixed
     */
    public function valueOrFail($column) {
        $column = $column instanceof CDatabase_Contract_Query_ExpressionInterface ? $column->getValue($this->getGrammar()) : $column;

        return $this->firstOrFail([$column])->{cstr::afterLast($column, '.')};
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param array $columns
     *
     * @return \CModel_Collection|static[]
     */
    public function get($columns = ['*']) {
        $builder = $this->applyScopes();

        // If we actually found models we will also eager load any relationships that
        // have been specified as needing to be eager loaded, which will solve the
        // n+1 query issue for the developers to avoid running a lot of queries.
        if (count($models = $builder->getModels($columns)) > 0) {
            $models = $builder->eagerLoadRelations($models);
        }

        return $builder->getModel()->newCollection($models);
    }

    /**
     * Get the hydrated models without eager loading.
     *
     * @param array $columns
     *
     * @return CModel[]
     */
    public function getModels($columns = ['*']) {
        return $this->model->hydrate($this->query->get($columns)->all())->all();
    }

    /**
     * Eager load the relationships for the models.
     *
     * @param array $models
     *
     * @return array
     */
    public function eagerLoadRelations(array $models) {
        foreach ($this->eagerLoad as $name => $constraints) {
            // For nested eager loads we'll skip loading them here and they will be set as an
            // eager load on the query to retrieve the relation so that they will be eager
            // loaded on that query, because that is where they get hydrated as models.
            if (strpos($name, '.') === false) {
                $models = $this->eagerLoadRelation($models, $name, $constraints);
            }
        }

        return $models;
    }

    /**
     * Eagerly load the relationship on a set of models.
     *
     * @param array    $models
     * @param string   $name
     * @param \Closure $constraints
     *
     * @return array
     */
    protected function eagerLoadRelation(array $models, $name, Closure $constraints) {
        // First we will "back up" the existing where conditions on the query so we can
        // add our eager constraints. Then we will merge the wheres that were on the
        // query back to it in order that any where conditions might be specified.
        $relation = $this->getRelation($name);

        $relation->addEagerConstraints($models);

        $constraints($relation);

        // Once we have the results, we just match those back up to their parent models
        // using the relationship instance. Then we just return the finished arrays
        // of models which have been eagerly hydrated and are readied for return.
        return $relation->match(
            $relation->initRelation($models, $name),
            $relation->getEager(),
            $name
        );
    }

    /**
     * Get the relation instance for the given relation name.
     *
     * @param string $name
     *
     * @return CModel_Relation
     */
    public function getRelation($name) {
        // We want to run a relationship query without any constrains so that we will
        // not have to remove these where clauses manually which gets really hacky
        // and error prone. We don't want constraints because we add eager ones.
        $relation = CModel_Relation::noConstraints(function () use ($name) {
            try {
                return $this->getModel()->newInstance()->{$name}();
            } catch (BadMethodCallException $e) {
                throw CModel_Exception_RelationNotFoundException::make($this->getModel(), $name);
            }
        });

        $nested = $this->relationsNestedUnder($name);

        // If there are nested relationships set on the query, we will put those onto
        // the query instances so that they can be handled after this relationship
        // is loaded. In this way they will all trickle down as they are loaded.
        if (count($nested) > 0) {
            $relation->getQuery()->with($nested);
        }

        return $relation;
    }

    /**
     * Get the deeply nested relations for a given top-level relation.
     *
     * @param string $relation
     *
     * @return array
     */
    protected function relationsNestedUnder($relation) {
        $nested = [];

        // We are basically looking for any relationships that are nested deeper than
        // the given top-level relationship. We will just check for any relations
        // that start with the given top relations and adds them to our arrays.
        foreach ($this->eagerLoad as $name => $constraints) {
            if ($this->isNestedUnder($relation, $name)) {
                $nested[substr($name, strlen($relation . '.'))] = $constraints;
            }
        }

        return $nested;
    }

    /**
     * Determine if the relationship is nested.
     *
     * @param string $relation
     * @param string $name
     *
     * @return bool
     */
    protected function isNestedUnder($relation, $name) {
        return cstr::contains($name, '.') && cstr::startsWith($name, $relation . '.');
    }

    /**
     * Get a lazy collection for the given query.
     *
     * @return CCollection_LazyCollection
     */
    public function cursor() {
        return $this->applyScopes()->query->cursor()->map(function ($record) {
            return $this->newModelInstance()->newFromBuilder($record);
        });
    }

    /**
     * Chunk the results of a query by comparing numeric IDs.
     *
     * @param int         $count
     * @param callable    $callback
     * @param string      $column
     * @param null|string $alias
     *
     * @return bool
     */
    public function chunkById($count, callable $callback, $column = null, $alias = null) {
        $column = is_null($column) ? $this->getModel()->getKeyName() : $column;

        $alias = is_null($alias) ? $column : $alias;

        $lastId = 0;

        do {
            $clone = clone $this;

            // We'll execute the query for the given page and get the results. If there are
            // no results we can just break and return from here. When there are results
            // we will call the callback with the current chunk of these results here.
            $results = $clone->forPageAfterId($count, $lastId, $column)->get();

            $countResults = $results->count();

            if ($countResults == 0) {
                break;
            }

            // On each chunk result set, we will pass them to the callback and then let the
            // developer take care of everything within the callback, which allows us to
            // keep the memory low for spinning through large result sets for working.
            if ($callback($results) === false) {
                return false;
            }

            $lastId = $results->last()->{$alias};

            unset($results);
        } while ($countResults == $count);

        return true;
    }

    /**
     * Add a generic "order by" clause if the query doesn't already have one.
     *
     * @return void
     */
    protected function enforceOrderBy() {
        if (empty($this->query->orders) && empty($this->query->unionOrders)) {
            $this->orderBy($this->model->getQualifiedKeyName(), 'asc');
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
        $results = $this->toBase()->pluck($column, $key);

        $column = $column instanceof CDatabase_Contract_Query_ExpressionInterface ? $column->getValue($this->getGrammar()) : $column;

        // If the model has a mutator for the requested column, we will spin through
        // the results and mutate the values so that the mutated version of these
        // columns are returned as you would expect from these Eloquent models.
        if (!$this->model->hasGetMutator($column)
            && !$this->model->hasCast($column)
            && !in_array($column, $this->model->getDates())
        ) {
            return $results;
        }

        return $results->map(function ($value) use ($column) {
            return $this->model->newFromBuilder([$column => $value])->{$column};
        });
    }

    /**
     * Paginate the given query.
     *
     * @param int      $perPage
     * @param array    $columns
     * @param string   $pageName
     * @param null|int $page
     *
     * @throws \InvalidArgumentException
     *
     * @return CPagination_LengthAwarePaginator
     */
    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null) {
        $page = $page ?: CPagination_Paginator::resolveCurrentPage($pageName);

        $perPage = $perPage ?: $this->model->getPerPage();

        $results = ($total = $this->toBase()->getCountForPagination()) ? $this->forPage($page, $perPage)->get($columns) : $this->model->newCollection();

        return $this->paginator($results, $total, $perPage, $page, [
            'path' => CPagination_Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ]);
    }

    /**
     * Paginate the given query into a simple paginator.
     *
     * @param int      $perPage
     * @param array    $columns
     * @param string   $pageName
     * @param null|int $page
     *
     * @return CPagination_PaginatorInterface
     */
    public function simplePaginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null) {
        $page = $page ?: CPagination_Paginator::resolveCurrentPage($pageName);

        $perPage = $perPage ?: $this->model->getPerPage();

        // Next we will set the limit and offset for this query so that when we get the
        // results we get the proper section of results. Then, we'll create the full
        // paginator instances for these results with the given page and per page.
        $this->skip(($page - 1) * $perPage)->take($perPage + 1);

        return $this->simplePaginator($this->get($columns), $perPage, $page, [
            'path' => CPagination_Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ]);
    }

    /**
     * Paginate the given query into a cursor paginator.
     *
     * @param null|int                        $perPage
     * @param array|string                    $columns
     * @param string                          $cursorName
     * @param null|\CPagination_Cursor|string $cursor
     *
     * @return \CPagination_CursorPaginatorInterface
     */
    public function cursorPaginate($perPage = null, $columns = ['*'], $cursorName = 'cursor', $cursor = null) {
        $perPage = $perPage ?: $this->model->getPerPage();

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
        if (empty($this->query->orders) && empty($this->query->unionOrders)) {
            $this->enforceOrderBy();
        }

        if ($shouldReverse) {
            $this->query->orders = c::collect($this->query->orders)->map(function ($order) {
                $order['direction'] = $order['direction'] === 'asc' ? 'desc' : 'asc';

                return $order;
            })->toArray();
        }

        if ($this->query->unionOrders) {
            return c::collect($this->query->unionOrders);
        }

        return c::collect($this->query->orders);
    }

    /**
     * Save a new model and return the instance.
     *
     * @param array $attributes
     *
     * @return CModel|$this
     */
    public function create(array $attributes = []) {
        return c::tap($this->newModelInstance($attributes), function ($instance) {
            if ($instance->status == null) {
                $instance->status = 1;
            }

            $instance->save();
        });
    }

    /**
     * Save a new model and return the instance. Allow mass-assignment.
     *
     * @param array $attributes
     *
     * @return \CModel|$this
     */
    public function forceCreate(array $attributes) {
        return $this->model->unguarded(function () use ($attributes) {
            return $this->newModelInstance()->create($attributes);
        });
    }

    /**
     * Update a record in the database.
     *
     * @param array $values
     *
     * @return int
     */
    public function update(array $values) {
        return $this->toBase()->update($this->addUpdatedAtColumn($values));
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
        return $this->toBase()->increment(
            $column,
            $amount,
            $this->addUpdatedAtColumn($extra)
        );
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
        return $this->toBase()->decrement(
            $column,
            $amount,
            $this->addUpdatedAtColumn($extra)
        );
    }

    /**
     * Add the "updated at" column to an array of values.
     *
     * @param array $values
     *
     * @return array
     */
    protected function addUpdatedAtColumn(array $values) {
        if (! $this->model->usesTimestamps() || is_null($this->model->getUpdatedAtColumn())) {
            return $values;
        }

        $column = $this->model->getUpdatedAtColumn();

        if (! array_key_exists($column, $values)) {
            $timestamp = $this->model->freshTimestampString();

            if ($this->model->hasSetMutator($column)
                || $this->model->hasAttributeSetMutator($column)
                || $this->model->hasCast($column)
            ) {
                $timestamp = $this->model->newInstance()
                    ->forceFill([$column => $timestamp])
                    ->getAttributes()[$column] ?? $timestamp;
            }

            $values = array_merge([$column => $timestamp], $values);
        }

        $segments = preg_split('/\s+as\s+/i', $this->query->from);

        $qualifiedColumn = c::arrayLast($segments).'.'.$column;

        $values[$qualifiedColumn] = carr::get($values, $qualifiedColumn, $values[$column]);

        unset($values[$column]);

        return $values;
    }

    /**
     * Delete a record from the database.
     *
     * @return int
     */
    public function delete() {
        if (isset($this->onDelete)) {
            return call_user_func($this->onDelete, $this);
        }

        return $this->toBase()->delete();
    }

    /**
     * Run the default delete function on the builder.
     *
     * Since we do not apply scopes here, the row will actually be deleted.
     *
     * @return mixed
     */
    public function forceDelete() {
        return $this->query->delete();
    }

    /**
     * Register a replacement for the default delete function.
     *
     * @param \Closure $callback
     *
     * @return void
     */
    public function onDelete(Closure $callback) {
        $this->onDelete = $callback;
    }

    /**
     * Determine if the given model has a scope.
     *
     * @param string $scope
     *
     * @return bool
     */
    public function hasNamedScope($scope) {
        return $this->model && $this->model->hasNamedScope($scope);
    }

    /**
     * Call the given local model scopes.
     *
     * @param array|string $scopes
     *
     * @return mixed
     */
    public function scopes($scopes) {
        $builder = $this;

        foreach (carr::wrap($scopes) as $scope => $parameters) {
            // If the scope key is an integer, then the scope was passed as the value and
            // the parameter list is empty, so we will format the scope name and these
            // parameters here. Then, we'll be ready to call the scope on the model.
            if (is_int($scope)) {
                list($scope, $parameters) = [$parameters, []];
            }

            // Next we'll pass the scope callback to the callScope method which will take
            // care of grouping the "wheres" properly so the logical order doesn't get
            // messed up when adding scopes. Then we'll return back out the builder.
            $builder = $builder->callNamedScope(
                $scope,
                carr::wrap($parameters)
            );
            // $builder = $builder->callScope(
            //     [$this->model, 'scope' . ucfirst($scope)],
            //     (array) $parameters
            // );
        }

        return $builder;
    }

    /**
     * Apply the scopes to the Eloquent builder instance and return it.
     *
     * @return static
     */
    public function applyScopes() {
        if (!$this->scopes) {
            return $this;
        }

        $builder = clone $this;

        foreach ($this->scopes as $identifier => $scope) {
            if (!isset($builder->scopes[$identifier])) {
                continue;
            }

            $builder->callScope(function (CModel_Query $builder) use ($scope) {
                // If the scope is a Closure we will just go ahead and call the scope with the
                // builder instance. The "callScope" method will properly group the clauses
                // that are added to this query so "where" clauses maintain proper logic.
                if ($scope instanceof Closure) {
                    $scope($builder);
                }

                // If the scope is a scope object, we will call the apply method on this scope
                // passing in the builder and the model instance. After we run all of these
                // scopes we will return back the builder instance to the outside caller.
                if ($scope instanceof CModel_Interface_Scope) {
                    $scope->apply($builder, $this->getModel());
                }
            });
        }

        return $builder;
    }

    /**
     * Apply the given scope on the current builder instance.
     *
     * @param callable $scope
     * @param array    $parameters
     *
     * @return mixed
     */
    protected function callScope(callable $scope, $parameters = []) {
        array_unshift($parameters, $this);

        $query = $this->getQuery();

        // We will keep track of how many wheres are on the query before running the
        // scope so that we can properly group the added scope constraints in the
        // query as their own isolated nested where statement and avoid issues.
        $originalWhereCount = is_null($query->wheres) ? 0 : count($query->wheres);

        //$result = $scope(...array_values($parameters)) ?? $this;
        //$result = isset($scope(array_values($parameters))) ? $scope(array_values($parameters)) : $this;
        $result = call_user_func_array($scope, array_values($parameters)) ?? $this;

        $result = isset($result) && !is_null($result) ? $result : $this;

        if (count((array) $query->wheres) > $originalWhereCount) {
            $this->addNewWheresWithinGroup($query, $originalWhereCount);
        }

        return $result;
    }

    /**
     * Apply the given named scope on the current builder instance.
     *
     * @param string $scope
     * @param array  $parameters
     *
     * @return mixed
     */
    protected function callNamedScope($scope, array $parameters = []) {
        return $this->callScope(function (...$parameters) use ($scope) {
            return $this->model->callNamedScope($scope, $parameters);
        }, $parameters);
    }

    /**
     * Nest where conditions by slicing them at the given where count.
     *
     * @param \CDatabase_Query_Builder $query
     * @param int                      $originalWhereCount
     *
     * @return void
     */
    protected function addNewWheresWithinGroup(CDatabase_Query_Builder $query, $originalWhereCount) {
        // Here, we totally remove all of the where clauses since we are going to
        // rebuild them as nested queries by slicing the groups of wheres into
        // their own sections. This is to prevent any confusing logic order.
        $allWheres = $query->wheres;

        $query->wheres = [];

        $this->groupWhereSliceForScope(
            $query,
            array_slice($allWheres, 0, $originalWhereCount)
        );

        $this->groupWhereSliceForScope(
            $query,
            array_slice($allWheres, $originalWhereCount)
        );
    }

    /**
     * Slice where conditions at the given offset and add them to the query as a nested condition.
     *
     * @param CDatabase_Query_Builder $query
     * @param array                   $whereSlice
     *
     * @return void
     */
    protected function groupWhereSliceForScope(CDatabase_Query_Builder $query, $whereSlice) {
        $whereBooleans = c::collect($whereSlice)->pluck('boolean');

        // Here we'll check if the given subset of where clauses contains any "or"
        // booleans and in this case create a nested where expression. That way
        // we don't add any unnecessary nesting thus keeping the query clean.
        if ($whereBooleans->contains(fn ($logicalOperator) => cstr::contains($logicalOperator, 'or'))) {
            $query->wheres[] = $this->createNestedWhere(
                $whereSlice,
                str_replace(' not', '', $whereBooleans->first())
            );
        } else {
            $query->wheres = array_merge($query->wheres, $whereSlice);
        }
    }

    /**
     * Create a where array with nested where conditions.
     *
     * @param array  $whereSlice
     * @param string $boolean
     *
     * @return array
     */
    protected function createNestedWhere($whereSlice, $boolean = 'and') {
        $whereGroup = $this->getQuery()->forNestedWhere();

        $whereGroup->wheres = $whereSlice;

        return ['type' => 'Nested', 'query' => $whereGroup, 'boolean' => $boolean];
    }

    /**
     * Set the relationships that should be eager loaded.
     *
     * @param mixed      $relations
     * @param null|mixed $callback
     *
     * @return $this
     */
    public function with($relations, $callback = null) {
        if ($callback instanceof Closure) {
            $eagerLoad = $this->parseWithRelations([$relations => $callback]);
        } else {
            $eagerLoad = $this->parseWithRelations(is_string($relations) ? func_get_args() : $relations);
        }

        $this->eagerLoad = array_merge($this->eagerLoad, $eagerLoad);

        return $this;
    }

    /**
     * Prevent the specified relations from being eager loaded.
     *
     * @param mixed $relations
     *
     * @return $this
     */
    public function without($relations) {
        $this->eagerLoad = array_diff_key($this->eagerLoad, array_flip(
            is_string($relations) ? func_get_args() : $relations
        ));

        return $this;
    }

    /**
     * Set the relationships that should be eager loaded while removing any previously added eager loading specifications.
     *
     * @param  array<array-key, array|(\Closure(\CModel_Relation<*,*,*>): mixed)|string>|string  $relations
     *
     * @return $this
     */
    public function withOnly($relations) {
        $this->eagerLoad = [];

        return $this->with($relations);
    }

    /**
     * Create a new instance of the model being queried.
     *
     * @param array $attributes
     *
     * @return CModel
     */
    public function newModelInstance($attributes = []) {
        $attributes = array_merge($this->pendingAttributes, $attributes);
        // return $this->model->newInstance($attributes);

        return $this->model->newInstance($attributes)->setConnection(
            $this->query->getConnection()->getName()
        );
    }

    /**
     * Parse a list of relations into individuals.
     *
     * @param array $relations
     *
     * @return array
     */
    protected function parseWithRelations(array $relations) {
        if ($relations === []) {
            return [];
        }

        $results = [];

        foreach ($this->prepareNestedWithRelationships($relations) as $name => $constraints) {
            // We need to separate out any nested includes, which allows the developers
            // to load deep relationships using "dots" without stating each level of
            // the relationship with its own key in the array of eager-load names.
            $results = $this->addNestedWiths($name, $results);

            $results[$name] = $constraints;
        }

        return $results;
    }

    /**
     * Prepare nested with relationships.
     *
     * @param array  $relations
     * @param string $prefix
     *
     * @return array
     */
    protected function prepareNestedWithRelationships($relations, $prefix = '') {
        $preparedRelationships = [];

        if ($prefix !== '') {
            $prefix .= '.';
        }

        // If any of the relationships are formatted with the [$attribute => array()]
        // syntax, we shall loop over the nested relations and prepend each key of
        // this array while flattening into the traditional dot notation format.
        foreach ($relations as $key => $value) {
            if (!is_string($key) || !is_array($value)) {
                continue;
            }

            list($attribute, $attributeSelectConstraint) = $this->parseNameAndAttributeSelectionConstraint($key);

            $preparedRelationships = array_merge(
                $preparedRelationships,
                ["{$prefix}{$attribute}" => $attributeSelectConstraint],
                $this->prepareNestedWithRelationships($value, "{$prefix}{$attribute}"),
            );

            unset($relations[$key]);
        }

        // We now know that the remaining relationships are in a dot notation format
        // and may be a string or Closure. We'll loop over them and ensure all of
        // the present Closures are merged + strings are made into constraints.
        foreach ($relations as $key => $value) {
            if (is_numeric($key) && is_string($value)) {
                list($key, $value) = $this->parseNameAndAttributeSelectionConstraint($value);
            }

            $preparedRelationships[$prefix . $key] = $this->combineConstraints([
                $value,
                $preparedRelationships[$prefix . $key] ?? static function () {
                },
            ]);
        }

        return $preparedRelationships;
    }

    /**
     * Combine an array of constraints into a single constraint.
     *
     * @param array $constraints
     *
     * @return \Closure
     */
    protected function combineConstraints(array $constraints) {
        return function ($builder) use ($constraints) {
            foreach ($constraints as $constraint) {
                $builder = $constraint($builder) ?? $builder;
            }

            return $builder;
        };
    }

    /**
     * Parse the attribute select constraints from the name.
     *
     * @param string $name
     *
     * @return array
     */
    protected function parseNameAndAttributeSelectionConstraint($name) {
        return cstr::contains($name, ':')
            ? $this->createSelectWithConstraint($name)
            : [$name, static function () {
            }];
    }

    /**
     * Create a constraint to select the given columns for the relation.
     *
     * @param string $name
     *
     * @return array
     */
    protected function createSelectWithConstraint($name) {
        return [explode(':', $name)[0], function ($query) use ($name) {
            $query->select(explode(',', explode(':', $name)[1]));
        }];
    }

    /**
     * Parse the nested relationships in a relation.
     *
     * @param string $name
     * @param array  $results
     *
     * @return array
     */
    protected function addNestedWiths($name, $results) {
        $progress = [];

        // If the relation has already been set on the result array, we will not set it
        // again, since that would override any constraints that were already placed
        // on the relationships. We will only set the ones that are not specified.
        foreach (explode('.', $name) as $segment) {
            $progress[] = $segment;

            if (!isset($results[$last = implode('.', $progress)])) {
                $results[$last] = function () {
                };
            }
        }

        return $results;
    }

    /**
     * Specify attributes that should be added to any new models created by this builder.
     *
     * The given key / value pairs will also be added as where conditions to the query.
     *
     * @param \CDatabase_Query_Expression|array|string $attributes
     * @param mixed                                    $value
     * @param bool                                     $asConditions
     *
     * @return $this
     */
    public function withAttributes($attributes, $value = null, $asConditions = true) {
        if (!is_array($attributes)) {
            $attributes = [$attributes => $value];
        }

        if ($asConditions) {
            foreach ($attributes as $column => $columnValue) {
                $this->where($this->qualifyColumn($column), $columnValue);
            }
        }

        $this->pendingAttributes = array_merge($this->pendingAttributes, $attributes);

        return $this;
    }

    /**
     * Apply query-time casts to the model instance.
     *
     * @param array $casts
     *
     * @return $this
     */
    public function withCasts($casts) {
        $this->model->mergeCasts($casts);

        return $this;
    }

    /**
     * Execute the given Closure within a transaction savepoint if needed.
     *
     * @template TModelValue
     *
     * @param  \Closure(): TModelValue  $scope
     *
     * @return TModelValue
     */
    public function withSavepointIfNeeded($scope) {
        return $this->getQuery()->getConnection()->transactionLevel() > 0
            ? $this->getQuery()->getConnection()->transaction($scope)
            : $scope();
    }

    /**
     * Get the Eloquent builder instances that are used in the union of the query.
     *
     * @return \CCollection
     */
    protected function getUnionBuilders() {
        return isset($this->query->unions)
            ? (new CCollection($this->query->unions))->pluck('query')
            : new CCollection();
    }

    /**
     * Get the underlying query builder instance.
     *
     * @return CDatabase_Query_Builder
     */
    public function getQuery() {
        return $this->query;
    }

    /**
     * Set the underlying query builder instance.
     *
     * @param CDatabase_Query_Builder $query
     *
     * @return $this
     */
    public function setQuery(CDatabase_Query_Builder $query) {
        $this->query = $query;

        return $this;
    }

    /**
     * Get a base query builder instance.
     *
     * @return CDatabase_Query_Builder
     */
    public function toBase() {
        return $this->applyScopes()->getQuery();
    }

    /**
     * Get the relationships being eagerly loaded.
     *
     * @return array
     */
    public function getEagerLoads() {
        return $this->eagerLoad;
    }

    /**
     * Set the relationships being eagerly loaded.
     *
     * @param array $eagerLoad
     *
     * @return $this
     */
    public function setEagerLoads(array $eagerLoad) {
        $this->eagerLoad = $eagerLoad;

        return $this;
    }

    /**
     * Indicate that the given relationships should not be eagerly loaded.
     *
     * @param array $relations
     *
     * @return $this
     */
    public function withoutEagerLoad(array $relations) {
        $relations = array_diff(array_keys($this->model->getRelations()), $relations);

        return $this->with($relations);
    }

    /**
     * Flush the relationships being eagerly loaded.
     *
     * @return $this
     */
    public function withoutEagerLoads() {
        return $this->setEagerLoads([]);
    }

    /**
     * Get the "limit" value from the query or null if it's not set.
     *
     * @return mixed
     */
    public function getLimit() {
        return $this->query->getLimit();
    }

    /**
     * Get the model instance being queried.
     *
     * @return CModel
     */
    public function getModel() {
        return $this->model;
    }

    /**
     * Set a model instance for the model being queried.
     *
     * @param CModel $model
     *
     * @return $this
     */
    public function setModel(CModel $model) {
        $this->model = $model;

        $this->query->from($model->getTable());

        return $this;
    }

    /**
     * Qualify the given column name by the model's table.
     *
     * @param string|CDatabase_Contract_Query_ExpressionInterface $column
     *
     * @return string
     */
    public function qualifyColumn($column) {
        $column = $column instanceof CDatabase_Contract_Query_ExpressionInterface ? $column->getValue($this->getGrammar()) : $column;

        return $this->model->qualifyColumn($column);
    }

    /**
     * Qualify the given columns with the model's table.
     *
     * @param array|\CDatabase_Contract_Query_ExpressionInterface $columns
     *
     * @return array
     */
    public function qualifyColumns($columns) {
        return $this->model->qualifyColumns($columns);
    }

    /**
     * Get the given macro by name.
     *
     * @param string $name
     *
     * @return \Closure
     */
    public function getMacro($name) {
        return carr::get($this->localMacros, $name);
    }

    /**
     * Checks if a macro is registered.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasMacro($name) {
        return isset($this->localMacros[$name]);
    }

    /**
     * Get the given global macro by name.
     *
     * @param string $name
     *
     * @return \Closure
     */
    public static function getGlobalMacro($name) {
        return carr::get(static::$macros, $name);
    }

    /**
     * Checks if a global macro is registered.
     *
     * @param string $name
     *
     * @return bool
     */
    public static function hasGlobalMacro($name) {
        return isset(static::$macros[$name]);
    }

    /**
     * Dynamically handle calls into the query instance.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters) {
        if ($method === 'macro') {
            $this->localMacros[$parameters[0]] = $parameters[1];

            return;
        }

        if ($this->hasMacro($method)) {
            array_unshift($parameters, $this);

            //return $this->localMacros[$method](...$parameters);
            return call_user_func_array($this->localMacros[$method], $parameters);
        }

        if (static::hasGlobalMacro($method)) {
            $callable = static::$macros[$method];
            if (static::$macros[$method] instanceof Closure) {
                return call_user_func_array($callable->bindTo($this, static::class), $parameters);
            }

            return call_user_func_array($callable, $parameters);
        }

        if (method_exists($this->model, $scope = 'scope' . ucfirst($method))) {
            return $this->callScope([$this->model, $scope], $parameters);
        }

        if (in_array($method, $this->passthru)) {
            return $this->toBase()->{$method}(...$parameters);
        }

        $this->forwardCallTo($this->query, $method, $parameters);

        return $this;
    }

    /**
     * Dynamically handle calls into the query instance.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @throws \BadMethodCallException
     *
     * @return mixed
     */
    public static function __callStatic($method, $parameters) {
        if ($method === 'macro') {
            static::$macros[$parameters[0]] = $parameters[1];

            return;
        }

        if (!static::hasGlobalMacro($method)) {
            static::throwBadMethodCallException($method);
        }

        $callable = static::$macros[$method];

        if ($callable instanceof Closure) {
            $callable = $callable->bindTo(null, static::class);
        }

        return $callable(...$parameters);
    }

    /**
     * Force a clone of the underlying query builder when cloning.
     *
     * @return void
     */
    public function __clone() {
        $this->query = clone $this->query;
    }
}
