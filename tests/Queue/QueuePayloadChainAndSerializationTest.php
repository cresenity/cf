<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Model/Integration/UjiModelSupport.php';

class UjiQueue_Log {
    /** @var array */
    public static $seen = [];
}

class UjiQueue_Job implements CQueue_ShouldQueueInterface {
    use CQueue_Trait_QueueableTrait;
    use CQueue_Trait_InteractsWithQueue;
    use CQueue_Trait_DispatchableTrait;

    /** @var int */
    public $tries = 3;

    /** @var int */
    public $timeout = 90;

    /** @var int */
    public $maxExceptions = 2;

    /** @var bool */
    public $failOnTimeout = true;

    /** @var array */
    public $backoff = [5, 10];

    /** @var string */
    public $label;

    public function __construct($label = 'a') {
        $this->label = $label;
    }

    public function handle() {
        UjiQueue_Log::$seen[] = $this->label;
    }

    public function retryUntil() {
        return CCarbon::now()->addMinutes(10);
    }

    public function displayName() {
        return 'uji:' . $this->label;
    }
}

class UjiQueue_PlainJob implements CQueue_ShouldQueueInterface {
    use CQueue_Trait_QueueableTrait;
    use CQueue_Trait_InteractsWithQueue;
    use CQueue_Trait_DispatchableTrait;

    /** @var string */
    public $label;

    public function __construct($label = 'plain') {
        $this->label = $label;
    }

    public function handle() {
        UjiQueue_Log::$seen[] = $this->label;
    }
}

class UjiQueue_FailingJob extends UjiQueue_PlainJob {
    public function handle() {
        UjiQueue_Log::$seen[] = 'fail:' . $this->label;

        throw new RuntimeException('gagal ' . $this->label);
    }
}

class UjiQueue_ModelJob implements CQueue_ShouldQueueInterface {
    use CQueue_Trait_QueueableTrait;
    use CQueue_Trait_InteractsWithQueue;
    use CQueue_Trait_SerializesModels;

    /** @var UjiModel_User */
    public $user;

    /** @var CModel_Collection */
    public $posts;

    /** @var string */
    public $note;

    public function __construct($user, $posts, $note) {
        $this->user = $user;
        $this->posts = $posts;
        $this->note = $note;
    }

    public function handle() {
        UjiQueue_Log::$seen[] = $this->user->name . ':' . $this->posts->count();
    }
}

/**
 * Bentuk payload job (uuid/displayName/maxTries/timeout/backoff/retryUntil/data), CQueue_JobName,
 * CallQueuedClosure, rantai job (chain + catch) di atas koneksi sync, dan SerializesModels yang
 * menyimpan model sebagai CModel_Identifier lalu memuat ulang dari SQLite in-memory.
 */
class QueuePayloadChainAndSerializationTest extends UjiModel_IntegrationTestCase {
    /**
     * @var string
     */
    protected $originalDefault;

    protected function setUp(): void {
        parent::setUp();
        UjiQueue_Log::$seen = [];
        $this->originalDefault = CQueue::queuer()->getDefaultDriver();
        CQueue::queuer()->setDefaultDriver('sync');
        CCarbon::setTestNow(null);
    }

    protected function tearDown(): void {
        CQueue::queuer()->setDefaultDriver($this->originalDefault);
        CCarbon::setTestNow(null);
        parent::tearDown();
    }

    /**
     * @return CQueue_Queue_SyncQueue
     */
    protected function syncQueue() {
        $queue = new CQueue_Queue_SyncQueue();
        $queue->setContainer(CContainer::getInstance());
        $queue->setConnectionName('sync');

        return $queue;
    }

    /**
     * @param object $job
     *
     * @return array
     */
    protected function payloadFor($job) {
        $queue = $this->syncQueue();
        $method = new ReflectionMethod($queue, 'createPayloadArray');
        $method->setAccessible(true);

        return $method->invoke($queue, $job, 'default');
    }

    // ---- payload ----

    public function testObjectPayloadCarriesJobOptions() {
        CCarbon::setTestNow(CCarbon::create(2026, 1, 1, 0, 0, 0));
        $payload = $this->payloadFor(new UjiQueue_Job('x'));
        $this->assertSame('uji:x', $payload['displayName'], 'displayName() menang atas nama kelas');
        $this->assertSame('CQueue_CallQueuedHandler@call', $payload['job']);
        $this->assertSame(3, $payload['maxTries']);
        $this->assertSame(2, $payload['maxExceptions']);
        $this->assertTrue($payload['failOnTimeout']);
        $this->assertSame(90, $payload['timeout']);
        $this->assertSame('5,10', $payload['backoff'], 'backoff array menjadi daftar koma');
        $this->assertSame(CCarbon::now()->addMinutes(10)->getTimestamp(), $payload['retryUntil']);
        $this->assertSame(UjiQueue_Job::class, $payload['data']['commandName']);
        $this->assertInstanceOf(UjiQueue_Job::class, unserialize($payload['data']['command']));
        $this->assertSame(36, strlen($payload['uuid']));
    }

