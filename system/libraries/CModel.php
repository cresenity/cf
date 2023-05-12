<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 */

/**
 * Class CModel.
 *
 * @method static       static                                    create($attributes = [])                                                                  Find a model by its primary key.
 * @method static       static|null                               find($id, $columns = ['*'])                                                               Find a model by its primary key.
 * @method static       CModel_Collection                         findMany($ids, $columns = ['*'])                                                          Find a model by its primary key.
 * @method static       static                                    findOrFail($id, $columns = ['*'])                                                         Find a model by its primary key or throw an exception.
 * @method static       CModel|CModel_Query|static|null           first($columns = ['*'])                                                                   Execute the query and get the first result.
 * @method static       CModel|CModel_Query|static                firstOrFail($columns = ['*'])                                                             Execute the query and get the first result or throw an exception.
 * @method static       CModel|CModel_Query|static                firstOrNew(array $attributes, array $values = [])                                         Get the first record matching the attributes or instantiate it.
 * @method static       CModel_Collection|CModel_Query[]|static[] get($columns = ['*'])                                                                     Execute the query as a "select" statement.
 * @method mixed        value($column)                                                                                                                      Get a single column's value from the first result of a query.
 * @method mixed        pluck($column)                                                                                                                      Get a single column's value from the first result of a query.
 * @method void         chunk($count, callable $callback)                                                                                                   Chunk the results of the query.
 * @method \CCollection lists($column, $key = null)                                                                                                         Get an array with the values of a given column.
 * @method static       \CPagination_LengthAwarePaginator         paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)             Paginate the given query.
 * @method static       \CPagination_PaginatorInterface           simplePaginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)       Paginate the given query into a simple paginator.
 * @method static       \CPagination_PaginatorCursorInterface     cursorPaginate($perPage = null, $columns = ['*'], $cursorName = 'cursor', $cursor = null) Paginate the given query into a cursor paginator.
 * @method void         onDelete(Closure $callback)                                                                                                         Register a replacement for the default delete function.
 * @method CModel[]     getModels($columns = ['*'])                                                                                                         Get the hydrated models without eager loading.
 * @method array        eagerLoadRelations(array $models)                                                                                                   Eager load the relationships for the models.
 * @method static       CModel_Query|static                       where($column, $operator = null, $value = null, $boolean = 'and')                         Add a basic where clause to the query.
 * @method static       CModel_Query|static                       whereHas($relation, Closure $callback = null, $operator = '>=', $count = 1)               Add a relationship count / exists condition to the query with where clauses.
 * @method static       CModel_Query|static                       orWhere($column, $operator = null, $value = null)                                         Add an "or where" clause to the query.
 * @method static       CModel_Query|static                       has($relation, $operator = '>=', $count = 1, $boolean = 'and', Closure $callback = null)  Add a relationship count condition to the query.
 * @method static       CModel_Query|static                       whereRaw($sql, array $bindings = [])
 * @method static       CModel_Query|static                       whereBetween($column, array $values)
 * @method static       CModel_Query|static                       whereNotBetween($column, array $values)
 * @method static       CModel_Query|static                       whereNested(Closure $callback)
 * @method static       CModel_Query|static                       addNestedWhereQuery($query)
 * @method static       CModel_Query|static                       whereExists(Closure $callback)
 * @method static       CModel_Query|static                       whereNotExists(Closure $callback)
 * @method static       CModel_Query|static                       whereIn($column, $values)
 * @method static       CModel_Query|static                       whereNotIn($column, $values)
 * @method static       CModel_Query|static                       whereNull($column)
 * @method static       CModel_Query|static                       whereNotNull($column)
 * @method static       CModel_Query|static                       whereDoesntHave($table, Closure $callback)
 * @method static       CModel_Query|static                       orWhereRaw($sql, array $bindings = [])
 * @method static       CModel_Query|static                       orWhereBetween($column, array $values)
 * @method static       CModel_Query|static                       orWhereNotBetween($column, array $values)
 * @method static       CModel_Query|static                       orWhereExists(Closure $callback)
 * @method static       CModel_Query|static                       orWhereNotExists(Closure $callback)
 * @method static       CModel_Query|static                       orWhereIn($column, $values)
 * @method static       CModel_Query|static                       orWhereNotIn($column, $values)
 * @method static       CModel_Query|static                       orWhereNull($column)
 * @method static       CModel_Query|static                       orWhereNotNull($column)
 * @method static       CModel_Query|static                       whereDate($column, $operator, $value)
 * @method static       CModel_Query|static                       whereDay($column, $operator, $value)
 * @method static       CModel_Query|static                       whereMonth($column, $operator, $value)
 * @method static       CModel_Query|static                       whereYear($column, $operator, $value)
 * @method static       CModel_Query|static                       join($table, $first, $operator = null, $second = null, $type = 'inner', $where = false)
 * @method static       CModel_Query|static                       select($columns = ['*'])
 * @method static       CModel_Query|static                       groupBy(...$groups)
 * @method static       CModel_Query|static                       from($table)
 * @method static       CModel_Query|static                       newQuery()
 * @method static       CModel_Query|static                       withTrashed()
 * @method static       CModel_Query|static                       leftJoinSub($query, $as, $first, $operator = null, $second = null)
 * @method static       CModel_Query|static                       addSelect($column)
 * @method static       CModel_Query|static                       selectRaw($expression, array $bindings = [])
 * @method static       CModel_Query|static                       orderBy($column, $direction = 'asc')
 * @method static       CModel_Query|static                       orderByDesc($column)
 * @method static       CModel_Query|static                       skip($value)
 * @method static       CModel_Query|static                       offset($value)
 * @method static       CModel_Query|static                       take($value)
 * @method static       CModel_Query|static                       limit($value)
 * @method static       CModel_Query|static                       lockForUpdate()                                                                           Lock the selected rows in the table for updating.
 * @method static       mixed                                     sum($column)                                                                              Retrieve the sum of the values of a given column..
 *
 * @see CModel_Query
 */
