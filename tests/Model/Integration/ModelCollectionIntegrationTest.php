<?php

require_once __DIR__ . '/UjiModelSupport.php';

/**
 * CModel_Collection dengan model nyata: find/contains/modelKeys berbasis kunci, fresh/load/loadCount/
 * loadMissing lewat satu query, diff/intersect/unique/only/except, makeHidden/append, toQuery, dan
 * pergantian jenis koleksi saat isinya bukan model lagi.
 */
class ModelCollectionIntegrationTest extends UjiModel_IntegrationTestCase {
    /**
     * @return CModel_Collection
     */
    protected function users() {
        $this->createUser(['name' => 'a']);
        $this->createUser(['name' => 'b']);
        $this->createUser(['name' => 'c']);

        return UjiModel_User::orderBy('uji_user_id')->get();
    }

    public function testGetReturnsAModelCollection() {
        $users = $this->users();

        $this->assertInstanceOf(CModel_Collection::class, $users);
        $this->assertInstanceOf(CCollection::class, $users);
        $this->assertCount(3, $users);
        $this->assertSame([1, 2, 3], $users->modelKeys());
        $this->assertInstanceOf(CModel_Collection::class, UjiModel_User::where('name', 'z')->get());
        $this->assertTrue(UjiModel_User::where('name', 'z')->get()->isEmpty());
    }

    public function testFindAndContainsWorkByKeyOrModel() {
        $users = $this->users();
        $b = UjiModel_User::find(2);

        $this->assertSame('b', $users->find(2)->name);
        $this->assertSame('b', $users->find($b)->name);
        $this->assertSame('bawaan', $users->find(99, 'bawaan'));
        $this->assertSame([1, 3], array_values($users->find([1, 3])->modelKeys()), 'find(array) memfilter dengan kunci asal dipertahankan');
        $this->assertTrue($users->contains(2));
        $this->assertTrue($users->contains($b));
        $this->assertTrue($users->contains('name', 'c'));
        $this->assertFalse($users->contains(99));
        $this->assertTrue($users->contains(function ($user) {
            return $user->name === 'a';
        }));
    }

    public function testDiffIntersectUniqueOnlyExcept() {
        $users = $this->users();
        $subset = UjiModel_User::whereIn('uji_user_id', [1, 2])->get();

        $this->assertSame([3], $users->diff($subset)->modelKeys());
        $this->assertSame([1, 2], $users->intersect($subset)->modelKeys());
        $this->assertSame([1, 2, 3], $users->merge($subset)->modelKeys(), 'merge tidak menggandakan kunci yang sama');
        $this->assertSame([1, 2, 3], $users->concat([UjiModel_User::find(1)])->unique()->modelKeys());
        $this->assertSame([2], $users->only([2])->modelKeys());
        $this->assertSame([1, 3], $users->except([2])->modelKeys());
        $this->assertSame([1, 2, 3], $users->only(null)->modelKeys());
    }

    public function testFreshReloadsEveryModelInOneQuery() {
        $users = $this->users();
        $this->table('uji_user')->update(['name' => 'ubah']);
        UjiModel_User::find(3)->delete();
        $queries = [];
        $this->connection->listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $fresh = $users->fresh();

        $this->assertCount(1, $queries);
        $this->assertSame(['ubah', 'ubah', 'ubah'], $fresh->pluck('name')->all());
        $this->assertSame([1, 2, 3], $fresh->modelKeys(), 'fresh() memakai query tanpa scope, jadi baris status 0 tetap ikut (sama seperti hulu)');
        $this->assertTrue($fresh[2]->trashed());
        $this->assertSame(['a', 'b', 'c'], $users->pluck('name')->all(), 'koleksi asal tidak berubah');
    }

