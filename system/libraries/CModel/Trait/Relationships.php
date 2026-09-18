<?php

defined('SYSPATH') or die('No direct access allowed.');

trait CModel_Trait_Relationships {
    use CModel_Trait_Relationships_ConcatenatesRelationships;

    /**
     * The many to many relationship methods.
     *
     * @var array
     */
    public static $manyMethods = [
        'belongsToMany', 'morphToMany', 'morphedByMany',
        'guessBelongsToManyRelation', 'findFirstMethodThatIsntRelation',
    ];

    /**
     * The loaded relationships for the model.
     *
     * @var array
     */
    protected $relations = [];

    /**
     * The relationships that should be touched on save.
     *
     * @var array
     */
    protected $touches = [];

    /**
     * The relation resolver callbacks.
     *
     * @var array
     */
    protected static $relationResolvers = [];

    /**
     * Get the dynamic relation resolver if defined or inherited, or return null.
     *
     * @param string $class
     * @param string $key
     *
     * @return mixed
     */
    public function relationResolver($class, $key) {
        if ($resolver = static::$relationResolvers[$class][$key] ?? null) {
            return $resolver;
        }

        if ($parent = get_parent_class($class)) {
            return $this->relationResolver($parent, $key);
        }

        return null;
    }

    /**
     * Define a dynamic relation resolver.
     *
     * @param string   $name
     * @param \Closure $callback
     *
     * @return void
     */
    public static function resolveRelationUsing($name, Closure $callback) {
        static::$relationResolvers = array_replace_recursive(
            static::$relationResolvers,
            [static::class => [$name => $callback]]
        );
    }

    /**
     * Define a one-to-one relationship.
     *
     * @param string $related
     * @param string $foreignKey
     * @param string $localKey
     *
     * @return CModel_Relation_HasOne
     */
    public function hasOne($related, $foreignKey = null, $localKey = null) {
        $instance = $this->newRelatedInstance($related);

        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $localKey = $localKey ?: $this->getKeyName();

        return $this->newHasOne($instance->newQuery(), $this, $instance->getTable() . '.' . $foreignKey, $localKey);
    }

    /**
     * Instantiate a new HasOne relationship.
     *
     * @param \CModel_Query $query
     * @param \CModel       $parent
     * @param string        $foreignKey
     * @param string        $localKey
     *
     * @return \CModel_Relation_HasOne
     */
    protected function newHasOne(CModel_Query $query, CModel $parent, $foreignKey, $localKey) {
        return new CModel_Relation_HasOne($query, $parent, $foreignKey, $localKey);
    }

    /**
     * Define a has-one-through relationship.
     *
     * @param string      $related
     * @param string      $through
     * @param null|string $firstKey
     * @param null|string $secondKey
     * @param null|string $localKey
     * @param null|string $secondLocalKey
     *
     * @return \CModel_Relation_HasOneThrough
     */
    public function hasOneThrough($related, $through, $firstKey = null, $secondKey = null, $localKey = null, $secondLocalKey = null) {
        $through = new $through();

        $firstKey = $firstKey ?: $this->getForeignKey();

        $secondKey = $secondKey ?: $through->getForeignKey();

        return $this->newHasOneThrough(
            $this->newRelatedInstance($related)->newQuery(),
            $this,
            $through,
            $firstKey,
            $secondKey,
            $localKey ?: $this->getKeyName(),
            $secondLocalKey ?: $through->getKeyName()
        );
    }

    /**
     * Instantiate a new HasOneThrough relationship.
     *
     * @param \CModel_Query $query
     * @param \CModel       $farParent
     * @param \CModel       $throughParent
     * @param string        $firstKey
     * @param string        $secondKey
     * @param string        $localKey
     * @param string        $secondLocalKey
     *
     * @return \CModel_Relation_HasOneThrough
     */
    protected function newHasOneThrough(CModel_Query $query, CModel $farParent, CModel $throughParent, $firstKey, $secondKey, $localKey, $secondLocalKey) {
        return new CModel_Relation_HasOneThrough($query, $farParent, $throughParent, $firstKey, $secondKey, $localKey, $secondLocalKey);
    }