    public function testObjectPayloadDefaultsWhenJobHasNoOptions() {
        $payload = $this->payloadFor(new UjiQueue_PlainJob());
        $this->assertSame(UjiQueue_PlainJob::class, $payload['displayName']);
        $this->assertNull($payload['maxTries']);
        $this->assertFalse($payload['maxExceptions']);
        $this->assertFalse($payload['failOnTimeout']);
        $this->assertNull($payload['backoff']);
        $this->assertNull($payload['timeout']);
        $this->assertNull($payload['retryUntil']);
    }

    public function testStringPayloadKeepsJobNameAndData() {
        $payload = $this->payloadFor('SomeHandler@fire');
        $this->assertSame('SomeHandler@fire', $payload['job']);
        $this->assertSame('SomeHandler', $payload['displayName'], 'displayName = kelas tanpa @method');
        $this->assertNull($payload['maxTries']);
    }

    public function testBackoffFromMethodAndDateTime() {
        $queue = $this->syncQueue();
        $this->assertNull($queue->getJobBackoff(new UjiQueue_PlainJob()));
        $job = new UjiQueue_Job();
        $job->backoff = 30;
        $this->assertSame('30', $queue->getJobBackoff($job));
        CCarbon::setTestNow(CCarbon::create(2026, 1, 1, 0, 0, 0));
        $job->backoff = [CCarbon::now()->addSeconds(45), 60];
        $this->assertSame('45,60', $queue->getJobBackoff($job), 'tanggal diubah ke detik dari sekarang');
    }

    public function testCreatePayloadUsingHookAddsKeys() {
        CQueue_AbstractQueue::createPayloadUsing(function ($connection, $queue, $payload) {
            return ['tenant' => 'uji-' . $queue];
        });
        try {
            $payload = $this->payloadFor(new UjiQueue_PlainJob());
            $this->assertSame('uji-default', $payload['tenant']);
        } finally {
            CQueue_AbstractQueue::createPayloadUsing(null);
        }
    }

    public function testJobNameParseAndResolve() {
        $this->assertSame(['Foo', 'fire'], CQueue_JobName::parse('Foo'));
        $this->assertSame(['Foo', 'bar'], CQueue_JobName::parse('Foo@bar'));
        $this->assertSame('Tampil', CQueue_JobName::resolve('Foo@bar', ['displayName' => 'Tampil']));
        $this->assertSame('Foo@bar', CQueue_JobName::resolve('Foo@bar', ['displayName' => '']));
        $this->assertSame('Foo@bar', CQueue_JobName::resolve('Foo@bar', []));
    }

    // ---- eksekusi lewat sync ----

    public function testPushingAnObjectJobRunsItsHandle() {
        $this->syncQueue()->push(new UjiQueue_Job('sync'));
        $this->assertSame(['sync'], UjiQueue_Log::$seen);
    }

    public function testCallQueuedClosureRunsAndReportsDisplayName() {
        // closure diserialisasi ke payload, jadi variabel by-reference tidak sampai: pakai log statis
        $closure = CQueue_CallQueuedClosure::create(function () {
            UjiQueue_Log::$seen[] = 'closure';
        });
        $this->assertInstanceOf(CQueue_CallQueuedClosure::class, $closure);
        $this->assertStringContainsString('Closure', $closure->displayName());
        $this->assertStringContainsString(basename(__FILE__), $closure->displayName(), 'nama tampil menyebut berkas asal');
        $this->syncQueue()->push($closure);
        $this->assertSame(['closure'], UjiQueue_Log::$seen);
    }

    public function testCallQueuedClosureFailureCallbacksReceiveTheException() {
        $seen = null;
        $closure = CQueue_CallQueuedClosure::create(function () {
            throw new RuntimeException('meledak');
        })->onFailure(function ($e) use (&$seen) {
            $seen = $e;
        });
        try {
            $closure->failed(new RuntimeException('meledak'));
        } catch (Exception $e) {
            $this->fail('failed() tidak boleh melempar');
        }
        $this->assertInstanceOf(RuntimeException::class, $seen);
        $this->assertSame('meledak', $seen->getMessage());
    }

    public function testDispatchRunsOnTheSyncConnection() {
        UjiQueue_PlainJob::dispatch('lewat-dispatch');
        $this->assertSame(['lewat-dispatch'], UjiQueue_Log::$seen);
    }

    public function testChainRunsJobsInOrder() {
        UjiQueue_PlainJob::withChain([new UjiQueue_PlainJob('kedua'), new UjiQueue_PlainJob('ketiga')])->dispatch('pertama');
        $this->assertSame(['pertama', 'kedua', 'ketiga'], UjiQueue_Log::$seen);
    }

