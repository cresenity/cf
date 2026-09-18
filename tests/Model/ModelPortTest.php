<?php

use PHPUnit\Framework\TestCase;

class UjiPort_Stub extends CModel {
    protected $table = 'stub';

    protected $guarded = [];

    public $connection;

    public function getListItemsAttribute($value) {
        return json_decode($value, true);
    }

    public function setListItemsAttribute($value) {
        $this->attributes['list_items'] = json_encode($value);
    }

    public function getPasswordAttribute() {
        return '******';
    }

    public function setPasswordAttribute($value) {
        $this->attributes['password_hash'] = sha1($value);
    }

    public function getAppendableAttribute() {
        return 'appended';
    }
}

class UjiPort_CastingStub extends CModel {
    protected $table = 'casting';

    protected $guarded = [];

    protected $casts = [
        'intAttribute' => 'int',
        'floatAttribute' => 'float',
        'stringAttribute' => 'string',
        'boolAttribute' => 'bool',
        'booleanAttribute' => 'boolean',
        'objectAttribute' => 'object',
        'arrayAttribute' => 'array',
        'jsonAttribute' => 'json',
        'collectionAttribute' => 'collection',
        'dateAttribute' => 'date',
        'datetimeAttribute' => 'datetime',
        'timestampAttribute' => 'timestamp',
    ];
}

class UjiPort_CamelStub extends CModel {
    public static $snakeAttributes = false;

    protected $table = 'camel';

    protected $guarded = [];

    public function getFullNameAttribute() {
        return 'nama lengkap';
    }
}

class UjiPort_NoTable extends CModel {
    protected $guarded = [];
}

/**
 * Port unit test model dari suite hulu (DatabaseEloquentModelTest) yang tidak butuh basis data:
 * dirty tracking, only/except, newInstance, visibilitas, fillable/guarded lanjutan, koneksi/kunci,
 * observable events, appends, cast, isset, atribut hilang, dan JSON.
 */
class ModelPortTest extends TestCase {
    protected function tearDown(): void {
        CModel::reguard();
        CModel::preventAccessingMissingAttributes(false);
        CModel::handleMissingAttributeViolationUsing(null);
        CModel::preventSilentlyDiscardingAttributes(false);
        CModel::handleDiscardedAttributeViolationUsing(null);
        UjiPort_Stub::flushEventListeners();
    }

    public function testDirtyAttributes() {
        $model = new UjiPort_Stub(['foo' => '1', 'bar' => 2, 'baz' => 3]);
        $model->syncOriginal();
        $model->foo = 1;
        $model->bar = 20;
        $model->baz = 30;

        $this->assertTrue($model->isDirty());
        $this->assertFalse($model->isDirty('foo'), '"1" dan 1 setara');
        $this->assertTrue($model->isDirty('bar'));
        $this->assertTrue($model->isDirty('foo', 'bar'));
        $this->assertTrue($model->isDirty(['foo', 'bar']));
        $this->assertSame(['bar' => 20, 'baz' => 30], $model->getDirty());
    }

    public function testIntAndNullComparisonWhenDirty() {
        $model = new UjiPort_Stub();
        $model->intAttribute = null;
        $model->syncOriginal();

        $this->assertFalse($model->isDirty('intAttribute'));
        $model->forceFill(['intAttribute' => 0]);
        $this->assertTrue($model->isDirty('intAttribute'), 'null → 0 adalah perubahan');
    }

    public function testFloatAndNullComparisonWhenDirty() {
        $model = new UjiPort_CastingStub();
        $model->floatAttribute = null;
        $model->syncOriginal();

        $this->assertFalse($model->isDirty('floatAttribute'));
        $model->forceFill(['floatAttribute' => 0.0]);
        $this->assertTrue($model->isDirty('floatAttribute'));
    }

