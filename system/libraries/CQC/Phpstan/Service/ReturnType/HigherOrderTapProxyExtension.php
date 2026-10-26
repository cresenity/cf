<?php

use PHPStan\Type\Type;
use PHPStan\Analyser\Scope;
use PHPStan\Type\MixedType;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;

/**
 * c::tap($value) (tanpa $callback) mengembalikan CBase_HigherOrderTapProxy -
 * metode apa pun yang dipanggil di atasnya diteruskan ke $target lalu
 * HASILNYA DIBUANG, mengembalikan $target sendiri (lihat __call() di
 * CBase_HigherOrderTapProxy). CQC_Phpstan_Method_HigherOrderTapProxyExtension
 * menjamin nama metodenya sendiri valid (ada di TClass); ekstensi ini
 * menimpa return type-nya jadi TClass, bukan return type asli metode yang
 * dipanggil.
 */
final class CQC_Phpstan_Service_ReturnType_HigherOrderTapProxyExtension implements DynamicMethodReturnTypeExtension {
    /**
     * @inheritdoc
     */
    public function getClass(): string {
        return CBase_HigherOrderTapProxy::class;
    }

    /**
     * @inheritdoc
     */
    public function isMethodSupported(MethodReflection $methodReflection): bool {
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
        $type = $scope->getType($methodCall->var);

        if ($type instanceof GenericObjectType) {
            $types = $type->getTypes();

            if (count($types) === 1 && $types[0]->getObjectClassNames() !== []) {
                return $types[0];
            }
        }

        return new MixedType();
    }
}