abstract class CModel implements ArrayAccess, CInterface_Arrayable, CInterface_Jsonable, CQueue_QueueableEntityInterface {
    use CModel_Trait_GuardsAttributes,
        CModel_Trait_Attributes,
        CModel_Trait_Relationships,
        CModel_Trait_Event,
        CModel_Trait_GlobalScopes,
        CModel_Trait_HidesAttributes,
        CModel_Trait_Timestamps,
        CTrait_ForwardsCalls;

    /**
     * The name of the "created" column.
     *
     * @var string
     */
    const CREATED = 'created';

    /**
     * The name of the "updated" column.
     *
     * @var string
     */
    const UPDATED = 'updated';

    /**
     * The name of the "createdby" column.
     *
     * @var string
     */
    const CREATEDBY = 'createdby';

    /**
     * The name of the "updatedby" column.
     *
     * @var string
     */
    const UPDATEDBY = 'updatedby';

    /**
     * The name of the "deleted" column.
     *
     * @var string
     */
    const DELETED = 'deleted';

    /**
     * The name of the "deletedby" column.
     *
     * @var string
     */
    const DELETEDBY = 'deletedby';

    /**
     * The name of the "status" column.
     *
     * @var string
     */
    const STATUS = 'status';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * Indicates if the model exists.
     *
     * @var bool
     */
    public $exists = false;

    /**
     * Indicates if the model was inserted during the current request lifecycle.
     *
     * @var bool
     */
    public $wasRecentlyCreated = false;

    /**
     * The connection name for the model.
     *
     * @var string
     *
     * @deprecated
     */
    protected $db;

    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'bigint';

    /**
     * The relations to eager load on every query.
     *
     * @var array
     */
    protected $with = [];

    /**
     * The relationship counts that should be eager loaded on every query.
     *
     * @var array
     */
    protected $withCount = [];

    /**
     * The number of models to return for pagination.
     *
     * @var int
     */
    protected $perPage = 15;

    /**
     * The connection resolver instance.
     *
     * @var CDatabase_ResolverInterface
     */
    protected static $resolver;

    /**
     * The array of booted models.
     *
     * @var array
     */
    protected static $booted = [];

    /**
     * The array of trait initializers that will be called on each new instance.
     *
     * @var array
     */
    protected static $traitInitializers = [];

    /**
     * The array of global scopes on the model.
     *
     * @var array
     */
    protected static $globalScopes = [];

    /**
     * The list of models classes that should not be affected with touch.
     *
     * @var array
     */
    protected static $ignoreOnTouch = [];

    /**
     * Indicates whether lazy loading should be restricted on all models.
     *
     * @var bool
     */
    protected static $modelsShouldPreventLazyLoading = false;

    /**
     * The callback that is responsible for handling lazy loading violations.
     *
     * @var null|callable
     */
    protected static $lazyLoadingViolationCallback;

    /**
     * The array of mapping model class.
     *
     * @var array
     */
    protected static $mappings;

    /**
     * Add Mapping.
     *
     * @param string $tableName
     * @param string $modelClass
     */
    public static function addMapping($tableName, $modelClass = '') {
        if (is_array($tableName)) {
            self::$mappings = carr::merge(self::$mappings, $tableName);
        } else {
            self::$mappings[$tableName] = $modelClass;
        }
    }

    public static function setMapping($mappings) {
        self::$mappings = $mappings;
    }

    public static function factory($tableName) {
        if (isset(self::$mappings[$tableName])) {
            return new self::$mappings[$tableName]();
        } else {
            return CDatabase::instance()->table($tableName);
        }
    }

    /**
     * Create a new model instance.
     *
     * @param array $attributes
     *
     * @throws CModel_Exception_MassAssignmentException
     *
     * @return void
     */
    public function __construct(array $attributes = []) {
        $this->primaryKey = $this->table ? $this->table . '_id' : 'id';
        $this->bootIfNotBooted();

        $this->initializeTraits();
        $this->syncOriginal();

        $this->fill($attributes);
    }

    /**
     * Check if the model needs to be booted and if so, do it.
     *
     * @return void
     */
    protected function bootIfNotBooted() {
        if (!isset(static::$booted[static::class])) {
            static::$booted[static::class] = true;

            $this->fireModelEvent('booting', false);
            static::booting();
            static::boot();
            static::booted();
            $this->fireModelEvent('booted', false);
        }
    }

