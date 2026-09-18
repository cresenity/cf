<?php

class CResources_ResponsiveImage_Generator {
    const DEFAULT_CONVERSION_QUALITY = 90;

    /**
     * @var CResources_FileNamerAbstract
     */
    protected $fileNamer;

    /**
     * @var CResources_FileSystem
     */
    protected $filesystem;

    /**
     * @var CResources_ResponsiveImage_WidthCalculatorInterface
     */
    protected $widthCalculator;

    /**
     * @var CResources_ResponsiveImage_TinyPlaceholderGeneratorInterface
     */
    protected $tinyPlaceholderGenerator;

    public function __construct(
        CResources_ResponsiveImage_WidthCalculatorInterface $widthCalculator,
        CResources_ResponsiveImage_TinyPlaceholderGeneratorInterface $tinyPlaceholderGenerator
    ) {
        $this->filesystem = CResources_Factory::createFileSystem();
        $this->fileNamer = c::container(CF::config('resource.file_namer'));
        $this->widthCalculator = $widthCalculator;
        $this->tinyPlaceholderGenerator = $tinyPlaceholderGenerator;
    }

    /**
     * @param CModel_Resource_ResourceInterface $resource
     *
     * @return void
     */
    public function generateResponsiveImages(CModel_Resource_ResourceInterface $resource) {
        $temporaryDirectory = CResources_Helpers_TemporaryDirectory::create();

        $baseImage = $this->filesystem->copyFromResourceLibrary(
            $resource,
            $temporaryDirectory->path(cstr::random(16) . '.' . $resource->extension)
        );

        $resource = $this->cleanResponsiveImages($resource);

        foreach ($this->widthCalculator->calculateWidthsFromFile($baseImage) as $width) {
            $this->generateResponsiveImage($resource, $baseImage, 'resource_original', $width, $temporaryDirectory);
        }

        c::event(new CResources_Event_ResponsiveImage_ResponsiveImageGenerated($resource));

        $this->generateTinyJpg($resource, $baseImage, 'resource_original', $temporaryDirectory);
        $temporaryDirectory->delete();
    }

    /**
     * @param CModel_Resource_ResourceInterface $resource
     * @param CResources_Conversion             $conversion
     * @param string                            $baseImage
     *
     * @return void
     */
    public function generateResponsiveImagesForConversion(CModel_Resource_ResourceInterface $resource, CResources_Conversion $conversion, string $baseImage): void {
        $temporaryDirectory = CResources_Helpers_TemporaryDirectory::create();

        $resource = $this->cleanResponsiveImages($resource, $conversion->getName());

        foreach ($this->widthCalculator->calculateWidthsFromFile($baseImage) as $width) {
            $this->generateResponsiveImage($resource, $baseImage, $conversion->getName(), $width, $temporaryDirectory, $this->getConversionQuality($conversion));
        }

        $this->generateTinyJpg($resource, $baseImage, $conversion->getName(), $temporaryDirectory);

        $temporaryDirectory->delete();
    }

    /**
     * @param CResources_Conversion $conversion
     *
     * @return int
     */
    private function getConversionQuality(CResources_Conversion $conversion) {
        return $conversion->getManipulations()->getManipulationArgument('quality') ?: self::DEFAULT_CONVERSION_QUALITY;
    }

    /**
     * @param CModel_Resource_ResourceInterface $resource
     * @param string                            $baseImage
     * @param string                            $conversionName
     * @param int                               $targetWidth
     * @param CTemporary_CustomDirectory        $temporaryDirectory
     * @param int                               $conversionQuality
     *
     * @return void
     */
    public function generateResponsiveImage(
        CModel_Resource_ResourceInterface $resource,
        $baseImage,
        $conversionName,
        $targetWidth,
        CTemporary_CustomDirectory $temporaryDirectory,
        $conversionQuality = self::DEFAULT_CONVERSION_QUALITY
    ) {
        $extension = $this->fileNamer->extensionFromBaseImage($baseImage);
        $responsiveImagePath = $this->fileNamer->temporaryFileName($resource, $extension);
        $tempDestination = $temporaryDirectory->path($responsiveImagePath);

        CResources_Helpers_ImageFactory::load($baseImage)
            ->optimize()
            ->width($targetWidth)
            ->quality($conversionQuality)
            ->save($tempDestination);

        $responsiveImageHeight = CResources_Helpers_ImageFactory::load($tempDestination)->getHeight();

        // Users can customize the name like they want, but we expect the last part in a certain format
        $fileName = $this->addPropertiesToFileName(
            $responsiveImagePath,
            $conversionName,
            $targetWidth,
            $responsiveImageHeight,
            $extension
        );

        $responsiveImagePath = $temporaryDirectory->path($fileName);

        rename($tempDestination, $responsiveImagePath);

        $this->filesystem->copyToResourceLibrary($responsiveImagePath, $resource, 'responsiveImages');

        CResources_ResponsiveImage::register($resource, $fileName, $conversionName);
    }

