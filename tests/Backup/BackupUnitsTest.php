<?php
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * CBackup tanpa menjalankan dump/upload nyata: builder perintah dumper, seleksi berkas,
 * manifest + zip, helper, tujuan backup di disk lokal, dan strategi retensi.
 */
class BackupUnitsTest extends TestCase {
    /** @var string */
    protected $tmp;

    protected function setUp(): void {
        $this->tmp = rtrim(sys_get_temp_dir(), '/') . '/uji-backup-' . uniqid() . '/';
        mkdir($this->tmp, 0777, true);
    }

    protected function tearDown(): void {
        CFile::deleteDirectory($this->tmp);
        CBackup_Config::instance()->reset();
        Carbon::setTestNow();
    }

    /**
     * @return CBackup_Database_Dumper_MySqlDumper
     */
    protected function mysql() {
        return CBackup_Database_Dumper_MySqlDumper::create()->setDbName('toko')->setUserName('hery')->setPassword('rahasia');
    }

    public function testMysqlDumpCommandDefaults() {
        $command = $this->mysql()->getDumpCommand('dump.sql', 'creds.txt');
        $this->assertSame("'mysqldump' --defaults-extra-file=\"creds.txt\" --skip-comments --extended-insert toko > \"dump.sql\"", $command, 'skipComments aktif secara default');
        $this->assertStringNotContainsString('--skip-comments', $this->mysql()->dontSkipComments()->getDumpCommand('d', 'c'));
    }

    public function testMysqlDumpCommandOptions() {
        $dumper = $this->mysql()
            ->setDumpBinaryPath('/usr/bin')
            ->skipComments()
            ->dontUseExtendedInserts()
            ->useSingleTransaction()
            ->skipLockTables()
            ->useQuick()
            ->setSocket('/var/run/mysqld.sock')
            ->excludeTables(['log', 'cache'])
            ->setDefaultCharacterSet('utf8mb4')
            ->addExtraOption('--no-tablespaces')
            ->setGtidPurged('OFF')
            ->doNotCreateTables();
        $command = $dumper->getDumpCommand('/tmp/a b/dump.sql', 'c.txt');
        $this->assertStringStartsWith("'/usr/bin/mysqldump' --defaults-extra-file=\"c.txt\" --no-create-info --skip-comments --skip-extended-insert --single-transaction --skip-lock-tables --quick --socket=/var/run/mysqld.sock --ignore-table=toko.log --ignore-table=toko.cache --default-character-set=utf8mb4 --no-tablespaces --set-gtid-purged=OFF toko", $command);
        $this->assertStringEndsWith(' > "/tmp/a b/dump.sql"', $command);
    }

    public function testMysqlIncludeTablesAndBinaryName() {
        $command = $this->mysql()->includeTables('user, order')->setDumpBinaryName('mariadb-dump')->getDumpCommand('d.sql', 'c');
        $this->assertStringContainsString("'mariadb-dump'", $command);
        $this->assertStringContainsString('toko --tables user order', $command);
        $this->expectException(CBackup_Database_Exception_CannotSetParameterException::class);
        $this->mysql()->includeTables(['a'])->excludeTables(['b']);
    }

    public function testMysqlAllDatabasesAndDatabasesExtraOptions() {
        $command = CBackup_Database_Dumper_MySqlDumper::create()->setUserName('u')->addExtraOption('--all-databases')->getDumpCommand('d.sql', 'c');
        $this->assertStringContainsString('--all-databases', $command);
        $this->assertStringNotContainsString(' toko', $command);
        $this->assertMatchesRegularExpression('/--all-databases > "d\.sql"$/', $command, 'nama db tidak ditambahkan lagi');

        $dumper = CBackup_Database_Dumper_MySqlDumper::create()->setUserName('u')->addExtraOption('--databases toko gudang');
        $this->assertSame('toko', $dumper->getDbName());
        $this->assertStringEndsWith('--databases toko gudang > "d.sql"', $dumper->getDumpCommand('d.sql', 'c'));
    }