    public function testLoadLoadMissingAndLoadCountOnTheCollection() {
        $users = $this->users();
        $users[0]->posts()->create(['title' => 'p1']);
        $users[0]->posts()->create(['title' => 'p2']);
        $queries = [];
        $this->connection->listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $users->load('posts');
        $this->assertCount(1, $queries, 'satu query untuk semua induk');
        $this->assertSame(2, $users[0]->posts->count());
        $this->assertSame(0, $users[2]->posts->count());
        $this->assertTrue($users[2]->relationLoaded('posts'));

        $users->loadMissing('posts');
        $this->assertCount(1, $queries, 'sudah dimuat: tidak ada query baru');

        $users->loadCount('posts');
        $this->assertEquals([2, 0, 0], $users->pluck('posts_count')->all());
        $users->loadExists('posts');
        $this->assertEquals([true, false, false], $users->map(function ($u) {
            return (bool) $u->posts_exists;
        })->all());
    }

    public function testMakeHiddenMakeVisibleAndAppendApplyToEveryModel() {
        $users = $this->users();

        $users->makeVisible('email')->makeHidden('name');
        $this->assertArrayHasKey('email', $users->toArray()[0]);
        $this->assertArrayNotHasKey('name', $users->toArray()[1]);

        $users->append('display_name');
        $this->assertSame('B', $users->toArray()[1]['display_name']);
        $this->assertSame([1, 2, 3], array_column($users->toArray(), 'uji_user_id'));
    }

    public function testToQueryBuildsAWhereInOnTheKeys() {
        $users = $this->users()->only([1, 3]);

        $query = $users->toQuery();

        $this->assertInstanceOf(CModel_Query::class, $query);
        $this->assertStringContainsString('"uji_user"."uji_user_id" in (?, ?)', $query->toSql());
        $this->assertSame(2, $query->update(['name' => 'massal']));
        $this->assertSame(['massal', 'b', 'massal'], UjiModel_User::orderBy('uji_user_id')->pluck('name')->all());
    }

    public function testToQueryOnAnEmptyCollectionThrows() {
        $this->expectException(LogicException::class);

        (new CModel_Collection())->toQuery();
    }

    public function testMapAndPluckDropToABaseCollectionWhenItemsAreNotModels() {
        $users = $this->users();

        $names = $users->map(function ($user) {
            return $user->name;
        });
        $this->assertNotInstanceOf(CModel_Collection::class, $names);
        $this->assertInstanceOf(CCollection::class, $names);
        $this->assertSame(['a', 'b', 'c'], $names->all());

        $stillModels = $users->map(function ($user) {
            return $user;
        });
        $this->assertInstanceOf(CModel_Collection::class, $stillModels);
        $this->assertInstanceOf(CCollection::class, $users->pluck('name'));
        $this->assertInstanceOf(CCollection::class, $users->keys());
        $this->assertInstanceOf(CCollection::class, $users->zip([1, 2, 3]));
        $this->assertInstanceOf(CModel_Collection::class, $users->filter(function ($u) {
            return $u->name !== 'a';
        }));
        $this->assertInstanceOf(CModel_Collection::class, $users->reverse());
    }

    public function testGetDictionaryAndKeyBy() {
        $users = $this->users();

        $dictionary = $users->getDictionary();
        $this->assertSame([1, 2, 3], array_keys($dictionary));
        $this->assertSame('b', $dictionary[2]->name);
        $this->assertSame('c', $users->keyBy('name')->get('c')->name);
        $this->assertSame(['a', 'b', 'c'], $users->sortByDesc('name')->reverse()->pluck('name')->values()->all());
    }

    public function testQueueableIdsAndClass() {
        $users = $this->users();

        $this->assertSame([1, 2, 3], $users->getQueueableIds());
        $this->assertSame(UjiModel_User::class, $users->getQueueableClass());
        $this->assertSame(UjiModel_IntegrationTestCase::CONNECTION, $users->getQueueableConnection());
        $this->assertSame([], $users->getQueueableRelations());
        $users->load('posts');
        $this->assertSame(['posts'], $users->getQueueableRelations());
    }

    public function testMixedModelClassesInOneCollection() {
        $this->users();
        $tag = UjiModel_Tag::create(['name' => 't']);
        $mixed = UjiModel_User::get()->push($tag);

        $this->expectException(LogicException::class);
        $mixed->getQueueableClass();
    }
}