    public function testDirtyOnCastOrDateAttributes() {
        $model = new UjiPort_CastingStub();
        $model->setDateFormat('Y-m-d H:i:s');
        $model->boolAttribute = 1;
        $model->foo = 1;
        $model->bar = '2017-03-18';
        $model->dateAttribute = '2017-03-18';
        $model->datetimeAttribute = '2017-03-23 22:17:00';
        $model->syncOriginal();

        $model->boolAttribute = true;
        $model->foo = true;
        $model->bar = '2017-03-18 00:00:00';
        $model->dateAttribute = '2017-03-18 00:00:00';
        $model->datetimeAttribute = null;

        $this->assertTrue($model->isDirty());
        $this->assertFalse($model->isDirty('boolAttribute'), 'cast bool: 1 dan true setara');
        $this->assertTrue($model->isDirty('foo'), 'tanpa cast: 1 dan true beda tipe');
        $this->assertTrue($model->isDirty('bar'));
        $this->assertFalse($model->isDirty('dateAttribute'), 'cast date: tanggal sama walau formatnya beda');
        $this->assertTrue($model->isDirty('datetimeAttribute'));
    }

    public function testDirtyOnCastedObjectsAndCollections() {
        $model = new UjiPort_CastingStub();
        $model->setRawAttributes([
            'objectAttribute' => '["one", "two", "three"]',
            'collectionAttribute' => '["one", "two", "three"]',
        ]);
        $model->syncOriginal();

        $model->objectAttribute = ['one', 'two', 'three'];
        $model->collectionAttribute = ['one', 'two', 'three'];
        $this->assertFalse($model->isDirty('objectAttribute'));
        $this->assertFalse($model->isDirty('collectionAttribute'));

        $model->collectionAttribute = ['one', 'two'];
        $this->assertTrue($model->isDirty('collectionAttribute'));
    }

    public function testCleanAttributes() {
        $model = new UjiPort_Stub(['foo' => '1', 'bar' => 2, 'baz' => 3]);
        $model->syncOriginal();
        $model->foo = 1;
        $model->bar = 20;
        $model->baz = 30;

        $this->assertFalse($model->isClean());
        $this->assertTrue($model->isClean('foo'));
        $this->assertFalse($model->isClean('bar'));
        $this->assertFalse($model->isClean('foo', 'bar'));
        $this->assertFalse($model->isClean(['foo', 'bar']));
    }

    public function testCleanWhenFloatUpdateAttribute() {
        $model = new UjiPort_CastingStub(['castedFloat' => 8 - 6.4]);
        $model->syncOriginal();
        $model->castedFloat = 1.6;
        $this->assertTrue($model->originalIsEquivalent('castedFloat'));

        $model = new UjiPort_CastingStub(['castedFloat' => 5.6]);
        $model->syncOriginal();
        $model->castedFloat = 5.5;
        $this->assertFalse($model->originalIsEquivalent('castedFloat'));
    }

    public function testCalculatedAttributes() {
        $model = new UjiPort_Stub();
        $model->password = 'secret';
        $attributes = $model->getAttributes();

        $this->assertArrayNotHasKey('password', $attributes, 'mutator menulis ke password_hash');
        $this->assertSame('******', $model->password, 'accessor');
        $hash = 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4';
        $this->assertSame($hash, $attributes['password_hash']);
        $this->assertSame($hash, $model->password_hash);
    }

    public function testOnlyAndExcept() {
        $model = new UjiPort_Stub(['first_name' => 'taylor', 'last_name' => 'otwell', 'project' => 'laravel']);

        $this->assertSame(['project' => 'laravel'], $model->only('project'));
        $this->assertSame(['first_name' => 'taylor', 'last_name' => 'otwell'], $model->only('first_name', 'last_name'));
        $this->assertSame(['first_name' => 'taylor', 'last_name' => 'otwell'], $model->only(['first_name', 'last_name']));
        $this->assertSame(['first_name' => 'taylor', 'last_name' => 'otwell'], $model->except('project'));
        $this->assertSame(['project' => 'laravel'], $model->except(['first_name', 'last_name']));
    }

