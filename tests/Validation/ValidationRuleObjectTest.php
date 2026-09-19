<?php

use PHPUnit\Framework\TestCase;

/**
 * Objek aturan yang menggantikan aturan berbentuk string.
 *
 * Ketiganya (`in`, `not_in`, `array`) pada akhirnya tetap menjadi string
 * aturan lewat `__toString()`, jadi yang dijaga di sini bentuk string itu -
 * termasuk pengutipan nilai, yang menentukan apakah nilai bertanda koma tetap
 * terbaca sebagai satu nilai atau pecah menjadi dua.
 */
class ValidationRuleObjectTest extends TestCase {
    /**
     * @param mixed $rules
     * @param mixed $value
     *
     * @return CValidation_Validator
     */
    private function validate($rules, $value) {
        return CValidation::createValidator(['x' => $value], ['x' => is_array($rules) ? $rules : [$rules]]);
    }

    /**
     * @return void
     */
    public function testInRendersAsAQuotedList() {
        $this->assertSame('in:"a","b"', (string) CValidation_Rule::in(['a', 'b']));
    }

    /**
     * @return void
     */
    public function testNotInRendersWithItsOwnRuleName() {
        $this->assertSame('not_in:"a","b"', (string) CValidation_Rule::notIn(['a', 'b']));
    }

    /**
     * Pengutipan itulah yang menjaga nilai bertanda koma tetap utuh, dan tanda
     * kutip di dalam nilai digandakan supaya tidak menutup kutipannya sendiri.
     *
     * @return void
     */
    public function testValuesWithCommasAndQuotesSurviveTheRendering() {
        $this->assertSame('in:"a""b","c,d"', (string) CValidation_Rule::in(['a"b', 'c,d']));
    }

    /**
     * @return void
     */
    public function testAnEmptyInListStillRendersItsPrefix() {
        $this->assertSame('in:', (string) CValidation_Rule::in([]));
    }

    /**
     * @return void
     */
    public function testInAcceptsAMatchingValueAndRejectsAnythingElse() {
        $this->assertFalse($this->validate(CValidation_Rule::in(['a', 'b']), 'b')->fails());
        $this->assertTrue($this->validate(CValidation_Rule::in(['a', 'b']), 'z')->fails());
    }

    /**
     * @return void
     */
    public function testNotInIsTheMirrorImage() {
        $this->assertTrue($this->validate(CValidation_Rule::notIn(['a', 'b']), 'b')->fails());
        $this->assertFalse($this->validate(CValidation_Rule::notIn(['a', 'b']), 'z')->fails());
    }

    /**
     * @return void
     */
    public function testArrayRuleWithoutKeysIsThePlainArrayRule() {
        $this->assertSame('array', (string) new CValidation_Rule_ArrayRule());
    }

    /**
     * @return void
     */
    public function testArrayRuleWithKeysListsThem() {
        $this->assertSame('array:a,b', (string) new CValidation_Rule_ArrayRule(['a', 'b']));
    }

    /**
     * @return void
     */
    public function testArrayRuleRejectsAnUnlistedKey() {
        $rule = new CValidation_Rule_ArrayRule(['a']);

        $this->assertFalse($this->validate($rule, ['a' => 1])->fails());
        $this->assertTrue($this->validate($rule, ['z' => 1])->fails());
    }

    /**
     * Closure menerima parameter ketiga berupa pemanggil kegagalan; pesan yang
     * diberikan padanya yang muncul sebagai galat.
     *
     * @return void
     */
    public function testClosureRuleReportsThroughTheFailCallback() {
        $rule = function ($attribute, $value, $fail) {
            if ($value < 10) {
                $fail('terlalu kecil');
            }
        };

        $validator = $this->validate($rule, 5);
        $this->assertTrue($validator->fails());
        $this->assertSame(['terlalu kecil'], $validator->errors()->all());
    }

    /**
     * @return void
     */
    public function testClosureRuleThatNeverFailsLetsTheValueThrough() {
        $rule = function ($attribute, $value, $fail) {
            if ($value < 10) {
                $fail('terlalu kecil');
            }
        };

        $this->assertFalse($this->validate($rule, 50)->fails());
    }

    /**
     * @return void
     */
    public function testClosureRuleReceivesTheAttributeName() {
        $terlihat = null;
        $rule = function ($attribute, $value, $fail) use (&$terlihat) {
            $terlihat = $attribute;
        };

        $this->validate($rule, 1)->fails();

        $this->assertSame('x', $terlihat);
    }

    /**
     * Kontrak `ValidationRule`/`InvokableRule` harus bisa diimplementasikan
     * tanpa type pada `$value` - di PHP 7.4 `mixed` dibaca sebagai nama kelas,
     * jadi implementor apa pun gagal TypeError saat dipanggil.
     *
     * @return void
     */
    public function testValidationRuleContractImplementorRunsWithoutTypedValue() {
        $rule = new class() implements CValidation_Contract_ValidationRuleInterface {
            public function validate(string $attribute, $value, Closure $fail): void {
                if ($value !== 'ok') {
                    $fail('bukan ok');
                }
            }
        };

        $this->assertFalse($this->validate($rule, 'ok')->fails());

        $validator = $this->validate($rule, 'lain');
        $this->assertTrue($validator->fails());
        $this->assertSame(['bukan ok'], $validator->errors()->all());
    }

    /**
     * @return void
     */
    public function testInvokableRuleContractImplementorRunsWithoutTypedValue() {
        $rule = new class() implements CValidation_Contract_InvokableRuleInterface {
            public function __invoke(string $attribute, $value, Closure $fail) {
                if ($value !== 'ok') {
                    $fail('bukan ok');
                }
            }
        };

        $this->assertFalse($this->validate($rule, 'ok')->fails());
        $this->assertTrue($this->validate($rule, 'lain')->fails());
    }

    /**
     * Closure menerima validator sebagai argumen keempat, dan `$fail()` bisa
     * dirantai `->translate()` seperti pada rule invokable.
     *
     * @return void
     */
    public function testClosureRuleReceivesTheValidatorAndCanTranslate() {
        $terlihat = null;
        $rule = function ($attribute, $value, $fail, $validator) use (&$terlihat) {
            $terlihat = $validator;
            $fail('validation.required')->translate();
        };

        $validator = $this->validate($rule, 1);
        $this->assertTrue($validator->fails());
        $this->assertSame($validator, $terlihat);
        // kunci terjemahan dijadikan pesan sungguhan, bukan kuncinya
        $this->assertStringNotContainsString('validation.required', $validator->errors()->first('x'));
    }

    /**
     * @return void
     */
    public function testClosureRuleStillExposesTheLastMessageProperty() {
        $rule = new CValidation_ClosureValidationRule(function ($attribute, $value, $fail) {
            $fail('pertama');
            $fail('kedua');
        });
        $rule->setValidator(CValidation::createValidator([], []));

        $this->assertFalse($rule->passes('x', 1));
        $this->assertSame(['pertama', 'kedua'], $rule->message());
        $this->assertSame('kedua', $rule->message);
    }
}
