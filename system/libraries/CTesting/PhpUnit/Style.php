<?php

/**
 * Description of Style
 */
use PHPUnit\Framework\Exception\AssertionFailedError;
use PHPUnit\Framework\ExceptionWrapper;
use PHPUnit\Framework\Exception\ExpectationFailedException;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Whoops\Exception\Inspector;

/**
 * @internal
 */
final class CTesting_PhpUnit_Style {
    /**
     * @var ConsoleOutput
     */
    private $output;

    /**
     * Style constructor.
     */
    public function __construct(ConsoleOutputInterface $output) {
        if (!$output instanceof ConsoleOutput) {
            throw new CTesting_Exception_ShouldNotHappen();
        }

        $this->output = $output;
    }

    /**
     * Prints the content.
     *
     * @param mixed $content
     */
    public function write($content) {
        $this->output->write($content);
    }

    /**
     * Prints the content similar too:.
     *
     * ```
     *    PASS  Unit\ExampleTest
     *    ✓ basic test
     * ```
     */
    public function writeCurrentTestCaseSummary(CTesting_PhpUnit_State $state) {
        if ($state->testCaseTestsCount() === 0) {
            return;
        }

        if (!$state->headerPrinted) {
            $this->output->writeln($this->titleLineFrom(
                $state->getTestCaseTitle() === 'FAIL' ? 'white' : 'black',
                $state->getTestCaseTitleColor(),
                $state->getTestCaseTitle(),
                $state->testCaseName
            ));
            $state->headerPrinted = true;
        }

        $state->eachTestCaseTests(function (CTesting_PhpUnit_TestResult $testResult) {
            usleep(20000);
            $this->output->writeln($this->testLineFrom(
                $testResult->color,
                $testResult->icon,
                $testResult->description,
                $testResult->warning
            ));
        });
    }

    /**
     * Prints the content similar too:.
     *
     * ```
     *    PASS  Unit\ExampleTest
     *    ✓ basic test
     * ```
     *
     * @param mixed $onFailure
     */
    public function writeErrorsSummary(CTesting_PhpUnit_State $state, $onFailure) {
        $errors = array_filter($state->suiteTests, function (CTesting_PhpUnit_TestResult $testResult) {
            return $testResult->type === CTesting_PhpUnit_TestResult::FAIL;
        });

        if (!$onFailure) {
            $this->output->writeln(['', "  \e[2m---\e[22m", '']);
        }

        array_map(function (CTesting_PhpUnit_TestResult $testResult) use ($onFailure) {
            if (!$onFailure) {
                $this->output->write(sprintf(
                    '  <fg=red;options=bold>• %s </>> <fg=red;options=bold>%s</>',
                    $testResult->testCaseName,
                    $testResult->description
                ));
            }

            /*
              if (!$testResult->throwable instanceof Throwable) {
              throw new CTesting_Exception_ShouldNotHappen();
              }
             *
             */

            $this->writeError($testResult->throwable);
        }, $errors);
    }

    /**
     * Writes the final recap.
     */
    public function writeRecap(CTesting_PhpUnit_State $state, ?CTesting_PhpUnit_Timer $timer = null) {
        $types = [
            CTesting_PhpUnit_TestResult::FAIL,
            CTesting_PhpUnit_TestResult::WARN,
            CTesting_PhpUnit_TestResult::RISKY,
            CTesting_PhpUnit_TestResult::INCOMPLETE,
            CTesting_PhpUnit_TestResult::SKIPPED,
            CTesting_PhpUnit_TestResult::PASS
        ];
        foreach ($types as $type) {
            if (($countTests = $state->countTestsInTestSuiteBy($type)) !== 0) {
                $color = CTesting_PhpUnit_TestResult::makeColor($type);
                $tests[] = "<fg=$color;options=bold>$countTests $type</>";
            }
        }

        $pending = $state->suiteTotalTests - $state->testSuiteTestsCount();
        if ($pending !== 0) {
            $tests[] = "\e[2m$pending pending\e[22m";
        }

        if (!empty($tests)) {
            $this->output->write([
                "\n",
                sprintf(
                    '  <fg=white;options=bold>Tests:  </><fg=default>%s</>',
                    implode(', ', $tests)
                ),
            ]);
        }

        if ($timer !== null) {
            $timeElapsed = number_format($timer->result(), 2, '.', '');
            $this->output->writeln(
                [
                    '',
                    sprintf(
                        '  <fg=white;options=bold>Time:   </><fg=default>%ss</>',
                        $timeElapsed
                    ),
                ]
            );
        }

        $this->output->writeln('');
    }

    /**
     * Displays a warning message.
     *
     * @param mixed $message
     */
    public function writeWarning($message) {
        $this->output->writeln($this->testLineFrom('yellow', $message, ''));
    }

    /**
     * Displays the error using Collision's writer
     * and terminates with exit code === 1.
     *
     * @param mixed $throwable
     */
    public function writeError($throwable) {
        $writer = (new CTesting_Writer())->setOutput($this->output);

        if ($throwable instanceof AssertionFailedError) {
            $writer->showTitle(false);
            $this->output->write('', true);
        }

        $writer->ignoreFilesIn([
            '/vendor\/pestphp\/pest/',
            '/vendor\/phpunit\/phpunit\/src/',
            '/vendor\/mockery\/mockery/',
            '/vendor\/laravel\/dusk/',
            '/vendor\/laravel\/framework\/src\/Illuminate\/Testing/',
            '/vendor\/laravel\/framework\/src\/Illuminate\/Foundation\/Testing/',
        ]);

        if ($throwable instanceof ExceptionWrapper && $throwable->getOriginalException() !== null) {
            $throwable = $throwable->getOriginalException();
        }

        $inspector = new Inspector($throwable);

        $writer->write($inspector);

        if ($throwable instanceof ExpectationFailedException && $comparisionFailure = $throwable->getComparisonFailure()) {
            $diff = $comparisionFailure->getDiff();
            $diff = trim((string) preg_replace("/\r|\n/", "\n  ", $diff));
            $this->output->write("  $diff");
        }

        $this->output->writeln('');
    }

    /**
     * Returns the title contents.
     *
     * @param mixed $fg
     * @param mixed $bg
     * @param mixed $title
     * @param mixed $testCaseName
     */
    private function titleLineFrom($fg, $bg, $title, $testCaseName) {
        return sprintf(
            "\n  <fg=%s;bg=%s;options=bold> %s </><fg=default> %s</>",
            $fg,
            $bg,
            $title,
            $testCaseName
        );
    }

    /**
     * Returns the test contents.
     *
     * @param mixed      $fg
     * @param mixed      $icon
     * @param mixed      $description
     * @param null|mixed $warning
     */
    private function testLineFrom($fg, $icon, $description, $warning = null) {
        if (!empty($warning)) {
            $warning = sprintf(
                ' → %s',
                $warning
            );
        }

        return sprintf(
            "  <fg=%s;options=bold>%s</><fg=default> \e[2m%s\e[22m</><fg=yellow>%s</>",
            $fg,
            $icon,
            $description,
            $warning
        );
    }
}
