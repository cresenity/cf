<?php


/**
 * @template TIntermediateModel of \CModel
 * @template TDeclaringModel of \C\Model
 */
class CModel_PendingHasThroughRelationship {
    /**
     * The root model that the relationship exists on.
     *
     * @var TDeclaringModel
     */
    protected $rootModel;

    /**
     * The local relationship.
     *
     * @var \CModel_Relation_HasMany<TIntermediateModel, TDeclaringModel>|\Illuminate\Database\Eloquent\Relations\HasOne<TIntermediateModel, TDeclaringModel>
     */
    protected $localRelationship;

    /**
     * Create a pending has-many-through or has-one-through relationship.
     *
     * @param TDeclaringModel                                                                                                                                   $rootModel
     * @param \CModel_Relation_HasMany<TIntermediateModel, TDeclaringModel>|\Illuminate\Database\Eloquent\Relations\HasOne<TIntermediateModel, TDeclaringModel> $localRelationship
     */
    public function __construct($rootModel, $localRelationship) {
        $this->rootModel = $rootModel;

        $this->localRelationship = $localRelationship;
    }

    /**
     * Define the distant relationship that this model has.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  string|(callable(TIntermediateModel): (\CModel_Relation_HasOne<TRelatedModel, TIntermediateModel>|\CModel_Relation_HasMany<TRelatedModel, TIntermediateModel>))  $callback
     *
     * @return (
     *     $callback is string
     *     ? \CModel_Relation_HasManyThrough<\CModel, TIntermediateModel, TDeclaringModel>|\CModel_Relation_HasOneThrough<\CModel, TIntermediateModel, TDeclaringModel>
     *     : (
     *         $callback is callable(TIntermediateModel): \CModel_Relation_HasOne<TRelatedModel, TIntermediateModel>
     *         ? \CModel_Relation_HasOneThrough<TRelatedModel, TIntermediateModel, TDeclaringModel>
     *         : \CModel_Relation_HasManyThrough<TRelatedModel, TIntermediateModel, TDeclaringModel>
     *     )
     * )
     */
    public function has($callback) {
        if (is_string($callback)) {
            $callback = fn () => $this->localRelationship->getRelated()->{$callback}();
        }

        $distantRelation = $callback($this->localRelationship->getRelated());
        $distantRelationRelatedClass = get_class($distantRelation->getRelated());
        $localRelationshipRelatedClass = get_class($this->localRelationship->getRelated());

        if ($distantRelation instanceof CModel_Relation_HasMany) {
            return $this->rootModel->hasManyThrough(
                $distantRelationRelatedClass,
                $localRelationshipRelatedClass,
                $this->localRelationship->getForeignKeyName(),
                $distantRelation->getForeignKeyName(),
                $this->localRelationship->getLocalKeyName(),
                $distantRelation->getLocalKeyName(),
            );
        }

        return $this->rootModel->hasOneThrough(
            $distantRelationRelatedClass,
            $localRelationshipRelatedClass,
            $this->localRelationship->getForeignKeyName(),
            $distantRelation->getForeignKeyName(),
            $this->localRelationship->getLocalKeyName(),
            $distantRelation->getLocalKeyName(),
        );
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
        if (cstr::startsWith($method, 'has')) {
            return $this->has(cstr::of($method)->after('has')->lcfirst()->toString());
        }

        throw new BadMethodCallException(sprintf(
            'Call to undefined method %s::%s()',
            static::class,
            $method
        ));
    }
}
