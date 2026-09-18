<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/CronFrequencyPortTest.php';

/**
 * Port EventTest + ScheduleTest hulu: penyusunan perintah shell (output, append, user,
 * background), isDue/nextRunDate/filter/callback, mutex tanpa-tumpang-tindih pada Event dan
 * CallbackEvent, serta CCron_Schedule sebagai pabrik event (call/exec/command, parameter,
 * dueEvents, timezone).
 */
class CronEventAndSchedulePortTest extends TestCase {
    /**
     * @var string
     */
    protected $defaultTimezone;

    protected function setUp(): void {
        $this->defaultTimezone = date_default_timezone_get();
        date_default_timezone_set('UTC');
    }

    protected function tearDown(): void {
        date_default_timezone_set($this->defaultTimezone);
        CCarbon::setTestNow(null);
    }

    /**
     * @param string $command
     *
     * @return CCron_Event
     */
    protected function event($command = 'php -i') {
        return new CCron_Event(new UjiCron_NullMutex(), $command);
    }

    // ---- buildCommand (unix; dev berjalan di Linux) ----

    public function testBuildCommandUsingUnix() {
        if (c::windowsOs()) {
            $this->markTestSkipped('khusus unix');
        }
        $this->assertSame("php -i > '/dev/null' 2>&1", $this->event()->buildCommand());
    }

    public function testBuildCommandSendOutputTo() {
        $this->assertSame("php -i > '/dev/null' 2>&1", $this->event()->sendOutputTo('/dev/null')->buildCommand());
        $this->assertSame("php -i > '/my folder/foo.log' 2>&1", $this->event()->sendOutputTo('/my folder/foo.log')->buildCommand(), 'path berspasi di-escape');
    }

    public function testBuildCommandAppendOutput() {
        $this->assertSame("php -i >> '/dev/null' 2>&1", $this->event()->appendOutputTo('/dev/null')->buildCommand());
    }

    public function testBuildCommandWithUserUsingUnix() {
        if (c::windowsOs()) {
            $this->markTestSkipped('khusus unix');
        }
        $this->assertSame("sudo -u sandbox -- sh -c 'php -i > '/dev/null' 2>&1'", $this->event()->user('sandbox')->buildCommand());
    }

    public function testBuildCommandInBackgroundUsingUnix() {
        if (c::windowsOs()) {
            $this->markTestSkipped('khusus unix');
        }
        $event = $this->event()->runInBackground();
        $command = $event->buildCommand();
        $finish = CConsole_Application::formatCommandString('schedule:finish');
        $this->assertSame("(php -i > '/dev/null' 2>&1 ; {$finish} \"{$event->mutexName()}\" \"\$?\") > '/dev/null' 2>&1 &", $command);
        $this->assertStringContainsString('schedule:finish', $command);
    }

    public function testBuildCommandInBackgroundWithUserUsingUnix() {
        if (c::windowsOs()) {
            $this->markTestSkipped('khusus unix');
        }
        $event = $this->event()->user('sandbox')->runInBackground();
        $this->assertStringStartsWith("sudo -u sandbox -- sh -c '(php -i > '/dev/null' 2>&1 ; ", $event->buildCommand());
        $this->assertStringEndsWith(" > '/dev/null' 2>&1 &'", $event->buildCommand());
    }

    // ---- mutex & nama ----

    public function testMutexNameDependsOnExpressionAndCommand() {
        $a = $this->event('php a');
        $b = $this->event('php b');
        $this->assertSame('framework' . DIRECTORY_SEPARATOR . 'schedule-' . sha1('* * * * *php a'), $a->mutexName());
        $this->assertNotSame($a->mutexName(), $b->mutexName());
        $this->assertNotSame($a->mutexName(), $this->event('php a')->hourly()->mutexName(), 'ekspresi berbeda = mutex berbeda');
    }

    public function testCallbackEventMutexNameUsesTheDescription() {
        $event = new CCron_CallbackEvent(new UjiCron_NullMutex(), function () {
        });
        $event->name('laporan harian');
        $this->assertSame('framework/schedule-' . sha1('laporan harian'), $event->mutexName());
        $this->assertSame('laporanharian', $event->getName(), 'nama folder log tanpa spasi');
        $this->assertSame('laporan harian', $event->getSummaryForDisplay());
    }

    public function testCallbackEventSummaryFallsBackToTheCallbackString() {
        $event = new CCron_CallbackEvent(new UjiCron_NullMutex(), 'strtoupper');
        $this->assertSame('strtoupper', $event->getSummaryForDisplay());
        $this->assertSame('Callback', (new CCron_CallbackEvent(new UjiCron_NullMutex(), function () {
        }))->getSummaryForDisplay());
    }