    /**
     * Define a polymorphic one-to-one relationship.
     *
     * @param string $related
     * @param string $name
     * @param string $type
     * @param string $id
     * @param string $localKey
     *
     * @return CModel_Relation_MorphOne
     */
    public function morphOne($related, $name, $type = null, $id = null, $localKey = null) {
        $instance = $this->newRelatedInstance($related);

        list($type, $id) = $this->getMorphs($name, $type, $id);

        $table = $instance->getTable();

        $localKey = $localKey ?: $this->getKeyName();

        return new CModel_Relation_MorphOne($instance->newQuery(), $this, $table . '.' . $type, $table . '.' . $id, $localKey);
    }

    /**
     * Define an inverse one-to-one or many relationship.
     *
     * @param string $related
     * @param string $foreignKey
     * @param string $ownerKey
     * @param string $relation
     *
     * @return CModel_Relation_BelongsTo
     */
    public function belongsTo($related, $foreignKey = null, $ownerKey = null, $relation = null) {
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relationships.
        if (is_null($relation)) {
            $relation = $this->guessBelongsToRelation();
        }

        $instance = $this->newRelatedInstance($related);

        // If no foreign key was supplied, we can use a backtrace to guess the proper
        // foreign key name by using the name of the relationship function, which
        // when combined with an "_id" should conventionally match the columns.
        if (is_null($foreignKey)) {
            $foreignKey = $instance->getKeyName();
        }

        // Once we have the foreign key names, we'll just create a new Eloquent query
        // for the related models and returns the relationship instance which will
        // actually be responsible for retrieving and hydrating every relations.
        $ownerKey = $ownerKey ?: $instance->getKeyName();

        return new CModel_Relation_BelongsTo(
            $instance->newQuery(),
            $this,
            $foreignKey,
            $ownerKey,
            $relation
        );
    }

    /**
     * Define a polymorphic, inverse one-to-one or many relationship.
     *
     * @param string $name
     * @param string $type
     * @param string $id
     *
     * @return CModel_Relation_MorphTo
     */
    public function morphTo($name = null, $type = null, $id = null) {
        // If no name is provided, we will use the backtrace to get the function name
        // since that is most likely the name of the polymorphic interface. We can
        // use that to get both the class and foreign key that will be utilized.
        $name = $name ?: $this->guessBelongsToRelation();

        list($type, $id) = $this->getMorphs(
            cstr::snake($name),
            $type,
            $id
        );

        // If the type value is null it is probably safe to assume we're eager loading
        // the relationship. In this case we'll just pass in a dummy query where we
        // need to remove any eager loads that may already be defined on a model.
        return empty($class = $this->{$type}) ? $this->morphEagerTo($name, $type, $id) : $this->morphInstanceTo($class, $name, $type, $id);
    }

    /**
     * Define a polymorphic, inverse one-to-one or many relationship.
     *
     * @param string $name
     * @param string $type
     * @param string $id
     *
     * @return CModel_Relation_MorphTo
     */
    protected function morphEagerTo($name, $type, $id) {
        return new CModel_Relation_MorphTo(
            $this->newQuery()->setEagerLoads([]),
            $this,
            $id,
            null,
            $type,
            $name
        );
    }

    /**
     * Define a polymorphic, inverse one-to-one or many relationship.
     *
     * @param string $target
     * @param string $name
     * @param string $type
     * @param string $id
     *
     * @return CModel_Relation_MorphTo
     */
    protected function morphInstanceTo($target, $name, $type, $id) {
        $instance = $this->newRelatedInstance(
            static::getActualClassNameForMorph($target)
        );

        return new CModel_Relation_MorphTo(
            $instance->newQuery(),
            $this,
            $id,
            $instance->getKeyName(),
            $type,
            $name
        );
    }

    /**
     * Retrieve the actual class name for a given morph class.
     *
     * @param string $class
     *
     * @return string
     */
    public static function getActualClassNameForMorph($class) {
        return carr::get(CModel_Relation::morphMap() ?: [], $class, $class);
    }

    /**
     * Guess the "belongs to" relationship name.
     *
     * @return string
     */
    protected function guessBelongsToRelation() {
        list($one, $two, $caller) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

        return $caller['function'];
    }

