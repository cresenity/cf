<?php
use PHPUnit\Framework\TestCase;

/**
 * Konteks git pada laporan exception: menghitung proses git yang benar-benar dijalankan.
 */
class UjiException_GitContext extends CException_Context_ConsoleContext {
    /** @var string[] */
    public $commands = [];

    /** @var bool */
    public $gitBroken = false;

    protected function command($command, $baseDir) {
        $this->commands[] = $command;
        if ($this->gitBroken) {
            return '';
        }

        return parent::command($command, $baseDir);
    }

    public function cachePath() {
        return $this->getGitCachePath($this->getGitBaseDirectory());
    }
}

/**
 * CException_ContextAbstract::getGit(): hash/remote tanpa proses git, `git status` hanya di luar production,
 * hasil di-cache per menit, perintah git dibatasi timeout, dan bisa dimatikan lewat config.
 */
class GitContextTest extends TestCase {
    /** @var array */
    protected $originalConfig = [];

    protected function setUp(): void {
        if (!file_exists(DOCROOT . '.git')) {
            $this->markTestSkipped('checkout ini bukan repo git');
        }
        foreach (['exception.git.enabled', 'exception.git.dirty', 'exception.git.cacheSeconds', 'exception.git.timeout'] as $key) {
            $this->originalConfig[$key] = CConfig::repository()->get($key);
        }
        CConfig::repository()->set('exception.git.enabled', true);
        CConfig::repository()->set('exception.git.dirty', false);
        CConfig::repository()->set('exception.git.cacheSeconds', 0);
        CConfig::repository()->set('exception.git.timeout', 3);
        @unlink((new UjiException_GitContext())->cachePath());
    }

    protected function tearDown(): void {
        foreach ($this->originalConfig as $key => $value) {
            CConfig::repository()->set($key, $value);
        }
        @unlink((new UjiException_GitContext())->cachePath());
    }

    public function testHashAndRemoteComeFromTheGitFolderWithoutRunningGitStatus() {
        $context = new UjiException_GitContext();
        $git = $context->getGit();

        $this->assertSame(['hash', 'message', 'tag', 'remote', 'isDirty'], array_keys($git), 'bentuk payload tidak berubah');
        $this->assertSame(CManager_Asset_Helper::gitRevision(DOCROOT), $git['hash'], 'hash = isi .git/HEAD');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $git['hash']);
        $this->assertStringContainsString('CApp', $git['remote'], 'remote origin dibaca dari .git/config');
        $this->assertNull($git['isDirty'], 'exception.git.dirty=false → git status tidak dijalankan, isDirty null');
        foreach ($context->commands as $command) {
            $this->assertStringNotContainsString('git status', $command);
            $this->assertStringNotContainsString('remote.origin.url', $command);
            $this->assertStringNotContainsString('%H', $command, 'hash tidak lewat git log');
        }
        $this->assertLessThanOrEqual(2, count($context->commands), 'hanya message dan tag yang masih lewat biner git');
    }

    public function testDirtyCheckRunsOnlyWhenEnabled() {
        CConfig::repository()->set('exception.git.dirty', true);
        $context = new UjiException_GitContext();
        $git = $context->getGit();
        $this->assertIsBool($git['isDirty']);
        $this->assertContains('git status -s', $context->commands);
    }

    public function testDirtyDefaultFollowsEnvironment() {
        CConfig::repository()->set('exception.git.dirty', null);
        $context = new UjiException_GitContext();
        $git = $context->getGit();
        if (CF::isProduction()) {
            $this->assertNull($git['isDirty'], 'di production git status tidak dijalankan');
            $this->assertNotContains('git status -s', $context->commands);
        } else {
            $this->assertIsBool($git['isDirty']);
        }
    }

    public function testResultIsCachedSoASecondReportRunsNoGitAtAll() {
        CConfig::repository()->set('exception.git.cacheSeconds', 60);
        $first = new UjiException_GitContext();
        $git = $first->getGit();
        $this->assertFileExists($first->cachePath());

        $second = new UjiException_GitContext();
        $this->assertSame($git, $second->getGit());
        $this->assertSame([], $second->commands, 'laporan kedua dalam 60 detik tidak menjalankan proses git sama sekali');
    }

    public function testCacheIsInvalidatedWhenHeadChanges() {
        CConfig::repository()->set('exception.git.cacheSeconds', 60);
        $first = new UjiException_GitContext();
        $first->getGit();
        $cache = json_decode(file_get_contents($first->cachePath()), true);
        $cache['head'] = 'deploy-baru';
        file_put_contents($first->cachePath(), json_encode($cache));

        $second = new UjiException_GitContext();
        $second->getGit();
        $this->assertNotSame([], $second->commands, 'HEAD berubah (deploy) → cache diabaikan dan dihitung ulang');
    }

    public function testGitCommandsAreBoundedByTimeoutAndNeverThrow() {
        CConfig::repository()->set('exception.git.timeout', 1);
        $context = new UjiException_GitContext();
        $method = new ReflectionMethod($context, 'command');
        $method->setAccessible(true);
        $start = microtime(true);
        $this->assertSame('', $method->invoke($context, 'sleep 5', DOCROOT));
        $this->assertLessThan(3, microtime(true) - $start, 'perintah yang menggantung dihentikan oleh timeout');
    }

    public function testHashSurvivesWithoutAWorkingGitBinary() {
        $context = new UjiException_GitContext();
        $context->gitBroken = true;
        $git = $context->getGit();
        $this->assertSame(CManager_Asset_Helper::gitRevision(DOCROOT), $git['hash']);
        $this->assertStringContainsString('CApp', $git['remote']);
        $this->assertSame('', $git['message']);
    }

    public function testCanBeDisabledEntirely() {
        CConfig::repository()->set('exception.git.enabled', false);
        $context = new UjiException_GitContext();
        $this->assertSame([], $context->getGit());
        $this->assertSame([], $context->commands);
    }
}
