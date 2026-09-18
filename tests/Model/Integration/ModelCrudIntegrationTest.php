<?php

require_once __DIR__ . '/UjiModelSupport.php';

/**
 * Siklus hidup model di atas basis data nyata (SQLite in-memory): create/save/update/find, dirty
 * tracking, firstOrCreate/updateOrCreate, increment, touch, replicate, fresh/refresh, chunk/cursor,
 * dan fill/guarded — padanan DatabaseEloquentIntegrationTest hulu dengan konvensi kolom CF.
 */
class ModelCrudIntegrationTest extends UjiModel_IntegrationTestCase {
    public function testBasicCreateRetrieveUpdateDelete() {
        $user = UjiModel_User::create(['name' => 'Budi', 'email' => 'budi@example.test']);

        $this->assertTrue($user->exists);
        $this->assertTrue($user->wasRecentlyCreated);
        $this->assertSame(1, $user->uji_user_id);

        $found = UjiModel_User::find(1);
        $this->assertInstanceOf(UjiModel_User::class, $found);
        $this->assertSame('Budi', $found->name);
        $this->assertFalse($found->wasRecentlyCreated);

        $found->name = 'Budi Santoso';
        $this->assertTrue($found->isDirty('name'));
        $this->assertTrue($found->save());
        $this->assertFalse($found->isDirty());
        $this->assertTrue($found->wasChanged('name'));
        $this->assertSame(['name' => 'Budi Santoso'], carr::only($found->getChanges(), ['name']));
        $this->assertSame('Budi Santoso', UjiModel_User::find(1)->name);
    }

    public function testSaveWithoutChangesDoesNotHitTheDatabaseAndReturnsTrue() {
        $user = $this->createUser();
        $updated = $this->table('uji_user')->value('updated');
        $queries = [];
        $this->connection->listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $this->assertTrue($user->save());

        $this->assertSame([], $queries, 'tidak ada query UPDATE saat tidak ada perubahan');
        $this->assertSame($updated, $this->table('uji_user')->value('updated'));
    }

    public function testFindVariants() {
        $a = $this->createUser(['name' => 'a']);
        $b = $this->createUser(['name' => 'b']);

        $this->assertSame(['a', 'b'], UjiModel_User::find([$a->getKey(), $b->getKey()])->pluck('name')->all());
        $this->assertSame(['a'], UjiModel_User::findMany([$a->getKey()])->pluck('name')->all());
        $this->assertNull(UjiModel_User::find(999));
        $this->assertSame('a', UjiModel_User::findOrFail($a->getKey())->name);
        $this->assertSame('a', UjiModel_User::where('name', 'a')->firstOrFail()->name);
        $this->assertSame('b', UjiModel_User::find($b->getKey(), ['name'])->name);
        $this->assertSame('a', UjiModel_User::findOr(999, function () use ($a) {
            return $a;
        })->name);
    }

    public function testFindOrFailThrowsModelNotFoundWithTheModelAndIds() {
        try {
            UjiModel_User::findOrFail(42);
            $this->fail('harus melempar');
        } catch (CModel_Exception_ModelNotFoundException $e) {
            $this->assertSame(UjiModel_User::class, $e->getModel());
            $this->assertSame([42], $e->getIds());
        }
    }

    public function testFirstOrCreateAndFirstOrNew() {
        $created = UjiModel_User::firstOrCreate(['email' => 'x@example.test'], ['name' => 'X']);
        $again = UjiModel_User::firstOrCreate(['email' => 'x@example.test'], ['name' => 'Lain']);

        $this->assertTrue($created->wasRecentlyCreated);
        $this->assertFalse($again->wasRecentlyCreated);
        $this->assertSame($created->getKey(), $again->getKey());
        $this->assertSame('X', $again->name);
        $this->assertSame(1, UjiModel_User::count());

        $new = UjiModel_User::firstOrNew(['email' => 'y@example.test'], ['name' => 'Y']);
        $this->assertFalse($new->exists);
        $this->assertSame('Y', $new->name);
        $this->assertSame('y@example.test', $new->email);
    }

    public function testUpdateOrCreate() {
        $a = UjiModel_User::updateOrCreate(['email' => 'x@example.test'], ['name' => 'Pertama']);
        $b = UjiModel_User::updateOrCreate(['email' => 'x@example.test'], ['name' => 'Kedua']);

        $this->assertSame($a->getKey(), $b->getKey());
        $this->assertSame('Kedua', UjiModel_User::find($a->getKey())->name);
        $this->assertSame(1, UjiModel_User::count());
    }

