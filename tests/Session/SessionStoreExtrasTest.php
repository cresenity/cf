<?php

use PHPUnit\Framework\TestCase;

class SessionStoreExtrasTest extends TestCase {
    /**
     * @return CSession_Store
     */
    protected function makeStore() {
        $store = new CSession_Store('nama_sesi', new CSession_Handler_ArraySessionHandler(10));
        $store->start();

        return $store;
    }

    public function testIdIsAnAliasOfGetId() {
        $store = $this->makeStore();

        $this->assertSame($store->getId(), $store->id());
    }

    public function testExceptReturnsEverythingButTheGivenKeys() {
        $store = $this->makeStore();
        $store->put(['a' => 1, 'b' => 2, 'c' => 3]);

        $result = $store->except(['a', '_token']);

        $this->assertSame(['b' => 2, 'c' => 3], array_intersect_key($result, ['a' => 1, 'b' => 1, 'c' => 1]));
        $this->assertArrayNotHasKey('_token', $result);
    }

    public function testHasAnyIsTrueWhenAtLeastOneKeyHasANonNullValue() {
        $store = $this->makeStore();
        $store->put(['ada' => 'x', 'kosong' => null]);

        $this->assertTrue($store->hasAny(['tidak-ada', 'ada']));
        $this->assertTrue($store->hasAny('tidak-ada', 'ada'));
        $this->assertFalse($store->hasAny(['tidak-ada', 'kosong']));
    }

    public function testPreviousRouteRoundTrips() {
        $store = $this->makeStore();

        $this->assertNull($store->previousRoute());
        $store->setPreviousRoute('home.index');
        $this->assertSame('home.index', $store->previousRoute());
    }

    public function testManagerRouteBlockDefaultsAndDefaultDriverSetter() {
        $manager = CSession_Manager::instance();
        $original = $manager->getDefaultDriver();

        try {
            $this->assertSame(10, $manager->defaultRouteBlockLockSeconds());
            $this->assertSame(10, $manager->defaultRouteBlockWaitSeconds());

            $manager->setDefaultDriver('array');
            $this->assertSame('array', $manager->getDefaultDriver());
        } finally {
            $manager->setDefaultDriver($original);
        }
    }
}
