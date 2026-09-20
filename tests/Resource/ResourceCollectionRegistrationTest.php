<?php

use PHPUnit\Framework\TestCase;

class UjiRegistrasiKoleksi extends CModel implements CModel_HasResourceInterface {
    use CModel_HasResource_HasResourceTrait;

    protected $table = 'uji_registrasi_koleksi';

    public function registerResourceCollections() {
        $this->addResourceCollection('avatar')->singleFile();
        $this->addResourceCollection('banner');
    }
}

/**
 * getResourceCollection()/getRegisteredResourceCollections()/
 * registerAllResourceConversions() all call registerResourceCollections() on
 * every access - a model's override always (re)declares its full, current set
 * via addResourceCollection(), which only ever appended to
 * $resourceCollections with nothing clearing it first. A long-lived instance
 * (a queue worker's memoized "current user", a daemon looping over many
 * models across many jobs) grew that array by one duplicate entry per call
 * for the rest of the process's life - the eventual cause of a production
 * OOM in a devcloud worker daemon (Exception Collector #14276,
 * CModel/HasResource/HasResourceTrait.php:356). Fixed by resetting
 * $resourceCollections immediately before every re-registration.
 */
class ResourceCollectionRegistrationTest extends TestCase {
    public function testRepeatedAccessorCallsDoNotGrowTheCollectionsArray() {
        $model = new UjiRegistrasiKoleksi();

        for ($i = 0; $i < 50; $i++) {
            $model->getResourceCollection('avatar');
            $model->getRegisteredResourceCollections();
        }

        $this->assertCount(2, $model->resourceCollections, 'harus tetap 2 (avatar, banner) walau accessor dipanggil berkali-kali pada instance yang sama');
    }

    public function testGetResourceCollectionStillFindsTheRightOneByName() {
        $model = new UjiRegistrasiKoleksi();

        $collection = $model->getResourceCollection('banner');

        $this->assertNotNull($collection);
        $this->assertSame('banner', $collection->name);
    }

    public function testRegisterAllResourceConversionsDoesNotGrowTheCollectionsArrayEither() {
        $model = new UjiRegistrasiKoleksi();

        for ($i = 0; $i < 10; $i++) {
            $model->registerAllResourceConversions();
        }

        $this->assertCount(2, $model->resourceCollections);
    }
}
