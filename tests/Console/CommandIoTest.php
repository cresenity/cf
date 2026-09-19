<?php
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

class UjiIo_ParentCommand extends CConsole_Command {
    protected $signature = 'uji:induk {nama=dunia} {--diam}';

    protected $description = 'Memanggil command lain';

    public function handle() {
        if ($this->option('diam')) {
            $exit = $this->callSilent('version');
            $this->line('diam:' . $exit);

            return $exit;
        }
        $exit = $this->call('uji:anak', ['salam' => 'Halo ' . $this->argument('nama')]);
        $this->info('induk selesai');

        return $exit;
    }
}

class UjiIo_ChildCommand extends CConsole_Command {
    protected $signature = 'uji:anak {salam} {--ulang=1 : Berapa kali}';

    protected $description = 'Menyapa';

    public function handle() {
        for ($i = 0; $i < (int) $this->option('ulang'); $i++) {
            $this->line($this->argument('salam'));
        }
        $this->warn('peringatan');
        $this->error('galat');
        $this->comment('komentar');
        $this->question('tanya');
        $this->newLine(2);

        return 5;
    }
}

class UjiIo_ConfirmableCommand extends CConsole_Command {
    use CConsole_Trait_ConfirmableTrait;

    protected $signature = 'uji:konfirmasi {--force}';

    /** @var bool */
    public static $production = false;

    public function handle() {
        if (!$this->confirmToProceed('Bahaya!', function () {
            return static::$production;
        })) {
            return 1;
        }
        $this->info('dijalankan');

        return 0;
    }
}

/**
 * CConsole_Command: parsing signature, helper IO, call()/callSilent(), CConsole_Result, ConfirmableTrait.
 */
class CommandIoTest extends CTesting_TestCase {
    /** @var bool */
    protected static $registered = false;

    protected function setUp(): void {
        parent::setUp();
        static::registerConsoleCommands();
        if (!static::$registered) {
            static::$registered = true;
            CConsole_Application::starting(function ($cfCli) {
                $cfCli->add(new UjiIo_ParentCommand());
                $cfCli->add(new UjiIo_ChildCommand());
                $cfCli->add(new UjiIo_ConfirmableCommand());
            });
        }
    }

    /**
     * @param CConsole_Command $command
     * @param string           $input
     *
     * @return array [exitCode, output]
     */
    protected function runIsolated(CConsole_Command $command, $input = '', $verbosity = OutputInterface::VERBOSITY_NORMAL) {
        $output = new BufferedOutput($verbosity);
        $exit = $command->run(new StringInput($input), $output);

        return [$exit, $output->fetch()];
    }

    public function testSignatureDefinesArgumentsOptionsAndDescription() {
        $command = new UjiIo_ChildCommand();
        $definition = $command->getDefinition();
        $this->assertSame('uji:anak', $command->getName());
        $this->assertSame('Menyapa', $command->getDescription());
        $this->assertTrue($definition->getArgument('salam')->isRequired());
        $this->assertSame('1', $definition->getOption('ulang')->getDefault());
        $this->assertSame('Berapa kali', $definition->getOption('ulang')->getDescription());
        $parent = (new UjiIo_ParentCommand())->getDefinition();
        $this->assertFalse($parent->getArgument('nama')->isRequired());
        $this->assertSame('dunia', $parent->getArgument('nama')->getDefault());
        $this->assertFalse($parent->getOption('diam')->acceptValue());
    }

    public function testIoHelpersWriteToTheOutput() {
        list($exit, $output) = $this->runIsolated(new UjiIo_ChildCommand(), 'Halo --ulang=2');
        $this->assertSame(5, $exit);
        $this->assertSame(2, substr_count($output, 'Halo'));
        $this->assertStringContainsString('peringatan', $output);
        $this->assertStringContainsString('galat', $output);
        $this->assertStringContainsString('komentar', $output);
        $this->assertStringContainsString('tanya', $output);
        $this->assertStringEndsWith("\n\n\n", $output, 'newLine(2) setelah baris terakhir');
    }

    public function testArgumentsAndOptionsAccessors() {
        $command = new UjiIo_ChildCommand();
        $command->run(new ArrayInput(['salam' => 'hai', '--ulang' => '0']), new BufferedOutput());
        $this->assertSame('hai', $command->argument('salam'));
        $this->assertSame(['salam' => 'hai'], array_intersect_key($command->arguments(), ['salam' => 1]));
        $this->assertTrue($command->hasArgument('salam'));
        $this->assertFalse($command->hasArgument('tidak'));
        $this->assertSame('0', $command->option('ulang'));
        $this->assertTrue($command->hasOption('ulang'));
        $this->assertFalse($command->hasOption('tidak'));
        $this->assertArrayHasKey('ulang', $command->options());
        $this->assertInstanceOf(CConsole_OutputStyle::class, $command->getOutput());
    }

    public function testCallRunsAnotherCommandSharingTheOutput() {
        $kernel = new CConsole_Kernel();
        $exit = $kernel->call('uji:induk', ['nama' => 'Hery']);
        $this->assertSame(5, $exit, 'exit code anak diteruskan');
        $output = $kernel->output();
        $this->assertStringContainsString('Halo Hery', $output);
        $this->assertStringContainsString('induk selesai', $output);
    }

