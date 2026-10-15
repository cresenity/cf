<?php

use PHPStan\Type\Type;
use PHPStan\Analyser\Scope;
use PHPStan\Type\TypeTraverser;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Type\GeneralizePrecision;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;

/**
 * Porting Larastan\Larastan\ReturnTypes\Helpers\EnvFunctionDynamicFunctionReturnTypeExtension
 * (helper global `env()`) ke c::env(), padanan CF untuk helper itu. Tanpa
 * $default, tipe tetap mengikuti @return di c::env() (mixed, karena CEnv
 * tidak tahu skema env). Dengan $default, tipe kembalian dipersempit jadi
 * tipe $default (atau tipe kembalian $default kalau $default itu sendiri
 * callable - pola sama dengan `env('KEY', fn () => ...)`), digeneralisasi
 * supaya nilai konstan literal (mis. default string tertentu) tidak jadi
 * terlalu sempit bagi key yang sebenarnya bisa diisi string apa pun di env.
 *
 * @internal
 */
final class CQC_Phpstan_Service_ReturnType_EnvExtension implements DynamicStaticMethodReturnTypeExtension {
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
        return $methodReflection->getName() === 'env';
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

        if (count($args) < 2) {
            return $methodReflection->getVariants()[0]->getReturnType();
        }

        $defaultArgType = $scope->getType($args[1]->value);

        return TypeTraverser::map($defaultArgType, static function (Type $type, callable $traverse) use ($scope): Type {
            if ($type->isConstantScalarValue()->no() && $type->isCallable()->yes()) {
                return $type->getCallableParametersAcceptors($scope)[0]->getReturnType();
            }

            return $traverse($type);
        })->generalize(GeneralizePrecision::lessSpecific());
    }
}
