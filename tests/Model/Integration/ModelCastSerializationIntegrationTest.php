<?php

require_once __DIR__ . '/UjiModelSupport.php';

/**
 * Cast atribut bolak-balik ke basis data nyata (boolean tinyint, array/json, datetime, decimal),
 * accessor/mutator, hidden/appends/visible, toArray/toJson termasuk relasi, dan $dates.
 */
class ModelCastSerializationIntegrationTest extends UjiModel_IntegrationTestCase {
    public function testBooleanCastRoundTrip() {
        $user = $this->createUser(['is_active' => false]);

        $this->assertSame('0', (string) $this->table('uji_user')->value('is_active'));
        $this->assertFalse(UjiModel_User::find($user->getKey())->is_active);
        $this->assertIsBool(UjiModel_User::find($user->getKey())->is_active);

        $user->is_active = '1';
        $user->save();
        $this->assertTrue(UjiModel_User::find($user->getKey())->is_active);
        $this->assertTrue($user->hasCast('is_active', 'boolean'));
    }

    public function testArrayAndJsonCastRoundTrip() {
        $user = $this->createUser(['settings' => ['tema' => 'gelap', 'angka' => 3]]);
        $post = UjiModel_Post::create(['title' => 'p', 'meta' => ['tags' => ['a', 'b']]]);

        $this->assertSame('{"tema":"gelap","angka":3}', $this->table('uji_user')->value('settings'));
        $this->assertSame(['tema' => 'gelap', 'angka' => 3], UjiModel_User::find($user->getKey())->settings);
        $this->assertSame(['tags' => ['a', 'b']], UjiModel_Post::find($post->getKey())->meta);

        $user->settings = null;
        $user->save();
        $this->assertNull(UjiModel_User::find($user->getKey())->settings);
    }

    public function testJsonArrowUpdateWritesIntoTheJsonColumn() {
        $user = $this->createUser(['settings' => ['tema' => 'gelap', 'angka' => 3]]);

        $user->update(['settings->tema' => 'terang']);

        $this->assertSame(['tema' => 'terang', 'angka' => 3], UjiModel_User::find($user->getKey())->settings);
    }

    public function testDatetimeCastReturnsCarbonAndAcceptsStringsAndInstances() {
        $user = $this->createUser(['birthday' => '1990-05-17 08:30:00']);

        $found = UjiModel_User::find($user->getKey());
        $this->assertInstanceOf(CCarbon::class, $found->birthday);
        $this->assertSame('1990-05-17', $found->birthday->format('Y-m-d'));
        $this->assertSame('1990-05-17 08:30:00', $this->table('uji_user')->value('birthday'));

        $found->birthday = CCarbon::parse('2000-01-02 03:04:05');
        $found->save();
        $this->assertSame('2000-01-02 03:04:05', $this->table('uji_user')->value('birthday'));

        $found->birthday = null;
        $this->assertNull($found->birthday);
        $this->assertTrue($found->hasCast('birthday', ['datetime']));
        $this->assertSame(['created', 'updated'], $found->getDates(), 'getDates() hanya $dates + timestamp, cast datetime tidak ikut');
    }

    public function testDecimalCastFormatsWithTheGivenScale() {
        $post = UjiModel_Post::create(['title' => 'p', 'price' => 12.5]);

        $this->assertSame('12.50', UjiModel_Post::find($post->getKey())->price);
        $this->assertSame('12.50', $post->price);
    }

    public function testSettingTheSameCastedValueIsNotDirty() {
        $user = $this->createUser(['is_active' => true, 'settings' => ['a' => 1], 'birthday' => '1990-05-17 00:00:00']);
        $user = $user->fresh();

        $user->is_active = 1;
        $user->settings = ['a' => 1];
        $user->birthday = '1990-05-17 00:00:00';

        $this->assertFalse($user->isDirty(), 'nilai setara sesudah cast tidak dianggap berubah');
        $user->settings = ['a' => 2];
        $this->assertTrue($user->isDirty('settings'));
    }

    public function testAccessorAndMutator() {
        $user = $this->createUser(['name' => '  budi  ']);

        $this->assertSame('budi', $this->table('uji_user')->value('name'), 'mutator trim sebelum disimpan');
        $this->assertSame('BUDI', $user->display_name, 'accessor');
        $this->assertSame('BUDI', $user->getAttribute('display_name'));
        $this->assertTrue($user->hasGetMutator('display_name'));
        $this->assertTrue($user->hasSetMutator('name'));
        $this->assertArrayNotHasKey('display_name', $user->getAttributes(), 'accessor bukan kolom');
    }

