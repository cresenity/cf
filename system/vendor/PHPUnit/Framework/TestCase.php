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
use SoapClient;
use const LC_ALL;
use const LC_TIME;
use const PHP_EOL;
use function trim;
use const LC_CTYPE;
use function chdir;
use function count;
use function getcwd;
use function is_int;
use function strpos;
use function substr;
use ReflectionClass;
use const LC_COLLATE;
use const LC_NUMERIC;
use function defined;
use function explode;
use function implode;
use function ini_set;
use function sprintf;
use Prophecy\Prophet;
use const LC_MONETARY;
use DeepCopy\DeepCopy;
use function basename;
use function in_array;
use function is_array;
use function ob_start;
use function pathinfo;
use PHPUnit\Util\Type;
use const PHP_URL_PATH;
use function array_pop;
use function get_class;
use function is_object;
use function is_string;
use function parse_url;
use function serialize;
use function setlocale;
use function array_flip;
use function array_keys;
use function var_export;
use ReflectionException;
use function array_merge;
use function is_callable;
use function array_filter;
use function array_search;
use function array_unique;
use function array_values;
use function class_exists;
use function ob_end_clean;
use function ob_get_level;
use function preg_replace;
use function method_exists;
use const PATHINFO_FILENAME;
use function call_user_func;
use function clearstatcache;
use function debug_backtrace;
use function ob_get_contents;
use PHPUnit\Util\GlobalState;
use function get_include_path;
use PHPUnit\Runner\PhptTestCase;
use function libxml_clear_errors;
use PHPUnit\Framework\Error\Error;
use PHPUnit\Runner\BaseTestRunner;
use PHPUnit\Util\Test as TestUtil;
use SebastianBergmann\Diff\Differ;
use PHPUnit\Framework\Error\Notice;
use Prophecy\Prophecy\MethodProphecy;
use Prophecy\Prophecy\ObjectProphecy;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\Error\Deprecated;
use PHPUnit\Framework\Exception\Warning;
use PHPUnit\Util\PHP\AbstractPhpProcess;
use SebastianBergmann\Exporter\Exporter;
use SebastianBergmann\Template\Template;
use PHPUnit\Framework\Exception\Exception;
use SebastianBergmann\GlobalState\Restorer;
use SebastianBergmann\GlobalState\Snapshot;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Util\Exception as UtilException;
use SebastianBergmann\Comparator\Comparator;
use PHPUnit\Framework\MockObject\MockBuilder;
use PHPUnit\Framework\Exception\RiskyTestError;
use PHPUnit\Framework\Exception\SkippedTestError;
use PHPUnit\Framework\MockObject\Stub\ReturnStub;
use SebastianBergmann\ObjectEnumerator\Enumerator;
use PHPUnit\Framework\Error\Warning as WarningError;
use PHPUnit\Framework\Exception\AssertionFailedError;
use Prophecy\Exception\Prediction\PredictionException;
use PHPUnit\Framework\Constraint\Exception\ExceptionCode;
use PHPUnit\Framework\Constraint\Exception\ExceptionMessage;
use PHPUnit\Framework\MockObject\Generator as MockGenerator;
use SebastianBergmann\Comparator\Factory as ComparatorFactory;
use PHPUnit\Framework\MockObject\Stub\Exception as ExceptionStub;
use PHPUnit\Framework\MockObject\Stub\ReturnSelf as ReturnSelfStub;
use SebastianBergmann\GlobalState\ExcludeList as GlobalStateExcludeList;
use PHPUnit\Framework\MockObject\Rule\InvokedCount as InvokedCountMatcher;
use PHPUnit\Framework\MockObject\Stub\ReturnArgument as ReturnArgumentStub;
use PHPUnit\Framework\MockObject\Stub\ReturnCallback as ReturnCallbackStub;
use PHPUnit\Framework\MockObject\Stub\ReturnValueMap as ReturnValueMapStub;
use PHPUnit\Framework\Constraint\Exception\Exception as ExceptionConstraint;
use PHPUnit\Framework\Constraint\Exception\ExceptionMessageRegularExpression;
use PHPUnit\Framework\MockObject\Rule\InvokedAtIndex as InvokedAtIndexMatcher;
use PHPUnit\Framework\MockObject\Stub\ConsecutiveCalls as ConsecutiveCallsStub;
use PHPUnit\Framework\MockObject\Rule\AnyInvokedCount as AnyInvokedCountMatcher;
use PHPUnit\Framework\MockObject\Rule\InvokedAtLeastOnce as InvokedAtLeastOnceMatcher;
use PHPUnit\Framework\MockObject\Rule\InvokedAtMostCount as InvokedAtMostCountMatcher;
use PHPUnit\Framework\MockObject\Rule\InvokedAtLeastCount as InvokedAtLeastCountMatcher;

/**
 * @no-named-arguments Parameter names are not covered by the backward compatibility promise for PHPUnit
 */
abstract class TestCase extends Assert implements Reorderable, SelfDescribing, Test {
    const LOCALE_CATEGORIES = [LC_ALL, LC_COLLATE, LC_CTYPE, LC_MONETARY, LC_NUMERIC, LC_TIME];

    /**
     * @var ?bool
     */
    protected $backupGlobals;

    /**
     * @var string[]
     */
    protected $backupGlobalsExcludeList = [];

    /**
     * @var string[]
     *
     * @deprecated Use $backupGlobalsExcludeList instead
     */
    protected $backupGlobalsBlacklist = [];

    /**
     * @var bool
     */
    protected $backupStaticAttributes;

    /**
     * @var array<string,array<int,string>>
     */
    protected $backupStaticAttributesExcludeList = [];

    /**
     * @var array<string,array<int,string>>
     *
     * @deprecated Use $backupStaticAttributesExcludeList instead
     */
    protected $backupStaticAttributesBlacklist = [];

    /**
     * @var bool
     */
    protected $runTestInSeparateProcess;

    /**
     * @var bool
     */
    protected $preserveGlobalState = true;

    /**
     * @var list<ExecutionOrderDependency>
     */
    protected $providedTests = [];

    /**
     * @var null|bool
     */
    private $backupStaticProperties = null;

    /**
     * @var bool
     */
    private $runClassInSeparateProcess;

    /**
     * @var bool
     */
    private $inIsolation = false;

    /**
     * @var array
     */
    private $data;

    /**
     * @var int|string
     */
    private $dataName;

    /**
     * @var null|string
     */
    private $expectedException;

    /**
     * @var null|string
     */
    private $expectedExceptionMessage;

    /**
     * @var null|string
     */
    private $expectedExceptionMessageRegExp;

    /**
     * @var null|int|string
     */
    private $expectedExceptionCode;

    /**
     * @var string
     */
    private $name = '';

    /**
     * @var list<ExecutionOrderDependency>
     */
    private $dependencies = [];

    /**
     * @var array
     */
    private $dependencyInput = [];

    /**
     * @var array<string,string>
     */
    private $iniSettings = [];

    /**
     * @var array
     */
    private $locale = [];

    /**
     * @var MockObject[]
     */
    private $mockObjects = [];

    /**
     * @var MockGenerator
     */
    private $mockObjectGenerator;

    /**
     * @var int
     */
    private $status = BaseTestRunner::STATUS_UNKNOWN;

    /**
     * @var string
     */
    private $statusMessage = '';

    /**
     * @var int
     */
    private $numAssertions = 0;

    /**
     * @var TestResult
     */
    private $result;

    /**
     * @var mixed
     */
    private $testResult;

    /**
     * @var string
     */
    private $output = '';

    /**
     * @var string
     */
    private $outputExpectedRegex;

    /**
     * @var string
     */
    private $outputExpectedString;

    /**
     * @var mixed
     */
    private $outputCallback = false;

    /**
     * @var bool
     */
    private $outputBufferingActive = false;

    /**
     * @var int
     */
    private $outputBufferingLevel;

    /**
     * @var bool
     */
    private $outputRetrievedForAssertion = false;

    /**
     * @var Snapshot
     */
    private $snapshot;

    /**
     * @var \Prophecy\Prophet
     */
    private $prophet;

