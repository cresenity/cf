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

use Throwable;
use function trim;
use function count;
use function assert;
use ReflectionClass;
use function sprintf;
use function get_class;
use PHPUnit\Util\Filter;
use PHPUnit\Util\Test as TestUtil;
use PHPUnit\Util\InvalidDataSetException;
use PHPUnit\Framework\Exception\Exception;
use PHPUnit\Framework\Exception\SkippedTestError;
use PHPUnit\Framework\Exception\IncompleteTestError;

/**
 * @internal This class is not covered by the backward compatibility promise for PHPUnit
 */
final class TestBuilder {
    public function build(ReflectionClass $theClass, $methodName) {
        $className = $theClass->getName();

        if (!$theClass->isInstantiable()) {
            return new ErrorTestCase(
                sprintf('Cannot instantiate class "%s".', $className)
            );
        }

        $backupSettings = TestUtil::getBackupSettings(
            $className,
            $methodName
        );

        $preserveGlobalState = TestUtil::getPreserveGlobalStateSettings(
            $className,
            $methodName
        );

        $runTestInSeparateProcess = TestUtil::getProcessIsolationSettings(
            $className,
            $methodName
        );

        $runClassInSeparateProcess = TestUtil::getClassProcessIsolationSettings(
            $className,
            $methodName
        );

        $constructor = $theClass->getConstructor();

        if ($constructor === null) {
            throw new Exception('No valid test provided.');
        }

        $parameters = $constructor->getParameters();

        // TestCase() or TestCase($name)
        if (count($parameters) < 2) {
            $test = $this->buildTestWithoutData($className);
        } // TestCase($name, $data)
        else {
            try {
                $data = TestUtil::getProvidedData(
                    $className,
                    $methodName
                );
            } catch (IncompleteTestError $e) {
                $message = sprintf(
                    "Test for %s::%s marked incomplete by data provider\n%s",
                    $className,
                    $methodName,
                    $this->throwableToString($e)
                );

                $data = new IncompleteTestCase($className, $methodName, $message);
            } catch (SkippedTestError $e) {
                $message = sprintf(
                    "Test for %s::%s skipped by data provider\n%s",
                    $className,
                    $methodName,
                    $this->throwableToString($e)
                );

                $data = new SkippedTestCase($className, $methodName, $message);
            } catch (Throwable $t) {
                $message = sprintf(
                    "The data provider specified for %s::%s is invalid.\n%s",
                    $className,
                    $methodName,
                    $this->throwableToString($t)
                );

                $data = new ErrorTestCase($message);
            }

            // Test method with @dataProvider.
            if (isset($data)) {
                $test = $this->buildDataProviderTestSuite(
                    $methodName,
                    $className,
                    $data,
                    $runTestInSeparateProcess,
                    $preserveGlobalState,
                    $runClassInSeparateProcess,
                    $backupSettings
                );
            } else {
                $test = $this->buildTestWithoutData($className);
            }
        }

        if ($test instanceof TestCase) {
            $test->setName($methodName);
            $this->configureTestCase(
                $test,
                $runTestInSeparateProcess,
                $preserveGlobalState,
                $runClassInSeparateProcess,
                $backupSettings
            );
        }

        return $test;
    }

    /**
     * @psalm-param class-string $className
     *
     * @param mixed $className
     */
    private function buildTestWithoutData($className) {
        return new $className();
    }

    /**
     * @psalm-param class-string $className
     *
     * @param mixed $methodName
     * @param mixed $className
     * @param mixed $data
     * @param mixed $runTestInSeparateProcess
     * @param mixed $preserveGlobalState
     * @param mixed $runClassInSeparateProcess
     */
    private function buildDataProviderTestSuite(
        $methodName,
        $className,
        $data,
        $runTestInSeparateProcess,
        $preserveGlobalState,
        $runClassInSeparateProcess,
        array $backupSettings
    ) {
        $dataProviderTestSuite = new DataProviderTestSuite(
            $className . '::' . $methodName
        );

        $groups = TestUtil::getGroups($className, $methodName);

        if ($data instanceof ErrorTestCase
            || $data instanceof SkippedTestCase
            || $data instanceof IncompleteTestCase) {
            $dataProviderTestSuite->addTest($data, $groups);
        } else {
            foreach ($data as $_dataName => $_data) {
                $_test = new $className($methodName, $_data, $_dataName);

                assert($_test instanceof TestCase);

                $this->configureTestCase(
                    $_test,
                    $runTestInSeparateProcess,
                    $preserveGlobalState,
                    $runClassInSeparateProcess,
                    $backupSettings
                );

                $dataProviderTestSuite->addTest($_test, $groups);
            }
        }

        return $dataProviderTestSuite;
    }

    private function configureTestCase(
        TestCase $test,
        $runTestInSeparateProcess,
        $preserveGlobalState,
        $runClassInSeparateProcess,
        array $backupSettings
    ) {
        if ($runTestInSeparateProcess) {
            $test->setRunTestInSeparateProcess(true);

            if ($preserveGlobalState !== null) {
                $test->setPreserveGlobalState($preserveGlobalState);
            }
        }

        if ($runClassInSeparateProcess) {
            $test->setRunClassInSeparateProcess(true);

            if ($preserveGlobalState !== null) {
                $test->setPreserveGlobalState($preserveGlobalState);
            }
        }

        if ($backupSettings['backupGlobals'] !== null) {
            $test->setBackupGlobals($backupSettings['backupGlobals']);
        }

        if ($backupSettings['backupStaticAttributes'] !== null) {
            $test->setBackupStaticAttributes(
                $backupSettings['backupStaticAttributes']
            );
        }
    }

    private function throwableToString(Throwable $t) {
        $message = $t->getMessage();

        if (empty(trim($message))) {
            $message = '<no message>';
        }

        if ($t instanceof InvalidDataSetException) {
            return sprintf(
                "%s\n%s",
                $message,
                Filter::getFilteredStacktrace($t)
            );
        }

        return sprintf(
            "%s: %s\n%s",
            get_class($t),
            $message,
            Filter::getFilteredStacktrace($t)
        );
    }
}
