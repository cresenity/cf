<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since May 1, 2019, 11:58:21 PM
 */
interface CModel_HasResourceInterface {
    /**
     * Set the polymorphic relation.
     *
     * @return mixed
     */
    public function resource();

    /**
     * Move a file to the resourcelibrary.
     *
     * @param string|\Symfony\Component\HttpFoundation\File\UploadedFile $file
     *
     * @return \CModel_HasResource_FileAdder_FileAdder
     */
    public function addResource($file);

    /**
     * Copy a file to the resourcelibrary.
     *
     * @param string|\Symfony\Component\HttpFoundation\File\UploadedFile $file
     *
     * @return \CModel_HasResource_FileAdder_FileAdder
     */
    public function copyResource($file);

    /**
     * Determine if there is resource in the given collection.
     *
     * @param $collectionResource
     *
     * @return bool
     */
    public function hasResource($collectionResource = '');

    /**
     * Get resource collection by its collectionName.
     *
     * @param string         $collectionName
     * @param array|callable $filters
     *
     * @return \CCollection
     */
    public function getResource($collectionName = 'default', $filters = []);

    /**
     * Remove all resource in the given collection.
     *
     * @param string $collectionName
     */
    public function clearResourceCollection($collectionName = 'default');

    /**
     * Remove all resource in the given collection except some.
     *
     * @param string                                                 $collectionName
     * @param \CApp_Model_Interface_ResourceInterface[]|\CCollection $excludedResource
     *
     * @return string $collectionName
     */
    public function clearResourceCollectionExcept($collectionName = 'default', $excludedResource = []);

    /**
     * Determines if the resource files should be preserved when the resource object gets deleted.
     *
     * @return bool
     */
    public function shouldDeletePreservingResource();

    /**
     * Cache the resource on the object.
     *
     * @param string $collectionName
     *
     * @return mixed
     */
    public function loadResource($collectionName);

    /**
     * Add a conversion.
     *
     * @param string $name
     *
     * @return CResources_Conversion
     */
    public function addResourceConversion($name);

    /**
     * Register the resource conversions.
     *
     * @param CApp_Model_Interface_ResourceInterface $resource
     */
    public function registerResourceConversions(CApp_Model_Interface_ResourceInterface $resource = null);

    /**
     * Register the resource collections.
     */
    public function registerResourceCollections();

    /**
     * Register the resource conversions and conversions set in resource collections.
     */
    public function registerAllResourceConversions();
}
