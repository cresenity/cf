<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @method static width($width);
 * @method static height($height)
 * @method static quality($quality)
 */
class CResources_Conversion {
    /**
     * @var string
     */
    protected $name = '';

    /**
     * @var int
     */
    protected $extractVideoFrameAtSecond = 0;

    /**
     * @var CImage_Manipulations
     */
    protected $manipulations;

    /**
     * @var array
     */
    protected $performOnCollections = [];

    /**
     * @var bool
     */
    protected $performOnQueue = true;

    /**
     * @var bool
     */
    protected $keepOriginalImageFormat = false;

    /**
     * @var bool
     */
    protected $generateResponsiveImages = false;

    /**
     * @var null|string
     */
    protected $loadingAttributeValue;

    /**
     * @var int
     */
    protected $pdfPageNumber = 1;

    public function __construct($name) {
        $this->name = $name;
        $this->performOnQueue = (bool) (CF::config('resource.queue_conversions_by_default') ?? true);
        $this->manipulations = (new CImage_Manipulations())
            ->optimize(CF::config('resource.image_optimizers'))
            ->format('jpg');
    }

    public static function create($name) {
        return new static($name);
    }

    public function getName() {
        return $this->name;
    }

    /**
     * Set the timecode in seconds to extract a video thumbnail.
     * Only used on video media.
     *
     * @param mixed $timeCode
     */
    public function extractVideoFrameAtSecond($timeCode) {
        $this->extractVideoFrameAtSecond = $timeCode;

        return $this;
    }

    public function getExtractVideoFrameAtSecond() {
        return $this->extractVideoFrameAtSecond;
    }

    public function keepOriginalImageFormat() {
        $this->keepOriginalImageFormat = true;

        return $this;
    }

    public function shouldKeepOriginalImageFormat() {
        return $this->keepOriginalImageFormat;
    }

    /**
     * @return CImage_Manipulations
     */
    public function getManipulations() {
        return $this->manipulations;
    }

    public function removeManipulation($manipulationName) {
        $this->manipulations->removeManipulation($manipulationName);

        return $this;
    }

    public function withoutManipulations() {
        $this->manipulations = new CImage_Manipulations();

        return $this;
    }

    public function __call($name, $arguments) {
        if (!method_exists($this->manipulations, $name)) {
            throw new BadMethodCallException("Manipulation `{$name}` does not exist");
        }
        $this->manipulations->$name(...$arguments);

        return $this;
    }

    /**
     * Set the manipulations for this conversion.
     *
     * @param CImage_Manipulations|closure $manipulations
     *
     * @return $this
     */
    public function setManipulations($manipulations) {
        if ($manipulations instanceof CImage_Manipulations) {
            $this->manipulations = $this->manipulations->mergeManipulations($manipulations);
        }
        if (is_callable($manipulations)) {
            $manipulations($this->manipulations);
        }

        return $this;
    }

    /**
     * Add the given manipulations as the first ones.
     *
     * @param \CImage_Manipulations $manipulations
     *
     * @return $this
     */
    public function addAsFirstManipulations(CImage_Manipulations $manipulations) {
        $manipulationSequence = $manipulations->getManipulationSequence()->toArray();
        $this->manipulations
            ->getManipulationSequence()
            ->mergeArray($manipulationSequence);

        return $this;
    }

    /**
     * Set the collection names on which this conversion must be performed.
     *
     * @param $collectionNames
     *
     * @return $this
     */
    public function performOnCollections(...$collectionNames) {
        $this->performOnCollections = $collectionNames;

        return $this;
    }

    /**
     * Determine if this conversion should be performed on the given
     * collection.
     *
     * @param mixed $collectionName
     */
    public function shouldBePerformedOn($collectionName) {
        //if no collections were specified, perform conversion on all collections
        if (!count($this->performOnCollections)) {
            return true;
        }
        if (in_array('*', $this->performOnCollections)) {
            return true;
        }

        return in_array($collectionName, $this->performOnCollections);
    }

    /**
     * Mark this conversion as one that should be queued.
     *
     * @return $this
     */
    public function queued() {
        $this->performOnQueue = true;

        return $this;
    }

    /**
     * Mark this conversion as one that should not be queued.
     *
     * @return $this
     */
    public function nonQueued() {
        $this->performOnQueue = false;

        return $this;
    }

    /**
     * Avoid optimization of the converted image.
     *
     * @return $this
     */
    public function nonOptimized() {
        $this->removeManipulation('optimize');

        return $this;
    }

    /**
     * When creating the converted image, responsive images will be created as well.
     */
    public function withResponsiveImages() {
        $this->generateResponsiveImages = true;

        return $this;
    }

    /**
     * Determine if responsive images should be created for this conversion.
     */
    public function shouldGenerateResponsiveImages() {
        return $this->generateResponsiveImages;
    }

    /**
     * Determine if the conversion should be queued.
     */
    public function shouldBeQueued() {
        return $this->performOnQueue;
    }

    /**
     * Get the extension that the result of this conversion must have.
     *
     * @param mixed $originalFileExtension
     */
    public function getResultExtension($originalFileExtension = '') {
        if ($this->shouldKeepOriginalImageFormat()) {
            if (in_array($originalFileExtension, ['jpg', 'pjpg', 'png', 'gif'])) {
                return $originalFileExtension;
            }
        }
        if ($manipulationArgument = $this->manipulations->getManipulationArgument('format')) {
            return $manipulationArgument;
        }

        return $originalFileExtension;
    }

    public function getConversionFile($file) {
        $fileName = pathinfo($file, PATHINFO_FILENAME);
        $extension = $this->getResultExtension();
        if (!$extension) {
            $extension = pathinfo($file, PATHINFO_EXTENSION);
        }

        return "{$fileName}-{$this->getName()}.{$extension}";
    }

    /**
     * @param string $value
     *
     * @return self
     */
    public function useLoadingAttributeValue($value) {
        $this->loadingAttributeValue = $value;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getLoadingAttributeValue() {
        return $this->loadingAttributeValue;
    }

    /**
     * @param int $pageNumber
     *
     * @return self
     */
    public function pdfPageNumber($pageNumber) {
        $this->pdfPageNumber = $pageNumber;

        return $this;
    }

    /**
     * @return int
     */
    public function getPdfPageNumber() {
        return $this->pdfPageNumber;
    }
}