    /**
     * Perform any actions required before the model boots.
     *
     * @return void
     */
    protected static function booting() {
    }

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function boot() {
        static::bootTraits();
    }

    /**
     * Boot all of the bootable traits on the model.
     *
     * @return void
     */
    protected static function bootTraits() {
        $class = static::class;
        $booted = [];
        static::$traitInitializers[$class] = [];
        foreach (c::classUsesRecursive($class) as $trait) {
            $method = 'boot' . c::classBasename($trait);
            $classMethod = $class . $method;
            if (method_exists($class, $method) && !in_array($classMethod, $booted)) {
                forward_static_call([$class, $method]);
                $booted[] = $classMethod;
            }
            if (method_exists($class, $method = 'initialize' . c::classBasename($trait))) {
                static::$traitInitializers[$class][] = $method;

                static::$traitInitializers[$class] = array_unique(
                    static::$traitInitializers[$class]
                );
            }
        }
    }

    /**
     * Initialize any initializable traits on the model.
     *
     * @return void
     */
    protected function initializeTraits() {
        if (isset(static::$traitInitializers[static::class])) {
            foreach (static::$traitInitializers[static::class] as $method) {
                $this->{$method}();
            }
        }
    }

    /**
     * Perform any actions required after the model boots.
     *
     * @return void
     */
    protected static function booted() {
    }

    /**
     * Clear the list of booted models so they will be re-booted.
     *
     * @return void
     */
    public static function clearBootedModels() {
        static::$booted = [];

        static::$globalScopes = [];
    }

    /**
     * Disables relationship model touching for the current class during given callback scope.
     *
     * @param callable $callback
     *
     * @return void
     */
    public static function withoutTouching($callback) {
        static::withoutTouchingOn([static::class], $callback);
    }

    /**
     * Disables relationship model touching for the given model classes during given callback scope.
     *
     * @param array    $models
     * @param callable $callback
     *
     * @return void
     */
    public static function withoutTouchingOn(array $models, $callback) {
        static::$ignoreOnTouch = array_values(array_merge(static::$ignoreOnTouch, $models));

        try {
            $callback();
        } finally {
            static::$ignoreOnTouch = array_values(array_diff(static::$ignoreOnTouch, $models));
        }
    }

