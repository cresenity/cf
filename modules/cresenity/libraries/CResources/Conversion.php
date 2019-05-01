<?php

defined('SYSPATH') OR die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @since May 2, 2019, 2:28:30 AM
 * @license Ittron Global Teknologi <ittron.co.id>
 */

/** @mixin \Spatie\Image\Manipulations */
class CResources_Conversion {

    /** @var string */
    protected $name = '';

    /** @var int */
    protected $extractVideoFrameAtSecond = 0;

    /** @var \Spatie\Image\Manipulations */
    protected $manipulations;

    /** @var array */
    protected $performOnCollections = [];

    /** @var bool */
    protected $performOnQueue = true;

    /** @var bool */
    protected $keepOriginalImageFormat = false;

    /** @var bool */
    protected $generateResponsiveImages = false;

    public function __construct($name) {
        $this->name = $name;
        $this->manipulations = (new Manipulations())
                ->optimize(config('medialibrary.image_optimizers'))
                ->format('jpg');
    }

    public static function create($name) {
        return new static($name);
    }

    public function getName() {
        return $this->name;
    }

    /*
     * Set the timecode in seconds to extract a video thumbnail.
     * Only used on video media.
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

    public function getManipulations() {
        return $this->manipulations;
    }

    public function removeManipulation($manipulationName) {
        $this->manipulations->removeManipulation($manipulationName);
        return $this;
    }

    public function withoutManipulations() {
        $this->manipulations = new Manipulations();
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
     * @param \Spatie\Image\Manipulations|closure $manipulations
     *
     * @return $this
     */
    public function setManipulations($manipulations) {
        if ($manipulations instanceof Manipulations) {
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
     * @param \Spatie\Image\Manipulations $manipulations
     *
     * @return $this
     */
    public function addAsFirstManipulations(Manipulations $manipulations) {
        $manipulationSequence = $manipulations->getManipulationSequence()->toArray();
        $this->manipulations
                ->getManipulationSequence()
                ->mergeArray($manipulationSequence);
        return $this;
    }

    /**
     * Set the collection names on which this conversion must be performed.
     *
     * @param  $collectionNames
     *
     * @return $this
     */
    public function performOnCollections(...$collectionNames) {
        $this->performOnCollections = $collectionNames;
        return $this;
    }

    /*
     * Determine if this conversion should be performed on the given
     * collection.
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

    /*
     * Determine if the conversion should be queued.
     */

    public function shouldBeQueued() {
        return $this->performOnQueue;
    }

    /*
     * Get the extension that the result of this conversion must have.
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

}
