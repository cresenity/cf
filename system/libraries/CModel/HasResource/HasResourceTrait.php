<?php
/**
 * @property-read CModel_Collection|CModel_Resource_ResourceInterface[] $resource
 */
trait CModel_HasResource_HasResourceTrait {
    /**
     * @var array
     */
    public $resourceConversions = [];

    /**
     * @var array
     */
    public $resourceCollections = [];

    /**
     * @var bool
     */
    protected $deletePreservingResource = false;

    /**
     * @var array
     */
    protected $unAttachedResourceLibraryItems = [];

    public static function bootHasResourceTrait() {
        static::deleting(function (CModel_HasResourceInterface $entity) {
            if ($entity->shouldDeletePreservingResource()) {
                return;
            }
            if (in_array(CModel_SoftDelete_SoftDeleteTrait::class, c::classUsesRecursive($entity))) {
                if (!$entity->forceDeleting) {
                    return;
                }
            }
            $entity->resource()->get()->each->delete();
        });
    }

    /**
     * Set the polymorphic relation.
     *
     * @return CModel_Relation_MorphMany
     */
    public function resource() {
        $resourceModel = CF::config('resource.resource_model', CApp_Model_Resource::class);

        return $this->morphMany($resourceModel, 'model');
    }

    /**
     * Add a file to the resourcelibrary.
     *
     * @param string|\Symfony\Component\HttpFoundation\File\UploadedFile $file
     *
     * @return CModel_HasResource_FileAdder_FileAdder
     */
    public function addResource($file) {
        return CModel_HasResource_FileAdder_FileAdderFactory::create($this, $file);
    }

    /**
     * Add a file from a request.
     *
     * @param string $key
     *
     * @return CModel_HasResource_FileAdder_FileAdder
     */
    public function addResourceFromRequest($key) {
        return CModel_HasResource_FileAdder_FileAdderFactory::createFromRequest($this, $key);
    }

    /**
     * Add a file from the given disk.
     *
     * @param string $key
     * @param string $disk
     *
     * @return CModel_HasResource_FileAdder_FileAdder
     */
    public function addResourceFromDisk($key, $disk = null) {
        return CModel_HasResource_FileAdder_FileAdderFactory::createFromDisk($this, $key, $disk ?: CF::config('storage.default'));
    }

    /**
     * Add multiple files from a request by keys.
     *
     * @param string[] $keys
     *
     * @return CModel_HasResource_FileAdder_FileAdder[]
     */
    public function addMultipleResourceFromRequest(array $keys) {
        return CModel_HasResource_FileAdder_FileAdderFactory::createMultipleFromRequest($this, $keys);
    }

    /**
     * Add all files from a request.
     *
     * @return CModel_HasResource_FileAdder_FileAdder[]
     */
    public function addAllResourceFromRequest() {
        return CModel_HasResource_FileAdder_FileAdderFactory::createAllFromRequest($this);
    }

    /**
     * Add a remote file to the resourcelibrary.
     *
     * //@param string       $url
     * //@param string|array ...$allowedMimeTypes
     *
     * @throws CResources_Exception_FileCannotBeAdded
     *
     * @return CModel_HasResource_FileAdder_FileAdder
     */
    public function addResourceFromUrl() {
        $args = func_get_args();
        $url = carr::get($args, 0);
        $allowedMimeTypes = array_slice($args, 2);

        if (!cstr::startsWith($url, ['http://', 'https://'])) {
            throw CResources_Exception_InvalidUrlException::doesNotStartWithProtocol($url);
        }
        $downloader = CF::config('resource.resource_downloader', CResources_Downloader_DefaultDownloader::class);
        $downloader = new $downloader();
        /** @var CResources_Downloader_DefaultDownloader $downloader */
        $temporaryFile = $downloader->getTempFile($url);

        $this->guardAgainstInvalidMimeType($temporaryFile, $allowedMimeTypes);
        $filename = carr::get($args, 1);

        if ($filename == null) {
            $filename = basename(parse_url($url, PHP_URL_PATH));
        }
        $filename = str_replace('%20', ' ', $filename);
        if ($filename === '') {
            $filename = 'file';
        }
        $resourceExtension = explode('/', mime_content_type($temporaryFile));
        if (!cstr::contains($filename, '.')) {
            $filename = "{$filename}.{$resourceExtension[1]}";
        }
        $file = CModel_HasResource_FileAdder_FileAdderFactory::create($this, $temporaryFile)
            ->usingName(pathinfo($filename, PATHINFO_FILENAME))
            ->usingFileName($filename);

        return $file;
    }

