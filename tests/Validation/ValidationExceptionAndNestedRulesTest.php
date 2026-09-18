<?php

use PHPUnit\Framework\TestCase;

/**
 * CValidation_Exception (withMessages, errors(), status/errorBag/redirectTo, pesan ringkasan),
 * Rule::forEach (rule per item bergantung nilainya), Rule::when bertingkat, dan Factory::validate()
 * yang melempar dengan data tervalidasi.
 */
class ValidationExceptionAndNestedRulesTest extends TestCase {
    public function testWithMessagesBuildsAnExceptionCarryingTheMessages() {
        $e = CValidation_Exception::withMessages(['email' => ['Email salah'], 'nama' => 'Nama wajib']);

        $this->assertInstanceOf(CValidation_Exception::class, $e);
        $this->assertSame(['email' => ['Email salah'], 'nama' => ['Nama wajib']], $e->errors());
        $this->assertSame(422, $e->status);
        $this->assertSame('default', $e->errorBag);
        $this->assertStringContainsString('Email salah', $e->getMessage(), 'pesan exception merangkum galat pertama');
    }

    public function testStatusErrorBagAndRedirectAreFluent() {
        $e = CValidation_Exception::withMessages(['a' => 'x']);

        $this->assertSame($e, $e->status(400)->errorBag('formUji')->redirectTo('/kembali'));
        $this->assertSame(400, $e->status);
        $this->assertSame('formUji', $e->errorBag);
        $this->assertSame('/kembali', $e->redirectTo);
    }

    public function testFactoryValidateThrowsWithTheValidatorAttached() {
        try {
            CValidation::factory()->validate(['nama' => ''], ['nama' => 'required']);
            $this->fail('harus melempar');
        } catch (CValidation_Exception $e) {
            $this->assertInstanceOf(CValidation_Validator::class, $e->validator);
            $this->assertArrayHasKey('nama', $e->errors());
        }

        $this->assertSame(['nama' => 'ok'], CValidation::factory()->validate(['nama' => 'ok', 'lain' => 1], ['nama' => 'required']), 'lolos → data tervalidasi saja');
    }

    public function testForEachAppliesRulesPerItemBasedOnItsValue() {
        $rules = ['items.*' => CValidation_Rule::forEach(function ($value, $attribute) {
            return carr::get($value, 'type') === 'angka' ? ['nilai' => 'required|integer'] : ['nilai' => 'required|string'];
        })];

        $this->assertTrue((new CValidation_Validator(['items' => [['type' => 'angka', 'nilai' => 5], ['type' => 'teks', 'nilai' => 'abc']]], $rules))->passes());
        $v = new CValidation_Validator(['items' => [['type' => 'angka', 'nilai' => 'bukan'], ['type' => 'teks', 'nilai' => 'abc']]], $rules);
        $this->assertTrue($v->fails());
        $this->assertSame(['items.0.nilai'], $v->errors()->keys());
    }

    public function testForEachCallbackReceivesTheAttributePath() {
        $seen = [];
        $rules = ['items.*' => CValidation_Rule::forEach(function ($value, $attribute) use (&$seen) {
            $seen[] = $attribute;

            return ['required'];
        })];
        (new CValidation_Validator(['items' => ['a', 'b']], $rules))->passes();

        $this->assertSame(['items.0', 'items.1'], $seen);
    }

    public function testConditionalRulesCanBeNestedAndUseTheData() {
        $rules = ['diskon' => CValidation_Rule::when(function ($input) {
            return $input->tipe === 'member';
        }, ['required', 'numeric', 'max:50'], ['prohibited'])];

        $this->assertTrue((new CValidation_Validator(['tipe' => 'member', 'diskon' => 20], $rules))->passes());
        $this->assertTrue((new CValidation_Validator(['tipe' => 'member', 'diskon' => 80], $rules))->fails());
        $this->assertTrue((new CValidation_Validator(['tipe' => 'umum', 'diskon' => 20], $rules))->fails(), 'default: prohibited');
        $this->assertTrue((new CValidation_Validator(['tipe' => 'umum'], $rules))->passes());
    }

    public function testRulesMayBeGivenAsArraysStringsOrMixed() {
        $data = ['n' => 'ab1'];
        $this->assertTrue((new CValidation_Validator($data, ['n' => 'required|alpha_num|max:5']))->passes());
        $this->assertTrue((new CValidation_Validator($data, ['n' => ['required', 'alpha_num', 'max:5']]))->passes());
        $this->assertTrue((new CValidation_Validator($data, ['n' => ['required', CValidation_Rule::in(['ab1']), 'max:5']]))->passes());
        $this->assertTrue((new CValidation_Validator(['n' => 'ab-1'], ['n' => 'alpha_num']))->fails());
    }

    public function testAddFailureRecordsAnErrorWithoutRunningARule() {
        $v = new CValidation_Validator(['n' => 'x'], ['n' => 'required']);
        $v->addFailure('n', 'In', ['a', 'b']);

        $this->assertTrue($v->errors()->has('n'));
        $this->assertSame(['In' => ['a', 'b']], $v->failed()['n'], 'nama rule dicatat apa adanya (StudlyCase seperti yang dipakai internal)');
        $this->assertFalse($v->fails(), 'passes()/fails() memvalidasi ulang dari nol - galat manual sebelumnya terbuang, jadi addFailure() dipakai SESUDAH passes(), mis. di after()');
        $this->assertFalse($v->errors()->has('n'));
    }

    public function testMessagesMayBeArraysPerAttributeAndRule() {
        $v = new CValidation_Validator(['a' => ''], ['a' => 'required|min:3'], ['a.required' => 'harus diisi', 'a.min' => 'terlalu pendek']);
        $v->fails();

        $this->assertSame(['harus diisi'], $v->errors()->get('a'), 'required implisit gagal → rule lain tidak dijalankan pada string kosong');
    }

    public function testValidatorGetRulesReflectsParsedRulesAndSetRulesReplaces() {
        $v = new CValidation_Validator(['a' => 1, 'b' => 2], ['a' => 'required|integer', 'b' => ['numeric']]);

        $this->assertSame(['a' => ['required', 'integer'], 'b' => ['numeric']], $v->getRules());
        $v->setRules(['b' => 'required|numeric|max:1']);
        $this->assertSame(['b' => ['required', 'numeric', 'max:1']], $v->getRules());
        $this->assertTrue($v->fails());
        $v->addRules(['c' => 'required']);
        $this->assertArrayHasKey('c', $v->getRules());
        $this->assertSame(['a' => 1, 'b' => 2], $v->getData());
    }
}
