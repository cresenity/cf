<?php

use PHPStan\Type\Type;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ObjectType;
use PHPStan\Type\TypeCombinator;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\DynamicMethodReturnTypeExtension;

/**
 * Tipe kembalian `addControl($name, $type)` menurut tipe kontrolnya:
 * `addControl('range', 'daterange-picker')` adalah `CElement_FormInput_DateRange`,
 * bukan sekadar `CElement_FormInput` yang ditulis di `@return`, sehingga
 * `setValueStart()` dkk. dikenali.
 *
 * Pemetaan tipe ke kelas dibaca dari CManager (kontrol bawaan dan yang didaftarkan
 * app), seperti pabriknya sendiri, dan nama kelas langsung juga diterima. Bila
 * argumen pertama bukan string (instans kontrol), tipe kontrolnya bukan string
 * konstan, tidak terdaftar, atau kelasnya bukan CElement_FormInput, tipe `@return`
 * yang dipakai.
 *
 * @internal
 */
final class CQC_Phpstan_Service_ReturnType_AddControlExtension implements DynamicMethodReturnTypeExtension {
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
        return CObservable::class;
    }

    /**
     * @inheritDoc
     */
    public function isMethodSupported(MethodReflection $methodReflection): bool {
        return $methodReflection->getName() === 'addControl';
    }

    /**
     * @inheritDoc
     */
    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): ?Type {
        $args = $methodCall->getArgs();
        if (count($args) === 0 || !$scope->getType($args[0]->value)->isString()->yes()) {
            return null;
        }

        if (count($args) === 1) {
            $typeNames = ['text'];
        } else {
            $typeNames = [];
            foreach ($scope->getType($args[1]->value)->getConstantStrings() as $constantString) {
                $typeNames[] = $constantString->getValue();
            }
            if (count($typeNames) === 0) {
                return null;
            }
        }

        $formInput = new ObjectType(CElement_FormInput::class);
        $types = [];
        foreach ($typeNames as $typeName) {
            $className = $this->controlClass($typeName);
            if ($className === null || !$this->reflectionProvider->hasClass($className)) {
                return null;
            }

            $type = new ObjectType($className);
            if (!$formInput->isSuperTypeOf($type)->yes()) {
                return null;
            }
            $types[] = $type;
        }

        return TypeCombinator::union(...$types);
    }

    /**
     * @param string $typeName
     *
     * @return null|string
     */
    private function controlClass(string $typeName): ?string {
        $manager = CManager::instance();
        if (count($manager->getRegisteredControls()) === 0) {
            CApp::registerControl();
        }

        $controls = $manager->getRegisteredControls();
        if (isset($controls[$typeName])) {
            return $controls[$typeName];
        }

        return $this->reflectionProvider->hasClass($typeName) ? $typeName : null;
    }
}
