<?php

use PHPStan\Type\Type;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ArrayType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\TypeCombinator;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;

/**
 * @internal
 */
final class CQC_Phpstan_Service_ReturnType_ModelQueryFindExtension implements DynamicMethodReturnTypeExtension {
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

    /**
     * @inheritdoc
     */
    public function getClass(): string {
        return CModel_Query::class;
    }

    /**
     * @inheritdoc
     */
    public function isMethodSupported(MethodReflection $methodReflection): bool {
        $methodName = $methodReflection->getName();

        if (!cstr::startsWith($methodName, 'find')) {
            return false;
        }

        $model = $methodReflection->getDeclaringClass()->getActiveTemplateTypeMap()->getType(CQC_Phpstan_Service_BuilderHelper::MODEL_TEMPLATE);

        if (!$model instanceof ObjectType) {
            return false;
        }

        if (!$this->reflectionProvider->getClass(CModel_Query::class)->hasNativeMethod($methodName)
            && !$this->reflectionProvider->getClass(CDatabase_Query_Builder::class)->hasNativeMethod($methodName)
        ) {
            return false;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope
    ): Type {
        /** @var ObjectType $model */
        $model = $methodReflection->getDeclaringClass()->getActiveTemplateTypeMap()->getType(CQC_Phpstan_Service_BuilderHelper::MODEL_TEMPLATE);
        $returnType = $methodReflection->getVariants()[0]->getReturnType();
        $argType = $scope->getType($methodCall->getArgs()[0]->value);

        $returnType = CQC_Phpstan_Method_ModelTypeHelper::replaceStaticTypeWithModel($returnType, $model->getClassName());

        if ($argType->isIterable()->yes()) {
            if (in_array(CModel_Collection::class, $returnType->getReferencedClasses(), true)) {
                $collectionClassName = $this->builderHelper->determineCollectionClassName($model->getClassName());

                return new GenericObjectType($collectionClassName, [new IntegerType(), $model]);
            }

            return TypeCombinator::remove($returnType, $model);
        }

        if ($argType instanceof MixedType) {
            return $returnType;
        }

        return TypeCombinator::remove(
            TypeCombinator::remove(
                $returnType,
                new ArrayType(new MixedType(), $model)
            ),
            new ObjectType(CModel_Collection::class)
        );
    }
}