    /**
     * Add a remote file to the resourcelibrary.
     *
     * //@param string       $url
     * //@param string|array ...$allowedMimeTypes
     *
     * @throws CResources_Exception_FileCannotBeAdded
     *
     * @return CModel_HasResource_FileAdder_FileAdder
     */
    public function addResourceFromString(string $text) {
        $tmpFile = tempnam(sys_get_temp_dir(), 'resource-library');

        file_put_contents($tmpFile, $text);

        $file = CModel_HasResource_FileAdder_FileAdderFactory::create($this, $tmpFile)
            ->usingFileName('text.txt');

        return $file;
    }

    /**
     * Add a base64 encoded file to the resourcelibrary.
     *
     * //@param string       $base64data
     * //@param string|array ...$allowedMimeTypes
     *
     * @throws CResources_Exception_FileCannotBeAdded_InvalidBase64Data
     * @throws CResources_Exception_FileCannotBeAdded
     *
     * @return CModel_HasResource_FileAdder_FileAdder
     */
    public function addResourceFromBase64() {
        $args = func_get_args();
        $base64data = carr::get($args, 0);
        $allowedMimeTypes = array_slice($args, 1);
        // strip out data uri scheme information (see RFC 2397)
        if (strpos($base64data, ';base64') !== false) {
            $base64data = carr::get(explode(';', $base64data), 1);
            $base64data = carr::get(explode(',', $base64data), 1);
        }
        // strict mode filters for non-base64 alphabet characters
        if (base64_decode($base64data, true) === false) {
            throw CResources_Exception_FileCannotBeAdded_InvalidBase64Data::create();
        }
        // decoding and then reencoding should not change the data
        if (base64_encode(base64_decode($base64data)) !== $base64data) {
            throw CResources_Exception_FileCannotBeAdded_InvalidBase64Data::create();
        }
        $binaryData = base64_decode($base64data);
        // temporarily store the decoded data on the filesystem to be able to pass it to the fileAdder
        $tmpFile = tempnam(sys_get_temp_dir(), 'resourcelibrary');
        file_put_contents($tmpFile, $binaryData);
        $this->guardAgainstInvalidMimeType($tmpFile, $allowedMimeTypes);
        $file = CModel_HasResource_FileAdder_FileAdderFactory::create($this, $tmpFile);

        return $file;
    }

    /**
     * Add a file to the media library from a stream.
     *
     * @param mixed $stream
     *
     * @return CModel_HasResource_FileAdder_FileAdder
     */
    public function addResourceFromStream($stream) {
        $tmpFile = tempnam(sys_get_temp_dir(), 'resource-library');

        file_put_contents($tmpFile, $stream);

        $file = CModel_HasResource_FileAdder_FileAdderFactory::create($this, $tmpFile)
            ->usingFileName('text.txt');

        return $file;
    }

    /**
     * Copy a file to the resourcelibrary.
     *
     * @param string|\Symfony\Component\HttpFoundation\File\UploadedFile $file
     *
     * @return CModel_HasResource_FileAdder_FileAdder
     */
    public function copyResource($file) {
        return $this->addResource($file)->preservingOriginal();
    }

    /**
     * Determine if there is resource in the given collection.
     *
     * @param mixed $collectionName
     */
    public function hasResource($collectionName = 'default') {
        return count($this->getResource($collectionName)) ? true : false;
    }

    /**
     * Get resource collection by its collectionName.
     *
     * @param string         $collectionName
     * @param array|callable $filters
     *
     * @return CModel_Resource_ResourceCollection
     */
    public function getResource($collectionName = 'default', $filters = []) {
        $repository = $this->getResourceRepository();

        return $repository->getCollection($this, $collectionName, $filters);
    }

    public function getResourceRepository(): CResources_Repository {
        return c::container(CResources_Repository::class);
    }

    public function getResourceModel(): string {
        return CF::config('resource.resource_model', CApp_Model_Resource::class);
    }

