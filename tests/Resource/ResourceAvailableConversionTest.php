<?php

use PHPUnit\Framework\TestCase;

class UjiTersedia extends CModel implements CModel_HasResourceInterface {
    use CModel_HasResource_HasResourceTrait;

    protected $table = 'uji_tersedia';

    public function registerResourceConversions(?CModel_Resource_ResourceInterface $resource = null) {
        $this->addResourceConversion('thumb')->width(10)->nonQueued();
        $this->addResourceConversion('large')->width(100)->nonQueued();
    }
}

/**
 * Varian *Available* memilih konversi pertama yang sudah dibuat, lalu jatuh ke berkas asli; response
 * dan stream bisa menunjuk ke sebuah konversi, bukan hanya berkas asli.
 */
class Resource_ResourceAvailableConversionTest extends TestCase {
    /**
     * @var CApp_Model_Resource
     */
    protected $resource;

    /**
     * @var string
     */
    protected $baseDir;

    /**
     * @var string
     */
    protected $original = 'berkas asli';

    /**
     * @var string
     */
    protected $thumb = 'konversi thumb yang lebih panjang';

    protected function setUp(): void {
        $key = 900000000 + random_int(1, 99999999);
        $model = new class() extends CApp_Model_Resource {
            public function save(array $options = []) {
                return true;
            }

            public function getTemporaryUrl(?DateTimeInterface $expiration = null, $conversionName = '', array $options = []) {
                return 'temporary:' . $conversionName;
            }
        };
        $this->resource = $model->newFromBuilder([
            'resource_id' => $key,
            'model_type' => 'UjiTersedia',
            'model_id' => 1,
            'collection_name' => 'default',
            'name' => 'uji',
            'file_name' => 'uji.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'local',
            'size' => strlen($this->original),
            'manipulations' => '[]',
            'custom_properties' => '[]',
            'responsive_images' => '[]',
            'order_column' => 1,
            'created' => date('Y-m-d H:i:s'),
        ]);
        $this->resource->markAsConversionGenerated('thumb', true);

        $filesystem = CResources_Factory::createFileSystem();
        $this->baseDir = DOCROOT . 'resources/' . date('Ymd') . '/UjiTersedia/' . $key;
        file_put_contents(DOCROOT . $filesystem->getResourceDirectory($this->resource) . 'uji.jpg', $this->original);
        $conversionDirectory = DOCROOT . $filesystem->getResourceDirectory($this->resource, 'conversions');
        file_put_contents($conversionDirectory . 'uji-thumb.jpg', $this->thumb);
    }

    protected function tearDown(): void {
        if ($this->baseDir && is_dir($this->baseDir)) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->baseDir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
            rmdir($this->baseDir);
        }
    }

    /**
     * @param \Symfony\Component\HttpFoundation\StreamedResponse $response
     *
     * @return string
     */
    protected function capture($response) {
        ob_start();
        $response->sendContent();

        return ob_get_clean();
    }

    public function testAvailablePathRelativeToRootPicksTheFirstGeneratedConversion() {
        $this->assertStringEndsWith('/conversions/uji-thumb.jpg', $this->resource->getAvailablePathRelativeToRoot(['large', 'thumb']));
        $this->assertStringEndsWith('/uji.jpg', $this->resource->getAvailablePathRelativeToRoot(['large']));
        $this->assertStringNotContainsString('conversions', $this->resource->getAvailablePathRelativeToRoot(['large']));
    }

    public function testAvailableTemporaryUrlPicksTheFirstGeneratedConversion() {
        $this->assertSame('temporary:thumb', $this->resource->getAvailableTemporaryUrl(['large', 'thumb']));
        $this->assertSame('temporary:', $this->resource->getAvailableTemporaryUrl(['large']));
    }

    public function testResponseCanTargetAConversion() {
        $response = $this->resource->toResponse(c::request(), 'thumb');

        $this->assertSame('attachment; filename="uji.jpg"', $response->headers->get('Content-Disposition'));
        $this->assertSame((string) strlen($this->thumb), $response->headers->get('Content-Length'), 'ukuran diambil dari berkas konversi, bukan kolom size');
        $this->assertSame($this->thumb, $this->capture($response));
    }

    public function testAvailableResponseFallsBackToTheOriginal() {
        $response = $this->resource->toAvailableResponse(c::request(), ['large', 'thumb']);
        $this->assertSame($this->thumb, $this->capture($response));

        $response = $this->resource->toAvailableInlineResponse(c::request(), ['large']);
        $this->assertSame('inline; filename="uji.jpg"', $response->headers->get('Content-Disposition'));
        $this->assertSame((string) strlen($this->original), $response->headers->get('Content-Length'));
        $this->assertSame($this->original, $this->capture($response));
    }

    public function testSmallChunkSizeStreamsTheWholeFile() {
        $response = $this->resource->setStreamChunkSize(4)->toInlineResponse(c::request(), 'thumb');

        $this->assertSame($this->thumb, $this->capture($response));
    }
}
