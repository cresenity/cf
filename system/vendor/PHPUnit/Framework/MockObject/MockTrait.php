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

use function class_exists;

/**
 * @internal This class is not covered by the backward compatibility promise for PHPUnit
 */
final class MockTrait implements MockType
{
    /**
     * @var string
     */
    private $classCode;

    /**
     * @var string
     */
    private $mockName;

    public function __construct($classCode, $mockName)
    {
        $this->classCode = $classCode;
        $this->mockName  = $mockName;
    }

    public function generate()
    {
        if (!class_exists($this->mockName, false)) {
            eval($this->classCode);
        }

        return $this->mockName;
    }

    public function getClassCode()
    {
        return $this->classCode;
    }
}
