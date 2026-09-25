<?php

use PHPUnit\Framework\TestCase;

class UjiAjax_Callable {
    /**
     * @var array
     */
    public static $received = [];

    public static function handle($data) {
        static::$received[] = $data;

        return 'hasil:' . carr::get($data, 'nilai');
    }
}

/**
 * Pembungkus disk temp asli - meneruskan exists()/get() apa adanya, tapi put() dibuat
 * gagal diam-diam (tidak benar-benar menulis, tidak melempar) sejumlah kali tertentu
 * sebelum akhirnya meneruskan ke disk asli. Mensimulasikan tulis-transient yang gagal
 * (mis. hiccup S3 sesaat pada app yang disk temp-nya cloud-backed) tanpa mengganti
 * seluruh disk config.
 */
class UjiAjax_FlakyDisk {
    protected $inner;

    protected $failuresLeft;

    public $putCalls = 0;

    public function __construct($inner, $failuresLeft) {
        $this->inner = $inner;
        $this->failuresLeft = $failuresLeft;
    }

    public function put($path, $contents, $options = []) {
        $this->putCalls++;
        if ($this->failuresLeft > 0) {
            $this->failuresLeft--;

            return false;
        }

        return $this->inner->put($path, $contents, $options);
    }

    public function exists($path) {
        return $this->inner->exists($path);
    }

    public function get($path) {
        return $this->inner->get($path);
    }

    public function delete($paths) {
        return $this->inner->delete($paths);
    }
}

/**
 * CAjax_Method: serialisasi bolak-balik, tipe dari nama kelas, makeUrl() menulis berkas temp per app
 * dengan URL cresenity/ajax/<id>, kedaluwarsa, auth, createEngine() + alias tipe, dan
 * CAjax::getData()/setData()/getDefaultExpiration()/info().
 */
class AjaxMethodTest extends TestCase {
    /**
     * @var string[]
     */
    protected $created = [];

    protected function tearDown(): void {
        $disk = CTemporary::disk();
        foreach ($this->created as $file) {
            if ($disk->exists($file)) {
                $disk->delete($file);
            }
        }
        // buang UjiAjax_FlakyDisk kalau salah satu test menukarnya, supaya test
        // lain di proses phpcf test yang sama (CStorage::instance() singleton
        // seluruh proses) kembali memakai disk temp asli, bukan sisa tukaran ini.
        CStorage::instance()->forgetDisk(CF::config('storage.temp'));
    }

    /**
     * @param string $url
     *
     * @return string
     */
    protected function idFromUrl($url) {
        $segments = array_values(array_filter(explode('/', parse_url($url, PHP_URL_PATH))));
        $id = end($segments);
        $this->created[] = CAjax::temporaryFile($id);

        return $id;
    }

    public function testDefaultsAndFluentSetters() {
        $method = CAjax::createMethod();

        $this->assertInstanceOf(CAjax_Method::class, $method);
        $this->assertSame('GET', $method->getMethod(), 'method HTTP bawaan GET');
        $this->assertNull($method->getType());
        $this->assertSame([], $method->getData());
        $this->assertNull($method->getExpiration());
        $this->assertNull($method->auth, 'konstruktor menyetel false lalu fromArray() menimpanya dengan null - keduanya berarti tanpa auth');

        $same = $method->setType('Reload')->setMethod('post')->setData('json', ['a' => 1])->setArgs(['x'])->setExpiration(1234);
        $this->assertSame($method, $same);
        $this->assertSame('Reload', $method->getType());
        $this->assertSame('post', $method->getMethod());
        $this->assertSame(['json' => ['a' => 1]], $method->getData());
        $this->assertSame(['x'], $method->getArgs());
        $this->assertSame(1234, $method->getExpiration());
    }

    public function testSetTypeAcceptsAnEngineClassNameAndKeepsTheBasename() {
        $method = CAjax::createMethod()->setType(CAjax_Engine_Reload::class);

        $this->assertSame('Reload', $method->getType());
        $this->assertSame('Callback', CAjax::createMethod()->setType('Callback')->getType());
    }

