<?php

use PHPUnit\Framework\TestCase;

/**
 * `CValidation_Rule_Password` menyusun syarat kekuatan kata sandi.
 *
 * Aturan yang diam-diam tidak berjalan di sini tidak menimbulkan galat apa pun
 * - ia hanya meloloskan kata sandi lemah, dan itu baru ketahuan jauh di
 * kemudian hari. Maka tiap syarat diuji dari dua sisi: satu nilai yang harus
 * ditolak dan satu yang harus lolos.
 *
 * `uncompromised()` sengaja tidak diuji: ia menanyakan cacahan kebocoran ke
 * layanan luar lewat jaringan, dan test yang bergantung padanya akan gagal
 * setiap kali jaringannya tidak ada.
 */
class ValidationPasswordRuleTest extends TestCase {
    /**
     * @var null|callable|CValidation_Rule_Password
     */
    private $defaultCallback;

    /**
     * `defaults()` menyimpan pilihannya sebagai properti statis bersama, jadi
     * nilainya dikembalikan supaya berkas ini tidak mengubah aturan kata sandi
     * bagi test lain yang berjalan sesudahnya.
     */
    protected function setUp(): void {
        parent::setUp();

        $property = new ReflectionProperty(CValidation_Rule_Password::class, 'defaultCallback');
        $property->setAccessible(true);
        $this->defaultCallback = $property->getValue();
    }

    protected function tearDown(): void {
        $property = new ReflectionProperty(CValidation_Rule_Password::class, 'defaultCallback');
        $property->setAccessible(true);
        $property->setValue(null, $this->defaultCallback);

        parent::tearDown();
    }

    /**
     * @param mixed $rules
     * @param mixed $value
     *
     * @return CValidation_Validator
     */
    private function validate($rules, $value) {
        return CValidation::createValidator(['pw' => $value], ['pw' => is_array($rules) ? $rules : [$rules]]);
    }

    /**
     * @param mixed $rules
     * @param mixed $value
     * @param mixed $message
     *
     * @return void
     */
    private function assertRejects($rules, $value, $message = '') {
        $this->assertTrue($this->validate($rules, $value)->fails(), $message ?: 'seharusnya ditolak: ' . var_export($value, true));
    }

    /**
     * @param mixed $rules
     * @param mixed $value
     *
     * @return void
     */
    private function assertAccepts($rules, $value) {
        $validator = $this->validate($rules, $value);
        $this->assertFalse(
            $validator->fails(),
            'seharusnya lolos: ' . var_export($value, true) . ' - ' . implode(' | ', $validator->errors()->all())
        );
    }

    /**
     * @return void
     */
    public function testMinimumLengthIsEnforced() {
        $rule = CValidation_Rule_Password::min(8);

        $this->assertRejects($rule, 'abc');
        $this->assertAccepts($rule, 'abcdefgh');
    }

    /**
     * @return void
     */
    public function testLettersDemandsAtLeastOneLetter() {
        $rule = CValidation_Rule_Password::min(4)->letters();

        $this->assertRejects($rule, '12345678');
        $this->assertAccepts($rule, 'abcd1234');
    }

    /**
     * @return void
     */
    public function testNumbersDemandsAtLeastOneDigit() {
        $rule = CValidation_Rule_Password::min(4)->numbers();

        $this->assertRejects($rule, 'abcdefgh');
        $this->assertAccepts($rule, 'abcdefg1');
    }

    /**
     * @return void
     */
    public function testSymbolsDemandsAtLeastOnePunctuationOrSign() {
        $rule = CValidation_Rule_Password::min(4)->symbols();

        $this->assertRejects($rule, 'abcdefgh');
        $this->assertAccepts($rule, 'abcdefg!');
    }

    /**
     * Bukan sekadar "ada huruf besar": harus ada keduanya.
     *
     * @return void
     */
    public function testMixedCaseDemandsBothCases() {
        $rule = CValidation_Rule_Password::min(4)->mixedCase();

        $this->assertRejects($rule, 'abcdefgh');
        $this->assertRejects($rule, 'ABCDEFGH');
        $this->assertAccepts($rule, 'abcDefgh');
    }

    /**
     * @return void
     */
    public function testRequirementsApplyTogether() {
        $rule = CValidation_Rule_Password::min(8)->letters()->numbers()->symbols()->mixedCase();

        $this->assertRejects($rule, 'abcdefgh');
        $this->assertAccepts($rule, 'Abcdefg1!');
    }

    /**
     * Tiap syarat yang tidak terpenuhi menyumbang pesannya sendiri, jadi
     * pengguna tahu semuanya sekaligus alih-alih satu per satu.
     *
     * @return void
     */
    public function testEveryUnmetRequirementReportsItsOwnMessage() {
        $rule = CValidation_Rule_Password::min(4)->letters()->numbers()->symbols()->mixedCase();
        $validator = $this->validate($rule, 'abcdefgh');

        $this->assertTrue($validator->fails());
        $this->assertCount(3, $validator->errors()->all(), 'mixedCase, symbols, dan numbers seharusnya semuanya melapor');
    }

