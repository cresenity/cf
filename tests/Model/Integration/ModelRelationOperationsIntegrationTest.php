<?php

require_once __DIR__ . '/UjiModelSupport.php';

/**
 * Operasi lewat relasi di basis data nyata: firstOrCreate/updateOrCreate/firstOrNew/findOrNew pada
 * hasMany, saveMany/createMany, sync varian pada belongsToMany (withoutDetaching, withPivotValues,
 * withPivotValue), orderByPivot, whereHas bersarang/orWhereHas, paginasi & chunk lewat relasi,
 * hasOneOfMany (latestOfMany), dan withDefault closure.
 */
class ModelRelationOperationsIntegrationTest extends UjiModel_IntegrationTestCase {
    public function testFirstOrCreateUpdateOrCreateFirstOrNewFindOrNewOnHasMany() {
        $user = $this->createUser();

        $post = $user->posts()->firstOrCreate(['title' => 'a'], ['body' => 'isi']);
        $this->assertTrue($post->wasRecentlyCreated);
        $this->assertEquals($user->getKey(), $post->uji_user_id, 'foreign key diisi otomatis');
        $this->assertSame('isi', $post->body);
        $this->assertFalse($user->posts()->firstOrCreate(['title' => 'a'])->wasRecentlyCreated);

        $updated = $user->posts()->updateOrCreate(['title' => 'a'], ['body' => 'baru']);
        $this->assertSame($post->getKey(), $updated->getKey());
        $this->assertSame('baru', $this->table('uji_post')->value('body'));
        $this->assertSame(1, $user->posts()->count());

        $new = $user->posts()->firstOrNew(['title' => 'b']);
        $this->assertFalse($new->exists);
        $this->assertEquals($user->getKey(), $new->uji_user_id);
        $this->assertFalse($user->posts()->findOrNew(999)->exists);
        $this->assertSame('a', $user->posts()->findOrNew($post->getKey())->title);
    }

    public function testSaveManyCreateManyAndMakeOnHasMany() {
        $user = $this->createUser();

        $saved = $user->posts()->saveMany([new UjiModel_Post(['title' => 'x']), new UjiModel_Post(['title' => 'y'])]);
        $this->assertCount(2, $saved);
        $this->assertSame(2, $user->posts()->count());

        $made = $user->posts()->make(['title' => 'z']);
        $this->assertFalse($made->exists);
        $this->assertEquals($user->getKey(), $made->uji_user_id);
        $this->assertSame(2, $user->posts()->count(), 'make() tidak menyimpan');
    }

    public function testSyncWithoutDetachingAndSyncWithPivotValues() {
        $post = UjiModel_Post::create(['title' => 'p']);
        $a = UjiModel_Tag::create(['name' => 'a']);
        $b = UjiModel_Tag::create(['name' => 'b']);
        $c = UjiModel_Tag::create(['name' => 'c']);
        $post->tags()->attach($a->getKey());

        $result = $post->tags()->syncWithoutDetaching([$b->getKey()]);
        $this->assertSame([$b->getKey()], $result['attached']);
        $this->assertSame([], $result['detached']);
        $this->assertSame(['a', 'b'], $post->tags()->orderBy('name')->pluck('name')->all());

        $post->tags()->syncWithPivotValues([$b->getKey(), $c->getKey()], ['note' => 'sama']);
        $this->assertSame(['b', 'c'], $post->tags()->orderBy('name')->pluck('name')->all());
        $this->assertSame(['sama', 'sama'], $post->tags()->orderBy('name')->get()->pluck('pivot.note')->all());

        $result = $post->tags()->sync([$b->getKey() => ['note' => 'diubah'], $c->getKey()]);
        $this->assertSame([$b->getKey()], array_values($result['updated']), 'pivot yang nilainya berubah masuk daftar updated');
        $this->assertSame('diubah', $post->tags()->find($b->getKey())->pivot->note);
    }

