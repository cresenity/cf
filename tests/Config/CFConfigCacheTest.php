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

    /**
     * @var array
     */
    protected $temporaryDirectories = [];

    protected function tearDown(): void {
        foreach ($this->temporaryPaths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        foreach ($this->temporaryDirectories as $directory) {
            if (is_dir($directory)) {
                @rmdir($directory);
            }
        }
        $this->temporaryPaths = [];
        $this->temporaryDirectories = [];

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

    /**
     * @param int $count
     *
     * @return string
     */
    protected function directoryHolding($count) {
        $directory = sys_get_temp_dir() . DS . 'cfconfigcache-' . uniqid();
        mkdir($directory, 0775, true);
        for ($i = 0; $i < $count; $i++) {
            $file = $directory . DS . 'config-' . str_pad((string) $i, 12, '0', STR_PAD_LEFT) . '.php';
            file_put_contents($file, '<?php return [];');
            $this->temporaryPaths[] = $file;
        }
        $this->temporaryDirectories[] = $directory;

        return $directory;
    }

    public function testANewVariantIsAllowedWhileBelowTheLimit() {
        $directory = $this->directoryHolding(CFConfig::CACHE_VARIANT_LIMIT - 1);

        $this->assertTrue($this->callProtected('mayAddVariant', [$directory . DS . 'config-baru.php']));
    }

    public function testANewVariantIsRefusedOnceTheLimitIsReached() {
        $directory = $this->directoryHolding(CFConfig::CACHE_VARIANT_LIMIT);

        $this->assertFalse($this->callProtected('mayAddVariant', [$directory . DS . 'config-baru.php']));
    }

    public function testRebuildingAnExistingVariantStaysAllowedAtTheLimit() {
        $directory = $this->directoryHolding(CFConfig::CACHE_VARIANT_LIMIT);
        $existing = $directory . DS . 'config-' . str_pad('0', 12, '0', STR_PAD_LEFT) . '.php';

        $this->assertTrue($this->callProtected('mayAddVariant', [$existing]));
    }

    public function testPartitionCachesAKeyThatLoadsIdenticallyTwice() {
        $first = ['app' => ['name' => 'Cresenity'], 'database' => ['host' => 'localhost']];

        list($items, $dynamic) = $this->callProtected('partition', [$first, $first]);

        $this->assertSame($first, $items);
        $this->assertSame([], $dynamic);
    }

    public function testPartitionRefusesToCacheAKeyThatDiffersBetweenLoads() {
        // Bentuk client_modules.php yang memakai uniqid() sebagai pembatal cache.
        $first = ['app' => ['name' => 'Cresenity'], 'client_modules' => ['js' => ['a.js?v=satu']]];
        $second = ['app' => ['name' => 'Cresenity'], 'client_modules' => ['js' => ['a.js?v=dua']]];

        list($items, $dynamic) = $this->callProtected('partition', [$first, $second]);

        $this->assertSame(['client_modules'], $dynamic);
        $this->assertArrayNotHasKey('client_modules', $items);
        $this->assertArrayHasKey('app', $items);
    }

    public function testPartitionRefusesToCacheAKeyHoldingAClosure() {
        $value = ['handler' => function () {
            return 1;
        }];
        $first = ['app' => ['name' => 'Cresenity'], 'filemanager' => $value];

        list($items, $dynamic) = $this->callProtected('partition', [$first, $first]);

        $this->assertSame(['filemanager'], $dynamic);
        $this->assertArrayNotHasKey('filemanager', $items);
    }

    public function testPartitionRefusesToCacheAKeyMissingFromTheSecondLoad() {
        $first = ['app' => ['name' => 'Cresenity'], 'hilang' => ['a' => 1]];
        $second = ['app' => ['name' => 'Cresenity']];

        list($items, $dynamic) = $this->callProtected('partition', [$first, $second]);

        $this->assertSame(['hilang'], $dynamic);
        $this->assertArrayNotHasKey('hilang', $items);
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
