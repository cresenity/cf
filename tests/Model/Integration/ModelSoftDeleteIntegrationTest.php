<?php

require_once __DIR__ . '/UjiModelSupport.php';

/**
 * Soft delete CF: kolom `status` (bukan deleted_at) sebagai scope global, delete() men-set status=0 +
 * deleted (bila DeletedTrait), withTrashed/onlyTrashed/withoutTrashed/restore, dan forceDelete.
 */
class ModelSoftDeleteIntegrationTest extends UjiModel_IntegrationTestCase {
    public function testCreateWritesStatusOneAndAuditTimestamps() {
        $user = $this->createUser();

        $row = $this->table('uji_user')->where('uji_user_id', $user->getKey())->first();
        $this->assertSame(1, (int) $row->status);
        $this->assertNotNull($row->created);
        $this->assertNotNull($row->updated);
        $this->assertNull($row->deleted);
        $this->assertSame('uji_user_id', $user->getKeyName(), 'kunci CF = <tabel>_id');
        $this->assertSame(1, $user->getKey());
    }

    public function testDeleteIsASoftDeleteThatSetsStatusZero() {
        $user = $this->createUser();

        $this->assertTrue($user->delete());

        $row = $this->table('uji_user')->where('uji_user_id', $user->getKey())->first();
        $this->assertNotNull($row, 'baris tetap ada');
        $this->assertSame(0, (int) $row->status);
        $this->assertNotNull($row->deleted, 'DeletedTrait mengisi kolom deleted');
        $this->assertTrue($user->trashed());
        $this->assertSame(0, (int) $user->status);
        $this->assertFalse($user->isDirty(), 'status/deleted/updated sudah disinkronkan ke original');
    }

    public function testDeleteWithoutDeletedTraitOnlyTouchesStatus() {
        $country = UjiModel_Country::create(['name' => 'ID']);

        $country->delete();

        $row = $this->table('uji_country')->first();
        $this->assertSame(0, (int) $row->status);
        $this->assertFalse(UjiModel_Country::usesDeleted());
    }

    public function testTrashedRowsAreInvisibleToEveryQuery() {
        $a = $this->createUser(['name' => 'a']);
        $b = $this->createUser(['name' => 'b']);
        $b->delete();

        $this->assertSame(1, UjiModel_User::count());
        $this->assertNull(UjiModel_User::find($b->getKey()));
        $this->assertSame(['a'], UjiModel_User::pluck('name')->all());
        $this->assertSame(0, UjiModel_User::where('name', 'b')->count());
        $this->assertSame(2, $this->table('uji_user')->count(), 'query builder mentah tidak kena scope');
        $this->assertStringContainsString('"status" > ?', UjiModel_User::query()->toSql());
        $this->assertSame([0], UjiModel_User::query()->getBindings());
    }

    public function testWithTrashedOnlyTrashedAndWithoutTrashed() {
        $a = $this->createUser(['name' => 'a']);
        $b = $this->createUser(['name' => 'b']);
        $b->delete();

        $this->assertSame(['a', 'b'], UjiModel_User::withTrashed()->orderBy('name')->pluck('name')->all());
        $this->assertSame(['b'], UjiModel_User::onlyTrashed()->pluck('name')->all());
        $this->assertSame(['a'], UjiModel_User::withTrashed()->withoutTrashed()->pluck('name')->all());
        $this->assertSame($b->getKey(), UjiModel_User::withTrashed()->find($b->getKey())->getKey());
        $this->assertStringNotContainsString('status', UjiModel_User::withTrashed()->toSql());
    }

    /**
     * Divergensi dari hulu: withTrashed(false) di hulu berarti withoutTrashed(); macro CF mengabaikan
     * argumennya dan tetap menyertakan baris terhapus.
     */
    public function testWithTrashedFalseStillIncludesTrashedRows() {
        $this->createUser(['name' => 'a']);
        $this->createUser(['name' => 'b'])->delete();

        $this->assertSame(2, UjiModel_User::withTrashed(false)->count());
    }

    public function testRestoreBringsTheRowBack() {
        $user = $this->createUser();
        $user->delete();

        $restoring = $restored = 0;
        UjiModel_User::restoring(function () use (&$restoring) {
            $restoring++;
        });
        UjiModel_User::restored(function () use (&$restored) {
            $restored++;
        });

        $this->assertTrue($user->restore());

        $this->assertSame(1, $restoring);
        $this->assertSame(1, $restored);
        $this->assertFalse($user->trashed());
        $this->assertSame(1, UjiModel_User::count());
        $this->assertSame(1, (int) $this->table('uji_user')->value('status'));
        $this->assertNotNull($this->table('uji_user')->value('deleted'), 'restore hanya membalik status, kolom deleted dibiarkan');
    }

