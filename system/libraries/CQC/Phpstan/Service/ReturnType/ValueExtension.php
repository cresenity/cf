<?php

use PHPStan\Type\Type;
use PHPStan\Analyser\Scope;
use PHPStan\Type\TypeTraverser;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;

/**
 * Porting Larastan\Larastan\ReturnTypes\Helpers\ValueExtension (helper global
 * `value()`) ke c::value(), yang jadi padanan CF untuk helper itu (bukan
 * fungsi global). c::value($x) mengembalikan tipe kembalian $x bila $x
 * callable, atau tipe $x sendiri bila tidak - argumen ...$args (dipakai untuk
 * memanggil $x) tidak mempengaruhi tipe kembaliannya.
 *
 * @internal
 */
final class CQC_Phpstan_Service_ReturnType_ValueExtension implements DynamicStaticMethodReturnTypeExtension {
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
        return $methodReflection->getName() === 'value';
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
            return $methodReflection->getVariants()[0]->getReturnType();
        }

        $argType = $scope->getType($args[0]->value);

        return TypeTraverser::map($argType, static function (Type $type, callable $traverse) use ($scope): Type {
            if ($type->isCallable()->yes()) {
                return $type->getCallableParametersAcceptors($scope)[0]->getReturnType();
            }

            return $traverse($type);
        });
    }
}
