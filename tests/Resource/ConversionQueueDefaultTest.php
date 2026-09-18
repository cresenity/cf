<?php

use PHPUnit\Framework\TestCase;

/**
 * `resource.queue_conversions_by_default` menentukan nasib konversi yang tidak memanggil queued()/nonQueued();
 * tanpa key-nya konversi tetap lewat queue seperti sebelumnya.
 */
class ConversionQueueDefaultTest extends TestCase {
    /**
     * @var mixed
     */
    protected $originalValue;

    protected function setUp(): void {
        $this->originalValue = CConfig::repository()->get('resource.queue_conversions_by_default');
    }

    protected function tearDown(): void {
        CConfig::repository()->set('resource.queue_conversions_by_default', $this->originalValue);
    }

    public function testConversionsAreQueuedWhenTheKeyIsMissing() {
        CConfig::repository()->set('resource.queue_conversions_by_default', null);

        $this->assertTrue(CResources_Conversion::create('thumb')->shouldBeQueued());
    }

    public function testShippedConfigKeepsConversionsQueued() {
        $this->assertTrue((bool) CF::config('resource.queue_conversions_by_default'));
        $this->assertTrue(CResources_Conversion::create('thumb')->shouldBeQueued());
        $this->assertFalse(CResources_Conversion::create('thumb')->nonQueued()->shouldBeQueued());
    }

    public function testFalseRunsConversionsSynchronouslyUnlessQueuedExplicitly() {
        CConfig::repository()->set('resource.queue_conversions_by_default', false);

        $this->assertFalse(CResources_Conversion::create('thumb')->shouldBeQueued());
        $this->assertTrue(CResources_Conversion::create('thumb')->queued()->shouldBeQueued());

        $collection = new CResources_ConversionCollection([
            CResources_Conversion::create('a'),
            CResources_Conversion::create('b')->queued(),
        ]);
        $this->assertSame(['a'], $collection->getNonQueuedConversions()->map->getName()->values()->all());
        $this->assertSame(['b'], $collection->getQueuedConversions()->map->getName()->values()->all());
    }
}