    public function testCallbackEventRejectsANonCallable() {
        $this->expectException(InvalidArgumentException::class);
        new CCron_CallbackEvent(new UjiCron_NullMutex(), 12345);
    }

    public function testCallbackEventWithoutOverlappingRequiresAName() {
        $this->expectException(LogicException::class);
        (new CCron_CallbackEvent(new UjiCron_NullMutex(), function () {
        }))->withoutOverlapping();
    }

    public function testWithoutOverlappingSkipsWhenTheMutexExists() {
        $mutex = new UjiCron_NullMutex();
        $event = new CCron_Event($mutex, 'php -i');
        $event->withoutOverlapping();
        $this->assertTrue($event->filtersPass());
        $mutex->existing = true;
        $this->assertFalse($event->filtersPass(), 'skip() yang ditambahkan withoutOverlapping melihat mutex');
        $this->assertTrue($event->isDue(), 'isDue tidak memandang filter');
    }

    public function testCallbackEventRunAcquiresAndReleasesTheMutex() {
        $mutex = new UjiCron_NullMutex();
        $ran = 0;
        $event = new CCron_CallbackEvent($mutex, function () use (&$ran) {
            $ran++;
        });
        $event->name('uji-mutex')->withoutOverlapping();
        $event->run();
        $this->assertSame(1, $ran);
        $this->assertSame(1, $mutex->created);
        $this->assertSame(1, $mutex->forgotten, 'mutex dilepas setelah selesai');

        $mutex->existing = true;
        $event->run();
        $this->assertSame(1, $ran, 'tidak jalan saat mutex tidak bisa dibuat');
    }

    public function testCallbackEventRunReleasesTheMutexEvenWhenTheCallbackThrows() {
        $mutex = new UjiCron_NullMutex();
        $event = new CCron_CallbackEvent($mutex, function () {
            throw new RuntimeException('meledak');
        });
        $event->name('uji-gagal')->withoutOverlapping();
        try {
            $event->run();
            $this->fail('harus melempar');
        } catch (RuntimeException $e) {
            $this->assertSame('meledak', $e->getMessage());
        }
        $this->assertSame(1, $mutex->forgotten);
        $this->assertSame(1, $event->exitCode);
    }

    public function testCallbackEventRunPassesParametersAndRecordsExitCode() {
        $seen = null;
        $event = new CCron_CallbackEvent(new UjiCron_NullMutex(), function ($a, $b) use (&$seen) {
            $seen = [$a, $b];

            return false;
        }, ['a' => 1, 'b' => 2]);
        $this->assertFalse($event->run());
        $this->assertSame([1, 2], $seen);
        $this->assertSame(1, $event->exitCode, 'false = exit code 1');

        $event = new CCron_CallbackEvent(new UjiCron_NullMutex(), function () {
            return 'ok';
        });
        $this->assertSame('ok', $event->run());
        $this->assertSame(0, $event->exitCode);
    }

    public function testBeforeAndAfterCallbacksRunAroundTheCallback() {
        $order = [];
        $event = new CCron_CallbackEvent(new UjiCron_NullMutex(), function () use (&$order) {
            $order[] = 'run';
        });
        $event->before(function () use (&$order) {
            $order[] = 'before';
        })->after(function () use (&$order) {
            $order[] = 'after';
        })->then(function () use (&$order) {
            $order[] = 'then';
        });
        $event->run();
        $this->assertSame(['before', 'run', 'after', 'then'], $order);
    }

    public function testOnSuccessAndOnFailureFollowTheExitCode() {
        $seen = [];
        $ok = new CCron_CallbackEvent(new UjiCron_NullMutex(), function () {
            return true;
        });
        $ok->onSuccess(function () use (&$seen) {
            $seen[] = 'success';
        })->onFailure(function () use (&$seen) {
            $seen[] = 'failure';
        });
        $ok->run();
        $bad = new CCron_CallbackEvent(new UjiCron_NullMutex(), function () {
            return false;
        });
        $bad->onSuccess(function () use (&$seen) {
            $seen[] = 'success2';
        })->onFailure(function () use (&$seen) {
            $seen[] = 'failure2';
        });
        $bad->run();
        $this->assertSame(['success', 'failure2'], $seen);
    }

    // ---- isDue / filter / lingkungan ----

    public function testIsDueFollowsTheExpressionAndTestNow() {
        CCarbon::setTestNow(CCarbon::create(2026, 1, 15, 8, 30, 0, 'UTC'));
        $this->assertTrue($this->event()->everyMinute()->isDue());
        $this->assertTrue($this->event()->dailyAt('8:30')->isDue());
        $this->assertFalse($this->event()->dailyAt('8:31')->isDue());
        $this->assertTrue($this->event()->days(4)->isDue(), '2026-01-15 = Kamis');
        $this->assertFalse($this->event()->days(1)->isDue());
    }

