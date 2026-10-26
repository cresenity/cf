<?php

use PHPUnit\Framework\TestCase;

class AssetBundleManifestTestStore {
    public $data = [];

    public function get($key) {
        return isset($this->data[$key]) ? $this->data[$key] : null;
    }

    public function set($key, $value, $ttl) {
        $this->data[$key] = $value;
    }
}

/**
 * CManager_Asset_BundleManifest: server yang belum punya bundel compiled menyusunnya dari manifest.
 */
class AssetBundleManifestTest extends TestCase {
    private $sourceDir;

    private $release;

    private $store;

    protected function setUp(): void {
        $id = uniqid('t');
        $this->sourceDir = DOCROOT . 'temp/test-bundle-' . $id . '/';
        mkdir($this->sourceDir, 0777, true);
        $this->release = 'test-' . $id;
        $this->store = new AssetBundleManifestTestStore();
        CManager_Asset_BundleManifest::$store = $this->store;
    }

    protected function tearDown(): void {
        CManager_Asset_BundleManifest::$store = null;
        CFile::deleteDirectory($this->sourceDir);
        foreach (['js', 'css'] as $type) {
            CFile::deleteDirectory(DOCROOT . 'compiled/asset/' . $type . '/' . $this->release);
        }
    }

    private function source($name, $content) {
        file_put_contents($this->sourceDir . $name, $content);

        return $this->sourceDir . $name;
    }

    private function bundle(array $files, $type = 'js') {
        $relative = CManager_Asset_Compiler::bundlePath($type, $files, $this->release);
        $compiler = new CManager_Asset_Compiler($files, ['type' => $type, 'outFile' => DOCROOT . $relative]);
        $compiler->compile();

        return $relative;
    }

    private function request($path) {
        return CHTTP_Request::create('/' . $path);
    }

    public function testAMissingBundleIsRebuiltFromItsManifest() {
        $files = [$this->source('a.js', 'var a = 1'), $this->source('b.js', 'var b = 2')];
        $path = $this->bundle($files);
        $original = file_get_contents(DOCROOT . $path);
        $this->assertArrayHasKey('cf:asset-bundle:' . $path, $this->store->data);

        //server lain: bundel belum ada
        unlink(DOCROOT . $path);
        $response = CManager_Asset_BundleManifest::respond($this->request($path));

        $this->assertInstanceOf(CHTTP_Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/javascript', $response->headers->get('Content-Type'));
        $this->assertSame($original, $response->getContent());
        $this->assertFileExists(DOCROOT . $path);
    }

    public function testCssBundleIsServedAsCss() {
        $path = $this->bundle([$this->source('a.css', 'body{color:red}')], 'css');
        unlink(DOCROOT . $path);

        $response = CManager_Asset_BundleManifest::respond($this->request($path));

        $this->assertSame('text/css', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('color', $response->getContent());
    }

    public function testWithoutAManifestTheRequestFallsThrough() {
        $path = 'compiled/asset/js/' . $this->release . '/' . md5('x') . '.js';

        $this->assertNull(CManager_Asset_BundleManifest::respond($this->request($path)));
    }

    public function testWithoutConfigNothingIsRecordedOrServed() {
        CManager_Asset_BundleManifest::$store = null;
        $files = [$this->source('a.js', 'var a = 1')];
        $path = $this->bundle($files);
        unlink(DOCROOT . $path);

        $this->assertFalse(CManager_Asset_BundleManifest::remember(DOCROOT . $path, $files));
        $this->assertNull(CManager_Asset_BundleManifest::respond($this->request($path)));
    }

    public function testAManifestPointingOutsideTheDocrootOrToAnotherTypeIsRejected() {
        $path = 'compiled/asset/js/' . $this->release . '/' . md5('evil') . '.js';
        $this->store->data['cf:asset-bundle:' . $path] = json_encode(['../../etc/passwd']);
        $this->assertNull(CManager_Asset_BundleManifest::respond($this->request($path)));

        $this->source('secret.php', '<?php echo 1;');
        $this->store->data['cf:asset-bundle:' . $path] = json_encode([substr($this->sourceDir, strlen(DOCROOT)) . 'secret.php']);
        $this->assertNull(CManager_Asset_BundleManifest::respond($this->request($path)));
        $this->assertFileDoesNotExist(DOCROOT . $path);
    }

    public function testOtherPathsAreIgnored() {
        $this->assertNull(CManager_Asset_BundleManifest::respond($this->request('login')));
        $this->assertNull(CManager_Asset_BundleManifest::respond($this->request('compiled/asset/js/r/' . md5('x') . '.css')));
        $this->assertNull(CManager_Asset_BundleManifest::respond($this->request('compiled/asset/js/../x/' . md5('x') . '.js')));
    }
}
