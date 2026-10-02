<?php

use PHPUnit\Framework\TestCase;

/**
 * CHTTP_FormRequest::validated() hanya mengembalikan kunci yang punya aturan (aturan ber-titik/wildcard tidak
 * boleh membawa seluruh subtree), validator dipakai ulang, dan request tanpa rules() tidak fatal.
 */
class HttpFormRequestNestedForTest extends CHTTP_FormRequest {
    public $validatorCalls = 0;

    public function rules() {
        return ['user.name' => 'required|string', 'items.*.name' => 'required|string', 'title' => 'required'];
    }

    public function withValidator($validator) {
        $this->validatorCalls++;
    }
}

class HttpFormRequestPlainForTest extends CHTTP_FormRequest {
    public function rules() {
        return ['name' => 'required', 'email' => 'required|email'];
    }
}

class HttpFormRequestWithoutRulesForTest extends CHTTP_FormRequest {
}

class HttpFormRequestAfterForTest extends CHTTP_FormRequest {
    protected $stopOnFirstFailure = true;

    public function authorize() {
        return true;
    }

    public function rules() {
        return ['nama' => 'required|min:5|alpha', 'umur' => 'required'];
    }

    public function after() {
        return [function ($validator) {
            if ($this->input('nama') === 'Larangan') {
                $validator->errors()->add('nama', 'nama dilarang');
            }
        }];
    }
}

class HttpFormRequestTest extends TestCase {
    /**
     * @param string $class
     * @param array  $input
     *
     * @return CHTTP_FormRequest
     */
    protected function make($class, array $input) {
        $request = $class::create('/uji', 'POST', $input);
        $request->setContainer(c::container());
        $request->setRedirector(CHTTP_Redirector::instance());

        return $request;
    }

    public function testDottedRuleDoesNotCarryUnvalidatedSiblingKeys() {
        $request = $this->make(HttpFormRequestNestedForTest::class, [
            'title' => 'Judul',
            'user' => ['name' => 'Budi', 'is_admin' => '1'],
            'items' => [['name' => 'A', 'price' => '9'], ['name' => 'B', 'price' => '8']],
        ]);

        $validated = $request->validated();

        $this->assertSame('Budi', $validated['user']['name']);
        $this->assertArrayNotHasKey('is_admin', $validated['user'], 'kunci tanpa aturan tidak boleh lolos (mass assignment)');
        $this->assertSame([['name' => 'A'], ['name' => 'B']], $validated['items'], 'wildcard hanya membawa kunci beraturan');
        $this->assertSame('Judul', $validated['title']);
    }

    public function testPlainRulesStillReturnOnlyRuledKeys() {
        $request = $this->make(HttpFormRequestPlainForTest::class, ['name' => 'Budi', 'email' => 'budi@contoh.test', 'role' => 'admin']);

        $this->assertSame(['name' => 'Budi', 'email' => 'budi@contoh.test'], $request->validated());
    }

    public function testInvalidDataThrowsAValidationException() {
        $request = $this->make(HttpFormRequestPlainForTest::class, ['name' => 'Budi', 'email' => 'bukan-email']);

        $this->expectException(CValidation_Exception::class);

        $request->validated();
    }

    public function testValidatorIsBuiltOnceAndReused() {
        $request = $this->make(HttpFormRequestNestedForTest::class, ['title' => 'x', 'user' => ['name' => 'a'], 'items' => [['name' => 'b']]]);

        $request->validated();
        $request->validated();

        $this->assertSame(1, $request->validatorCalls, 'withValidator() cukup sekali per request');
    }

    public function testRequestWithoutRulesMethodDoesNotFatal() {
        $request = $this->make(HttpFormRequestWithoutRulesForTest::class, ['apa' => 'saja']);

        $this->assertSame([], $request->validated());
    }

    public function testSafeReturnsTheValidatedInputContainer() {
        $request = $this->make(HttpFormRequestPlainForTest::class, ['name' => 'Budi', 'email' => 'budi@contoh.test', 'role' => 'admin']);

        $safe = $request->safe();

        $this->assertSame(['name' => 'Budi', 'email' => 'budi@contoh.test'], $safe->all());
        $this->assertSame(['name' => 'Budi'], $request->safe(['name']));
    }

    public function testAfterCallbacksRunAndCanAddErrors() {
        $request = $this->make(HttpFormRequestAfterForTest::class, ['nama' => 'Larangan', 'umur' => '30']);

        try {
            $request->validateResolved();
            $this->fail('seharusnya gagal karena callback after');
        } catch (CValidation_Exception $e) {
            $this->assertSame(['nama dilarang'], $e->validator->errors()->get('nama'));
        }
    }

    public function testStopOnFirstFailureStopsAfterTheFirstFailingAttribute() {
        $request = $this->make(HttpFormRequestAfterForTest::class, ['nama' => 'ab1']);

        try {
            $request->validateResolved();
            $this->fail('seharusnya gagal');
        } catch (CValidation_Exception $e) {
            $this->assertGreaterThanOrEqual(1, count($e->validator->errors()->get('nama')));
            $this->assertCount(0, $e->validator->errors()->get('umur'), 'atribut sesudah kegagalan pertama tidak dievaluasi');
        }
    }

    public function testSetValidatorReplacesTheValidatorUsedByTheRequest() {
        $request = $this->make(HttpFormRequestPlainForTest::class, ['name' => 'Budi', 'email' => 'x@contoh.test']);
        $custom = CValidation_Factory::instance()->make(['a' => 1], ['a' => 'required']);

        $this->assertSame($request, $request->setValidator($custom));

        $this->assertSame(['a' => 1], $request->validated());
    }
}