    /**
     * @var bool
     */
    private $beStrictAboutChangesToGlobalState = false;

    /**
     * @var bool
     */
    private $registerMockObjectsFromTestArgumentsRecursively = false;

    /**
     * @var string[]
     */
    private $warnings = [];

    /**
     * @var string[]
     */
    private $groups = [];

    /**
     * @var bool
     */
    private $doesNotPerformAssertions = false;

    /**
     * @var Comparator[]
     */
    private $customComparators = [];

    /**
     * @var string[]
     */
    private $doubledTypes = [];

    /**
     * Returns a matcher that matches when the method is executed
     * zero or more times.
     *
     * @return AnyInvokedCountMatcher
     */
    public static function any() {
        return new AnyInvokedCountMatcher();
    }

    /**
     * Returns a matcher that matches when the method is never executed.
     *
     * @return InvokedCountMatcher
     */
    public static function never() {
        return new InvokedCountMatcher(0);
    }

    /**
     * Returns a matcher that matches when the method is executed
     * at least N times.
     *
     * @param mixed $requiredInvocations
     *
     * @return InvokedAtLeastCountMatcher
     */
    public static function atLeast($requiredInvocations) {
        return new InvokedAtLeastCountMatcher(
            $requiredInvocations
        );
    }

    /**
     * Returns a matcher that matches when the method is executed at least once.
     *
     * @return InvokedAtLeastOnceMatcher
     */
    public static function atLeastOnce() {
        return new InvokedAtLeastOnceMatcher();
    }

    /**
     * Returns a matcher that matches when the method is executed exactly once.
     *
     * @return InvokedCountMatcher
     */
    public static function once() {
        return new InvokedCountMatcher(1);
    }

    /**
     * Returns a matcher that matches when the method is executed
     * exactly $count times.
     *
     * @param int $count
     *
     * @return InvokedCountMatcher
     */
    public static function exactly($count) {
        return new InvokedCountMatcher($count);
    }

    /**
     * Returns a matcher that matches when the method is executed
     * at most N times.
     *
     * @param int $allowedInvocations
     *
     * @return InvokedAtMostCountMatcher
     */
    public static function atMost($allowedInvocations) {
        return new InvokedAtMostCountMatcher($allowedInvocations);
    }

    /**
     * @param mixed $value
     *
     * @return ReturnStub
     */
    public static function returnValue($value) {
        return new ReturnStub($value);
    }

    /**
     * @param array $valueMap
     *
     * @return ReturnValueMapStub
     */
    public static function returnValueMap(array $valueMap) {
        return new ReturnValueMapStub($valueMap);
    }

    public static function returnArgument($argumentIndex) {
        return new ReturnArgumentStub($argumentIndex);
    }

    public static function returnCallback($callback) {
        return new ReturnCallbackStub($callback);
    }

    /**
     * Returns the current object.
     *
     * This method is useful when mocking a fluent interface.
     */
    public static function returnSelf() {
        return new ReturnSelfStub();
    }

    public static function throwException(Throwable $exception) {
        return new ExceptionStub($exception);
    }

    public static function onConsecutiveCalls(...$args) {
        return new ConsecutiveCallsStub($args);
    }

    /**
     * @param int|string $dataName
     * @param null|mixed $name
     *
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     */
    public function __construct($name = null, array $data = [], $dataName = '') {
        if ($name !== null) {
            $this->setName($name);
        }

        $this->data = $data;
        $this->dataName = $dataName;
    }

    /**
     * This method is called before the first test of this test class is run.
     */
    public static function setUpBeforeClass() {
    }

    /**
     * This method is called after the last test of this test class is run.
     */
    public static function tearDownAfterClass() {
    }

    /**
     * This method is called before each test.
     */
    protected function setUp() {
    }

    /**
     * This method is called after each test.
     */
    protected function tearDown() {
    }

