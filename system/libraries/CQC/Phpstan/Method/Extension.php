<?php

use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Reflection\Php\PhpMethodReflectionFactory;
use PHPStan\Reflection\MethodsClassReflectionExtension;

/**
 * @internal
 */
final class CQC_Phpstan_Method_Extension implements MethodsClassReflectionExtension {
    /**
     * @var Kernel
     */
    private $kernel;

    /**
     * @var MethodReflection[]
     */
    private $methodReflections = [];

    public function __construct(PhpMethodReflectionFactory $methodReflectionFactory, ReflectionProvider $reflectionProvider, CQC_Phpstan_Method_Kernel $kernel = null) {
        $this->kernel = $kernel ?? new CQC_Phpstan_Method_Kernel($methodReflectionFactory, $reflectionProvider);
    }

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool {
        if ($classReflection->getName() === CModel::class) {
            return false;
        }

        if (array_key_exists($methodName . '-' . $classReflection->getName(), $this->methodReflections)) {
            return true;
        }

        $passable = $this->kernel->handle($classReflection, $methodName);

        $found = $passable->hasFound();

        if ($found) {
            $this->methodReflections[$methodName . '-' . $classReflection->getName()] = $passable->getMethodReflection();
        }

        return $found;
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection {
        return $this->methodReflections[$methodName . '-' . $classReflection->getName()];
    }
}
