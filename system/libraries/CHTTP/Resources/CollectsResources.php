<?php


use Illuminate\Http\Resources\Json\JsonResource;

use Illuminate\Support\Str;

trait CHTTP_Resources_CollectsResources
{
    /**
     * Map the given collection resource into its individual resources.
     *
     * @param  mixed  $resource
     * @return mixed
     */
    protected function collectResource($resource)
    {
        if ($resource instanceof CHTTP_Resources_MissingValue) {
            return $resource;
        }

        if (is_array($resource)) {
            $resource = new CCollection($resource);
        }

        $collects = $this->collects();

        $this->collection = $collects && ! $resource->first() instanceof $collects
            ? $resource->mapInto($collects)
            : $resource->toBase();

        return ($resource instanceof CPagination_AbstractPaginator || $resource instanceof CPagination_CursorPaginatorAbstract)
            ? $resource->setCollection($this->collection)
            : $this->collection;
    }

    /**
     * Get the resource that this resource collects.
     *
     * @return string|null
     */
    protected function collects()
    {
        $collects = null;

        if ($this->collects) {
            $collects = $this->collects;
        } elseif (cstr::endsWith(c::classBasename($this), 'Collection') &&
            (class_exists($class = cstr::replaceLast('Collection', '', get_class($this))) ||
             class_exists($class = cstr::replaceLast('Collection', 'Resource', get_class($this))))
        ) {
            $collects = $class;
        }

        if (! $collects || is_a($collects, CHTTP_Resources_Json_JsonResource::class, true)) {
            return $collects;
        }

        throw new LogicException('Resource collections must collect instances of '.CHTTP_Resources_Json_JsonResource::class.'.');
    }

    /**
     * Get the JSON serialization options that should be applied to the resource response.
     *
     * @return int
     *
     * @throws \ReflectionException
     */
    public function jsonOptions()
    {
        $collects = $this->collects();

        if (! $collects) {
            return 0;
        }

        return (new ReflectionClass($collects))
            ->newInstanceWithoutConstructor()
            ->jsonOptions();
    }

    /**
     * Get an iterator for the resource collection.
     *
     * @return \ArrayIterator
     */
    public function getIterator(): Traversable
    {
        return $this->collection->getIterator();
    }
}
