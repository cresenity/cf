<?php

use PHPUnit\Framework\TestCase;

/**
 * CLogger_Rotator_Rotate - lock antarproses, di direktori temp sendiri.
 */
class LogRotatorRotateTest extends TestCase {
    /**
     * @var string
     */
    private $tempDir;

    protected function setUp(): void {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cf-log-rotate-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void {
        CFile::deleteDirectory($this->tempDir);
    }

    public function testAProcessWaitingForTheLockDoesNotRotateAgain() {
        $log = $this->tempDir . '/cron.log';
        file_put_contents($log, str_repeat('besar ', 100));
        $lock = $this->tempDir . '/.cron.log.rotate.lock';

        //proses lain memegang lock, merotasi cron.log sendiri, lalu melepas lock
        $script = '$l = fopen(' . var_export($lock, true) . ', "c"); flock($l, LOCK_EX); echo "locked\n"; usleep(1000000);'
            . ' rename(' . var_export($log, true) . ', ' . var_export($log . '.1', true) . '); file_put_contents(' . var_export($log, true) . ', "baru");'
            . ' flock($l, LOCK_UN);';
        $process = proc_open([PHP_BINARY, '-r', $script], [1 => ['pipe', 'w']], $pipes);
        $this->assertSame("locked\n", fgets($pipes[1]));

        $start = microtime(true);
        $rotated = CLogger_Rotator::createRotate($log)->size('100B')->keep(5)->run();
        $elapsed = microtime(true) - $start;
        fclose($pipes[1]);
        proc_close($process);

        $this->assertGreaterThan(0.5, $elapsed, 'run() harus menunggu lock');
        $this->assertSame([], $rotated);
        $this->assertSame('baru', file_get_contents($log));
        $this->assertStringContainsString('besar', file_get_contents($log . '.1'));
        $this->assertFileDoesNotExist($log . '.2');
    }

    public function testRotationStillShiftsTheCopies() {
        $log = $this->tempDir . '/app.log';
        file_put_contents($log, 'tiga');
        file_put_contents($log . '.1', 'dua');
        file_put_contents($log . '.2', 'satu');

        CLogger_Rotator::createRotate($log)->keep(2)->forceRotate();

        $this->assertSame('tiga', file_get_contents($log . '.1'));
        $this->assertSame('dua', file_get_contents($log . '.2'));
        $this->assertFileDoesNotExist($log);
        $this->assertFileDoesNotExist($log . '.3');
    }

    public function testDryRunDoesNotCreateALockFile() {
        $log = $this->tempDir . '/dry.log';
        file_put_contents($log, 'x');
        $rotate = CLogger_Rotator::createRotate($log);
        $rotate->setDryRun(true);

        $this->assertSame([$log], $rotate->run(false));
        $this->assertFileExists($log);
        $this->assertFileDoesNotExist($this->tempDir . '/.dry.log.rotate.lock');
    }
}
