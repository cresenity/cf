<?php
/*
 * This file is part of PHPUnit.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PHPUnit\Runner;

use function array_diff;
use function array_merge;
use function array_reverse;
use function array_splice;
use function count;
use function in_array;
use function max;
use function shuffle;
use function usort;
use PHPUnit\Framework\DataProviderTestSuite;
use PHPUnit\Framework\Reorderable;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Util\Test as TestUtil;

/**
 * @internal This class is not covered by the backward compatibility promise for PHPUnit
 */
final class TestSuiteSorter
{
    /**
     * @var int
     */
    const ORDER_DEFAULT = 0;

    /**
     * @var int
     */
    const ORDER_RANDOMIZED = 1;

    /**
     * @var int
     */
    const ORDER_REVERSED = 2;

    /**
     * @var int
     */
    const ORDER_DEFECTS_FIRST = 3;

    /**
     * @var int
     */
    const ORDER_DURATION = 4;

    /**
     * Order tests by @size annotation 'small', 'medium', 'large'.
     *
     * @var int
     */
    const ORDER_SIZE = 5;

    /**
     * List of sorting weights for all test result codes. A higher number gives higher priority.
     */
    public $DEFECT_SORT_WEIGHT = [
        BaseTestRunner::STATUS_ERROR      => 6,
        BaseTestRunner::STATUS_FAILURE    => 5,
        BaseTestRunner::STATUS_WARNING    => 4,
        BaseTestRunner::STATUS_INCOMPLETE => 3,
        BaseTestRunner::STATUS_RISKY      => 2,
        BaseTestRunner::STATUS_SKIPPED    => 1,
        BaseTestRunner::STATUS_UNKNOWN    => 0,
    ];

    public $SIZE_SORT_WEIGHT = [
        TestUtil::SMALL   => 1,
        TestUtil::MEDIUM  => 2,
        TestUtil::LARGE   => 3,
        TestUtil::UNKNOWN => 4,
    ];

    /**
     * @var array<string, int> Associative array of (=> DEFECT_SORT_WEIGHT) elements
     */
    private $defectSortOrder = [];

    /**
     * @var TestResultCache
     */
    private $cache;

    /**
     * @var array<string> A list of normalized names of tests before reordering
     */
    private $originalExecutionOrder = [];

    /**
     * @var array<string> A list of normalized names of tests affected by reordering
     */
    private $executionOrder = [];

    public function __construct(TestResultCache $cache = null)
    {
        $this->cache = $cache!=null ? $cache : new NullTestResultCache;
    }

    /**
     * @throws Exception
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public function reorderTestsInSuite(Test $suite, $order, $resolveDependencies, $orderDefects, $isRootTestSuite = true)
    {
        $allowedOrders = [
            self::ORDER_DEFAULT,
            self::ORDER_REVERSED,
            self::ORDER_RANDOMIZED,
            self::ORDER_DURATION,
            self::ORDER_SIZE,
        ];

        if (!in_array($order, $allowedOrders, true)) {
            throw new Exception(
                '$order must be one of TestSuiteSorter::ORDER_[DEFAULT|REVERSED|RANDOMIZED|DURATION|SIZE]'
            );
        }

        $allowedOrderDefects = [
            self::ORDER_DEFAULT,
            self::ORDER_DEFECTS_FIRST,
        ];

        if (!in_array($orderDefects, $allowedOrderDefects, true)) {
            throw new Exception(
                '$orderDefects must be one of TestSuiteSorter::ORDER_DEFAULT, TestSuiteSorter::ORDER_DEFECTS_FIRST'
            );
        }

        if ($isRootTestSuite) {
            $this->originalExecutionOrder = $this->calculateTestExecutionOrder($suite);
        }

        if ($suite instanceof TestSuite) {
            foreach ($suite as $_suite) {
                $this->reorderTestsInSuite($_suite, $order, $resolveDependencies, $orderDefects, false);
            }

            if ($orderDefects === self::ORDER_DEFECTS_FIRST) {
                $this->addSuiteToDefectSortOrder($suite);
            }

            $this->sort($suite, $order, $resolveDependencies, $orderDefects);
        }

        if ($isRootTestSuite) {
            $this->executionOrder = $this->calculateTestExecutionOrder($suite);
        }
    }

    public function getOriginalExecutionOrder()
    {
        return $this->originalExecutionOrder;
    }

    public function getExecutionOrder()
    {
        return $this->executionOrder;
    }

    private function sort(TestSuite $suite, $order, $resolveDependencies, $orderDefects)
    {
        if (empty($suite->tests())) {
            return;
        }

        if ($order === self::ORDER_REVERSED) {
            $suite->setTests($this->reverse($suite->tests()));
        } elseif ($order === self::ORDER_RANDOMIZED) {
            $suite->setTests($this->randomize($suite->tests()));
        } elseif ($order === self::ORDER_DURATION && $this->cache !== null) {
            $suite->setTests($this->sortByDuration($suite->tests()));
        } elseif ($order === self::ORDER_SIZE) {
            $suite->setTests($this->sortBySize($suite->tests()));
        }

        if ($orderDefects === self::ORDER_DEFECTS_FIRST && $this->cache !== null) {
            $suite->setTests($this->sortDefectsFirst($suite->tests()));
        }

        if ($resolveDependencies && !($suite instanceof DataProviderTestSuite)) {
            /** @var TestCase[] $tests */
            $tests = $suite->tests();

