<?php

use PHPUnit\Framework\TestCase;

/**
 * CLogger_Rotator_Delete - di direktori temp sendiri.
 */
class LogRotatorDeleteTest extends TestCase {
    /**
     * @var string
     */
    private $tempDir;

    protected function setUp(): void {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cf-log-delete-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void {
        CFile::deleteDirectory($this->tempDir);
    }

    public function testFilesWhoseDateCannotBeParsedAreKept() {
        foreach (['app-20200101.log', 'app-backup.log', 'app-latest.log', 'app-20991231.log'] as $name) {
            file_put_contents($this->tempDir . '/' . $name, 'x');
        }
        $delete = new CLogger_Rotator_Delete($this->tempDir . '/app-{Ymd}.log');
        $delete->setNow(new DateTime('2026-10-16'));

        $deleted = $delete->deleteByFilenameTime('3 months');

        $this->assertSame([$this->tempDir . '/app-20200101.log'], $deleted);
        $this->assertFileExists($this->tempDir . '/app-backup.log');
        $this->assertFileExists($this->tempDir . '/app-latest.log');
        $this->assertFileExists($this->tempDir . '/app-20991231.log');
    }

    public function testSafePathDoesNotCoverASiblingWithTheSamePrefix() {
        mkdir($this->tempDir . '/log/lama', 0777, true);
        mkdir($this->tempDir . '/logs-other', 0777, true);
        file_put_contents($this->tempDir . '/logs-other/penting.txt', 'x');

        $delete = new CLogger_Rotator_Delete();
        $delete->addSafeRecursiveDeletePath($this->tempDir . '/log');
        $method = new ReflectionMethod(CLogger_Rotator_Delete::class, 'recursiveDeleteFolder');
        $method->setAccessible(true);

        $method->invoke($delete, $this->tempDir . '/log/lama');
        $this->assertDirectoryDoesNotExist($this->tempDir . '/log/lama');

        try {
            $method->invoke($delete, $this->tempDir . '/logs-other');
            $this->fail('logs-other bukan di dalam safe path log/');
        } catch (CLogger_Rotator_Exception_RotateException $e) {
            $this->assertFileExists($this->tempDir . '/logs-other/penting.txt');
        }

        //safe path itu sendiri juga tidak boleh dihapus
        $this->expectException(CLogger_Rotator_Exception_RotateException::class);
        $method->invoke($delete, $this->tempDir . '/log');
    }
}
