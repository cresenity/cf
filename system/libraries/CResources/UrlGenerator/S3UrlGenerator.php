<?php

class CResources_UrlGenerator_S3UrlGenerator extends CResources_UrlGeneratorAbstract {
    /**
     * @var \CStorage
     */
    protected $filesystemManager;

    public function __construct() {
        $this->filesystemManager = CStorage::instance();
        //parent::__construct();
    }

    /**
     * Get the url for a media item.
     *
     * @return string
     */
    public function getUrl() {
        $url = $this->getPathRelativeToRoot();
        // $url = $this->rawUrlEncodeFilename($url);
        $url = $this->versionUrl($url);

        return CStorage::instance()->disk($this->resource->disk)->url($url);
    }

    /**
     * Get the temporary url for a media item.
     *
     * @param \DateTimeInterface $expiration
     * @param array              $options
     *
     * @return string
     */
    public function getTemporaryUrl(DateTimeInterface $expiration, array $options = []) {
        return $this
            ->filesystemManager
            ->disk($this->resource->disk)
            ->temporaryUrl($this->getPath(), $expiration, $options);
    }

    /**
     * Get the url for the profile of a media item.
     *
     * @return string
     */
    public function getPath() {
        return $this->getPathRelativeToRoot();
    }

    /**
     * Get the url to the directory containing responsive images.
     *
     * @return string
     */
    public function getResponsiveImagesDirectoryUrl() {
        $url = $this->pathGenerator->getPathForResponsiveImages($this->resource);
        if ($root = CF::config('storage.disks.' . $this->resource->disk . '.root')) {
            $url = $root . '/' . $url;
        }
        return CF::config('resource.s3.domain') . '/' . $url;
    }
}
