<?php
/*
 * This file is part of PHPUnit.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PHPUnit\Framework;

/**
 * @internal This class is not covered by the backward compatibility promise for PHPUnit
 */
final class PHPTAssertionFailedError extends SyntheticError
{
    /**
     * @var string
     */
    private $diff;

    public function __construct($message, $code, $file, $line, array $trace, $diff)
    {
        parent::__construct($message, $code, $file, $line, $trace);
        $this->diff = $diff;
    }

    public function getDiff()
    {
        return $this->diff;
    }
}
