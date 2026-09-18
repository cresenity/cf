<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/ApiTestSupport.php';

/**
 * CApi_MethodAbstract - kontrak kelas Method: bentuk result(), fail(), sumber request(),
 * sessionId() dari parameter/Bearer, lang(), middleware(), dan trait validasi request.
 */
class ApiMethodTest extends TestCase {
    protected function setUp(): void {
        ApiTestSupport::registerGroup();
    }

    /**
     * @param array $request
     *
     * @return ApiTestMethod_Validator
     */
    private function validator(array $request) {
        return new ApiTestMethod_Validator(1, 'sesi', $request);
    }

    public function testResultShape() {
        $method = new ApiTestMethod_Ping(1, 'sesi', ['echo' => 'a']);

        $this->assertSame(['errCode' => 0, 'errMessage' => '', 'data' => []], $method->result());
        $this->assertSame($method->result(), $method->toArray());
        $this->assertFalse($method->hasError());

        $method->execute();
        $this->assertSame(['pong' => true, 'echo' => 'a'], $method->result()['data']);
    }

    public function testFailIncrementsOrSetsTheErrCode() {
        $method = new ApiTestMethod_Fail(1, 'sesi', []);
        $method->execute();
        $this->assertSame(7, $method->getErrCode());
        $this->assertSame('gagal terkendali', $method->getErrMessage());
        $this->assertTrue($method->hasError());

        $method = new ApiTestMethod_Fail(1, 'sesi', ['code' => 0]);
        $method->execute();
        $this->assertSame(0, $method->getErrCode(), 'errCode 0 eksplisit tetap 0');

        $method = new ApiTestMethod_Validator(1, 'sesi', []);
        $call = function () {
            $this->fail('satu');
            $this->fail('dua');
        };
        $call->call($method);
        $this->assertSame(2, $method->getErrCode(), 'tanpa kode, tiap fail() menambah 1');
        $this->assertSame('dua', $method->getErrMessage());
    }

    public function testRunnerExecutesOnlyWhenThereIsNoErrorYet() {
        $method = new ApiTestMethod_Ping(1, 'sesi', ['echo' => 'run']);
        $this->assertSame('run', CApi::runner()->runMethod($method)['data']['echo']);

        $method = new ApiTestMethod_Validator(1, 'sesi', []);
        $call = function () {
            $this->fail('sudah gagal', 9);
        };
        $call->call($method);
        $result = CApi_Runner::instance()->runMethod($method);
        $this->assertSame(9, $result['errCode']);
        $this->assertSame([], $result['data']);
    }

    public function testRequestPrefersTheExplicitArrayThenTheApiRequest() {
        $method = new ApiTestMethod_Ping(1, 'sesi', ['from' => 'array']);
        $this->assertSame(['from' => 'array'], $method->request());

        $method = new ApiTestMethod_Ping(1, 'sesi');
        $apiRequest = CApi_HTTP_Request::createFromBaseHttp(ApiTestSupport::request('x', 'POST', ['from' => 'http']));
        $this->assertSame($method, $method->setApiRequest($apiRequest));
        $this->assertSame($apiRequest, $method->getApiRequest());
        $this->assertSame('http', $method->request()['from']);
    }

    public function testOrgIdDefaultsToTheCurrentOrg() {
        $method = new ApiTestMethod_Member_Profile(null, 'sesi', []);
        $method->execute();

        $this->assertSame(CF::orgId(), $method->result()['data']['orgId']);

        $method = new ApiTestMethod_Member_Profile(99, 'sesi', []);
        $method->execute();
        $this->assertSame(99, $method->result()['data']['orgId']);
    }

    public function testSessionIdComesFromTheConstructorThenTheParameterThenTheBearerToken() {
        $this->assertSame('sesi', (new ApiTestMethod_Ping(1, 'sesi', []))->sessionId());

        $method = new ApiTestMethod_Ping(1, null, ['sessionId' => 'dari-param']);
        $this->assertSame('dari-param', $method->sessionId());

        $method = new ApiTestMethod_Ping(1, null, []);
        $method->setApiRequest(CApi_HTTP_Request::createFromBaseHttp(ApiTestSupport::request('x', 'POST', [], ['HTTP_AUTHORIZATION' => 'Bearer token-bearer'])));
        $this->assertSame('token-bearer', $method->sessionId());
    }

    public function testLangTranslatesThroughTheFramework() {
        $method = new ApiTestMethod_Ping(1, 'sesi', []);

        $this->assertSame('Parameter :key is empty', c::__('Parameter :key is empty'));
        $this->assertSame('Parameter email is empty', $method->lang('Parameter :key is empty', [':key' => 'email']));
    }

    public function testMiddlewareIsRegisteredWithOptions() {
        $method = new ApiTestMethod_Ping(1, 'sesi', []);
        $this->assertSame([], $method->getMiddleware());

        $this->assertSame($method, $method->middleware('one', ['only' => 'post']));
        $method->middleware(['two', 'three']);

        $this->assertSame(['one', 'two', 'three'], array_column($method->getMiddleware(), 'middleware'));
        $this->assertSame(['only' => 'post'], $method->getMiddleware()[0]['options']);
        $this->assertSame([], $method->getMiddleware()[1]['options']);
    }

    public function testGroupAccessors() {
        $method = new ApiTestMethod_Ping(1, 'sesi', []);
        $this->assertNull($method->getGroup());
        $this->assertSame($method, $method->setGroup(ApiTestSupport::GROUP));
        $this->assertSame(ApiTestSupport::GROUP, $method->getGroup());
    }

