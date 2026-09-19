<?php
use PHPUnit\Framework\TestCase;

/**
 * CDevSuite bagian murni: Helper (replace/retry/resolve/swap) dan ClaudeHookInstaller
 * yang menulis ke settings.json Claude di HOME sementara.
 */
class DevSuiteHelperAndClaudeHookTest extends TestCase {
    /** @var string */
    protected $home;

    /** @var mixed */
    protected $originalHome;

    protected function setUp(): void {
        $this->originalHome = carr::get($_SERVER, 'HOME');
        $this->home = rtrim(sys_get_temp_dir(), '/') . '/uji-home-' . uniqid();
        mkdir($this->home, 0777, true);
        $_SERVER['HOME'] = $this->home;
    }

    protected function tearDown(): void {
        if ($this->originalHome === null) {
            unset($_SERVER['HOME']);
        } else {
            $_SERVER['HOME'] = $this->originalHome;
        }
        CFile::deleteDirectory($this->home);
    }

    public function testStrArrayReplace() {
        $this->assertSame('halo dunia', CDevSuite_Helper::strArrayReplace(['{a}' => 'halo', '{b}' => 'dunia'], '{a} {b}'));
        $this->assertSame('tetap', CDevSuite_Helper::strArrayReplace([], 'tetap'));
    }

    public function testRetryRepeatsUntilSuccessOrExhausted() {
        $calls = 0;
        $result = CDevSuite_Helper::retry(3, function () use (&$calls) {
            $calls++;
            if ($calls < 3) {
                throw new RuntimeException('belum');
            }

            return 'sukses';
        });
        $this->assertSame('sukses', $result);
        $this->assertSame(3, $calls);

        $calls = 0;
        try {
            CDevSuite_Helper::retry(2, function () use (&$calls) {
                $calls++;

                throw new RuntimeException('selalu gagal');
            });
            $this->fail('harus melempar setelah percobaan habis');
        } catch (RuntimeException $e) {
            $this->assertSame('selalu gagal', $e->getMessage());
            $this->assertSame(3, $calls, '1 percobaan + 2 ulangan');
        }
    }

    public function testResolveAndSwapUseTheContainer() {
        $instance = new stdClass();
        $instance->tanda = 'uji';
        CDevSuite_Helper::swap('UjiDevSuite_Layanan', $instance);
        try {
            $this->assertSame($instance, CDevSuite_Helper::resolve('UjiDevSuite_Layanan'));
        } finally {
            c::container()->forgetInstance('UjiDevSuite_Layanan');
        }
    }

    public function testClaudeHookInstallerAddsUpdatesAndKeepsUnchanged() {
        $result = CDevSuite_ClaudeHookInstaller::install();
        $settingsPath = $this->home . '/.claude/settings.json';
        $this->assertSame('added', $result['action']);
        $this->assertSame($settingsPath, $result['settingsPath']);
        $this->assertStringEndsWith('system/data/claude/hooks/' . CDevSuite_ClaudeHookInstaller::MARKER, $result['hookPath']);
        $this->assertFileExists($result['hookPath'], 'skrip hook ikut di repo');
        $settings = json_decode(file_get_contents($settingsPath), true);
        $this->assertCount(1, $settings['hooks']['PreToolUse']);
        $this->assertSame('Edit|NotebookEdit', $settings['hooks']['PreToolUse'][0]['matcher'], 'Write sengaja tidak diblokir');
        $this->assertSame('php ' . $result['hookPath'], $settings['hooks']['PreToolUse'][0]['hooks'][0]['command']);

        $this->assertSame('unchanged', CDevSuite_ClaudeHookInstaller::install()['action']);
        $this->assertCount(1, json_decode(file_get_contents($settingsPath), true)['hooks']['PreToolUse'], 'tidak digandakan');

        $settings['hooks']['PreToolUse'][0]['matcher'] = 'Edit';
        $settings['permissions'] = ['allow' => ['Bash(ls:*)']];
        file_put_contents($settingsPath, json_encode($settings));
        $this->assertSame('updated', CDevSuite_ClaudeHookInstaller::install()['action']);
        $after = json_decode(file_get_contents($settingsPath), true);
        $this->assertSame('Edit|NotebookEdit', $after['hooks']['PreToolUse'][0]['matcher'], 'entri lama dengan marker yang sama ditimpa');
        $this->assertSame(['allow' => ['Bash(ls:*)']], $after['permissions'], 'pengaturan lain dipertahankan');
    }

    public function testClaudeHookInstallerPreservesOtherPreToolUseEntries() {
        $settingsPath = $this->home . '/.claude/settings.json';
        mkdir(dirname($settingsPath), 0777, true);
        file_put_contents($settingsPath, json_encode(['hooks' => ['PreToolUse' => [['matcher' => 'Bash', 'hooks' => [['type' => 'command', 'command' => 'echo lain']]]]]]));
        $this->assertSame('added', CDevSuite_ClaudeHookInstaller::install()['action']);
        $entries = json_decode(file_get_contents($settingsPath), true)['hooks']['PreToolUse'];
        $this->assertCount(2, $entries);
        $this->assertSame('Bash', $entries[0]['matcher']);
        $this->assertSame('Edit|NotebookEdit', $entries[1]['matcher']);
    }
}
