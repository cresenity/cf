<?php

abstract class CResources_UrlGeneratorAbstract implements CResources_UrlGeneratorInterface {
    /**
     * @var CApp_Model_Interface_ResourceInterface
     */
    protected $resource;

    /**
     * @var CResources_Conversion
     */
    protected $conversion;

    /**
     * @var CResources_PathGeneratorInterface
     */
    protected $pathGenerator;

    /**
     * @param CApp_Model_Interface_ResourceInterface $resource
     *
     * @return CResources_UrlGeneratorInterface
     */
    public function setResource(CApp_Model_Interface_ResourceInterface $resource) {
        $this->resource = $resource;
        return $this;
    }

    /**
     * @param CResources_Conversion $conversion
     *
     * @return CResources_UrlGeneratorInterface
     */
    public function setConversion(CResources_Conversion $conversion) {
        $this->conversion = $conversion;
        return $this;
    }

    /**
     * @param CResources_PathGeneratorInterface $pathGenerator
     *
     * @return CResources_UrlGeneratorInterface
     */
    public function setPathGenerator(CResources_PathGeneratorInterface $pathGenerator) {
        $this->pathGenerator = $pathGenerator;
        return $this;
    }

    /**
     * Get the path to the requested file relative to the root of the resource directory.
     */
    public function getPathRelativeToRoot() {
        if (is_null($this->conversion)) {
            return $this->pathGenerator->getPath($this->resource) . ($this->resource->file_name);
        }
        return $this->pathGenerator->getPathForConversions($this->resource)
                . pathinfo($this->resource->file_name, PATHINFO_FILENAME)
                . '-' . $this->conversion->getName()
                . '.'
                . $this->conversion->getResultExtension($this->resource->extension);
    }

    public function rawUrlEncodeFilename($path = '') {
        return pathinfo($path, PATHINFO_DIRNAME) . '/' . rawurlencode(pathinfo($path, PATHINFO_BASENAME));
    }

    protected function getDiskName() {
        return $this->conversion === null
            ? $this->resource->disk
            : $this->resource->conversions_disk;
    }

    protected function getDisk() {
        return CStorage::instance()->disk($this->getDiskName());
    }

    public function versionUrl($path = '') {
        if (!CF::config('resource.version_urls')) {
            return $path;
        }
        return "{$path}?v={$this->resource->updated->timestamp}";
    }
}
