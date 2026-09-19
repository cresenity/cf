<?php
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Generator yang menulis ke direktori sementara, bukan ke application/.
 */
class UjiConsole_MakeWidgetCommand extends CConsole_GeneratorCommand {
    protected $name = 'uji:make-widget';

    protected $type = 'Widget';

    /** @var string */
    public static $basePath;

    /** @var string */
    public static $stubPath;

    protected function getStub() {
        return static::$stubPath;
    }

    protected function rootNamespace() {
        return 'Uji';
    }

    protected function getDefaultNamespace($rootNamespace) {
        return $rootNamespace . '\Widget';
    }

    protected function getPath($name) {
        $name = cstr::replaceFirst($this->rootNamespace(), '', $name);

        return static::$basePath . str_replace('\\', '/', $name) . '.php';
    }

    protected function getOptions() {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Timpa bila sudah ada'],
        ];
    }
}

/**
 * Command interaktif untuk menguji ekspektasi pertanyaan/pilihan/tabel harness.
 */
class UjiConsole_InteractiveCommand extends CConsole_Command {
    protected $signature = 'uji:interaktif {--tabel}';

    public function handle() {
        if ($this->option('tabel')) {
            $this->table(['Kode', 'Nama'], [['A', 'Apel'], ['B', 'Beras']]);

            return 0;
        }
        $nama = $this->ask('Siapa nama Anda?');
        $lanjut = $this->confirm('Lanjutkan?');
        $warna = $this->choice('Pilih warna', ['merah', 'hijau', 'biru'], 1);
        $this->info('Halo ' . $nama . ', ' . ($lanjut ? 'lanjut' : 'berhenti') . ', ' . $warna);

        return $lanjut ? 0 : 2;
    }
}

/**
 * Command phpcf yang murni (tanpa jaringan/DB) lewat harness $this->cf(), generator, dan cron:*.
 */
class ConsoleCommandsTest extends CTesting_TestCase {
    /** @var string */
    protected $tmp;

    /** @var bool */
    protected static $interactiveRegistered = false;

    protected function setUp(): void {
        parent::setUp();
        static::registerConsoleCommands();
        if (!static::$interactiveRegistered) {
            static::$interactiveRegistered = true;
            CConsole_Application::starting(function ($cfCli) {
                $cfCli->add(new UjiConsole_InteractiveCommand());
            });
        }
        $this->tmp = rtrim(sys_get_temp_dir(), '/') . '/uji-console-' . uniqid() . '/';
        mkdir($this->tmp, 0777, true);
        UjiConsole_MakeWidgetCommand::$basePath = $this->tmp . 'libraries/';
        UjiConsole_MakeWidgetCommand::$stubPath = $this->tmp . 'widget.stub';
        file_put_contents(UjiConsole_MakeWidgetCommand::$stubPath, "<?php\n\nnamespace {{ namespace }};\n\nuse Zeta;\nuse Alpha;\n\nclass {{ class }} extends DummyRootNamespaceBase {\n}\n");
    }

    protected function tearDown(): void {
        CFile::deleteDirectory($this->tmp);
        parent::tearDown();
    }

    /**
     * @return CConsole_Kernel
     */
    protected function kernelWith(CConsole_Command $command) {
        $kernel = new CConsole_Kernel();
        $kernel->cfCli()->add($command);

        return $kernel;
    }

    public function testEnvAndVersionThroughKernel() {
        $this->cf('env')->expectsOutputToContain('Current application environment')->assertExitCode(0);
        $this->cf('version')->expectsOutput(CF::version())->assertSuccessful();
    }

    public function testAppCodeWithoutArgumentOnlyReports() {
        $this->cf('app:code')
            ->expectsOutputToContain('current app code for ' . CF::domain() . ' is: ' . CF::appCode())
            ->assertExitCode(0);
    }

    public function testAboutJsonListsEnvironmentAndDrivers() {
        $kernel = new CConsole_Kernel();
        $this->assertSame(0, $kernel->call('about', ['--json' => true]));
        $json = json_decode(trim($kernel->output()), true);
        $this->assertIsArray($json, 'keluaran --json harus JSON valid: ' . substr($kernel->output(), 0, 200));
        $this->assertArrayHasKey('environment', $json);
        $this->assertSame(CF::version(), $json['environment']['cf_version']);
        $this->assertSame(CF::appCode(), $json['environment']['application_code']);
        $this->assertArrayHasKey('drivers', $json);
    }

