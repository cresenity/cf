<?php
use PHPUnit\Framework\TestCase;

/**
 * CStorage sebagai manajer disk: resolusi nama, build ad hoc, set/forget/purge, extend, temp/cloud,
 * disk yang salah konfigurasi, dan opsi lokal (visibility/permissions/url).
 */
class StorageManagerTest extends TestCase {
    /** @var string */
    protected $tmp;

    protected function setUp(): void {
        $this->tmp = rtrim(sys_get_temp_dir(), '/') . '/uji-storage-' . uniqid() . '/';
        mkdir($this->tmp, 0777, true);
    }

    protected function tearDown(): void {
        CFile::deleteDirectory($this->tmp);
        CStorage::instance()->forgetDisk(['uji-disk', 'uji-custom', 'uji-ftp-broken']);
    }

    public function testInstanceIsASingletonAndDefaultDiskFollowsConfig() {
        $this->assertSame(CStorage::instance(), CStorage::instance());
        $this->assertSame(CF::config('storage.default'), CStorage::instance()->getDefaultDriver());
        $this->assertSame(CF::config('storage.temp'), CStorage::instance()->getTempDriver());
        $this->assertSame(CF::config('storage.cloud'), CStorage::instance()->getDefaultCloudDriver());
        $this->assertSame(CStorage::instance()->getTempDriver(), CStorage::instance()->getPublicTempDriver(), 'public-temp jatuh ke temp bila tidak diset');
        $this->assertInstanceOf(CStorage_Adapter::class, CStorage::instance()->disk());
        $this->assertSame(CStorage::instance()->disk(), CStorage::instance()->drive(), 'drive() alias disk()');
    }

    public function testDiskInstancesAreCachedPerName() {
        $manager = CStorage::instance();
        $this->assertSame($manager->disk('local'), $manager->disk('local'));
        $manager->purge('local');
        $this->assertNotSame($manager->disk('local'), $manager->build($this->tmp));
        $this->assertSame($manager->disk('local'), $manager->disk('local'));
    }

    public function testBuildAcceptsPathOrConfig() {
        $byPath = CStorage::instance()->build($this->tmp);
        $byPath->put('a.txt', 'isi');
        $this->assertFileExists($this->tmp . 'a.txt');
        $byConfig = CStorage::instance()->build(['driver' => 'local', 'root' => $this->tmp . 'sub']);
        $byConfig->put('b.txt', 'isi');
        $this->assertFileExists($this->tmp . 'sub/b.txt');
        $this->assertSame('isi', $byConfig->get('b.txt'));
        $this->assertFalse($byPath->exists('b.txt'), 'root berbeda');
        $this->assertTrue($byPath->exists('sub/b.txt'));
    }

    public function testSetRegistersAnAdapterUnderAName() {
        $disk = CStorage::instance()->build($this->tmp);
        CStorage::instance()->set('uji-disk', $disk);
        $this->assertSame($disk, CStorage::instance()->disk('uji-disk'));
        $this->assertSame($disk, CTemporary::disk('uji-disk'), 'CTemporary::disk(nama) = CStorage::temp(nama)');
        CStorage::instance()->forgetDisk('uji-disk');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not have a configured driver');
        CStorage::instance()->disk('uji-disk');
    }

    public function testUnsupportedDriverThrows() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not supported');
        CStorage::instance()->build(['driver' => 'tape-drive']);
    }

    public function testExtendRegistersACustomDriver() {
        $seen = [];
        CStorage::instance()->extend('uji-memory', function (array $config) use (&$seen) {
            $seen[] = $config;

            return CStorage::instance()->build($config['root']);
        });
        $disk = CStorage::instance()->build(['driver' => 'uji-memory', 'root' => $this->tmp . 'mem']);
        $disk->put('x.txt', '1');
        $this->assertFileExists($this->tmp . 'mem/x.txt');
        $this->assertSame('uji-memory', $seen[0]['driver']);
    }

    public function testLocalDriverHonoursVisibilityUrlAndPermissions() {
        $disk = CStorage::instance()->build([
            'driver' => 'local',
            'root' => $this->tmp . 'pub',
            'url' => 'https://cdn.uji.test/pub',
            'visibility' => 'public',
            'permissions' => ['file' => ['public' => 0644, 'private' => 0600], 'dir' => ['public' => 0755, 'private' => 0700]],
        ]);
        $disk->put('foto.jpg', 'x');
        $this->assertSame('https://cdn.uji.test/pub/foto.jpg', $disk->url('foto.jpg'));
        $this->assertSame('public', $disk->getVisibility('foto.jpg'));
        $this->assertSame('0644', substr(sprintf('%o', fileperms($this->tmp . 'pub/foto.jpg')), -4));
        $disk->setVisibility('foto.jpg', 'private');
        clearstatcache(true, $this->tmp . 'pub/foto.jpg');
        $this->assertSame('0600', substr(sprintf('%o', fileperms($this->tmp . 'pub/foto.jpg')), -4));
        $this->assertSame('private', $disk->getVisibility('foto.jpg'));
    }

    public function testTempDiskIsTheConfiguredTempDriver() {
        $temp = CStorage::instance()->temp();
        $this->assertInstanceOf(CStorage_Adapter::class, $temp);
        $this->assertSame($temp, CTemporary::disk());
        $this->assertSame(CStorage::instance()->publicTemp(), CTemporary::publicDisk());
        $this->assertSame(CTemporary::defaultDiskName(), CStorage::instance()->getTempDriver());
    }

    public function testFacadeForwardsToTheDefaultDisk() {
        $name = 'uji-facade-' . uniqid() . '.txt';
        CStorage::instance()->disk()->put($name, 'lewat instance');
        try {
            $this->assertSame('lewat instance', CStorage::instance()->get($name), '__call meneruskan ke disk default');
            $this->assertTrue(CStorage::instance()->exists($name));
        } finally {
            CStorage::instance()->disk()->delete($name);
        }
    }

    public function testCloudDiskUsesTheCloudConfigName() {
        $config = CF::config('storage.disks.' . CStorage::instance()->getDefaultCloudDriver());
        $this->assertSame('s3', carr::get($config, 'driver'), 'cloud default = s3');
        if (empty($config['key']) || empty($config['secret'])) {
            $this->markTestSkipped('kredensial S3 tidak tersedia di lingkungan ini');
        }
        $this->assertInstanceOf(CStorage_Adapter_AwsS3V3Adapter::class, CStorage::instance()->cloud());
    }
}
