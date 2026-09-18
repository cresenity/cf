<?php

use PHPUnit\Framework\TestCase;

class UjiKonversi extends CModel implements CModel_HasResourceInterface {
    use CModel_HasResource_HasResourceTrait;

    protected $table = 'uji_konversi';

    public function registerResourceConversions(?CModel_Resource_ResourceInterface $resource = null) {
        $this->addResourceConversion('thumb')->width(10)->nonQueued();
    }
}

/**
 * Konversi gambar tidak boleh meninggalkan berkas apa pun di temp/resource: sebelumnya hanya
 * salinan asli yang dihapus, hasil konversi `<nama>-<konversi>.<ext>` tertinggal selamanya.
 */
class Resource_ConversionTemporaryFilesTest extends TestCase {
    /**
     * @var CApp_Model_Resource
     */
    protected $resource;

    /**
     * @var string
     */
    protected $baseDir;

    protected function setUp(): void {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('butuh ekstensi gd');
        }
        $key = 900000000 + random_int(1, 99999999);
        $this->resource = new class() extends CApp_Model_Resource {
            public function save(array $options = []) {
                //tidak menyentuh basis data; yang diuji hanya berkas
                return true;
            }
        };
        $this->resource = $this->resource->newFromBuilder([
            'resource_id' => $key,
            'model_type' => 'UjiKonversi',
            'model_id' => 1,
            'collection_name' => 'default',
            'name' => 'uji',
            'file_name' => 'uji.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'local',
            'conversions_disk' => 'local',
            'size' => 0,
            'manipulations' => '[]',
            'custom_properties' => '[]',
            'responsive_images' => '[]',
            'order_column' => 1,
            'created' => date('Y-m-d H:i:s'),
        ]);

        $filesystem = CResources_Factory::createFileSystem();
        $directory = $filesystem->getResourceDirectory($this->resource);
        $this->baseDir = DOCROOT . 'resources/' . date('Ymd') . '/UjiKonversi/' . $key;
        $image = imagecreatetruecolor(40, 30);
        imagejpeg($image, DOCROOT . $directory . 'uji.jpg');
        imagedestroy($image);
    }

    protected function tearDown(): void {
        if ($this->baseDir && is_dir($this->baseDir)) {
            $this->removeDirectory($this->baseDir);
        }
    }

    /**
     * @param string $dir
     *
     * @return void
     */
    protected function removeDirectory($dir) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }

    /**
     * @return int
     */
    protected function countTemporaryEntries() {
        $temporary = DOCROOT . 'temp/resource';
        if (!is_dir($temporary)) {
            return 0;
        }
        $count = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporary, FilesystemIterator::SKIP_DOTS)) as $item) {
            $count++;
        }

        return $count;
    }

    public function testConversionLeavesNothingInTemporaryDirectory() {
        $before = $this->countTemporaryEntries();
        $conversions = new CResources_ConversionCollection([
            CResources_Conversion::create('thumb')->width(10)->nonQueued(),
        ]);

        (new CResources_FileManipulator())->performConversions($conversions, $this->resource);

        $this->assertFileExists($this->baseDir . '/conversions/uji-thumb.jpg', 'hasil konversi harus tersimpan di library');
        $this->assertSame($before, $this->countTemporaryEntries(), 'temp/resource tidak boleh bertambah sesudah konversi');
    }
}
