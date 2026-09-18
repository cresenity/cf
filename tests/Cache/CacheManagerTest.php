<?php
use PHPUnit\Framework\TestCase;

/**
 * Port CacheManagerTest hulu: resolusi store dari config cache.stores, custom creator lewat
 * extend(), purge/forgetDriver, dan delegasi __call ke store default.
 */
class CacheManagerTest extends TestCase {
    /**
     * @var array
     */
    protected $originalStores;

    /**
     * @var string
     */
    protected $originalDefault;

    protected function setUp(): void {
        $this->originalStores = CConfig::repository()->get('cache.stores');
        $this->originalDefault = CConfig::repository()->get('cache.default');
    }

    protected function tearDown(): void {
        CConfig::repository()->set('cache.stores', $this->originalStores);
        CConfig::repository()->set('cache.default', $this->originalDefault);
    }

    /**
     * @param string $name
     * @param array  $config
     *
     * @return void
     */
    protected function defineStore($name, array $config) {
        CConfig::repository()->set('cache.stores.' . $name, $config);
    }

    public function testStoreResolvesArrayDriverFromConfig() {
        $manager = new CCache_Manager();
        $this->defineStore('uji_array', ['driver' => 'array']);
        $store = $manager->store('uji_array');
        $this->assertInstanceOf(CCache_Repository::class, $store);
        $this->assertInstanceOf(CCache_Driver_ArrayDriver::class, $store->getDriver());
        $this->assertSame($store, $manager->store('uji_array'), 'store di-memoize per nama');
        $this->assertSame($store, $manager->driver('uji_array'), 'driver() = alias store()');
    }

    public function testStoreResolvesFileDriverFromConfig() {
        $manager = new CCache_Manager();
        $this->defineStore('uji_file', ['driver' => 'file', 'engine' => 'temp', 'options' => ['directory' => 'uji-manager']]);
        $store = $manager->store('uji_file');
        $this->assertInstanceOf(CCache_Driver_FileDriver::class, $store->getDriver());
        $store->put('foo', 'bar', 10);
        $this->assertSame('bar', $store->get('foo'));
        $store->getDriver()->flush();
    }

    public function testNullStoreNameResolvesNullDriverWithoutConfig() {
        $manager = new CCache_Manager();
        $this->assertInstanceOf(CCache_Driver_NullDriver::class, $manager->store('null')->getDriver());
    }

    public function testDefaultDriverComesFromConfig() {
        $manager = new CCache_Manager();
        $this->defineStore('uji_default', ['driver' => 'array']);
        CConfig::repository()->set('cache.default', 'uji_default');
        $this->assertSame('uji_default', $manager->getDefaultDriver());
        $this->assertInstanceOf(CCache_Driver_ArrayDriver::class, $manager->store()->getDriver());
        $manager->put('foo', 'bar', 10);
        $this->assertSame('bar', $manager->get('foo'), '__call mendelegasikan ke store default');
    }

    public function testThrowExceptionWhenUnknownStoreIsUsed() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cache store [alien] is not defined.');
        (new CCache_Manager())->store('alien');
    }

    public function testThrowExceptionWhenUnknownDriverIsUsed() {
        $this->defineStore('uji_bad', ['driver' => 'alien']);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Driver [alien] is not supported.');
        (new CCache_Manager())->store('uji_bad');
    }

    public function testCustomDriverClosureIsBoundToManagerAndReceivesConfig() {
        $manager = new CCache_Manager();
        $this->defineStore('uji_custom', ['driver' => 'uji', 'flag' => 42]);
        $seen = null;
        $manager->extend('uji', function (array $config) use (&$seen) {
            $seen = ['self' => $this, 'config' => $config];

            return $this->repository(new CCache_Driver_ArrayDriver());
        });
        $store = $manager->store('uji_custom');
        $this->assertInstanceOf(CCache_Repository::class, $store);
        $this->assertSame($manager, $seen['self'], 'closure diikat ke manager');
        $this->assertSame(42, $seen['config']['flag']);
    }

    public function testCustomDriverOverridesInternalDrivers() {
        $manager = new CCache_Manager();
        $this->defineStore('uji_override', ['driver' => 'array']);
        $manager->extend('array', function () {
            return $this->repository(new CCache_Driver_NullDriver([]));
        });
        $this->assertInstanceOf(CCache_Driver_NullDriver::class, $manager->store('uji_override')->getDriver());
    }

    public function testPurgeAndForgetDriverDropMemoizedStores() {
        $manager = new CCache_Manager();
        $this->defineStore('uji_purge', ['driver' => 'array']);
        $first = $manager->store('uji_purge');
        $manager->purge('uji_purge');
        $second = $manager->store('uji_purge');
        $this->assertNotSame($first, $second);
        $this->assertSame($manager, $manager->forgetDriver(['uji_purge']));
        $this->assertNotSame($second, $manager->store('uji_purge'));
    }

    public function testRepositoryWrapsAnyDriver() {
        $manager = new CCache_Manager();
        $driver = new CCache_Driver_ArrayDriver();
        $repository = $manager->repository($driver);
        $this->assertInstanceOf(CCache_Repository::class, $repository);
        $this->assertSame($driver, $repository->getDriver());
    }

    public function testFacadeStoreUsesTheSingletonManager() {
        $this->assertSame(CCache_Manager::instance(), CCache::manager());
        $this->assertInstanceOf(CCache_Driver_NullDriver::class, CCache::store('null')->getDriver());
    }
}