    /**
     * @param string $collectionName
     *
     * @return null|CModel_Resource_ResourceInterface
     */
    public function getFirstResource($collectionName = 'default', array $filters = []) {
        return $this->getResourceItem($collectionName, $filters, CResources_Enum_CollectionPosition::FIRST);
    }

    /**
     * @param string $collectionName
     *
     * @return null|CModel_Resource_ResourceInterface
     */
    public function getLastResource($collectionName = 'default', array $filters = []) {
        return $this->getResourceItem($collectionName, $filters, CResources_Enum_CollectionPosition::LAST);
    }

    /**
     * @param array|callable $filters
     *
     * @return CModel_Resource_ResourceInterface
     */
    protected function getResourceItem(string $collectionName, $filters, string $position) {
        $resource = $this->getResource($collectionName, $filters);

        return $position === CResources_Enum_CollectionPosition::FIRST
            ? $resource->first()
            : $resource->last();
    }

    private function getResourceItemUrl(string $collectionName, string $conversionName, string $position): string {
        $resource = $position === CResources_Enum_CollectionPosition::FIRST
            ? $this->getFirstResource($collectionName)
            : $this->getLastResource($collectionName);

        if (!$resource) {
            return $this->getFallbackResourceUrl($collectionName, $conversionName) ?: '';
        }

        if ($conversionName !== '' && !$resource->hasGeneratedConversion($conversionName)) {
            return $resource->getUrl();
        }

        return $resource->getUrl($conversionName);
    }

    /**
     * Get the url of the image for the given conversionName
     * for first resource for the given collectionName.
     * If no profile is given, return the source's url.
     *
     * @param mixed $collectionName
     * @param mixed $conversionName
     *
     * @return string
     */
    public function getFirstResourceUrl($collectionName = 'default', $conversionName = '') {
        return $this->getResourceItemUrl($collectionName, $conversionName, CResources_Enum_CollectionPosition::FIRST);
    }

    /**
     * Get the url of the image for the given conversionName
     * for first resource for the given collectionName.
     * If no profile is given, return the source's url.
     *
     * @param mixed $collectionName
     * @param mixed $conversionName
     *
     * @return string
     */
    public function getLastResourceUrl($collectionName = 'default', $conversionName = '') {
        return $this->getResourceItemUrl($collectionName, $conversionName, CResources_Enum_CollectionPosition::LAST);
    }

    private function getResourceItemFullUrl(string $collectionName, string $conversionName, string $position): string {
        $resource = $position === CResources_Enum_CollectionPosition::FIRST
            ? $this->getFirstResource($collectionName)
            : $this->getLastResource($collectionName);

        if (!$resource) {
            return $this->getFallbackResourceUrl($collectionName, $conversionName) ?: '';
        }

        if ($conversionName !== '' && !$resource->hasGeneratedConversion($conversionName)) {
            return $resource->getFullUrl();
        }

        return $resource->getFullUrl($conversionName);
    }

    /**
     * Get the url of the image for the given conversionName
     * for first resource for the given collectionName.
     * If no profile is given, return the source's full url.
     *
     * @param mixed $collectionName
     * @param mixed $conversionName
     *
     * @return string
     */
    public function getFirstResourceFullUrl($collectionName = 'default', $conversionName = '') {
        return $this->getResourceItemFullUrl($collectionName, $conversionName, CResources_Enum_CollectionPosition::FIRST);
    }

    /**
     * Get the url of the image for the given conversionName
     * for last resource for the given collectionName.
     * If no profile is given, return the source's full url.
     *
     * @param mixed $collectionName
     * @param mixed $conversionName
     *
     * @return string
     */
    public function getLastResourceFullUrl($collectionName = 'default', $conversionName = '') {
        return $this->getResourceItemFullUrl($collectionName, $conversionName, CResources_Enum_CollectionPosition::LAST);
    }

    private function getResourceItemTemporaryUrl(
        DateTimeInterface $expiration,
        string $collectionName,
        string $conversionName,
        string $position
    ): string {
        $resource = $position === CResources_Enum_CollectionPosition::FIRST
            ? $this->getFirstResource($collectionName)
            : $this->getLastResource($collectionName);

        if (!$resource) {
            return $this->getFallbackResourceUrl($collectionName, $conversionName) ?: '';
        }

        if ($conversionName !== '' && !$resource->hasGeneratedConversion($conversionName)) {
            return $resource->getTemporaryUrl($expiration);
        }

        return $resource->getTemporaryUrl($expiration, $conversionName);
    }