    public function testWithPivotValueAndOrderByPivot() {
        $post = UjiModel_Post::create(['title' => 'p']);
        $a = UjiModel_Tag::create(['name' => 'a']);
        $b = UjiModel_Tag::create(['name' => 'b']);
        $post->tags()->attach($a->getKey(), ['note' => 'z-akhir']);
        $post->tags()->attach($b->getKey(), ['note' => 'a-awal']);

        $this->assertSame(['b', 'a'], $post->tags()->orderByPivot('note')->pluck('name')->all());
        $this->assertSame(['a', 'b'], $post->tags()->orderByPivot('note', 'desc')->pluck('name')->all());

        $relation = new class() extends UjiModel_Post {
            public function penting() {
                return $this->belongsToMany(UjiModel_Tag::class, 'post_tag', 'uji_post_id', 'uji_tag_id')->withPivotValue('note', 'penting');
            }
        };
        $p2 = $relation->newQuery()->create(['title' => 'p2']);
        $p2->penting()->attach($a->getKey());
        $this->assertSame('penting', $this->table('post_tag')->where('uji_post_id', $p2->getKey())->value('note'), 'withPivotValue mengisi kolom saat attach');
        $this->assertSame(1, $p2->penting()->count());
        $this->assertSame(0, $relation->newQuery()->find($post->getKey())->penting()->count(), 'dan menyaring saat membaca');
    }

    public function testDetachAllAndDetachWithModels() {
        $post = UjiModel_Post::create(['title' => 'p']);
        $tags = c::collect(['a', 'b', 'c'])->map(function ($name) {
            return UjiModel_Tag::create(['name' => $name]);
        });
        $post->tags()->attach($tags->pluck('uji_tag_id')->all());

        $this->assertSame(1, $post->tags()->detach($tags[0]));
        $this->assertSame(2, $post->tags()->detach());
        $this->assertSame(0, $this->table('post_tag')->count());
    }

    public function testNestedWhereHasAndOrWhereHas() {
        $a = $this->createUser(['name' => 'a']);
        $a->posts()->create(['title' => 'pa'])->comments()->create(['body' => 'cocok']);
        $b = $this->createUser(['name' => 'b']);
        $b->posts()->create(['title' => 'pb'])->comments()->create(['body' => 'lain']);
        $this->createUser(['name' => 'c']);

        $this->assertSame(['a'], UjiModel_User::whereHas('posts.comments', function ($q) {
            $q->where('body', 'cocok');
        })->pluck('name')->all());
        $this->assertSame(['a', 'b'], UjiModel_User::has('posts.comments')->orderBy('name')->pluck('name')->all());
        $this->assertSame(['a', 'c'], UjiModel_User::where('name', 'c')->orWhereHas('posts', function ($q) {
            $q->where('title', 'pa');
        })->orderBy('name')->pluck('name')->all());
        $this->assertSame(['b', 'c'], UjiModel_User::whereDoesntHave('posts', function ($q) {
            $q->where('title', 'pa');
        })->orderBy('name')->pluck('name')->all());
        $this->assertSame(['a', 'c'], UjiModel_User::where('name', 'a')->orWhereDoesntHave('posts')->orderBy('name')->pluck('name')->all());
    }

    public function testHasWithCountsAndComparisonOperators() {
        $a = $this->createUser(['name' => 'a']);
        $a->posts()->createMany([['title' => '1'], ['title' => '2'], ['title' => '3']]);
        $b = $this->createUser(['name' => 'b']);
        $b->posts()->create(['title' => '1']);

        $this->assertSame(['a'], UjiModel_User::has('posts', '>', 1)->pluck('name')->all());
        $this->assertSame(['b'], UjiModel_User::has('posts', '=', 1)->pluck('name')->all());
        $this->assertSame(['a'], UjiModel_User::has('posts', '>=', 3)->pluck('name')->all());
        $this->assertSame(['a', 'b'], UjiModel_User::has('posts', '<', 5)->orderBy('name')->pluck('name')->all());
        $this->assertSame(['b'], UjiModel_User::whereHas('posts', null, '<', 2)->pluck('name')->all());
    }