    public function testSetExpirationAcceptsDateTime() {
        $when = CCarbon::parse('2030-01-01 00:00:00');

        $this->assertSame($when->getTimestamp(), CAjax::createMethod()->setExpiration($when)->getExpiration());
        $this->assertSame($when->getTimestamp(), CAjax::createMethod()->setExpiration(new DateTimeImmutable('2030-01-01 00:00:00'))->getExpiration());
    }

    public function testToArrayToJsonAndFromJsonRoundTrip() {
        $method = CAjax::createMethod(['name' => 'n', 'method' => 'post', 'type' => 'Reload', 'target' => '#t', 'param' => ['p' => 1], 'args' => [1, 2], 'expiration' => 99, 'auth' => ['guard' => 'web', 'id' => 5], 'data' => ['json' => 'x']]);
        $array = $method->toArray();

        $this->assertSame(['name', 'method', 'type', 'target', 'param', 'args', 'expiration', 'auth', 'data'], array_keys($array));
        $this->assertSame('post', $array['method']);
        $this->assertSame(['guard' => 'web', 'id' => 5], $array['auth']);

        $copy = CAjax::createMethod($method->toJson());
        $this->assertSame($array, $copy->toArray(), 'createMethod(string json) = createFromJson');
        $this->assertSame($array, CAjax_Method::createFromJson($method->toJson())->toArray());
        $this->assertSame($array, (new CAjax_Method())->fromArray($array)->toArray());
    }

    public function testFromArrayFallsBackToDefaultsForMissingKeys() {
        $method = (new CAjax_Method())->fromArray(['type' => 'Reload']);

        $this->assertSame('GET', $method->getMethod());
        $this->assertSame([], $method->getData());
        $this->assertNull($method->auth);
        $this->assertNull($method->getArgs());
    }

    public function testMakeUrlWritesTheMethodToTheAppTempFolderAndReturnsAnAjaxUrl() {
        $method = CAjax::createMethod()->setType('Reload')->setData('json', ['halo' => 'dunia']);

        $url = $method->makeUrl();
        $id = $this->idFromUrl($url);

        $this->assertStringStartsWith(curl::httpbase() . 'cresenity/ajax/', $url);
        $this->assertSame(40, strlen($id), 'id = Ymd + md5 acak');
        $this->assertSame(date('Ymd'), substr($id, 0, 8));
        $file = CAjax::temporaryFile($id);
        $this->assertStringStartsWith('ajax' . DIRECTORY_SEPARATOR . CF::appCode() . DIRECTORY_SEPARATOR, $file);
        $this->assertTrue(CTemporary::disk()->exists($file));
        $this->assertSame($method->toArray(), CAjax::createMethod(CTemporary::disk()->get($file))->toArray());
        $this->assertSame(['halo' => 'dunia'], CAjax::getData($id)['data']['json'], 'CAjax::getData() membaca berkas yang sama');
    }

    public function testTwoMakeUrlCallsProduceDistinctIds() {
        $method = CAjax::createMethod()->setType('Reload');

        $a = $this->idFromUrl($method->makeUrl());
        $b = $this->idFromUrl($method->makeUrl());

        $this->assertNotSame($a, $b);
    }

