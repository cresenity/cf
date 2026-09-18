<?php

require_once __DIR__ . '/UjiModelSupport.php';

class UjiModel_UserObserver {
    /**
     * @var array
     */
    public static $log = [];

    public function creating($user) {
        static::$log[] = 'creating:' . $user->name;
    }

    public function created($user) {
        static::$log[] = 'created:' . $user->getKey();
    }

    public function updating($user) {
        static::$log[] = 'updating';
    }

    public function updated($user) {
        static::$log[] = 'updated';
    }

    public function saving($user) {
        static::$log[] = 'saving';
    }

    public function saved($user) {
        static::$log[] = 'saved';
    }

    public function deleting($user) {
        static::$log[] = 'deleting';
    }

    public function deleted($user) {
        static::$log[] = 'deleted';
    }
}

class UjiModel_UserCreatedEvent {
    public $user;

    public function __construct($user) {
        $this->user = $user;
    }
}

class UjiModel_EventfulUser extends UjiModel_User {
    protected $dispatchesEvents = ['created' => UjiModel_UserCreatedEvent::class];
}

class UjiModel_ScopedPost extends UjiModel_Post {
    protected static function booted() {
        static::addGlobalScope('terbit', function ($query) {
            $query->where('is_published', 1);
        });
    }
}

/**
 * Event model (closure, observer, kelas event), saveQuietly/withoutEvents, urutan event, dan
 * scope global (kelas & closure) + scope lokal + scopes() dinamis.
 */
class ModelEventScopeIntegrationTest extends UjiModel_IntegrationTestCase {
    protected function setUp(): void {
        parent::setUp();
        UjiModel_UserObserver::$log = [];
    }

    protected function tearDown(): void {
        UjiModel_EventfulUser::flushEventListeners();
        UjiModel_ScopedPost::flushEventListeners();
        parent::tearDown();
    }

    public function testEventOrderOnCreateUpdateAndDelete() {
        UjiModel_User::observe(UjiModel_UserObserver::class);

        $user = UjiModel_User::create(['name' => 'x']);
        $this->assertSame(['saving', 'creating:x', 'created:1', 'saved'], UjiModel_UserObserver::$log);

        UjiModel_UserObserver::$log = [];
        $user->name = 'y';
        $user->save();
        $this->assertSame(['saving', 'updating', 'updated', 'saved'], UjiModel_UserObserver::$log);

        UjiModel_UserObserver::$log = [];
        $user->save();
        $this->assertSame(['saving', 'saved'], UjiModel_UserObserver::$log, 'tanpa perubahan: updating/updated tidak dipanggil');

        UjiModel_UserObserver::$log = [];
        $user->delete();
        $this->assertSame(['deleting', 'deleted'], UjiModel_UserObserver::$log);
    }

    public function testCreatingReturningFalseAbortsTheInsert() {
        UjiModel_User::creating(function ($user) {
            return $user->name !== 'tolak';
        });

        $this->assertFalse((new UjiModel_User(['name' => 'tolak']))->save());
        $this->assertTrue((new UjiModel_User(['name' => 'terima']))->save());
        $this->assertSame(['terima'], UjiModel_User::pluck('name')->all());
    }

    public function testSavingListenerCanMutateAttributesBeforeInsert() {
        UjiModel_User::saving(function ($user) {
            $user->email = strtolower((string) $user->email);
        });

        $user = UjiModel_User::create(['name' => 'x', 'email' => 'BUDI@EXAMPLE.TEST']);

        $this->assertSame('budi@example.test', $this->table('uji_user')->value('email'));
        $this->assertSame('budi@example.test', $user->email);
    }

    public function testUpdatingReturningFalseKeepsTheRowUnchanged() {
        $user = $this->createUser(['name' => 'lama']);
        UjiModel_User::updating(function () {
            return false;
        });

        $user->name = 'baru';
        $this->assertFalse($user->save());
        $this->assertSame('lama', $this->table('uji_user')->value('name'));
        $this->assertTrue($user->isDirty('name'));
    }

    public function testSaveQuietlyAndWithoutEventsSkipListeners() {
        UjiModel_User::observe(UjiModel_UserObserver::class);

        $user = new UjiModel_User(['name' => 'diam']);
        $user->saveQuietly();
        $this->assertSame([], UjiModel_UserObserver::$log);
        $this->assertTrue($user->exists);

        UjiModel_User::withoutEvents(function () use ($user) {
            $user->update(['name' => 'tetap diam']);
            $user->delete();
        });
        $this->assertSame([], UjiModel_UserObserver::$log);
        $this->assertSame(0, UjiModel_User::count());

        $user->restore();
        $this->assertSame(['saving', 'updating', 'updated', 'saved'], UjiModel_UserObserver::$log, 'restore() menyimpan lewat save() biasa, jadi bersuara');
        UjiModel_UserObserver::$log = [];
        $user->updateQuietly(['name' => 'q']);
        $user->deleteQuietly();
        $this->assertSame([], UjiModel_UserObserver::$log);

        UjiModel_User::create(['name' => 'bersuara']);
        $this->assertContains('creating:bersuara', UjiModel_UserObserver::$log, 'listener kembali aktif sesudah withoutEvents()');
    }