    /**
     * @param CModel_Resource_ResourceInterface $resource
     * @param string                            $originalImagePath
     * @param string                            $conversionName
     * @param CTemporary_CustomDirectory        $temporaryDirectory
     *
     * @return void
     */
    public function generateTinyJpg(
        CModel_Resource_ResourceInterface $resource,
        $originalImagePath,
        $conversionName,
        CTemporary_CustomDirectory $temporaryDirectory
    ): void {
        $tempDestination = $temporaryDirectory->path('tiny.jpg');
        $this->tinyPlaceholderGenerator->generateTinyPlaceholder($originalImagePath, $tempDestination);

        $this->guardAgainstInvalidTinyPlaceHolder($tempDestination);

        $tinyImageDataBase64 = base64_encode(file_get_contents($tempDestination));

        $tinyImageBase64 = 'data:image/jpeg;base64,' . $tinyImageDataBase64;

        $originalImage = CResources_Helpers_ImageFactory::load($originalImagePath);

        $originalImageWidth = $originalImage->getWidth();

        $originalImageHeight = $originalImage->getHeight();

        $svg = c::view('cresenity.resource.placeholder-svg', compact(
            'originalImageWidth',
            'originalImageHeight',
            'tinyImageBase64'
        ));

        $base64Svg = 'data:image/svg+xml;base64,' . base64_encode($svg);

        CResources_ResponsiveImage::registerTinySvg($resource, $base64Svg, $conversionName);
    }

    /**
     * @param string      $filePath
     * @param string      $suffix
     * @param null|string $extensionFilePath
     *
     * @return string
     */
    protected function appendToFileName($filePath, $suffix, $extensionFilePath = null) {
        $baseName = pathinfo($filePath, PATHINFO_FILENAME);

        $extension = pathinfo($extensionFilePath ?? $filePath, PATHINFO_EXTENSION);

        return "{$baseName}{$suffix}.{$extension}";
    }

    /**
     * @param string $tinyPlaceholderPath
     *
     * @return void
     */
    protected function guardAgainstInvalidTinyPlaceHolder($tinyPlaceholderPath) {
        if (!file_exists($tinyPlaceholderPath)) {
            throw CResources_Exception_InvalidTinyJpgException::doesNotExist($tinyPlaceholderPath);
        }

        if (CResources_Helpers_File::getMimeType($tinyPlaceholderPath) !== 'image/jpeg') {
            throw CResources_Exception_InvalidTinyJpgException::hasWrongMimeType($tinyPlaceholderPath);
        }
    }

    /**
     * @param CModel_Resource_ResourceInterface $resource
     * @param string                            $conversionName
     *
     * @return CModel_Resource_ResourceInterface
     */
    protected function cleanResponsiveImages(CModel_Resource_ResourceInterface $resource, $conversionName = 'resource_original') {
        $responsiveImages = $resource->responsive_images;
        $responsiveImages[$conversionName]['urls'] = [];
        $resource->responsive_images = $responsiveImages;

        $this->filesystem->removeResponsiveImages($resource, $conversionName);

        return $resource;
    }

    /**
     * @param string $fileName
     * @param string $conversionName
     * @param int    $width
     * @param int    $height
     * @param string $extension
     *
     * @return string
     */
    protected function addPropertiesToFileName($fileName, $conversionName, $width, $height, $extension) {
        $fileName = pathinfo($fileName, PATHINFO_FILENAME);

        return "{$fileName}___{$conversionName}_{$width}_{$height}.{$extension}";
    }
}
