<?php

require_once __DIR__ . '/UjiModelSupport.php';

class UjiModel_PostTagPivot extends CModel_Relation_Pivot {
    protected $connection = UjiModel_IntegrationTestCase::CONNECTION;

    public function getNoteUpperAttribute() {
        return strtoupper((string) $this->note);
    }
}

class UjiModel_PostWithCustomPivot extends UjiModel_Post {
    public function tags() {
        return $this->belongsToMany(UjiModel_Tag::class, 'post_tag', 'uji_post_id', 'uji_tag_id')->using(UjiModel_PostTagPivot::class)->withPivot('note');
    }
}

class UjiModel_TouchingPost extends UjiModel_Post {
    protected $touches = ['user'];
}

class UjiModel_EagerUser extends UjiModel_User {
    protected $with = ['profile'];

    protected $withCount = ['posts'];
}

class UjiModel_DatedUser extends UjiModel_User {
    protected $casts = [];

    protected $dates = ['birthday'];

    protected $dateFormat = 'Y-m-d';

    protected function serializeDate(DateTimeInterface $date) {
        return $date->format('d/m/Y');
    }
}

class UjiModel_TaggableComment extends UjiModel_Comment {
    public function tags() {
        return $this->morphToMany(UjiModel_Tag::class, 'taggable', 'uji_taggable');
    }
}

class UjiModel_MorphedTag extends UjiModel_Tag {
    public function comments() {
        return $this->morphedByMany(UjiModel_TaggableComment::class, 'taggable', 'uji_taggable');
    }
}

/**
 * Relasi lanjutan: hasOneThrough, morphOne, morphToMany/morphedByMany, pivot kustom (using), touches,
 * eager load bawaan ($with/$withCount), $dates/$dateFormat/serializeDate, dan pencegahan lazy loading.
 */
class ModelAdvancedRelationIntegrationTest extends UjiModel_IntegrationTestCase {
    protected function createSchema() {
        parent::createSchema();
        $this->connection->statement('create table uji_taggable (uji_tag_id integer, taggable_type varchar(100), taggable_id integer)');
    }

    protected function tearDown(): void {
        CModel::preventLazyLoading(false);
        CModel::handleLazyLoadingViolationUsing(null);
        parent::tearDown();
    }

    public function testHasOneThrough() {
        $country = UjiModel_Country::create(['name' => 'ID']);
        $user = $this->createUser(['uji_country_id' => $country->getKey()]);
        $user->profile()->create(['bio' => 'lewat user']);
        $model = new class() extends UjiModel_Country {
            public function profile() {
                return $this->hasOneThrough(UjiModel_Profile::class, UjiModel_User::class);
            }
        };

        $found = $model->newQuery()->find($country->getKey());
        $this->assertInstanceOf(CModel_Relation_HasOneThrough::class, $found->profile());
        $this->assertSame('lewat user', $found->profile->bio);
        $this->assertSame('lewat user', $model->newQuery()->with('profile')->find($country->getKey())->profile->bio);
    }

    public function testMorphOne() {
        $model = new class() extends UjiModel_Post {
            public function latestComment() {
                return $this->morphOne(UjiModel_Comment::class, 'commentable');
            }
        };
        $post = $model->newQuery()->create(['title' => 'p']);
        $post->latestComment()->create(['body' => 'satu']);

        $this->assertSame('satu', $post->fresh()->latestComment->body);
        $this->assertSame(get_class($post), $this->table('uji_comment')->value('commentable_type'), 'morph type = kelas model yang dipakai');
    }

    public function testMorphToManyAndMorphedByMany() {
        $comment = UjiModel_TaggableComment::create(['body' => 'c']);
        $tag = UjiModel_MorphedTag::create(['name' => 't']);

        $comment->tags()->attach($tag->getKey());

        $this->assertSame(['t'], $comment->tags()->pluck('name')->all());
        $this->assertSame(UjiModel_TaggableComment::class, $this->table('uji_taggable')->value('taggable_type'));
        $this->assertSame(['c'], $tag->comments()->pluck('body')->all());
        $this->assertSame(['t'], UjiModel_TaggableComment::with('tags')->first()->tags->pluck('name')->all());
        $this->assertSame(1, UjiModel_MorphedTag::has('comments')->count());
        $comment->tags()->detach();
        $this->assertSame(0, $this->table('uji_taggable')->count());
    }

    public function testCustomPivotClassViaUsing() {
        $post = UjiModel_PostWithCustomPivot::create(['title' => 'p']);
        $tag = UjiModel_Tag::create(['name' => 't']);
        $post->tags()->attach($tag->getKey(), ['note' => 'kecil']);

        $pivot = $post->tags()->first()->pivot;
        $this->assertInstanceOf(UjiModel_PostTagPivot::class, $pivot);
        $this->assertSame('KECIL', $pivot->note_upper, 'accessor di kelas pivot kustom');
        $this->assertSame('post_tag', $pivot->getTable());
        $this->assertSame($post->getKey(), (int) $pivot->uji_post_id);
    }

