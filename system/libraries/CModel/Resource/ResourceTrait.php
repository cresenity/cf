<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @property      string     $uuid
 * @property      string     $model_type
 * @property      string|int $model_id
 * @property      string     $collection_name
 * @property      string     $name
 * @property      string     $file_name
 * @property      string     $mime_type
 * @property      string     $disk
 * @property      string     $conversions_disk
 * @property      string     $type
 * @property      string     $extension
 * @property-read string     $humanReadableSize
 * @property-read string     $preview_url
 * @property-read string     $original_url
 * @property      int        $size
 * @property      ?int       $order_column
 * @property      array      $manipulations
 * @property      array      $custom_properties
 * @property      array      $generated_conversions
 * @property      array      $responsive_images
 * @property-read ?\CCarbon  $created_at
 * @property-read ?\CCarbon  $updated_at
 */
trait CModel_Resource_ResourceTrait {
    use CModel_Resource_Concern_CustomResourcePropertiesTrait;
    use CModel_Resource_Concern_IsSortedTrait;

    protected int $streamChunkSize = (1024 * 1024); // default to 1MB chunks.

    public static function bootResourceTrait() {
        static::observe(new CModel_Resource_ResourceObserver());
    }

    /**
     * Every resource model stores its conversion flags as an array, whichever model class an app uses.
     *
     * @return void
     */
    public function initializeResourceTrait() {
        $this->mergeCasts(['generated_conversions' => 'array']);
    }

    public function model() {
        return $this->morphTo();
    }

    /**
     * Get the full url to a original media file.
     *
     * @param mixed $conversionName
     */
    public function getFullUrl($conversionName = '') {
        return c::url($this->getUrl($conversionName));
    }

    /**
     * Get the url to a original media file.
     *
     * @param mixed $conversionName
     */
    public function getUrl($conversionName = '') {
        $urlGenerator = $this->getUrlGenerator($conversionName);

        return $urlGenerator->getUrl();
    }

    public function getTemporaryUrl(DateTimeInterface $expiration, $conversionName = '', array $options = []) {
        $urlGenerator = $this->getUrlGenerator($conversionName);
        return $urlGenerator->getTemporaryUrl($expiration, $options);
    }

    /**
     * Get the path to the original media file.
     *
     * @param mixed $conversionName
     */
    public function getPath($conversionName = '') {
        $urlGenerator = $this->getUrlGenerator($conversionName);

        return $urlGenerator->getPath();
    }

    public function getPathRelativeToRoot(string $conversionName = ''): string {
        return $this->getUrlGenerator($conversionName)->getPathRelativeToRoot();
    }

    /**
     * @param string $conversionName
     *
     * @return CResources_UrlGeneratorAbstract
     */
    public function getUrlGenerator($conversionName) {
        return CResources_Factory::createUrlGeneratorForResource($this, $conversionName);
    }

    /**
     * @param array $conversionNames
     *
     * @return string
     */
    public function getAvailableUrl($conversionNames) {
        foreach ($conversionNames as $conversionName) {
            if (!$this->hasGeneratedConversion($conversionName)) {
                continue;
            }

            return $this->getUrl($conversionName);
        }

        return $this->getUrl();
    }

    public function getDownloadFilename(): string {
        return $this->file_name;
    }

    /**
     * Url of the original file, exposed as the `original_url` attribute.
     *
     * @return string
     */
    public function getOriginalUrlAttribute() {
        return $this->getFullUrl();
    }

    /**
     * Url of the `preview` conversion when it exists, exposed as the `preview_url` attribute.
     *
     * @return string
     */
    public function getPreviewUrlAttribute() {
        return $this->hasGeneratedConversion('preview') ? $this->getFullUrl('preview') : '';
    }