    public function testMysqlCompressorWrapsTheCommand() {
        $dumper = $this->mysql()->useCompressor(new CBackup_Compressor_GzipCompressor());
        $this->assertSame('gz', $dumper->getCompressorExtension());
        $command = $dumper->getDumpCommand('d.sql', 'c');
        $this->assertStringStartsWith("(((('mysqldump'", $command);
        $this->assertStringContainsString('| gzip > "d.sql") 3>&1) | (read x; exit $x))', $command);
        $this->assertSame('bz2', (new CBackup_Compressor_Bzip2Compressor())->useExtension());
        $this->assertSame('bzip2', (new CBackup_Compressor_Bzip2Compressor())->useCommand());
    }

    public function testMysqlCredentialsFile() {
        $contents = $this->mysql()->setHost('db.local')->setPort(3307)->getContentsOfCredentialsFile();
        $this->assertSame("[client]\nuser = 'hery'\npassword = 'rahasia'\nhost = 'db.local'\nport = '3307'", str_replace(PHP_EOL, "\n", $contents));
    }

    public function testMysqlDumpRefusesIncompleteCredentials() {
        try {
            CBackup_Database_Dumper_MySqlDumper::create()->setDbName('toko')->dumpToFile($this->tmp . 'x.sql');
            $this->fail('userName kosong harus ditolak');
        } catch (CBackup_Database_Exception_CannotStartDumpException $e) {
            $this->assertStringContainsString('userName', $e->getMessage());
        }
        try {
            CBackup_Database_Dumper_MySqlDumper::create()->setUserName('u')->setHost('h')->dumpToFile($this->tmp . 'x.sql');
            $this->fail('dbName kosong harus ditolak sebelum mysqldump dijalankan');
        } catch (CBackup_Database_Exception_CannotStartDumpException $e) {
            $this->assertStringContainsString('dbName', $e->getMessage());
        }
        $this->assertFileDoesNotExist($this->tmp . 'x.sql');
    }

    public function testPostgresAndSqliteDumpCommands() {
        $pg = CBackup_Database_Dumper_PostgreSqlDumper::create()->setDbName('toko')->setUserName('hery')->setHost('127.0.0.1')->setPort(5433)->includeTables(['a', 'b'])->useInserts()->doNotCreateTables();
        $command = $pg->getDumpCommand('d.sql');
        $this->assertSame("'pg_dump' -U hery -h 127.0.0.1 -p 5433 --inserts --data-only -t a -t b > \"d.sql\"", $command);

        $sqlite = CBackup_Database_Dumper_SqliteDumper::create()->setDbName('/data/app.sqlite')->setDumpBinaryPath('/opt/bin');
        $this->assertSame("echo 'BEGIN IMMEDIATE;\n.dump' | '/opt/bin/sqlite3' --bail '/data/app.sqlite' > \"d.sql\"", $sqlite->getDumpCommand('d.sql'));
    }

    public function testFileSelectionIncludesFilesAndDirectoriesMinusExclusions() {
        mkdir($this->tmp . 'src/vendor', 0777, true);
        mkdir($this->tmp . 'src/app', 0777, true);
        file_put_contents($this->tmp . 'src/app/a.php', 'a');
        file_put_contents($this->tmp . 'src/app/.env', 'e');
        file_put_contents($this->tmp . 'src/vendor/lib.php', 'v');
        file_put_contents($this->tmp . 'tunggal.txt', 't');

        $selected = iterator_to_array(CBackup_FileSelection::create([$this->tmp . 'src', $this->tmp . 'tunggal.txt'])
            ->excludeFilesFrom([$this->tmp . 'src/vendor', ''])
            ->selectedFiles(), false);
        sort($selected);
        $this->assertSame([
            $this->tmp . 'src/app',
            $this->tmp . 'src/app/.env',
            $this->tmp . 'src/app/a.php',
            $this->tmp . 'tunggal.txt',
        ], array_map(function ($p) {
            return str_replace(realpath($this->tmp), rtrim($this->tmp, '/'), $p);
        }, $selected), 'direktori ikut (CBackup_Zip::add membuat entri folder), dotfile ikut, vendor dikecualikan');

        $this->assertSame([], iterator_to_array(CBackup_FileSelection::create()->selectedFiles()), 'tanpa include → kosong');
    }