    public function testMassUpdateAndQueryUpdateTouchTheUpdatedColumn() {
        $user = $this->createUser();
        $this->table('uji_user')->update(['updated' => '2000-01-01 00:00:00']);

        $this->assertSame(1, UjiModel_User::where('uji_user_id', $user->getKey())->update(['name' => 'Baru']));

        $row = $this->table('uji_user')->first();
        $this->assertSame('Baru', $row->name);
        $this->assertNotSame('2000-01-01 00:00:00', $row->updated, 'update lewat CModel_Query mengisi kolom updated');
    }

    public function testIncrementAndDecrement() {
        $post = UjiModel_Post::create(['title' => 't', 'price' => 10]);

        $post->increment('price', 5);
        $this->assertEquals(15, $post->price);
        $this->assertEquals(15, $this->table('uji_post')->value('price'));

        $post->decrement('price', 3, ['title' => 'turun']);
        $this->assertEquals(12, $this->table('uji_post')->value('price'));
        $this->assertSame('turun', $this->table('uji_post')->value('title'));
        $this->assertTrue($post->isClean('price'));
        //sama seperti hulu: kolom $extra ikut ditulis tapi tetap dirty di instance
        $this->assertTrue($post->isDirty('title'));
    }

    /**
     * Divergensi: hulu memakai newQueryWithoutScopes() sehingga model terhapus tetap ter-increment;
     * CF memakai query berscope, jadi pada model trashed tidak ada baris yang berubah.
     */
    public function testIncrementOnATrashedModelAffectsNothing() {
        $post = UjiModel_Post::create(['title' => 't', 'price' => 10]);
        $post->delete();

        $this->assertSame(0, $post->increment('price', 5));
        $this->assertEquals(10, $this->table('uji_post')->value('price'));
    }

    public function testTouchUpdatesOnlyTheUpdatedColumn() {
        $user = $this->createUser();
        $this->table('uji_user')->update(['updated' => '2000-01-01 00:00:00', 'created' => '2000-01-01 00:00:00']);
        $user = $user->fresh();

        $this->assertTrue($user->touch());

        $row = $this->table('uji_user')->first();
        $this->assertNotSame('2000-01-01 00:00:00', $row->updated);
        $this->assertSame('2000-01-01 00:00:00', $row->created);
    }

    public function testTimestampsCanBeTurnedOff() {
        $user = new UjiModel_User(['name' => 'tanpa']);
        $user->timestamps = false;
        $user->save();

        $row = $this->table('uji_user')->first();
        $this->assertNull($row->created);
        $this->assertNull($row->updated);
    }

    public function testFreshAndRefresh() {
        $user = $this->createUser(['name' => 'lama']);
        $this->table('uji_user')->update(['name' => 'dari luar']);

        $this->assertSame('lama', $user->name);
        $this->assertSame('dari luar', $user->fresh()->name);
        $this->assertSame('lama', $user->name, 'fresh() tidak mengubah instance');
        $user->name = 'kotor';
        $this->assertSame($user, $user->refresh());
        $this->assertSame('dari luar', $user->name, 'refresh() memuat ulang & membuang perubahan');
        $this->assertFalse($user->isDirty());
        $this->assertNull((new UjiModel_User())->fresh(), 'fresh() pada model yang belum ada = null');
    }

    public function testReplicate() {
        $user = $this->createUser(['name' => 'asli']);
        $user->posts()->create(['title' => 'p']);
        $user->load('posts');

        $copy = $user->replicate();

        $this->assertFalse($copy->exists);
        $this->assertNull($copy->getKey());
        $this->assertSame('asli', $copy->name);
        $this->assertNull($copy->created, 'timestamp tidak disalin');
        $this->assertTrue($copy->relationLoaded('posts'), 'relasi yang dimuat ikut disalin');
        $copy->save();
        $this->assertSame(2, UjiModel_User::count());

        $this->assertNull($user->replicate(['name'])->name);
    }

    public function testIsAndIsNot() {
        $a = $this->createUser();
        $b = $this->createUser();

        $this->assertTrue($a->is(UjiModel_User::find($a->getKey())));
        $this->assertFalse($a->is($b));
        $this->assertTrue($a->isNot($b));
        $this->assertFalse($a->is(null));
        $this->assertFalse($a->is(UjiModel_Country::create(['uji_country_id' => $a->getKey()])), 'tabel beda = bukan model yang sama');
    }

