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

    /**
     * 5 log, 2 per chunk: ts 10, 11 | 500, 501 | 502.
     *
     * @return CLogger_Reader_LogIndex
     */
    private function chunkedIndex() {
        $index = new CLogger_Reader_LogIndex(new CLogger_Reader_LogFile($this->tempDir . '/chunked-' . uniqid() . '.log'));
        $index->setMaxChunkSize(2);
        foreach ([10, 11, 500, 501, 502] as $i => $timestamp) {
            $index->addToIndex($i * 10, $timestamp, 'info', $i);
        }

        return $index;
    }

    public function testDateFilterIsRespectedWhenSkippingChunks() {
        $index = $this->chunkedIndex();
        $index->forDateRange(500)->skip(2);
        $this->assertSame([4 => 40], $index->getFlatIndex());

        $index = $this->chunkedIndex();
        $index->forDateRange(11, 500)->skip(1);
        $this->assertSame([2 => 20], $index->getFlatIndex());
        $this->assertSame([500 => ['info' => [2 => 20]]], $index->get());
    }

    public function testCountRespectsTheDateFilter() {
        $this->assertSame(3, $this->chunkedIndex()->forDateRange(500)->count());
        $this->assertSame(2, $this->chunkedIndex()->forDateRange(11, 500)->count());
        $this->assertSame(0, $this->chunkedIndex()->forDateRange(600)->count());
        $this->assertSame(5, $this->chunkedIndex()->count());
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

    public function testLongLineIsNotSplitIntoAFakeLog() {
        $path = $this->tempDir . '/long.log';
        $prefix = '[2026-10-16 10:00:01] local.ERROR: ';
        //potongan setelah 1023 byte pertama kebetulan diawali timestamp
        file_put_contents($path, $prefix . str_repeat('x', 1023 - strlen($prefix)) . "[2026-10-16 10:00:02] local.INFO: bukan log baru\n");
        $reader = new CLogger_Reader_LogReader(new CLogger_Reader_LogFile($path));

        $this->assertSame(1, $reader->scan()->count());
    }

    public function testFileTimestampsCoverTheWholeFileEvenWhenScannedBySearch() {
        $path = $this->tempDir . '/ts.log';
        file_put_contents($path, $this->logLines(3));
        $file = new CLogger_Reader_LogFile($path);
        (new CLogger_Reader_LogReader($file))->search('ke-2')->scan();

        $this->assertSame(strtotime('2026-10-16 10:00:01'), $file->earliestTimestamp());
        $this->assertSame(strtotime('2026-10-16 10:00:03'), $file->latestTimestamp());
    }

    public function testSearchIndexKeepsTheLogNumberWhenTheLastLogDidNotMatch() {
        $path = $this->tempDir . '/num.log';
        file_put_contents($path, "[2026-10-16 10:00:01] local.ERROR: cocok satu\n[2026-10-16 10:00:02] local.ERROR: lain\n");
        $reader = new CLogger_Reader_LogReader(new CLogger_Reader_LogFile($path));
        $reader->search('cocok')->scan();

        file_put_contents($path, "[2026-10-16 10:00:03] local.ERROR: cocok dua\n", FILE_APPEND);
        $reader = new CLogger_Reader_LogReader(new CLogger_Reader_LogFile($path));
        $reader->search('cocok')->scan();

        //nomor log mengikuti urutan di berkas (0, 1, 2), jadi log-index:2 menunjuk 'cocok dua'
        $this->assertSame([0, 2], array_keys($reader->index()->getFlatIndex()));
    }

    /**
     * @param string $content
     *
     * @return CLogger_Reader_Log
     */
    private function firstLog($content) {
        $path = $this->tempDir . '/parse-' . uniqid() . '.log';
        file_put_contents($path, $content);

        return (new CLogger_Reader_LogReader(new CLogger_Reader_LogFile($path)))->scan()->get(1)[0];
    }

    public function testLineWithoutEnvironmentAndLevelIsReadWithoutWarnings() {
        $warnings = [];
        set_error_handler(function ($errno, $errstr) use (&$warnings) {
            $warnings[] = $errstr;

            return true;
        });

        try {
            $log = $this->firstLog("[2026-10-16 10:00:01] cron finished ok\n");
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $warnings);
        $this->assertSame('cron finished ok', $log->text);
        $this->assertSame('2026-10-16 10:00:01', $log->time->format('Y-m-d H:i:s'));
    }

    public function testMiddleTextKeepsItsTrailingDot() {
        $log = $this->firstLog("[2026-10-16 10:00:01] a.local.ERROR: pesan\n");

        $this->assertSame('local', $log->environment);
        $this->assertSame('a. pesan', $log->text);
    }

    public function testItemCallbackIsAppliedToALogIndexSearch() {
        $path = $this->tempDir . '/cb.log';
        file_put_contents($path, $this->logLines(3));
        $reader = new CLogger_Reader_LogReader(new CLogger_Reader_LogFile($path));
        $reader->scan();

        $page = $reader->search('log-index:1')->paginate(25, 1, function (CLogger_Reader_Log $log) {
            return ['text' => $log->text];
        });

        $this->assertSame([['text' => 'pesan ke-2']], $page->items());
    }

    public function testANormalSearchAfterALogIndexSearchShowsAllMatches() {
        $path = $this->tempDir . '/again.log';
        file_put_contents($path, $this->logLines(3));
        //mtime di detik ini membuat requiresScan() menunda scan dalam request yang sama
        touch($path, time() - 60);
        $reader = new CLogger_Reader_LogReader(new CLogger_Reader_LogFile($path));
        $reader->scan();
        $reader->search('log-index:1')->paginate(25, 1);

        //reader yang sama (LogReader::instance() di-cache per path) dipakai untuk search berikutnya
        $this->assertSame(3, $reader->search('pesan')->scan()->paginate(25, 1)->total());
    }

    public function testSubFolderIsTheDirectoryEvenWhenItContainsTheFileName() {
        $file = new CLogger_Reader_LogFile($this->tempDir . '/app.log.d/app.log');

        $this->assertSame($this->tempDir . '/app.log.d', $file->subFolder);
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

    public function testKeywordIsARegexOrLiteralTextWhenItIsNotAValidRegex() {
        $path = $this->tempDir . '/kw.log';
        file_put_contents($path, "[2026-10-16 10:00:01] local.ERROR: gagal di app/Http/Kernel.php\n"
            . "[2026-10-16 10:00:02] local.ERROR: Undefined index [x\n"
            . "[2026-10-16 10:00:03] local.INFO: selesai (ok)\n");
        $count = function ($keyword) use ($path) {
            return (new CLogger_Reader_LogReader(new CLogger_Reader_LogFile($path)))->search($keyword)->scan()->count();
        };

        $this->assertSame(1, $count('app/Http'));
        $this->assertSame(1, $count('index [x'));
        $this->assertSame(1, $count('(ok'));
        $this->assertSame(2, $count('kernel|selesai'));
        $this->assertSame(0, $count('index [y'));
    }

    public function testFullTextMatchesAcceptsAnInvalidRegexKeyword() {
        $log = $this->firstLog("[2026-10-16 10:00:01] local.ERROR: Undefined index [x\n");

        $this->assertTrue($log->fullTextMatches('index [x'));
        $this->assertTrue($log->fullTextMatches('undefined|nope'));
        $this->assertFalse($log->fullTextMatches('index [y'));
    }
}
