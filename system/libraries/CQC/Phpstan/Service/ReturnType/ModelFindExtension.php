<?php

use PHPStan\Type\Type;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ArrayType;
use PHPStan\Type\ErrorType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\TypeCombinator;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;

/**
 * @internal
 */
final class CQC_Phpstan_Service_ReturnType_ModelFindExtension implements DynamicStaticMethodReturnTypeExtension {
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
        return CModel::class;
    }

    /**
     * @inheritdoc
     */
    public function isStaticMethodSupported(MethodReflection $methodReflection): bool {
        $methodName = $methodReflection->getName();

        if (!cstr::startsWith($methodName, 'find')) {
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
    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope
    ): Type {
        if (count($methodCall->getArgs()) < 1) {
            return new ErrorType();
        }

        $class = $methodCall->class;

        if (!$class instanceof Name) {
            return new ErrorType();
        }

        $modelName = $class->toString();

        $returnType = $methodReflection->getVariants()[0]->getReturnType();
        $argType = $scope->getType($methodCall->getArgs()[0]->value);

        if ($argType->isIterable()->yes()) {
            if (in_array(CModel_Collection::class, $returnType->getReferencedClasses(), true)) {
                $collectionClassName = $this->builderHelper->determineCollectionClassName($modelName);

                return new GenericObjectType($collectionClassName, [new IntegerType(), new ObjectType($modelName)]);
            }

            return TypeCombinator::remove($returnType, new ObjectType($modelName));
        }
        $returnType = TypeCombinator::union($returnType, new ObjectType($modelName));
        if ($argType instanceof MixedType) {
            return $returnType;
        }

        //return TypeCombinator::addNull(new ObjectType($modelName));

        return TypeCombinator::remove(
            TypeCombinator::remove(
                $returnType,
                new ArrayType(new MixedType(), new ObjectType($modelName))
            ),
            new ObjectType(CModel_Collection::class)
        );
    }
}