    public function testValidateThrowsAValidationExceptionWithTheErrors() {
        $method = new ApiTestMethod_Invalid(1, 'sesi', ['email' => 'x', 'age' => 'abc']);

        try {
            $method->execute();
            $this->fail('seharusnya melempar CValidation_Exception');
        } catch (CValidation_Exception $e) {
            $this->assertSame(422, $e->status);
            $this->assertSame(['email', 'age'], array_keys($e->errors()));
        }

        $method = new ApiTestMethod_Invalid(1, 'sesi', ['email' => 'a@b.co', 'age' => 30]);
        $method->execute();
        $this->assertTrue($method->result()['data']['ok']);
    }

    public function testValidateRequestNotEmpty() {
        $method = $this->validator(['name' => 'Hery', 'empty' => '']);

        $this->assertSame('Hery', $method->validateRequestNotEmpty('name'));
        $this->assertFalse($method->hasError());

        $this->assertSame('', $method->validateRequestNotEmpty('empty'));
        $this->assertSame(1, $method->getErrCode());
        $this->assertSame('Parameter empty is empty', $method->getErrMessage());

        //sesudah gagal, validasi berikutnya tidak dijalankan dan mengembalikan null
        $this->assertNull($method->validateRequestNotEmpty('name'));
        $this->assertSame(1, $method->getErrCode());
    }

    public function testValidateRequestNotEmptyCustomMessage() {
        $method = $this->validator([]);
        $method->validateRequestNotEmpty('name', 'Nama wajib');

        $this->assertSame('Nama wajib', $method->getErrMessage());
    }

    public function testValidateRequestLengths() {
        $method = $this->validator(['pin' => '123456']);
        $this->assertSame('123456', $method->validateRequestMaxLength('pin', 6));
        $this->assertSame('123456', $method->validateRequestMinLength('pin', 6));
        $this->assertFalse($method->hasError());

        $method = $this->validator(['pin' => '1234567']);
        $method->validateRequestMaxLength('pin', 6);
        $this->assertSame('Parameter pin max length is 6', $method->getErrMessage());

        $method = $this->validator(['pin' => '12']);
        $method->validateRequestMinLength('pin', 4);
        $this->assertSame('Parameter pin min length is 4', $method->getErrMessage());
    }

    public function testValidateRequestInArrayAndIsArray() {
        $method = $this->validator(['status' => 'aktif', 'list' => [1, 2]]);
        $method->validateRequestInArray('status', ['aktif', 'nonaktif']);
        $method->validateRequestIsArray('list');
        $this->assertFalse($method->hasError());

        $method = $this->validator(['status' => 'lain']);
        $method->validateRequestInArray('status', ['aktif', 'nonaktif']);
        $this->assertSame('Parameter status is must in this possible values: aktif,nonaktif', $method->getErrMessage());

        $method = $this->validator(['list' => 'bukan-array']);
        $method->validateRequestIsArray('list');
        $this->assertSame('Parameter list must be array', $method->getErrMessage());
    }

    public function testValidateRequestNumericUrlEmail() {
        $method = $this->validator(['n' => '12.5', 'u' => 'https://example.com/x', 'e' => 'a@b.co']);
        $method->validateRequestIsNumeric('n');
        $method->validateRequestIsUrl('u');
        $method->validateRequestIsEmail('e');
        $this->assertFalse($method->hasError());

        $method = $this->validator(['u' => 'bukan url']);
        $method->validateRequestIsUrl('u');
        $this->assertSame('Key u is not valid url', $method->getErrMessage());

        $method = $this->validator(['e' => 'bukan email']);
        $method->validateRequestIsEmail('e');
        $this->assertSame('Key e is not valid email', $method->getErrMessage());

        //pesan numerik memakai kunci bahasa bernuansa app tertentu (tbcore.*) — dikembalikan apa adanya
        $method = $this->validator(['n' => 'abc']);
        $method->validateRequestIsNumeric('n');
        $this->assertTrue($method->hasError());
        $this->assertSame('tbcore.parameterKeyValueMustBeNumeric', $method->getErrMessage());
    }

    public function testValidateRequestIsNotUrl() {
        $method = $this->validator(['bio' => 'saya suka kopi']);
        $method->validateRequestIsNotUrl('bio');
        $this->assertFalse($method->hasError());

        $method = $this->validator(['bio' => 'kunjungi www.example.com ya']);
        $method->validateRequestIsNotUrl('bio');
        $this->assertSame('bio is url', $method->getErrMessage());
    }

    public function testValidateDateValidYmdAndMatch() {
        $method = $this->validator(['tanggal' => '2026-09-18', 'a' => 'x', 'b' => 'x']);
        $method->validateDateValidYmd('tanggal');
        $this->assertSame('x', $method->validateRequestMatch('a', 'b'));
        $this->assertFalse($method->hasError());

        $method = $this->validator(['tanggal' => '2026-09']);
        $method->validateDateValidYmd('tanggal');
        $this->assertSame('tanggal invalid date format', $method->getErrMessage());

        $method = $this->validator(['a' => 'x', 'b' => 'y']);
        $method->validateRequestMatch('a', 'b');
        $this->assertSame('Parameter a and b is not match', $method->getErrMessage());
    }

    public function testValidateRequestIsDatetimeDependsOnAnAppClass() {
        //TBUtils (tribelio) dirujuk langsung dari trait framework; di luar app itu method ini fatal
        $method = $this->validator(['at' => '2026-09-18 10:00:00']);

        if (class_exists('TBUtils')) {
            $method->validateRequestIsDatetime('at');
            $this->assertFalse($method->hasError());

            return;
        }

        $this->expectException(Error::class);
        $method->validateRequestIsDatetime('at');
    }
}
