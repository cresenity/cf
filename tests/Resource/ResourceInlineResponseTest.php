<?php

use PHPUnit\Framework\TestCase;

class UjiRespons extends CModel implements CModel_HasResourceInterface {
    use CModel_HasResource_HasResourceTrait;

    protected $table = 'uji_respons';

    public function registerResourceConversions(?CModel_Resource_ResourceInterface $resource = null) {
    }
}

/**
 * toResponse() mengunduh (attachment), toInlineResponse() menampilkan di browser (inline); keduanya
 * mengalirkan isi berkas yang sama.
 */
class Resource_ResourceInlineResponseTest extends TestCase {
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
    protected $content = 'isi berkas uji';

    protected function setUp(): void {
        $key = 900000000 + random_int(1, 99999999);
        $this->resource = new class() extends CApp_Model_Resource {
            public function save(array $options = []) {
                return true;
            }
        };
        $this->resource = $this->resource->newFromBuilder([
            'resource_id' => $key,
            'model_type' => 'UjiRespons',
            'model_id' => 1,
            'collection_name' => 'default',
            'name' => 'uji',
            'file_name' => 'uji.txt',
            'mime_type' => 'text/plain',
            'disk' => 'local',
            'size' => strlen($this->content),
            'manipulations' => '[]',
            'custom_properties' => '[]',
            'responsive_images' => '[]',
            'order_column' => 1,
            'created' => date('Y-m-d H:i:s'),
        ]);

        $directory = CResources_Factory::createFileSystem()->getResourceDirectory($this->resource);
        $this->baseDir = DOCROOT . 'resources/' . date('Ymd') . '/UjiRespons/' . $key;
        file_put_contents(DOCROOT . $directory . 'uji.txt', $this->content);
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

    public function testToResponseSendsAnAttachment() {
        $response = $this->resource->toResponse(c::request());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('attachment; filename="uji.txt"', $response->headers->get('Content-Disposition'));
        $this->assertSame('text/plain', $response->headers->get('Content-Type'));
        $this->assertSame((string) strlen($this->content), $response->headers->get('Content-Length'));
        $this->assertSame($this->content, $this->capture($response));
    }

    public function testToInlineResponseOnlyChangesTheDisposition() {
        $response = $this->resource->toInlineResponse(c::request());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('inline; filename="uji.txt"', $response->headers->get('Content-Disposition'));
        $this->assertSame('text/plain', $response->headers->get('Content-Type'));
        $this->assertSame((string) strlen($this->content), $response->headers->get('Content-Length'));
        $this->assertSame($this->content, $this->capture($response));
    }
}