    public function testNewInstanceReturnsNewInstanceWithAttributesTableAndConnectionSet() {
        $model = new UjiPort_Stub();
        $model->setTable('tabel_baru');
        $model->setConnection('koneksi_baru');

        $instance = $model->newInstance(['name' => 'taylor']);

        $this->assertInstanceOf(UjiPort_Stub::class, $instance);
        $this->assertSame('taylor', $instance->name);
        $this->assertSame('tabel_baru', $instance->getTable());
        $this->assertSame('koneksi_baru', $instance->getConnectionName());
        $this->assertFalse($instance->exists);
        $this->assertTrue($model->newInstance([], true)->exists);
    }

    public function testNewInstanceReturnsNewInstanceWithMergedCasts() {
        $model = new UjiPort_Stub();
        $model->mergeCasts(['foo' => 'date']);
        $instance = $model->newInstance(['foo' => '2020-01-01']);

        $this->assertArrayHasKey('foo', $instance->getCasts());
        $this->assertSame('date', $instance->getCasts()['foo']);
        $this->assertInstanceOf(CCarbon::class, $instance->foo);
    }

    public function testMakeMethodDoesNotSaveNewModel() {
        $model = UjiPort_Stub::make(['name' => 'taylor']);

        $this->assertInstanceOf(UjiPort_Stub::class, $model);
        $this->assertSame('taylor', $model->name);
        $this->assertFalse($model->exists);
    }

    public function testToArrayUsesMutatorsAndSnakeAttributes() {
        $model = new UjiPort_Stub();
        $model->list_items = [1, 2, 3];
        $model->setRelation('partner', new UjiPort_Stub(['name' => 'abby']));
        $model->setRelation('group', null);
        $model->setRelation('multi', new CModel_Collection());

        $array = $model->toArray();
        $this->assertSame([1, 2, 3], $array['list_items']);
        $this->assertSame('abby', $array['partner']['name']);
        $this->assertNull($array['group']);
        $this->assertSame([], $array['multi']);

        $camel = new UjiPort_CamelStub();
        $camel->setRelation('namaRelasi', new UjiPort_Stub(['name' => 'x']));
        $this->assertArrayHasKey('namaRelasi', $camel->toArray(), 'snakeAttributes = false mempertahankan camelCase');
        $snake = new UjiPort_Stub();
        $snake->setRelation('namaRelasi', new UjiPort_Stub(['name' => 'x']));
        $this->assertArrayHasKey('nama_relasi', $snake->toArray());
    }

    public function testVisibleCreatesArrayWhitelistAndHiddenExcludesRelations() {
        $model = new UjiPort_Stub(['name' => 'taylor', 'age' => 26]);
        $model->setVisible(['name']);
        $this->assertSame(['name' => 'taylor'], $model->toArray());

        $model = new UjiPort_Stub(['name' => 'taylor', 'age' => 26]);
        $model->setRelation('foo', ['bar']);
        $model->setHidden(['foo', 'list_items', 'password']);
        $this->assertSame(['name' => 'taylor', 'age' => 26], $model->toArray());
        $this->assertSame(['foo', 'list_items', 'password'], $model->getHidden());
    }

    public function testDynamicHiddenAndVisibleAndMakeVisible() {
        $model = new UjiPort_Stub(['name' => 'taylor', 'age' => 26, 'id' => 'foo']);
        $model->setHidden(['age', 'id']);
        $this->assertSame(['name' => 'taylor'], $model->toArray());

        $model->makeVisible('age');
        $this->assertSame(['name' => 'taylor', 'age' => 26], $model->toArray());
        $this->assertSame(['id'], array_values($model->getHidden()));

        $model = new UjiPort_Stub(['name' => 'taylor', 'age' => 26]);
        $model->setVisible(['name']);
        $model->makeVisible('age');
        $this->assertSame(['name', 'age'], $model->getVisible());
        $model->makeHidden('name');
        $this->assertSame(['age' => 26], $model->toArray());
        $this->assertSame(['name'], $model->getHidden());
    }