    /**
     * Create a pending has-many-through or has-one-through relationship.
     *
     * @template TIntermediateModel of \CModel
     *
     * // @param  string|\CModel_Relation_HasMany<TIntermediateModel, covariant $this>|\CModel_Relation_HasOne<TIntermediateModel, covariant $this>  $relationship
     *
     * @param mixed $relationship
     *
     * @return (
     *     $relationship is string
     *     ? \CModel_PendingHasThroughRelationship<\CModel, $this>
     *     : \CModel_PendingHasThroughRelationship<TIntermediateModel, $this>
     * )
     */
    public function through($relationship) {
        if (is_string($relationship)) {
            $relationship = $this->{$relationship}();
        }

        return new CModel_PendingHasThroughRelationship($this, $relationship);
    }

    /**
     * Define a one-to-many relationship.
     *
     * @param string $related
     * @param string $foreignKey
     * @param string $localKey
     *
     * @return CModel_Relation_HasMany
     */
    public function hasMany($related, $foreignKey = null, $localKey = null) {
        $instance = $this->newRelatedInstance($related);

        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $localKey = $localKey ?: $this->getKeyName();

        return new CModel_Relation_HasMany(
            $instance->newQuery(),
            $this,
            $instance->getTable() . '.' . $foreignKey,
            $localKey
        );
    }

    /**
     * Define a has-many-through relationship.
     *
     * @param string      $related
     * @param string      $through
     * @param null|string $firstKey
     * @param null|string $secondKey
     * @param null|string $localKey
     * @param null|string $secondLocalKey
     *
     * @return CModel_Relation_HasManyThrough
     */
    public function hasManyThrough($related, $through, $firstKey = null, $secondKey = null, $localKey = null, $secondLocalKey = null) {
        $through = new $through();

        $firstKey = $firstKey ?: $this->getForeignKey();

        $secondKey = $secondKey ?: $through->getForeignKey();

        $localKey = $localKey ?: $this->getKeyName();

        $secondLocalKey = $secondLocalKey ?: $through->getKeyName();

        $instance = $this->newRelatedInstance($related);

        return new CModel_Relation_HasManyThrough($instance->newQuery(), $this, $through, $firstKey, $secondKey, $localKey, $secondLocalKey);
    }

    /**
     * Define a polymorphic one-to-many relationship.
     *
     * @param string $related
     * @param string $name
     * @param string $type
     * @param string $id
     * @param string $localKey
     *
     * @return CModel_Relation_MorphMany
     */
    public function morphMany($related, $name, $type = null, $id = null, $localKey = null) {
        $instance = $this->newRelatedInstance($related);

        // Here we will gather up the morph type and ID for the relationship so that we
        // can properly query the intermediate table of a relation. Finally, we will
        // get the table and create the relationship instances for the developers.
        list($type, $id) = $this->getMorphs($name, $type, $id);

        $table = $instance->getTable();

        $localKey = $localKey ?: $this->getKeyName();

        return new CModel_Relation_MorphMany($instance->newQuery(), $this, $table . '.' . $type, $table . '.' . $id, $localKey);
    }

    /**
     * Define a many-to-many relationship.
     *
     * @param string $related
     * @param string $table
     * @param string $foreignPivotKey
     * @param string $relatedPivotKey
     * @param string $parentKey
     * @param string $relatedKey
     * @param string $relation
     *
     * @return CModel_Relation_BelongsToMany
     */
    public function belongsToMany($related, $table = null, $foreignPivotKey = null, $relatedPivotKey = null, $parentKey = null, $relatedKey = null, $relation = null) {
        // If no relationship name was passed, we will pull backtraces to get the
        // name of the calling function. We will use that function name as the
        // title of this relation since that is a great convention to apply.
        if (is_null($relation)) {
            $relation = $this->guessBelongsToManyRelation();
        }

        // First, we'll need to determine the foreign key and "other key" for the
        // relationship. Once we have determined the keys we'll make the query
        // instances as well as the relationship instances we need for this.
        $instance = $this->newRelatedInstance($related);

        $foreignPivotKey = $foreignPivotKey ?: $this->getForeignKey();

        $relatedPivotKey = $relatedPivotKey ?: $instance->getForeignKey();

        // If no table name was provided, we can guess it by concatenating the two
        // models using underscores in alphabetical order. The two model names
        // are transformed to snake case from their default CamelCase also.
        if (is_null($table)) {
            $table = $this->joiningTable($related);
        }

        return new CModel_Relation_BelongsToMany(
            $instance->newQuery(),
            $this,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey ?: $this->getKeyName(),
            $relatedKey ?: $instance->getKeyName(),
            $relation
        );
    }

