<?php

use PHPStan\Type\Type;
use PHPStan\TrinaryLogic;
use PHPStan\Type\ObjectType;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\FunctionVariant;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\ClassMemberReflection;

final class CQC_Phpstan_Reflection_ModelQueryMethodReflection implements MethodReflection {
    /**
     * @var string
     */
    private $methodName;

    /**
     * @var ClassReflection
     */
    private $classReflection;

    /**
     * @var MethodReflection
     */
    private $originalMethodReflection;

    /**
     * @var ParameterReflection[]
     */
    private $parameters;

    /**
     * @var Type
     */
    private $returnType;

    /**
     * @var bool
     */
    private $isVariadic;

    /**
     * @param string                $methodName
     * @param ClassReflection       $classReflection
     * @param MethodReflection      $originalMethodReflection
     * @param ParameterReflection[] $parameters
     * @param null|Type             $returnType
     * @param bool                  $isVariadic
     */
    public function __construct(string $methodName, ClassReflection $classReflection, MethodReflection $originalMethodReflection, array $parameters, ?Type $returnType = null, bool $isVariadic = false) {
        $this->methodName = $methodName;
        $this->classReflection = $classReflection;
        $this->originalMethodReflection = $originalMethodReflection;
        $this->parameters = $parameters;
        $this->returnType = $returnType ?? new ObjectType(CDatabase_Query_Builder::class);
        $this->isVariadic = $isVariadic;
    }

    public function getDeclaringClass(): ClassReflection {
        return $this->classReflection;
    }

    public function isStatic(): bool {
        return true;
    }

    public function isPrivate(): bool {
        return false;
    }

    public function isPublic(): bool {
        return true;
    }

    public function getName(): string {
        return $this->methodName;
    }

    public function getPrototype(): ClassMemberReflection {
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getVariants(): array {
        return [
            new FunctionVariant(
                TemplateTypeMap::createEmpty(),
                null,
                $this->parameters,
                $this->isVariadic,
                $this->returnType
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

    public function getDocComment(): ?string {
        return null;
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
        return TrinaryLogic::createMaybe();
    }

    /**
     * @return MethodReflection
     */
    public function getOriginalMethodReflection(): MethodReflection {
        return $this->originalMethodReflection;
    }
}
