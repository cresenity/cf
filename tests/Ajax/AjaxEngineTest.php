<?php

use PHPUnit\Framework\TestCase;

class UjiAjaxEngine_Callable {
    /**
     * @var array
     */
    public static $received = [];

    public static function handle($data) {
        static::$received[] = $data;

        return 'hasil:' . carr::get($data, 'nilai');
    }

    public static function resolveDependsOn($value) {
        return [['key' => $value . '-1', 'value' => 'Pilihan ' . $value . ' 1'], ['key' => $value . '-2', 'value' => 'Pilihan ' . $value . ' 2']];
    }
}

/**
 * Engine ajax dieksekusi langsung lewat CAjax_Method (tanpa HTTP): Callback (CFunction), Reload,
 * AjaxHandler (json/callback menerima CApp), DependsOn (resolver terserialisasi), Validation.
 */
class AjaxEngineTest extends TestCase {
    /**
     * @var string[]
     */
    protected $created = [];

    protected function setUp(): void {
        UjiAjaxEngine_Callable::$received = [];
        $_GET = [];
        $_POST = [];
    }

    protected function tearDown(): void {
        $disk = CTemporary::disk();
        foreach ($this->created as $file) {
            if ($disk->exists($file)) {
                $disk->delete($file);
            }
        }
        $_GET = [];
        $_POST = [];
    }

    /**
     * Simulasi controller cresenity/ajax: tulis lewat makeUrl(), baca kembali dari berkas temp, eksekusi.
     *
     * @param CAjax_Method $method
     * @param array        $input
     *
     * @return mixed
     */
    protected function roundTrip(CAjax_Method $method, array $input = []) {
        $url = $method->makeUrl();
        $segments = array_values(array_filter(explode('/', parse_url($url, PHP_URL_PATH))));
        $id = end($segments);
        $file = CAjax::temporaryFile($id);
        $this->created[] = $file;
        $this->assertTrue(CTemporary::disk()->exists($file));

        $stored = CAjax::createMethod(CTemporary::disk()->get($file))->setArgs([$id]);

        return $stored->executeEngine($input);
    }

    public function testCallbackEngineInvokesTheCallableWithTheMethodData() {
        $method = CAjax::createMethod()->setType(CAjax::TYPE_CALLBACK)
            ->setData('callable', [UjiAjaxEngine_Callable::class, 'handle'])
            ->setData('nilai', 42);

        $result = $this->roundTrip($method);

        $this->assertSame('hasil:42', $result);
        $this->assertCount(1, UjiAjaxEngine_Callable::$received);
        $this->assertSame(42, UjiAjaxEngine_Callable::$received[0]['nilai']);
        $this->assertArrayHasKey('callable', UjiAjaxEngine_Callable::$received[0], 'seluruh data method diteruskan sebagai argumen');
    }

    public function testCallbackEngineAcceptsASerializableClosure() {
        $method = CAjax::createMethod()->setType(CAjax::TYPE_CALLBACK)
            ->setData('callable', c::toSerializableClosure(function ($data) {
                return strtoupper((string) carr::get($data, 'kata'));
            }))
            ->setData('kata', 'halo');

        $this->assertSame('HALO', $this->roundTrip($method));
    }

    public function testSetDataSerializesClosuresSoTheySurviveTheJsonFile() {
        $method = CAjax::createMethod()->setType('Reload')->setData('callback', function () {
            return 'dari closure';
        });

        $stored = $method->getData()['callback'];
        $this->assertIsString($stored);
        $this->assertStringStartsWith('O:', $stored, 'closure disimpan sebagai serialize(CFunction_SerializableClosure)');
        $this->assertSame('dari closure', $this->roundTrip($method));
        $this->assertSame('pra-serialisasi', $this->roundTrip(CAjax::createMethod()->setType('Reload')->setData('callback', serialize(c::toSerializableClosure(function () {
            return 'pra-serialisasi';
        })))), 'string yang sudah diserialisasi pemanggil tetap diterima');
    }

    public function testReloadEngineReturnsStoredJsonOrCallsTheCallback() {
        $json = $this->roundTrip(CAjax::createMethod()->setType(CAjax::TYPE_RELOAD)->setData('json', ['html' => '<b>x</b>']));
        $this->assertSame(['html' => '<b>x</b>'], $json);

        $called = $this->roundTrip(CAjax::createMethod()->setType(CAjax::TYPE_RELOAD)
            ->setData('json', 'diabaikan')
            ->setData('callback', c::toSerializableClosure(function () {
                return 'dari callback';
            })));
        $this->assertSame('dari callback', $called, 'callback menang atas json');
    }

    public function testAjaxHandlerEngineReturnsJsonOrPassesTheAppToTheCallback() {
        $this->assertSame(['a' => 1], $this->roundTrip(CAjax::createMethod()->setType('AjaxHandler')->setData('json', ['a' => 1])));

        $result = $this->roundTrip(CAjax::createMethod()->setType('AjaxHandler')
            ->setData('callback', c::toSerializableClosure(function ($app) {
                return get_class($app) . ':' . ($app instanceof CApp ? 'ya' : 'tidak');
            })));
        $this->assertSame(CApp::class . ':ya', $result);
    }

