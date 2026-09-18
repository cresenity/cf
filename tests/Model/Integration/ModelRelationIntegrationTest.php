<?php

require_once __DIR__ . '/UjiModelSupport.php';

/**
 * Relasi CModel di atas basis data nyata: konvensi kunci CF (<tabel>_id di kedua sisi), eager load,
 * lazy load, whereHas/withCount, belongsToMany + pivot, morph, hasManyThrough — padanan bagian
 * relasi DatabaseEloquentIntegrationTest hulu.
 */
class ModelRelationIntegrationTest extends UjiModel_IntegrationTestCase {
    /**
     * @return UjiModel_User
     */
    protected function seedUserWithPosts() {
        $user = $this->createUser(['name' => 'penulis']);
        $user->posts()->create(['title' => 'pertama']);
        $user->posts()->create(['title' => 'kedua']);
        $this->createUser(['name' => 'kosong']);

        return $user;
    }

    public function testForeignKeyConventionIsThePrimaryKeyName() {
        $user = new UjiModel_User();

        $this->assertSame('uji_user_id', $user->getForeignKey(), 'CF: foreign key = nama kunci utama, bukan <kelas>_id');
        $this->assertSame('uji_user_id', $user->posts()->getForeignKeyName());
        $this->assertSame('uji_user_id', $user->posts()->getLocalKeyName());
        $this->assertSame('uji_country_id', $user->country()->getForeignKeyName());
        $this->assertSame('uji_country_id', $user->country()->getOwnerKeyName());
        $this->assertSame('post_tag', (new UjiModel_Post())->tags()->getTable(), 'pivot = basename kelas (sesudah prefix_) diurutkan, bukan nama tabel');
    }

    public function testHasManyCreateSaveAndLazyLoad() {
        $user = $this->seedUserWithPosts();

        $this->assertSame(2, $user->posts()->count());
        $this->assertSame(['pertama', 'kedua'], $user->posts->pluck('title')->all());
        $this->assertTrue($user->relationLoaded('posts'));
        $this->assertEquals($user->getKey(), $user->posts->first()->uji_user_id);

        $post = new UjiModel_Post(['title' => 'ketiga']);
        $user->posts()->save($post);
        $this->assertSame($user->getKey(), $post->uji_user_id);
        $this->assertSame(3, UjiModel_Post::where('uji_user_id', $user->getKey())->count());

        $many = $user->posts()->createMany([['title' => 'a'], ['title' => 'b']]);
        $this->assertCount(2, $many);
        $this->assertSame(5, $user->posts()->count());
    }

    public function testBelongsToAssociateDissociateAndLazyLoad() {
        $country = UjiModel_Country::create(['name' => 'ID']);
        $user = $this->createUser();

        $user->country()->associate($country)->save();
        $this->assertSame($country->getKey(), $user->uji_country_id);
        $this->assertSame('ID', UjiModel_User::find($user->getKey())->country->name);

        $user->country()->dissociate()->save();
        $this->assertNull($user->uji_country_id);
        $this->assertNull(UjiModel_User::find($user->getKey())->country);
    }

    public function testHasOneCreateAndDefault() {
        $user = $this->createUser();
        $this->assertNull($user->profile);

        $user->profile()->create(['bio' => 'halo']);
        $this->assertSame('halo', $user->fresh()->profile->bio);
        $this->assertEquals($user->getKey(), $user->fresh()->profile->uji_user_id);

        $other = $this->createUser();
        $default = $other->profile()->withDefault(['bio' => 'bawaan'])->getResults();
        $this->assertInstanceOf(UjiModel_Profile::class, $default);
        $this->assertFalse($default->exists);
        $this->assertSame('bawaan', $default->bio);
    }

    public function testEagerLoadingRunsOneQueryPerRelation() {
        $this->seedUserWithPosts();
        $queries = [];
        $this->connection->listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $users = UjiModel_User::with('posts')->orderBy('uji_user_id')->get();

        $this->assertCount(2, $queries, 'satu query user + satu query posts');
        $this->assertStringContainsString('"uji_user_id" in (', $queries[1]);
        $this->assertSame(2, $users[0]->posts->count());
        $this->assertSame(0, $users[1]->posts->count());
        $this->assertTrue($users[1]->relationLoaded('posts'));
    }

    public function testEagerLoadWithConstraintsAndNested() {
        $user = $this->seedUserWithPosts();
        $user->posts->first()->comments()->create(['body' => 'komentar']);

        $loaded = UjiModel_User::with(['posts' => function ($q) {
            $q->where('title', 'pertama');
        }, 'posts.comments'])->find($user->getKey());

        $this->assertSame(['pertama'], $loaded->posts->pluck('title')->all());
        $this->assertSame('komentar', $loaded->posts->first()->comments->first()->body);
        $this->assertTrue($loaded->posts->first()->relationLoaded('comments'));
    }