    public function testManifestAndZip() {
        file_put_contents($this->tmp . 'a.txt', 'aaa');
        file_put_contents($this->tmp . 'b.txt', 'bbbb');
        $manifest = CBackup_Manifest::create($this->tmp . 'manifest.txt')->addFiles([$this->tmp . 'a.txt', ''])->addFiles($this->tmp . 'b.txt');
        $this->assertSame([$this->tmp . 'a.txt', $this->tmp . 'b.txt'], iterator_to_array($manifest->files(), false));
        $this->assertSame(2, $manifest->count());

        mkdir($this->tmp . 'out');
        $zip = CBackup_Zip::createForManifest($manifest, $this->tmp . 'out/backup.zip');
        $this->assertFileExists($this->tmp . 'out/backup.zip');
        $this->assertSame(2, $zip->count());
        $this->assertGreaterThan(0, $zip->size());
        $this->assertStringEndsWith(' B', $zip->humanReadableSize());
        $archive = new ZipArchive();
        $archive->open($this->tmp . 'out/backup.zip');
        $names = [];
        for ($i = 0; $i < $archive->numFiles; $i++) {
            $names[] = $archive->getNameIndex($i);
        }
        $archive->close();
        sort($names);
        $this->assertSame(['/a.txt', '/b.txt'], array_map(function ($n) {
            return '/' . basename($n);
        }, $names), 'nama di dalam zip relatif terhadap folder zip bila berada di bawahnya');
    }

    public function testHelperFormats() {
        $this->assertSame('0 KB', CBackup_Helper::formatHumanReadableSize(0));
        $this->assertSame('512 B', CBackup_Helper::formatHumanReadableSize(512));
        $this->assertSame('1.5 KB', CBackup_Helper::formatHumanReadableSize(1536));
        $this->assertSame('2 MB', CBackup_Helper::formatHumanReadableSize(2 * 1024 * 1024));
        $this->assertSame('✅', CBackup_Helper::formatEmoji(true));
        $this->assertSame('❌', CBackup_Helper::formatEmoji(false));
        $this->assertStringStartsWith('2.00 (', CBackup_Helper::formatAgeInDays(Carbon::now()->subDays(2)));
    }

    public function testPeriodKeepsItsBounds() {
        $start = Carbon::parse('2026-01-10');
        $end = Carbon::parse('2026-01-01');
        $period = new CBackup_HouseKeeping_Period($start, $end);
        $this->assertTrue($start->eq($period->startDate()));
        $this->assertTrue($end->eq($period->endDate()));
        $this->assertTrue($period->startDate()->gt($period->endDate()), 'periode retensi berjalan mundur: start lebih baru dari end');
    }

    /**
     * @return CStorage_Adapter
     */
    protected function localDisk() {
        return CStorage::instance()->build(['driver' => 'local', 'root' => $this->tmp . 'disk']);
    }

