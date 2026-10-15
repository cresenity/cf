<?php

use PHPStan\Type\Type;
use PHPStan\Analyser\Scope;
use PHPStan\Type\StringType;
use PHPStan\Type\BooleanType;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;

/**
 * Porting Larastan\Larastan\ReturnTypes\{AppEnvironmentReturnTypeExtension,
 * AppFacadeEnvironmentReturnTypeExtension} ke CF::environment(). Jumlah
 * argumen (bukan nilainya) yang membedakan cabangnya - tanpa argumen
 * mengembalikan nama environment aktif (string), dengan argumen
 * mengembalikan hasil pencocokan pola (bool) - jumlah argumen variadik tidak
 * bisa diuji lewat sintaks conditional-return docblock (@return (... is ...
 * ? ... : ...) cuma menguji tipe/nilai SATU parameter, bukan count()),
 * makanya butuh kelas ekstensi, bukan sekadar anotasi.
 *
 * @internal
 */
final class CQC_Phpstan_Service_ReturnType_EnvironmentExtension implements DynamicStaticMethodReturnTypeExtension {
    /**
     * @inheritdoc
     */
    public function getClass(): string {
        return CF::class;
    }

    /**
     * @inheritdoc
     */
    public function isStaticMethodSupported(MethodReflection $methodReflection): bool {
        return $methodReflection->getName() === 'environment';
    }

    /**
     * @inheritdoc
     */
    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope
    ): Type {
        if (count($methodCall->getArgs()) === 0) {
            return new StringType();
        }

        return new BooleanType();
    }
}
