<?php

require_once __DIR__ . '/UjiModelSupport.php';

/**
 * CModel_Query di atas basis data nyata: pengurutan bawaan CF (latest/oldest = kolom created),
 * whereKey/find dengan kunci <tabel>_id, sole/firstWhere/valueOrFail, when/unless, paginasi,
 * lazy/chunkMap, hydrate/fromQuery, macro, insert/upsert statis, withCasts, whereBelongsTo/whereRelation
 * dan agregat relasi (withSum/withMax/withExists).
 */
class ModelQueryIntegrationTest extends UjiModel_IntegrationTestCase {
    /**
     * @return void
     */
    protected function seed() {
        $this->createUser(['name' => 'a', 'is_active' => true]);
        $this->createUser(['name' => 'b', 'is_active' => false]);
        $this->createUser(['name' => 'c', 'is_active' => true]);
        $this->table('uji_user')->where('name', 'a')->update(['created' => '2024-01-01 00:00:00']);
        $this->table('uji_user')->where('name', 'b')->update(['created' => '2024-03-01 00:00:00']);
        $this->table('uji_user')->where('name', 'c')->update(['created' => '2024-02-01 00:00:00']);
    }

    public function testLatestAndOldestDefaultToTheCreatedColumn() {
        $this->seed();

        $this->assertStringContainsString('order by "created" desc', UjiModel_User::latest()->toSql(), 'CF: created, bukan created_at');
        $this->assertSame(['b', 'c', 'a'], UjiModel_User::latest()->pluck('name')->all());
        $this->assertSame(['a', 'c', 'b'], UjiModel_User::oldest()->pluck('name')->all());
        $this->assertSame(['c', 'b', 'a'], UjiModel_User::latest('name')->pluck('name')->all());
        $this->assertSame(['c', 'b', 'a'], UjiModel_User::orderByDesc('name')->pluck('name')->all());
    }

    public function testWhereKeyAndFindUseTheTableKeyName() {
        $this->seed();

        $this->assertStringContainsString('"uji_user"."uji_user_id" = ?', UjiModel_User::whereKey(1)->toSql());
        $this->assertSame(['a', 'c'], UjiModel_User::whereKey([1, 3])->pluck('name')->all());
        $this->assertSame(['b'], UjiModel_User::whereKeyNot([1, 3])->pluck('name')->all());
        $this->assertSame('b', UjiModel_User::find(2)->name);
        $this->assertSame(['b'], UjiModel_User::whereKey(UjiModel_User::find(2))->pluck('name')->all(), 'whereKey() menerima instance model (bukan whereIn atas seluruh atributnya)');
        $this->assertSame(['a', 'c'], UjiModel_User::whereKeyNot(UjiModel_User::find(2))->pluck('name')->all());
    }

    public function testFirstWhereSoleValueOrFail() {
        $this->seed();

        $this->assertSame('b', UjiModel_User::firstWhere('name', 'b')->name);
        $this->assertNull(UjiModel_User::firstWhere('name', 'z'));
        $this->assertSame('b', UjiModel_User::where('name', 'b')->sole()->name);
        $this->assertSame('b', UjiModel_User::where('name', 'b')->soleValue('name'));
        $this->assertSame('a', UjiModel_User::oldest()->valueOrFail('name'));

        try {
            UjiModel_User::where('is_active', 1)->sole();
            $this->fail('dua baris harus melempar');
        } catch (CDatabase_Exception_MultipleRecordsFoundException $e) {
            $this->assertSame(2, $e->getCount());
        }
        //divergensi: sole() pada CModel_Query melempar RecordsNotFoundException (trait builder), bukan
        //ModelNotFoundException seperti hulu; sole() pada relasi yang melempar ModelNotFound
        $this->expectException(CDatabase_Exception_RecordsNotFoundException::class);
        UjiModel_User::where('name', 'z')->sole();
    }

    public function testSoleOnARelationThrowsModelNotFound() {
        $user = $this->createUser();

        $this->expectException(CModel_Exception_ModelNotFoundException::class);
        $user->posts()->sole();
    }

    public function testValueOrFailThrowsWhenNothingMatches() {
        $this->expectException(CModel_Exception_ModelNotFoundException::class);

        UjiModel_User::where('name', 'z')->valueOrFail('name');
    }

    public function testWhenAndUnless() {
        $this->seed();

        $names = function ($filter) {
            return UjiModel_User::query()->when($filter, function ($q, $value) {
                $q->where('name', $value);
            }, function ($q) {
                $q->where('is_active', 1);
            })->orderBy('name')->pluck('name')->all();
        };
        $this->assertSame(['b'], $names('b'));
        $this->assertSame(['a', 'c'], $names(null), 'default callback saat nilai falsy');
        $this->assertSame(['b'], UjiModel_User::query()->unless(false, function ($q) {
            $q->where('name', 'b');
        })->pluck('name')->all());
    }