    public function testLoadAndLoadMissingOnExistingModels() {
        $user = $this->seedUserWithPosts();
        $fresh = UjiModel_User::find($user->getKey());
        $this->assertFalse($fresh->relationLoaded('posts'));

        $fresh->load('posts');
        $this->assertSame(2, $fresh->posts->count());

        $queries = [];
        $this->connection->listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });
        $fresh->loadMissing('posts');
        $this->assertSame([], $queries, 'loadMissing tidak memuat ulang relasi yang sudah ada');
        $fresh->loadMissing('profile');
        $this->assertCount(1, $queries);
    }

    public function testWithCountHasAndWhereHas() {
        $user = $this->seedUserWithPosts();

        $counts = UjiModel_User::withCount('posts')->orderBy('uji_user_id')->get();
        $this->assertEquals([2, 0], $counts->pluck('posts_count')->all());

        $this->assertSame(['penulis'], UjiModel_User::has('posts')->pluck('name')->all());
        $this->assertSame(['kosong'], UjiModel_User::doesntHave('posts')->pluck('name')->all());
        $this->assertSame(['penulis'], UjiModel_User::whereHas('posts', function ($q) {
            $q->where('title', 'kedua');
        })->pluck('name')->all());
        $this->assertSame([], UjiModel_User::whereHas('posts', function ($q) {
            $q->where('title', 'tidak ada');
        })->pluck('name')->all());
        $this->assertSame(['penulis'], UjiModel_User::has('posts', '>=', 2)->pluck('name')->all());
        $this->assertSame(['kosong'], UjiModel_User::whereDoesntHave('posts')->pluck('name')->all());
        $this->assertEquals(2, UjiModel_User::withCount(['posts as jumlah'])->find($user->getKey())->jumlah);
    }

    public function testBelongsToManyAttachDetachSyncAndPivot() {
        $post = UjiModel_Post::create(['title' => 'p']);
        $a = UjiModel_Tag::create(['name' => 'a']);
        $b = UjiModel_Tag::create(['name' => 'b']);
        $c = UjiModel_Tag::create(['name' => 'c']);

        $post->tags()->attach($a->getKey(), ['note' => 'catatan a']);
        $post->tags()->attach([$b->getKey() => ['note' => 'catatan b']]);

        $tags = $post->tags()->orderBy('name')->get();
        $this->assertSame(['a', 'b'], $tags->pluck('name')->all());
        $this->assertSame('catatan a', $tags[0]->pivot->note);
        $this->assertEquals($post->getKey(), $tags[0]->pivot->uji_post_id);
        $this->assertNotNull($tags[0]->pivot->created, 'withTimestamps mengisi created di pivot');
        $this->assertInstanceOf(CModel_Relation_Pivot::class, $tags[0]->pivot);

        $result = $post->tags()->sync([$b->getKey(), $c->getKey() => ['note' => 'baru']]);
        $this->assertSame([$a->getKey()], $result['detached']);
        $this->assertSame([$c->getKey()], $result['attached']);
        $this->assertSame(['b', 'c'], $post->tags()->orderBy('name')->pluck('name')->all());

        $this->assertSame(1, $post->tags()->detach($b->getKey()));
        $this->assertSame(['c'], $post->tags()->pluck('name')->all());
        $this->assertSame(['p'], $c->posts()->pluck('title')->all(), 'sisi balik');

        $post->tags()->toggle([$c->getKey(), $a->getKey()]);
        $this->assertSame(['a'], $post->tags()->pluck('name')->all());
        $this->assertSame(1, $this->table('post_tag')->count());
    }

    public function testBelongsToManyUpdateExistingPivotAndWherePivot() {
        $post = UjiModel_Post::create(['title' => 'p']);
        $a = UjiModel_Tag::create(['name' => 'a']);
        $post->tags()->attach($a->getKey(), ['note' => 'lama']);

        $this->assertSame(1, $post->tags()->updateExistingPivot($a->getKey(), ['note' => 'baru']));
        $this->assertSame('baru', $post->tags()->first()->pivot->note);
        $this->assertSame(1, $post->tags()->wherePivot('note', 'baru')->count());
        $this->assertSame(0, $post->tags()->wherePivot('note', 'lama')->count());
        $this->assertSame(1, $post->tags()->wherePivotIn('note', ['baru', 'x'])->count());
    }

    public function testBelongsToManyEagerLoadKeepsPivotPerParent() {
        $p1 = UjiModel_Post::create(['title' => 'p1']);
        $p2 = UjiModel_Post::create(['title' => 'p2']);
        $tag = UjiModel_Tag::create(['name' => 't']);
        $p1->tags()->attach($tag->getKey(), ['note' => 'satu']);
        $p2->tags()->attach($tag->getKey(), ['note' => 'dua']);

        $posts = UjiModel_Post::with('tags')->orderBy('uji_post_id')->get();

        $this->assertSame('satu', $posts[0]->tags->first()->pivot->note);
        $this->assertSame('dua', $posts[1]->tags->first()->pivot->note);
    }

    public function testMorphManyAndMorphTo() {
        $post = UjiModel_Post::create(['title' => 'p']);
        $comment = $post->comments()->create(['body' => 'isi']);

        $this->assertSame(UjiModel_Post::class, $comment->commentable_type);
        $this->assertEquals($post->getKey(), $comment->commentable_id);
        $this->assertSame('p', $comment->fresh()->commentable->title);
        $this->assertInstanceOf(UjiModel_Post::class, UjiModel_Comment::with('commentable')->first()->commentable);
        $this->assertSame(1, $post->comments()->count());
        $this->assertSame(['isi'], UjiModel_Post::with('comments')->first()->comments->pluck('body')->all());
    }

    public function testMorphMapAliasIsStoredInsteadOfTheClass() {
        CModel_Relation::morphMap(['tulisan' => UjiModel_Post::class]);
        try {
            $post = UjiModel_Post::create(['title' => 'p']);
            $comment = $post->comments()->create(['body' => 'isi']);

            $this->assertSame('tulisan', $comment->commentable_type);
            $this->assertSame('tulisan', $post->getMorphClass());
            $this->assertInstanceOf(UjiModel_Post::class, $comment->fresh()->commentable);
            $this->assertSame(UjiModel_Post::class, CModel_Relation::getMorphedModel('tulisan'));
        } finally {
            CModel_Relation::morphMap([], false);
        }
    }

    public function testWhereHasMorphAndWhereMorphedTo() {
        $post = UjiModel_Post::create(['title' => 'cocok']);
        $post->comments()->create(['body' => 'a']);
        UjiModel_Post::create(['title' => 'lain'])->comments()->create(['body' => 'b']);

        $bodies = UjiModel_Comment::whereHasMorph('commentable', [UjiModel_Post::class], function ($q) {
            $q->where('title', 'cocok');
        })->pluck('body')->all();
        $this->assertSame(['a'], $bodies);
        $this->assertSame(['a'], UjiModel_Comment::whereMorphedTo('commentable', $post)->pluck('body')->all());
    }

    public function testHasManyThrough() {
        $country = UjiModel_Country::create(['name' => 'ID']);
        $user = $this->createUser(['uji_country_id' => $country->getKey()]);
        $user->posts()->create(['title' => 'lewat negara']);
        $this->createUser()->posts()->create(['title' => 'tanpa negara']);

        $this->assertSame(['lewat negara'], $country->posts->pluck('title')->all());
        $this->assertSame(1, $country->posts()->count());
        $this->assertSame(['lewat negara'], UjiModel_Country::with('posts')->first()->posts->pluck('title')->all());
        $this->assertSame(['ID'], UjiModel_Country::has('posts')->pluck('name')->all());
    }

    public function testRelationQueryReturnsTheRelatedModelClass() {
        $user = $this->seedUserWithPosts();

        $this->assertInstanceOf(CModel_Relation_HasMany::class, $user->posts());
        $this->assertInstanceOf(CModel_Query::class, $user->posts()->getQuery());
        $this->assertInstanceOf(UjiModel_Post::class, $user->posts()->getRelated());
        $this->assertInstanceOf(UjiModel_Post::class, $user->posts()->where('title', 'kedua')->first());
        $this->assertSame(['kedua'], $user->posts()->where('title', 'like', 'ke%')->pluck('title')->all());
    }

    public function testUndefinedRelationThrowsRelationNotFound() {
        $this->createUser();
        $this->expectException(CModel_Exception_RelationNotFoundException::class);

        UjiModel_User::with('tidakAda')->get();
    }

    public function testSetRelationAndUnsetRelation() {
        $user = $this->createUser();
        $user->setRelation('posts', new CModel_Collection([new UjiModel_Post(['title' => 'manual'])]));

        $this->assertTrue($user->relationLoaded('posts'));
        $this->assertSame('manual', $user->posts->first()->title);
        $this->assertArrayHasKey('posts', $user->toArray());

        $user->unsetRelation('posts');
        $this->assertFalse($user->relationLoaded('posts'));
    }
}
