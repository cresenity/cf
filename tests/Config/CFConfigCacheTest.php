<?php
use PHPUnit\Framework\TestCase;

/**
 * Cache config CFConfig membatalkan dirinya sendiri lewat mtime, jadi deploy
 * cukup `git pull` tanpa perintah pembersih. Yang diuji di sini justru bagian
 * yang membuat itu aman: sidik jari mtime, penyaringan nilai yang tidak bisa
 * diserialkan, dan penulisan yang atomik.
 *
 * Pembangkitan cache-nya sendiri sengaja mati saat testing (cachePath()
 * mengembalikan null), supaya suite tidak saling mewarisi config - karena itu
 * method-method di bawah dipanggil langsung lewat refleksi, bukan lewat
 * CFConfig::bootstrap().
 */
class CFConfigCacheTest extends TestCase {
    /**
     * @var array
     */
    protected $temporaryPaths = [];

    protected function tearDown(): void {
        foreach ($this->temporaryPaths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $this->temporaryPaths = [];

        parent::tearDown();
    }

    /**
     * @param string $method
     *
     * @return mixed
     */
    protected function callProtected($method, array $arguments = []) {
        $reflection = new ReflectionMethod('CFConfig', $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(null, $arguments);
    }

    /**
     * @param string $contents
     *
     * @return string
     */
    protected function temporaryFile($contents = 'x') {
        $path = tempnam(sys_get_temp_dir(), 'cfconfigcache');
        file_put_contents($path, $contents);
        $this->temporaryPaths[] = $path;

        return $path;
    }

    public function testExportableAcceptsScalarsNullAndNestedArrays() {
        $value = [
            'string' => 'satu',
            'int' => 1,
            'float' => 1.5,
            'bool' => false,
            'null' => null,
            'nested' => ['a' => ['b' => ['c' => 'd']]],
            'list' => [1, 2, 3],
        ];

        $this->assertTrue($this->callProtected('isExportable', [$value]));
    }

    public function testExportableRejectsAClosureHiddenDeepInsideAnArray() {
        $value = ['a' => ['b' => ['handler' => function () {
            return 1;
        }]]];

        $this->assertFalse($this->callProtected('isExportable', [$value]));
    }

    public function testExportableRejectsAnObject() {
        $this->assertFalse($this->callProtected('isExportable', [new stdClass()]));
    }

    public function testFingerprintIsStableWhenNothingChanges() {
        $watch = [$this->temporaryFile(), $this->temporaryFile()];

        $this->assertSame(
            $this->callProtected('fingerprint', [$watch]),
            $this->callProtected('fingerprint', [$watch])
        );
    }

    public function testFingerprintChangesWhenAWatchedFileIsTouched() {
        $file = $this->temporaryFile();
        $watch = [$file];
        $before = $this->callProtected('fingerprint', [$watch]);

        // mtime berbutir detik - dimajukan, bukan menunggu.
        touch($file, time() + 10);
        clearstatcache(true, $file);

        $this->assertNotSame($before, $this->callProtected('fingerprint', [$watch]));
    }

    public function testFingerprintChangesWhenAWatchedPathAppears() {
        $file = $this->temporaryFile();
        $watch = [$file];
        unlink($file);
        clearstatcache(true, $file);
        $whileMissing = $this->callProtected('fingerprint', [$watch]);

        file_put_contents($file, 'ada lagi');
        clearstatcache(true, $file);

        $this->assertNotSame($whileMissing, $this->callProtected('fingerprint', [$watch]));
    }

    public function testFingerprintOfADirectoryChangesWhenAFileIsAddedToIt() {
        $directory = sys_get_temp_dir() . DS . 'cfconfigcache-' . uniqid() . DS;
        mkdir($directory, 0775, true);
        $watch = [$directory];
        $before = $this->callProtected('fingerprint', [$watch]);

        touch($directory, time() + 10);
        clearstatcache(true, $directory);
        $after = $this->callProtected('fingerprint', [$watch]);

        rmdir($directory);

        $this->assertNotSame($before, $after);
    }

    public function testPutCacheWritesPhpThatLoadsBackAsTheSameArray() {
        $path = $this->temporaryFile();
        $payload = ['version' => 1, 'items' => ['app' => ['name' => 'Cresenity', 'debug' => false]]];

        $this->callProtected('putCache', [$path, $payload]);

        $this->assertSame($payload, require $path);
    }

    public function testPutCacheLeavesNoTemporaryFileBehind() {
        $path = $this->temporaryFile();

        $this->callProtected('putCache', [$path, ['version' => 1]]);

        $this->assertSame([], glob($path . '.*.tmp'));
    }

    public function testPutCacheCreatesTheDirectoryWhenItIsMissing() {
        $directory = sys_get_temp_dir() . DS . 'cfconfigcache-' . uniqid();
        $path = $directory . DS . 'config.php';
        $this->temporaryPaths[] = $path;

        $this->callProtected('putCache', [$path, ['version' => 1]]);
        $written = is_file($path);

        if ($written) {
            unlink($path);
        }
        if (is_dir($directory)) {
            rmdir($directory);
        }

        $this->assertTrue($written);
    }

    public function testCacheIsDisabledWhileTesting() {
        $this->assertNull($this->callProtected('cachePath'));
    }

    public function testGetCachedConfigPathKeepsItsOriginalValueWithoutAVariant() {
        $default = CFConfig::getCachedConfigPath();
        $variant = CFConfig::getCachedConfigPath('abc123');

        if ($default === null) {
            $this->assertNull($variant, 'tanpa app code, keduanya harus null');

            return;
        }

        $this->assertStringEndsWith(DS . 'config.php', $default);
        $this->assertSame(substr($default, 0, -strlen('.php')) . '-abc123.php', $variant);
    }
}
