<?php
/*
 * This file is part of PHPUnit.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PHPUnit\Framework\Exception;

use function debug_backtrace;
use function in_array;
use function lcfirst;
use function sprintf;

/**
 * @internal This class is not covered by the backward compatibility promise for PHPUnit
 */
final class InvalidArgumentException extends Exception
{
    public static function create($argument, $type)
    {
        $stack = debug_backtrace();

        return new self(
            sprintf(
                'Argument #%d of %s::%s() must be %s %s',
                $argument,
                $stack[1]['class'],
                $stack[1]['function'],
                in_array(lcfirst($type)[0], ['a', 'e', 'i', 'o', 'u'], true) ? 'an' : 'a',
                $type
            )
        );
    }

    public function __construct($message = '', $code = 0, ?\Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
