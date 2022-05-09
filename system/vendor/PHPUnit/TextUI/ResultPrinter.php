<?php
/*
 * This file is part of PHPUnit.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PHPUnit\TextUI;

use PHPUnit\Framework\TestResult;
use PHPUnit\Framework\TestListener;

/**
 * @no-named-arguments Parameter names are not covered by the backward compatibility promise for PHPUnit
 */
interface ResultPrinter extends TestListener {
    public function printResult(TestResult $result);

    public function write($buffer);
}