    public function testBackupDestinationWritesListsAndMeasures() {
        $disk = $this->localDisk();
        CStorage::instance()->set('uji-backup', $disk);
        try {
            $destination = CBackup_BackupDestination::create('uji-backup', 'toko');
            $this->assertTrue($destination->isReachable());
            $this->assertNull($destination->connectionError());
            $this->assertSame('uji-backup', $destination->diskName());
            $this->assertSame('toko', $destination->backupName());
            $this->assertSame('localfilesystemadapter', $destination->filesystemType(), 'nama kelas adapter Flysystem, huruf kecil');
            $this->assertNull($destination->newestBackup());

            file_put_contents($this->tmp . 'one.zip', str_repeat('x', 100));
            $this->assertSame('toko/one.zip', $destination->write($this->tmp . 'one.zip'));
            $this->assertTrue($disk->exists('toko/one.zip'));
            $disk->put('toko/catatan.txt', 'bukan zip');
            $disk->put('toko/two.zip', str_repeat('y', 50));
            touch($this->tmp . 'disk/toko/two.zip', time() - 3600);

            $destination->fresh();
            $backups = $destination->backups();
            $this->assertInstanceOf(CBackup_RecordCollection::class, $backups);
            $this->assertCount(2, $backups, 'berkas non-zip diabaikan');
            $this->assertSame('toko/one.zip', $backups->newest()->path());
            $this->assertSame('toko/two.zip', $backups->oldest()->path());
            $this->assertSame(150, $destination->usedStorage());
            $this->assertFalse($destination->newestBackupIsOlderThan(Carbon::now()->subMinutes(5)), 'backup barusan tidak lebih tua dari 5 menit lalu');
            $this->assertTrue($destination->newestBackupIsOlderThan(Carbon::now()->addMinutes(5)));
            $this->assertTrue(CBackup_BackupDestination::create('uji-backup', 'kosong')->newestBackupIsOlderThan(Carbon::now()), 'tanpa backup sama sekali = sudah terlalu tua');
            $backups->oldest()->delete();
            $this->assertFalse($disk->exists('toko/two.zip'));
        } finally {
            CStorage::instance()->forgetDisk('uji-backup');
        }
    }

    public function testBackupDestinationWithUnknownDiskRecordsTheError() {
        $destination = CBackup_BackupDestination::create('disk-tidak-ada-' . uniqid(), 'toko');
        $this->assertFalse($destination->isReachable());
        $this->assertInstanceOf(Exception::class, $destination->connectionError());
        $this->assertCount(0, $destination->backups());
        $this->expectException(CBackup_Exception_InvalidBackupDestinationException::class);
        $destination->write(__FILE__);
    }

    public function testDefaultStrategyKeepsOnePerPeriodAndNeverTheNewest() {
        Carbon::setTestNow(Carbon::parse('2026-09-19 12:00:00'));
        CBackup_Config::instance()->setConfig(['house_keeping' => ['default_strategy' => [
            'keep_all_backups_for_days' => 2,
            'keep_daily_backups_for_days' => 7,
            'keep_weekly_backups_for_weeks' => 4,
            'keep_monthly_backups_for_months' => 3,
            'keep_yearly_backups_for_years' => 1,
            'delete_oldest_backups_when_using_more_megabytes_than' => 5000,
        ]]]);
        $disk = $this->localDisk();
        $ages = [0, 1, 1, 3, 3, 5, 10, 12, 40, 100, 400, 800];
        foreach ($ages as $i => $daysAgo) {
            $path = 'toko/b' . str_pad($i, 2, '0', STR_PAD_LEFT) . '.zip';
            $disk->put($path, 'zip');
            touch($this->tmp . 'disk/' . $path, Carbon::now()->subDays($daysAgo)->subMinutes($i)->timestamp);
        }
        $backups = CBackup_RecordCollection::createFromFiles($disk, $disk->allFiles('toko'));
        $this->assertCount(12, $backups);

        (new CBackup_HouseKeeping_Strategy_DefaultStrategy())->deleteOldBackups($backups);

        $remaining = array_map('basename', $disk->allFiles('toko'));
        sort($remaining);
        $this->assertContains('b00.zip', $remaining, 'terbaru tidak pernah dihapus');
        $this->assertContains('b01.zip', $remaining, 'dalam keep_all: 1 hari');
        $this->assertContains('b02.zip', $remaining, 'dalam keep_all: keduanya disimpan');
        $this->assertContains('b03.zip', $remaining, 'harian: satu per hari (yang lebih baru)');
        $this->assertNotContains('b04.zip', $remaining, 'harian: duplikat hari yang sama dihapus');
        $this->assertContains('b05.zip', $remaining);
        $this->assertNotContains('b11.zip', $remaining, '800 hari > horizon tahunan → dihapus');
        $this->assertContains('b10.zip', $remaining, '400 hari masih di dalam horizon tahunan (2+7+28+~90+365 hari) → satu per tahun disimpan');
        $this->assertContains('b09.zip', $remaining, '100 hari: satu per bulan');
    }

