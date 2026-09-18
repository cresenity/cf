<?php

/**
 * Description of TestResult
 */
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class CTesting_PhpUnit_TestResult {
    const FAIL = 'failed';
    const SKIPPED = 'skipped';
    const INCOMPLETE = 'incompleted';
    const RISKY = 'risked';
    const WARN = 'warnings';
    const RUNS = 'pending';
    const PASS = 'passed';

    /**
     * @readonly
     *
     * @var string
     */
    public $testCaseName;

    /**
     * @readonly
     *
     * @var string
     */
    public $description;

    /**
     * @readonly
     *
     * @var string
     */
    public $type;

    /**
     * @readonly
     *
     * @var string
     */
    public $icon;

    /**
     * @readonly
     *
     * @var string
     */
    public $color;

    /**
     * @readonly
     *
     * @var Throwable|null
     */
    public $throwable;

    /**
     * @readonly
     *
     * @var string
     */
    public $warning = '';

    /**
     * Test constructor.
     *
     * @param mixed      $testCaseName
     * @param mixed      $description
     * @param mixed      $type
     * @param mixed      $icon
     * @param mixed      $color
     * @param null|mixed $throwable
     */
    private function __construct($testCaseName, $description, $type, $icon, $color, $throwable = null) {
        $this->testCaseName = $testCaseName;
        $this->description = $description;
        $this->type = $type;
        $this->icon = $icon;
        $this->color = $color;
        $this->throwable = $throwable;

        $asWarning = $this->type === CTesting_PhpUnit_TestResult::WARN || $this->type === CTesting_PhpUnit_TestResult::RISKY || $this->type === CTesting_PhpUnit_TestResult::SKIPPED || $this->type === CTesting_PhpUnit_TestResult::INCOMPLETE;

        if ($throwable instanceof Throwable && $asWarning) {
            $this->warning = trim((string) preg_replace("/\r|\n/", ' ', $throwable->getMessage()));
        }
    }

    /**
     * Creates a new test from the given test case.
     *
     * @param mixed      $type
     * @param null|mixed $throwable
     */
    public static function fromTestCase(TestCase $testCase, $type, $throwable = null) {
        $testCaseName = CTesting_PhpUnit_State::getPrintableTestCaseName($testCase);

        $description = self::makeDescription($testCase);

        $icon = self::makeIcon($type);

        $color = self::makeColor($type);

        return new self($testCaseName, $description, $type, $icon, $color, $throwable);
    }

    /**
     * Get the test case description.
     */
    public static function makeDescription(TestCase $testCase) {
        $name = $testCase->getName(false);

        if ($testCase instanceof CTesting_PhpUnit_HasPrintableTestCaseNameInterface) {
            return $name;
        }

        // First, lets replace underscore by spaces.
        $name = str_replace('_', ' ', $name);

        // Then, replace upper cases by spaces.
        $name = (string) preg_replace('/([A-Z])/', ' $1', $name);

        // Finally, if it starts with `test`, we remove it.
        $name = (string) preg_replace('/^test/', '', $name);

        // Removes spaces
        $name = trim($name);

        // Lower case everything
        $name = mb_strtolower($name);

        // Add the dataset name if it has one
        if ($dataName = $testCase->dataName()) {
            if (is_int($dataName)) {
                $name .= sprintf(' with data set #%d', $dataName);
            } else {
                $name .= sprintf(' with data set "%s"', $dataName);
            }
        }

        return $name;
    }

    /**
     * Get the test case icon.
     *
     * @param mixed $type
     */
    public static function makeIcon($type) {
        switch ($type) {
            case self::FAIL:
                return '⨯';
            case self::SKIPPED:
                return '-';
            case self::RISKY:
                return '!';
            case self::INCOMPLETE:
                return '…';
            case self::WARN:
                return '!';
            case self::RUNS:
                return '•';
            default:
                return '✓';
        }
    }

    /**
     * Get the test case color.
     *
     * @param mixed $type
     */
    public static function makeColor($type) {
        switch ($type) {
            case self::FAIL:
                return 'red';
            case self::SKIPPED:
            case self::INCOMPLETE:
            case self::RISKY:
            case self::WARN:
            case self::RUNS:
                return 'yellow';
            default:
                return 'green';
        }
    }
}
