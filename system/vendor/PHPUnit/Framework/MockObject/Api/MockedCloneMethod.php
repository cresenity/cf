<?php
/*
 * This file is part of PHPUnit.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PHPUnit\Framework\MockObject\Api;

/**
 * @internal This trait is not covered by the backward compatibility promise for PHPUnit
 */
trait MockedCloneMethod {
    public function __clone() {
        $this->__phpunit_invocationMocker = clone $this->__phpunit_getInvocationHandler();
    }
}
