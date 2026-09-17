<?php

use PHPStan\Type\Type;
use PHPStan\Reflection;
use PHPStan\TrinaryLogic;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Reflection\FunctionVariant;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Reflection\ParameterReflection;

final class CQC_Phpstan_Reflection_DynamicWhereMethodReflection implements Reflection\MethodReflection {
    private Reflection\ClassReflection $classReflection;

    private string $methodName;

    public function __construct(Reflection\ClassReflection $classReflection, string $methodName) {
        $this->classReflection = $classReflection;
        $this->methodName = $methodName;
    }

    public function getDeclaringClass(): Reflection\ClassReflection {
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

    public function getPrototype(): Reflection\ClassMemberReflection {
        return $this;
    }

    public function getVariants(): array {
        return [
            new FunctionVariant(
                TemplateTypeMap::createEmpty(),
                TemplateTypeMap::createEmpty(),
                [
                    new class() implements ParameterReflection {
                        public function getName(): string {
                            return 'dynamic-with';
                        }

                        public function isOptional(): bool {
                            return false;
                        }

                        public function getType(): Type {
                            return new MixedType();
                        }

                        public function passedByReference(): PHPStan\Reflection\PassedByReference {
                            return Reflection\PassedByReference::createNo();
                        }

                        public function isVariadic(): bool {
                            return false;
                        }

                        public function getDefaultValue(): ?Type {
                            return null;
                        }
                    },
                ],
                false,
                new ObjectType($this->classReflection->getName())
            ),
        ];
    }

    /**
     * Satu-satunya varian; method CF tidak pernah punya lebih dari satu.
     *
     * @return \PHPStan\Reflection\ParametersAcceptor
     */
    public function getOnlyVariant() {
        return $this->getVariants()[0];
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

    public function getThrowType(): ?Type {
        return null;
    }

    public function hasSideEffects(): TrinaryLogic {
        return TrinaryLogic::createNo();
    }
}
