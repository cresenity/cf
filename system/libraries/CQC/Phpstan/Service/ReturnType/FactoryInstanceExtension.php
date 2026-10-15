<?php

use PHPStan\Type\Type;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ObjectType;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;

/**
 * Porting Larastan\Larastan\ReturnTypes\Helpers\{ResponseExtension,
 * ValidatorExtension} (+ pola yang sama berlaku untuk c::view(), tidak ada
 * di Larastan karena `view()` Laravel cuma punya satu bentuk). Ketiga
 * method c:: ini sama bentuknya: tanpa argumen mengembalikan factory-nya,
 * dengan argumen mengembalikan instance yang sudah dibuat factory itu -
 * dibedakan lewat func_num_args() di kode aslinya, BUKAN is_null() pada
 * salah satu parameter (parameter pertamanya tidak berdefault null), jadi
 * TIDAK bisa ditambal lewat anotasi conditional-return docblock saja -
 * dicoba dan dibuktikan salah lewat probe (`c::validator([])` dengan array
 * kosong eksplisit tetap kena cabang "dengan argumen" di runtime, tapi
 * conditional type berbasis tipe nilai akan salah menyimpulkannya sebagai
 * cabang "tanpa argumen" karena `[]` cocok dengan `array{}`). Makanya
 * dites lewat count($args) di sini, sama seperti Larastan aslinya.
 *
 * @internal
 */
final class CQC_Phpstan_Service_ReturnType_FactoryInstanceExtension implements DynamicStaticMethodReturnTypeExtension {
    /**
     * Nama method c:: => [kelas factory (0 argumen), kelas instance (>=1 argumen)].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private static $methods = [
        'validator' => [CValidation_Factory::class, CValidation_Validator::class],
        'response' => [CHTTP_ResponseFactory::class, CHTTP_Response::class],
        'view' => [CView_Factory::class, CView_View::class],
    ];

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
        return array_key_exists($methodReflection->getName(), self::$methods);
    }

    /**
     * @inheritdoc
     */
    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope
    ): Type {
        list($factoryClass, $instanceClass) = self::$methods[$methodReflection->getName()];

        if (count($methodCall->getArgs()) === 0) {
            return new ObjectType($factoryClass);
        }

        return new ObjectType($instanceClass);
    }
}
