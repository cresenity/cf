<?php

use PHPStan\Type\Type;
use PHPStan\Analyser\Scope;
use PHPStan\Type\MixedType;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;

/**
 * Narrowing yang sama dengan CQC_Phpstan_Service_ReturnType_ContainerExtension
 * (c::container()), tapi untuk pola yang lebih mentah:
 * CContainer::getInstance()->make(Foo::class)/makeWith(Foo::class).
 *
 * @internal
 */
final class CQC_Phpstan_Service_ReturnType_ContainerMakeExtension implements DynamicMethodReturnTypeExtension {
    /**
     * @var CQC_Phpstan_Service_Support_ContainerMakeHelper
     */
    private $helper;

    public function __construct(CQC_Phpstan_Service_Support_ContainerMakeHelper $helper) {
        $this->helper = $helper;
    }

    /**
     * @inheritdoc
     */
    public function getClass(): string {
        return CContainer_Container::class;
    }

    /**
     * @inheritdoc
     */
    public function isMethodSupported(MethodReflection $methodReflection): bool {
        return in_array($methodReflection->getName(), ['make', 'makeWith'], true);
    }

    /**
     * @inheritdoc
     */
    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope
    ): Type {
        $resolved = $this->helper->resolveTypeFromArgs($methodCall->getArgs(), $scope);

        return $resolved instanceof MixedType ? $methodReflection->getVariants()[0]->getReturnType() : $resolved;
    }
}