    public function testDependsOnEngineResolvesOptionsFromThePostedValue() {
        $dependsOn = new CElement_Depends_DependsOn('#provinsi', [UjiAjaxEngine_Callable::class, 'resolveDependsOn']);
        $method = CAjax::createMethod()->setType(CAjax::TYPE_DEPENDS_ON)->setMethod('post')
            ->setData('dependsOn', serialize($dependsOn));

        $response = $this->roundTrip($method, ['value' => 'JB']);

        $this->assertInstanceOf(CHTTP_JsonResponse::class, $response);
        $payload = json_decode($response->getContent(), true);
        $this->assertSame(0, $payload['errCode']);
        $this->assertSame(['JB-1', 'JB-2'], array_column($payload['data'], 'key'));
    }

    public function testDependsOnEngineWrapsAScalarResolverResult() {
        $dependsOn = new CElement_Depends_DependsOn('#a', function ($value) {
            return 'skalar ' . $value;
        });
        $method = CAjax::createMethod()->setType(CAjax::TYPE_DEPENDS_ON)->setData('dependsOn', serialize($dependsOn));

        $payload = json_decode($this->roundTrip($method, ['value' => 'x'])->getContent(), true);

        $this->assertSame(['value' => 'skalar x'], $payload['data']);
    }

    public function testDependsOnEngineRendersACAppResultToArray() {
        $dependsOn = new CElement_Depends_DependsOn('#a', function ($value) {
            $app = c::app();
            $app->addDiv()->add('konten ' . $value);

            return $app;
        });
        $method = CAjax::createMethod()->setType(CAjax::TYPE_DEPENDS_ON)->setData('dependsOn', serialize($dependsOn));

        $payload = json_decode($this->roundTrip($method, ['value' => 'z'])->getContent(), true);

        $this->assertArrayHasKey('html', $payload['data']);
        $this->assertStringContainsString('konten z', $payload['data']['html']);
    }

    /**
     * Alur validasi jarak jauh (jsvalidation): request membawa `_jsvalidation=<field>`; hasilnya selalu
     * dilempar sebagai exception ber-response - ResponseException(true) bila lolos, CValidation_Exception
     * dengan JSON pesan bila gagal - dan exception handler yang mengubahnya jadi respons HTTP.
     */
    public function testValidationEngineReportsTheRemoteFieldResult() {
        $method = CAjax::createMethod()->setType(CAjax::TYPE_VALIDATION)->setMethod('post')
            ->setData('dataValidation', serialize(['email' => ['required', 'email'], 'umur' => ['required', 'integer', 'min:17']]))
            ->setData('formId', 'form_uji');

        //tanpa _validate_all hanya rule "remote" (closure/exists/unique/active_url) yang dicek di server
        $_POST = ['_jsvalidation' => 'email', '_jsvalidation_validate_all' => 'true', 'email' => 'bukan-email', 'umur' => '20'];
        try {
            $this->roundTrip($method);
            $this->fail('validasi gagal harus melempar');
        } catch (CValidation_Exception $e) {
            $messages = json_decode($e->getResponse()->getContent(), true);
            $this->assertIsArray($messages);
            $this->assertNotEmpty($messages, 'pesan galat untuk field email');
        }

        $_POST = ['_jsvalidation' => 'email', '_jsvalidation_validate_all' => 'true', 'email' => 'a@b.co', 'umur' => '20'];
        try {
            $this->roundTrip($method);
            $this->fail('validasi lolos pun dilempar sebagai response');
        } catch (CHTTP_Exception_ResponseException $e) {
            $this->assertSame('true', $e->getResponse()->getContent());
        }
    }

    public function testValidationEngineRulesMayContainSerializedClosures() {
        $rule = c::toSerializableClosure(function ($attribute, $value, $fail) {
            if ($value !== 'rahasia') {
                $fail('kata sandi salah');
            }
        });
        $method = CAjax::createMethod()->setType(CAjax::TYPE_VALIDATION)->setMethod('post')
            ->setData('dataValidation', serialize(['kode' => ['required', $rule]]));

        $_POST = ['_jsvalidation' => 'kode', 'kode' => 'salah'];
        try {
            $this->roundTrip($method);
            $this->fail('harus gagal');
        } catch (CValidation_Exception $e) {
            $this->assertStringContainsString('kata sandi salah', $e->getResponse()->getContent());
        }
    }

    public function testEngineInputComesFromTheVerbOfTheMethodWhenNotGivenExplicitly() {
        $_GET = ['dari' => 'get'];
        $_POST = ['dari' => 'post'];

        $this->assertSame(['dari' => 'get'], CAjax_Method::createEngine(CAjax::createMethod()->setType('Reload'))->getInput());
        $this->assertSame(['dari' => 'post'], CAjax_Method::createEngine(CAjax::createMethod()->setType('Reload')->setMethod('post'))->getInput());
        $this->assertSame(['x' => 1], CAjax_Method::createEngine(CAjax::createMethod()->setType('Reload'), ['x' => 1])->getInput(), 'input eksplisit menang');
    }

    public function testArgsFromTheUrlSegmentsReachTheEngine() {
        $method = CAjax::createMethod()->setType('Reload')->setData('json', 'x');
        $url = $method->makeUrl();
        $segments = array_values(array_filter(explode('/', parse_url($url, PHP_URL_PATH))));
        $id = end($segments);
        $this->created[] = CAjax::temporaryFile($id);

        $stored = CAjax::createMethod(CAjax::getData($id) ? CTemporary::disk()->get(CAjax::temporaryFile($id)) : '{}')->setArgs([$id, 'ekstra']);
        $engine = CAjax_Method::createEngine($stored);

        $this->assertSame([$id, 'ekstra'], $engine->getArgs());
    }
}