    public function testChainStopsAtTheFailingJobAndCallsCatch() {
        $chain = CQueue::dispatcher()->chain([new UjiQueue_PlainJob('satu'), new UjiQueue_FailingJob('dua'), new UjiQueue_PlainJob('tiga')]);
        // callback catch ikut diserialisasi bersama job, jadi hasilnya dicatat lewat log statis
        $chain->catch(function ($e) {
            UjiQueue_Log::$seen[] = 'catch:' . $e->getMessage();
        });
        try {
            $chain->dispatch();
        } catch (RuntimeException $e) {
            // koneksi sync meneruskan exception job ke pemanggil
        }
        $this->assertSame(['satu', 'fail:dua', 'catch:gagal dua'], array_slice(UjiQueue_Log::$seen, 0, 3), 'job sesudah yang gagal tidak jalan, catch dipanggil');
        // khas driver sync: job berikutnya dijalankan di dalam call() job sebelumnya sebelum job itu
        // di-delete, jadi exception yang merambat membuat job "satu" ikut fail() dan catch terpanggil
        // lagi; di worker nyata (job berikutnya masuk antrean) catch hanya sekali
        $this->assertSame(2, count(array_keys(UjiQueue_Log::$seen, 'catch:gagal dua', true)));
    }

    public function testChainedJobsAreSerializedOnTheFirstJob() {
        $job = new UjiQueue_PlainJob('a');
        $job->chain([new UjiQueue_PlainJob('b'), new UjiQueue_PlainJob('c')]);
        $this->assertCount(2, $job->chained);
        $this->assertInstanceOf(UjiQueue_PlainJob::class, unserialize($job->chained[0]));
        $job->prependToChain(new UjiQueue_PlainJob('z'));
        $job->appendToChain(new UjiQueue_PlainJob('d'));
        $this->assertSame(['z', 'b', 'c', 'd'], array_map(function ($s) {
            return unserialize($s)->label;
        }, $job->chained));
    }

    public function testQueueableSettersAreFluent() {
        $job = new UjiQueue_PlainJob();
        $this->assertSame($job, $job->onConnection('sync')->onQueue('lambat')->delay(10)->afterCommit()->through(['x']));
        $this->assertSame('sync', $job->connection);
        $this->assertSame('lambat', $job->queue);
        $this->assertSame(10, $job->delay);
        $this->assertTrue($job->afterCommit);
        $this->assertSame(['x'], $job->middleware);
        $job->allOnConnection('database')->allOnQueue('cepat')->withoutDelay()->beforeCommit();
        $this->assertSame('database', $job->chainConnection);
        $this->assertSame('cepat', $job->chainQueue);
        $this->assertSame(0, $job->delay, 'withoutDelay = 0, bukan null');
        $this->assertFalse($job->afterCommit);
    }

    // ---- SerializesModels di SQLite ----

    public function testModelsAreSerializedAsIdentifiersAndRestored() {
        $user = UjiModel_User::create(['name' => 'Budi', 'email' => 'b@x.id']);
        $posts = new CModel_Collection([
            UjiModel_Post::create(['uji_user_id' => $user->getKey(), 'title' => 'p1']),
            UjiModel_Post::create(['uji_user_id' => $user->getKey(), 'title' => 'p2']),
        ]);
        $job = new UjiQueue_ModelJob($user, $posts, 'catatan');

        $serialized = serialize($job);
        $this->assertStringContainsString('CModel_Identifier', $serialized, 'model disimpan sebagai identifier');
        $this->assertStringNotContainsString('Budi', $serialized, 'atribut model tidak ikut diserialisasi');
        $this->assertStringContainsString('catatan', $serialized, 'skalar biasa tetap');

        $user->name = 'Diubah';
        $user->save();

        $restored = unserialize($serialized);
        $this->assertInstanceOf(UjiModel_User::class, $restored->user);
        $this->assertSame('Diubah', $restored->user->name, 'dimuat ulang dari DB, bukan salinan lama');
        $this->assertInstanceOf(CModel_Collection::class, $restored->posts);
        $this->assertSame(['p1', 'p2'], $restored->posts->pluck('title')->all());
        $this->assertSame('catatan', $restored->note);

        $restored->handle();
        $this->assertSame(['Diubah:2'], UjiQueue_Log::$seen);
    }

    public function testRestoringADeletedModelThrows() {
        $user = UjiModel_User::create(['name' => 'Hilang', 'email' => 'h@x.id']);
        $job = new UjiQueue_ModelJob($user, new CModel_Collection([]), 'x');
        $serialized = serialize($job);
        $user->forceDelete();
        $this->expectException(CModel_Exception_ModelNotFoundException::class);
        unserialize($serialized);
    }

    public function testEmptyCollectionRoundTrips() {
        $user = UjiModel_User::create(['name' => 'Kosong', 'email' => 'k@x.id']);
        $restored = unserialize(serialize(new UjiQueue_ModelJob($user, new CModel_Collection([]), 'x')));
        $this->assertInstanceOf(CModel_Collection::class, $restored->posts);
        $this->assertCount(0, $restored->posts);
    }
}
