<?php
use PHPUnit\Framework\TestCase;

/**
 * CRunner_Command (pembungkus proses untuk wkhtmltopdf/ffmpeg/tesseract): penyusunan perintah +
 * argumen ter-escape, tiga jalur eksekusi (Symfony Process, exec, proc_open), dan yang
 * terpenting - perintah berjalan tepat satu kali.
 */
class RunnerCommandTest extends TestCase {
    /** @var string[] */
    protected $files = [];

    protected function tearDown(): void {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    /**
     * @return string
     */
    protected function tempFile() {
        $file = tempnam(sys_get_temp_dir(), 'uji-runner');
        $this->files[] = $file;

        return $file;
    }

    public function testConstructorAcceptsCommandStringOrOptions() {
        $this->assertSame('ls', (new CRunner_Command('ls'))->getCommand());
        $command = new CRunner_Command(['command' => 'ls', 'useSymfony' => false, 'timeout' => 5]);
        $this->assertSame('ls', $command->getCommand());
        $this->assertFalse($command->useSymfony);
        $this->assertSame(5, $command->timeout);
    }

    public function testUnknownOptionThrows() {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Unknown configuration option 'bukanOpsi'");
        new CRunner_Command(['bukanOpsi' => 1]);
    }

    public function testArgsAreEscapedAndJoined() {
        $command = new CRunner_Command('printf');
        $command->addArg('%s')->addArg('--out', 'a b')->addArg('--in=', 'x')->addArg('--raw', 'y', false)->addArg('-i', ['p q', 'r']);
        $this->assertSame("'%s' '--out' 'a b' '--in'='x' --raw y '-i' 'p q' 'r'", $command->getArgs());
        $this->assertSame('printf ' . $command->getArgs(), $command->getExecCommand());
        $this->assertSame($command->getExecCommand(), (string) $command);
    }

    public function testEscapeArgsCanBeTurnedOffGlobally() {
        $command = new CRunner_Command(['command' => 'echo', 'escapeArgs' => false]);
        $command->addArg('--flag', 'a b');
        $this->assertSame('--flag a b', $command->getArgs());
        $command->setArgs('--reset');
        $this->assertSame('--reset', $command->getArgs(), 'setArgs mengganti seluruh daftar');
    }

    public function testExecCommandIsFalseWithoutACommand() {
        $command = new CRunner_Command();
        $this->assertFalse($command->getExecCommand());
        $this->assertFalse($command->execute());
        $this->assertSame('Could not locate any executable command', $command->getError());
    }

    public function testExecuteRunsTheCommandExactlyOnceWithSymfonyProcess() {
        $file = $this->tempFile();
        $command = new CRunner_Command('echo x >> ' . escapeshellarg($file));
        $this->assertTrue($command->useSymfony, 'jalur default');
        $this->assertTrue($command->execute());
        $this->assertTrue($command->getExecuted());
        $this->assertSame(0, $command->getExitCode());
        $this->assertSame(1, count(array_filter(explode("\n", file_get_contents($file)))), 'perintah dulu berjalan dua kali (Symfony lalu proc_open)');
    }

    public function testExecuteRunsExactlyOnceWithProcOpen() {
        $file = $this->tempFile();
        $command = new CRunner_Command(['command' => 'echo x >> ' . escapeshellarg($file), 'useSymfony' => false]);
        $this->assertTrue($command->execute());
        $this->assertSame(1, count(array_filter(explode("\n", file_get_contents($file)))));
    }

    public function testExecuteRunsExactlyOnceWithExec() {
        $file = $this->tempFile();
        $command = new CRunner_Command(['command' => 'echo x >> ' . escapeshellarg($file), 'useSymfony' => false, 'useExec' => true]);
        $this->assertTrue($command->execute());
        $this->assertSame(1, count(array_filter(explode("\n", file_get_contents($file)))));
    }

    public function testOutputIsCapturedOnEveryPath() {
        foreach ([['useSymfony' => true], ['useSymfony' => false], ['useSymfony' => false, 'useExec' => true]] as $options) {
            $command = new CRunner_Command(array_merge(['command' => 'printf'], $options));
            $command->addArg('%s', "hai dunia\n");
            $this->assertTrue($command->execute(), json_encode($options));
            $this->assertSame('hai dunia', $command->getOutput(), json_encode($options));
            $this->assertStringStartsWith('hai dunia', $command->getOutput(false), 'tanpa trim (jalur exec membuang newline akhir)');
            $this->assertSame(0, $command->getExitCode());
        }
    }

    public function testFailureReportsExitCodeAndError() {
        foreach ([['useSymfony' => true], ['useSymfony' => false], ['useSymfony' => false, 'useExec' => true]] as $options) {
            $command = new CRunner_Command(array_merge(['command' => 'ls'], $options));
            $command->addArg('/tidak/ada/direktori/uji');
            $this->assertFalse($command->execute(), json_encode($options));
            $this->assertNotSame(0, $command->getExitCode());
            $this->assertNotSame('', $command->getError(), 'pesan error terisi');
            $this->assertFalse($command->getExecuted());
        }
    }

    public function testStdInStringIsPipedOnProcOpenPath() {
        $command = new CRunner_Command(['command' => 'cat', 'useSymfony' => false]);
        $command->setStdIn("dari stdin\n");
        $this->assertTrue($command->execute());
        $this->assertSame('dari stdin', $command->getOutput());
    }

    public function testStdInStreamIsPipedOnProcOpenPath() {
        $file = $this->tempFile();
        file_put_contents($file, "isi berkas\n");
        $handle = fopen($file, 'r');
        $command = new CRunner_Command(['command' => 'cat', 'useSymfony' => false]);
        $command->setStdIn($handle);
        $this->assertTrue($command->execute());
        $this->assertSame('isi berkas', $command->getOutput());
        fclose($handle);
    }

    public function testProcCwdAndEnvAreHonouredOnProcOpenPath() {
        $command = new CRunner_Command(['command' => 'pwd', 'useSymfony' => false, 'procCwd' => sys_get_temp_dir()]);
        $this->assertTrue($command->execute());
        $this->assertSame(realpath(sys_get_temp_dir()), realpath($command->getOutput()));

        $command = new CRunner_Command(['command' => 'sh -c \'echo "$UJI_VAR"\'', 'useSymfony' => false, 'procEnv' => ['UJI_VAR' => 'nilai', 'PATH' => getenv('PATH')]]);
        $this->assertTrue($command->execute());
        $this->assertSame('nilai', $command->getOutput());
    }

    public function testWkHtmlToPdfCommandUsesTheSameWrapper() {
        $this->assertInstanceOf(CRunner_Command::class, new CRunner_WkHtmlToPdf_Command());
    }
}
