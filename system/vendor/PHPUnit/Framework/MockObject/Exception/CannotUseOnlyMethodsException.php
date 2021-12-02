<?php
/*
 * This file is part of PHPUnit.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PHPUnit\Framework\MockObject\Exception;

use function sprintf;

/**
 * @internal This class is not covered by the backward compatibility promise for PHPUnit
 */
final class CannotUseOnlyMethodsException extends \PHPUnit\Framework\Exception\Exception implements Exception {
    public function __construct($type, $methodName) {
        parent::__construct(
            sprintf(
                'Trying to set mock method "%s" with onlyMethods, but it does not exist in class "%s". Use addMethods() for methods that do not exist in the class',
                $methodName,
                $type
            )
        );
    }
}