    /**
     * store() sebelumnya tidak pernah mengecek hasil put() - satu kegagalan tulis yang
     * transient (mis. hiccup S3 sesaat) membuat makeUrl() tetap mengembalikan token yang
     * berkasnya tidak pernah benar-benar ada, dan cresenity/ajax/{token} baru 404 belakangan
     * saat token itu dipakai, tanpa galat apa pun tercatat di titik penulisannya (tribelio
     * collector #14389 - DownloadProgress export diklik menit setelah halaman dimuat, 404
     * pada token yang store()-nya sempat berjalan tanpa keluhan).
     */
    public function testStoreRetriesOnceWhenTheFirstDiskWriteDoesNotActuallyPersist() {
        $realDisk = CTemporary::disk();
        $flaky = new UjiAjax_FlakyDisk($realDisk, 1);
        CStorage::instance()->set(CF::config('storage.temp'), $flaky);

        $method = CAjax::createMethod()->setType('Reload')->setData('json', ['halo' => 'dunia']);
        $id = $this->idFromUrl($method->makeUrl());
        $file = CAjax::temporaryFile($id);
        $this->created[] = $file;

        $this->assertSame(2, $flaky->putCalls, 'put() pertama gagal, retry sekali lagi berhasil');
        $this->assertTrue($realDisk->exists($file), 'berkas benar-benar ada di disk asli setelah retry');
        $this->assertSame(['halo' => 'dunia'], CAjax::getData($id)['data']['json']);
    }

    public function testStoreLogsAWarningWhenBothWritesFail() {
        $realDisk = CTemporary::disk();
        $flaky = new UjiAjax_FlakyDisk($realDisk, 2);
        CStorage::instance()->set(CF::config('storage.temp'), $flaky);

        $method = CAjax::createMethod()->setType('Reload');
        $id = $this->idFromUrl($method->makeUrl());
        $file = CAjax::temporaryFile($id);

        $this->assertSame(2, $flaky->putCalls, 'dua percobaan tulis, keduanya gagal, tidak dicoba ketiga kalinya');
        $this->assertFalse($realDisk->exists($file), 'berkas memang tidak pernah ada - inilah yang tadinya bikin 404 diam-diam');
    }

    public function testSetDataAndGetDataOnTheFacade() {
        $id = date('Ymd') . cutils::randmd5();
        $this->created[] = CAjax::temporaryFile($id);

        $returned = CAjax::setData($id, ['type' => 'Reload', 'data' => ['x' => 1]]);

        $this->assertSame(['type' => 'Reload', 'data' => ['x' => 1]], $returned);
        $this->assertSame(['type' => 'Reload', 'data' => ['x' => 1]], CAjax::getData($id));
        CAjax::setData($id, ['type' => 'Reload', 'data' => ['x' => 2]]);
        $this->assertSame(2, CAjax::getData($id)['data']['x'], 'setData menimpa');
    }

    public function testGetDefaultExpirationFollowsConfig() {
        $original = CConfig::repository()->get('app.ajax.expiration');
        try {
            CConfig::repository()->set('app.ajax.expiration', 90);
            $expected = c::now()->addMinutes(90)->getTimestamp();
            $this->assertEqualsWithDelta($expected, CAjax::getDefaultExpiration(), 2);
        } finally {
            CConfig::repository()->set('app.ajax.expiration', $original);
        }
    }

    public function testCreateEngineResolvesTheEngineClassAndAlias() {
        $this->assertInstanceOf(CAjax_Engine_Reload::class, CAjax_Method::createEngine(CAjax::createMethod()->setType('Reload')));
        $this->assertInstanceOf(CAjax_Engine_SelectSearch::class, CAjax_Method::createEngine(CAjax::createMethod()->setType('SearchSelect')), 'alias lama SearchSelect');

        foreach ([CAjax::TYPE_SELECT_SEARCH, CAjax::TYPE_CALLBACK, CAjax::TYPE_DATA_TABLE, CAjax::TYPE_FILE_MANAGER, CAjax::TYPE_IMG_UPLOAD, CAjax::TYPE_FILE_UPLOAD, CAjax::TYPE_RELOAD, CAjax::TYPE_VALIDATION, CAjax::TYPE_DEPENDS_ON, CAjax::TYPE_TREE_VIEW, CAjax::TYPE_CALENDAR] as $type) {
            $this->assertInstanceOf(CAjax_Engine::class, CAjax_Method::createEngine(CAjax::createMethod()->setType($type)), $type);
        }
    }

