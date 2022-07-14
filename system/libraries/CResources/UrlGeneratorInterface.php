<?php

interface CResources_UrlGeneratorInterface {
    /**
     * Get the url for a resource item.
     *
     * @return string
     */
    public function getUrl();

    /**
     * Get the path for a resource item.
     *
     * @return string
     */
    public function getPath();

    /**
     * @param CModel_Resource_ResourceInterface $resource
     *
     * @return CResources_UrlGeneratorInterface
     */
    public function setResource(CModel_Resource_ResourceInterface $resource);

    /**
     * @param CResources_Conversion $conversion
     *
     * @return \CResources_UrlGeneratorInterface
     */
    public function setConversion(CResources_Conversion $conversion);

    /**
     * Set the path generator class.
     *
     * @param \Spatie\MediaLibrary\PathGenerator\PathGenerator $pathGenerator
     *
     * @return CResources_PathGeneratorInterface
     */
    public function setPathGenerator(CResources_PathGeneratorInterface $pathGenerator);

    /**
     * Get the temporary url for a media item.
     *
     * @param DateTimeInterface $expiration
     * @param array             $options
     *
     * @return string
     */
    public function getTemporaryUrl(DateTimeInterface $expiration, array $options = []);

    /**
     * Get the url to the directory containing responsive images.
     *
     * @return string
     */
    public function getResponsiveImagesDirectoryUrl();
}
