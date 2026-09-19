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

    /*
    |--------------------------------------------------------------------------
    | Builder fluent: Numeric, StringRule, Date, Email, AnyOf
    |--------------------------------------------------------------------------
    */

    /**
     * @return void
     */
    public function testNumericBuilderRendersItsConstraints() {
        $this->assertSame('numeric', (string) CValidation_Rule::numeric());
        $this->assertSame(
            'numeric|min:1|max:10|multiple_of:0.5|gt:other',
            (string) CValidation_Rule::numeric()->min(1)->max(10)->multipleOf(0.5)->greaterThan('other')
        );
        // digits()/exactly() menyisipkan integer sekali walau dipanggil dua kali
        $this->assertSame('numeric|integer|digits:4|size:4', (string) CValidation_Rule::numeric()->digits(4)->exactly(4));
        $this->assertSame('numeric|integer:strict', (string) CValidation_Rule::numeric()->integer(true));
        $this->assertSame('numeric|decimal:2,4', (string) CValidation_Rule::numeric()->decimal(2, 4));
    }

    /**
     * @return void
     */
    public function testNumericBuilderIsUsableAsARule() {
        $this->assertFalse($this->validate(CValidation_Rule::numeric()->between(1, 10), '5')->fails());
        $this->assertTrue($this->validate(CValidation_Rule::numeric()->between(1, 10), '11')->fails());
        $this->assertTrue($this->validate(CValidation_Rule::numeric()->integer(true), '5')->fails());
        $this->assertFalse($this->validate(CValidation_Rule::numeric()->integer(true), 5)->fails());
    }

    /**
     * @return void
     */
    public function testStringBuilderRendersItsConstraints() {
        $this->assertSame(
            'string|min:3|max:10|alpha:ascii|starts_with:a,b|uppercase',
            (string) CValidation_Rule::string()->min(3)->max(10)->alpha(true)->startsWith('a', 'b')->uppercase()
        );
        $this->assertTrue($this->validate(CValidation_Rule::string()->startsWith('ab'), 'xy')->fails());
        $this->assertFalse($this->validate(CValidation_Rule::string()->startsWith('ab')->max(5), 'abc')->fails());
    }

    /**
     * @return void
     */
    public function testDateBuilderRendersItsConstraints() {
        $this->assertSame('date', (string) CValidation_Rule::date());
        $this->assertSame('date|after:today|before:2030-01-01', (string) CValidation_Rule::date()->afterToday()->before('2030-01-01'));
        $this->assertSame('date_format:Y-m-d H:i:s', (string) CValidation_Rule::dateTime());
        // DateTime diformat mengikuti format() yang dipilih
        $this->assertSame(
            'date_format:d/m/Y|after_or_equal:01/02/2026',
            (string) CValidation_Rule::date()->format('d/m/Y')->afterOrEqual(new DateTime('2026-02-01'))
        );
        $this->assertTrue($this->validate(CValidation_Rule::date()->afterToday(), '2000-01-01')->fails());
        $this->assertFalse($this->validate(CValidation_Rule::date()->betweenOrEqual('2026-01-01', '2026-12-31'), '2026-06-15')->fails());
    }

    /**
     * @return void
     */
    public function testEmailBuilderRunsTheEmailRuleWithItsOptions() {
        $this->assertFalse($this->validate(CValidation_Rule::email(), 'user@example.com')->fails());

        $validator = $this->validate(CValidation_Rule::email()->rfcCompliant(), 'bukan-email');
        $this->assertTrue($validator->fails());
        // pesan berasal dari rule email standar, bukan nama kelas
        $this->assertStringNotContainsString('CValidation_Rule_Email', $validator->errors()->first('x'));

        $this->assertTrue($this->validate(CValidation_Rule::email()->rules('max:5'), 'user@example.com')->fails());
    }

    /**
     * @return void
     */
    public function testEmailDefaultsAppliesToEveryDefaultCall() {
        CValidation_Rule_Email::defaults(function () {
            return CValidation_Rule::email()->strict();
        });

        try {
            $this->assertTrue(CValidation_Rule_Email::defaults()->strictRfcCompliant);
            $this->assertTrue(CValidation_Rule_Email::default()->strictRfcCompliant);
        } finally {
            CValidation_Rule_Email::$defaultCallback = null;
        }

        $this->assertFalse(CValidation_Rule_Email::defaults()->strictRfcCompliant);
    }

    /**
     * `any_of`: lolos kalau salah satu set rule lolos - "email ATAU nomor HP".
     *
     * @return void
     */
    public function testAnyOfPassesWhenOneRuleSetPasses() {
        $rule = CValidation_Rule::anyOf(['email', 'digits:10']);

        $this->assertFalse($this->validate($rule, 'user@example.com')->fails());
        $this->assertFalse($this->validate($rule, '0812345678')->fails());

        $validator = $this->validate($rule, 'bukan keduanya');
        $this->assertTrue($validator->fails());
        $this->assertNotEmpty($validator->errors()->first('x'));
    }

    /**
     * @return void
     */
    public function testAnyOfAcceptsAssociativeRuleSetsForArrayValues() {
        $rule = CValidation_Rule::anyOf([
            ['type' => 'required|in:email', 'email' => 'required|email'],
            ['type' => 'required|in:phone', 'phone' => 'required|digits:10'],
        ]);

        $this->assertFalse($this->validate($rule, ['type' => 'phone', 'phone' => '0812345678'])->fails());
        $this->assertTrue($this->validate($rule, ['type' => 'phone', 'phone' => 'x'])->fails());
    }

    /**
     * @return void
     */
    public function testAnyOfRejectsANonArrayDefinition() {
        $this->expectException(InvalidArgumentException::class);
        new CValidation_Rule_AnyOf('email');
    }
}
