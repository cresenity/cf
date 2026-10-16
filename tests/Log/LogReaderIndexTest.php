<?php

use PHPUnit\Framework\TestCase;

/**
 * CLogger_Reader index - pakai cache array dan berkas log di direktori temp sendiri.
 */
class LogReaderIndexTest extends TestCase {
    /**
     * @var string
     */
    private $tempDir;

    /**
     * @var mixed
     */
    private $originalCacheDriver;

    protected function setUp(): void {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cf-log-reader-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
        $this->originalCacheDriver = CConfig::repository()->get('log.reader.cache_driver');
        CConfig::repository()->set('log.reader.cache_driver', 'array');
    }

    protected function tearDown(): void {
        CConfig::repository()->set('log.reader.cache_driver', $this->originalCacheDriver);
        CFile::deleteDirectory($this->tempDir);
    }

    /**
     * @param int $count
     *
     * @return string
     */
    private function logLines($count) {
        $lines = '';
        for ($i = 1; $i <= $count; $i++) {
            $lines .= '[2026-10-16 10:00:0' . $i . '] local.ERROR: pesan ke-' . $i . "\n";
        }

        return $lines;
    }

    public function testClearCacheResetsTheCurrentChunk() {
        $index = new CLogger_Reader_LogIndex(new CLogger_Reader_LogFile($this->tempDir . '/a.log'));
        $index->addToIndex(0, 100, 'error', 0);
        $index->addToIndex(50, 101, 'info', 1);
        $index->addToIndex(90, 102, 'info', 2);
        $index->save();
        $this->assertSame(3, $index->count());

        $index->clearCache();
        $this->assertSame(0, $index->count());

        $index->addToIndex(0, 200, 'debug', 0);
        $this->assertSame(1, $index->count());
    }

    public function testRescanAfterTheFileShrinksDropsTheOldEntries() {
        $path = $this->tempDir . '/b.log';
        file_put_contents($path, $this->logLines(3));
        $reader = new CLogger_Reader_LogReader(new CLogger_Reader_LogFile($path));
        $this->assertSame(3, $reader->scan()->count());

        //log di-truncate/dirotasi lalu dibuka di request berikutnya (reader baru, cache index tetap):
        //berkas mengecil sehingga scan() memaksa index ulang
        file_put_contents($path, $this->logLines(1));
        $reader = new CLogger_Reader_LogReader(new CLogger_Reader_LogFile($path));
        $this->assertSame(1, $reader->scan()->count());
    }

    public function testSearchByLogIndex() {
        $path = $this->tempDir . '/c.log';
        file_put_contents($path, $this->logLines(3));
        $reader = new CLogger_Reader_LogReader(new CLogger_Reader_LogFile($path));
        $reader->scan();

        //indeks 0 ada di posisi byte 0 - tidak boleh dianggap tidak ditemukan
        $first = $reader->search('log-index:0')->paginate(25, 1);
        $this->assertSame(1, $first->total());
        $this->assertStringContainsString('pesan ke-1', $first->items()[0]->text);

        $missing = $reader->search('log-index:999999')->paginate(25, 1);
        $this->assertSame(0, $missing->total());
        $this->assertSame([], $missing->items());
    }

    public function testExceptLevelsExcludesTheGivenLevels() {
        $path = $this->tempDir . '/d.log';
        file_put_contents($path, "[2026-10-16 10:00:01] local.ERROR: satu\n[2026-10-16 10:00:02] local.INFO: dua\n[2026-10-16 10:00:03] local.ERROR: tiga\n");
        $reader = new CLogger_Reader_LogReader(new CLogger_Reader_LogFile($path));
        $reader->scan();

        $this->assertSame(1, $reader->exceptLevels('error')->count());
        $this->assertSame(1, $reader->except(['ERROR'])->count());
        $this->assertSame(2, $reader->exceptLevels('info')->count());
        $this->assertSame(3, $reader->exceptLevels(null)->count());
    }

    public function testDirectionSurvivesASearch() {
        $path = $this->tempDir . '/e.log';
        file_put_contents($path, $this->logLines(3));
        $reader = new CLogger_Reader_LogReader(new CLogger_Reader_LogFile($path));

        //pola CTrait_Controller_Application_Log_System: arah diset dulu, baru search
        $logs = $reader->setDirection(CLogger_Reader_Direction::BACKWARD)->search('pesan')->scan()->get(3);

        $this->assertCount(3, $logs);
        $this->assertStringContainsString('pesan ke-3', $logs[0]->text);
        $this->assertStringContainsString('pesan ke-1', $logs[2]->text);
    }
}
