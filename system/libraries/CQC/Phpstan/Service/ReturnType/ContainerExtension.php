<?php

use PHPStan\Type\Type;
use PHPStan\Analyser\Scope;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;

/**
 * c::container($abstract) sudah punya anotasi conditional
 * (@phpstan-return ($abstract is null ? CContainer_Container : mixed)) -
 * yang masih kurang cuma narrowing cabang "dengan $abstract" dari `mixed`
 * ke tipe $abstract sendiri bila itu string kelas literal. Lihat
 * CQC_Phpstan_Service_Support_ContainerMakeHelper untuk batasannya.
 *
 * @internal
 */
final class CQC_Phpstan_Service_ReturnType_ContainerExtension implements DynamicStaticMethodReturnTypeExtension {
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
        return c::class;
    }

    /**
     * @inheritdoc
     */
    public function isStaticMethodSupported(MethodReflection $methodReflection): bool {
        return $methodReflection->getName() === 'container';
    }

    /**
     * @inheritdoc
     */
    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope
    ): Type {
        $args = $methodCall->getArgs();

        if (count($args) === 0) {
            return new ObjectType(CContainer_Container::class);
        }

        $resolved = $this->helper->resolveTypeFromArgs($args, $scope);

        return $resolved instanceof MixedType ? $methodReflection->getVariants()[0]->getReturnType() : $resolved;
    }
}