    public function testPaginateSimplePaginateAndCursorPaginate() {
        $this->seed();

        $page = UjiModel_User::orderBy('name')->paginate(2, ['*'], 'halaman', 2);
        $this->assertInstanceOf(CPagination_LengthAwarePaginator::class, $page);
        $this->assertSame(3, $page->total());
        $this->assertSame(2, $page->lastPage());
        $this->assertSame(['c'], $page->getCollection()->pluck('name')->all());
        $this->assertInstanceOf(UjiModel_User::class, $page->items()[0]);

        $simple = UjiModel_User::orderBy('name')->simplePaginate(2);
        $this->assertInstanceOf(CPagination_Paginator::class, $simple);
        $this->assertSame(['a', 'b'], $simple->getCollection()->pluck('name')->all());
        $this->assertTrue($simple->hasMorePages());

        // test lain (DatabaseQueryBuilderPortTest) meninggalkan resolver cursor statis; netralkan untuk request "halaman pertama"
        CPagination_CursorPaginator::currentCursorResolver(function () {
            return null;
        });
        $cursor = UjiModel_User::orderBy('name')->cursorPaginate(2);
        $this->assertInstanceOf(CPagination_CursorPaginator::class, $cursor);
        $this->assertSame(['a', 'b'], $cursor->getCollection()->pluck('name')->all());
        $this->assertNotNull($cursor->nextCursor());
        $next = UjiModel_User::orderBy('name')->cursorPaginate(2, ['*'], 'cursor', $cursor->nextCursor());
        $this->assertSame(['c'], $next->getCollection()->pluck('name')->all());
    }

    public function testLazyLazyByIdAndChunkMap() {
        $this->seed();

        $lazy = UjiModel_User::orderBy('name')->lazy(2);
        $this->assertInstanceOf(CCollection_LazyCollection::class, $lazy);
        $this->assertSame(['a', 'b', 'c'], $lazy->pluck('name')->all());
        $this->assertSame([1, 2, 3], UjiModel_User::lazyById(1)->map->getKey()->all());
        $this->assertSame(['A', 'B', 'C'], UjiModel_User::orderBy('name')->chunkMap(function ($user) {
            return strtoupper($user->name);
        }, 2)->all());
    }

    public function testHydrateFromQueryAndGetModels() {
        $this->seed();

        $models = UjiModel_User::hydrate([['uji_user_id' => 9, 'name' => 'hidrasi']]);
        $this->assertInstanceOf(CModel_Collection::class, $models);
        $this->assertTrue($models->first()->exists);
        $this->assertFalse($models->first()->isDirty());
        $this->assertSame('hidrasi', $models->first()->name);

        $raw = UjiModel_User::fromQuery('select * from uji_user where name = ?', ['b']);
        $this->assertSame(['b'], $raw->pluck('name')->all());
        $this->assertInstanceOf(UjiModel_User::class, $raw->first());

        $plain = UjiModel_User::orderBy('name')->getModels(['name']);
        $this->assertIsArray($plain);
        $this->assertCount(3, $plain);
        $this->assertNull($plain[0]->email, 'kolom yang tidak diminta tidak ada');
    }

    public function testToBaseReturnsThePlainQueryBuilderWithScopesApplied() {
        $this->seed();
        UjiModel_User::find(2)->delete();

        $base = UjiModel_User::query()->toBase();
        $this->assertInstanceOf(CDatabase_Query_Builder::class, $base);
        $this->assertNotInstanceOf(CModel_Query::class, $base);
        $this->assertSame(2, $base->count(), 'scope status ikut diterapkan');
        $this->assertIsObject($base->first(), 'hasil toBase() adalah baris mentah, bukan model');
    }

    public function testQueryMacro() {
        $this->seed();
        CModel_Query::macro('bernama', function ($name) {
            return $this->where('name', $name);
        });

        $this->assertTrue(CModel_Query::hasGlobalMacro('bernama'));
        $this->assertFalse(UjiModel_User::query()->hasMacro('bernama'), 'hasMacro() hanya untuk macro lokal (mis. withTrashed dari scope)');
        $this->assertTrue(UjiModel_User::query()->hasMacro('withTrashed'));
        $this->assertSame(['b'], UjiModel_User::query()->bernama('b')->pluck('name')->all());
    }

    public function testStaticInsertAndUpsertBypassModelEventsAndTimestamps() {
        $this->assertTrue(UjiModel_User::insert([['name' => 'x'], ['name' => 'y']]));
        $this->assertSame(2, $this->table('uji_user')->count());
        $this->assertNull($this->table('uji_user')->value('created'), 'insert massal tidak mengisi timestamp');

        $this->assertSame(1, UjiModel_Tag::insertGetId(['name' => 't']));
        UjiModel_Tag::upsert([['uji_tag_id' => 1, 'name' => 'diganti'], ['uji_tag_id' => 2, 'name' => 'baru']], ['uji_tag_id'], ['name']);
        $this->assertSame(['diganti', 'baru'], UjiModel_Tag::orderBy('uji_tag_id')->pluck('name')->all());
    }

