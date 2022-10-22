<?php

use Carbon\Carbon;

trait CQC_Testing_Repository_Concern_TestTrait {
    use CQC_Testing_Repository_Concern_QueueTrait;
    use CQC_Testing_Repository_Concern_RunTrait;

    /**
     * Find test by filename and suite.
     *
     * @param $file
     * @param $suite
     *
     * @return mixed
     */
    protected function findTestByFileAndSuite($file, $suite) {
        $exists = CQC_Testing_Model_Test::where('name', $file->getRelativePathname())
            ->where('suite_id', $suite->suite_id)
            ->first();

        return $exists;
    }

    /**
     * Create or update a test.
     *
     * @param \Symfony\Component\Finder\SplFileInfo $file
     * @param \CQC_Testing_Model_Suite              $suite
     *
     * @return bool
     */
    public function createOrUpdateTest($file, $suite) {
        $test = CQC_Testing_Model_Test::where('path', $path = $this->normalizePath($file->getPath()))
            ->where('name', $name = trim($file->getFilename()))
            ->first();

        if (is_null($test)) {
            $test = CQC_Testing_Model_Test::create([
                'sha1' => sha1("{$path}/{$name}"),
                'path' => $path,
                'name' => $name,
                'suite_id' => $suite->suite_id,
            ]);
        }

        if ($test->wasRecentlyCreated && $this->findTestByFileAndSuite($file, $suite)) {
            $this->addTestToQueue($test);
        }

        return $test->wasRecentlyCreated;
    }

    /**
     * Sync all tests.
     *
     * @param $exclusions
     */
    public function syncTests($exclusions) {
        /** @var CQC_Testing_Repository $this */
        foreach ($this->getSuites() as $suite) {
            $this->syncSuiteTests($suite, $exclusions);
        }
    }

    /**
     * Check if a file is a test file.
     *
     * @param $path
     *
     * @return \___PHPSTORM_HELPERS\static|bool|mixed
     */
    public function isTestFile($path) {
        if (file_exists($path)) {
            foreach (CQC_Testing_Model_Test::all() as $test) {
                if ($test->fullPath == $path) {
                    return $test;
                }
            }
        }

        return false;
    }

    /**
     * Store the test result.
     *
     * @param $run
     * @param $test
     * @param $lines
     * @param $ok
     * @param $startedAt
     * @param $endedAt
     *
     * @return mixed
     */
    public function storeTestResult($run, $test, $lines, $ok, $startedAt, $endedAt) {
        if (!$this->testExists($test)) {
            return false;
        }

        $run = $this->updateRun($run, $test, $lines, $ok, $startedAt, $endedAt);

        $test->state = $ok ? CQC_Testing::STATE_OK : CQC_Testing::STATE_FAILED;

        $test->last_run_id = $run->run_id;

        $test->save();

        $this->removeTestFromQueue($test);

        return $ok;
    }

    /**
     * Mark a test as being running.
     *
     * @param $test
     *
     * @return mixed
     */
    public function markTestAsRunning($test) {
        $test->state = CQC_Testing::STATE_RUNNING;

        $test->save();

        return $this->createNewRunForTest($test);
    }

    /**
     * Find a test by name and suite.
     *
     * @param $suite
     * @param $file
     *
     * @return mixed
     */
    protected function findTestByNameAndSuite($file, $suite) {
        return CQC_Testing_Model_Test::where('name', $file->getRelativePathname())->where('suite_id', $suite->suite_id)->first();
    }

    /**
     * Enable tests.
     *
     * @param $enable
     * @param $project_id
     * @param null $test_id
     *
     * @return bool
     */
    public function enableTests($enable, $project_id, $test_id) {
        $enable = is_bool($enable) ? $enable : ($enable === 'true');

        $tests = $this->queryTests($project_id, $test_id == 'all' ? null : $test_id)->get();

        foreach ($tests as $test) {
            $this->enableTest($enable, $test);
        }

        return $enable;
    }

    /**
     * Run a test.
     *
     * @param $test
     * @param bool $force
     */
    public function runTest($test, $force = false) {
        if (!$test instanceof CQC_Testing_Model_Test) {
            $test = CQC_Testing_Model_Test::find($test);
        }

        $this->addTestToQueue($test, $force);
    }

    /**
     * Run all test.
     *
     * @param bool $force
     */
    public function runAllTest($force = false) {
        foreach (CQC_Testing_Model_Test::get() as $test) {
            $this->runTest($test, $force);
        }
    }

    /**
     * Reset all tests.
     */
    public function resetAllTest() {
        foreach (CQC_Testing_Model_Test::get() as $test) {
            $this->resetTest($test);
        }
    }

    /**
     * Enable a test.
     *
     * @param $enable
     * @param \CQC_Testing_Model_Test $test
     */
    protected function enableTest($enable, $test) {
        $test->timestamps = false;

        $test->enabled = $enable;

        $test->save();

        if (!$enable) {
            $this->removeTestFromQueue($test);

            return;
        }

        if ($test->state !== CQC_Testing::STATE_OK) {
            $this->addTestToQueue($test);
        }
    }

    /**
     * Query tests.
     *
     * @param $test_id
     * @param mixed $projects
     *
     * @return mixed
     */
    protected function queryTests($projects, $test_id = null) {
        $projects = (array) $projects;

        $query = CQC_Testing_Model_Test::select('test.*')
            ->join('tddd_suites', 'tddd_suites.id', '=', 'tddd_tests.suite_id');

        if ($projects && $projects != 'all') {
            $query->whereIn('tddd_suites.project_id', $projects);
        }

        if ($test_id && $test_id != 'all') {
            $query->where('tddd_tests.id', $test_id);
        }

        return $query;
    }

    /**
     * Mark tests as notified.
     *
     * @param $tests
     */
    public function markTestsAsNotified($tests) {
        $tests->each(function ($test) {
            $test['run']->notified_at = Carbon::now();

            $test['run']->save();
        });
    }

    /**
     * Check if the test exists.
     *
     * @param $test
     *
     * @return bool
     */
    protected function testExists($test) {
        return !is_null(CQC_Testing_Model_Test::find($test->test_id));
    }

    /**
     * Update the run.
     *
     * @param $run
     * @param $test
     * @param $lines
     * @param $ok
     * @param $startedAt
     * @param $endedAt
     *
     * @return mixed
     */
    private function updateRun($run, $test, $lines, $ok, $startedAt, $endedAt) {
        /** @var CQC_Testing_Repository $this */
        $run->test_id = $test->test_id;
        $run->was_ok = $ok;
        $run->log = $this->formatLog($lines, $test) ?: '(empty)';
        //TODO: html
        // $run->html = $this->getOutput(
        //     $test,
        //     $test->suite->tester->output_folder,
        //     $test->suite->tester->output_html_fail_extension
        // );
        //TODO: screenshots
        //$run->screenshots = $this->getScreenshots($test, $lines);
        $run->started_at = $startedAt;
        $run->ended_at = $endedAt;

        $run->save();

        return $run;
    }
}
