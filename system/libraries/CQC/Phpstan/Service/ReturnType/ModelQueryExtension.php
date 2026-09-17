<?php

use PHPStan\Type\Type;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ObjectType;
use PHPStan\Type\IntegerType;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\Generic\TemplateMixedType;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\DynamicMethodReturnTypeExtension;

final class CQC_Phpstan_Service_ReturnType_ModelQueryExtension implements DynamicMethodReturnTypeExtension {
    /**
     * @var CQC_Phpstan_Service_BuilderHelper
     */
    private $builderHelper;

    /**
     * @var ReflectionProvider
     */
    private $reflectionProvider;

    public function __construct(ReflectionProvider $reflectionProvider, CQC_Phpstan_Service_BuilderHelper $builderHelper) {
        $this->builderHelper = $builderHelper;
        $this->reflectionProvider = $reflectionProvider;
    }

    public function getClass(): string {
        return CModel_Query::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool {
        $builderReflection = $this->reflectionProvider->getClass(CModel_Query::class);

        // Don't handle dynamic wheres
        if (cstr::startsWith($methodReflection->getName(), 'where')
            && !$builderReflection->hasNativeMethod($methodReflection->getName())
        ) {
            return false;
        }

        if (cstr::startsWith($methodReflection->getName(), 'find')
            && $builderReflection->hasNativeMethod($methodReflection->getName())
        ) {
            return false;
        }

        $templateTypeMap = $methodReflection->getDeclaringClass()->getActiveTemplateTypeMap();
        if (!$templateTypeMap->getType(CQC_Phpstan_Service_BuilderHelper::MODEL_TEMPLATE) instanceof ObjectType) {
            return false;
        }

        return $builderReflection->hasNativeMethod($methodReflection->getName());
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope
    ): Type {
        $returnType = ParametersAcceptorSelector::selectFromArgs($scope, $methodCall->getArgs(), $methodReflection->getVariants())->getReturnType();
        $templateTypeMap = $methodReflection->getDeclaringClass()->getActiveTemplateTypeMap();

        /** @var Type|ObjectType|TemplateMixedType $modelType */
        $modelType = $templateTypeMap->getType(CQC_Phpstan_Service_BuilderHelper::MODEL_TEMPLATE);

        if ($modelType instanceof ObjectType && in_array(CModel_Collection::class, $returnType->getReferencedClasses(), true)) {
            $collectionClassName = $this->builderHelper->determineCollectionClassName($modelType->getClassName());

            return new GenericObjectType($collectionClassName, [new IntegerType(), $modelType]);
        }

        return $returnType;
    }
}
