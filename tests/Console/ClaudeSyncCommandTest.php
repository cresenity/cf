<?php
use PHPUnit\Framework\TestCase;

/**
 * claude:sync — penemuan app di empat root dan target berkas per app, tanpa menyentuh devcloud.
 */
class ClaudeSyncCommandTest extends TestCase {
    /** @var string */
    protected $tmpApp;

    protected function tearDown(): void {
        if ($this->tmpApp && is_dir($this->tmpApp)) {
            CFile::deleteDirectory($this->tmpApp);
        }
    }

    /**
     * @param string $method
     * @param array  $args
     *
     * @return mixed
     */
    protected function invoke($method, ...$args) {
        $command = new CConsole_Command_Claude_SyncCommand();
        $reflection = new ReflectionMethod($command, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($command, ...$args);
    }

    public function testAppRootsCoverAllFourFolders() {
        $this->assertSame(['application', 'project', 'frontend', 'mobile'], CConsole_Command_Claude_SyncCommand::APP_ROOTS);
        $this->assertSame('CLAUDE_MD', CConsole_Command_Claude_SyncCommand::DOC_CLAUDE_MD);
        $this->assertSame('TODO_MD', CConsole_Command_Claude_SyncCommand::DOC_TODO_MD);
    }

    public function testBaseDirForCfIsTheDocroot() {
        $this->assertSame(c::fixPath(DOCROOT), $this->invoke('baseDirFor', 'cf'));
    }

    public function testBaseDirForFindsAnAppUnderAnyRoot() {
        $this->tmpApp = c::fixPath(DOCROOT . 'project' . DS . 'uji_sync_' . uniqid());
        mkdir($this->tmpApp, 0777, true);
        $this->assertSame($this->tmpApp, $this->invoke('baseDirFor', basename($this->tmpApp)));
        $this->assertSame(c::fixPath(DOCROOT . 'application' . DS . 'uji_tidak_ada'), $this->invoke('baseDirFor', 'uji_tidak_ada'), 'app baru yang belum ada lokal → application/<code>/');
    }

    public function testTargetsForReportsPathsAndHashes() {
        $this->tmpApp = c::fixPath(DOCROOT . 'frontend' . DS . 'uji_sync_' . uniqid());
        mkdir($this->tmpApp . 'docs', 0777, true);
        file_put_contents($this->tmpApp . 'CLAUDE.md', "# uji\n");
        $targets = $this->invoke('targetsFor', basename($this->tmpApp));
        $this->assertSame($this->tmpApp . 'CLAUDE.md', $targets['CLAUDE_MD']['path']);
        $this->assertSame(hash('sha256', "# uji\n"), $targets['CLAUDE_MD']['hash']);
        $this->assertSame($this->tmpApp . 'docs' . DS . 'TODO.md', $targets['TODO_MD']['path']);
        $this->assertNull($targets['TODO_MD']['hash'], 'berkas yang belum ada → hash null');
    }

    public function testSignatureExposesAppAndDryRun() {
        $definition = (new CConsole_Command_Claude_SyncCommand())->getDefinition();
        $this->assertTrue($definition->hasOption('app'));
        $this->assertTrue($definition->hasOption('dry-run'));
        $this->assertFalse($definition->getOption('dry-run')->acceptValue());
        $this->assertTrue($definition->getOption('app')->acceptValue());
    }
}
