<?php
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
class CModel_Relation_MorphToMany extends CModel_Relation_BelongsToMany {
    /**
     * The type of the polymorphic relation.
     *
     * @var string
     */
    protected $morphType;

    /**
     * The class name of the morph type constraint.
     *
     * @var string
     */
    protected $morphClass;

    /**
     * Indicates if we are connecting the inverse of the relation.
     *
     * This primarily affects the morphClass constraint.
     *
     * @var bool
     */
    protected $inverse;

    /**
     * Create a new morph to many relationship instance.
     *
     * @param CModel_Query $query
     * @param CModel       $parent
     * @param string       $name
     * @param string       $table
     * @param string       $foreignPivotKey
     * @param string       $relatedPivotKey
     * @param string       $parentKey
     * @param string       $relatedKey
     * @param string       $relationName
     * @param bool         $inverse
     *
     * @return void
     */
    public function __construct(
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
        $this->inverse = $inverse;
        $this->morphType = $name . '_type';
        $this->morphClass = $inverse ? $query->getModel()->getMorphClass() : $parent->getMorphClass();

        parent::__construct(
            $query,
            $parent,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            $relationName
        );
    }

    /**
     * Set the where clause for the relation query.
     *
     * @return $this
     */
    protected function addWhereConstraints() {
        parent::addWhereConstraints();

        $this->query->where($this->qualifyPivotColumn($this->morphType), $this->morphClass);

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
        parent::addEagerConstraints($models);

        $this->query->where($this->qualifyPivotColumn($this->morphType), $this->morphClass);
    }

    /**
     * Create a new pivot attachment record.
     *
     * @param int  $id
     * @param bool $timed
     *
     * @return array
     */
    protected function baseAttachRecord($id, $timed) {
        return carr::add(
            parent::baseAttachRecord($id, $timed),
            $this->morphType,
            $this->morphClass
        );
    }

    /**
     * Add the constraints for a relationship count query.
     *
     * @param CModel_Query $query
     * @param CModel_Query $parentQuery
     * @param array|mixed  $columns
     *
     * @return CModel_Query
     */
    public function getRelationExistenceQuery(CModel_Query $query, CModel_Query $parentQuery, $columns = ['*']) {
        return parent::getRelationExistenceQuery($query, $parentQuery, $columns)->where(
            $this->qualifyPivotColumn($this->morphType),
            $this->morphClass
        );
    }

    /**
     * Get the pivot models that are currently attached.
     *
     * @return \CCollection
     */
    protected function getCurrentlyAttachedPivots() {
        return parent::getCurrentlyAttachedPivots()->map(function ($record) {
            return $record instanceof CModel_Relation_MorphPivot
                ? $record->setMorphType($this->morphType)->setMorphClass($this->morphClass)
                : $record;
        });
    }

    /**
     * Create a new query builder for the pivot table.
     *
     * @return \CDatabase_Query_Builder
     */
    protected function newPivotQuery() {
        return parent::newPivotQuery()->where($this->morphType, $this->morphClass);
    }

    /**
     * Create a new pivot model instance.
     *
     * @param array $attributes
     * @param bool  $exists
     *
     * @return CModel_Relation_Pivot
     */
    public function newPivot(array $attributes = [], $exists = false) {
        $using = $this->using;

        $pivot = $using ? $using::fromRawAttributes($this->parent, $attributes, $this->table, $exists)
                        : CModel_Relation_MorphPivot::fromAttributes($this->parent, $attributes, $this->table, $exists);

        $pivot->setPivotKeys($this->foreignPivotKey, $this->relatedPivotKey)
            ->setMorphType($this->morphType)
            ->setMorphClass($this->morphClass);

        return $pivot;
    }

    /**
     * Get the pivot columns for the relation.
     *
     * "pivot_" is prefixed at each column for easy removal later.
     *
     * @return array
     */
    protected function aliasedPivotColumns() {
        $defaults = [$this->foreignPivotKey, $this->relatedPivotKey, $this->morphType];

        return c::collect(array_merge($defaults, $this->pivotColumns))->map(function ($column) {
            return $this->qualifyPivotColumn($column) . ' as pivot_' . $column;
        })->unique()->all();
    }

    /**
     * Get the foreign key "type" name.
     *
     * @return string
     */
    public function getMorphType() {
        return $this->morphType;
    }

    /**
     * Get the class name of the parent model.
     *
     * @return string
     */
    public function getMorphClass() {
        return $this->morphClass;
    }

    /**
     * Get the indicator for a reverse relationship.
     *
     * @return bool
     */
    public function getInverse() {
        return $this->inverse;
    }
}