    public function testFillableAndGuardedInteraction() {
        $model = new class() extends UjiPort_Stub {
            protected $fillable = ['name', 'age'];

            protected $guarded = ['age'];
        };
        $model->fill(['name' => 'foo', 'age' => 'bar', 'foo' => 'bar']);

        $this->assertSame('foo', $model->name);
        $this->assertSame('bar', $model->age, 'fillable menang atas guarded (sama seperti hulu)');
        $this->assertNull($model->foo);
        $this->assertTrue($model->isFillable('name'));
        $this->assertFalse($model->isFillable('foo'));
        $this->assertSame(['name', 'age'], $model->getFillable());
    }

    public function testUnderscorePropertiesAreNotFilled() {
        $model = new UjiPort_Stub();
        $model->fill(['_method' => 'PUT']);

        $this->assertSame([], $model->getAttributes());
    }

    public function testGuardedDiscardingCanBeMadeLoud() {
        $model = new class() extends UjiPort_Stub {
            protected $fillable = ['name'];
        };
        CModel::preventSilentlyDiscardingAttributes();

        try {
            $model->fill(['name' => 'ok', 'email' => 'dibuang']);
            $this->fail('harus melempar');
        } catch (CModel_Exception_MassAssignmentException $e) {
            $this->assertStringContainsString('email', $e->getMessage());
        }

        $seen = null;
        CModel::handleDiscardedAttributeViolationUsing(function ($model, $keys) use (&$seen) {
            $seen = $keys;
        });
        $model->fill(['name' => 'ok', 'email' => 'dibuang', 'age' => 1]);
        $this->assertSame(['email', 'age'], array_values($seen));
        $this->assertSame('ok', $model->name);
    }

    public function testUnguardedRunsCallbackAndRestoresStateEvenOnException() {
        $this->assertFalse(CModel::isUnguarded());

        $result = CModel::unguarded(function () {
            return CModel::isUnguarded();
        });
        $this->assertTrue($result);
        $this->assertFalse(CModel::isUnguarded());

        try {
            CModel::unguarded(function () {
                throw new RuntimeException('meledak');
            });
        } catch (RuntimeException $e) {
        }
        $this->assertFalse(CModel::isUnguarded(), 'reguard di finally');

        CModel::unguard();
        $this->assertTrue(CModel::unguarded(function () {
            return CModel::isUnguarded();
        }));
        $this->assertTrue(CModel::isUnguarded(), 'unguarded() tidak mengubah status yang sudah unguard');
        CModel::reguard();
    }

    public function testQualifyColumn() {
        $model = new UjiPort_Stub();

        $this->assertSame('stub.column', $model->qualifyColumn('column'));
        $this->assertSame('lain.column', $model->qualifyColumn('lain.column'), 'sudah terkualifikasi dibiarkan');
        $this->assertSame(['stub.a', 'x.b'], $model->qualifyColumns(['a', 'x.b']));
        $this->assertSame('stub.stub_id', $model->getQualifiedKeyName());
    }

    public function testGetAndSetTableAndKey() {
        $model = new UjiPort_Stub();
        $this->assertSame('stub', $model->getTable());
        $this->assertSame('stub_id', $model->getKeyName(), 'kunci CF = <tabel>_id');
        $this->assertSame('stub_id', $model->getRouteKeyName());

        $model->setTable('foo');
        $this->assertSame('foo', $model->getTable());
        $this->assertSame('stub_id', $model->getKeyName(), 'mengganti tabel tidak mengganti kunci yang sudah ditentukan');

        $model->stub_id = 7;
        $this->assertSame(7, $model->getKey());
        $this->assertSame(7, $model->getRouteKey());

        $this->assertSame('no_tables', (new UjiPort_NoTable())->getTable(), 'tanpa $table: snake plural dari basename kelas sesudah prefix_ (CF), bukan seluruh nama kelas');
        $this->assertSame('id', (new UjiPort_NoTable())->getKeyName(), 'tanpa $table: kunci id');
    }

    public function testConnectionManagement() {
        $model = new UjiPort_Stub();
        $this->assertNull($model->getConnectionName());

        $this->assertSame($model, $model->setConnection('foo'));
        $this->assertSame('foo', $model->getConnectionName());
        $this->assertInstanceOf(CDatabase_Manager::class, CModel::getConnectionResolver());
    }