    public function testPaginateChunkAndPluckThroughARelation() {
        $user = $this->createUser();
        foreach (range(1, 5) as $i) {
            $user->posts()->create(['title' => 'p' . $i]);
        }

        $page = $user->posts()->orderBy('uji_post_id')->paginate(2, ['*'], 'page', 2);
        $this->assertSame(5, $page->total());
        $this->assertSame(['p3', 'p4'], $page->getCollection()->pluck('title')->all());

        $seen = [];
        $user->posts()->orderBy('uji_post_id')->chunk(2, function ($posts) use (&$seen) {
            $seen[] = $posts->count();
        });
        $this->assertSame([2, 2, 1], $seen);
        $this->assertSame(['p1', 'p2', 'p3', 'p4', 'p5'], $user->posts()->orderBy('uji_post_id')->pluck('title')->all());
        $this->assertTrue($user->posts()->where('title', 'p3')->exists());
        $this->assertSame(1, $user->posts()->where('title', 'p3')->update(['body' => 'x']));
        $this->assertSame(1, $user->posts()->where('title', 'p3')->delete());
        $this->assertSame(4, $user->posts()->count());
    }

    public function testLatestOfManyAndOfMany() {
        $user = $this->createUser();
        $first = $user->posts()->create(['title' => 'lama', 'price' => 1]);
        $second = $user->posts()->create(['title' => 'baru', 'price' => 9]);
        $this->table('uji_post')->where('uji_post_id', $first->getKey())->update(['created' => '2020-01-01 00:00:00']);
        $this->table('uji_post')->where('uji_post_id', $second->getKey())->update(['created' => '2021-01-01 00:00:00']);
        $model = new class() extends UjiModel_User {
            public function latestPost() {
                return $this->hasOne(UjiModel_Post::class)->latestOfMany('created');
            }

            public function cheapestPost() {
                return $this->hasOne(UjiModel_Post::class)->ofMany('price', 'min');
            }
        };

        $found = $model->newQuery()->find($user->getKey());
        $this->assertSame('baru', $found->latestPost->title);
        $this->assertSame('lama', $found->cheapestPost->title);
        $this->assertSame('baru', $model->newQuery()->with('latestPost')->find($user->getKey())->latestPost->title);
    }

    public function testWithDefaultClosureAndBelongsToDefault() {
        $post = UjiModel_Post::create(['title' => 'tanpa pemilik']);
        $model = new class() extends UjiModel_Post {
            public function user() {
                return $this->belongsTo(UjiModel_User::class)->withDefault(function ($user, $post) {
                    $user->name = 'tamu untuk ' . $post->title;
                });
            }
        };

        $found = $model->newQuery()->find($post->getKey());
        $this->assertInstanceOf(UjiModel_User::class, $found->user);
        $this->assertFalse($found->user->exists);
        $this->assertSame('tamu untuk tanpa pemilik', $found->user->name);
        $this->assertSame('tamu untuk tanpa pemilik', $model->newQuery()->with('user')->find($post->getKey())->user->name, 'eager load juga memberi default');
    }

    public function testRelationCountsAreNotAffectedByEagerLoadConstraintsOnOtherRelations() {
        $user = $this->createUser();
        $user->posts()->create(['title' => 'a']);
        $user->posts()->create(['title' => 'b']);

        $loaded = UjiModel_User::with(['posts' => function ($q) {
            $q->where('title', 'a');
        }])->withCount('posts')->first();

        $this->assertSame(1, $loaded->posts->count(), 'relasi ter-constraint');
        $this->assertEquals(2, $loaded->posts_count, 'withCount tanpa constraint');
    }
}