            $suite->setTests($this->resolveDependencies($tests));
        }
    }

    /**
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    private function addSuiteToDefectSortOrder(TestSuite $suite)
    {
        $max = 0;

        foreach ($suite->tests() as $test) {
            if (!$test instanceof Reorderable) {
                continue;
            }

            if (!isset($this->defectSortOrder[$test->sortId()])) {
                $this->defectSortOrder[$test->sortId()] = self::$DEFECT_SORT_WEIGHT[$this->cache->getState($test->sortId())];
                $max                                    = max($max, $this->defectSortOrder[$test->sortId()]);
            }
        }

        $this->defectSortOrder[$suite->sortId()] = $max;
    }

    private function reverse(array $tests)
    {
        return array_reverse($tests);
    }

    private function randomize(array $tests)
    {
        shuffle($tests);

        return $tests;
    }

    private function sortDefectsFirst(array $tests)
    {
        usort(
            $tests,
            /**
             * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
             */
            function ($left, $right) {
                return $this->cmpDefectPriorityAndTime($left, $right);
            }
        );

        return $tests;
    }

    private function sortByDuration(array $tests)
    {
        usort(
            $tests,
            /**
             * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
             */
            function ($left, $right) {
                return $this->cmpDuration($left, $right);
            }
        );

        return $tests;
    }

    private function sortBySize(array $tests)
    {
        usort(
            $tests,
            /**
             * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
             */
            function ($left, $right) {
                return $this->cmpSize($left, $right);
            }
        );

        return $tests;
    }

    /**
     * Comparator callback function to sort tests for "reach failure as fast as possible".
     *
     * 1. sort tests by defect weight defined in self::DEFECT_SORT_WEIGHT
     * 2. when tests are equally defective, sort the fastest to the front
     * 3. do not reorder successful tests
     *
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    private function cmpDefectPriorityAndTime(Test $a, Test $b)
    {
        if (!($a instanceof Reorderable && $b instanceof Reorderable)) {
            return 0;
        }

        $priorityA = isset($this->defectSortOrder[$a->sortId()]) ? $this->defectSortOrder[$a->sortId()] : 0;
        $priorityB = isset($this->defectSortOrder[$b->sortId()]) ? $this->defectSortOrder[$b->sortId()] : 0;

        
        $spaceshipResult = ($priorityB < $priorityA) ? -1 : 1;
        if ($priorityB == $priorityA) {
            $spaceshipResult = 0;
        }
        
        if ($spaceshipResult) {
            // Sort defect weight descending
            return $spaceshipResult;
        }

        if ($priorityA || $priorityB) {
            return $this->cmpDuration($a, $b);
        }

        // do not change execution order
        return 0;
    }

    /**
     * Compares test duration for sorting tests by duration ascending.
     *
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    private function cmpDuration(Test $a, Test $b)
    {
        if (!($a instanceof Reorderable && $b instanceof Reorderable)) {
            return 0;
        }
        
        $operandA = $this->cache->getTime($a->sortId());
        $operandB = $this->cache->getTime($b->sortId());
        $spaceshipResult = ($operandA < $operandB) ? -1 : 1;
        if ($operandA == $operandB) {
            $spaceshipResult = 0;
        }
        return $spaceshipResult;
    }

    /**
     * Compares test size for sorting tests small->medium->large->unknown.
     */
    private function cmpSize(Test $a, Test $b)
    {
        $sizeA = ($a instanceof TestCase || $a instanceof DataProviderTestSuite)
            ? $a->getSize()
            : TestUtil::UNKNOWN;
        $sizeB = ($b instanceof TestCase || $b instanceof DataProviderTestSuite)
            ? $b->getSize()
            : TestUtil::UNKNOWN;

        $operandA = self::$SIZE_SORT_WEIGHT[$sizeA];
        $operandB = self::$SIZE_SORT_WEIGHT[$sizeB];
        $spaceshipResult = ($operandA < $operandB) ? -1 : 1;
        if ($operandA == $operandB) {
            $spaceshipResult = 0;
        }
        return $spaceshipResult;
    }

    /**
     * Reorder Tests within a TestCase in such a way as to resolve as many dependencies as possible.
     * The algorithm will leave the tests in original running order when it can.
     * For more details see the documentation for test dependencies.
     *
     * Short description of algorithm:
     * 1. Pick the next Test from remaining tests to be checked for dependencies.
     * 2. If the test has no dependencies: mark done, start again from the top
     * 3. If the test has dependencies but none left to do: mark done, start again from the top
     * 4. When we reach the end add any leftover tests to the end. These will be marked 'skipped' during execution.
     *
     * @param array<DataProviderTestSuite|TestCase> $tests
     *
     * @return array<DataProviderTestSuite|TestCase>
     */
    private function resolveDependencies(array $tests)
    {
        $newTestOrder = [];
        $i            = 0;
        $provided     = [];

        do {
            if ([] === array_diff($tests[$i]->requires(), $provided)) {
                $provided     = array_merge($provided, $tests[$i]->provides());
                $newTestOrder = array_merge($newTestOrder, array_splice($tests, $i, 1));
                $i            = 0;
            } else {
                $i++;
            }
        } while (!empty($tests) && ($i < count($tests)));

        return array_merge($newTestOrder, $tests);
    }

    /**
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    private function calculateTestExecutionOrder(Test $suite)
    {
        $tests = [];

        if ($suite instanceof TestSuite) {
            foreach ($suite->tests() as $test) {
                if (!$test instanceof TestSuite && $test instanceof Reorderable) {
                    $tests[] = $test->sortId();
                } else {
                    $tests = array_merge($tests, $this->calculateTestExecutionOrder($test));
                }
            }
        }

        return $tests;
    }
}