    /**
     * Returns a string representation of the test case.
     *
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @throws Exception
     */
    public function toString() {
        try {
            $class = new ReflectionClass($this);
            // @codeCoverageIgnoreStart
        } catch (ReflectionException $e) {
            throw new Exception(
                $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
        // @codeCoverageIgnoreEnd

        $buffer = sprintf(
            '%s::%s',
            $class->name,
            $this->getName(false)
        );

        return $buffer . $this->getDataSetAsString();
    }

    #[\ReturnTypeWillChange]
    public function count() {
        return 1;
    }

    public function getActualOutputForAssertion() {
        $this->outputRetrievedForAssertion = true;

        return $this->getActualOutput();
    }

    public function expectOutputRegex($expectedRegex) {
        $this->outputExpectedRegex = $expectedRegex;
    }

    public function expectOutputString($expectedString) {
        $this->outputExpectedString = $expectedString;
    }

    /**
     * @psalm-param class-string<\Throwable> $exception
     *
     * @param mixed $exception
     */
    public function expectException($exception) {
        // @codeCoverageIgnoreStart
        switch ($exception) {
            case Deprecated::class:
                $this->addWarning('Support for using expectException() with PHPUnit\Framework\Error\Deprecated is deprecated and will be removed in PHPUnit 10. Use expectDeprecation() instead.');

                break;

            case Error::class:
                $this->addWarning('Support for using expectException() with PHPUnit\Framework\Error\Error is deprecated and will be removed in PHPUnit 10. Use expectError() instead.');

                break;

            case Notice::class:
                $this->addWarning('Support for using expectException() with PHPUnit\Framework\Error\Notice is deprecated and will be removed in PHPUnit 10. Use expectNotice() instead.');

                break;

            case WarningError::class:
                $this->addWarning('Support for using expectException() with PHPUnit\Framework\Error\Warning is deprecated and will be removed in PHPUnit 10. Use expectWarning() instead.');

                break;
        }
        // @codeCoverageIgnoreEnd

        $this->expectedException = $exception;
    }

    /**
     * @param int|string $code
     */
    public function expectExceptionCode($code) {
        $this->expectedExceptionCode = $code;
    }

    public function expectExceptionMessage($message) {
        $this->expectedExceptionMessage = $message;
    }

    public function expectExceptionMessageMatches($regularExpression) {
        $this->expectedExceptionMessageRegExp = $regularExpression;
    }

    /**
     * Sets up an expectation for an exception to be raised by the code under test.
     * Information for expected exception class, expected exception message, and
     * expected exception code are retrieved from a given Exception object.
     */
    public function expectExceptionObject(Exception $exception) {
        $this->expectException(get_class($exception));
        $this->expectExceptionMessage($exception->getMessage());
        $this->expectExceptionCode($exception->getCode());
    }

    public function expectNotToPerformAssertions() {
        $this->doesNotPerformAssertions = true;
    }

    public function expectDeprecation() {
        $this->expectedException = Deprecated::class;
    }

    public function expectDeprecationMessage($message) {
        $this->expectExceptionMessage($message);
    }

    public function expectDeprecationMessageMatches($regularExpression) {
        $this->expectExceptionMessageMatches($regularExpression);
    }

    public function expectNotice() {
        $this->expectedException = Notice::class;
    }

    public function expectNoticeMessage($message) {
        $this->expectExceptionMessage($message);
    }

    public function expectNoticeMessageMatches($regularExpression) {
        $this->expectExceptionMessageMatches($regularExpression);
    }

    public function expectWarning() {
        $this->expectedException = WarningError::class;
    }

    public function expectWarningMessage($message) {
        $this->expectExceptionMessage($message);
    }

    public function expectWarningMessageMatches($regularExpression) {
        $this->expectExceptionMessageMatches($regularExpression);
    }

    public function expectError() {
        $this->expectedException = Error::class;
    }

    public function expectErrorMessage($message) {
        $this->expectExceptionMessage($message);
    }

    public function expectErrorMessageMatches($regularExpression) {
        $this->expectExceptionMessageMatches($regularExpression);
    }

    public function getStatus() {
        return $this->status;
    }

    public function markAsRisky() {
        $this->status = BaseTestRunner::STATUS_RISKY;
    }

    public function getStatusMessage() {
        return $this->statusMessage;
    }

    public function hasFailed() {
        $status = $this->getStatus();

        return $status === BaseTestRunner::STATUS_FAILURE || $status === BaseTestRunner::STATUS_ERROR;
    }

    /**
     * Runs the test case and collects the results in a TestResult object.
     * If no TestResult object is passed a new one will be created.
     *
     * @throws CodeCoverageException
     * @throws UtilException
     * @throws \SebastianBergmann\CodeCoverage\InvalidArgumentException
     * @throws \SebastianBergmann\CodeCoverage\UnintentionallyCoveredCodeException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public function run(?TestResult $result = null) {
        if ($result === null) {
            $result = $this->createResult();
        }

        if (!$this instanceof ErrorTestCase && !$this instanceof WarningTestCase) {
            $this->setTestResultObject($result);
        }

        if (!$this instanceof ErrorTestCase
            && !$this instanceof WarningTestCase
            && !$this instanceof SkippedTestCase
            && !$this->handleDependencies()) {
            return $result;
        }

        if ($this->runInSeparateProcess()) {
            $runEntireClass = $this->runClassInSeparateProcess && !$this->runTestInSeparateProcess;

            try {
                $class = new ReflectionClass($this);
                // @codeCoverageIgnoreStart
            } catch (ReflectionException $e) {
                throw new Exception(
                    $e->getMessage(),
                    (int) $e->getCode(),
                    $e
                );
            }
            // @codeCoverageIgnoreEnd

            if ($runEntireClass) {
                $template = new Template(
                    __DIR__ . '/../Util/PHP/Template/TestCaseClass.tpl'
                );
            } else {
                $template = new Template(
                    __DIR__ . '/../Util/PHP/Template/TestCaseMethod.tpl'
                );
            }

            if ($this->preserveGlobalState) {
                $constants = GlobalState::getConstantsAsString();
                $globals = GlobalState::getGlobalsAsString();
                $includedFiles = GlobalState::getIncludedFilesAsString();
                $iniSettings = GlobalState::getIniSettingsAsString();
            } else {
                $constants = '';

                if (!empty($GLOBALS['__PHPUNIT_BOOTSTRAP'])) {
                    $globals = '$GLOBALS[\'__PHPUNIT_BOOTSTRAP\'] = ' . var_export($GLOBALS['__PHPUNIT_BOOTSTRAP'], true) . ";\n";
                } else {
                    $globals = '';
                }

                $includedFiles = '';
                $iniSettings = '';
            }

            $coverage = $result->getCollectCodeCoverageInformation() ? 'true' : 'false';
            $isStrictAboutTestsThatDoNotTestAnything = $result->isStrictAboutTestsThatDoNotTestAnything() ? 'true' : 'false';
            $isStrictAboutOutputDuringTests = $result->isStrictAboutOutputDuringTests() ? 'true' : 'false';
            $enforcesTimeLimit = $result->enforcesTimeLimit() ? 'true' : 'false';
            $isStrictAboutTodoAnnotatedTests = $result->isStrictAboutTodoAnnotatedTests() ? 'true' : 'false';
            $isStrictAboutResourceUsageDuringSmallTests = $result->isStrictAboutResourceUsageDuringSmallTests() ? 'true' : 'false';

            if (defined('PHPUNIT_COMPOSER_INSTALL')) {
                $composerAutoload = var_export(PHPUNIT_COMPOSER_INSTALL, true);
            } else {
                $composerAutoload = '\'\'';
            }

            if (defined('__PHPUNIT_PHAR__')) {
                $phar = var_export(__PHPUNIT_PHAR__, true);
            } else {
                $phar = '\'\'';
            }

            $codeCoverage = $result->getCodeCoverage();
            $codeCoverageFilter = null;
            $cachesStaticAnalysis = 'false';
            $codeCoverageCacheDirectory = null;
            $driverMethod = 'forLineCoverage';

            if ($codeCoverage) {
                $codeCoverageFilter = $codeCoverage->filter();

                if ($codeCoverage->collectsBranchAndPathCoverage()) {
                    $driverMethod = 'forLineAndPathCoverage';
                }

                if ($codeCoverage->cachesStaticAnalysis()) {
                    $cachesStaticAnalysis = 'true';
                    $codeCoverageCacheDirectory = $codeCoverage->cacheDirectory();
                }
            }

            $data = var_export(serialize($this->data), true);
            $dataName = var_export($this->dataName, true);
            $dependencyInput = var_export(serialize($this->dependencyInput), true);
            $includePath = var_export(get_include_path(), true);
            $codeCoverageFilter = var_export(serialize($codeCoverageFilter), true);
            $codeCoverageCacheDirectory = var_export(serialize($codeCoverageCacheDirectory), true);
            // must do these fixes because TestCaseMethod.tpl has unserialize('{data}') in it, and we can't break BC
            // the lines above used to use addcslashes() rather than var_export(), which breaks null byte escape sequences
            $data = "'." . $data . ".'";
            $dataName = "'.(" . $dataName . ").'";
            $dependencyInput = "'." . $dependencyInput . ".'";
            $includePath = "'." . $includePath . ".'";
            $codeCoverageFilter = "'." . $codeCoverageFilter . ".'";
            $codeCoverageCacheDirectory = "'." . $codeCoverageCacheDirectory . ".'";

            $configurationFilePath = isset($GLOBALS['__PHPUNIT_CONFIGURATION_FILE']) ? $GLOBALS['__PHPUNIT_CONFIGURATION_FILE'] : '';

            $var = [
                'composerAutoload' => $composerAutoload,
                'phar' => $phar,
                'filename' => $class->getFileName(),
                'className' => $class->getName(),
                'collectCodeCoverageInformation' => $coverage,
                'cachesStaticAnalysis' => $cachesStaticAnalysis,
                'codeCoverageCacheDirectory' => $codeCoverageCacheDirectory,
                'driverMethod' => $driverMethod,
                'data' => $data,
                'dataName' => $dataName,
                'dependencyInput' => $dependencyInput,
                'constants' => $constants,
                'globals' => $globals,
                'include_path' => $includePath,
                'included_files' => $includedFiles,
                'iniSettings' => $iniSettings,
                'isStrictAboutTestsThatDoNotTestAnything' => $isStrictAboutTestsThatDoNotTestAnything,
                'isStrictAboutOutputDuringTests' => $isStrictAboutOutputDuringTests,
                'enforcesTimeLimit' => $enforcesTimeLimit,
                'isStrictAboutTodoAnnotatedTests' => $isStrictAboutTodoAnnotatedTests,
                'isStrictAboutResourceUsageDuringSmallTests' => $isStrictAboutResourceUsageDuringSmallTests,
                'codeCoverageFilter' => $codeCoverageFilter,
                'configurationFilePath' => $configurationFilePath,
                'name' => $this->getName(false),
            ];

            if (!$runEntireClass) {
                $var['methodName'] = $this->name;
            }

            $template->setVar($var);

            $php = AbstractPhpProcess::factory();
            $php->runTestJob($template->render(), $this, $result);
        } else {
            $result->run($this);
        }

        $this->result = null;

        return $result;
    }

    /**
     * Returns a builder object to create mock objects using a fluent interface.
     *
     * @psalm-template RealInstanceType of object
     * @psalm-param class-string<RealInstanceType> $className
     * @psalm-return MockBuilder<RealInstanceType>
     *
     * @param mixed $className
     */
    public function getMockBuilder($className) {
        $this->recordDoubledType($className);

        return new MockBuilder($this, $className);
    }

    public function registerComparator(Comparator $comparator) {
        ComparatorFactory::getInstance()->register($comparator);

        $this->customComparators[] = $comparator;
    }

    /**
     * @return string[]
     *
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     */
    public function doubledTypes() {
        return array_unique($this->doubledTypes);
    }

    /**
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     */
    public function getGroups() {
        return $this->groups;
    }

    /**
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     */
    public function setGroups(array $groups) {
        $this->groups = $groups;
    }

    /**
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     *
     * @param mixed $withDataSet
     *
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public function getName($withDataSet = true) {
        if ($withDataSet) {
            return $this->name . $this->getDataSetAsString(false);
        }

        return $this->name;
    }

    /**
     * Returns the size of the test.
     *
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     *
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     */
    public function getSize() {
        return TestUtil::getSize(
            static::class,
            $this->getName(false)
        );
    }

    /**
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     *
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     */
    public function hasSize() {
        return $this->getSize() !== TestUtil::UNKNOWN;
    }

    /**
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     *
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     */
    public function isSmall() {
        return $this->getSize() === TestUtil::SMALL;
    }

    /**
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     *
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     */
    public function isMedium() {
        return $this->getSize() === TestUtil::MEDIUM;
    }

    /**
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     *
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     */
    public function isLarge() {
        return $this->getSize() === TestUtil::LARGE;
    }

    /**
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     */
    public function getActualOutput() {
        if (!$this->outputBufferingActive) {
            return $this->output;
        }

        return (string) ob_get_contents();
    }

    /**
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     */
    public function hasOutput() {
        if ($this->output === '') {
            return false;
        }

        if ($this->hasExpectationOnOutput()) {
            return false;
        }

        return true;
    }

    /**
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     */
    public function doesNotPerformAssertions() {
        return $this->doesNotPerformAssertions;
    }

    /**
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     */
    public function hasExpectationOnOutput() {
        return is_string($this->outputExpectedString) || is_string($this->outputExpectedRegex) || $this->outputRetrievedForAssertion;
    }

    /**
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     */
    public function getExpectedException() {
        return $this->expectedException;
    }

    /**
     * @return null|int|string
     *
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     */
    public function getExpectedExceptionCode() {
        return $this->expectedExceptionCode;
    }

    /**
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     */
    public function getExpectedExceptionMessage() {
        return $this->expectedExceptionMessage;
    }

    /**
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     */
    public function getExpectedExceptionMessageRegExp() {
        return $this->expectedExceptionMessageRegExp;
    }

    /**
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     *
     * @param mixed $flag
     */
    public function setRegisterMockObjectsFromTestArgumentsRecursively($flag) {
        $this->registerMockObjectsFromTestArgumentsRecursively = $flag;
    }

    /**
     * @throws Throwable
     *
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     */
    public function runBare() {
        $this->numAssertions = 0;

        $this->snapshotGlobalState();
        $this->startOutputBuffering();
        clearstatcache();
        $currentWorkingDirectory = getcwd();

        $hookMethods = TestUtil::getHookMethods(static::class);

        $hasMetRequirements = false;

        try {
            $this->checkRequirements();
            $hasMetRequirements = true;

            if ($this->inIsolation) {
                foreach ($hookMethods['beforeClass'] as $method) {
                    $this->{$method}();
                }
            }

            $this->setDoesNotPerformAssertionsFromAnnotation();

            foreach ($hookMethods['before'] as $method) {
                $this->{$method}();
            }

            foreach ($hookMethods['preCondition'] as $method) {
                $this->{$method}();
            }

            $this->testResult = $this->runTest();
            $this->verifyMockObjects();

            foreach ($hookMethods['postCondition'] as $method) {
                $this->{$method}();
            }

            if (!empty($this->warnings)) {
                throw new Warning(
                    implode(
                        "\n",
                        array_unique($this->warnings)
                    )
                );
            }

            $this->status = BaseTestRunner::STATUS_PASSED;
        } catch (IncompleteTest $e) {
            $this->status = BaseTestRunner::STATUS_INCOMPLETE;
            $this->statusMessage = $e->getMessage();
        } catch (SkippedTest $e) {
            $this->status = BaseTestRunner::STATUS_SKIPPED;
            $this->statusMessage = $e->getMessage();
        } catch (Warning $e) {
            $this->status = BaseTestRunner::STATUS_WARNING;
            $this->statusMessage = $e->getMessage();
        } catch (AssertionFailedError $e) {
            $this->status = BaseTestRunner::STATUS_FAILURE;
            $this->statusMessage = $e->getMessage();
        } catch (PredictionException $e) {
            $this->status = BaseTestRunner::STATUS_FAILURE;
            $this->statusMessage = $e->getMessage();
        } catch (Throwable $_e) {
            $e = $_e;
            $this->status = BaseTestRunner::STATUS_ERROR;
            $this->statusMessage = $_e->getMessage();
        }

        $this->mockObjects = [];
        $this->prophet = null;

        // Tear down the fixture. An exception raised in tearDown() will be
        // caught and passed on when no exception was raised before.
        try {
            if ($hasMetRequirements) {
                foreach ($hookMethods['after'] as $method) {
                    $this->{$method}();
                }

                if ($this->inIsolation) {
                    foreach ($hookMethods['afterClass'] as $method) {
                        $this->{$method}();
                    }
                }
            }
        } catch (Throwable $_e) {
            $e = isset($e) ? $e : $_e;
        }

        try {
            $this->stopOutputBuffering();
        } catch (RiskyTestError $_e) {
            $e = isset($e) ? $e : $_e;
        }

        if (isset($_e)) {
            $this->status = BaseTestRunner::STATUS_ERROR;
            $this->statusMessage = $_e->getMessage();
        }

        clearstatcache();

        if ($currentWorkingDirectory !== getcwd()) {
            chdir($currentWorkingDirectory);
        }

        $this->restoreGlobalState();
        $this->unregisterCustomComparators();
        $this->cleanupIniSettings();
        $this->cleanupLocaleSettings();
        libxml_clear_errors();

        // Perform assertion on output.
        if (!isset($e)) {
            try {
                if ($this->outputExpectedRegex !== null) {
                    $this->assertMatchesRegularExpression($this->outputExpectedRegex, $this->output);
                } elseif ($this->outputExpectedString !== null) {
                    $this->assertEquals($this->outputExpectedString, $this->output);
                }
            } catch (Throwable $_e) {
                $e = $_e;
            }
        }

        // Workaround for missing "finally".
        if (isset($e)) {
            if ($e instanceof PredictionException) {
                $e = new AssertionFailedError($e->getMessage());
            }

            $this->onNotSuccessfulTest($e);
        }
    }

    /**
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     *
     * @param mixed $name
     */
    public function setName($name) {
        $this->name = $name;

        if (is_callable($this->sortId(), true)) {
            $this->providedTests = [new ExecutionOrderDependency($this->sortId())];
        }
    }

    /**
     * @param list<ExecutionOrderDependency> $dependencies
     *
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     */
    public function setDependencies(array $dependencies) {
        $this->dependencies = $dependencies;
    }

    /**
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     */
    public function setDependencyInput(array $dependencyInput) {
        $this->dependencyInput = $dependencyInput;
    }

    /**
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     *
     * @param mixed $beStrictAboutChangesToGlobalState
     */
    public function setBeStrictAboutChangesToGlobalState($beStrictAboutChangesToGlobalState) {
        $this->beStrictAboutChangesToGlobalState = $beStrictAboutChangesToGlobalState;
    }

    /**
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     *
     * @param mixed $backupGlobals
     */
    public function setBackupGlobals($backupGlobals) {
        if ($this->backupGlobals === null && $backupGlobals !== null) {
            $this->backupGlobals = $backupGlobals;
        }
    }

    /**
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     *
     * @param mixed $backupStaticAttributes
     */
    public function setBackupStaticAttributes($backupStaticAttributes) {
        if ($this->backupStaticAttributes === null && $backupStaticAttributes !== null) {
            $this->backupStaticAttributes = $backupStaticAttributes;
        }
    }

    /**
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     *
     * @param mixed $runTestInSeparateProcess
     */
    public function setRunTestInSeparateProcess($runTestInSeparateProcess) {
        if ($this->runTestInSeparateProcess === null) {
            $this->runTestInSeparateProcess = $runTestInSeparateProcess;
        }
    }

    /**
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     *
     * @param mixed $runClassInSeparateProcess
     */
    public function setRunClassInSeparateProcess($runClassInSeparateProcess) {
        if ($this->runClassInSeparateProcess === null) {
            $this->runClassInSeparateProcess = $runClassInSeparateProcess;
        }
    }

    /**
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     *
     * @param mixed $preserveGlobalState
     */
    public function setPreserveGlobalState($preserveGlobalState) {
        $this->preserveGlobalState = $preserveGlobalState;
    }

    /**
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     *
     * @param mixed $inIsolation
     */
    public function setInIsolation($inIsolation) {
        $this->inIsolation = $inIsolation;
    }

    /**
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     */
    public function isInIsolation() {
        return $this->inIsolation;
    }

    /**
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     */
    public function getResult() {
        return $this->testResult;
    }

    /**
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     *
     * @param mixed $result
     */
    public function setResult($result) {
        $this->testResult = $result;
    }

    /**
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     */
    public function setOutputCallback(callable $callback) {
        $this->outputCallback = $callback;
    }

    /**
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     */
    public function getTestResultObject() {
        return $this->result;
    }

    /**
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     */
    public function setTestResultObject(TestResult $result) {
        $this->result = $result;
    }

    /**
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     */
    public function registerMockObject(MockObject $mockObject) {
        $this->mockObjects[] = $mockObject;
    }

    /**
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     *
     * @param mixed $count
     */
    public function addToAssertionCount($count) {
        $this->numAssertions += $count;
    }

    /**
     * Returns the number of assertions performed by this test.
     *
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     */
    public function getNumAssertions() {
        return $this->numAssertions;
    }

    /**
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     */
    public function usesDataProvider() {
        return !empty($this->data);
    }

    /**
     * @return int|string
     *
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     */
    public function dataName() {
        return $this->dataName;
    }

    /**
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     *
     * @param mixed $includeData
     */
    public function getDataSetAsString($includeData = true) {
        $buffer = '';

        if (!empty($this->data)) {
            if (is_int($this->dataName)) {
                $buffer .= sprintf(' with data set #%d', $this->dataName);
            } else {
                $buffer .= sprintf(' with data set "%s"', $this->dataName);
            }

            $exporter = new Exporter();

            if ($includeData) {
                $buffer .= sprintf(' (%s)', $exporter->shortenedRecursiveExport($this->data));
            }
        }

        return $buffer;
    }

    /**
     * Gets the data set of a TestCase.
     *
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     */
    public function getProvidedData() {
        return $this->data;
    }

    /**
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     *
     * @param mixed $warning
     */
    public function addWarning($warning) {
        $this->warnings[] = $warning;
    }

    public function sortId() {
        $id = $this->name;

        if (strpos($id, '::') === false) {
            $id = static::class . '::' . $id;
        }

        if ($this->usesDataProvider()) {
            $id .= $this->getDataSetAsString(false);
        }

        return $id;
    }

    /**
     * Returns the normalized test name as class::method.
     *
     * @return list<ExecutionOrderDependency>
     */
    public function provides() {
        return $this->providedTests;
    }

    /**
     * Returns a list of normalized dependency names, class::method.
     *
     * This list can differ from the raw dependencies as the resolver has
     * no need for the [!][shallow]clone prefix that is filtered out
     * during normalization.
     *
     * @return list<ExecutionOrderDependency>
     */
    public function requires() {
        return $this->dependencies;
    }

    /**
     * Override to run the test and assert its state.
     *
     * @throws AssertionFailedError
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\ObjectEnumerator\InvalidArgumentException
     * @throws Throwable
     */
    protected function runTest() {
        if (trim($this->name) === '') {
            throw new Exception(
                'PHPUnit\Framework\TestCase::$name must be a non-blank string.'
            );
        }

        $testArguments = array_merge($this->data, $this->dependencyInput);

        $this->registerMockObjectsFromTestArguments($testArguments);

        try {
            $testResult = $this->{$this->name}(...array_values($testArguments));
        } catch (Throwable $exception) {
            if (!$this->checkExceptionExpectations($exception)) {
                throw $exception;
            }

            if ($this->expectedException !== null) {
                $this->assertThat(
                    $exception,
                    new ExceptionConstraint(
                        $this->expectedException
                    )
                );
            }

            if ($this->expectedExceptionMessage !== null) {
                $this->assertThat(
                    $exception,
                    new ExceptionMessage(
                        $this->expectedExceptionMessage
                    )
                );
            }

            if ($this->expectedExceptionMessageRegExp !== null) {
                $this->assertThat(
                    $exception,
                    new ExceptionMessageRegularExpression(
                        $this->expectedExceptionMessageRegExp
                    )
                );
            }

            if ($this->expectedExceptionCode !== null) {
                $this->assertThat(
                    $exception,
                    new ExceptionCode(
                        $this->expectedExceptionCode
                    )
                );
            }

            return;
        } catch (Exception $exception) {
            if (!$this->checkExceptionExpectations($exception)) {
                throw $exception;
            }

            if ($this->expectedException !== null) {
                $this->assertThat(
                    $exception,
                    new ExceptionConstraint(
                        $this->expectedException
                    )
                );
            }

            if ($this->expectedExceptionMessage !== null) {
                $this->assertThat(
                    $exception,
                    new ExceptionMessage(
                        $this->expectedExceptionMessage
                    )
                );
            }

            if ($this->expectedExceptionMessageRegExp !== null) {
                $this->assertThat(
                    $exception,
                    new ExceptionMessageRegularExpression(
                        $this->expectedExceptionMessageRegExp
                    )
                );
            }

            if ($this->expectedExceptionCode !== null) {
                $this->assertThat(
                    $exception,
                    new ExceptionCode(
                        $this->expectedExceptionCode
                    )
                );
            }

            return;
        }

        if ($this->expectedException !== null) {
            $this->assertThat(
                null,
                new ExceptionConstraint(
                    $this->expectedException
                )
            );
        } elseif ($this->expectedExceptionMessage !== null) {
            $this->numAssertions++;

            throw new AssertionFailedError(
                sprintf(
                    'Failed asserting that exception with message "%s" is thrown',
                    $this->expectedExceptionMessage
                )
            );
        } elseif ($this->expectedExceptionMessageRegExp !== null) {
            $this->numAssertions++;

            throw new AssertionFailedError(
                sprintf(
                    'Failed asserting that exception with message matching "%s" is thrown',
                    $this->expectedExceptionMessageRegExp
                )
            );
        } elseif ($this->expectedExceptionCode !== null) {
            $this->numAssertions++;

            throw new AssertionFailedError(
                sprintf(
                    'Failed asserting that exception with code "%s" is thrown',
                    $this->expectedExceptionCode
                )
            );
        }

        return $testResult;
    }

    /**
     * This method is a wrapper for the ini_set() function that automatically
     * resets the modified php.ini setting to its original value after the
     * test is run.
     *
     * @param mixed $varName
     * @param mixed $newValue
     *
     * @throws Exception
     */
    protected function iniSet($varName, $newValue) {
        $currentValue = ini_set($varName, $newValue);

        if ($currentValue !== false) {
            $this->iniSettings[$varName] = $currentValue;
        } else {
            throw new Exception(
                sprintf(
                    'INI setting "%s" could not be set to "%s".',
                    $varName,
                    $newValue
                )
            );
        }
    }

    /**
     * This method is a wrapper for the setlocale() function that automatically
     * resets the locale to its original value after the test is run.
     *
     * @throws Exception
     */
    protected function setLocale(...$args) {
        if (count($args) < 2) {
            throw new Exception();
        }

        list($category, $locale) = $args;

        if (!in_array($category, self::LOCALE_CATEGORIES, true)) {
            throw new Exception();
        }

        if (!is_array($locale) && !is_string($locale)) {
            throw new Exception();
        }

        $this->locale[$category] = setlocale($category, 0);

        $result = setlocale(...$args);

        if ($result === false) {
            throw new Exception(
                'The locale functionality is not implemented on your platform, '
                . 'the specified locale does not exist or the category name is '
                . 'invalid.'
            );
        }
    }

    /**
     * Makes configurable stub for the specified class.
     *
     * @psalm-template RealInstanceType of object
     * @psalm-param    class-string<RealInstanceType> $originalClassName
     * @psalm-return   Stub&RealInstanceType
     *
     * @param mixed $originalClassName
     */
    protected function createStub($originalClassName) {
        return $this->createMockObject($originalClassName);
    }

    /**
     * Returns a mock object for the specified class.
     *
     * @psalm-template RealInstanceType of object
     * @psalm-param class-string<RealInstanceType> $originalClassName
     * @psalm-return MockObject&RealInstanceType
     *
     * @param mixed $originalClassName
     */
    protected function createMock($originalClassName) {
        return $this->createMockObject($originalClassName);
    }

    /**
     * Returns a configured mock object for the specified class.
     *
     * @psalm-template RealInstanceType of object
     * @psalm-param class-string<RealInstanceType> $originalClassName
     * @psalm-return MockObject&RealInstanceType
     *
     * @param mixed $originalClassName
     */
    protected function createConfiguredMock($originalClassName, array $configuration) {
        $o = $this->createMockObject($originalClassName);

        foreach ($configuration as $method => $return) {
            $o->method($method)->willReturn($return);
        }

        return $o;
    }

    /**
     * Returns a partial mock object for the specified class.
     *
     * @param string[] $methods
     * @param mixed    $originalClassName
     *
     * @psalm-template RealInstanceType of object
     * @psalm-param class-string<RealInstanceType> $originalClassName
     * @psalm-return MockObject&RealInstanceType
     */
    protected function createPartialMock($originalClassName, array $methods) {
        try {
            $reflector = new ReflectionClass($originalClassName);
            // @codeCoverageIgnoreStart
        } catch (ReflectionException $e) {
            throw new Exception(
                $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
        // @codeCoverageIgnoreEnd

        $mockedMethodsThatDontExist = array_filter(
            $methods,
            static function ($method) use ($reflector) {
                return !$reflector->hasMethod($method);
            }
        );

        if ($mockedMethodsThatDontExist) {
            $this->addWarning(
                sprintf(
                    'createPartialMock() called with method(s) %s that do not exist in %s. This will not be allowed in future versions of PHPUnit.',
                    implode(', ', $mockedMethodsThatDontExist),
                    $originalClassName
                )
            );
        }

        return $this->getMockBuilder($originalClassName)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->disallowMockingUnknownTypes()
            ->setMethods(empty($methods) ? null : $methods)
            ->getMock();
    }

    /**
     * Returns a test proxy for the specified class.
     *
     * @psalm-template RealInstanceType of object
     * @psalm-param class-string<RealInstanceType> $originalClassName
     * @psalm-return MockObject&RealInstanceType
     *
     * @param mixed $originalClassName
     */
    protected function createTestProxy($originalClassName, array $constructorArguments = []) {
        return $this->getMockBuilder($originalClassName)
            ->setConstructorArgs($constructorArguments)
            ->enableProxyingToOriginalMethods()
            ->getMock();
    }

    /**
     * Mocks the specified class and returns the name of the mocked class.
     *
     * @param null|array $methods                 $methods
     * @param mixed      $originalClassName
     * @param mixed      $mockClassName
     * @param mixed      $callOriginalConstructor
     * @param mixed      $callOriginalClone
     * @param mixed      $callAutoload
     * @param mixed      $cloneArguments
     *
     * @psalm-template RealInstanceType of object
     * @psalm-param class-string<RealInstanceType>|string $originalClassName
     * @psalm-return class-string<MockObject&RealInstanceType>
     */
    protected function getMockClass($originalClassName, $methods = [], array $arguments = [], $mockClassName = '', $callOriginalConstructor = false, $callOriginalClone = true, $callAutoload = true, $cloneArguments = false) {
        $this->recordDoubledType($originalClassName);

        $mock = $this->getMockObjectGenerator()->getMock(
            $originalClassName,
            $methods,
            $arguments,
            $mockClassName,
            $callOriginalConstructor,
            $callOriginalClone,
            $callAutoload,
            $cloneArguments
        );

        return get_class($mock);
    }

    /**
     * Returns a mock object for the specified abstract class with all abstract
     * methods of the class mocked. Concrete methods are not mocked by default.
     * To mock concrete methods, use the 7th parameter ($mockedMethods).
     *
     * @psalm-template RealInstanceType of object
     * @psalm-param class-string<RealInstanceType> $originalClassName
     * @psalm-return MockObject&RealInstanceType
     *
     * @param mixed $originalClassName
     * @param mixed $mockClassName
     * @param mixed $callOriginalConstructor
     * @param mixed $callOriginalClone
     * @param mixed $callAutoload
     * @param mixed $cloneArguments
     */
    protected function getMockForAbstractClass($originalClassName, array $arguments = [], $mockClassName = '', $callOriginalConstructor = true, $callOriginalClone = true, $callAutoload = true, array $mockedMethods = [], $cloneArguments = false) {
        $this->recordDoubledType($originalClassName);

        $mockObject = $this->getMockObjectGenerator()->getMockForAbstractClass(
            $originalClassName,
            $arguments,
            $mockClassName,
            $callOriginalConstructor,
            $callOriginalClone,
            $callAutoload,
            $mockedMethods,
            $cloneArguments
        );

        $this->registerMockObject($mockObject);

        return $mockObject;
    }

    /**
     * Returns a mock object based on the given WSDL file.
     *
     * @psalm-template RealInstanceType of object
     * @psalm-param class-string<RealInstanceType>|string $originalClassName
     * @psalm-return MockObject&RealInstanceType
     *
     * @param mixed $wsdlFile
     * @param mixed $originalClassName
     * @param mixed $mockClassName
     * @param mixed $callOriginalConstructor
     */
    protected function getMockFromWsdl($wsdlFile, $originalClassName = '', $mockClassName = '', array $methods = [], $callOriginalConstructor = true, array $options = []) {
        $this->recordDoubledType(SoapClient::class);

        if ($originalClassName === '') {
            $fileName = pathinfo(basename(parse_url($wsdlFile, PHP_URL_PATH)), PATHINFO_FILENAME);
            $originalClassName = preg_replace('/\W/', '', $fileName);
        }

        if (!class_exists($originalClassName)) {
            eval(
                $this->getMockObjectGenerator()->generateClassFromWsdl(
                    $wsdlFile,
                    $originalClassName,
                    $methods,
                    $options
                )
            );
        }

        $mockObject = $this->getMockObjectGenerator()->getMock(
            $originalClassName,
            $methods,
            ['', $options],
            $mockClassName,
            $callOriginalConstructor,
            false,
            false
        );

        $this->registerMockObject($mockObject);

        return $mockObject;
    }

    /**
     * Returns a mock object for the specified trait with all abstract methods
     * of the trait mocked. Concrete methods to mock can be specified with the
     * `$mockedMethods` parameter.
     *
     * @psalm-param trait-string $traitName
     *
     * @param mixed $traitName
     * @param mixed $mockClassName
     * @param mixed $callOriginalConstructor
     * @param mixed $callOriginalClone
     * @param mixed $callAutoload
     * @param mixed $cloneArguments
     */
    protected function getMockForTrait($traitName, array $arguments = [], $mockClassName = '', $callOriginalConstructor = true, $callOriginalClone = true, $callAutoload = true, array $mockedMethods = [], $cloneArguments = false) {
        $this->recordDoubledType($traitName);

        $mockObject = $this->getMockObjectGenerator()->getMockForTrait(
            $traitName,
            $arguments,
            $mockClassName,
            $callOriginalConstructor,
            $callOriginalClone,
            $callAutoload,
            $mockedMethods,
            $cloneArguments
        );

        $this->registerMockObject($mockObject);

        return $mockObject;
    }

    /**
     * Returns an object for the specified trait.
     *
     * @psalm-param trait-string $traitName
     *
     * @param mixed $traitName
     * @param mixed $traitClassName
     * @param mixed $callOriginalConstructor
     * @param mixed $callOriginalClone
     * @param mixed $callAutoload
     */
    protected function getObjectForTrait($traitName, array $arguments = [], $traitClassName = '', $callOriginalConstructor = true, $callOriginalClone = true, $callAutoload = true) {
        $this->recordDoubledType($traitName);

        return $this->getMockObjectGenerator()->getObjectForTrait(
            $traitName,
            $traitClassName,
            $callAutoload,
            $callOriginalConstructor,
            $arguments
        );
    }

    /**
     * @psalm-param class-string|null $classOrInterface
     *
     * @param null|mixed $classOrInterface
     *
     * @throws \Prophecy\Exception\Doubler\ClassNotFoundException
     * @throws \Prophecy\Exception\Doubler\DoubleException
     * @throws \Prophecy\Exception\Doubler\InterfaceNotFoundException
     */
    protected function prophesize($classOrInterface = null) {
        $this->addWarning('PHPUnit\Framework\TestCase::prophesize() is deprecated and will be removed in PHPUnit 10. Please use the trait provided by phpspec/prophecy-phpunit.');

        if (is_string($classOrInterface)) {
            $this->recordDoubledType($classOrInterface);
        }

        return $this->getProphet()->prophesize($classOrInterface);
    }

    /**
     * Creates a default TestResult object.
     *
     * @internal This method is not covered by the backward compatibility promise for PHPUnit
     */
    protected function createResult() {
        return new TestResult();
    }

    /**
     * Performs assertions shared by all tests of a test case.
     *
     * This method is called between setUp() and test.
     */
    protected function assertPreConditions() {
    }

    /**
     * Performs assertions shared by all tests of a test case.
     *
     * This method is called between test and tearDown().
     */
    protected function assertPostConditions() {
    }

    /**
     * This method is called when a test method did not execute successfully.
     *
     * @throws Throwable
     */
    protected function onNotSuccessfulTest(Throwable $t) {
        throw $t;
    }

    protected function recordDoubledType($originalClassName) {
        $this->doubledTypes[] = $originalClassName;
    }

    /**
     * @throws Throwable
     */
    private function verifyMockObjects() {
        foreach ($this->mockObjects as $mockObject) {
            if ($mockObject->__phpunit_hasMatchers()) {
                $this->numAssertions++;
            }

            $mockObject->__phpunit_verify(
                $this->shouldInvocationMockerBeReset($mockObject)
            );
        }

        if ($this->prophet !== null) {
            try {
                $this->prophet->checkPredictions();
            } finally {
                foreach ($this->prophet->getProphecies() as $objectProphecy) {
                    foreach ($objectProphecy->getMethodProphecies() as $methodProphecies) {
                        foreach ($methodProphecies as $methodProphecy) {
                            /* @var MethodProphecy $methodProphecy */
                            $this->numAssertions += count($methodProphecy->getCheckedPredictions());
                        }
                    }
                }
            }
        }
    }

    /**
     * @throws Warning
     * @throws SkippedTestError
     * @throws SyntheticSkippedError
     */
    private function checkRequirements() {
        if (!$this->name || !method_exists($this, $this->name)) {
            return;
        }

        $missingRequirements = TestUtil::getMissingRequirements(
            static::class,
            $this->name
        );

        if (!empty($missingRequirements)) {
            $this->markTestSkipped(implode(PHP_EOL, $missingRequirements));
        }
    }

    private function handleDependencies() {
        if ([] === $this->dependencies || $this->inIsolation) {
            return true;
        }

        $passed = $this->result->passed();
        $passedKeys = array_keys($passed);
        $numKeys = count($passedKeys);

        for ($i = 0; $i < $numKeys; $i++) {
            $pos = strpos($passedKeys[$i], ' with data set');

            if ($pos !== false) {
                $passedKeys[$i] = substr($passedKeys[$i], 0, $pos);
            }
        }

        $passedKeys = array_flip(array_unique($passedKeys));

        foreach ($this->dependencies as $dependency) {
            if (!$dependency->isValid()) {
                $this->markSkippedForNotSpecifyingDependency();

                return false;
            }

            if ($dependency->targetIsClass()) {
                $dependencyClassName = $dependency->getTargetClassName();

                if (array_search($dependencyClassName, $this->result->passedClasses(), true) === false) {
                    $this->markSkippedForMissingDependency($dependency);

                    return false;
                }

                continue;
            }

            $dependencyTarget = $dependency->getTarget();

            if (!isset($passedKeys[$dependencyTarget])) {
                if (!$this->isCallableTestMethod($dependencyTarget)) {
                    $this->markWarningForUncallableDependency($dependency);
                } else {
                    $this->markSkippedForMissingDependency($dependency);
                }

                return false;
            }

            if (isset($passed[$dependencyTarget])) {
                if ($passed[$dependencyTarget]['size'] != \PHPUnit\Util\Test::UNKNOWN
                    && $this->getSize() != \PHPUnit\Util\Test::UNKNOWN
                    && $passed[$dependencyTarget]['size'] > $this->getSize()) {
                    $this->result->addError(
                        $this,
                        new SkippedTestError(
                            'This test depends on a test that is larger than itself.'
                        ),
                        0
                    );

                    return false;
                }

                if ($dependency->useDeepClone()) {
                    $deepCopy = new DeepCopy();
                    $deepCopy->skipUncloneable(false);

                    $this->dependencyInput[$dependencyTarget] = $deepCopy->copy($passed[$dependencyTarget]['result']);
                } elseif ($dependency->useShallowClone()) {
                    $this->dependencyInput[$dependencyTarget] = clone $passed[$dependencyTarget]['result'];
                } else {
                    $this->dependencyInput[$dependencyTarget] = $passed[$dependencyTarget]['result'];
                }
            } else {
                $this->dependencyInput[$dependencyTarget] = null;
            }
        }

        return true;
    }

    private function markSkippedForNotSpecifyingDependency() {
        $this->status = BaseTestRunner::STATUS_SKIPPED;

        $this->result->startTest($this);

        $this->result->addError(
            $this,
            new SkippedTestError(
                sprintf('This method has an invalid @depends annotation.')
            ),
            0
        );

        $this->result->endTest($this, 0);
    }

    private function markSkippedForMissingDependency(ExecutionOrderDependency $dependency) {
        $this->status = BaseTestRunner::STATUS_SKIPPED;

        $this->result->startTest($this);

        $this->result->addError(
            $this,
            new SkippedTestError(
                sprintf(
                    'This test depends on "%s" to pass.',
                    $dependency->getTarget()
                )
            ),
            0
        );

        $this->result->endTest($this, 0);
    }

    private function markWarningForUncallableDependency(ExecutionOrderDependency $dependency) {
        $this->status = BaseTestRunner::STATUS_WARNING;

        $this->result->startTest($this);

        $this->result->addWarning(
            $this,
            new Warning(
                sprintf(
                    'This test depends on "%s" which does not exist.',
                    $dependency->getTarget()
                )
            ),
            0
        );

        $this->result->endTest($this, 0);
    }

    /**
     * Get the mock object generator, creating it if it doesn't exist.
     */
    private function getMockObjectGenerator() {
        if ($this->mockObjectGenerator === null) {
            $this->mockObjectGenerator = new MockGenerator();
        }

        return $this->mockObjectGenerator;
    }

    private function startOutputBuffering() {
        ob_start();

        $this->outputBufferingActive = true;
        $this->outputBufferingLevel = ob_get_level();
    }

    /**
     * @throws RiskyTestError
     */
    private function stopOutputBuffering() {
        if (ob_get_level() !== $this->outputBufferingLevel) {
            while (ob_get_level() >= $this->outputBufferingLevel) {
                ob_end_clean();
            }

            throw new RiskyTestError(
                'Test code or tested code did not (only) close its own output buffers'
            );
        }

        $this->output = ob_get_contents();

        if ($this->outputCallback !== false) {
            $this->output = (string) call_user_func($this->outputCallback, $this->output);
        }

        ob_end_clean();

        $this->outputBufferingActive = false;
        $this->outputBufferingLevel = ob_get_level();
    }

    private function snapshotGlobalState() {
        if ($this->runTestInSeparateProcess || $this->inIsolation
            || (!$this->backupGlobals && !$this->backupStaticAttributes)) {
            return;
        }

        $this->snapshot = $this->createGlobalStateSnapshot($this->backupGlobals === true);
    }

    /**
     * @throws RiskyTestError
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    private function restoreGlobalState() {
        if (!$this->snapshot instanceof Snapshot) {
            return;
        }

        if ($this->beStrictAboutChangesToGlobalState) {
            try {
                $this->compareGlobalStateSnapshots(
                    $this->snapshot,
                    $this->createGlobalStateSnapshot($this->backupGlobals === true)
                );
            } catch (RiskyTestError $rte) {
                // Intentionally left empty
            }
        }

        $restorer = new Restorer();

        if ($this->backupGlobals) {
            $restorer->restoreGlobalVariables($this->snapshot);
        }

        if ($this->backupStaticAttributes) {
            $restorer->restoreStaticAttributes($this->snapshot);
        }

        $this->snapshot = null;

        if (isset($rte)) {
            throw $rte;
        }
    }

    private function createGlobalStateSnapshot($backupGlobals) {
        $excludeList = new GlobalStateExcludeList();

        foreach ($this->backupGlobalsExcludeList as $globalVariable) {
            $excludeList->addGlobalVariable($globalVariable);
        }

        if (!defined('PHPUNIT_TESTSUITE')) {
            $excludeList->addClassNamePrefix('PHPUnit');
            $excludeList->addClassNamePrefix('SebastianBergmann\CodeCoverage');
            $excludeList->addClassNamePrefix('SebastianBergmann\FileIterator');
            $excludeList->addClassNamePrefix('SebastianBergmann\Invoker');
            $excludeList->addClassNamePrefix('SebastianBergmann\Template');
            $excludeList->addClassNamePrefix('SebastianBergmann\Timer');
            $excludeList->addClassNamePrefix('Symfony');
            $excludeList->addClassNamePrefix('Doctrine\Instantiator');
            $excludeList->addClassNamePrefix('Prophecy');
            $excludeList->addStaticAttribute(ComparatorFactory::class, 'instance');

            foreach ($this->backupStaticAttributesExcludeList as $class => $attributes) {
                foreach ($attributes as $attribute) {
                    $excludeList->addStaticAttribute($class, $attribute);
                }
            }
        }

        return new Snapshot(
            $excludeList,
            $backupGlobals,
            (bool) $this->backupStaticAttributes,
            false,
            false,
            false,
            false,
            false,
            false,
            false
        );
    }

    /**
     * @throws RiskyTestError
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    private function compareGlobalStateSnapshots(Snapshot $before, Snapshot $after) {
        $backupGlobals = $this->backupGlobals === null || $this->backupGlobals;

        if ($backupGlobals) {
            $this->compareGlobalStateSnapshotPart(
                $before->globalVariables(),
                $after->globalVariables(),
                "--- Global variables before the test\n+++ Global variables after the test\n"
            );

            $this->compareGlobalStateSnapshotPart(
                $before->superGlobalVariables(),
                $after->superGlobalVariables(),
                "--- Super-global variables before the test\n+++ Super-global variables after the test\n"
            );
        }

        if ($this->backupStaticAttributes) {
            $this->compareGlobalStateSnapshotPart(
                $before->staticAttributes(),
                $after->staticAttributes(),
                "--- Static attributes before the test\n+++ Static attributes after the test\n"
            );
        }
    }

    /**
     * @param mixed $header
     *
     * @throws RiskyTestError
     */
    private function compareGlobalStateSnapshotPart(array $before, array $after, $header) {
        if ($before != $after) {
            $differ = new Differ($header);
            $exporter = new Exporter();

            $diff = $differ->diff(
                $exporter->export($before),
                $exporter->export($after)
            );

            throw new RiskyTestError(
                $diff
            );
        }
    }

    private function getProphet() {
        if ($this->prophet === null) {
            $this->prophet = new Prophet();
        }

        return $this->prophet;
    }

    /**
     * @throws \SebastianBergmann\ObjectEnumerator\InvalidArgumentException
     */
    private function shouldInvocationMockerBeReset(MockObject $mock) {
        $enumerator = new Enumerator();

        foreach ($enumerator->enumerate($this->dependencyInput) as $object) {
            if ($mock === $object) {
                return false;
            }
        }

        if (!is_array($this->testResult) && !is_object($this->testResult)) {
            return true;
        }

        return !in_array($mock, $enumerator->enumerate($this->testResult), true);
    }

    /**
     * @throws \SebastianBergmann\ObjectEnumerator\InvalidArgumentException
     * @throws \SebastianBergmann\ObjectReflector\InvalidArgumentException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    private function registerMockObjectsFromTestArguments(array $testArguments, array &$visited = []) {
        if ($this->registerMockObjectsFromTestArgumentsRecursively) {
            foreach ((new Enumerator())->enumerate($testArguments) as $object) {
                if ($object instanceof MockObject) {
                    $this->registerMockObject($object);
                }
            }
        } else {
            foreach ($testArguments as $testArgument) {
                if ($testArgument instanceof MockObject) {
                    if (Type::isCloneable($testArgument)) {
                        $testArgument = clone $testArgument;
                    }

                    $this->registerMockObject($testArgument);
                } elseif (is_array($testArgument) && !in_array($testArgument, $visited, true)) {
                    $visited[] = $testArgument;

                    $this->registerMockObjectsFromTestArguments(
                        $testArgument,
                        $visited
                    );
                }
            }
        }
    }

    private function setDoesNotPerformAssertionsFromAnnotation() {
        $annotations = TestUtil::parseTestMethodAnnotations(
            static::class,
            $this->name
        );

        if (isset($annotations['method']['doesNotPerformAssertions'])) {
            $this->doesNotPerformAssertions = true;
        }
    }

    private function unregisterCustomComparators() {
        $factory = ComparatorFactory::getInstance();

        foreach ($this->customComparators as $comparator) {
            $factory->unregister($comparator);
        }

        $this->customComparators = [];
    }

    private function cleanupIniSettings() {
        foreach ($this->iniSettings as $varName => $oldValue) {
            ini_set($varName, $oldValue);
        }

        $this->iniSettings = [];
    }

    private function cleanupLocaleSettings() {
        foreach ($this->locale as $category => $locale) {
            setlocale($category, $locale);
        }

        $this->locale = [];
    }

    /**
     * @throws Exception
     */
    private function checkExceptionExpectations(Throwable $throwable) {
        $result = false;

        if ($this->expectedException !== null || $this->expectedExceptionCode !== null || $this->expectedExceptionMessage !== null || $this->expectedExceptionMessageRegExp !== null) {
            $result = true;
        }

        if ($throwable instanceof Exception) {
            $result = false;
        }

        if (is_string($this->expectedException)) {
            try {
                $reflector = new ReflectionClass($this->expectedException);
                // @codeCoverageIgnoreStart
            } catch (ReflectionException $e) {
                throw new Exception(
                    $e->getMessage(),
                    (int) $e->getCode(),
                    $e
                );
            }
            // @codeCoverageIgnoreEnd

            if ($this->expectedException === 'PHPUnit\Framework\Exception\Exception'
                || $this->expectedException === '\PHPUnit\Framework\Exception\Exception'
                || $reflector->isSubclassOf(Exception::class)) {
                $result = true;
            }
        }

        return $result;
    }

    private function runInSeparateProcess() {
        return ($this->runTestInSeparateProcess || $this->runClassInSeparateProcess)
            && !$this->inIsolation && !$this instanceof PhptTestCase;
    }

    private function isCallableTestMethod($dependency) {
        list($className, $methodName) = explode('::', $dependency);

        if (!class_exists($className)) {
            return false;
        }

        try {
            $class = new ReflectionClass($className);
        } catch (ReflectionException $e) {
            return false;
        }

        if (!$class->isSubclassOf(__CLASS__)) {
            return false;
        }

        if (!$class->hasMethod($methodName)) {
            return false;
        }

        try {
            $method = $class->getMethod($methodName);
        } catch (ReflectionException $e) {
            return false;
        }

        return TestUtil::isTestMethod($method);
    }

    /**
     * @psalm-template RealInstanceType of object
     * @psalm-param class-string<RealInstanceType> $originalClassName
     * @psalm-return MockObject&RealInstanceType
     *
     * @param mixed $originalClassName
     */
    private function createMockObject($originalClassName) {
        return $this->getMockBuilder($originalClassName)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->disallowMockingUnknownTypes()
            ->getMock();
    }
}