    public function testDefaultStrategyEnforcesMaximumStorage() {
        Carbon::setTestNow(Carbon::parse('2026-09-19 12:00:00'));
        CBackup_Config::instance()->setConfig(['house_keeping' => ['default_strategy' => [
            'keep_all_backups_for_days' => 30,
            'keep_daily_backups_for_days' => 30,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => 1,
        ]]]);
        $disk = $this->localDisk();
        foreach ([0, 1, 2, 3] as $i) {
            $disk->put('toko/b' . $i . '.zip', str_repeat('x', 400 * 1024));
            touch($this->tmp . 'disk/toko/b' . $i . '.zip', Carbon::now()->subDays($i)->timestamp);
        }
        $backups = CBackup_RecordCollection::createFromFiles($disk, $disk->allFiles('toko'));
        (new CBackup_HouseKeeping_Strategy_DefaultStrategy())->deleteOldBackups($backups);
        $remaining = array_map('basename', $disk->allFiles('toko'));
        sort($remaining);
        $this->assertSame(['b0.zip', 'b1.zip'], $remaining, '4 × 400KB > 1MB → yang tertua dihapus sampai ≤ 1MB');
    }

    public function testBackupTempHousekeepingPrunesOldFoldersInBothLayouts() {
        $disk = CTemporary::disk();
        $old = date('YmdHis', strtotime('-200 days'));
        $today = date('YmdHis');
        $files = [
            'backup/' . $old . '/lama.zip',
            'backup/' . $today . '/baru.zip',
            'backup/' . CF::appCode() . '/' . $old . '/lama-app.zip',
            'backup/' . CF::appCode() . '/' . $today . '/baru-app.zip',
            'backup/' . CF::appCode() . '/99999999/bukan-tanggal.zip',
        ];
        foreach ($files as $file) {
            $disk->put($file, 'zip');
        }
        try {
            $this->assertTrue(CHouseKeeping_FileTemp_BackupFileTemp::execute(90));
            $this->assertTrue(CHouseKeeping_FileTemp_BackupFileTemp::execute(90), 'satu folder per panggilan');
            $this->assertFalse(CHouseKeeping_FileTemp_BackupFileTemp::execute(90), 'tidak ada lagi yang kedaluwarsa');
            $this->assertFalse($disk->exists($files[0]));
            $this->assertFalse($disk->exists($files[2]));
            $this->assertTrue($disk->exists($files[1]));
            $this->assertTrue($disk->exists($files[3]));
            $this->assertTrue($disk->exists($files[4]), 'delapan digit yang bukan tanggal valid dilewati, bukan bikin housekeeping mati');
        } finally {
            foreach ([$today, CF::appCode() . '/' . $today, CF::appCode() . '/99999999', CF::appCode() . '/' . $old, $old] as $dir) {
                $disk->deleteDirectory('backup/' . $dir);
            }
        }
    }

    public function testConfigOverridesFallBackToAppConfig() {
        $config = CBackup_Config::instance();
        $config->setConfig(['house_keeping' => ['default_strategy' => ['keep_all_backups_for_days' => 99]]]);
        $this->assertSame(99, CBackup::getConfig('house_keeping.default_strategy.keep_all_backups_for_days'));
        $this->assertSame(CF::config('backup.house_keeping.default_strategy.keep_daily_backups_for_days'), CBackup::getConfig('house_keeping.default_strategy.keep_daily_backups_for_days'), 'kunci yang tidak dioverride jatuh ke config app');
        $this->assertSame('x', CBackup::getConfig('tidak.ada', 'x'));
    }
}