    /**
     * @return void
     */
    public function testNonStringValueIsRejectedAsNotAString() {
        $validator = $this->validate(CValidation_Rule_Password::min(4)->letters(), 12345678);

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('string', implode(' ', $validator->errors()->all()));
    }

    /**
     * @return void
     */
    public function testRulesAppendsOrdinaryValidationRules() {
        $rule = CValidation_Rule_Password::min(4)->rules('max:10');

        $this->assertRejects($rule, 'abcdefghijklmno');
        $this->assertAccepts($rule, 'abcdefgh');
    }

    /**
     * @return void
     */
    public function testWithoutConfigurationTheDefaultIsMinimumEight() {
        $property = new ReflectionProperty(CValidation_Rule_Password::class, 'defaultCallback');
        $property->setAccessible(true);
        $property->setValue(null, null);

        $this->assertRejects(CValidation_Rule_Password::default(), 'abcdefg');
        $this->assertAccepts(CValidation_Rule_Password::default(), 'abcdefgh');
    }

    /**
     * @return void
     */
    public function testDefaultsAcceptsAClosureAndIsUsedByDefault() {
        CValidation_Rule_Password::defaults(function () {
            return CValidation_Rule_Password::min(12)->numbers();
        });

        $this->assertRejects(CValidation_Rule_Password::default(), 'abcdefghijkl');
        $this->assertAccepts(CValidation_Rule_Password::default(), 'abcdefghijk1');
    }

    /**
     * @return void
     */
    public function testDefaultsAlsoAcceptsARuleInstance() {
        CValidation_Rule_Password::defaults(CValidation_Rule_Password::min(20));

        $this->assertRejects(CValidation_Rule_Password::default(), str_repeat('a', 19));
        $this->assertAccepts(CValidation_Rule_Password::default(), str_repeat('a', 20));
    }

    /**
     * @return void
     */
    public function testDefaultsRefusesSomethingThatIsNeitherCallableNorARule() {
        $this->expectException(InvalidArgumentException::class);

        CValidation_Rule_Password::defaults('bukan callable');
    }

    /**
     * @return void
     */
    public function testRequiredAndSometimesPrependTheirOwnRule() {
        CValidation_Rule_Password::defaults(function () {
            return CValidation_Rule_Password::min(9);
        });

        $required = CValidation_Rule_Password::required();
        $sometimes = CValidation_Rule_Password::sometimes();

        $this->assertSame('required', $required[0]);
        $this->assertSame('sometimes', $sometimes[0]);
        $this->assertInstanceOf(CValidation_Rule_Password::class, $required[1]);
        $this->assertInstanceOf(CValidation_Rule_Password::class, $sometimes[1]);
    }

    /**
     * `sometimes` melewati aturan seluruhnya ketika kuncinya memang tidak ada,
     * sedangkan `required` menuntutnya.
     *
     * @return void
     */
    public function testSometimesSkipsAnAbsentFieldWhileRequiredDemandsIt() {
        CValidation_Rule_Password::defaults(function () {
            return CValidation_Rule_Password::min(8);
        });

        $this->assertFalse(
            CValidation::createValidator([], ['pw' => CValidation_Rule_Password::sometimes()])->fails()
        );
        $this->assertTrue(
            CValidation::createValidator([], ['pw' => CValidation_Rule_Password::required()])->fails()
        );
    }

    /**
     * @return void
     */
    public function testMaximumLengthIsEnforcedWhenSet() {
        $rule = CValidation_Rule_Password::min(4)->max(6);

        $this->assertAccepts($rule, 'abcdef');
        $this->assertRejects($rule, 'abcdefg');
        // tanpa max() tidak ada batas atas, seperti sebelumnya
        $this->assertAccepts(CValidation_Rule_Password::min(4), str_repeat('a', 200));
    }

    /**
     * @return void
     */
    public function testAppliedRulesDescribesTheCurrentState() {
        $rule = CValidation_Rule_Password::min(10)->max(64)->mixedCase()->numbers()->rules('not_in:password');

        $this->assertSame([
            'min' => 10,
            'max' => 64,
            'mixedCase' => true,
            'letters' => false,
            'numbers' => true,
            'symbols' => false,
            'uncompromised' => false,
            'compromisedThreshold' => 0,
            'customRules' => ['not_in:password'],
        ], $rule->appliedRules());
    }

    /**
     * @return void
     */
    public function testPasswordRulesStringForPasswordManagers() {
        $this->assertSame('minlength: 8;', CValidation_Rule_Password::min(8)->toPasswordRulesString());
        $this->assertSame(
            'minlength: 8; maxlength: 64; required: lower; required: upper; required: digit; required: special;',
            CValidation_Rule_Password::min(8)->max(64)->mixedCase()->numbers()->symbols()->toPasswordRulesString()
        );
        $this->assertSame('minlength: 8; required: lower;', CValidation_Rule_Password::min(8)->letters()->toPasswordRulesString());
    }
}