    /**
     * Determine if the given model is ignoring touches.
     *
     * @param null|string $class
     *
     * @return bool
     */
    public static function isIgnoringTouch($class = null) {
        $class = $class ?: static::class;

        if (!get_class_vars($class)['timestamps'] || !$class::UPDATED_AT) {
            return true;
        }

        foreach (static::$ignoreOnTouch as $ignoredClass) {
            if ($class === $ignoredClass || is_subclass_of($class, $ignoredClass)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove the table name from a given key.
     *
     * @param string $key
     *
     * @return string
     */
    protected function removeTableFromKey($key) {
        return cstr::contains($key, '.') ? carr::last(explode('.', $key)) : $key;
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param array $attributes
     *
     * @throws CModel_Exception_MassAssignmentException
     *
     * @return $this
     */
    public function fill(array $attributes) {
        $totallyGuarded = $this->totallyGuarded();
        foreach ($this->fillableFromArray($attributes) as $key => $value) {
            $key = $this->removeTableFromKey($key);

            // The developers may choose to place some attributes in the "fillable" array
            // which means only those attributes may be set through mass assignment to
            // the model, and all others will just get ignored for security reasons.
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            } elseif ($totallyGuarded) {
                throw new CModel_Exception_MassAssignmentException(sprintf(
                    'Add [%s] to fillable property to allow mass assignment on [%s].',
                    $key,
                    get_class($this)
                ));
            }
        }

        return $this;
    }

    /**
     * Fill the model with an array of attributes. Force mass assignment.
     *
     * @param array $attributes
     *
     * @throws CModel_Exception_MassAssignmentException
     *
     * @return $this
     */
    public function forceFill(array $attributes) {
        return static::unguarded(function () use ($attributes) {
            return $this->fill($attributes);
        });
    }

    /**
     * Qualify the given column name by the model's table.
     *
     * @param string $column
     *
     * @return string
     */
    public function qualifyColumn($column) {
        if (cstr::contains($column, '.')) {
            return $column;
        }

        return $this->getTable() . '.' . $column;
    }

    /**
     * Create a new instance of the given model.
     *
     * @param array $attributes
     * @param bool  $exists
     *
     * @throws CModel_Exception_MassAssignmentException
     *
     * @return static
     */
    public function newInstance($attributes = [], $exists = false) {
        // This method just provides a convenient way for us to generate fresh model
        // instances of this current model. It is particularly useful during the
        // hydration of new objects via the Eloquent query builder instances.
        $model = new static((array) $attributes);

        $model->exists = $exists;

        $model->setConnection($this->getConnectionName());

        $model->setTable($this->getTable());

        $model->mergeCasts($this->casts);

        return $model;
    }

    /**
     * Create a new model instance that is existing.
     *
     * @param array       $attributes
     * @param null|string $connection
     *
     * @throws CModel_Exception_MassAssignmentException
     *
     * @return static
     */
    public function newFromBuilder($attributes = [], $connection = null) {
        $model = $this->newInstance([], true);

        $model->setRawAttributes((array) $attributes, true);
        $model->setConnection($connection ?: $this->getConnectionName());
        $model->fireModelEvent('retrieved', false);

        return $model;
    }

    /**
     * Begin querying the model on a given connection.
     *
     * @param null|string $connection
     *
     * @return CModel_Query
     */
    public static function on($connection = null) {
        // First we will just create a fresh instance of this model, and then we can
        // set the connection on the model so that it is be used for the queries
        // we execute, as well as being set on each relationship we retrieve.
        $instance = new static();

        $instance->setConnection($connection);

        return $instance->newQuery();
    }

    /**
     * Begin querying the model on the write connection.
     *
     * @return CDatabase_Query_Builder
     */
    public static function onWriteConnection() {
        $instance = new static();

        return $instance->newQuery()->useWritePdo();
    }

    /**
     * Get all of the models from the database.
     *
     * @param array|mixed $columns
     *
     * @return CModel_COllection|static[]
     */
    public static function all($columns = ['*']) {
        return static::query()->get(
            is_array($columns) ? $columns : func_get_args()
        );
    }

    /**
     * Begin querying a model with eager loading.
     *
     * @param array|string $relations
     *
     * @return CModel_Query|static
     */
    public static function with($relations) {
        return static::query()->with(
            is_string($relations) ? func_get_args() : $relations
        );
    }

    /**
     * Eager load relations on the model.
     *
     * @param array|string $relations
     *
     * @return $this
     */
    public function load($relations) {
        $query = $this->newQueryWithoutRelationships()->with(
            is_string($relations) ? func_get_args() : $relations
        );

        $query->eagerLoadRelations([$this]);

        return $this;
    }

    /**
     * Eager load relations on the model if they are not already eager loaded.
     *
     * @param array|string $relations
     *
     * @return $this
     */
    public function loadMissing($relations) {
        $relations = is_string($relations) ? func_get_args() : $relations;

        return $this->load(array_filter($relations, function ($relation) {
            return !$this->relationLoaded($relation);
        }));
    }

    /**
     * Increment a column's value by a given amount.
     *
     * @param string $column
     * @param int    $amount
     * @param array  $extra
     *
     * @throws CModel_Exception_MassAssignmentException
     *
     * @return int
     */
    public function increment($column, $amount = 1, array $extra = []) {
        return $this->incrementOrDecrement($column, $amount, $extra, 'increment');
    }

    /**
     * Decrement a column's value by a given amount.
     *
     * @param string $column
     * @param int    $amount
     * @param array  $extra
     *
     * @throws CModel_Exception_MassAssignmentException
     *
     * @return int
     */
    public function decrement($column, $amount = 1, array $extra = []) {
        return $this->incrementOrDecrement($column, $amount, $extra, 'decrement');
    }

    /**
     * Run the increment or decrement method on the model.
     *
     * @param string $column
     * @param int    $amount
     * @param array  $extra
     * @param string $method
     *
     * @throws CModel_Exception_MassAssignmentException
     *
     * @return int
     */
    protected function incrementOrDecrement($column, $amount, $extra, $method) {
        $query = $this->newQuery();

        if (!$this->exists) {
            return $query->{$method}($column, $amount, $extra);
        }

        $this->incrementOrDecrementAttributeValue($column, $amount, $extra, $method);

        return $query->where(
            $this->getKeyName(),
            $this->getKey()
        )->{$method}($column, $amount, $extra);
    }

    /**
     * Increment the underlying attribute value and sync with original.
     *
     * @param string $column
     * @param int    $amount
     * @param array  $extra
     * @param string $method
     *
     * @throws CModel_Exception_MassAssignmentException
     *
     * @return void
     */
    protected function incrementOrDecrementAttributeValue($column, $amount, $extra, $method) {
        $this->{$column} = $this->{$column} + ($method == 'increment' ? $amount : $amount * -1);

        $this->forceFill($extra);

        $this->syncOriginalAttribute($column);
    }

    /**
     * Update the model in the database.
     *
     * @param array $attributes
     * @param array $options
     *
     * @throws CModel_Exception_MassAssignmentException
     *
     * @return bool
     */
    public function update(array $attributes = [], array $options = []) {
        if (!$this->exists) {
            return false;
        }

        return $this->fill($attributes)->save($options);
    }

    /**
     * Save the model and all of its relationships.
     *
     * @return bool
     */
    public function push() {
        if (!$this->save()) {
            return false;
        }

        // To sync all of the relationships to the database, we will simply spin through
        // the relationships and save each model via this "push" method, which allows
        // us to recurse into all of these nested relations for the model instance.
        foreach ($this->relations as $models) {
            $models = $models instanceof CCollection ? $models->all() : [$models];

            foreach (array_filter($models) as $model) {
                if (!$model->push()) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Save the model to the database without raising any events.
     *
     * @param array $options
     *
     * @return bool
     */
    public function saveQuietly(array $options = []) {
        return static::withoutEvents(function () use ($options) {
            return $this->save($options);
        });
    }

    /**
     * Save the model to the database.
     *
     * @param array $options
     *
     * @return bool
     */
    public function save(array $options = []) {
        $query = $this->newModelQuery();

        // If the "saving" event returns false we'll bail out of the save and return
        // false, indicating that the save failed. This provides a chance for any
        // listeners to cancel save operations if validations fail or whatever.
        if ($this->fireModelEvent('saving') === false) {
            return false;
        }

        // If the model already exists in the database we can just update our record
        // that is already in this database using the current IDs in this "where"
        // clause to only update this model. Otherwise, we'll just insert them.
        if ($this->exists) {
            $saved = $this->isDirty()
                ? $this->performUpdate($query) : true;
        } else {
            // If the model is brand new, we'll insert it into our database and set the
            // ID attribute on the model to the value of the newly inserted row's ID
            // which is typically an auto-increment value managed by the database.
            $saved = $this->performInsert($query);

            if (!$this->getConnectionName() && $connection = $query->getConnection()) {
                $this->setConnection($connection->getName());
            }
        }

        // If the model is successfully saved, we need to do a few more things once
        // that is done. We will call the "saved" method here to run any actions
        // we need to happen after a model gets successfully saved right here.
        if ($saved) {
            $this->finishSave($options);
        }

        return $saved;
    }

    /**
     * Save the model to the database using transaction.
     *
     * @param array $options
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function saveOrFail(array $options = []) {
        return $this->getConnection()->transaction(function () use ($options) {
            return $this->save($options);
        });
    }

    /**
     * Perform any actions that are necessary after the model is saved.
     *
     * @param array $options
     *
     * @return void
     */
    protected function finishSave(array $options) {
        $this->fireModelEvent('saved', false);

        if ($this->isDirty() && (isset($options['touch']) ? $options['touch'] : true)) {
            $this->touchOwners();
        }

        $this->syncOriginal();
    }

    /**
     * Perform a model update operation.
     *
     * @param CModel_Query $query
     *
     * @return bool
     */
    protected function performUpdate(CModel_Query $query) {
        // If the updating event returns false, we will cancel the update operation so
        // developers can hook Validation systems into their models and cancel this
        // operation if the model does not pass validation. Otherwise, we update.
        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        // First we need to create a fresh query instance and touch the creation and
        // update timestamp on the model which are maintained by us for developer
        // convenience. Then we will just continue saving the model instances.
        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        // Once we have run the update operation, we will fire the "updated" event for
        // this model instance. This will allow developers to hook into these after
        // models are updated, giving them a chance to do any special processing.
        $dirty = $this->getDirty();

        if (count($dirty) > 0) {
            $this->setKeysForSaveQuery($query)->update($dirty);

            $this->fireModelEvent('updated', false);

            $this->syncChanges();
        }

        return true;
    }

    /**
     * Set the keys for a save update query.
     *
     * @param CModel_Query $query
     *
     * @return CModel_Query
     */
    protected function setKeysForSaveQuery(CModel_Query $query) {
        $query->where($this->getKeyName(), '=', $this->getKeyForSaveQuery());

        return $query;
    }

    /**
     * Get the primary key value for a save query.
     *
     * @return mixed
     */
    protected function getKeyForSaveQuery() {
        return isset($this->original[$this->getKeyName()]) ? $this->original[$this->getKeyName()] : $this->getKey();
    }

    /**
     * Perform a model insert operation.
     *
     * @param CModel_Query $query
     *
     * @return bool
     */
    protected function performInsert(CModel_Query $query) {
        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        // First we'll need to create a fresh query instance and touch the creation and
        // update timestamps on this model, which are maintained by us for developer
        // convenience. After, we will just continue saving these model instances.
        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        // If the model has an incrementing key, we can use the "insertGetId" method on
        // the query builder, which will give us back the final inserted ID for this
        // table from the database. Not all tables have to be incrementing though.
        $attributes = $this->attributes;

        if ($this->getIncrementing()) {
            $this->insertAndSetId($query, $attributes);
        } else {
            // If the table isn't incrementing we'll simply insert these attributes as they
            // are. These attribute arrays must contain an "id" column previously placed
            // there by the developer as the manually determined key for these models.
            if (empty($attributes)) {
                return true;
            }

            $query->insert($attributes);
        }

        // We will go ahead and set the exists property to true, so that it is set when
        // the created event is fired, just in case the developer tries to update it
        // during the event. This will allow them to do so and run an update here.
        $this->exists = true;

        $this->wasRecentlyCreated = true;

        $this->fireModelEvent('created', false);

        return true;
    }

    /**
     * Insert the given attributes and set the ID on the model.
     *
     * @param CModel_Query $query
     * @param array        $attributes
     *
     * @return void
     */
    protected function insertAndSetId(CModel_Query $query, $attributes) {
        $id = $query->insertGetId($attributes, $keyName = $this->getKeyName());

        $this->setAttribute($keyName, $id);
    }

    /**
     * Destroy the models for the given IDs.
     *
     * @param array|int $ids
     *
     * @return int
     */
    public static function destroy($ids) {
        // We'll initialize a count here so we will return the total number of deletes
        // for the operation. The developers can then check this number as a boolean
        // type value or get this total count of records deleted for logging, etc.
        $count = 0;

        $ids = is_array($ids) ? $ids : func_get_args();

        // We will actually pull the models from the database table and call delete on
        // each of them individually so that their events get fired properly with a
        // correct set of attributes in case the developers wants to check these.
        $instance = new static();
        $key = $instance->getKeyName();

        foreach ($instance->whereIn($key, $ids)->get() as $model) {
            if ($model->delete()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Delete the model from the database.
     *
     * @throws \Exception
     *
     * @return null|bool
     */
    public function delete() {
        if (is_null($this->getKeyName())) {
            throw new Exception('No primary key defined on model.');
        }

        // If the model doesn't exist, there is nothing to delete so we'll just return
        // immediately and not do anything else. Otherwise, we will continue with a
        // deletion process on the model, firing the proper events, and so forth.
        if (!$this->exists) {
            return null;
        }

        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }

        // Here, we'll touch the owning models, verifying these timestamps get updated
        // for the models. This will allow any caching to get broken on the parents
        // by the timestamp. Then we will go ahead and delete the model instance.
        $this->touchOwners();

        $this->performDeleteOnModel();

        // Once the model has been deleted, we will fire off the deleted event so that
        // the developers may hook into post-delete operations. We will then return
        // a boolean true as the delete is presumably successful on the database.
        $this->fireModelEvent('deleted', false);

        return true;
    }

    /**
     * Force a hard delete on a soft deleted model.
     *
     * This method protects developers from running forceDelete when trait is missing.
     *
     * @throws Exception
     *
     * @return null|bool
     */
    public function forceDelete() {
        return $this->delete();
    }

    /**
     * Perform the actual delete query on this model instance.
     *
     * @return void
     */
    protected function performDeleteOnModel() {
        $this->setKeysForSaveQuery($this->newModelQuery())->delete();

        $this->exists = false;
    }

    /**
     * Begin querying the model.
     *
     * @return CModel_Query|static
     *
     * @phpstan-return CModel_Query<static>|static
     */
    public static function query() {
        return (new static())->newQuery();
    }

    /**
     * Get a new query builder for the model's table.
     *
     * @return CModel_Query
     */
    public function newQuery() {
        return $this->registerGlobalScopes($this->newQueryWithoutScopes());
    }

    /**
     * Get a new query builder with no relationships loaded.
     *
     * @return CModel_Query
     */
    public function newQueryWithoutRelationships() {
        return $this->registerGlobalScopes($this->newModelQuery());
    }

    /**
     * Register the global scopes for this builder instance.
     *
     * @param CModel_Query $query
     *
     * @return CModel_Query
     */
    public function registerGlobalScopes($query) {
        foreach ($this->getGlobalScopes() as $identifier => $scope) {
            $query->withGlobalScope($identifier, $scope);
        }

        return $query;
    }

    /**
     * Get a new query builder that doesn't have any global scopes.
     *
     * @return CModel_Query|static
     */
    public function newQueryWithoutScopes() {
        return $this->newModelQuery()
            ->with($this->with)
            ->withCount($this->withCount);
    }

    /**
     * Get a new query instance without a given scope.
     *
     * @param CModel_Interface_Scope|string $scope
     *
     * @return CModel_Query
     */
    public function newQueryWithoutScope($scope) {
        $builder = $this->newQuery();

        return $builder->withoutGlobalScope($scope);
    }

    /**
     * Get a new query to restore one or more models by their queueable IDs.
     *
     * @param array|int $ids
     *
     * @return CModel_Query
     */
    public function newQueryForRestoration($ids) {
        if (is_array($ids)) {
            return $this->newQueryWithoutScopes()->whereIn($this->getQualifiedKeyName(), $ids);
        } else {
            return $this->newQueryWithoutScopes()->whereKey($ids);
        }
    }

    /**
     * Get a new query builder that doesn't have any global scopes or eager loading.
     *
     * @return CModel_Query|static
     */
    public function newModelQuery() {
        return $this->newModelBuilder($this->newBaseQueryBuilder())->setModel($this);
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param CDatabase_Query_Builder $query
     *
     * @return CModel_Query|static
     */
    public function newModelBuilder($query) {
        return new CModel_Query($query);
    }

    /**
     * Get a new query builder instance for the connection.
     *
     * @return CDatabase_Query_Builder
     */
    protected function newBaseQueryBuilder() {
        return new CDatabase_Query_Builder($this->getConnection());
    }

    /**
     * Create a new Eloquent Collection instance.
     *
     * @param array $models
     *
     * @return CModel_Collection
     */
    public function newCollection(array $models = []) {
        return new CModel_Collection($models);
    }

    /**
     * Create a new pivot model instance.
     *
     * @param CModel      $parent
     * @param array       $attributes
     * @param string      $table
     * @param bool        $exists
     * @param null|string $using
     *
     * @return CModel_Relation_Pivot
     */
    public function newPivot(CModel $parent, array $attributes, $table, $exists, $using = null) {
        return $using ? $using::fromRawAttributes($parent, $attributes, $table, $exists) : CModel_Relation_Pivot::fromAttributes($parent, $attributes, $table, $exists);
    }

    /**
     * Convert the model instance to an array.
     *
     * @return array
     */
    public function toArray() {
        return array_merge($this->attributesToArray(), $this->relationsToArray());
    }

    /**
     * Convert the model instance to JSON.
     *
     * @param int $options
     *
     * @throws CModel_Exception_JsonEncodingException
     *
     * @return string
     */
    public function toJson($options = 0) {
        $json = json_encode($this->jsonSerialize(), $options);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw CModel_Exception_JsonEncodingException::forModel($this, json_last_error_msg());
        }

        return $json;
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize() {
        return $this->toArray();
    }

    /**
     * Reload a fresh model instance from the database.
     *
     * @param array|string $with
     *
     * @return null|static
     */
    public function fresh($with = []) {
        if (!$this->exists) {
            return null;
        }

        return $this->newQueryWithoutScopes()
            ->with(is_string($with) ? func_get_args() : $with)
            ->where($this->getKeyName(), $this->getKey())
            ->first();
    }

    /**
     * Reload the current model instance with fresh attributes from the database.
     *
     * @return $this
     */
    public function refresh() {
        if (!$this->exists) {
            return $this;
        }

        $this->load(array_keys($this->relations));

        $this->setRawAttributes(static::findOrFail($this->getKey())->attributes);

        return $this;
    }

    /**
     * Clone the model into a new, non-existing instance.
     *
     * @param null|array $except
     *
     * @return static
     */
    public function replicate(array $except = null) {
        $defaults = [
            $this->getKeyName(),
            $this->getCreatedAtColumn(),
            $this->getUpdatedAtColumn(),
        ];

        $attributes = carr::except(
            $this->attributes,
            $except ? array_unique(array_merge($except, $defaults)) : $defaults
        );

        return c::tap(new static(), function ($instance) use ($attributes) {
            $instance->setRawAttributes($attributes);

            $instance->setRelations($this->relations);
        });
    }

    /**
     * Determine if two models have the same ID and belong to the same table.
     *
     * @param null|\CModel $model
     *
     * @return bool
     */
    public function is($model) {
        return !is_null($model)
                && $this->getKey() === $model->getKey()
                && $this->getTable() === $model->getTable()
                && $this->getConnectionName() === $model->getConnectionName();
    }

    /**
     * Determine if two models are not the same.
     *
     * @param null|\CModel $model
     *
     * @return bool
     */
    public function isNot($model) {
        return !$this->is($model);
    }

    /**
     * Get the database connection for the model.
     *
     * @return CDatabase
     */
    public function getConnection() {
        return static::resolveConnection($this->getConnectionName());
    }

    /**
     * Get the current connection name for the model.
     *
     * @return string
     */
    public function getConnectionName() {
        return $this->connection;
    }

    /**
     * Set the connection associated with the model.
     *
     * @param mixed $connection
     *
     * @return $this
     */
    public function setConnection($connection) {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Resolve a connection instance.
     *
     * @param null|string $connection
     *
     * @return CDatabase
     */
    public static function resolveConnection($connection = null) {
        return static::getConnectionResolver()->connection($connection);
    }

    /**
     * Get the connection resolver instance.
     *
     * @param null|mixed $domain
     *
     * @return CDatabase_ResolverInterface
     */
    public static function getConnectionResolver($domain = null) {
        if ($domain == null) {
            $domain = CF::domain();
        }

        return CDatabase_Resolver::instance($domain);
    }

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable() {
        if (!isset($this->table)) {
            return str_replace('\\', '', cstr::snake(cstr::plural(c::classBasename($this))));
        }

        return $this->table;
    }

    /**
     * Set the table associated with the model.
     *
     * @param string $table
     *
     * @return $this
     */
    public function setTable($table) {
        $this->table = $table;

        return $this;
    }

    /**
     * Get the primary key for the model.
     *
     * @return string
     */
    public function getKeyName() {
        return $this->primaryKey;
    }

    /**
     * Set the primary key for the model.
     *
     * @param string $key
     *
     * @return $this
     */
    public function setKeyName($key) {
        $this->primaryKey = $key;

        return $this;
    }

    /**
     * Get the table qualified key name.
     *
     * @return string
     */
    public function getQualifiedKeyName() {
        return $this->qualifyColumn($this->getKeyName());
    }

    /**
     * Get the auto-incrementing key type.
     *
     * @return string
     */
    public function getKeyType() {
        return $this->keyType;
    }

    /**
     * Set the data type for the primary key.
     *
     * @param string $type
     *
     * @return $this
     */
    public function setKeyType($type) {
        $this->keyType = $type;

        return $this;
    }

    /**
     * Get the value indicating whether the IDs are incrementing.
     *
     * @return bool
     */
    public function getIncrementing() {
        return $this->incrementing;
    }

    /**
     * Set whether IDs are incrementing.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setIncrementing($value) {
        $this->incrementing = $value;

        return $this;
    }

    /**
     * Get the value of the model's primary key.
     *
     * @return mixed
     */
    public function getKey() {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * Get the queueable identity for the entity.
     *
     * @return mixed
     */
    public function getQueueableId() {
        return $this->getKey();
    }

    /**
     * Get the queueable relationships for the entity.
     *
     * @return array
     */
    public function getQueueableRelations() {
        $relations = [];

        foreach ($this->getRelations() as $key => $relation) {
            $relations[] = $key;

            if ($relation instanceof CQueue_QueueableCollectionInterface) {
                foreach ($relation->getQueueableRelations() as $collectionKey => $collectionValue) {
                    $relations[] = $key . '.' . $collectionKey;
                }
            }

            if ($relation instanceof CQueue_QueueableEntityInterface) {
                foreach ($relation->getQueueableRelations() as $entityKey => $entityValue) {
                    $relations[] = $key . '.' . $entityValue;
                }
            }
        }

        return array_unique($relations);
    }

    /**
     * Get the queueable connection for the entity.
     *
     * @return mixed
     */
    public function getQueueableConnection() {
        return $this->getConnectionName();
    }

    /**
     * Get the value of the model's route key.
     *
     * @return mixed
     */
    public function getRouteKey() {
        return $this->getAttribute($this->getRouteKeyName());
    }

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName() {
        return $this->getKeyName();
    }

    /**
     * Retrieve the model for a bound value.
     *
     * @param mixed $value
     *
     * @return null|\CModel
     */
    public function resolveRouteBinding($value) {
        return $this->where($this->getRouteKeyName(), $value)->first();
    }

    /**
     * Get the default foreign key name for the model.
     *
     * @return string
     */
    public function getForeignKey() {
        return $this->primaryKey;
    }

    /**
     * Get the number of models to return per page.
     *
     * @return int
     */
    public function getPerPage() {
        return $this->perPage;
    }

    /**
     * Set the number of models to return per page.
     *
     * @param int $perPage
     *
     * @return $this
     */
    public function setPerPage($perPage) {
        $this->perPage = $perPage;

        return $this;
    }

    /**
     * Dynamically retrieve attributes on the model.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function __get($key) {
        /**
         * Backward compatibility for use this->db using in model. it deprecated and this code will removed.
         */
        if ($key == 'db') {
            return $this->getConnection();
        }

        return $this->getAttribute($key);
    }

    /**
     * Dynamically set attributes on the model.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return void
     */
    public function __set($key, $value) {
        $this->setAttribute($key, $value);
    }

    /**
     * Determine if the given attribute exists.
     *
     * @param mixed $offset
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset) {
        return !is_null($this->getAttribute($offset));
    }

    /**
     * Get the value for a given offset.
     *
     * @param mixed $offset
     *
     * @return mixed
     */
    public function offsetGet($offset) {
        return $this->getAttribute($offset);
    }

    /**
     * Set the value for a given offset.
     *
     * @param mixed $offset
     * @param mixed $value
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value) {
        $this->setAttribute($offset, $value);
    }

    /**
     * Unset the value for a given offset.
     *
     * @param mixed $offset
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset) {
        unset($this->attributes[$offset], $this->relations[$offset]);
    }

    /**
     * Determine if an attribute or relation exists on the model.
     *
     * @param string $key
     *
     * @return bool
     */
    public function __isset($key) {
        return $this->offsetExists($key);
    }

    /**
     * Unset an attribute on the model.
     *
     * @param string $key
     *
     * @return void
     */
    public function __unset($key) {
        $this->offsetUnset($key);
    }

    /**
     * Handle dynamic method calls into the model.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters) {
        if (in_array($method, ['increment', 'decrement'])) {
            return $this->$method(...$parameters);
        }

        return $this->forwardCallTo($this->newQuery(), $method, $parameters);
    }

    /**
     * Handle dynamic static method calls into the method.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public static function __callStatic($method, $parameters) {
        return (new static())->$method(...$parameters);
    }

    /**
     * Convert the model to its string representation.
     *
     * @return string
     */
    public function __toString() {
        return $this->toJson();
    }

    /**
     * @return bool
     */
    public static function usesSoftDelete() {
        $classUses = c::collect(c::classUsesRecursive(static::class));
        $lastClassUses = $classUses->map(function ($item) {
            return carr::last(explode('_', $item));
        });
        if (in_array('SoftDeleteTrait', $lastClassUses->toArray())) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public static function usesDeleted() {
        $classUses = c::collect(c::classUsesRecursive(static::class));
        $lastClassUses = $classUses->map(function ($item) {
            return carr::last(explode('_', $item));
        });
        if (in_array('DeletedTrait', $lastClassUses->toArray())) {
            return true;
        }

        return false;
    }

    /**
     * Prepare the object for serialization.
     *
     * @return array
     */
    public function __sleep() {
        $this->mergeAttributesFromCachedCasts();

        $this->classCastCache = [];
        $this->attributeCastCache = [];

        return array_keys(get_object_vars($this));
    }

    /**
     * When a model is being unserialized, check if it needs to be booted.
     *
     * @return void
     */
    public function __wakeup() {
        $this->bootIfNotBooted();
        $this->initializeTraits();
    }
}