    /**
     * Get the url of the image for the given conversionName
     * for first resource for the given collectionName.
     * If no profile is given, return the source's url.
     *
     * @param mixed $collectionName
     * @param mixed $conversionName
     */
    public function getFirstTemporaryUrl(?DateTimeInterface $expiration = null, $collectionName = 'default', $conversionName = '') {
        $expiration = $expiration ?: c::now()->addMinutes(CF::config('resource.temporary_url_default_lifetime'));

        return $this->getResourceItemTemporaryUrl($expiration, $collectionName, $conversionName, CResources_Enum_CollectionPosition::FIRST);
    }

    /**
     * Get the url of the image for the given conversionName
     * for last resource for the given collectionName.
     * If no profile is given, return the source's url.
     *
     * @param mixed $collectionName
     * @param mixed $conversionName
     */
    public function getLastTemporaryUrl(?DateTimeInterface $expiration = null, $collectionName = 'default', $conversionName = '') {
        $expiration = $expiration ?: c::now()->addMinutes(CF::config('resource.temporary_url_default_lifetime'));

        return $this->getResourceItemTemporaryUrl($expiration, $collectionName, $conversionName, CResources_Enum_CollectionPosition::LAST);
    }

    public function getRegisteredResourceCollections() {
        $this->reregisterResourceCollections();

        return c::collect($this->resourceCollections);
    }

    /**
     * Get the resource collection by its collectionName.
     *
     * @return null|CResources_ResourceCollection
     */
    public function getResourceCollection(string $collectionName = 'default') {
        $this->reregisterResourceCollections();

        return c::collect($this->resourceCollections)
            ->first(fn (CResources_ResourceCollection $collection) => $collection->name === $collectionName);
    }

    /**
     * registerResourceCollections() is a model's override hook that (re)declares
     * its full, current set of collections via addResourceCollection() - each
     * call is meant to fully replace the set, not add to it. Every accessor
     * above calls this on every access, so resetting first keeps a long-lived
     * instance (a queue worker's memoized "current user", a daemon looping
     * over many models) from growing $resourceCollections by one duplicate
     * entry per call for the rest of the process's life.
     *
     * @return void
     */
    protected function reregisterResourceCollections() {
        $this->resourceCollections = [];
        $this->registerResourceCollections();
    }

    public function getFallbackResourceUrl(string $collectionName = 'default', string $conversionName = ''): string {
        $fallbackUrls = c::optional($this->getResourceCollection($collectionName))->fallbackUrls;

        if (in_array($conversionName, ['', 'default'], true)) {
            return $fallbackUrls['default'] ?? '';
        }

        return $fallbackUrls[$conversionName] ?? $fallbackUrls['default'] ?? '';
    }

    public function getFallbackResourcePath(string $collectionName = 'default', string $conversionName = ''): string {
        $fallbackPaths = c::optional($this->getResourceCollection($collectionName))->fallbackPaths;

        if (in_array($conversionName, ['', 'default'], true)) {
            return $fallbackPaths['default'] ?? '';
        }

        return $fallbackPaths[$conversionName] ?? $fallbackPaths['default'] ?? '';
    }

    private function getResourceItemPath(string $collectionName, string $conversionName, string $position): string {
        $media = $position === CResources_Enum_CollectionPosition::FIRST
            ? $this->getFirstResource($collectionName)
            : $this->getLastResource($collectionName);

        if (!$media) {
            return $this->getFallbackResourcePath($collectionName, $conversionName) ?: '';
        }

        if ($conversionName !== '' && !$media->hasGeneratedConversion($conversionName)) {
            return $media->getPath();
        }

        return $media->getPath($conversionName);
    }

    /**
     * Get the url of the image for the given conversionName
     * for first resource for the given collectionName.
     * If no profile is given, return the source's url.
     *
     * @param mixed $collectionName
     * @param mixed $conversionName
     */
    public function getFirstResourcePath($collectionName = 'default', $conversionName = '') {
        return $this->getResourceItemPath($collectionName, $conversionName, CResources_Enum_CollectionPosition::FIRST);
    }

