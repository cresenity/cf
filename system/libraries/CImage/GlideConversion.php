<?php

use League\Glide\Server;
use League\Glide\ServerFactory;

final class CImage_GlideConversion {
    /**
     * @var string
     */
    private $inputImage;

    /**
     * @var string
     */
    private $imageDriver = 'gd';

    /**
     * @var string
     */
    private $conversionResult = null;

    /**
     * @var string
     */
    private $temporaryDirectory = null;

    /**
     * @param string $inputImage
     *
     * @return \CImage_GlideConversion
     */
    public static function create($inputImage) {
        return new self($inputImage);
    }

    public function setTemporaryDirectory($temporaryDirectory) {
        if (!isset($temporaryDirectory)) {
            return $this;
        }
        if (!is_dir($temporaryDirectory)) {
            try {
                mkdir($temporaryDirectory);
            } catch (Exception $exception) {
                throw CImage_Exception_InvalidTemporaryDirectoryException::temporaryDirectoryNotCreatable($temporaryDirectory);
            }
        }
        if (!is_writable($temporaryDirectory)) {
            throw CImage_Exception_InvalidTemporaryDirectoryException::temporaryDirectoryNotWritable($temporaryDirectory);
        }
        $this->temporaryDirectory = $temporaryDirectory;

        return $this;
    }

    public function getTemporaryDirectory() {
        return $this->temporaryDirectory;
    }

    public function __construct($inputImage) {
        $this->temporaryDirectory = sys_get_temp_dir();
        $this->inputImage = $inputImage;
    }

    public function useImageDriver($imageDriver) {
        $this->imageDriver = $imageDriver;

        return $this;
    }

    public function performManipulations(CImage_Manipulations $manipulations) {
        foreach ($manipulations->getManipulationSequence() as $manipulationGroup) {
            $inputFile = $this->conversionResult == null ? $this->inputImage : $this->conversionResult;
            $watermarkPath = $this->extractWatermarkPath($manipulationGroup);
            $glideServer = $this->createGlideServer($inputFile, $watermarkPath);
            $glideServer->setGroupCacheInFolders(false);
            $manipulatedImage = $this->temporaryDirectory . DIRECTORY_SEPARATOR . $glideServer->makeImage(
                pathinfo($inputFile, PATHINFO_BASENAME),
                $this->prepareManipulations($manipulationGroup)
            );
            if ($this->conversionResult) {
                unlink($this->conversionResult);
            }
            $this->conversionResult = $manipulatedImage;
        }

        return $this;
    }

    /**
     * Removes the watermark path from the manipulationGroup and returns it. This way it can be injected into the Glide
     * server as the `watermarks` path.
     *
     * @param $manipulationGroup
     *
     * @return null|string
     */
    private function extractWatermarkPath(&$manipulationGroup) {
        if (array_key_exists('watermark', $manipulationGroup)) {
            $watermarkPath = dirname($manipulationGroup['watermark']);
            $manipulationGroup['watermark'] = basename($manipulationGroup['watermark']);

            return $watermarkPath;
        }
    }

    /**
     * @param string $inputFile
     * @param string $watermarkPath
     *
     * @return \League\Glide\Server
     */
    private function createGlideServer($inputFile, $watermarkPath = null) {
        $config = [
            'source' => dirname($inputFile),
            'cache' => $this->temporaryDirectory,
            'driver' => $this->imageDriver,
        ];
        if ($watermarkPath) {
            $config['watermarks'] = $watermarkPath;
        }

        return ServerFactory::create($config);
    }

    public function save($outputFile) {
        if ($this->conversionResult == '') {
            copy($this->inputImage, $outputFile);

            return;
        }
        $conversionResultDirectory = pathinfo($this->conversionResult, PATHINFO_DIRNAME);
        copy($this->conversionResult, $outputFile);
        unlink($this->conversionResult);
        if ($this->directoryIsEmpty($conversionResultDirectory) && $conversionResultDirectory !== '/tmp') {
            rmdir($conversionResultDirectory);
        }
    }

    private function prepareManipulations(array $manipulationGroup) {
        $glideManipulations = [];
        foreach ($manipulationGroup as $name => $argument) {
            if ($name !== 'optimize') {
                // Glide membaca parameter seperti query string; `or` dibandingkan ketat dengan string.
                $glideManipulations[$this->convertToGlideParameter($name)] = is_scalar($argument) ? (string) $argument : $argument;
            }
        }

        return $glideManipulations;
    }

    private function convertToGlideParameter($manipulationName) {
        $conversions = [
            'width' => 'w',
            'height' => 'h',
            'blur' => 'blur',
            'pixelate' => 'pixel',
            'crop' => 'fit',
            'manualCrop' => 'crop',
            'orientation' => 'or',
            'flip' => 'flip',
            'fit' => 'fit',
            'devicePixelRatio' => 'dpr',
            'brightness' => 'bri',
            'contrast' => 'con',
            'gamma' => 'gam',
            'sharpen' => 'sharp',
            'filter' => 'filt',
            'background' => 'bg',
            'border' => 'border',
            'quality' => 'q',
            'format' => 'fm',
            'watermark' => 'mark',
            'watermarkWidth' => 'markw',
            'watermarkHeight' => 'markh',
            'watermarkFit' => 'markfit',
            'watermarkPaddingX' => 'markx',
            'watermarkPaddingY' => 'marky',
            'watermarkPosition' => 'markpos',
            'watermarkOpacity' => 'markalpha',
        ];
        if (!isset($conversions[$manipulationName])) {
            throw CImage_Exception_CouldNotConvertException::unknownManipulation($manipulationName);
        }

        return $conversions[$manipulationName];
    }

    private function directoryIsEmpty($directory) {
        $iterator = new FilesystemIterator($directory);

        return !$iterator->valid();
    }
}
