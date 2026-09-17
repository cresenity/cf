<?php

use PHPUnit\Framework\TestCase;

/**
 * Dua sumber tipe properti model yang diperbaiki bersama #-13849.
 *
 * `model:update` menulis argumen pertama method relasi sebagai kelas terkait.
 * Pada morphTo() argumen itu nama relasinya (`__FUNCTION__`), sehingga anotasi
 * yang lahir berbunyi `CModel_Collection|__FUNCTION__[]` - 67 salah lapor pada
 * tribelio. Kelas yang tidak ada harus membuat generator mundur, bukan mengarang.
 *
 * DatabaseSchemaHelper membaca kolom dari DB yang tersambung. Tanpa koneksi ia
 * harus diam (null), bukan menghentikan analisis PHPStan dengan exception.
 */
class ModelPropertySourceTest extends TestCase {
    public function testGeneratorSkipsARelationWhoseArgumentIsNotAClass() {
        $method = new ReflectionMethod(ModelPropertySourceMorphFixture::class, 'product');

        $this->assertSame([null, null], CModel_Console_PropertiesHelper::getRelationClass($method));
    }

    public function testGeneratorStillReadsARealRelatedClass() {
        $method = new ReflectionMethod(ModelPropertySourceMorphFixture::class, 'owner');

        $this->assertSame([ModelPropertySourceMorphFixture::class, true], CModel_Console_PropertiesHelper::getRelationClass($method));
    }

    public function testSchemaHelperIsSilentWithoutADatabaseConnection() {
        $helper = new CQC_Phpstan_Service_Property_DatabaseSchemaHelper();
        $model = new ModelPropertySourceUnreachableFixture();

        $this->assertNull($helper->table($model));
        $this->assertNull($helper->column($model, 'name'));
        //panggilan berikutnya tidak mencoba lagi
        $this->assertNull($helper->table($model));
    }
}

class ModelPropertySourceMorphFixture extends CModel {
    protected $table = 'model_property_source_fixture';

    public function product() {
        return $this->morphTo(__FUNCTION__, 'product_model', 'product_id');
    }

    public function owner() {
        return $this->belongsTo(ModelPropertySourceMorphFixture::class)->withTrashed();
    }
}

class ModelPropertySourceUnreachableFixture extends CModel {
    protected $table = 'model_property_source_fixture';

    protected $connection = 'koneksi-yang-tidak-ada';
}
