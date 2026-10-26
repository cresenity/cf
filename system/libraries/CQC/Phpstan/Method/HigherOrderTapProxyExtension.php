<?php

use PHPStan\Type;
use PHPStan\TrinaryLogic;
use PHPStan\Analyser\OutOfClassScope;
use PHPStan\ShouldNotHappenException;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\FunctionVariant;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;

/**
 * Metode apa pun yang dipanggil lewat CBase_HigherOrderTapProxy harus benar2
 * ada di target (TClass), supaya nama metode yang salah tetap kelihatan
 * sebagai error - return type-nya yang ditimpa ke TClass sendiri ada di
 * CQC_Phpstan_Service_ReturnType_HigherOrderTapProxyExtension, mengikuti
 * perilaku runtime __call() yang membuang hasil metode dan mengembalikan
 * $target.
 */
final class CQC_Phpstan_Method_HigherOrderTapProxyExtension implements MethodsClassReflectionExtension {
    public function hasMethod(ClassReflection $classReflection, string $methodName): bool {
        if ($classReflection->getName() !== CBase_HigherOrderTapProxy::class) {
            return false;
        }

        $targetType = $classReflection->getActiveTemplateTypeMap()->getType('TClass');

        if ($targetType === null || $targetType->getObjectClassReflections() === []) {
            return false;
        }

        return $targetType->hasMethod($methodName)->yes();
    }

    public function getMethod(
        ClassReflection $classReflection,
        string $methodName
    ): MethodReflection {
        $targetType = $classReflection->getActiveTemplateTypeMap()->getType('TClass');

        if ($targetType === null || $targetType->getObjectClassReflections() === [] || !$targetType->hasMethod($methodName)->yes()) {
            throw new ShouldNotHappenException();
        }

        $targetMethodReflection = $targetType->getMethod($methodName, new OutOfClassScope());

        return new class($classReflection, $methodName, $targetMethodReflection, $targetType) implements MethodReflection {
            /**
             * @var ClassReflection
             */
            private $classReflection;

            /**
             * @var string
             */
            private $methodName;

            /**
             * @var MethodReflection
             */
            private $targetMethodReflection;

            /**
             * @var Type\Type
             */
            private $returnType;

            public function __construct(ClassReflection $classReflection, string $methodName, MethodReflection $targetMethodReflection, Type\Type $returnType) {
                $this->classReflection = $classReflection;
                $this->methodName = $methodName;
                $this->targetMethodReflection = $targetMethodReflection;
                $this->returnType = $returnType;
            }

            public function getDeclaringClass(): ClassReflection {
                return $this->classReflection;
            }

            public function isStatic(): bool {
                return false;
            }

            public function isPrivate(): bool {
                return false;
            }

            public function isPublic(): bool {
                return true;
            }

            public function getDocComment(): ?string {
                return null;
            }

            public function getName(): string {
                return $this->methodName;
            }

            public function getPrototype(): PHPStan\Reflection\ClassMemberReflection {
                return $this;
            }

            public function getVariants(): array {
                return [
                    new FunctionVariant(
                        $this->targetMethodReflection->getVariants()[0]->getTemplateTypeMap(),
                        $this->targetMethodReflection->getVariants()[0]->getResolvedTemplateTypeMap(),
                        $this->targetMethodReflection->getVariants()[0]->getParameters(),
                        $this->targetMethodReflection->getVariants()[0]->isVariadic(),
                        $this->returnType
                    ),
                ];
            }

            public function isDeprecated(): TrinaryLogic {
                return TrinaryLogic::createNo();
            }

            public function getDeprecatedDescription(): ?string {
                return null;
            }

            public function isFinal(): TrinaryLogic {
                return TrinaryLogic::createNo();
            }

            public function isInternal(): TrinaryLogic {
                return TrinaryLogic::createNo();
            }

            public function getThrowType(): ?PHPStan\Type\Type {
                return null;
            }

            public function hasSideEffects(): TrinaryLogic {
                return TrinaryLogic::createMaybe();
            }
        };
    }
}
