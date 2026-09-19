<?php
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * CFConsole registry, CConsole_Kernel/Application, dan harness CTesting `$this->cf()`.
 */
class ConsoleKernelTest extends CTesting_TestCase {
    protected function setUp(): void {
        parent::setUp();
        static::registerConsoleCommands();
    }

    public function testEveryRegisteredCommandClassExistsAndHasAUniqueName() {
        $names = [];
        foreach (array_unique(CFConsole::$defaultCommands) as $class) {
            $this->assertTrue(class_exists($class), $class);
            $command = c::container()->make($class);
            $this->assertInstanceOf(Symfony\Component\Console\Command\Command::class, $command, $class);
            $name = $command->getName();
            $this->assertNotEmpty($name, $class . ' tanpa nama');
            $this->assertArrayNotHasKey($name, $names, $name . ' dipakai dua kelas: ' . $class . ' & ' . carr::get($names, $name));
            $names[$name] = $class;
        }
        $this->assertGreaterThan(100, count($names));
    }

    public function testDuplicatesInTheDefaultListAreTheSameClass() {
        $counts = array_count_values(CFConsole::$defaultCommands);
        $duplicates = array_keys(array_filter($counts, function ($count) {
            return $count > 1;
        }));
        $this->assertSame([CConsole_Command_DevSuite_DevSuiteStartCommand::class], $duplicates, 'satu-satunya duplikat yang diketahui (tidak berbahaya, Symfony menimpa dengan instance yang sama namanya)');
    }

    public function testAddCommandRejectsUnknownClass() {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('not exists');
        CFConsole::addCommand('CConsole_Command_TidakAda');
    }

    public function testKernelListsAllDefaultCommands() {
        $all = (new CConsole_Kernel())->all();
        $this->assertArrayHasKey('version', $all);
        $this->assertArrayHasKey('about', $all);
        $this->assertArrayHasKey('test', $all);
        $this->assertArrayHasKey('make:model', $all);
        $this->assertArrayHasKey('claude:sync', $all);
        $this->assertArrayHasKey('resource:clean', $all);
        $this->assertArrayHasKey('list', $all, 'command bawaan Symfony ikut');
    }

    public function testKernelCallCapturesOutput() {
        $kernel = new CConsole_Kernel();
        $exit = $kernel->call('version');
        $this->assertSame(0, $exit);
        $this->assertStringContainsString(CF::version(), $kernel->output());

        $buffer = new BufferedOutput();
        $kernel->call('env', [], $buffer);
        $this->assertStringContainsString(CF::environment(), $buffer->fetch());
    }

    public function testKernelCallUnknownCommandThrows() {
        $this->expectException(Symfony\Component\Console\Exception\CommandNotFoundException::class);
        (new CConsole_Kernel())->call('perintah:tidak-ada');
    }

    public function testClosureCommandsRegisteredThroughTheKernel() {
        $kernel = new CConsole_Kernel();
        $seen = [];
        $kernel->command('uji:salam {nama} {--keras}', function ($nama) use (&$seen) {
            $seen[] = $nama;
            $this->line(($this->option('keras') ? strtoupper('halo ' . $nama) : 'halo ' . $nama));
        })->describe('uji closure command');
        $this->assertSame(0, $kernel->call('uji:salam', ['nama' => 'Hery', '--keras' => true]));
        $this->assertSame(['Hery'], $seen);
        $this->assertStringContainsString('HALO HERY', $kernel->output());
        $this->assertSame('uji closure command', $kernel->all()['uji:salam']->getDescription());
    }

    public function testCfHarnessExpectsOutputAndExitCode() {
        $this->cf('version')
            ->expectsOutput(CF::version())
            ->assertExitCode(0);
    }

    public function testCfHarnessSubstringsAndAssertSuccessful() {
        $this->cf('env')
            ->expectsOutputToContain(CF::environment())
            ->doesntExpectOutputToContain('tidak-mungkin-muncul')
            ->assertSuccessful();
    }

    public function testCfHarnessDetectsUnexpectedOutput() {
        $pending = $this->cf('version')->expectsOutput('bukan versi');
        try {
            $pending->run();
            $this->fail('harus gagal karena output tidak cocok');
        } catch (PHPUnit\Framework\Exception\AssertionFailedError $e) {
            $this->assertStringContainsString('bukan versi', $e->getMessage());
            $this->assertStringContainsString('was not printed', $e->getMessage());
        }
        $this->expectedOutput = [];
    }

    public function testCfHarnessWithoutMockingReturnsExitCode() {
        $this->withoutMockingConsoleOutput();
        $this->assertSame(0, $this->cf('version'));
        $this->mockConsoleOutput = true;
    }

    public function testQueuedCommandRunsThroughTheKernelWhenTheJobIsHandled() {
        $seen = [];
        $kernel = new CConsole_Kernel();
        $kernel->command('uji:antre {x}', function ($x) use (&$seen) {
            $seen[] = $x;
        });
        c::container()->instance(CConsole_KernelInterface::class, $kernel);
        try {
            $job = new CConsole_QueuedCommand(['uji:antre', ['x' => 'dari-antrean']]);
            $this->assertInstanceOf(CQueue_ShouldQueueInterface::class, $job);
            c::container()->call([$job, 'handle']);
            $this->assertSame(['dari-antrean'], $seen);
        } finally {
            c::container()->forgetInstance(CConsole_KernelInterface::class);
        }
    }

    public function testKernelQueueDispatchesAQueuedCommandJob() {
        $fake = new CTesting_Fake_Base_BusFake(CQueue::dispatcher());
        $original = CQueue::dispatcher();
        $dispatch = new ReflectionProperty(CQueue::class, 'dispatcher');
        $dispatch->setAccessible(true);
        $dispatch->setValue(null, $fake);
        try {
            $pending = (new CConsole_Kernel())->queue('version', ['--ansi' => true]);
            $this->assertInstanceOf(CQueue_PendingDispatch::class, $pending);
            unset($pending);
            $fake->assertDispatched(CConsole_QueuedCommand::class);
        } finally {
            $dispatch->setValue(null, $original);
        }
    }

    public function testApplicationFormatCommandString() {
        $formatted = CConsole_Application::formatCommandString('queue:work');
        $this->assertStringEndsWith(' queue:work', $formatted);
        $this->assertStringContainsString(CConsole_Application::cfBinary(), $formatted);
    }

    public function testApplicationCallByClassName() {
        $application = new CConsole_Application();
        $application->resolveCommands([CConsole_Command_VersionCommand::class]);
        $application->setContainerCommandLoader();
        $this->assertSame(0, $application->call(CConsole_Command_VersionCommand::class));
        $this->assertStringContainsString(CF::version(), $application->output());
    }

    public function testCommandEventsAreDispatchedAroundRun() {
        $events = [];
        CEvent::dispatcher()->listen(CConsole_Event_CommandStarting::class, function ($event) use (&$events) {
            $events[] = 'starting:' . $event->command;
        });
        CEvent::dispatcher()->listen(CConsole_Event_CommandFinished::class, function ($event) use (&$events) {
            $events[] = 'finished:' . $event->command . ':' . $event->exitCode;
        });
        (new CConsole_Kernel())->call('version');
        $this->assertContains('starting:version', $events);
        $this->assertContains('finished:version:0', $events);
    }
}