    /**
     * Define a polymorphic many-to-many relationship.
     *
     * @param string $related
     * @param string $name
     * @param string $table
     * @param string $foreignPivotKey
     * @param string $relatedPivotKey
     * @param string $parentKey
     * @param string $relatedKey
     * @param bool   $inverse
     *
     * @return CModel_Relation_MorphToMany
     */
    public function morphToMany($related, $name, $table = null, $foreignPivotKey = null, $relatedPivotKey = null, $parentKey = null, $relatedKey = null, $inverse = false) {
        $caller = $this->guessBelongsToManyRelation();

        // First, we will need to determine the foreign key and "other key" for the
        // relationship. Once we have determined the keys we will make the query
        // instances, as well as the relationship instances we need for these.
        $instance = $this->newRelatedInstance($related);

        $foreignPivotKey = $foreignPivotKey ?: $name . '_id';

        $relatedPivotKey = $relatedPivotKey ?: $instance->getForeignKey();

        // Now we're ready to create a new query builder for this related model and
        // the relationship instances for this relation. This relations will set
        // appropriate query constraints then entirely manages the hydrations.
        $table = $table ?: cstr::plural($name);

        return new CModel_Relation_MorphToMany(
            $instance->newQuery(),
            $this,
            $name,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey ?: $this->getKeyName(),
            $relatedKey ?: $instance->getKeyName(),
            $caller,
            $inverse
        );
    }

    /**
     * Define a polymorphic, inverse many-to-many relationship.
     *
     * @param string $related
     * @param string $name
     * @param string $table
     * @param string $foreignPivotKey
     * @param string $relatedPivotKey
     * @param string $parentKey
     * @param string $relatedKey
     *
     * @return CModel_Relation_MorphToMany
     */
    public function morphedByMany($related, $name, $table = null, $foreignPivotKey = null, $relatedPivotKey = null, $parentKey = null, $relatedKey = null) {
        $foreignPivotKey = $foreignPivotKey ?: $this->getForeignKey();

        // For the inverse of the polymorphic many-to-many relations, we will change
        // the way we determine the foreign and other keys, as it is the opposite
        // of the morph-to-many method since we're figuring out these inverses.
        $relatedPivotKey = $relatedPivotKey ?: $name . '_id';

        return $this->morphToMany(
            $related,
            $name,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            true
        );
    }

    /**
     * Get the relationship name of the belongs to many.
     *
     * @return string
     */
    protected function guessBelongsToManyRelation() {
        $caller = carr::first(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), function ($trace) {
            return !in_array($trace['function'], CModel::$manyMethods);
        });

