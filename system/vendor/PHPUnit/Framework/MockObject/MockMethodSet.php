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

use function array_key_exists;
use function array_values;
use function strtolower;

/**
 * @internal This class is not covered by the backward compatibility promise for PHPUnit
 */
final class MockMethodSet
{
    /**
     * @var MockMethod[]
     */
    private $methods = [];

    public function addMethods(MockMethod ...$methods)
    {
        foreach ($methods as $method) {
            $this->methods[strtolower($method->getName())] = $method;
        }
    }

    /**
     * @return MockMethod[]
     */
    public function asArray()
    {
        return array_values($this->methods);
    }

    public function hasMethod($methodName)
    {
        return array_key_exists(strtolower($methodName), $this->methods);
    }
}