    public function testRestoringEventReturningFalseCancelsTheRestore() {
        $user = $this->createUser();
        $user->delete();
        UjiModel_User::restoring(function () {
            return false;
        });

        $this->assertFalse($user->restore());
        $this->assertSame(0, UjiModel_User::count());
    }

    public function testRestoreOnTheQueryBuilderUpdatesEveryMatchingTrashedRow() {
        $this->createUser(['name' => 'a'])->delete();
        $this->createUser(['name' => 'b'])->delete();
        $this->createUser(['name' => 'c']);

        $affected = UjiModel_User::onlyTrashed()->where('name', '!=', 'b')->restore();

        $this->assertSame(1, $affected);
        $this->assertSame(['a', 'c'], UjiModel_User::orderBy('name')->pluck('name')->all());
    }

    public function testForceDeleteRemovesTheRow() {
        $user = $this->createUser();

        $user->forceDelete();

        $this->assertSame(0, $this->table('uji_user')->count());
        $this->assertFalse($user->exists);
        $this->assertFalse($user->isForceDeleting(), 'flag kembali false sesudah selesai');
    }

    public function testQueryDeleteIsASoftDeleteToo() {
        $this->createUser(['name' => 'a']);
        $this->createUser(['name' => 'b']);

        $affected = UjiModel_User::where('name', 'a')->delete();

        $this->assertSame(1, $affected);
        $this->assertSame(['b'], UjiModel_User::pluck('name')->all());
        $this->assertSame(2, $this->table('uji_user')->count());
        $this->assertSame(0, (int) $this->table('uji_user')->where('name', 'a')->value('status'));
    }

    public function testQueryForceDeleteRemovesRows() {
        $this->createUser(['name' => 'a']);
        $this->createUser(['name' => 'b'])->delete();

        UjiModel_User::withTrashed()->where('name', 'b')->forceDelete();

        $this->assertSame(1, $this->table('uji_user')->count());
    }

    public function testDeletingEventReturningFalseCancelsTheDelete() {
        $user = $this->createUser();
        UjiModel_User::deleting(function () {
            return false;
        });

        $this->assertFalse($user->delete());
        $this->assertSame(1, UjiModel_User::count());
        $this->assertFalse($user->trashed());
    }

    public function testDeletedEventFiresOnceWithTheModel() {
        $user = $this->createUser();
        $seen = [];
        UjiModel_User::deleted(function ($model) use (&$seen) {
            $seen[] = $model->getKey();
        });

        $user->delete();

        $this->assertSame([$user->getKey()], $seen);
    }

    public function testTrashedParentIsInvisibleThroughRelations() {
        $user = $this->createUser();
        $post = $user->posts()->create(['title' => 'halo']);
        $user->delete();

        $this->assertNull($post->fresh()->user, 'belongsTo ke induk terhapus mengembalikan null');
        $this->assertSame($user->getKey(), UjiModel_Post::with(['user' => function ($q) {
            $q->withTrashed();
        }])->first()->user->getKey());
    }

    public function testWhereHasHonoursTheScopeOnTheRelatedModel() {
        $user = $this->createUser();
        $user->posts()->create(['title' => 'tampil']);
        $user->posts()->create(['title' => 'hilang'])->delete();

        $this->assertSame(1, UjiModel_User::has('posts')->count());
        $this->assertEquals(1, UjiModel_User::withCount('posts')->first()->posts_count);
        $this->assertSame(1, $user->posts()->count());
        $this->assertSame(2, $user->posts()->withTrashed()->count());
    }

    public function testJoinedQueryQualifiesTheStatusColumn() {
        $sql = UjiModel_Post::query()->join('uji_user', 'uji_user.uji_user_id', '=', 'uji_post.uji_user_id')->toSql();

        $this->assertStringContainsString('"uji_post"."status" > ?', $sql);
    }

    public function testWithoutGlobalScopesDropsTheStatusFilter() {
        $this->createUser()->delete();

        $this->assertSame(1, UjiModel_User::withoutGlobalScopes()->count());
        $this->assertSame(1, UjiModel_User::withoutGlobalScope(CModel_SoftDelete_Scope::class)->count());
    }
}