    public function testObservableEventsCanBeSetAddedAndRemoved() {
        $model = new UjiPort_Stub();
        $model->setObservableEvents(['foo']);
        $this->assertContains('foo', $model->getObservableEvents());

        $model->addObservableEvents('bar');
        $model->addObservableEvents(['baz', 'qux']);
        foreach (['foo', 'bar', 'baz', 'qux', 'saving', 'deleted'] as $event) {
            $this->assertContains($event, $model->getObservableEvents(), 'event bawaan selalu ikut');
        }

        $model->removeObservableEvents('bar');
        $model->removeObservableEvents(['baz', 'qux']);
        $this->assertContains('foo', $model->getObservableEvents());
        $this->assertNotContains('bar', $model->getObservableEvents());
        $this->assertNotContains('qux', $model->getObservableEvents());
    }

    public function testObserverCanBeAttachedWithStringInstanceOrArrayOnlyOnce() {
        UjiPort_Stub::observe(UjiPort_Observer::class);
        UjiPort_Stub::observe(new UjiPort_Observer());
        UjiPort_Stub::observe([UjiPort_Observer::class]);
        UjiPort_Observer::$hits = 0;

        $fire = new ReflectionMethod(UjiPort_Stub::class, 'fireModelEvent');
        $fire->setAccessible(true);
        $fire->invoke(new UjiPort_Stub(), 'saving');

        $this->assertGreaterThanOrEqual(1, UjiPort_Observer::$hits, 'observer terpasang lewat string, instance, maupun array');
        $this->assertSame(3, UjiPort_Observer::$hits, 'tiap observe() mendaftarkan listener sendiri: ' . UjiPort_Observer::$hits . ' kali');
    }

    public function testAttachingANonExistentObserverThrows() {
        $this->expectException(InvalidArgumentException::class);

        UjiPort_Stub::observe('UjiPort_TidakAda');
    }

    public function testAppendingOfAttributes() {
        $model = new UjiPort_Stub();
        $model->setAppends(['appendable']);
        $this->assertTrue($model->hasAppended('appendable'));
        $this->assertFalse($model->hasAppended('lain'));

        $array = $model->toArray();
        $this->assertSame('appended', $array['appendable']);

        $model->append('lain_lagi');
        $this->assertSame(['appendable', 'lain_lagi'], $model->getAppends());
        $this->assertSame(['appendable'], (new UjiPort_Stub())->append('appendable')->getAppends());
    }

    public function testGetMutatedAttributes() {
        $model = new UjiPort_Stub();

        $this->assertSame(['list_items', 'password', 'appendable'], $model->getMutatedAttributes());
        $this->assertSame(['fullName'], (new UjiPort_CamelStub())->getMutatedAttributes(), 'snakeAttributes = false: nama accessor camelCase');
    }

