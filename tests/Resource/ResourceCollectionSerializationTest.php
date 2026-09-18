<?php

use PHPUnit\Framework\TestCase;

class UjiSerialisasi extends CModel implements CModel_HasResourceInterface {
    use CModel_HasResource_HasResourceTrait;

    protected $table = 'uji_serialisasi';

    public function registerResourceConversions(?CModel_Resource_ResourceInterface $resource = null) {
        $this->addResourceConversion('preview')->width(10)->nonQueued();
    }
}

/**
 * CModel_Resource_ResourceCollection - serialisasi koleksi (JSON & toHtml) memberi satu entri per
 * resource walau tabel tidak punya kolom uuid, dan atribut original_url/preview_url terisi.
 */
class ResourceCollectionSerializationTest extends TestCase {
    /**
     * @param int   $id
     * @param array $extra
     *
     * @return CApp_Model_Resource
     */
    private function resource($id, array $extra = []) {
        $model = new class() extends CApp_Model_Resource {
            public function save(array $options = []) {
                return true;
            }
        };

        return $model->newFromBuilder(array_merge([
            'resource_id' => $id,
            'model_type' => 'UjiSerialisasi',
            'model_id' => 1,
            'collection_name' => 'images',
            'name' => 'gambar-' . $id,
            'file_name' => 'gambar-' . $id . '.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'local',
            'size' => 10 * $id,
            'manipulations' => '[]',
            'custom_properties' => '[]',
            'responsive_images' => '[]',
            'order_column' => $id,
        ], $extra));
    }

    /**
     * @param array $resources
     *
     * @return CModel_Resource_ResourceCollection
     */
    private function collection(array $resources) {
        return (new CModel_Resource_ResourceCollection($resources))->collectionName('images');
    }

    public function testEveryResourceSurvivesSerializationWithoutAUuidColumn() {
        $collection = $this->collection([$this->resource(1), $this->resource(2), $this->resource(3)]);

        $json = $collection->jsonSerialize();

        $this->assertCount(3, $json);
        $this->assertSame(['1', '2', '3'], array_map('strval', array_keys($json)), 'tanpa uuid, kuncinya resource_id');
        $this->assertSame('gambar-2.jpg', $json[2]['file_name']);
        $this->assertSame(20, $json[2]['size']);
        $this->assertSame(2, $json[2]['order']);
        $this->assertSame('jpg', $json[2]['extension']);
        $this->assertNull($json[2]['uuid']);
        $this->assertCount(3, json_decode(json_encode($collection), true));
    }

    public function testUuidIsUsedAsTheKeyWhenPresent() {
        $collection = $this->collection([
            $this->resource(1, ['uuid' => 'aaaa-1']),
            $this->resource(2, ['uuid' => 'bbbb-2']),
            $this->resource(3),
        ]);

        $json = $collection->jsonSerialize();

        $this->assertSame(['aaaa-1', 'bbbb-2', 3], array_keys($json));
        $this->assertSame('aaaa-1', $json['aaaa-1']['uuid']);
    }

    public function testOriginalAndPreviewUrlAttributes() {
        $resource = $this->resource(7);

        $this->assertSame($resource->getFullUrl(), $resource->original_url);
        $this->assertStringContainsString('gambar-7.jpg', $resource->original_url);
        $this->assertSame('', $resource->preview_url, 'tanpa konversi preview → kosong');

        $resource->markAsConversionGenerated('preview', true);
        $this->assertSame($resource->getFullUrl('preview'), $resource->preview_url);
        $this->assertStringContainsString('gambar-7-preview.jpg', $resource->preview_url);

        $json = $this->collection([$resource])->jsonSerialize();
        $this->assertSame($resource->original_url, $json[7]['original_url']);
        $this->assertSame($resource->preview_url, $json[7]['preview_url']);
    }

    public function testToHtmlIsEscapedJsonWithOneEntryPerResource() {
        $collection = $this->collection([$this->resource(1), $this->resource(2)]);

        $html = $collection->toHtml();
        $decoded = json_decode(html_entity_decode($html, ENT_QUOTES), true);

        $this->assertStringNotContainsString('"', $html, 'tanda kutip di-escape untuk atribut HTML');
        $this->assertCount(2, $decoded);
        $this->assertSame('gambar-1.jpg', $decoded[1]['file_name']);
    }

    public function testWithoutACollectionNameJsonIsEmpty() {
        $collection = new CModel_Resource_ResourceCollection([$this->resource(1)]);

        $this->assertSame([], $collection->jsonSerialize());
        $this->assertSame('images', $this->collection([])->collectionName);
        $this->assertSame('foto', $this->collection([])->formFieldName('foto')->formFieldName);
    }

    public function testTotalSizeInBytes() {
        $this->assertSame(60, $this->collection([$this->resource(1), $this->resource(2), $this->resource(3)])->totalSizeInBytes());
    }

    public function testConversionFlagsCanBeSetAndReset() {
        $resource = $this->resource(9);

        $this->assertFalse($resource->hasGeneratedConversion('thumb'));
        $resource->markAsConversionGenerated('thumb', true);
        $this->assertTrue($resource->hasGeneratedConversion('thumb'));
        $this->assertSame(['thumb' => true], $resource->getGeneratedConversions()->all());

        $this->assertSame($resource, $resource->markAsConversionNotGenerated('thumb'));
        $this->assertFalse($resource->hasGeneratedConversion('thumb'));
        $this->assertSame(['thumb' => false], $resource->getGeneratedConversions()->all());
        $this->assertSame(['generated_conversions' => ['thumb' => false]], $resource->custom_properties, 'tanpa kolom generated_conversions → custom_properties');
    }

    public function testConversionFlagsUseTheGeneratedConversionsColumnWhenTheRowHasOne() {
        $model = new class() extends CApp_Model_Resource {
            public function save(array $options = []) {
                return true;
            }

        };
        $resource = $model->newFromBuilder([
            'resource_id' => 11,
            'file_name' => 'a.jpg',
            'custom_properties' => '[]',
            'generated_conversions' => '{"thumb":true}',
        ]);

        //baris dimuat dengan atribut generated_conversions → tabelnya punya kolom itu
        $this->assertTrue($resource->hasGeneratedConversion('thumb'), 'dibaca dari kolom');
        $resource->markAsConversionGenerated('preview', true);
        $this->assertSame(['thumb' => true, 'preview' => true], $resource->generated_conversions);
        $this->assertSame([], $resource->custom_properties, 'custom_properties tidak disentuh bila kolom ada');

        $resource->markAsConversionNotGenerated('thumb');
        $this->assertFalse($resource->hasGeneratedConversion('thumb'));
        $this->assertTrue($resource->hasGeneratedConversion('preview'));
    }
}
