<?php

use PHPStan\Type\Type;
use PHPStan\Type\IntegerType;
use PHPStan\Type\TypeWithClassName;
use PHPStan\Analyser\OutOfClassScope;
use PHPStan\ShouldNotHappenException;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\Generic\TemplateMixedType;
use PHPStan\Type\Generic\TemplateObjectType;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\MissingMethodFromReflectionException;

final class CQC_Phpstan_Service_Method_ModelQueryForwardsCallsExtension implements MethodsClassReflectionExtension {
    /**
     * @var array<string, MethodReflection>
     */
    private $cache = [];

    /**
     * @var CQC_Phpstan_Service_BuilderHelper
     */
    private $builderHelper;

    /**
     * @var ReflectionProvider
     */
    private $reflectionProvider;

    public function __construct(CQC_Phpstan_Service_BuilderHelper $builderHelper, ReflectionProvider $reflectionProvider) {
        $this->builderHelper = $builderHelper;
        $this->reflectionProvider = $reflectionProvider;
    }

    /**
     * @throws ShouldNotHappenException
     * @throws MissingMethodFromReflectionException
     */
    public function hasMethod(ClassReflection $classReflection, string $methodName): bool {
        if (array_key_exists($classReflection->getCacheKey() . '-' . $methodName, $this->cache)) {
            return true;
        }

        $methodReflection = $this->findMethod($classReflection, $methodName);

        if ($methodReflection !== null && $classReflection->isGeneric()) {
            $this->cache[$classReflection->getCacheKey() . '-' . $methodName] = $methodReflection;

            return true;
        }

        return false;
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection {
        return $this->cache[$classReflection->getCacheKey() . '-' . $methodName];
    }

    /**
     * @throws MissingMethodFromReflectionException
     * @throws ShouldNotHappenException
     */
    private function findMethod(ClassReflection $classReflection, string $methodName): ?MethodReflection {
        if ($classReflection->getName() !== CModel_Query::class && !$classReflection->isSubclassOf(CModel_Query::class)) {
            return null;
        }

        /** @var null|Type|TemplateMixedType $modelType */
        $modelType = $classReflection->getActiveTemplateTypeMap()->getType('TModelClass');

        // Generic type is not specified
        if ($modelType === null) {
            return null;
        }

        if ($modelType instanceof TemplateObjectType) {
            $modelType = $modelType->getBound();
        }

        if ($modelType instanceof TypeWithClassName) {
            $modelReflection = $modelType->getClassReflection();
        } else {
            $modelReflection = $this->reflectionProvider->getClass(CModel::class);
        }

        if ($modelReflection === null) {
            return null;
        }

        $ref = $this->builderHelper->searchOnModelQuery($classReflection, $methodName, $modelReflection);

        if ($ref === null) {
            // Special case for `SoftDeletes` trait
            if (in_array($methodName, ['withTrashed', 'onlyTrashed', 'withoutTrashed', 'restore'], true)
                && in_array(CModel_SoftDelete_SoftDeleteTrait::class, array_keys($modelReflection->getTraits(true)))
            ) {
                $ref = $this->reflectionProvider->getClass(CModel_SoftDelete_SoftDeleteTrait::class)->getMethod($methodName, new OutOfClassScope());

                if ($methodName === 'restore') {
                    return new CQC_Phpstan_Reflection_ModelQueryMethodReflection(
                        $methodName,
                        $classReflection,
                        $ref,
                        ParametersAcceptorSelector::selectSingle($ref->getVariants())->getParameters(),
                        new IntegerType(),
                        ParametersAcceptorSelector::selectSingle($ref->getVariants())->isVariadic()
                    );
                }

                return new CQC_Phpstan_Reflection_ModelQueryMethodReflection(
                    $methodName,
                    $classReflection,
                    $ref,
                    ParametersAcceptorSelector::selectSingle($ref->getVariants())->getParameters(),
                    new GenericObjectType($classReflection->getName(), [$modelType]),
                    ParametersAcceptorSelector::selectSingle($ref->getVariants())->isVariadic()
                );
            }

            return null;
        }

        $parametersAcceptor = ParametersAcceptorSelector::selectSingle($ref->getVariants());

        if (in_array($methodName, $this->builderHelper->passthru, true)) {
            $returnType = $parametersAcceptor->getReturnType();

            return new CQC_Phpstan_Reflection_ModelQueryMethodReflection(
                $methodName,
                $classReflection,
                $ref,
                $parametersAcceptor->getParameters(),
                $returnType,
                $parametersAcceptor->isVariadic()
            );
        }

        // Returning custom reflection
        // to ensure return type is always `CModel_Query<CModel>`
        return new CQC_Phpstan_Reflection_ModelQueryMethodReflection(
            $methodName,
            $classReflection,
            $ref,
            $parametersAcceptor->getParameters(),
            new GenericObjectType($classReflection->getName(), [$modelType]),
            $parametersAcceptor->isVariadic()
        );
    }
}