    public function testTouchesUpdatesTheParentTimestamp() {
        $user = $this->createUser();
        $this->table('uji_user')->update(['updated' => '2000-01-01 00:00:00']);

        $post = new UjiModel_TouchingPost(['title' => 'p']);
        $post->uji_user_id = $user->getKey();
        $post->save();

        $this->assertNotSame('2000-01-01 00:00:00', $this->table('uji_user')->value('updated'), 'induk ikut di-touch');

        $this->table('uji_user')->update(['updated' => '2000-01-01 00:00:00']);
        UjiModel_User::withoutTouching(function () use ($post) {
            $post->title = 'ubah';
            $post->save();
        });
        $this->assertSame('2000-01-01 00:00:00', $this->table('uji_user')->value('updated'), 'withoutTouching pada kelas yang DISENTUH (induk) yang menahan, seperti hulu');
        $this->assertTrue(UjiModel_User::isIgnoringTouch(UjiModel_Country::class) === false);
        $this->assertTrue($post->touches('user'));
        $this->assertFalse($post->touches('tags'));
    }

    public function testDefaultWithAndWithCountAreAppliedToEveryQuery() {
        $user = UjiModel_EagerUser::create(['name' => 'a']);
        $user->profile()->create(['bio' => 'b']);
        $user->posts()->create(['title' => 'p']);

        $loaded = UjiModel_EagerUser::first();
        $this->assertTrue($loaded->relationLoaded('profile'));
        $this->assertSame('b', $loaded->profile->bio);
        $this->assertEquals(1, $loaded->posts_count);

        $bare = UjiModel_EagerUser::without('profile')->first();
        $this->assertFalse($bare->relationLoaded('profile'));
        $this->assertFalse(UjiModel_EagerUser::withOnly([])->first()->relationLoaded('profile'));
    }

    public function testDatesPropertyDateFormatAndSerializeDate() {
        $user = UjiModel_DatedUser::create(['name' => 'a', 'birthday' => '1990-05-17']);

        $this->assertSame('1990-05-17', $this->table('uji_user')->value('birthday'), 'disimpan memakai $dateFormat');
        $found = UjiModel_DatedUser::find($user->getKey());
        $this->assertInstanceOf(CCarbon::class, $found->birthday);
        $this->assertSame('17/05/1990', $found->toArray()['birthday'], 'serializeDate() menentukan bentuk JSON');
        $this->assertContains('birthday', $found->getDates());
        $this->assertSame('Y-m-d', $found->getDateFormat());
        $this->assertSame('1990-05-17', $found->fromDateTime('1990-05-17 10:00:00'));
    }

    public function testPreventLazyLoadingThrowsAndCanBeHandled() {
        $user = $this->createUser();
        $user->posts()->create(['title' => 'p']);
        $this->createUser();
        CModel::preventLazyLoading();

        $this->assertSame(1, UjiModel_User::with('posts')->get()->first()->posts->count(), 'eager load tetap boleh');
        $this->assertSame(1, UjiModel_User::first()->posts->count(), 'satu model (first) tidak dianggap N+1, seperti hulu');

        $violations = [];
        CModel::handleLazyLoadingViolationUsing(function ($model, $relation) use (&$violations) {
            $violations[] = get_class($model) . '.' . $relation;
        });
        $users = UjiModel_User::orderBy('uji_user_id')->get();
        $this->assertTrue($users[0]->preventsLazyLoading);
        $this->assertSame(1, $users[0]->posts->count(), 'handler kustom: tidak melempar, tetap memuat');
        $this->assertSame([UjiModel_User::class . '.posts'], $violations);

        CModel::handleLazyLoadingViolationUsing(null);
        $this->expectException(CModel_Exception_LazyLoadingViolationException::class);
        UjiModel_User::get()->first()->posts;
    }

    public function testLazyLoadingOnAFreshUnsavedModelIsAllowed() {
        CModel::preventLazyLoading();

        $this->assertSame(0, (new UjiModel_User())->posts->count(), 'model yang belum tersimpan bebas lazy load');
    }

    public function testRelationExistenceQueriesUseQualifiedColumns() {
        $sql = UjiModel_User::has('posts')->toSql();

        $this->assertStringContainsString('where exists (select * from "uji_post" where "uji_user"."uji_user_id" = "uji_post"."uji_user_id"', $sql);
        $this->assertStringContainsString('(select count(*) from "uji_post" where', UjiModel_User::has('posts', '>=', 2)->toSql(), 'jumlah > 1 memakai count');
        $this->assertStringContainsString('"uji_post"."status" > ?', $sql, 'scope status pada relasi ikut di subquery');
    }
}