    public function testWithCastsOverridesCastsForTheQuery() {
        $this->createUser(['name' => 'a', 'is_active' => true]);

        $this->assertSame('1', (string) UjiModel_User::withCasts(['is_active' => 'string'])->first()->is_active);
        $this->assertTrue(UjiModel_User::first()->is_active, 'cast asli tidak berubah');
    }

    public function testWhereBelongsToAndWhereRelation() {
        $user = $this->createUser(['name' => 'pemilik']);
        $user->posts()->create(['title' => 'punya', 'is_published' => true]);
        $this->createUser(['name' => 'lain'])->posts()->create(['title' => 'lain', 'is_published' => false]);

        $this->assertSame(['punya'], UjiModel_Post::whereBelongsTo($user)->pluck('title')->all());
        $this->assertSame(['pemilik'], UjiModel_User::whereRelation('posts', 'is_published', 1)->pluck('name')->all());
        $this->assertSame(['pemilik', 'lain'], UjiModel_User::whereRelation('posts', 'title', 'punya')->orWhereRelation('posts', 'title', 'lain')->orderBy('uji_user_id')->pluck('name')->all());
    }

    public function testRelationAggregatesWithSumMaxExists() {
        $user = $this->createUser(['name' => 'a']);
        $user->posts()->create(['title' => 'p1', 'price' => 10]);
        $user->posts()->create(['title' => 'p2', 'price' => 30]);
        $this->createUser(['name' => 'b']);

        $rows = UjiModel_User::withSum('posts', 'price')->withMax('posts', 'price')->withExists('posts')->orderBy('uji_user_id')->get();
        $this->assertEquals(40, $rows[0]->posts_sum_price);
        $this->assertEquals(30, $rows[0]->posts_max_price);
        $this->assertTrue((bool) $rows[0]->posts_exists);
        $this->assertNull($rows[1]->posts_sum_price);
        $this->assertFalse((bool) $rows[1]->posts_exists);

        $loaded = UjiModel_User::find($user->getKey())->loadSum('posts', 'price')->loadCount('posts');
        $this->assertEquals(40, $loaded->posts_sum_price);
        $this->assertEquals(2, $loaded->posts_count);
    }

    public function testWithWhereHasLoadsOnlyTheMatchingRelated() {
        $user = $this->createUser(['name' => 'a']);
        $user->posts()->create(['title' => 'cocok']);
        $user->posts()->create(['title' => 'tidak']);
        $this->createUser(['name' => 'b']);

        $users = UjiModel_User::withWhereHas('posts', function ($q) {
            $q->where('title', 'cocok');
        })->get();

        $this->assertSame(['a'], $users->pluck('name')->all());
        $this->assertSame(['cocok'], $users->first()->posts->pluck('title')->all());
    }

    public function testForwardedBuilderMethodsReturnModels() {
        $this->seed();

        $this->assertInstanceOf(UjiModel_User::class, UjiModel_User::whereIn('name', ['a', 'b'])->orderBy('name')->first());
        $this->assertSame(['a', 'c'], UjiModel_User::whereNotIn('name', ['b'])->orderBy('name')->pluck('name')->all());
        $this->assertSame(['a'], UjiModel_User::whereDate('created', '2024-01-01')->pluck('name')->all());
        $this->assertSame(['a', 'c'], UjiModel_User::whereBetween('created', ['2024-01-01', '2024-02-15'])->orderBy('name')->pluck('name')->all());
        $this->assertSame(3, UjiModel_User::whereNull('email')->orWhereNotNull('email')->count());
        $this->assertSame(['b'], UjiModel_User::where('name', 'like', 'b%')->pluck('name')->all());
        $this->assertSame(['a', 'b'], UjiModel_User::orderBy('name')->limit(2)->pluck('name')->all());
        $this->assertSame(['c'], UjiModel_User::orderBy('name')->offset(2)->limit(5)->pluck('name')->all());
        $this->assertSame(['c'], UjiModel_User::orderBy('name')->skip(2)->take(5)->pluck('name')->all());
        $this->assertEquals(2, UjiModel_User::selectRaw('count(*) as jumlah')->where('is_active', 1)->first()->jumlah);
        $this->assertSame([1, 2], UjiModel_User::query()->select('is_active')->distinct()->orderBy('is_active')->get()->map(function ($u) {
            return $u->is_active ? 2 : 1;
        })->all());
    }

    public function testGroupByHavingThroughTheModel() {
        $u = $this->createUser(['name' => 'a']);
        $u->posts()->create(['title' => 'x', 'price' => 5]);
        $u->posts()->create(['title' => 'y', 'price' => 7]);
        $this->createUser(['name' => 'b'])->posts()->create(['title' => 'z', 'price' => 1]);

        $rows = UjiModel_Post::selectRaw('uji_user_id, sum(price) as total')->groupBy('uji_user_id')->having('total', '>', 5)->get();

        $this->assertCount(1, $rows);
        $this->assertEquals(12, $rows->first()->total);
        $this->assertEquals($u->getKey(), $rows->first()->uji_user_id);
    }
}
