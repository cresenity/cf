<?php

defined('SYSPATH') or die('No direct access allowed.');

class CResources_ConversionCollection extends CCollection {
    /**
     * @var CModel_Resource_ResourceInterface
     */
    protected $resource;

    /**
     * @param CModel_Resource_ResourceInterface $resource
     *
     * @return static
     */
    public static function createForResource(CModel_Resource_ResourceInterface $resource) {
        return (new static())->setResource($resource);
    }

    /**
     * @param CModel_Resource_ResourceInterface $resource
     *
     * @return $this
     */
    public function setResource(CModel_Resource_ResourceInterface $resource) {
        $this->resource = $resource;
        $this->items = [];
        $this->addConversionsFromRelatedModel($resource);
        $this->addManipulationsFromDb($resource);

        return $this;
    }

    /**
     *  Get a conversion by it's name.
     *
     * @param string $name
     *
     * @throws CResources_Exception_InvalidConversion
     *
     * @return CResources_Conversion
     */
    public function getByName($name) {
        $conversion = $this->first(function (CResources_Conversion $conversion) use ($name) {
            return $conversion->getName() === $name;
        });
        if (!$conversion) {
            throw CResources_Exception_InvalidConversion::unknownName($name);
        }

        return $conversion;
    }

    /**
     * Add the conversion that are defined on the related model of
     * the given resource.
     *
     * @param \Spatie\ResourceLibrary\Models\Resource $resource
     */
    protected function addConversionsFromRelatedModel(CModel_Resource_ResourceInterface $resource) {
        $modelName = carr::get(CModel_Relation::morphMap(), $resource->model_type, $resource->model_type);
        /* @var CModel_HasResourceInterface $model */
        $model = new $modelName();
        /*
         * In some cases the user might want to get the actual model
         * instance so conversion parameters can depend on model
         * properties. This will causes extra queries.
         */
        if ($model->registerResourceConversionsUsingModelInstance) {
            $model = $resource->model;
            $model->resourceConversion = [];
        }
        $model->registerAllResourceConversions($resource);

        $this->items = $model->resourceConversions;
    }

    /**
     * Add the extra manipulations that are defined on the given resource.
     *
     * @param CModel_Resource_ResourceInterface $resource
     */
    protected function addManipulationsFromDb(CModel_Resource_ResourceInterface $resource) {
        c::collect($resource->manipulations)->each(function ($manipulations, $conversionName) {
            $this->addManipulationToConversion(new CImage_Manipulations([$manipulations]), $conversionName);
        });
    }

    public function getConversions($collectionName = '') {
        if ($collectionName === '') {
            return $this;
        }

        return $this->filter->shouldBePerformedOn($collectionName);
    }

    /**
     * Get all the conversions in the collection that should be queued.
     *
     * @param mixed $collectionName
     */
    public function getQueuedConversions($collectionName = '') {
        return $this->getConversions($collectionName)->filter->shouldBeQueued();
    }

    /**
     * Add the given manipulation to the conversion with the given name.
     *
     * @param mixed $conversionName
     */
    protected function addManipulationToConversion(CImage_Manipulations $manipulations, $conversionName) {
        c::optional($this->first(function (CResources_Conversion $conversion) use ($conversionName) {
            return $conversion->getName() === $conversionName;
        }))->addAsFirstManipulations($manipulations);
        if ($conversionName === '*') {
            $this->each->addAsFirstManipulations(clone $manipulations);
        }
    }

    /**
     * Get all the conversions in the collection that should not be queued.
     *
     * @param mixed $collectionName
     */
    public function getNonQueuedConversions($collectionName = '') {
        return $this->getConversions($collectionName)->reject->shouldBeQueued();
    }

    /**
     * Return the list of conversion files.
     *
     * @param mixed $collectionName
     */
    public function getConversionsFiles($collectionName = '') {
        $fileName = $this->resource->file_name;

        return $this->getConversions($collectionName)->map(function (CResources_Conversion $conversion) use ($fileName) {
            return $conversion->getConversionFile($fileName);
        });
    }
}
