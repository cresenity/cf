<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since May 2, 2019, 1:24:01 AM
 */
class CResources_PathGenerator implements CResources_PathGeneratorInterface {
    /**
     * Get the path for the given resource, relative to the root storage path.
     */
    public function getPath(CModel_Resource_ResourceInterface $resource) {
        return $this->getBasePath($resource) . '/';
    }

    /**
     * Get the path for conversions of the given resource, relative to the root storage path.
     */
    public function getPathForConversions(CModel_Resource_ResourceInterface $resource) {
        return $this->getBasePath($resource) . '/conversions/';
    }

    /**
     * Get the path for responsive images of the given resource, relative to the root storage path.
     */
    public function getPathForResponsiveImages(CModel_Resource_ResourceInterface $resource) {
        return $this->getBasePath($resource) . '/responsive-images/';
    }

    /**
     * Get a unique base path for the given resource.
     */
    protected function getBasePath(CModel_Resource_ResourceInterface $resource) {
        /** @var CModel $resource */
        $ymd = date('Ymd', strtotime($resource->created));

        return 'resources' . '/' . $ymd . '/' . $resource->model_type . '/' . $resource->getKey();
    }
}