    public function testAboutOnlyFiltersSections() {
        $kernel = new CConsole_Kernel();
        $kernel->call('about', ['--json' => true, '--only' => 'drivers']);
        $json = json_decode(trim($kernel->output()), true);
        $this->assertSame(['drivers'], array_keys($json));
    }

    public function testCronListShowsScheduledEventsWithNextDue() {
        $schedule = CCron::schedule();
        $schedule->exec('echo uji-cron-list')->dailyAt('03:15')->description('Uji cron list');
        $kernel = new CConsole_Kernel();
        $this->assertSame(0, $kernel->call('cron:list'));
        $output = $kernel->output();
        $this->assertStringContainsString('echo uji-cron-list', $output);
        $this->assertStringContainsString('15 3 * * *', $output);
        $this->assertStringContainsString('Uji cron list', $output);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} 03:15:00 [+-]\d{2}:\d{2}/', $output, 'kolom Next Due terformat dengan zona waktu default app (tanpa --timezone)');

        $kernel->call('cron:list', ['--timezone' => 'UTC']);
        $this->assertStringContainsString('+00:00', $kernel->output());
    }

    public function testCronFinishRunsAfterCallbacksWithTheExitCodeAndReleasesTheMutex() {
        $schedule = CCron::schedule();
        $seen = [];
        $event = $schedule->exec('echo uji-cron-finish-' . uniqid())->runInBackground()->after(function () use (&$seen) {
            $seen[] = 'after';
        })->onSuccess(function () use (&$seen) {
            $seen[] = 'success';
        })->onFailure(function () use (&$seen) {
            $seen[] = 'failure';
        });
        $mutex = new UjiConsole_RecordingMutex();
        $event->preventOverlapsUsing($mutex);
        $event->withoutOverlapping();
        $mutex->existing = true;

        $this->assertSame(0, (new CConsole_Kernel())->call('cron:finish', ['id' => $event->mutexName(), 'code' => 3]));
        $this->assertSame(['after', 'failure'], $seen, 'kode 3 = gagal, onFailure jalan, onSuccess tidak');
        $this->assertSame(3, $event->exitCode);
        $this->assertSame(1, $mutex->forgotten, 'mutex dilepas setelah proses latar selesai');

        $kernel = new CConsole_Kernel();
        $kernel->call('cron:finish', ['id' => 'tidak-ada']);
        $this->assertSame('', trim($kernel->output()), 'id yang tidak dikenal diabaikan tanpa error');
    }

    public function testHarnessExpectsQuestionConfirmationAndChoice() {
        $this->cf('uji:interaktif')
            ->expectsQuestion('Siapa nama Anda?', 'Hery')
            ->expectsConfirmation('Lanjutkan?', 'yes')
            ->expectsChoice('Pilih warna', 'biru', ['merah', 'hijau', 'biru'], true)
            ->expectsOutput('Halo Hery, lanjut, biru')
            ->assertExitCode(0);
    }

    public function testHarnessFluentQuestionApiAndFailureExitCode() {
        $this->cf('uji:interaktif')
            ->expectsQuestion('Siapa nama Anda?', 'Budi')
            ->expectsConfirmation('Lanjutkan?', 'no')
            ->expectsChoice('Pilih warna', 'merah', ['merah', 'hijau', 'biru'])
            ->expectsOutput('Halo Budi, berhenti, merah')
            ->assertExitCode(2);
        $this->cf('uji:interaktif')
            ->expectsQuestion('Siapa nama Anda?', 'Budi')
            ->expectsConfirmation('Lanjutkan?', 'no')
            ->expectsChoice('Pilih warna', 'merah', ['merah', 'hijau', 'biru'])
            ->assertFailed();
    }

    public function testHarnessExpectsTable() {
        $this->cf('uji:interaktif', ['--tabel' => true])
            ->expectsTable(['Kode', 'Nama'], [['A', 'Apel'], ['B', 'Beras']])
            ->assertSuccessful();
    }

    public function testHarnessUnaskedQuestionFails() {
        $pending = $this->cf('uji:interaktif', ['--tabel' => true])->expectsQuestion('Siapa nama Anda?', 'x');
        try {
            $pending->run();
            $this->fail('pertanyaan yang tidak ditanyakan harus gagal');
        } catch (PHPUnit\Framework\Exception\AssertionFailedError $e) {
            $this->assertStringContainsString('was not asked', $e->getMessage());
        }
        $this->expectedQuestions = [];
    }

    public function testGeneratorWritesTheClassFromTheStub() {
        $kernel = $this->kernelWith(new UjiConsole_MakeWidgetCommand());
        $this->assertSame(0, $kernel->call('uji:make-widget', ['name' => 'Kartu/Saldo']));
        $this->assertStringContainsString('Widget created successfully', $kernel->output());
        $file = $this->tmp . 'libraries/Widget/Kartu/Saldo.php';
        $this->assertFileExists($file);
        $content = file_get_contents($file);
        $this->assertStringContainsString('namespace Uji\Widget\Kartu;', $content);
        $this->assertStringContainsString('class Saldo extends UjiBase', $content, 'DummyRootNamespace diganti root namespace');
        $this->assertStringContainsString("use Alpha;\nuse Zeta;", $content, 'import diurutkan');
    }

    public function testGeneratorRefusesToOverwriteUnlessForced() {
        $kernel = $this->kernelWith(new UjiConsole_MakeWidgetCommand());
        $kernel->call('uji:make-widget', ['name' => 'Ulang']);
        $file = $this->tmp . 'libraries/Widget/Ulang.php';
        file_put_contents($file, 'diubah tangan');
        $kernel->call('uji:make-widget', ['name' => 'Ulang']);
        $this->assertStringContainsString('already exists', $kernel->output());
        $this->assertSame('diubah tangan', file_get_contents($file));
        $kernel->call('uji:make-widget', ['name' => 'Ulang', '--force' => true]);
        $this->assertStringContainsString('class Ulang', file_get_contents($file));
    }

    public function testGeneratorRejectsReservedNames() {
        $kernel = $this->kernelWith(new UjiConsole_MakeWidgetCommand());
        $kernel->call('uji:make-widget', ['name' => 'Class']);
        $this->assertStringContainsString('reserved by PHP', $kernel->output());
        $this->assertFileDoesNotExist($this->tmp . 'libraries/Widget/Class.php');
    }

    public function testGeneratorQualifiesNamesAgainstTheRootNamespace() {
        $command = new UjiConsole_MakeWidgetCommand();
        $qualify = new ReflectionMethod($command, 'qualifyClass');
        $qualify->setAccessible(true);
        $this->assertSame('Uji\Widget\Kartu', $qualify->invoke($command, 'Kartu'));
        $this->assertSame('Uji\Widget\Kartu', $qualify->invoke($command, '/Kartu'));
        $this->assertSame('Uji\Lain\Kartu', $qualify->invoke($command, 'Uji\Lain\Kartu'), 'nama yang sudah berawalan root tidak diubah');
        $ns = new ReflectionMethod($command, 'getNamespace');
        $ns->setAccessible(true);
        $this->assertSame('Uji\Widget', $ns->invoke($command, 'Uji\Widget\Kartu'));
        $this->assertSame('', $ns->invoke($command, 'Kartu'));
    }

    public function testMakeTestCommandTargetsTheAppTestsFolder() {
        $command = new CConsole_Command_Make_MakeTestCommand();
        $getPath = new ReflectionMethod($command, 'getPath');
        $getPath->setAccessible(true);
        $expected = CF::appDir() . DS . 'default' . DS . 'tests/Feature/LoginTest.php';
        $this->assertSame($expected, $getPath->invoke($command, 'Tests\Feature\LoginTest'));
        $this->assertNotNull(CF::findFile('stubs', 'tests/test', false, 'stub'), 'stub make:test tersedia');
    }

    public function testBaseGeneratorPathAndNamespaceDefaults() {
        $generic = new class() extends CConsole_GeneratorCommand {
            protected $name = 'uji:generic';

            protected function getStub() {
                return '';
            }
        };
        $getPath = new ReflectionMethod($generic, 'getPath');
        $getPath->setAccessible(true);
        $this->assertSame(CF::appDir() . DS . 'default' . DS . 'libraries' . DS . 'UjiModel_Foo.php', $getPath->invoke($generic, 'UjiModel_Foo'), 'default: libraries app');
        $rootNamespace = new ReflectionMethod($generic, 'rootNamespace');
        $rootNamespace->setAccessible(true);
        $this->assertSame('', $rootNamespace->invoke($generic), 'kelas CF tanpa namespace');
        $qualify = new ReflectionMethod($generic, 'qualifyClass');
        $qualify->setAccessible(true);
        $this->assertSame('UjiModel_Foo', $qualify->invoke($generic, 'UjiModel_Foo'));
    }
}

class UjiConsole_RecordingMutex implements CCron_Contract_EventMutexInterface {
    /** @var int */
    public $forgotten = 0;

    /** @var bool */
    public $existing = false;

    public function create(CCron_Event $event) {
        return true;
    }

    public function exists(CCron_Event $event) {
        return $this->existing;
    }

    public function forget(CCron_Event $event) {
        $this->forgotten++;
    }
}
