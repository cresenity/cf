<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Jul 21, 2019, 3:39:27 AM
 */
trait CModel_Resource_ResourceTrait {
    use CModel_Resource_Concern_CustomResourcePropertiesTrait;
    use CModel_Resource_Concern_IsSortedTrait;

    public static function bootResourceTrait() {
        static::observe(new CModel_Resource_ResourceObserver());
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
        $this->setCustomProperty("generated_conversions.{$conversionName}", $generated);
        $this->save();

        return $this;
    }

    public function getGeneratedConversions() {
        return c::collect($this->getCustomProperty('generated_conversions', []));
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

    public function move(CModel_HasResourceInterface $model, $collectionName = 'default') {
        $newMedia = $this->copy($model, $collectionName);
        $this->delete();

        return $newMedia;
    }

    public function copy(CModel_HasResourceInterface $model, $collectionName = 'default') {
        $temporaryDirectory = CResources_Helpers_TemporaryDirectory::create();
        $temporaryFile = $temporaryDirectory->path($this->file_name);
        CResources_Factory::createFileSystem()->copyFromResourceLibrary($this, $temporaryFile);
        $newMedia = $model
            ->addResource($temporaryFile)
            ->usingName($this->name)
            ->withCustomProperties($this->custom_properties)
            ->toResourceCollection($collectionName);
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
}