    public function testCallSilentSwallowsTheChildOutput() {
        $kernel = new CConsole_Kernel();
        $exit = $kernel->call('uji:induk', ['--diam' => true]);
        $this->assertSame(0, $exit);
        $output = $kernel->output();
        $this->assertStringNotContainsString(CF::version(), $output);
        $this->assertStringContainsString('diam:0', $output);
        $this->assertSame('', $kernel->output(), 'output() mengosongkan buffer (BufferedOutput::fetch)');
    }

    public function testVerbosityHidesVerboseLines() {
        $command = new class() extends CConsole_Command {
            protected $signature = 'uji:verbose';

            public function handle() {
                $this->line('selalu');
                $this->line('hanya-v', null, OutputInterface::VERBOSITY_VERBOSE);
                $this->info('hanya-vv', 'vv');
            }
        };
        list($exit, $output) = $this->runIsolated($command);
        $this->assertStringContainsString('selalu', $output);
        $this->assertStringNotContainsString('hanya-v', $output);
        list($exit, $output) = $this->runIsolated($command, '', OutputInterface::VERBOSITY_VERBOSE);
        $this->assertStringContainsString('hanya-v', $output);
        $this->assertStringNotContainsString('hanya-vv', $output);
        list($exit, $output) = $this->runIsolated($command, '', OutputInterface::VERBOSITY_VERY_VERBOSE);
        $this->assertStringContainsString('hanya-vv', $output);
    }

    public function testTableAndProgressBarRender() {
        $command = new class() extends CConsole_Command {
            protected $signature = 'uji:tabel';

            public function handle() {
                $this->table(['Kode', 'Nama'], [['A', 'Apel']]);
                $this->withProgressBar(3, function ($bar) {
                    $bar->advance(3);
                });
                $this->line('bar selesai');
            }
        };
        list($exit, $output) = $this->runIsolated($command);
        $this->assertStringContainsString('| Kode | Nama |', $output);
        $this->assertStringContainsString('| A    | Apel |', $output);
        $this->assertStringContainsString('3/3', $output);
        $this->assertStringContainsString('bar selesai', $output);
    }

    public function testWithProgressBarIteratesCollections() {
        $command = new class() extends CConsole_Command {
            protected $signature = 'uji:progress';

            public function handle() {
                $doubled = $this->withProgressBar(c::collect([1, 2, 3]), function ($item) {
                    return $item * 2;
                });
                $this->line(json_encode($doubled instanceof CCollection ? $doubled->all() : $doubled));
            }
        };
        list($exit, $output) = $this->runIsolated($command);
        $this->assertStringContainsString('[1,2,3]', $output, 'withProgressBar mengembalikan koleksi asal (callback hanya efek samping)');
    }

    public function testConfirmableTraitOutsideProductionRunsDirectly() {
        UjiIo_ConfirmableCommand::$production = false;
        $this->cf('uji:konfirmasi')->expectsOutput('dijalankan')->assertExitCode(0);
    }

    public function testConfirmableTraitInProductionAsksUnlessForced() {
        UjiIo_ConfirmableCommand::$production = true;
        try {
            $this->cf('uji:konfirmasi')
                ->expectsConfirmation('Do you really wish to run this command?', 'no')
                ->expectsOutput('Command Canceled!')
                ->assertExitCode(1);
            $this->cf('uji:konfirmasi')
                ->expectsConfirmation('Do you really wish to run this command?', 'yes')
                ->expectsOutput('dijalankan')
                ->assertExitCode(0);
            $this->cf('uji:konfirmasi', ['--force' => true])->expectsOutput('dijalankan')->assertExitCode(0);
        } finally {
            UjiIo_ConfirmableCommand::$production = false;
        }
    }

    public function testResultCollectsLabelsAndPrintsATable() {
        $result = new CConsole_Result();
        $result->add('Versi', '1.9');
        $result->addInfo('Env', 'dev');
        $result->addWarning('Cache', 'kosong');
        $result->addError('DB', 'gagal konek');
        $result->addHint('coba lagi');
        $result->addErrorAndHint('Redis', 'mati', 'jalankan redis-server');
        $rows = $result->toArray();
        $this->assertCount(7, $rows, 'addErrorAndHint = baris error + baris hint');
        $this->assertSame('Versi', $rows[0]['label']);
        $this->assertSame('1.9', $rows[0]['value']);

        $command = new class() extends CConsole_Command {
            protected $signature = 'uji:result';

            /** @var CConsole_Result */
            public $result;

            public function handle() {
                $this->result->printToConsole($this, ['Keterangan', 'Nilai']);
            }
        };
        $command->result = $result;
        list($exit, $output) = $this->runIsolated($command);
        $this->assertStringContainsString('Keterangan', $output);
        $this->assertStringContainsString('1.9', $output);
        $this->assertStringContainsString('gagal konek', $output);
        $this->assertStringContainsString('jalankan redis-server', $output);
        $result->reset();
        $this->assertSame([], $result->toArray());
    }

    public function testHiddenAndAliases() {
        $command = new class() extends CConsole_Command {
            protected $signature = 'uji:tersembunyi';

            protected $hidden = true;

            protected $aliases = ['uji:alias'];

            public function handle() {
            }
        };
        $this->assertTrue($command->isHidden());
        $this->assertSame(['uji:alias'], $command->getAliases());
    }
}
