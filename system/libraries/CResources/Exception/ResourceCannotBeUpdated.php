<?php

class CResources_Exception_ResourceCannotBeUpdated extends CResources_Exception {
    public static function doesNotBelongToCollection($collectionName, CModel_Resource_ResourceInterface $resource) {
        /** @var CModel_Resource_ResourceInterface|CModel $resource */
        return new static("Resource id {$resource->getKey()} is not part of collection `{$collectionName}`");
    }
}