    public function testModelAttributesAreCastedWhenPresentInCastsProperty() {
        $model = new UjiPort_CastingStub();
        $model->setDateFormat('Y-m-d H:i:s');
        $model->intAttribute = '3';
        $model->floatAttribute = '4.0';
        $model->stringAttribute = 2.5;
        $model->boolAttribute = 1;
        $model->booleanAttribute = 0;
        $model->objectAttribute = ['foo' => 'bar'];
        $obj = new stdClass();
        $obj->foo = 'bar';
        $model->arrayAttribute = $obj;
        $model->jsonAttribute = ['foo' => 'bar'];
        $model->dateAttribute = '1969-07-20';
        $model->datetimeAttribute = '1969-07-20 22:56:00';
        $model->timestampAttribute = '1969-07-20 22:56:00';
        $model->collectionAttribute = new CCollection();

        $this->assertIsInt($model->intAttribute);
        $this->assertIsFloat($model->floatAttribute);
        $this->assertIsString($model->stringAttribute);
        $this->assertIsBool($model->boolAttribute);
        $this->assertIsBool($model->booleanAttribute);
        $this->assertIsObject($model->objectAttribute);
        $this->assertIsArray($model->arrayAttribute);
        $this->assertIsArray($model->jsonAttribute);
        $this->assertTrue($model->boolAttribute);
        $this->assertFalse($model->booleanAttribute);
        $this->assertEquals($obj, $model->objectAttribute);
        $this->assertSame(['foo' => 'bar'], $model->arrayAttribute);
        $this->assertSame(['foo' => 'bar'], $model->jsonAttribute);
        $this->assertInstanceOf(CCarbon::class, $model->dateAttribute);
        $this->assertInstanceOf(CCarbon::class, $model->datetimeAttribute);
        $this->assertInstanceOf(CCollection::class, $model->collectionAttribute);
        $this->assertSame('1969-07-20', $model->dateAttribute->toDateString());
        $this->assertSame('1969-07-20 22:56:00', $model->datetimeAttribute->toDateTimeString());
        $this->assertEquals(CCarbon::parse('1969-07-20 22:56:00')->getTimestamp(), $model->timestampAttribute);

        $arr = $model->toArray();
        $this->assertIsInt($arr['intAttribute']);
        $this->assertIsFloat($arr['floatAttribute']);
        $this->assertIsString($arr['stringAttribute']);
        $this->assertIsBool($arr['boolAttribute']);
        $this->assertIsObject($arr['objectAttribute']);
        $this->assertIsArray($arr['arrayAttribute']);
        $this->assertIsArray($arr['jsonAttribute']);
        $this->assertIsArray($arr['collectionAttribute']);
        $this->assertSame(CCarbon::parse('1969-07-20')->toJSON(), $arr['dateAttribute']);
        $this->assertSame(CCarbon::parse('1969-07-20 22:56:00')->toJSON(), $arr['datetimeAttribute']);
        $this->assertEquals(CCarbon::parse('1969-07-20 22:56:00')->getTimestamp(), $arr['timestampAttribute']);
    }

    public function testModelDateAttributeCastingResetsTime() {
        $model = new UjiPort_CastingStub();
        $model->setDateFormat('Y-m-d H:i:s');
        $model->dateAttribute = '1969-07-20 22:56:00';

        $this->assertSame('1969-07-20 00:00:00', $model->dateAttribute->toDateTimeString());
        $this->assertSame(CCarbon::parse('1969-07-20')->toJSON(), $model->toArray()['dateAttribute'], 'ISO-8601 dalam UTC (zona waktu app dikonversi)');
    }

    public function testModelAttributeCastingPreservesNull() {
        $model = new UjiPort_CastingStub();
        foreach (['intAttribute', 'floatAttribute', 'stringAttribute', 'boolAttribute', 'booleanAttribute', 'objectAttribute', 'arrayAttribute', 'jsonAttribute', 'dateAttribute', 'datetimeAttribute', 'timestampAttribute', 'collectionAttribute'] as $key) {
            $model->{$key} = null;
        }

        $attributes = $model->getAttributes();
        $array = $model->toArray();
        foreach (array_keys($attributes) as $key) {
            $this->assertNull($attributes[$key], $key);
            $this->assertNull($model->{$key}, $key);
            $this->assertNull($array[$key], $key);
        }
    }

    public function testModelAttributeCastingFailsOnUnencodableData() {
        $model = new UjiPort_CastingStub();

        //CF mengencode saat assignment (bukan saat getAttributes() seperti hulu), jadi galatnya muncul lebih awal
        $this->expectException(CModel_Exception_JsonEncodingException::class);
        $model->objectAttribute = ['foo' => "b\xF8r"];
    }

    public function testModelAttributeCastingWithFloatsAndArrays() {
        $model = new UjiPort_CastingStub();

        $model->floatAttribute = 0;
        $this->assertSame(0.0, $model->floatAttribute);
        //divergensi: hulu memetakan string 'Infinity'/'-Infinity'/'NaN' ke INF/-INF/NAN; CF memakai (float) polos
        $model->floatAttribute = 'Infinity';
        $this->assertSame(0.0, $model->floatAttribute);
        $model->floatAttribute = '1e3';
        $this->assertSame(1000.0, $model->floatAttribute);

        $model->arrayAttribute = ['foo' => 'bar'];
        $this->assertSame(['foo' => 'bar'], $model->arrayAttribute);
        $model->arrayAttribute = '{"foo": "bar"}';
        $this->assertSame('{"foo": "bar"}', $model->arrayAttribute, 'string yang di-assign ke cast array dikembalikan sebagai string (sama seperti hulu: dianggap nilai string, bukan JSON)');
        $this->assertSame('"{\"foo\": \"bar\"}"', $model->getAttributes()['arrayAttribute'], 'disimpan sebagai JSON string (double-encoded), seperti hulu');
    }