    public function testCreateEngineThrowsForAnUnknownType() {
        $this->expectException(CAjax_Exception::class);
        $this->expectExceptionMessage('CAjax_Engine_TidakAda');

        CAjax_Method::createEngine(CAjax::createMethod()->setType('TidakAda'));
    }

    public function testEngineExposesMethodInputDataTypeAndArgs() {
        $method = CAjax::createMethod()->setType('Reload')->setData('json', 'x')->setArgs(['id1']);
        $engine = CAjax_Method::createEngine($method, ['q' => 'cari']);

        $this->assertSame('GET', $engine->getMethod(), 'getMethod() di engine = method HTTP');
        $this->assertSame($method, $engine->getAjaxMethod());
        $this->assertSame(['q' => 'cari'], $engine->getInput());
        $this->assertSame(['json' => 'x'], $engine->getData());
        $this->assertSame('Reload', $engine->getType());
        $this->assertSame(['id1'], $engine->getArgs());
        $engine->setInput(['q' => 'lain']);
        $this->assertSame(['q' => 'lain'], $engine->getInput());
    }

    public function testToJsonResponseShape() {
        $engine = CAjax_Method::createEngine(CAjax::createMethod()->setType('Reload'));

        $response = $engine->toJsonResponse(0, '', ['k' => 'v']);
        $this->assertInstanceOf(CHTTP_JsonResponse::class, $response);
        $this->assertSame(['errCode' => 0, 'errMessage' => '', 'data' => ['k' => 'v']], json_decode($response->getContent(), true));
    }

    public function testExecuteEngineRefusesAnExpiredMethod() {
        $method = CAjax::createMethod()->setType('Reload')->setData('json', 'x')->setExpiration(CCarbon::now()->subMinute());

        $this->expectException(CAjax_Exception_ExpiredAjaxException::class);
        $method->executeEngine();
    }

    public function testExecuteEngineRunsWhenTheExpirationIsInTheFuture() {
        $method = CAjax::createMethod()->setType('Reload')->setData('json', 'masih')->setExpiration(CCarbon::now()->addMinute());

        $this->assertSame('masih', $method->executeEngine());
        $this->assertSame('tanpa batas', CAjax::createMethod()->setType('Reload')->setData('json', 'tanpa batas')->executeEngine());
    }

    public function testExecuteEngineRefusesWhenAuthIsRequiredAndNobodyIsLoggedIn() {
        $method = CAjax::createMethod(['type' => 'Reload', 'data' => ['json' => 'x'], 'auth' => ['guard' => null]]);

        $this->expectException(CAjax_Exception_AuthAjaxException::class);
        $method->executeEngine();
    }

    public function testExecuteEngineWithAuthTrueUsesTheDefaultGuard() {
        // auth === true adalah bentuk yang ditulis enableAuth() saat tidak ada guard bernama; dulu fatal
        // "Call to a member function check() on null", kini ditolak rapi sebagai belum login
        $method = CAjax::createMethod(['type' => 'Reload', 'data' => ['json' => 'x'], 'auth' => true]);

        $this->expectException(CAjax_Exception_AuthAjaxException::class);
        $method->executeEngine();
    }

    public function testInfoReadsUploadInfoFilesFromTheTempDisk() {
        $fileId = date('Ymd') . cutils::randmd5() . '.txt';
        $path = CTemporary::getPath(CAjax_Engine_FileUpload::FOLDER_INFO, $fileId);
        $this->created[] = $path;
        CTemporary::disk()->put($path, json_encode(['filename' => 'asli.txt', 'size' => 3]));

        $this->assertSame(['filename' => 'asli.txt', 'size' => 3], CAjax_Info::getFileInfo($fileId));
        $this->assertSame(['filename' => 'asli.txt', 'size' => 3], CAjax::info()->getFileInfo($fileId), 'CAjax::info() meneruskan ke CAjax_Info');
    }
}