        return !is_null($caller) ? $caller['function'] : null;
    }

    /**
     * Get the joining table name for a many-to-many relation.
     *
     * @param string $related
     *
     * @return string
     */
    public function joiningTable($related) {
        // The joining table name, by convention, is simply the snake cased models
        // sorted alphabetically and concatenated with an underscore, so we can
        // just sort the models and join them together to get the table name.
        $models = [
            cstr::snake(c::classBasename($related)),
            cstr::snake(c::classBasename($this)),
        ];

        // Now that we have the model names in an array we can just sort them and
        // use the implode function to join them together with an underscores,
        // which is typically used by convention within the database system.
        sort($models);

        return strtolower(implode('_', $models));
    }

    /**
     * Determine if the model touches a given relation.
     *
     * @param string $relation
     *
     * @return bool
     */
    public function touches($relation) {
        return in_array($relation, $this->touches);
    }

    /**
     * Touch the owning relations of the model.
     *
     * @return void
     */
    public function touchOwners() {
        foreach ($this->touches as $relation) {
            $this->$relation()->touch();

            if ($this->$relation instanceof self) {
                $this->$relation->fireModelEvent('saved', false);

                $this->$relation->touchOwners();
            } elseif ($this->$relation instanceof CCollection) {
                $this->$relation->each(function (CModel $relation) {
                    $relation->touchOwners();
                });
            }
        }
    }

    /**
     * Get the polymorphic relationship columns.
     *
     * @param string $name
     * @param string $type
     * @param string $id
     *
     * @return array
     */
    protected function getMorphs($name, $type, $id) {
        return [$type ?: $name . '_type', $id ?: $name . '_id'];
    }

    /**
     * Get the class name for polymorphic relations.
     *
     * @return string
     */
    public function getMorphClass() {
        $morphMap = CModel_Relation::morphMap();

        if (!empty($morphMap) && in_array(static::class, $morphMap)) {
            return array_search(static::class, $morphMap, true);
        }

        return static::class;
    }

    /**
     * Create a new model instance for a related model.
     *
     * @param string $class
     *
     * @return mixed
     */
    protected function newRelatedInstance($class) {
        return c::tap(new $class(), function ($instance) {
            //do nothing
        });
    }

    /**
     * Get all the loaded relations for the instance.
     *
     * @return array
     */
    public function getRelations() {
        return $this->relations;
    }

    /**
     * Get a specified relationship.
     *
     * @param string $relation
     *
     * @return mixed
     */
    public function getRelation($relation) {
        return $this->relations[$relation];
    }

    /**
     * Determine if the given relation is loaded.
     *
     * @param string $key
     *
     * @return bool
     */
    public function relationLoaded($key) {
        return array_key_exists($key, $this->relations);
    }

    /**
     * Set the specific relationship in the model.
     *
     * @param string $relation
     * @param mixed  $value
     *
     * @return $this
     */
    public function setRelation($relation, $value) {
        $this->relations[$relation] = $value;

        return $this;
    }

    /**
     * Set the entire relations array on the model.
     *
     * @param array $relations
     *
     * @return $this
     */
    public function setRelations(array $relations) {
        $this->relations = $relations;

        return $this;
    }

    /**
     * Duplicate the instance and unset all the loaded relations.
     *
     * @return $this
     */
    public function withoutRelations() {
        $model = clone $this;

        return $model->unsetRelations();
    }

    /**
     * Unset a loaded relationship.
     *
     * @param string $relation
     *
     * @return $this
     */
    public function unsetRelation($relation) {
        unset($this->relations[$relation]);

        return $this;
    }

    /**
     * Unset all the loaded relations for the instance.
     *
     * @return $this
     */
    public function unsetRelations() {
        $this->relations = [];

        return $this;
    }

    /**
     * Get the relationships that are touched on save.
     *
     * @return array
     */
    public function getTouchedRelations() {
        return $this->touches;
    }

    /**
     * Set the relationships that are touched on save.
     *
     * @param array $touches
     *
     * @return $this
     */
    public function setTouchedRelations(array $touches) {
        $this->touches = $touches;

        return $this;
    }

    /**
     * Define a belongs-to-through relationship.
     *
     * @param string       $related
     * @param array|string $through
     * @param null|string  $localKey
     * @param string       $prefix
     * @param array        $foreignKeyLookup
     *
     * @return CModel_Relation_BelongsToThrough
     */
    public function belongsToThrough($related, $through, $localKey = null, $prefix = '', $foreignKeyLookup = []) {
        $relatedInstance = $this->newRelatedInstance($related);
        $throughParents = [];
        $foreignKeys = [];

        foreach ((array) $through as $model) {
            $foreignKey = null;

            if (is_array($model)) {
                $foreignKey = $model[1];

                $model = $model[0];
            }

            $instance = $this->belongsToThroughParentInstance($model);

            if ($foreignKey) {
                $foreignKeys[$instance->getTable()] = $foreignKey;
            }

            $throughParents[] = $instance;
        }

        foreach ($foreignKeyLookup as $model => $foreignKey) {
            $instance = new $model();

            if ($foreignKey) {
                $foreignKeys[$instance->getTable()] = $foreignKey;
            }
        }

        return $this->newBelongsToThrough($relatedInstance->newQuery(), $this, $throughParents, $localKey, $prefix, $foreignKeys);
    }

    /**
     * Create a through parent instance for a belongs-to-through relationship.
     *
     * @param string $model
     *
     * @return \CModel
     */
    protected function belongsToThroughParentInstance($model) {
        $segments = preg_split('/\s+as\s+/i', $model);

        /** @var \CModel $instance */
        $instance = new $segments[0]();

        if (isset($segments[1])) {
            $instance->setTable($instance->getTable() . ' as ' . $segments[1]);
        }

        return $instance;
    }

    /**
     * Instantiate a new BelongsToThrough relationship.
     *
     * @param \CModel_Query $query
     * @param \CModel       $parent
     * @param \CModel[]     $throughParents
     * @param string        $localKey
     * @param string        $prefix
     * @param array         $foreignKeyLookup
     *
     * @return \CModel_Relation_BelongsToThrough
     */
    protected function newBelongsToThrough(CModel_Query $query, CModel $parent, array $throughParents, $localKey, $prefix, array $foreignKeyLookup) {
        return new CModel_Relation_BelongsToThrough($query, $parent, $throughParents, $localKey, $prefix, $foreignKeyLookup);
    }

    /**
     * Define a one-to-one via pivot relationship.
     *
     * @param string $related
     * @param string $table
     * @param string $foreignPivotKey
     * @param string $relatedPivotKey
     * @param string $parentKey
     * @param string $relatedKey
     * @param string $relation
     *
     * @return CModel_Relation_BelongsToOne
     */
    public function belongsToOne(
        $related,
        $table = null,
        $foreignPivotKey = null,
        $relatedPivotKey = null,
        $parentKey = null,
        $relatedKey = null,
        $relation = null
    ) {
        // If no relationship name was passed, we will pull backtraces to get the
        // name of the calling function. We will use that function name as the
        // title of this relation since that is a great convention to apply.
        if (is_null($relation)) {
            $relation = $this->guessBelongsToOneRelation();
        }

        // First, we'll need to determine the foreign key and "other key" for the
        // relationship. Once we have determined the keys we'll make the query
        // instances as well as the relationship instances we need for this.
        $instance = $this->newRelatedInstance($related);

        $foreignPivotKey = $foreignPivotKey ?: $this->getForeignKey();

        $relatedPivotKey = $relatedPivotKey ?: $instance->getForeignKey();

        // If no table name was provided, we can guess it by concatenating the two
        // models using underscores in alphabetical order. The two model names
        // are transformed to snake case from their default CamelCase also.
        if (is_null($table)) {
            $table = $this->joiningTable($related);
        }

        return $this->newBelongsToOne(
            $instance->newQuery(),
            $this,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey ?: $this->getKeyName(),
            $relatedKey ?: $instance->getKeyName(),
            $relation
        );
    }

    /**
     * Instantiate a new BelongsToOne relationship.
     *
     * @param \CModel_Query $query
     * @param \CModel       $parent
     * @param string        $table
     * @param string        $foreignPivotKey
     * @param string        $relatedPivotKey
     * @param string        $parentKey
     * @param string        $relatedKey
     * @param string        $relationName
     *
     * @return CModel_Relation_BelongsToOne
     */
    protected function newBelongsToOne(
        CModel_Query $query,
        CModel $parent,
        $table,
        $foreignPivotKey,
        $relatedPivotKey,
        $parentKey,
        $relatedKey,
        $relationName = null
    ) {
        return new CModel_Relation_BelongsToOne($query, $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName);
    }

    /**
     * Get the relationship name of the belongs to many.
     *
     * @return string
     */
    protected function guessBelongsToOneRelation() {
        list($one, $two, $caller) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

        return $caller['function'];
    }

    /**
     * Define a one-to-one via pivot relationship.
     *
     * @param string $related
     * @param string $name
     * @param string $table
     * @param string $foreignPivotKey
     * @param string $relatedPivotKey
     * @param string $parentKey
     * @param string $relatedKey
     * @param bool   $inverse
     *
     * @return \CModel_Relation_MorphToOne
     */
    public function morphToOne(
        $related,
        $name,
        $table = null,
        $foreignPivotKey = null,
        $relatedPivotKey = null,
        $parentKey = null,
        $relatedKey = null,
        $inverse = false
    ) {
        $caller = $this->guessBelongsToManyRelation();

        // First, we'll need to determine the foreign key and "other key" for the
        // relationship. Once we have determined the keys we'll make the query
        // instances as well as the relationship instances we need for this.
        $instance = $this->newRelatedInstance($related);

        $foreignPivotKey = $foreignPivotKey ?: $name . '_id';

        $relatedPivotKey = $relatedPivotKey ?: $instance->getForeignKey();

        // If no table name was provided, we can guess it by concatenating the two
        // models using underscores in alphabetical order. The two model names
        // are transformed to snake case from their default CamelCase also.
        $table = $table ?: cstr::plural($name);

        return $this->newMorphToOne(
            $instance->newQuery(),
            $this,
            $name,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey ?: $this->getKeyName(),
            $relatedKey ?: $instance->getKeyName(),
            $caller,
            $inverse
        );
    }

    /**
     * Instantiate a new MorphToOne relationship.
     *
     * @param \CModel_Query $query
     * @param \CModel       $parent
     * @param string        $name
     * @param string        $table
     * @param string        $foreignPivotKey
     * @param string        $relatedPivotKey
     * @param string        $parentKey
     * @param string        $relatedKey
     * @param string        $relationName
     * @param bool          $inverse
     *
     * @return \CModel_Relation_MorphToOne
     */
    protected function newMorphToOne(
        CModel_Query $query,
        CModel $parent,
        $name,
        $table,
        $foreignPivotKey,
        $relatedPivotKey,
        $parentKey,
        $relatedKey,
        $relationName = null,
        $inverse = false
    ) {
        return new CModel_Relation_MorphToOne(
            $query,
            $parent,
            $name,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            $relationName,
            $inverse
        );
    }

    /**
     * Define a polymorphic, inverse many-to-many relationship but one.
     *
     * @param string $related
     * @param string $name
     * @param string $table
     * @param string $foreignPivotKey
     * @param string $relatedPivotKey
     * @param string $parentKey
     * @param string $relatedKey
     *
     * @return \CModel_Relation_MorphToOne
     */
    public function morphedByOne(
        $related,
        $name,
        $table = null,
        $foreignPivotKey = null,
        $relatedPivotKey = null,
        $parentKey = null,
        $relatedKey = null
    ) {
        $foreignPivotKey = $foreignPivotKey ?: $this->getForeignKey();

        // For the inverse of the polymorphic many-to-many relations, we will change
        // the way we determine the foreign and other keys, as it is the opposite
        // of the morph-to-many method since we're figuring out these inverses.
        $relatedPivotKey = $relatedPivotKey ?: $name . '_id';

        return $this->morphToOne(
            $related,
            $name,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            true
        );
    }

    /**
     * Define a has-many-deep relationship.
     *
     * @param string $related
     * @param array  $through
     * @param array  $foreignKeys
     * @param array  $localKeys
     *
     * @return \CModel_Relation_HasManyDeep
     */
    public function hasManyDeep($related, array $through, array $foreignKeys = [], array $localKeys = []) {
        return $this->newHasManyDeep(...$this->hasOneOrManyDeep($related, $through, $foreignKeys, $localKeys));
    }

    /**
     * Define a has-many-deep relationship from existing relationships.
     *
     * @param \CModel_Relation ...$relations
     *
     * @return \CModel_Relation_HasManyDeep
     */
    public function hasManyDeepFromRelations(...$relations) {
        return $this->hasManyDeep(...$this->hasOneOrManyDeepFromRelations($relations));
    }

    /**
     * Define a has-one-deep relationship.
     *
     * @param string $related
     * @param array  $through
     * @param array  $foreignKeys
     * @param array  $localKeys
     *
     * @return \CModel_Relation_HasOneDeep
     */
    public function hasOneDeep($related, array $through, array $foreignKeys = [], array $localKeys = []) {
        return $this->newHasOneDeep(...$this->hasOneOrManyDeep($related, $through, $foreignKeys, $localKeys));
    }

    /**
     * Define a has-one-deep relationship from existing relationships.
     *
     * @param \CModel_Relation ...$relations
     *
     * @return \CModel_Relation_HasOneDeep
     */
    public function hasOneDeepFromRelations(...$relations) {
        return $this->hasOneDeep(...$this->hasOneOrManyDeepFromRelations($relations));
    }

    /**
     * Prepare a has-one-deep or has-many-deep relationship.
     *
     * @param string $related
     * @param array  $through
     * @param array  $foreignKeys
     * @param array  $localKeys
     *
     * @return array
     */
    protected function hasOneOrManyDeep($related, array $through, array $foreignKeys, array $localKeys) {
        $relatedSegments = preg_split('/\s+from\s+/i', $related);

        /** @var \CModel $relatedInstance */
        $relatedInstance = $this->newRelatedInstance($relatedSegments[0]);

        if (isset($relatedSegments[1])) {
            $relatedInstance->setTable($relatedSegments[1]);
        }

        $throughParents = $this->hasOneOrManyDeepThroughParents($through);

        $foreignKeys = $this->hasOneOrManyDeepForeignKeys($relatedInstance, $throughParents, $foreignKeys);

        $localKeys = $this->hasOneOrManyDeepLocalKeys($relatedInstance, $throughParents, $localKeys);

        return [$relatedInstance->newQuery(), $this, $throughParents, $foreignKeys, $localKeys];
    }

    /**
     * Prepare the through parents for a has-one-deep or has-many-deep relationship.
     *
     * @param array $through
     *
     * @return array
     */
    protected function hasOneOrManyDeepThroughParents(array $through) {
        return array_map(function ($class) {
            $segments = preg_split('/\s+as\s+/i', $class);

            $instance = cstr::contains($segments[0], '\\')
                ? new $segments[0]()
                : (new CModel_Relation_Pivot())->setTable($segments[0]);

            if (isset($segments[1])) {
                $instance->setTable($instance->getTable() . ' as ' . $segments[1]);
            }

            return $instance;
        }, $through);
    }

    /**
     * Prepare the foreign keys for a has-one-deep or has-many-deep relationship.
     *
     * @param \CModel   $related
     * @param \CModel[] $throughParents
     * @param array     $foreignKeys
     *
     * @return array
     */
    protected function hasOneOrManyDeepForeignKeys(CModel $related, array $throughParents, array $foreignKeys) {
        foreach (array_merge([$this], $throughParents) as $i => $instance) {
            /** @var \CModel $instance */
            if (!isset($foreignKeys[$i])) {
                if ($instance instanceof CModel_Relation_Pivot) {
                    $parent = (isset($throughParents[$i]) ? $throughParents[$i] : $related);
                    $foreignKeys[$i] = $parent->getKeyName();
                } else {
                    $foreignKeys[$i] = $instance->getForeignKey();
                }
            }
        }

        return $foreignKeys;
    }

    /**
     * Prepare the local keys for a has-one-deep or has-many-deep relationship.
     *
     * @param \CModel   $related
     * @param \CModel[] $throughParents
     * @param array     $localKeys
     *
     * @return array
     */
    protected function hasOneOrManyDeepLocalKeys(CModel $related, array $throughParents, array $localKeys) {
        foreach (array_merge([$this], $throughParents) as $i => $instance) {
            /** @var \CModel $instance */
            if (!isset($localKeys[$i])) {
                if ($instance instanceof CModel_Relation_Pivot) {
                    $parent = $parent = (isset($throughParents[$i]) ? $throughParents[$i] : $related);
                    $localKeys[$i] = $parent->getForeignKey();
                } else {
                    $localKeys[$i] = $instance->getKeyName();
                }
            }
        }

        return $localKeys;
    }

    /**
     * Instantiate a new HasManyDeep relationship.
     *
     * @param \CModel_Query $query
     * @param \CModel       $farParent
     * @param \CModel[]     $throughParents
     * @param array         $foreignKeys
     * @param array         $localKeys
     *
     * @return \CModel_Relation_HasManyDeep
     */
    protected function newHasManyDeep(CModel_Query $query, CModel $farParent, array $throughParents, array $foreignKeys, array $localKeys) {
        return new CModel_Relation_HasManyDeep($query, $farParent, $throughParents, $foreignKeys, $localKeys);
    }

    /**
     * Instantiate a new HasOneDeep relationship.
     *
     * @param \CModel_Query $query
     * @param \CModel       $farParent
     * @param \CModel[]     $throughParents
     * @param array         $foreignKeys
     * @param array         $localKeys
     *
     * @return \CModel_Relation_HasOneDeep
     */
    protected function newHasOneDeep(CModel_Query $query, CModel $farParent, array $throughParents, array $foreignKeys, array $localKeys) {
        return new CModel_Relation_HasOneDeep($query, $farParent, $throughParents, $foreignKeys, $localKeys);
    }
}
