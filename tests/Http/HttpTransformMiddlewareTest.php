<?php

use PHPUnit\Framework\TestCase;

/**
 * Middleware transform input (TrimStrings, ConvertEmptyStringsToNull) dan ValidatePostSize:
 * tes karakterisasi perilaku saat ini; sebelumnya tidak ada satu pun tes untuk folder CHTTP/Middleware.
 */
class HttpTrimStringsExceptForTest extends CHTTP_Middleware_TrimStrings {
    protected $except = ['password', 'pengguna.sandi'];
}

class HttpTransformMiddlewareTest extends TestCase {
    protected function tearDown(): void {
        foreach ([CHTTP_Middleware_TrimStrings::class, CHTTP_Middleware_ConvertEmptyStringsToNull::class] as $class) {
            $property = new ReflectionProperty($class, 'skipCallbacks');
            $property->setAccessible(true);
            $property->setValue(null, []);
        }
        parent::tearDown();
    }

    /**
     * @param object        $middleware
     * @param CHTTP_Request $request
     *
     * @return CHTTP_Request
     */
    protected function pass($middleware, CHTTP_Request $request) {
        return $middleware->handle($request, function ($request) {
            return $request;
        });
    }

    protected function post(array $data, array $query = []) {
        return CHTTP_Request::create('/uji?' . http_build_query($query), 'POST', $data);
    }

    protected function json($payload) {
        return CHTTP_Request::create('/uji', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
    }

    public function testTrimStringsTrimsQueryAndFormValuesIncludingNestedOnes() {
        $request = $this->pass(new CHTTP_Middleware_TrimStrings(), $this->post(
            ['nama' => '  Budi  ', 'alamat' => ['kota' => ' Bandung ', 'rt' => [' 01 ']]],
            ['cari' => '  kata  ']
        ));

        $this->assertSame('kata', $request->query->get('cari'));
        $this->assertSame('Budi', $request->request->get('nama'));
        $this->assertSame('Bandung', $request->request->get('alamat')['kota']);
        $this->assertSame('01', $request->request->get('alamat')['rt'][0]);
    }

    public function testTrimStringsTrimsJsonBodiesAndLeavesNonStringsAlone() {
        $request = $this->pass(new CHTTP_Middleware_TrimStrings(), $this->json(['nama' => '  Budi ', 'umur' => 30, 'aktif' => true, 'kosong' => null]));

        $this->assertSame('Budi', $request->json('nama'));
        $this->assertSame(30, $request->json('umur'));
        $this->assertTrue($request->json('aktif'));
        $this->assertNull($request->json('kosong'));
    }

    public function testTrimStringsHasNoDefaultExceptionSoPasswordsAreTrimmed() {
        $request = $this->pass(new CHTTP_Middleware_TrimStrings(), $this->post(['password' => '  rahasia  ']));

        $this->assertSame('rahasia', $request->request->get('password'));
    }

    public function testTrimStringsExceptListUsesExactDottedKeys() {
        $request = $this->pass(new HttpTrimStringsExceptForTest(), $this->post([
            'password' => '  tetap  ',
            'pengguna' => ['sandi' => '  tetap2  ', 'nama' => '  dipangkas  '],
            'password_lain' => '  dipangkas2  ',
        ]));

        $this->assertSame('  tetap  ', $request->request->get('password'));
        $this->assertSame('  tetap2  ', $request->request->get('pengguna')['sandi']);
        $this->assertSame('dipangkas', $request->request->get('pengguna')['nama']);
        $this->assertSame('dipangkas2', $request->request->get('password_lain'), 'except tidak memakai wildcard');
    }

    public function testTrimStringsSkipWhenBypassesTheTransform() {
        CHTTP_Middleware_TrimStrings::skipWhen(function ($request) {
            return $request->is('uji');
        });

        $request = $this->pass(new CHTTP_Middleware_TrimStrings(), $this->post(['nama' => '  Budi  ']));

        $this->assertSame('  Budi  ', $request->request->get('nama'));
    }

    public function testTrimStringsIsNotMultibyteAware() {
        $nbsp = "\xC2\xA0";
        $request = $this->pass(new CHTTP_Middleware_TrimStrings(), $this->post(['nama' => $nbsp . 'Budi' . $nbsp]));

        $this->assertSame($nbsp . 'Budi' . $nbsp, $request->request->get('nama'), 'spasi Unicode tidak dipangkas (trim biasa)');
    }

    public function testConvertEmptyStringsToNullOnlyChangesEmptyStrings() {
        $request = $this->pass(new CHTTP_Middleware_ConvertEmptyStringsToNull(), $this->post(
            ['a' => '', 'b' => ' ', 'c' => '0', 'd' => ['e' => '', 'f' => 'x']],
            ['q' => '']
        ));

        $this->assertNull($request->query->get('q'));
        $this->assertNull($request->request->get('a'));
        $this->assertSame(' ', $request->request->get('b'), 'spasi bukan string kosong');
        $this->assertSame('0', $request->request->get('c'));
        $this->assertNull($request->request->get('d')['e']);
        $this->assertSame('x', $request->request->get('d')['f']);
    }

    public function testConvertEmptyStringsToNullAppliesToJsonAndHonorsSkipWhen() {
        $request = $this->pass(new CHTTP_Middleware_ConvertEmptyStringsToNull(), $this->json(['a' => '', 'b' => 'x']));
        $this->assertNull($request->json('a'));
        $this->assertSame('x', $request->json('b'));

        CHTTP_Middleware_ConvertEmptyStringsToNull::skipWhen(function () {
            return true;
        });
        $skipped = $this->pass(new CHTTP_Middleware_ConvertEmptyStringsToNull(), $this->post(['a' => '']));
        $this->assertSame('', $skipped->request->get('a'));
    }

    public function testTrimStringsAndConvertEmptyStringsCompose() {
        $trim = new CHTTP_Middleware_TrimStrings();
        $convert = new CHTTP_Middleware_ConvertEmptyStringsToNull();

        $request = $trim->handle($this->post(['a' => '   ']), function ($request) use ($convert) {
            return $convert->handle($request, function ($request) {
                return $request;
            });
        });

        $this->assertNull($request->request->get('a'), 'dipangkas dulu menjadi kosong, lalu menjadi null');
    }

    /**
     * @return int
     */
    protected function postMaxSize() {
        $method = new ReflectionMethod(CHTTP_Middleware_ValidatePostSize::class, 'getPostMaxSize');
        $method->setAccessible(true);

        return $method->invoke(new CHTTP_Middleware_ValidatePostSize());
    }

    public function testValidatePostSizeAllowsBodiesWithinTheLimitAndRejectsLargerOnes() {
        $max = $this->postMaxSize();
        if ($max <= 0) {
            $this->markTestSkipped('post_max_size tidak dibatasi');
        }
        $middleware = new CHTTP_Middleware_ValidatePostSize();

        $within = CHTTP_Request::create('/uji', 'POST', [], [], [], ['CONTENT_LENGTH' => $max]);
        $this->assertSame($within, $this->pass($middleware, $within), 'tepat sama dengan batas masih lolos');

        $this->expectException(CHTTP_Exception_PostTooLargeException::class);
        $this->pass($middleware, CHTTP_Request::create('/uji', 'POST', [], [], [], ['CONTENT_LENGTH' => $max + 1]));
    }

    public function testValidatePostSizeIgnoresRequestsWithoutAContentLength() {
        $request = CHTTP_Request::create('/uji', 'GET');

        $this->assertSame($request, $this->pass(new CHTTP_Middleware_ValidatePostSize(), $request));
    }
}
