<?php

defined('SYSPATH') or die('No direct access allowed.');

class CResources_Repository {
    /**
     * @var CModel_Resource_ResourceInterface|CModel
     */
    protected $model;

    /**
     * @param CModel_Resource_ResourceInterface $model
     */
    public function __construct(?CModel_Resource_ResourceInterface $model = null) {
        if ($model == null) {
            $resourceModel = CF::config('resource.resource_model', CApp_Model_Resource::class);
            $model = new $resourceModel();
        }
        $this->model = $model;
    }

    /**
     * Get all resource in the collection.
     *
     * @param CModel_HasResourceInterface $model
     * @param string                      $collectionName
     * @param array|callable              $filter
     *
     * @return CCollection
     */
    public function getCollection(CModel_HasResourceInterface $model, $collectionName, $filter = []) {
        return $this->applyFilterToResourceCollection($model->loadResource($collectionName), $filter);
    }

    /**
     * Apply given filters on resource.
     *
     * @param CCollection    $resource
     * @param array|callable $filter
     *
     * @return CCollection
     */
    protected function applyFilterToResourceCollection(CCollection $resource, $filter) {
        if (is_array($filter)) {
            $filter = $this->getDefaultFilterFunction($filter);
        }

        return $resource->filter($filter);
    }

    /**
     * @return CModel_Resource_ResourceInterface|CModel
     */
    public function getModel() {
        return $this->model;
    }

    public function all() {
        return $this->model->all();
    }

    /**
     * @return CCollection
     */
    public function allIds() {
        return $this->query()->pluck($this->model->getKeyName());
    }

    /**
     * Every disk named by a resource row, original or conversions.
     *
     * @return CCollection
     */
    public function allDiskNames() {
        $diskNames = $this->query()->distinct()->pluck('disk');
        if ($this->model->hasConversionsDiskColumn()) {
            $diskNames = $diskNames->merge($this->query()->distinct()->pluck('conversions_disk'));
        }

        return $diskNames->filter()->unique()->values();
    }

    public function getByModelType($modelType) {
        return $this->model->where('model_type', $modelType)->get();
    }

    public function getByIds($ids) {
        return $this->model->whereIn($this->model->getKeyName(), $ids)->get();
    }

    /**
     * @param int    $startingFromId
     * @param bool   $excludeStartingId
     * @param string $modelType
     *
     * @return CModel_Collection
     */
    public function getByIdGreaterThan($startingFromId, $excludeStartingId = false, $modelType = '') {
        return $this->query()
            ->where($this->model->getKeyName(), $excludeStartingId ? '>' : '>=', $startingFromId)
            ->when($modelType !== '', function (CModel_Query $q) use ($modelType) {
                $q->where('model_type', $modelType);
            })
            ->get();
    }

    public function getByModelTypeAndCollectionName($modelType, $collectionName) {
        return $this->model
            ->where('model_type', $modelType)
            ->where('collection_name', $collectionName)
            ->get();
    }

    public function getByCollectionName($collectionName) {
        return $this->model
            ->where('collection_name', $collectionName)
            ->get();
    }

    /**
     * Resources whose owning model row no longer exists (soft-deleted owners still count as present).
     *
     * @return CModel_Collection
     */
    public function getOrphans() {
        return $this->orphansQuery()->get();
    }

    /**
     * @param string $collectionName
     *
     * @return CModel_Collection
     */
    public function getOrphansByCollectionName($collectionName) {
        return $this->orphansQuery()->where('collection_name', $collectionName)->get();
    }

    /**
     * Query narrowed by model type and/or collection name; both null for every resource.
     *
     * @param null|string $modelType
     * @param null|string $collectionName
     *
     * @return CModel_Query
     */
    public function queryFor($modelType = null, $collectionName = null) {
        return $this->query()
            ->when($modelType !== null && $modelType !== '', function (CModel_Query $q) use ($modelType) {
                $q->where('model_type', $modelType);
            })
            ->when($collectionName !== null && $collectionName !== '', function (CModel_Query $q) use ($collectionName) {
                $q->where('collection_name', $collectionName);
            });
    }

    /**
     * Orphan query over the model types that resolve to a class; see getUnresolvableModelTypes() for the rest.
     *
     * @return CModel_Query
     */
    public function orphansQuery() {
        $types = $this->resolvableModelTypes();
        if ($types->isEmpty()) {
            return $this->query()->whereRaw('0 = 1');
        }

        return $this->query()->whereDoesntHaveMorph('model', $types->all(), function (CModel_Query $q) {
            return $q->hasMacro('withTrashed') ? $q->withTrashed() : $q;
        });
    }

    /**
     * Distinct model_type values with no loadable class; their rows cannot be checked for orphans.
     *
     * @return CCollection
     */
    public function getUnresolvableModelTypes() {
        return $this->distinctModelTypes()->reject(function ($type) {
            return class_exists(CModel_Relation::getMorphedModel($type) ?: $type);
        })->values();
    }

    /**
     * @return CCollection
     */
    protected function resolvableModelTypes() {
        return $this->distinctModelTypes()->filter(function ($type) {
            return class_exists(CModel_Relation::getMorphedModel($type) ?: $type);
        })->values();
    }

    /**
     * @return CCollection
     */
    protected function distinctModelTypes() {
        return $this->query()->distinct()->pluck('model_type')->filter()->values();
    }

    /**
     * @return CModel_Query
     */
    protected function query() {
        return $this->model->newQuery();
    }

    /**
     * Convert the given array to a filter function.
     *
     * @param $filters
     *
     * @return \Closure
     */
    protected function getDefaultFilterFunction(array $filters) {
        return function (CModel_Resource_ResourceInterface $resource) use ($filters) {
            foreach ($filters as $property => $value) {
                if (!carr::has($resource->custom_properties, $property)) {
                    return false;
                }
                if (carr::get($resource->custom_properties, $property) !== $value) {
                    return false;
                }
            }

            return true;
        };
    }
}
