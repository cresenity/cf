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

use function count;
use function assert;
use RecursiveIterator;
use PHPUnit\Framework\Exception\NoChildTestSuiteException;

/**
 * @internal This class is not covered by the backward compatibility promise for PHPUnit
 */
final class TestSuiteIterator implements RecursiveIterator {
    /**
     * @var int
     */
    private $position = 0;

    /**
     * @var Test[]
     */
    private $tests;

    public function __construct(TestSuite $testSuite) {
        $this->tests = $testSuite->tests();
    }

    public function rewind() {
        $this->position = 0;
    }

    public function valid() {
        return $this->position < count($this->tests);
    }

    public function key() {
        return $this->position;
    }

    public function current() {
        return $this->tests[$this->position];
    }

    public function next() {
        $this->position++;
    }

    /**
     * @throws NoChildTestSuiteException
     */
    public function getChildren() {
        if (!$this->hasChildren()) {
            throw new NoChildTestSuiteException(
                'The current item is not a TestSuite instance and therefore does not have any children.'
            );
        }

        $current = $this->current();

        assert($current instanceof TestSuite);

        return new self($current);
    }

    public function hasChildren() {
        return $this->valid() && $this->current() instanceof TestSuite;
    }
}