    public function testMergeCastsMergesCasts() {
        $model = new UjiPort_CastingStub();
        $before = $model->getCasts();
        $model->mergeCasts(['foo' => 'date', 'bar' => 'datetime']);

        $this->assertSame(count($before) + 2, count($model->getCasts()));
        $this->assertSame('date', $model->getCasts()['foo']);
        $this->assertTrue($model->hasCast('bar', 'datetime'));
    }

    public function testIssetBehavesCorrectlyWithAttributesAndRelationships() {
        $model = new UjiPort_Stub();
        $model->name = 'ada';
        $model->kosong = null;
        $model->setRelation('relasi', new UjiPort_Stub());
        $model->setRelation('relasiNull', null);

        $this->assertTrue(isset($model->name));
        $this->assertFalse(isset($model->kosong), 'null = tidak isset');
        $this->assertFalse(isset($model->tidakAda));
        $this->assertTrue(isset($model->relasi));
        $this->assertFalse(isset($model->relasiNull));
        $this->assertTrue(isset($model['name']));
        $this->assertFalse(isset($model['kosong']));

        unset($model->name, $model['relasi']);
        $this->assertFalse(isset($model->name));
        $this->assertFalse($model->relationLoaded('relasi'));
    }

    public function testKeyTypeIsPreservedWhenSetting() {
        $model = new UjiPort_Stub();
        $model->stub_id = '1';
        $this->assertSame(1, $model->getKey(), 'keyType int memaksa int');

        $string = new class() extends UjiPort_Stub {
            protected $keyType = 'string';
        };
        $string->stub_id = 1;
        $this->assertSame('1', $string->getKey());
    }

    public function testThrowsWhenAccessingMissingAttributesOnAnExistingModelOnly() {
        $model = new UjiPort_Stub(['name' => 'ada']);
        $model->exists = true;
        CModel::preventAccessingMissingAttributes();

        $this->assertSame('ada', $model->name);
        try {
            $model->tidak_ada;
            $this->fail('harus melempar');
        } catch (CModel_Exception_MissingAttributeException $e) {
            $this->assertStringContainsString('tidak_ada', $e->getMessage());
        }

        $fresh = new UjiPort_Stub(['name' => 'ada']);
        $this->assertNull($fresh->tidak_ada, 'model yang belum disimpan bebas');
        $model->wasRecentlyCreated = true;
        $this->assertNull($model->tidak_ada, 'model yang baru dibuat bebas');
        $model->wasRecentlyCreated = false;

        $seen = [];
        CModel::handleMissingAttributeViolationUsing(function ($model, $key) use (&$seen) {
            $seen[] = $key;
        });
        $model->tidak_ada;
        $this->assertSame(['tidak_ada'], $seen);
        $this->assertFalse(isset($model->tidak_ada), 'isset tidak melempar');
        $model->tidak_ada = 1;
        $this->assertSame(1, $model->tidak_ada, 'assignment lalu baca boleh');
    }

    public function testHasAttributeAndDiscardChangesWithCasts() {
        $model = new UjiPort_CastingStub(['intAttribute' => 1]);
        $model->syncOriginal();

        $this->assertTrue($model->hasAttribute('intAttribute'));
        $this->assertFalse($model->hasAttribute('lain'));

        $model->intAttribute = 2;
        $model->lain = 'x';
        $this->assertTrue($model->isDirty());
        $model->discardChanges();
        $this->assertFalse($model->isDirty());
        $this->assertSame(1, $model->intAttribute);
        $this->assertFalse($model->hasAttribute('lain'));
    }