    /**
     * Get the url of the image for the given conversionName
     * for last resource for the given collectionName.
     * If no profile is given, return the source's url.
     *
     * @param mixed $collectionName
     * @param mixed $conversionName
     */
    public function getLastResourcePath($collectionName = 'default', $conversionName = '') {
        return $this->getResourceItemPath($collectionName, $conversionName, CResources_Enum_CollectionPosition::LAST);
    }

    /**
     * Update a resource collection by deleting and inserting again with new values.
     *
     * @param string $collectionName
     *
     * @throws \CResources_Exception_ResourceCannotBeUpdated
     *
     * @return CCollection
     */
    public function updateResource(array $newResourceArray, $collectionName = 'default') {
        $this->removeResourceItemsNotPresentInArray($newResourceArray, $collectionName);
        $resourceClass = $this->getResourceModel();

        return c::collect($newResourceArray)
            ->map(function (array $newResourceItem) use ($collectionName, $resourceClass) {
                static $orderColumn = 1;
                $currentResource = $resourceClass::findOrFail($newResourceItem['id']);
                /** @var CModel_Resource_ResourceInterface|CModel $currentResource */
                if ($currentResource->collection_name !== $collectionName) {
                    throw CResources_Exception_ResourceCannotBeUpdated::doesNotBelongToCollection($collectionName, $currentResource);
                }
                if (array_key_exists('name', $newResourceItem)) {
                    $currentResource->name = $newResourceItem['name'];
                }
                if (array_key_exists('custom_properties', $newResourceItem)) {
                    $currentResource->custom_properties = $newResourceItem['custom_properties'];
                }
                $currentResource->order_column = $orderColumn++;
                $currentResource->save();

                return $currentResource;
            });
    }

    protected function removeResourceItemsNotPresentInArray(array $newResourceArray, $collectionName = 'default') {
        $this->getResource($collectionName)
            ->reject(function (CModel_Resource_ResourceInterface $currentResourceItem) use ($newResourceArray) {
                return in_array($currentResourceItem->getKey(), array_column($newResourceArray, $currentResourceItem->getKeyName()));
            })
            ->each->delete();
        if ($this->resourceIsPreloaded()) {
            unset($this->resource);
        }
    }

    /**
     * Remove all resource in the given collection.
     *
     * @param string $collectionName
     *
     * @return $this
     */
    public function clearResourceCollection($collectionName = 'default') {
        $this->getResource($collectionName)->each->delete();
        c::event(new CResources_Event_CollectionHasBeenCleared($this, $collectionName));
        if ($this->resourceIsPreloaded()) {
            unset($this->resource);
        }

        return $this;
    }

    /**
     * Remove all resource in the given collection except some.
     *
     * @param string                              $collectionName
     * @param \CModel_Resource_ResourceCollection $excludedResource
     *
     * @return $this
     */
    public function clearResourceCollectionExcept($collectionName = 'default', $excludedResource = []) {
        if ($excludedResource instanceof CModel_Resource_ResourceInterface) {
            $excludedResource = c::collect()->push($excludedResource);
        }
        $excludedResource = c::collect($excludedResource);

        if ($excludedResource->isEmpty()) {
            return $this->clearResourceCollection($collectionName);
        }

        $this->getResource($collectionName)
            ->reject(function (CModel_Resource_ResourceInterface $resource) use ($excludedResource) {
                return $excludedResource->where($resource->getKeyName(), $resource->getKey())->count();
            })
            ->each(function (CModel_Resource_ResourceInterface $resource) {
                $resource->delete();
            });
        if ($this->resourceIsPreloaded()) {
            unset($this->resource);
        }

        return $this;
    }

    /**
     * Delete the associated resource with the given id.
     * You may also pass a resource object.
     *
     * @param int|\Spatie\ResourceLibrary\Models\Resource $resourceId
     *
     * @throws \CResources_Exception_ResourceCannotBeDeleted
     */
    public function deleteResource($resourceId) {
        if ($resourceId instanceof CModel_Resource_ResourceInterface) {
            $resourceId = $resourceId->id;
        }
        $resource = $this->resource->find($resourceId);
        if (!$resource) {
            throw CResources_Exception_ResourceCannotBeDeleted::doesNotBelongToModel($resourceId, $this);
        }
        $resource->delete();
    }

