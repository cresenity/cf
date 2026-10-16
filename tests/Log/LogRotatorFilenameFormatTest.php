<?php

use PHPUnit\Framework\TestCase;

/**
 * CLogger_Rotator_FilenameFormat - pola nama berkas yang dipakai CLogger_Rotator_Rotate.
 */
class LogRotatorFilenameFormatTest extends TestCase {
    /**
     * @var string
     */
    private $tempDir;

    protected function setUp(): void {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cf-log-rotator-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void {
        CFile::deleteDirectory($this->tempDir);
    }

    /**
     * @param string $pattern
     * @param string $filename
     *
     * @return bool
     */
    private function patternMatches($pattern, $filename) {
        $format = new CLogger_Rotator_FilenameFormat($this->tempDir . '/' . $pattern);

        return (bool) preg_match($format->getFilenameRegex(), $filename);
    }

    public function testExistingPatternsKeepMatching() {
        $this->assertTrue($this->patternMatches('cron.log', 'cron.log'));
        $this->assertFalse($this->patternMatches('cron.log', 'cronxlog'));
        $this->assertTrue($this->patternMatches('*.log', 'apa-saja.log'));
        $this->assertTrue($this->patternMatches('order.{Ymd}.log', 'order.20261016.log'));
        $this->assertTrue($this->patternMatches('a-b+c:d.log', 'a-b+c:d.log'));
    }

    public function testRegexMetacharactersInTheFilenameAreLiteral() {
        foreach (['Sync(daily).log', 'job[1].log', 'a$b^c.log', 'a|b.log', 'tanya?.log'] as $name) {
            $this->assertTrue($this->patternMatches($name, $name), $name);
        }
        $this->assertFalse($this->patternMatches('Sync(daily).log', 'Syncdaily.log'));
        $this->assertFalse($this->patternMatches('a|b.log', 'b.log'));
    }

    public function testBracesThatAreNotADateFormatAreLiteral() {
        $format = new CLogger_Rotator_FilenameFormat($this->tempDir . '/Job{1}.log');
        $this->assertFalse($format->hasDateFormat());
        $this->assertTrue((bool) preg_match($format->getFilenameRegex(), 'Job{1}.log'));
    }

    public function testDateFormatWithSeparators() {
        $format = new CLogger_Rotator_FilenameFormat($this->tempDir . '/app-{Y-m-d}.log');
        $this->assertSame('Y-m-d', $format->getDateFormat());
        $this->assertTrue((bool) preg_match($format->getFilenameRegex(), 'app-2026-10-16.log', $m));
        $this->assertSame('2026-10-16', $m[1]);
    }

    public function testFileWithRegexMetacharactersIsRotated() {
        $path = $this->tempDir . '/Sync(daily).log';
        file_put_contents($path, 'isi');

        $rotated = CLogger_Rotator::createRotate($path)->keep(3)->run(false);

        $this->assertContains($path, $rotated);
        $this->assertFileExists($path . '.1');
        $this->assertFileDoesNotExist($path);
    }

    public function testOldestCopyIsDeletedOnceKeepIsReached() {
        $path = $this->tempDir . '/cron.log';
        for ($run = 1; $run <= 4; $run++) {
            file_put_contents($path, 'run ' . $run);
            $rotated = CLogger_Rotator::createRotate($path)->keep(3)->run(false);
        }

        //run ke-4: .3 (berisi run 1) dihapus dulu sebelum .2 digeser ke .3
        $this->assertContains($path . '.3', $rotated);
        $this->assertSame('run 4', file_get_contents($path . '.1'));
        $this->assertSame('run 3', file_get_contents($path . '.2'));
        $this->assertSame('run 2', file_get_contents($path . '.3'));
        $this->assertFileDoesNotExist($path . '.4');
    }

    public function testFilenamePartWithASlashIsRejected() {
        $format = new CLogger_Rotator_FilenameFormat($this->tempDir . '/cron.log');

        $this->expectException(CLogger_Rotator_Exception_FilenameFormatException::class);
        $format->extractRegex('a/b.log');
    }
}
