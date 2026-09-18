<?php

class CResources_PathGeneratorFactory {
    /**
     * @param CModel_Resource_ResourceInterface $resource
     *
     * @return CResources_PathGeneratorInterface
     */
    public static function create(CModel_Resource_ResourceInterface $resource) {
        $pathGeneratorClass = self::getPathGeneratorClass($resource);
        if ($pathGeneratorClass == null) {
            $pathGeneratorClass = CResources_PathGenerator::class;
        }
        static::guardAgainstInvalidPathGenerator($pathGeneratorClass);

        return c::container($pathGeneratorClass);
    }

    protected static function getPathGeneratorClass(CModel_Resource_ResourceInterface $resource) {
        $defaultPathGeneratorClass = CF::config('resource.path_generator');

        foreach (CF::config('resource.custom_path_generators', []) as $modelClass => $customPathGeneratorClass) {
            if (is_a($resource->model_type, $modelClass, true)) {
                return $customPathGeneratorClass;
            }
        }

        return $defaultPathGeneratorClass;
    }

    /**
     * @param string $pathGeneratorClass
     *
     * @return void
     */
    protected static function guardAgainstInvalidPathGenerator($pathGeneratorClass) {
        if (!class_exists($pathGeneratorClass)) {
            throw CResources_Exception_InvalidPathGenerator::doesntExist($pathGeneratorClass);
        }

        if (!is_subclass_of($pathGeneratorClass, CResources_PathGeneratorInterface::class)) {
            throw CResources_Exception_InvalidPathGenerator::doesNotImplementPathGenerator($pathGeneratorClass);
        }
    }
}