    public function testIsDueHonoursTheEventTimezone() {
        CCarbon::setTestNow(CCarbon::create(2026, 1, 15, 1, 0, 0, 'UTC'));
        $this->assertTrue($this->event()->dailyAt('1:00')->isDue());
        $this->assertFalse($this->event()->dailyAt('1:00')->timezone('Asia/Jakarta')->isDue(), '01:00 UTC = 08:00 WIB');
        $this->assertTrue($this->event()->dailyAt('8:00')->timezone('Asia/Jakarta')->isDue());
    }

    public function testEnvironmentsFilterIsDue() {
        $current = CF::environment();
        $this->assertTrue($this->event()->environments($current)->isDue());
        $this->assertTrue($this->event()->environments([$current, 'lain'])->isDue());
        $this->assertFalse($this->event()->environments('lingkungan-tak-ada')->isDue());
        $this->assertTrue($this->event()->runsInEnvironment('apa saja'), 'tanpa daftar = semua lingkungan');
    }

    public function testWhenAndSkipFiltersReceiveNoArgumentsAndMayBeBooleans() {
        $this->assertTrue($this->event()->when(true)->filtersPass());
        $this->assertFalse($this->event()->when(false)->filtersPass());
        $this->assertFalse($this->event()->skip(true)->filtersPass());
        $this->assertTrue($this->event()->skip(false)->filtersPass());
        $this->assertFalse($this->event()->when(function () {
            return true;
        })->skip(function () {
            return true;
        })->filtersPass(), 'satu penolak cukup');
    }

    public function testFilterCallbacksMayBeInvokableObjects() {
        $this->assertFalse($this->event()->when(new UjiCron_FalseInvokable())->filtersPass());
        $this->assertTrue($this->event()->skip(new UjiCron_FalseInvokable())->filtersPass());
    }

    public function testEvenInMaintenanceMode() {
        $event = $this->event();
        $this->assertFalse($event->runsInMaintenanceMode());
        $this->assertTrue($event->evenInMaintenanceMode()->runsInMaintenanceMode());
    }

