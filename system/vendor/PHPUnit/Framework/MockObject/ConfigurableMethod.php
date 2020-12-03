<?php
/*
 * This file is part of PHPUnit.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PHPUnit\Framework\MockObject;

use SebastianBergmann\Type\Type;

/**
 * @internal This class is not covered by the backward compatibility promise for PHPUnit
 */
final class ConfigurableMethod
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var Type
     */
    private $returnType;

    public function __construct($name, Type $returnType)
    {
        $this->name       = $name;
        $this->returnType = $returnType;
    }

    public function getName()
    {
        return $this->name;
    }

    public function mayReturn($value)
    {
        if ($value === null && $this->returnType->allowsNull()) {
            return true;
        }

        return $this->returnType->isAssignable(Type::fromValue($value, false));
    }

    public function getReturnTypeDeclaration()
    {
        return $this->returnType->asString();
    }
}