    public function testToJsonPrettyAndJsonError() {
        $model = new UjiPort_Stub(['name' => 'taylor', 'age' => 26]);

        $this->assertSame("{\n    \"name\": \"taylor\",\n    \"age\": 26\n}", $model->toJson(JSON_PRETTY_PRINT));
        $this->assertSame('{"name":"taylor","age":26}', (string) $model);
        $this->assertSame('{"name":"taylor","age":26}', $model->toJson());
    }

    public function testToJsonSucceedsWithPriorErrors() {
        $model = new UjiPort_Stub(['name' => 'taylor', 'age' => 26]);
        json_decode('rusak{');
        $this->assertNotSame(JSON_ERROR_NONE, json_last_error());

        $this->assertSame('{"name":"taylor","age":26}', $model->toJson());
    }

    public function testFromDateTime() {
        $model = new UjiPort_Stub();
        $model->setDateFormat('Y-m-d H:i:s');

        $value = CCarbon::parse('2015-04-17 22:59:01');
        $this->assertSame('2015-04-17 22:59:01', $model->fromDateTime($value));
        $this->assertSame('2015-04-17 22:59:01', $model->fromDateTime(new DateTime('2015-04-17 22:59:01')));
        $this->assertSame('2015-04-17 22:59:01', $model->fromDateTime(new DateTimeImmutable('2015-04-17 22:59:01')));
        $this->assertSame('2015-04-17 22:59:01', $model->fromDateTime('2015-04-17 22:59:01'));
        $this->assertSame('2015-04-17 00:00:00', $model->fromDateTime('2015-04-17'));
        $this->assertSame(CCarbon::createFromTimestamp(1429311541)->format('Y-m-d H:i:s'), $model->fromDateTime(1429311541), 'timestamp dikonversi ke zona waktu app');
        $this->assertNull($model->fromDateTime(null));
    }

    public function testTimestampsAreCreatedFromStringsIntegersAndDates() {
        $model = new UjiPort_Stub();
        $model->created = '2013-05-22 00:00:00';
        $this->assertInstanceOf(CCarbon::class, $model->created);
        $model->created = 1391878421;
        $this->assertInstanceOf(CCarbon::class, $model->created);
        $this->assertSame('2014-02-08', $model->created->toDateString());
        $model->created = '2012-01-01';
        $this->assertInstanceOf(CCarbon::class, $model->created);
        $model->created = null;
        $this->assertNull($model->created);
    }

    public function testReplicateFiresReplicatingEventAndReplicateQuietlyDoesNot() {
        $model = new UjiPort_Stub(['stub_id' => 5, 'name' => 'asli', 'created' => '2020-01-01 00:00:00']);
        $fired = 0;
        UjiPort_Stub::replicating(function () use (&$fired) {
            $fired++;
        });

        $copy = $model->replicate();
        $this->assertSame(1, $fired);
        $this->assertNull($copy->stub_id);
        $this->assertNull($copy->created);
        $this->assertSame('asli', $copy->name);

        $model->replicateQuietly();
        $this->assertSame(1, $fired);
    }

    public function testCloneModelMakesAFreshCopy() {
        $model = new UjiPort_Stub(['stub_id' => 1, 'name' => 'x']);
        $model->exists = true;
        $model->setRelation('r', new UjiPort_Stub());

        $clone = clone $model;

        $this->assertSame(1, $clone->stub_id, 'clone PHP biasa menyalin semuanya, termasuk kunci dan exists (beda dari replicate())');
        $this->assertTrue($clone->exists);
        $this->assertTrue($clone->relationLoaded('r'));
    }

    public function testNonExistingAttributeWithInternalMethodNameDoesntCallMethod() {
        $model = new UjiPort_Stub();

        $this->assertNull($model->delete, 'nama method model bukan atribut');
        $this->assertNull($model->getTable, 'getter internal tidak dipanggil lewat __get');
    }
}

class UjiPort_Observer {
    public static $hits = 0;

    public function saving($model) {
        static::$hits++;
    }
}