    public function testToArrayAppliesHiddenAppendsAndCasts() {
        $user = $this->createUser(['is_active' => true, 'settings' => ['x' => 1], 'birthday' => '1990-05-17 08:30:00']);
        $array = UjiModel_User::find($user->getKey())->toArray();

        $this->assertArrayNotHasKey('email', $array, 'hidden');
        $this->assertSame('BUDI', $array['display_name'], 'appends');
        $this->assertTrue($array['is_active']);
        $this->assertSame(['x' => 1], $array['settings']);
        $this->assertIsString($array['birthday'], 'tanggal diserialisasi jadi string');
        $this->assertStringStartsWith('1990-05-17', $array['birthday']);
        $this->assertArrayHasKey('created', $array);
    }

    public function testMakeVisibleMakeHiddenAndOnly() {
        $user = $this->createUser();

        $this->assertArrayHasKey('email', $user->makeVisible('email')->toArray());
        $this->assertArrayNotHasKey('name', $user->makeHidden('name')->toArray());
        $this->assertSame(['name' => 'Budi'], $user->only('name'));
        $this->assertSame(['name'], array_keys(UjiModel_User::find($user->getKey())->setVisible(['name'])->toArray()));
        $this->assertArrayNotHasKey('display_name', UjiModel_User::find($user->getKey())->setAppends([])->toArray());
        $this->assertSame([], UjiModel_User::find($user->getKey())->makeHidden('name')->setVisible(['name'])->toArray(), 'hidden menang atas visible, seperti hulu');
    }

    public function testToJsonAndJsonSerializeIncludeLoadedRelations() {
        $user = $this->createUser();
        $user->posts()->create(['title' => 'p', 'meta' => ['k' => 'v'], 'is_published' => true]);
        $loaded = UjiModel_User::with('posts')->find($user->getKey());

        $json = json_decode($loaded->toJson(), true);
        $this->assertSame('p', $json['posts'][0]['title']);
        $this->assertSame(['k' => 'v'], $json['posts'][0]['meta']);
        $this->assertTrue($json['posts'][0]['is_published']);
        $this->assertSame($json, json_decode(json_encode($loaded), true), 'json_encode() memakai jsonSerialize(), bukan properti publik');
        $this->assertSame($json, $loaded->toArray());
        $this->assertArrayNotHasKey('posts', UjiModel_User::find($user->getKey())->toArray(), 'relasi yang tidak dimuat tidak ikut');
    }

    public function testHiddenRelationIsLeftOutOfTheArray() {
        $user = $this->createUser();
        $user->posts()->create(['title' => 'p']);
        $loaded = UjiModel_User::with('posts')->find($user->getKey())->makeHidden('posts');

        $this->assertArrayNotHasKey('posts', $loaded->toArray());
    }

    public function testCollectionToArrayAndPluckOnCastedAttributes() {
        $this->createUser(['name' => 'a', 'is_active' => true]);
        $this->createUser(['name' => 'b', 'is_active' => false]);

        $users = UjiModel_User::orderBy('name')->get();
        $this->assertSame([true, false], $users->pluck('is_active')->all(), 'pluck di koleksi lewat cast');
        $this->assertSame(['A', 'B'], $users->pluck('display_name')->all(), 'pluck accessor');
        $this->assertSame(['a', 'b'], array_column($users->toArray(), 'name'));
        $this->assertArrayNotHasKey('email', $users->toArray()[0]);
    }

    public function testInvalidJsonInTheColumnThrowsOnRead() {
        $user = $this->createUser();
        $this->table('uji_user')->update(['settings' => '{rusak']);

        $this->assertNull(UjiModel_User::find($user->getKey())->settings, 'json rusak dibaca sebagai null (json_decode gagal)');
    }

    public function testUnencodableAttributeThrowsJsonEncodingExceptionOnAssignment() {
        $user = $this->createUser();

        $this->expectException(CModel_Exception_JsonEncodingException::class);
        $user->settings = ['bad' => "\xB1\x31"];
    }

    public function testGetOriginalReturnsCastedValues() {
        $user = $this->createUser(['is_active' => false, 'settings' => ['a' => 1]])->fresh();
        $user->is_active = true;
        $user->settings = ['a' => 2];

        $this->assertFalse($user->getOriginal('is_active'));
        $this->assertSame(['a' => 1], $user->getOriginal('settings'));
        $this->assertSame('0', (string) $user->getRawOriginal('is_active'));
        $dirty = $user->getDirty();
        $this->assertSame(['is_active', 'settings'], array_keys($dirty));
        $this->assertSame('{"a":2}', $dirty['settings'], 'getDirty() berisi nilai mentah siap simpan');
    }
}