    public function testNextRunDate() {
        CCarbon::setTestNow(CCarbon::create(2026, 1, 15, 8, 30, 0, 'UTC'));
        $event = $this->event()->dailyAt('10:15');
        $this->assertSame('2026-01-15 10:15:00', $event->nextRunDate()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-16 10:15:00', $event->nextRunDate('now', 1)->format('Y-m-d H:i:s'), 'nth = 1 lompat satu kejadian');
        $this->assertSame('2026-01-16 00:00:00', $this->event()->daily()->nextRunDate()->format('Y-m-d H:i:s'));
    }

    public function testNextRunDateWithTimezone() {
        CCarbon::setTestNow(CCarbon::create(2026, 1, 15, 8, 30, 0, 'UTC'));
        $next = $this->event()->dailyAt('10:15')->timezone('Asia/Jakarta')->nextRunDate();
        $this->assertSame('2026-01-16 10:15:00 Asia/Jakarta', $next->format('Y-m-d H:i:s e'), '08:30 UTC = 15:30 WIB → besok');
    }

    public function testPreventOverlapsUsingSwapsTheMutex() {
        $first = new UjiCron_NullMutex();
        $second = new UjiCron_NullMutex();
        $second->existing = true;
        $event = new CCron_Event($first, 'php -i');
        $event->withoutOverlapping();
        $this->assertTrue($event->filtersPass());
        $event->preventOverlapsUsing($second);
        $this->assertFalse($event->filtersPass());
    }

    public function testNameDescriptionAndFluentSetters() {
        $event = $this->event()->name('Laporan Pagi')->user('www')->runInBackground()->onOneServer()->evenInMaintenanceMode();
        $this->assertSame('Laporan Pagi', $event->description);
        $this->assertSame('LaporanPagi', $event->getName());
        $this->assertSame('www', $event->user);
        $this->assertTrue($event->runInBackground);
        $this->assertTrue($event->onOneServer);
        $this->assertSame('Laporan Pagi', $event->getSummaryForDisplay(), 'nama menang atas perintah');
        $this->assertSame("php -i > '/dev/null' 2>&1", $this->event()->getSummaryForDisplay(), 'tanpa nama = perintah lengkap yang dibangun');
        $this->assertStringEndsWith('LaporanPagi', rtrim($event->getLogDirectory(), DIRECTORY_SEPARATOR));
    }

    // ---- Schedule ----

    public function testExecCreatesNewCommand() {
        $schedule = new CCron_Schedule();
        $schedule->exec('path/to/command');
        $schedule->exec('path/to/command -f --foo="bar"');
        $schedule->exec('path/to/command', ['-f']);
        $schedule->exec('path/to/command', ['--foo' => 'bar']);
        $schedule->exec('path/to/command', ['-f', '--foo' => 'bar']);
        $schedule->exec('path/to/command', ['--title' => 'A "real" test']);
        $schedule->exec('path/to/command', [['one', 'two']]);
        $schedule->exec('path/to/command', ['-1 minute']);
        $schedule->exec('path/to/command', ['foo' => ['bar', 'baz']]);
        $schedule->exec('path/to/command', ['--foo' => ['bar', 'baz']]);
        $schedule->exec('path/to/command', ['-F' => ['bar', 'baz']]);

        $events = $schedule->events();
        $this->assertCount(11, $events);
        $this->assertSame('path/to/command', $events[0]->command);
        $this->assertSame('path/to/command -f --foo="bar"', $events[1]->command);
        $this->assertSame('path/to/command -f', $events[2]->command);
        $this->assertSame("path/to/command --foo='bar'", $events[3]->command, 'nilai opsi di-escape (hulu juga)');
        $this->assertSame("path/to/command -f --foo='bar'", $events[4]->command);
        $this->assertSame("path/to/command --title='A \"real\" test'", $events[5]->command);
        $this->assertSame("path/to/command 'one' 'two'", $events[6]->command);
        $this->assertSame("path/to/command '-1 minute'", $events[7]->command);
        $this->assertSame("path/to/command 'bar' 'baz'", $events[8]->command, 'kunci tanpa tanda hubung = argumen posisi');
        $this->assertSame("path/to/command --foo='bar' --foo='baz'", $events[9]->command);
        $this->assertSame("path/to/command -F 'bar' -F 'baz'", $events[10]->command);
    }

    public function testExecCreatesNewCommandWithTimezone() {
        $schedule = new CCron_Schedule('Asia/Jakarta');
        $event = $schedule->exec('path/to/command');
        $this->assertSame('Asia/Jakarta', $event->timezone);
        $this->assertSame('Asia/Jakarta', $schedule->call(function () {
        })->timezone, 'call() juga mewarisi timezone jadwal');
    }

    public function testCommandCreatesNewConsoleCommand() {
        $schedule = new CCron_Schedule();
        $event = $schedule->command('queue:work', ['--tries' => 3]);
        $this->assertStringEndsWith(' queue:work --tries=3', $event->command);
        $this->assertStringContainsString(CConsole_Application::formatCommandString('queue:work'), $event->command);
    }

    public function testCallCreatesACallbackEventAndPassesParameters() {
        $schedule = new CCron_Schedule();
        $seen = null;
        $event = $schedule->call(function ($x) use (&$seen) {
            $seen = $x;
        }, ['x' => 42]);
        $this->assertInstanceOf(CCron_CallbackEvent::class, $event);
        $event->run();
        $this->assertSame(42, $seen);
    }

    public function testCronJobWrapsACronJobObject() {
        $schedule = new CCron_Schedule();
        $job = new UjiCron_Job();
        $event = $schedule->run($job);
        $this->assertSame('*/15 * * * *', $event->getExpression(), 'jadwal dari objek job');
        $this->assertSame('uji-job', $event->description);
        $this->assertSame('ok', $event->run());
        $this->assertSame(1, $job->executed);
    }

    public function testDueEventsFiltersByIsDue() {
        CCarbon::setTestNow(CCarbon::create(2026, 1, 15, 8, 30, 0, 'UTC'));
        $schedule = new CCron_Schedule();
        $schedule->exec('a')->everyMinute();
        $schedule->exec('b')->dailyAt('9:00');
        $schedule->exec('c')->dailyAt('8:30');
        $schedule->exec('d')->environments('lingkungan-tak-ada');
        $due = $schedule->dueEvents()->map(function ($e) {
            return $e->command;
        })->values()->all();
        $this->assertSame(['a', 'c'], $due);
    }

    public function testScheduleIsMacroable() {
        CCron_Schedule::macro('ujiNightly', function ($command) {
            return $this->exec($command)->dailyAt('23:00');
        });
        $schedule = new CCron_Schedule();
        $this->assertSame('0 23 * * *', $schedule->ujiNightly('php -i')->getExpression());
        $this->assertCount(1, $schedule->events());
    }
}

class UjiCron_FalseInvokable {
    public function __invoke() {
        return false;
    }
}

class UjiCron_Job extends CCron_Job {
    /** @var int */
    public $executed = 0;

    public function getSchedule() {
        return '*/15 * * * *';
    }

    public function getName() {
        return 'uji-job';
    }

    public function execute() {
        $this->executed++;

        return 'ok';
    }
}
