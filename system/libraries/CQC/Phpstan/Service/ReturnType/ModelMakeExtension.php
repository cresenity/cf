<?php

use PHPStan\Type\Type;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ObjectType;
use PHPStan\Type\TypeCombinator;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;

/**
 * Tipe kembalian pabrik model berdasarkan nama: `TBModel::make('VoucherCode')`
 * adalah `TBModel_VoucherCode`, bukan sekadar `TBModel` yang ditulis di `@return`.
 *
 * Nama argumen boleh berawalan kelas (`make('TBModel_VoucherCode')`) - yang
 * dipakai hanya ruas terakhir, sama seperti pabriknya. Bila argumennya bukan
 * string konstan atau kelasnya tidak ada, tipe `@return` yang dipakai.
 *
 * @internal
 */
final class CQC_Phpstan_Service_ReturnType_ModelMakeExtension implements DynamicStaticMethodReturnTypeExtension {
    /**
     * @var ReflectionProvider
     */
    private $reflectionProvider;

    public function __construct(ReflectionProvider $reflectionProvider) {
        $this->reflectionProvider = $reflectionProvider;
    }

    /**
     * @inheritDoc
     */
    public function getClass(): string {
        return CModel::class;
    }

    /**
     * @inheritDoc
     */
    public function isStaticMethodSupported(MethodReflection $methodReflection): bool {
        return $methodReflection->getName() === 'make'
            && $methodReflection->getDeclaringClass()->getName() !== CModel::class;
    }

    /**
     * @inheritDoc
     */
    public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): ?Type {
        if (count($methodCall->getArgs()) === 0) {
            return null;
        }

        $prefix = $methodReflection->getDeclaringClass()->getName() . '_';
        $types = [];
        foreach ($scope->getType($methodCall->getArgs()[0]->value)->getConstantStrings() as $constantString) {
            $segments = explode('_', $constantString->getValue());
            $className = $prefix . end($segments);
            if (!$this->reflectionProvider->hasClass($className)) {
                return null;
            }

            $types[] = new ObjectType($className);
        }

        return count($types) > 0 ? TypeCombinator::union(...$types) : null;
    }
}