    /**
     * @param array $conversionNames
     *
     * @return string
     */
    public function getAvailableFullUrl($conversionNames) {
        foreach ($conversionNames as $conversionName) {
            if (!$this->hasGeneratedConversion($conversionName)) {
                continue;
            }

            return $this->getFullUrl($conversionName);
        }

        return $this->getFullUrl();
    }

    /**
     * @param array $conversionNames
     *
     * @return string
     */
    public function getAvailablePath($conversionNames) {
        foreach ($conversionNames as $conversionName) {
            if (!$this->hasGeneratedConversion($conversionName)) {
                continue;
            }

            return $this->getPath($conversionName);
        }

        return $this->getPath();
    }

    /**
     * @return CModel_Casts_Attribute
     */
    protected function type() {
        return CModel_Casts_Attribute::get(
            function () {
                $type = $this->getTypeFromExtension();

                if ($type !== CModel_Resource::TYPE_OTHER) {
                    return $type;
                }

                return $this->getTypeFromMime();
            }
        );
    }

    public function getTypeFromExtension() {
        $imageGenerator = $this->getImageGenerators()
            ->map(function ($className) {
                return new $className();
            })
            ->first->canHandleExtension(strtolower($this->extension));

        return $imageGenerator ? $imageGenerator->getType() : CModel_Resource::TYPE_OTHER;
    }

    public function getTypeFromMime() {
        $imageGenerator = $this->getImageGenerators()
            ->map(function ($className) {
                return new $className();
            })
            ->first->canHandleMime($this->mime_type);

        return $imageGenerator ? $imageGenerator->getType() : CModel_Resource::TYPE_OTHER;
    }

    protected function extension(): CModel_Casts_Attribute {
        return CModel_Casts_Attribute::get(function () {
            return pathinfo($this->file_name, PATHINFO_EXTENSION);
        });
    }

    protected function humanReadableSize(): CModel_Casts_Attribute {
        return CModel_Casts_Attribute::get(function () {
            return CResources_Helpers_File::getHumanReadableSize($this->size);
        });
    }

    public function getContent($conversionName = '') {
        $disk = CStorage::instance()->disk($this->disk);

        return $disk->get($this->getPath($conversionName));
    }

    public function getImageGenerators() {
        return c::collect(CF::config('resource.image_generators'));
    }

    public function getTypeAttribute() {
        $type = $this->getTypeFromExtension();
        if ($type !== CModel_Resource::TYPE_OTHER) {
            return $type;
        }

        return $this->getTypeFromMime();
    }

    public function getExtensionAttribute() {
        return pathinfo($this->file_name, PATHINFO_EXTENSION);
    }

    public function getHumanReadableSizeAttribute() {
        return CResources_Helpers_File::getHumanReadableSize($this->size);
    }

    public function getDiskDriverName() {
        if (strlen($this->disk) == 0) {
            return 'local';
        }

        return strtolower(CF::config("storage.disks.{$this->disk}.driver"));
    }

    public function getConversionsDiskDriverName(): string {
        $diskName = $this->conversions_disk ?? $this->disk;

        return strtolower(CF::config("storage.disks.{$diskName}.driver"));
    }

    /**
     * Determine if the media item has a custom property with the given name.
     *
     * @param mixed $propertyName
     */
    public function hasCustomProperty($propertyName) {
        return carr::has($this->custom_properties, $propertyName);
    }