    public function testFillRespectsFillableAndGuarded() {
        $model = new class() extends UjiModel_Base {
            protected $table = 'uji_user';

            protected $fillable = ['name'];
        };

        $model->fill(['name' => 'ok', 'email' => 'tidak']);
        $this->assertSame('ok', $model->name);
        $this->assertNull($model->email);

        $model->forceFill(['email' => 'paksa']);
        $this->assertSame('paksa', $model->email);

        CModel::unguard();
        try {
            $model->fill(['email' => 'lolos']);
            $this->assertSame('lolos', $model->email);
        } finally {
            CModel::reguard();
        }
    }

    public function testFillingAGuardedKeyThrowsWhenTotallyGuarded() {
        $model = new class() extends UjiModel_Base {
            protected $table = 'uji_user';

            protected $guarded = ['*'];
        };
        $this->expectException(CModel_Exception_MassAssignmentException::class);

        $model->fill(['name' => 'x']);
    }

    public function testChunkChunkByIdEachAndCursor() {
        foreach (range(1, 7) as $i) {
            $this->createUser(['name' => 'u' . $i]);
        }

        $chunks = [];
        UjiModel_User::orderBy('uji_user_id')->chunk(3, function ($users) use (&$chunks) {
            $chunks[] = $users->count();
        });
        $this->assertSame([3, 3, 1], $chunks);

        $ids = [];
        UjiModel_User::chunkById(2, function ($users) use (&$ids) {
            foreach ($users as $user) {
                $ids[] = $user->getKey();
            }
        });
        $this->assertSame(range(1, 7), $ids);

        $names = [];
        UjiModel_User::each(function ($user) use (&$names) {
            $names[] = $user->name;

            return $user->name !== 'u3';
        });
        $this->assertSame(['u1', 'u2', 'u3'], $names, 'each() berhenti saat callback mengembalikan false');

        $cursor = UjiModel_User::orderBy('uji_user_id')->cursor();
        $this->assertInstanceOf(CCollection_LazyCollection::class, $cursor);
        $this->assertSame('u1', $cursor->first()->name);
        $this->assertSame(7, $cursor->count());
    }

    public function testPluckValueExistsAndAggregates() {
        $this->createUser(['name' => 'a']);
        $this->createUser(['name' => 'b']);
        UjiModel_Post::create(['title' => 'x', 'price' => 10]);
        UjiModel_Post::create(['title' => 'y', 'price' => 30]);

        $this->assertSame(['a', 'b'], UjiModel_User::orderBy('name')->pluck('name')->all());
        $this->assertSame([1 => 'a', 2 => 'b'], UjiModel_User::orderBy('name')->pluck('name', 'uji_user_id')->all());
        $this->assertSame('a', UjiModel_User::orderBy('name')->value('name'));
        $this->assertTrue(UjiModel_User::where('name', 'a')->exists());
        $this->assertTrue(UjiModel_User::where('name', 'z')->doesntExist());
        $this->assertEquals(40, UjiModel_Post::sum('price'));
        $this->assertEquals(30, UjiModel_Post::max('price'));
        $this->assertEquals(20, UjiModel_Post::avg('price'));
    }

    public function testDestroyByKeysSoftDeletesAndReturnsTheCount() {
        $a = $this->createUser();
        $b = $this->createUser();
        $this->createUser();

        $this->assertSame(2, UjiModel_User::destroy($a->getKey(), $b->getKey()));
        $this->assertSame(1, UjiModel_User::count());
        $this->assertSame(3, $this->table('uji_user')->count());
        $this->assertSame(0, UjiModel_User::destroy(999));
    }

    public function testInsertGetIdIsUsedForIncrementingKeysAndSetOnTheModel() {
        $a = $this->createUser();
        $b = $this->createUser();

        $this->assertSame(1, $a->getKey());
        $this->assertSame(2, $b->getKey());
        $this->assertSame('int', $a->getKeyType());
        $this->assertTrue($a->getIncrementing());
    }

    public function testNonIncrementingStringKey() {
        $model = new class() extends UjiModel_Base {
            protected $table = 'uji_country';

            protected $primaryKey = 'name';

            protected $keyType = 'string';

            public $incrementing = false;
        };

        $saved = $model->newInstance(['name' => 'ID']);
        $saved->save();

        $this->assertSame('ID', $saved->getKey());
        $this->assertSame('ID', $model->newQuery()->find('ID')->name);
    }

    public function testCreatedAndUpdatedAreCarbonInstances() {
        $user = $this->createUser();

        $this->assertInstanceOf(CCarbon::class, $user->created);
        $this->assertInstanceOf(CCarbon::class, $user->fresh()->updated);
        $this->assertSame('created', $user->getCreatedAtColumn());
        $this->assertSame('updated', $user->getUpdatedAtColumn());
    }
}