    /**
     * Add a conversion.
     *
     * @param string $name
     *
     * @return CResources_Conversion
     */
    public function addResourceConversion($name) {
        $conversion = CResources_Conversion::create($name);
        $this->resourceConversions[] = $conversion;

        return $conversion;
    }

    public function addResourceCollection($name) {
        $resourceCollection = CResources_ResourceCollection::create($name);
        $this->resourceCollections[] = $resourceCollection;

        return $resourceCollection;
    }

    /**
     * Delete the model, but preserve all the associated resource.
     *
     * @return bool
     */
    public function deletePreservingResource() {
        $this->deletePreservingResource = true;

        return $this->delete();
    }

    /**
     * Determines if the resource files should be preserved when the resource object gets deleted.
     *
     * @return bool
     */
    public function shouldDeletePreservingResource() {
        return $this->deletePreservingResource ? $this->deletePreservingResource : false;
    }

    protected function resourceIsPreloaded() {
        return $this->relationLoaded('resource');
    }

    /**
     * Cache the resource on the object.
     *
     * @param string $collectionName
     *
     * @return mixed
     */
    public function loadResource($collectionName) {
        if (CF::config('resource.force_lazy_loading') && $this->exists) {
            $this->loadMissing('resource');
        }
        $collection = $this->exists ? $this->resource : c::collect($this->unAttachedResourceLibraryItems)->pluck('resource');

        $collection = new CModel_Resource_ResourceCollection($collection);
        $values = $collection
            ->filter(function (CModel_Resource_ResourceInterface $resourceItem) use ($collectionName) {
                if ($collectionName == '') {
                    return true;
                }

                return $resourceItem->collection_name === $collectionName;
            })
            ->sortBy('order_column')
            ->values();

        return $values;
    }

    public function prepareToAttachResource(CModel_Resource_ResourceInterface $resource, CModel_HasResource_FileAdder_FileAdder $fileAdder) {
        $this->unAttachedResourceLibraryItems[] = compact('resource', 'fileAdder');
    }

    public function processUnattachedResource(callable $callable) {
        foreach ($this->unAttachedResourceLibraryItems as $item) {
            $callable($item['resource'], $item['fileAdder']);
        }
        $this->unAttachedResourceLibraryItems = [];
    }

    /**
     * //@param string $file
     * //@param string ..$allowedMimeTypes.
     *
     * @throws type
     *
     * @return type
     */
    protected function guardAgainstInvalidMimeType() {
        $args = func_get_args();
        $file = carr::get($args, 0);
        $allowedMimeTypes = array_slice($args, 1);
        $allowedMimeTypes = carr::flatten($allowedMimeTypes);
        if (empty($allowedMimeTypes)) {
            return;
        }
        $validation = CValidation::factory()->make(
            ['file' => new CHTTP_File($file)],
            ['file' => 'mimetypes:' . implode(',', $allowedMimeTypes)]
        );
        if ($validation->fails()) {
            throw CResources_Exception_FileCannotBeAdded_MimeTypeNotAllowed::create($file, $allowedMimeTypes);
        }
    }

    public function registerResourceConversions(?CModel_Resource_ResourceInterface $resource = null) {
    }

    public function registerResourceCollections() {
    }

    public function registerAllResourceConversions(?CModel_Resource_ResourceInterface $resource = null) {
        $this->reregisterResourceCollections();
        c::collect($this->resourceCollections)->each(function (CResources_ResourceCollection $resourceCollection) use ($resource) {
            $actualResourceConversions = $this->resourceConversions;
            $this->resourceConversions = [];
            call_user_func_array($resourceCollection->resourceConversionRegistrations, [$resource]);

            $preparedResourceConversions = c::collect($this->resourceConversions)
                ->each(function (CResources_Conversion $conversion) use ($resourceCollection) {
                    $conversion->performOnCollections($resourceCollection->name);
                })
                ->values()
                ->toArray();
            $this->resourceConversions = array_merge($actualResourceConversions, $preparedResourceConversions);
        });
        $this->registerResourceConversions($resource);
    }
}