    public function testDispatchesEventsMapsToEventClasses() {
        $received = [];
        CEvent::dispatcher()->listen(UjiModel_UserCreatedEvent::class, function ($event) use (&$received) {
            $received[] = $event->user->name;
        });

        UjiModel_EventfulUser::create(['name' => 'kelas']);

        $this->assertSame(['kelas'], $received);
    }

    public function testRetrievedEventFiresForEachModelLoaded() {
        $this->createUser(['name' => 'a']);
        $this->createUser(['name' => 'b']);
        $seen = [];
        UjiModel_User::retrieved(function ($user) use (&$seen) {
            $seen[] = $user->name;
        });

        UjiModel_User::orderBy('name')->get();
        UjiModel_User::where('name', 'a')->first();

        $this->assertSame(['a', 'b', 'a'], $seen);
    }

    public function testObservableEventsCanBeExtended() {
        $user = new UjiModel_User();
        $user->addObservableEvents('disetujui');

        $this->assertContains('disetujui', $user->getObservableEvents());
        $this->assertContains('restoring', $user->getObservableEvents(), 'SoftDeleteTrait menambah restoring/restored');
        $this->assertContains('restored', $user->getObservableEvents());
    }

    public function testGlobalScopeClosureAppliesToEveryQueryAndCanBeRemoved() {
        UjiModel_ScopedPost::create(['title' => 'tampil', 'is_published' => true]);
        UjiModel_ScopedPost::create(['title' => 'draf', 'is_published' => false]);

        $this->assertSame(['tampil'], UjiModel_ScopedPost::pluck('title')->all());
        $this->assertSame(2, UjiModel_ScopedPost::withoutGlobalScope('terbit')->count());
        $this->assertSame(2, UjiModel_ScopedPost::withoutGlobalScopes(['terbit'])->count(), 'status scope tetap berlaku');
        $this->assertSame(2, UjiModel_ScopedPost::withoutGlobalScopes()->count());
        $this->assertTrue(UjiModel_ScopedPost::hasGlobalScope('terbit'));
        $this->assertTrue(UjiModel_ScopedPost::hasGlobalScope(CModel_SoftDelete_Scope::class));
        $this->assertSame(['terbit'], UjiModel_ScopedPost::withoutGlobalScope('terbit')->removedScopes());
        $this->assertSame(['draf'], UjiModel_ScopedPost::withoutGlobalScope('terbit')->where('is_published', 0)->pluck('title')->all());
    }

    public function testGlobalScopeAppliesToUpdateAndDeleteQueries() {
        UjiModel_ScopedPost::create(['title' => 'tampil', 'is_published' => true]);
        UjiModel_ScopedPost::create(['title' => 'draf', 'is_published' => false]);

        $this->assertSame(1, UjiModel_ScopedPost::query()->update(['body' => 'x']));
        $this->assertSame('x', $this->table('uji_post')->where('title', 'tampil')->value('body'));
        $this->assertNull($this->table('uji_post')->where('title', 'draf')->value('body'));
        $this->assertSame(1, UjiModel_ScopedPost::query()->delete());
        $this->assertSame(1, (int) $this->table('uji_post')->where('title', 'draf')->value('status'), 'draf di luar scope tidak terhapus');
    }

    public function testLocalScopesChainAndAcceptArguments() {
        $this->createUser(['name' => 'aktif', 'is_active' => true]);
        $this->createUser(['name' => 'nonaktif', 'is_active' => false]);
        $this->createUser(['name' => 'aktif dua', 'is_active' => true]);

        $this->assertSame(['aktif', 'aktif dua'], UjiModel_User::active()->orderBy('name')->pluck('name')->all());
        $this->assertSame(['aktif'], UjiModel_User::active()->named('aktif')->pluck('name')->all());
        $this->assertSame(['nonaktif'], UjiModel_User::named('nonaktif')->pluck('name')->all());
        $this->assertSame(['aktif'], UjiModel_User::query()->scopes(['active', 'named' => 'aktif'])->pluck('name')->all());
        $this->assertTrue(UjiModel_User::query()->hasNamedScope('active'));
        $this->assertFalse(UjiModel_User::query()->hasNamedScope('tidakAda'));
    }

    public function testScopeWithOrWhereIsWrappedInParentheses() {
        $this->createUser(['name' => 'a', 'is_active' => true]);
        $this->createUser(['name' => 'b', 'is_active' => false]);

        $sql = UjiModel_User::where('name', 'z')->orWhere(function ($q) {
            $q->active();
        })->toSql();

        $this->assertStringContainsString('or ("is_active" = ?)', $sql);
        $this->assertSame(['a'], UjiModel_User::where('name', 'z')->orWhere(function ($q) {
            $q->active();
        })->pluck('name')->all());
    }

    public function testBootedHookRunsOncePerClass() {
        $first = new UjiModel_ScopedPost();
        $second = new UjiModel_ScopedPost();

        $this->assertCount(2, UjiModel_ScopedPost::query()->getModel()->getGlobalScopes(), 'terbit + status, tidak digandakan');
        $this->assertSame($first->getGlobalScopes(), $second->getGlobalScopes());
    }
}