    /**
     * Get the value of custom property with the given name.
     *
     * @param string $propertyName
     * @param mixed  $default
     *
     * @return mixed
     */
    public function getCustomProperty($propertyName, $default = null) {
        return carr::get($this->custom_properties, $propertyName, $default);
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return $this
     */
    public function setCustomProperty($name, $value) {
        $customProperties = $this->custom_properties;
        carr::set($customProperties, $name, $value);
        $this->custom_properties = $customProperties;

        return $this;
    }

    public function forgetCustomProperty($name) {
        $customProperties = $this->custom_properties;
        carr::forget($customProperties, $name);
        $this->custom_properties = $customProperties;

        return $this;
    }

    /**
     * Get all the names of the registered resource conversions.
     */
    public function getResourceConversionNames() {
        $conversions = CResources_ConversionCollection::createForResource($this);

        return $conversions->map(function (CResources_Conversion $conversion) {
            return $conversion->getName();
        })->toArray();
    }

    public function hasGeneratedConversion($conversionName) {
        $generatedConversions = $this->getGeneratedConversions();

        return isset($generatedConversions[$conversionName]) ? $generatedConversions[$conversionName] : false;
    }

    public function markAsConversionGenerated($conversionName, $generated) {
        if ($this->usesGeneratedConversionsAttribute()) {
            $generatedConversions = $this->getGeneratedConversions()->all();
            $generatedConversions[$conversionName] = $generated;
            $this->generated_conversions = $generatedConversions;
        } else {
            $this->setCustomProperty("generated_conversions.{$conversionName}", $generated);
        }
        $this->save();

        return $this;
    }

    /**
     * Flag a conversion as missing again, e.g. before regenerating it or after it failed.
     *
     * @param string $conversionName
     *
     * @return $this
     */
    public function markAsConversionNotGenerated($conversionName) {
        return $this->markAsConversionGenerated($conversionName, false);
    }

    public function getGeneratedConversions() {
        if ($this->usesGeneratedConversionsAttribute()) {
            $fromColumn = $this->generated_conversions;
            if (is_string($fromColumn)) {
                $fromColumn = json_decode($fromColumn, true);
            }
            if (!empty($fromColumn)) {
                return c::collect($fromColumn);
            }
        }

        return c::collect($this->getCustomProperty('generated_conversions', []));
    }

    /**
     * True when this row carries a generated_conversions attribute (the table has the column);
     * decided from the loaded attributes so no schema query is needed on the read path.
     *
     * @return bool
     */
    protected function usesGeneratedConversionsAttribute() {
        /** @var CModel $this */
        return array_key_exists('generated_conversions', $this->getAttributes());
    }

    /**
     * Create an HTTP response that represents the object.
     *
     * @param CHTTP_Request $request
     *
     * @return CHTTP_Response
     */
    public function toResponse($request) {
        $downloadHeaders = [
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Content-Type' => $this->mime_type,
            'Content-Length' => $this->size,
            'Content-Disposition' => 'attachment; filename="' . $this->file_name . '"',
            'Pragma' => 'public',
        ];

        return c::response()->stream(function () {
            $stream = $this->stream();
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, $downloadHeaders);
    }

    public function getResponsiveImageUrls($conversionName = '') {
        return $this->responsiveImages($conversionName)->getUrls();
    }

    public function hasResponsiveImages($conversionName = '') {
        return count($this->getResponsiveImageUrls($conversionName)) > 0;
    }

    public function getSrcset($conversionName = '') {
        return $this->responsiveImages($conversionName)->getSrcset();
    }

    protected function previewUrl(): CModel_Casts_Attribute {
        return CModel_Casts_Attribute::get(function () {
            return $this->hasGeneratedConversion('preview') ? $this->getUrl('preview') : '';
        });
    }

    protected function originalUrl(): CModel_Casts_Attribute {
        return CModel_Casts_Attribute::get(function () {
            return $this->getUrl();
        });
    }

    public function move(CModel_HasResourceInterface $model, $collectionName = 'default') {
        $newMedia = $this->copy($model, $collectionName);
        $this->delete();

        return $newMedia;
    }

    public function copy(
        CModel_HasResourceInterface $model,
        $collectionName = 'default',
        $diskName = '',
        $fileName = '',
        $fileAdderCallback = null
    ) {
        $temporaryDirectory = CResources_Helpers_TemporaryDirectory::create();
        $temporaryFile = $temporaryDirectory->path($this->file_name);
        $filesystem = CResources_Factory::createFileSystem();
        $filesystem->copyFromResourceLibrary($this, $temporaryFile);
        $fileAdder = $model
            ->addResource($temporaryFile)
            ->usingName($this->name)
            ->setOrder($this->order_column)
            ->withManipulations($this->manipulations)
            ->withCustomProperties($this->custom_properties);
        if ($fileName !== '') {
            $fileAdder->usingFileName($fileName);
        }
        if ($fileAdderCallback instanceof Closure) {
            $fileAdder = $fileAdderCallback($fileAdder);
        }
        $newMedia = $fileAdder
            ->toResourceCollection($collectionName, $diskName);
        $temporaryDirectory->delete();

        return $newMedia;
    }

    public function responsiveImages($conversionName = '') {
        return new CResources_ResponsiveImage_RegisteredResponsiveImage($this, $conversionName);
    }

    public function stream() {
        $filesystem = CResources_Factory::createFileSystem();

        return $filesystem->getStream($this);
    }

    public function toHtml() {
        return $this->img()->toHtml();
    }

    /**
     * @param string $conversionName
     * @param array  $extraAttributes
     *
     * @return CResources_HtmlableMedia
     */
    public function img($conversionName = '', array $extraAttributes = []) {
        return (new CResources_HtmlableMedia($this))
            ->conversion($conversionName)
            ->attributes($extraAttributes);
    }

    public function __invoke(...$arguments) {
        return new CBase_HtmlString($this->img(...$arguments));
    }

    public function regenerateConversion($only = [], $onlyMissing = true) {
        $fileManipulator = CResources_Factory::createFileManipulator();
        $fileManipulator->createDerivedFiles($this, $only, $onlyMissing);
    }

    public function withImage(callable $call) {
        $resourceFileSystem = CResources_Factory::createFileSystem();
        $temporaryDirectoryPath = CResources_Helpers_TemporaryDirectory::generateLocalFilePath($this->getExtensionAttribute());
        $copiedOriginalFile = $resourceFileSystem->copyFromResourceLibrary(
            $this,
            $temporaryDirectoryPath
        );

        $image = new CImage_Image($copiedOriginalFile);

        $call($image);

        CResources_Helpers_TemporaryDirectory::delete($temporaryDirectoryPath);
    }

    public function mailAttachment(string $conversion = ''): CEmail_Attachment {
        $attachment = CEmail_Attachment::fromStorageDisk($this->disk, $this->getPathRelativeToRoot($conversion))->as($this->file_name);

        if ($this->mime_type) {
            $attachment->withMime($this->mime_type);
        }

        return $attachment;
    }

    public function toMailAttachment(): CEmail_Attachment {
        return $this->mailAttachment();
    }

    protected function saveOrTouch(): bool {
        if (!$this->exists || $this->isDirty()) {
            return $this->save();
        }

        return $this->touch();
    }

    public function hasVersionColumn() {
        /** @var CModel $this */
        return $this->getConnection()->getSchemaBuilder()->hasColumn($this->getTable(), 'version');
    }

    /**
     * Whether the resource table has a conversions_disk column (checked once per table).
     *
     * @return bool
     */
    public function hasConversionsDiskColumn() {
        return $this->hasResourceColumn('conversions_disk');
    }

    /**
     * Whether the resource table has a generated_conversions column (checked once per table).
     *
     * @return bool
     */
    public function hasGeneratedConversionsColumn() {
        return $this->hasResourceColumn('generated_conversions');
    }

    /**
     * Column presence on the resource table, checked once per connection.table.column.
     *
     * @param string $column
     *
     * @return bool
     */
    protected function hasResourceColumn($column) {
        static $columns = [];
        /** @var CModel $this */
        $key = $this->getConnectionName() . '.' . $this->getTable() . '.' . $column;
        if (!array_key_exists($key, $columns)) {
            $columns[$key] = $this->getConnection()->getSchemaBuilder()->hasColumn($this->getTable(), $column);
        }

        return $columns[$key];
    }
}
