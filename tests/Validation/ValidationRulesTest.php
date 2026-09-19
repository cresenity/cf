<?php

use PHPUnit\Framework\TestCase;

/**
 * Coverage for the individual per-rule validation LOGIC implemented in
 * CValidation_Trait_ValidateAttributeTrait (system/libraries/CValidation/Trait/ValidateAttributeTrait.php).
 *
 * Each test constructs a CValidation_Validator via the CValidation facade / CValidation_Factory
 * and asserts pass/fail behavior for representative valid/invalid inputs for ONE rule at a time.
 *
 * This intentionally does NOT touch Validator orchestration mechanics (sometimes/after/bail/custom
 * messages/Rule objects) - that is covered separately in tests/Validation/ValidatorTest.php.
 *
 * Rules requiring real DB access (unique/exists) or real network access (active_url) are skipped.
 */
class ValidationRulesTest extends TestCase {
    /**
     * @param array $data
     * @param array $rules
     *
     * @return CValidation_Validator
     */
    protected function validator(array $data, array $rules) {
        // Note: intentionally NOT using the CValidation::factory($data, $rules) facade here -
        // it guards on `$data != null` (loose comparison), which is true for an empty array,
        // so factory([], $rules) would return the CValidation_Factory itself instead of a
        // Validator instance. Going straight to the Factory sidesteps that gotcha.
        return CValidation_Factory::instance()->make($data, $rules);
    }

    protected function assertPasses(array $data, array $rules, $message = '') {
        $v = $this->validator($data, $rules);
        $this->assertTrue($v->passes(), $message ?: ('Expected rules ' . json_encode($rules) . ' to PASS for data ' . json_encode($data) . ' but failed with: ' . json_encode($v->errors()->all())));
    }

    protected function assertFailsRule(array $data, array $rules, $message = '') {
        $v = $this->validator($data, $rules);
        $this->assertTrue($v->fails(), $message ?: ('Expected rules ' . json_encode($rules) . ' to FAIL for data ' . json_encode($data) . ' but passed.'));
    }

    // ------------------------------------------------------------------
    // accepted / accepted_if
    // ------------------------------------------------------------------

    public function testValidateAccepted() {
        foreach (['yes', 'on', '1', 1, true, 'true'] as $value) {
            $this->assertPasses(['field' => $value], ['field' => 'accepted']);
        }

        foreach (['no', 'off', '0', 0, false, 'false', 'foo', null] as $value) {
            $this->assertFailsRule(['field' => $value], ['field' => 'accepted']);
        }
    }

    public function testValidateAcceptedIf() {
        $this->assertPasses(['field' => 'yes', 'other' => 'foo'], ['field' => 'accepted_if:other,foo']);
        $this->assertFailsRule(['field' => 'no', 'other' => 'foo'], ['field' => 'accepted_if:other,foo']);
        // other attribute doesn't match -> rule is skipped, passes regardless
        $this->assertPasses(['field' => 'no', 'other' => 'bar'], ['field' => 'accepted_if:other,foo']);
    }

    // ------------------------------------------------------------------
    // declined / declined_if
    // ------------------------------------------------------------------

    public function testValidateDeclined() {
        foreach (['no', 'off', '0', 0, false, 'false'] as $value) {
            $this->assertPasses(['field' => $value], ['field' => 'declined']);
        }

        foreach (['yes', 'on', '1', 1, true, 'true', 'foo'] as $value) {
            $this->assertFailsRule(['field' => $value], ['field' => 'declined']);
        }
    }

    public function testValidateDeclinedIf() {
        $this->assertPasses(['field' => 'no', 'other' => 'foo'], ['field' => 'declined_if:other,foo']);
        $this->assertFailsRule(['field' => 'yes', 'other' => 'foo'], ['field' => 'declined_if:other,foo']);
        $this->assertPasses(['field' => 'yes', 'other' => 'bar'], ['field' => 'declined_if:other,foo']);
    }

    // ------------------------------------------------------------------
    // active_url - SKIPPED (requires real DNS/network access)
    // ------------------------------------------------------------------

    // ------------------------------------------------------------------
    // ascii
    // ------------------------------------------------------------------

    public function testValidateAscii() {
        $this->assertPasses(['field' => 'Hello World! 123'], ['field' => 'ascii']);
        $this->assertFailsRule(['field' => 'héllo'], ['field' => 'ascii']);
        $this->assertFailsRule(['field' => 'こんにちは'], ['field' => 'ascii']);
    }

    // ------------------------------------------------------------------
    // before / before_or_equal / after / after_or_equal / date_equals
    // ------------------------------------------------------------------

    public function testValidateBefore() {
        $this->assertPasses(['field' => '2020-01-01'], ['field' => 'before:2020-01-02']);
        $this->assertFailsRule(['field' => '2020-01-02'], ['field' => 'before:2020-01-02']);
        $this->assertFailsRule(['field' => '2020-01-03'], ['field' => 'before:2020-01-02']);
    }

    public function testValidateBeforeOrEqual() {
        $this->assertPasses(['field' => '2020-01-02'], ['field' => 'before_or_equal:2020-01-02']);
        $this->assertPasses(['field' => '2020-01-01'], ['field' => 'before_or_equal:2020-01-02']);
        $this->assertFailsRule(['field' => '2020-01-03'], ['field' => 'before_or_equal:2020-01-02']);
    }

    public function testValidateAfter() {
        $this->assertPasses(['field' => '2020-01-03'], ['field' => 'after:2020-01-02']);
        $this->assertFailsRule(['field' => '2020-01-02'], ['field' => 'after:2020-01-02']);
        $this->assertFailsRule(['field' => '2020-01-01'], ['field' => 'after:2020-01-02']);
    }

    public function testValidateAfterOrEqual() {
        $this->assertPasses(['field' => '2020-01-02'], ['field' => 'after_or_equal:2020-01-02']);
        $this->assertPasses(['field' => '2020-01-03'], ['field' => 'after_or_equal:2020-01-02']);
        $this->assertFailsRule(['field' => '2020-01-01'], ['field' => 'after_or_equal:2020-01-02']);
    }

    public function testValidateAfterWithAnotherField() {
        $this->assertPasses(['start' => '2020-01-01', 'end' => '2020-01-02'], ['end' => 'after:start']);
        $this->assertFailsRule(['start' => '2020-01-02', 'end' => '2020-01-01'], ['end' => 'after:start']);
    }

    public function testValidateDateEquals() {
        $this->assertPasses(['field' => '2020-01-02'], ['field' => 'date_equals:2020-01-02']);
        $this->assertFailsRule(['field' => '2020-01-01'], ['field' => 'date_equals:2020-01-02']);
    }

    // ------------------------------------------------------------------
    // alpha / alpha_dash / alpha_num
    // ------------------------------------------------------------------

    public function testValidateAlpha() {
        $this->assertPasses(['field' => 'abcXYZ'], ['field' => 'alpha']);
        $this->assertFailsRule(['field' => 'abc123'], ['field' => 'alpha']);
        $this->assertFailsRule(['field' => 'abc def'], ['field' => 'alpha']);
        // Note: an empty string is NOT run through non-implicit rules like "alpha" at all
        // (CValidation_Validator::presentOrRuleIsImplicit() skips non-implicit rules when the
        // value is an empty string) - so this combination actually PASSES, matching real Laravel.
        $this->assertPasses(['field' => ''], ['field' => 'alpha']);
        // combined with "required" (implicit) it correctly fails on the empty string.
        $this->assertFailsRule(['field' => ''], ['field' => 'required|alpha']);
    }

    public function testValidateAlphaAsciiOption() {
        $this->assertPasses(['field' => 'abcXYZ'], ['field' => 'alpha:ascii']);
        $this->assertFailsRule(['field' => 'héllo'], ['field' => 'alpha:ascii']);
    }

    public function testValidateAlphaDash() {
        $this->assertPasses(['field' => 'abc-123_XYZ'], ['field' => 'alpha_dash']);
        $this->assertFailsRule(['field' => 'abc 123'], ['field' => 'alpha_dash']);
        $this->assertFailsRule(['field' => 'abc.123'], ['field' => 'alpha_dash']);
    }

    public function testValidateAlphaNum() {
        $this->assertPasses(['field' => 'abc123'], ['field' => 'alpha_num']);
        $this->assertFailsRule(['field' => 'abc-123'], ['field' => 'alpha_num']);
        $this->assertFailsRule(['field' => 'abc 123'], ['field' => 'alpha_num']);
    }

    // ------------------------------------------------------------------
    // array / required_array_keys
    // ------------------------------------------------------------------

    public function testValidateArray() {
        $this->assertPasses(['field' => [1, 2, 3]], ['field' => 'array']);
        $this->assertFailsRule(['field' => 'not an array'], ['field' => 'array']);
    }

    public function testValidateArrayWithAllowedKeys() {
        $this->assertPasses(['field' => ['a' => 1, 'b' => 2]], ['field' => 'array:a,b']);
        $this->assertFailsRule(['field' => ['a' => 1, 'c' => 2]], ['field' => 'array:a,b']);
    }

    public function testValidateRequiredArrayKeys() {
        $this->assertPasses(['field' => ['a' => 1, 'b' => 2]], ['field' => 'required_array_keys:a,b']);
        $this->assertFailsRule(['field' => ['a' => 1]], ['field' => 'required_array_keys:a,b']);
        $this->assertFailsRule(['field' => 'not-array'], ['field' => 'required_array_keys:a,b']);
    }

    // ------------------------------------------------------------------
    // between
    // ------------------------------------------------------------------

    public function testValidateBetweenNumeric() {
        $this->assertPasses(['field' => 5], ['field' => 'numeric|between:1,10']);
        $this->assertPasses(['field' => 1], ['field' => 'numeric|between:1,10']);
        $this->assertPasses(['field' => 10], ['field' => 'numeric|between:1,10']);
        $this->assertFailsRule(['field' => 0], ['field' => 'numeric|between:1,10']);
        $this->assertFailsRule(['field' => 11], ['field' => 'numeric|between:1,10']);
    }

    public function testValidateBetweenString() {
        $this->assertPasses(['field' => 'abcde'], ['field' => 'between:3,6']);
        $this->assertFailsRule(['field' => 'ab'], ['field' => 'between:3,6']);
        $this->assertFailsRule(['field' => 'abcdefgh'], ['field' => 'between:3,6']);
    }

    public function testValidateBetweenArray() {
        $this->assertPasses(['field' => [1, 2, 3]], ['field' => 'array|between:2,4']);
        $this->assertFailsRule(['field' => [1]], ['field' => 'array|between:2,4']);
    }

    // ------------------------------------------------------------------
    // boolean
    // ------------------------------------------------------------------

    public function testValidateBoolean() {
        foreach ([true, false, 0, 1, '0', '1'] as $value) {
            $this->assertPasses(['field' => $value], ['field' => 'boolean']);
        }

        foreach (['yes', 'no', 2, '2', 'true', 'false', null] as $value) {
            $this->assertFailsRule(['field' => $value], ['field' => 'boolean']);
        }
    }

    // ------------------------------------------------------------------
    // confirmed
    // ------------------------------------------------------------------

    public function testValidateConfirmed() {
        $this->assertPasses(['field' => 'secret', 'field_confirmation' => 'secret'], ['field' => 'confirmed']);
        $this->assertFailsRule(['field' => 'secret', 'field_confirmation' => 'other'], ['field' => 'confirmed']);
        $this->assertFailsRule(['field' => 'secret'], ['field' => 'confirmed']);
    }

    // ------------------------------------------------------------------
    // date / date_format
    // ------------------------------------------------------------------

    public function testValidateDate() {
        $this->assertPasses(['field' => '2020-01-01'], ['field' => 'date']);
        $this->assertPasses(['field' => 'January 1st 2020'], ['field' => 'date']);
        $this->assertFailsRule(['field' => 'not a date'], ['field' => 'date']);
        $this->assertFailsRule(['field' => '2020-13-01'], ['field' => 'date']);
        $this->assertFailsRule(['field' => '2020-02-30'], ['field' => 'date']);
    }

    public function testValidateDateWithDateTimeInstance() {
        $this->assertPasses(['field' => new DateTime('now')], ['field' => 'date']);
    }

    public function testValidateDateFormat() {
        $this->assertPasses(['field' => '2020-01-01'], ['field' => 'date_format:Y-m-d']);
        $this->assertFailsRule(['field' => '01/01/2020'], ['field' => 'date_format:Y-m-d']);
        $this->assertFailsRule(['field' => '2020-01-01 10:00:00'], ['field' => 'date_format:Y-m-d']);
    }

    // ------------------------------------------------------------------
    // decimal
    // ------------------------------------------------------------------

    public function testValidateDecimalExact() {
        $this->assertPasses(['field' => '1.23'], ['field' => 'decimal:2']);
        $this->assertFailsRule(['field' => '1.2'], ['field' => 'decimal:2']);
        $this->assertFailsRule(['field' => '1.234'], ['field' => 'decimal:2']);
    }

    public function testValidateDecimalRange() {
        $this->assertPasses(['field' => '1.2'], ['field' => 'decimal:1,3']);
        $this->assertPasses(['field' => '1.234'], ['field' => 'decimal:1,3']);
        $this->assertFailsRule(['field' => '1.2345'], ['field' => 'decimal:1,3']);
    }

    // ------------------------------------------------------------------
    // different
    // ------------------------------------------------------------------

    public function testValidateDifferent() {
        $this->assertPasses(['field' => 'foo', 'other' => 'bar'], ['field' => 'different:other']);
        $this->assertFailsRule(['field' => 'foo', 'other' => 'foo'], ['field' => 'different:other']);
    }

    // ------------------------------------------------------------------
    // digits / digits_between
    // ------------------------------------------------------------------

    public function testValidateDigits() {
        $this->assertPasses(['field' => '12345'], ['field' => 'digits:5']);
        $this->assertFailsRule(['field' => '1234'], ['field' => 'digits:5']);
        $this->assertFailsRule(['field' => '123456'], ['field' => 'digits:5']);
        $this->assertFailsRule(['field' => '12a45'], ['field' => 'digits:5']);
    }

    public function testValidateDigitsBetween() {
        $this->assertPasses(['field' => '123'], ['field' => 'digits_between:2,4']);
        $this->assertFailsRule(['field' => '1'], ['field' => 'digits_between:2,4']);
        $this->assertFailsRule(['field' => '12345'], ['field' => 'digits_between:2,4']);
    }

    // ------------------------------------------------------------------
    // dimensions - SKIPPED (requires real image files)
    // ------------------------------------------------------------------

    // ------------------------------------------------------------------
    // distinct
    // ------------------------------------------------------------------

    public function testValidateDistinct() {
        $this->assertPasses(['field' => ['a', 'b', 'c']], ['field.*' => 'distinct']);
        $this->assertFailsRule(['field' => ['a', 'b', 'a']], ['field.*' => 'distinct']);
    }

    public function testValidateDistinctIgnoreCase() {
        $this->assertPasses(['field' => ['a', 'b', 'c']], ['field.*' => 'distinct:ignore_case']);
        $this->assertFailsRule(['field' => ['a', 'A', 'c']], ['field.*' => 'distinct:ignore_case']);
    }

    public function testValidateDistinctStrict() {
        $this->assertPasses(['field' => ['1', 1, 'a']], ['field.*' => 'distinct:strict']);
        $this->assertFailsRule(['field' => ['1', '1', 'a']], ['field.*' => 'distinct:strict']);
    }

    // ------------------------------------------------------------------
    // email
    // ------------------------------------------------------------------

    public function testValidateEmail() {
        $this->assertPasses(['field' => 'foo@example.com'], ['field' => 'email']);
        $this->assertFailsRule(['field' => 'not-an-email'], ['field' => 'email']);
        $this->assertFailsRule(['field' => 'foo@'], ['field' => 'email']);
        $this->assertFailsRule(['field' => 123], ['field' => 'email']);
    }

    public function testValidateEmailFilter() {
        $this->assertPasses(['field' => 'foo@example.com'], ['field' => 'email:filter']);
        $this->assertFailsRule(['field' => 'not-an-email'], ['field' => 'email:filter']);
    }

    // ------------------------------------------------------------------
    // exists / unique - SKIPPED (require real DB access / presence verifier)
    // ------------------------------------------------------------------

    // ------------------------------------------------------------------
    // file - SKIPPED (requires real uploaded file / Symfony File instance)
    // ------------------------------------------------------------------

    // ------------------------------------------------------------------
    // filled
    // ------------------------------------------------------------------

    public function testValidateFilled() {
        $this->assertPasses(['field' => 'value'], ['field' => 'filled']);
        $this->assertFailsRule(['field' => ''], ['field' => 'filled']);
        $this->assertFailsRule(['field' => null], ['field' => 'filled']);
        // attribute not present at all -> passes (nothing to validate)
        $this->assertPasses([], ['field' => 'filled']);
    }

    // ------------------------------------------------------------------
    // gt / lt / gte / lte
    // ------------------------------------------------------------------

    public function testValidateGtNumeric() {
        $this->assertPasses(['field' => 10, 'other' => 5], ['field' => 'numeric|gt:other']);
        $this->assertFailsRule(['field' => 5, 'other' => 10], ['field' => 'numeric|gt:other']);
        $this->assertFailsRule(['field' => 5, 'other' => 5], ['field' => 'numeric|gt:other']);
    }

    public function testValidateGtWithNumericLiteral() {
        // when parameter is a numeric literal (not an attribute), gt compares against getValue()
        // which returns the literal itself when no data attribute matches
        $this->assertPasses(['field' => 10], ['field' => 'numeric|gt:5']);
        $this->assertFailsRule(['field' => 3], ['field' => 'numeric|gt:5']);
    }

    public function testValidateGtString() {
        $this->assertPasses(['field' => 'abcdef', 'other' => 'abc'], ['field' => 'gt:other']);
        $this->assertFailsRule(['field' => 'ab', 'other' => 'abc'], ['field' => 'gt:other']);
    }

    public function testValidateLtNumeric() {
        $this->assertPasses(['field' => 5, 'other' => 10], ['field' => 'numeric|lt:other']);
        $this->assertFailsRule(['field' => 10, 'other' => 5], ['field' => 'numeric|lt:other']);
    }

    public function testValidateGteNumeric() {
        $this->assertPasses(['field' => 10, 'other' => 10], ['field' => 'numeric|gte:other']);
        $this->assertPasses(['field' => 11, 'other' => 10], ['field' => 'numeric|gte:other']);
        $this->assertFailsRule(['field' => 9, 'other' => 10], ['field' => 'numeric|gte:other']);
    }

    public function testValidateLteNumeric() {
        $this->assertPasses(['field' => 10, 'other' => 10], ['field' => 'numeric|lte:other']);
        $this->assertPasses(['field' => 9, 'other' => 10], ['field' => 'numeric|lte:other']);
        $this->assertFailsRule(['field' => 11, 'other' => 10], ['field' => 'numeric|lte:other']);
    }

    // ------------------------------------------------------------------
    // lowercase / uppercase
    // ------------------------------------------------------------------

    public function testValidateLowercase() {
        $this->assertPasses(['field' => 'hello world'], ['field' => 'lowercase']);
        $this->assertFailsRule(['field' => 'Hello World'], ['field' => 'lowercase']);
    }

    public function testValidateUppercase() {
        $this->assertPasses(['field' => 'HELLO WORLD'], ['field' => 'uppercase']);
        $this->assertFailsRule(['field' => 'Hello World'], ['field' => 'uppercase']);
    }

    // ------------------------------------------------------------------
    // image / mimes / mimetypes - SKIPPED (require real uploaded files)
    // ------------------------------------------------------------------

    // ------------------------------------------------------------------
    // in / not_in / in_array
    // ------------------------------------------------------------------

    public function testValidateIn() {
        $this->assertPasses(['field' => 'foo'], ['field' => 'in:foo,bar,baz']);
        $this->assertFailsRule(['field' => 'qux'], ['field' => 'in:foo,bar,baz']);
    }

    public function testValidateInWithArrayValue() {
        $this->assertPasses(['field' => ['foo', 'bar']], ['field' => 'array', 'field.*' => 'in:foo,bar,baz']);
    }

    public function testValidateNotIn() {
        $this->assertPasses(['field' => 'qux'], ['field' => 'not_in:foo,bar,baz']);
        $this->assertFailsRule(['field' => 'foo'], ['field' => 'not_in:foo,bar,baz']);
    }

    public function testValidateInArray() {
        $this->assertPasses(
            ['field' => 'foo', 'other' => ['foo', 'bar']],
            ['field' => 'in_array:other.*']
        );
        $this->assertFailsRule(
            ['field' => 'baz', 'other' => ['foo', 'bar']],
            ['field' => 'in_array:other.*']
        );
    }

    // ------------------------------------------------------------------
    // integer
    // ------------------------------------------------------------------

    public function testValidateInteger() {
        $this->assertPasses(['field' => 123], ['field' => 'integer']);
        $this->assertPasses(['field' => '123'], ['field' => 'integer']);
        $this->assertPasses(['field' => '-123'], ['field' => 'integer']);
        $this->assertFailsRule(['field' => '123.45'], ['field' => 'integer']);
        $this->assertFailsRule(['field' => 'abc'], ['field' => 'integer']);
    }

    // ------------------------------------------------------------------
    // ip / ipv4 / ipv6 / mac_address
    // ------------------------------------------------------------------

    public function testValidateIp() {
        $this->assertPasses(['field' => '127.0.0.1'], ['field' => 'ip']);
        $this->assertPasses(['field' => '::1'], ['field' => 'ip']);
        $this->assertFailsRule(['field' => 'not-an-ip'], ['field' => 'ip']);
        $this->assertFailsRule(['field' => '999.999.999.999'], ['field' => 'ip']);
    }

    public function testValidateIpv4() {
        $this->assertPasses(['field' => '127.0.0.1'], ['field' => 'ipv4']);
        $this->assertFailsRule(['field' => '::1'], ['field' => 'ipv4']);
    }

    public function testValidateIpv6() {
        $this->assertPasses(['field' => '::1'], ['field' => 'ipv6']);
        $this->assertFailsRule(['field' => '127.0.0.1'], ['field' => 'ipv6']);
    }

    public function testValidateMacAddress() {
        $this->assertPasses(['field' => '01:23:45:67:89:ab'], ['field' => 'mac_address']);
        $this->assertFailsRule(['field' => 'not-a-mac'], ['field' => 'mac_address']);
    }

    // ------------------------------------------------------------------
    // json
    // ------------------------------------------------------------------

    public function testValidateJson() {
        $this->assertPasses(['field' => '{"a":1}'], ['field' => 'json']);
        $this->assertPasses(['field' => '[1,2,3]'], ['field' => 'json']);
        $this->assertFailsRule(['field' => 'not json'], ['field' => 'json']);
        $this->assertFailsRule(['field' => ['a' => 1]], ['field' => 'json']);
    }

    // ------------------------------------------------------------------
    // max / max_digits / min / min_digits / size
    // ------------------------------------------------------------------

    public function testValidateMaxString() {
        $this->assertPasses(['field' => 'abc'], ['field' => 'max:5']);
        $this->assertFailsRule(['field' => 'abcdef'], ['field' => 'max:5']);
    }

    public function testValidateMaxNumeric() {
        $this->assertPasses(['field' => 5], ['field' => 'numeric|max:5']);
        $this->assertFailsRule(['field' => 6], ['field' => 'numeric|max:5']);
    }

    public function testValidateMaxArray() {
        $this->assertPasses(['field' => [1, 2]], ['field' => 'array|max:3']);
        $this->assertFailsRule(['field' => [1, 2, 3, 4]], ['field' => 'array|max:3']);
    }

    public function testValidateMaxDigits() {
        $this->assertPasses(['field' => '123'], ['field' => 'max_digits:3']);
        $this->assertFailsRule(['field' => '1234'], ['field' => 'max_digits:3']);
        $this->assertFailsRule(['field' => '12a'], ['field' => 'max_digits:3']);
    }

    public function testValidateMinString() {
        $this->assertPasses(['field' => 'abcde'], ['field' => 'min:5']);
        $this->assertFailsRule(['field' => 'abc'], ['field' => 'min:5']);
    }

    public function testValidateMinNumeric() {
        $this->assertPasses(['field' => 5], ['field' => 'numeric|min:5']);
        $this->assertFailsRule(['field' => 4], ['field' => 'numeric|min:5']);
    }

    public function testValidateMinDigits() {
        $this->assertPasses(['field' => '1234'], ['field' => 'min_digits:3']);
        $this->assertFailsRule(['field' => '12'], ['field' => 'min_digits:3']);
    }

    public function testValidateSize() {
        $this->assertPasses(['field' => 'abcde'], ['field' => 'size:5']);
        $this->assertFailsRule(['field' => 'abcd'], ['field' => 'size:5']);
        $this->assertPasses(['field' => 5], ['field' => 'numeric|size:5']);
        $this->assertPasses(['field' => [1, 2, 3]], ['field' => 'array|size:3']);
    }

    // ------------------------------------------------------------------
    // missing / missing_if / missing_unless / missing_with / missing_with_all
    // ------------------------------------------------------------------

    public function testValidateMissing() {
        $this->assertPasses([], ['field' => 'missing']);
        $this->assertFailsRule(['field' => 'value'], ['field' => 'missing']);
        $this->assertFailsRule(['field' => null], ['field' => 'missing']);
    }

    public function testValidateMissingIf() {
        $this->assertPasses(['other' => 'foo'], ['field' => 'missing_if:other,foo']);
        $this->assertFailsRule(['field' => 'value', 'other' => 'foo'], ['field' => 'missing_if:other,foo']);
        $this->assertPasses(['field' => 'value', 'other' => 'bar'], ['field' => 'missing_if:other,foo']);
    }

    public function testValidateMissingUnless() {
        $this->assertPasses(['other' => 'bar'], ['field' => 'missing_unless:other,foo']);
        $this->assertFailsRule(['field' => 'value', 'other' => 'bar'], ['field' => 'missing_unless:other,foo']);
        $this->assertPasses(['field' => 'value', 'other' => 'foo'], ['field' => 'missing_unless:other,foo']);
    }

    public function testValidateMissingWith() {
        $this->assertPasses(['other' => 'x'], ['field' => 'missing_with:other']);
        $this->assertFailsRule(['field' => 'value', 'other' => 'x'], ['field' => 'missing_with:other']);
        // "other" absent -> rule not triggered
        $this->assertPasses(['field' => 'value'], ['field' => 'missing_with:other']);
    }

    public function testValidateMissingWithAll() {
        $this->assertFailsRule(
            ['field' => 'value', 'a' => 1, 'b' => 2],
            ['field' => 'missing_with_all:a,b']
        );
        // only some of a,b present -> not triggered
        $this->assertPasses(
            ['field' => 'value', 'a' => 1],
            ['field' => 'missing_with_all:a,b']
        );
    }

    // ------------------------------------------------------------------
    // multiple_of
    // ------------------------------------------------------------------

    public function testValidateMultipleOf() {
        $this->assertPasses(['field' => 10], ['field' => 'multiple_of:5']);
        $this->assertFailsRule(['field' => 7], ['field' => 'multiple_of:5']);
        $this->assertPasses(['field' => 0], ['field' => 'multiple_of:5']);
        $this->assertFailsRule(['field' => 5], ['field' => 'multiple_of:0']);
    }

    // ------------------------------------------------------------------
    // numeric
    // ------------------------------------------------------------------

    public function testValidateNumeric() {
        $this->assertPasses(['field' => 123], ['field' => 'numeric']);
        $this->assertPasses(['field' => '123.45'], ['field' => 'numeric']);
        $this->assertPasses(['field' => '-5'], ['field' => 'numeric']);
        $this->assertFailsRule(['field' => 'abc'], ['field' => 'numeric']);
        $this->assertFailsRule(['field' => '12abc'], ['field' => 'numeric']);
    }

    // ------------------------------------------------------------------
    // present
    // ------------------------------------------------------------------

    public function testValidatePresent() {
        $this->assertPasses(['field' => null], ['field' => 'present']);
        $this->assertPasses(['field' => ''], ['field' => 'present']);
        $this->assertFailsRule([], ['field' => 'present']);
    }

    // ------------------------------------------------------------------
    // regex / not_regex
    // ------------------------------------------------------------------

    public function testValidateRegex() {
        $this->assertPasses(['field' => 'abc123'], ['field' => 'regex:/^[a-z0-9]+$/']);
        $this->assertFailsRule(['field' => 'ABC123'], ['field' => 'regex:/^[a-z0-9]+$/']);
        $this->assertFailsRule(['field' => ['array']], ['field' => 'regex:/^[a-z0-9]+$/']);
    }

    public function testValidateNotRegex() {
        $this->assertPasses(['field' => 'ABC123'], ['field' => 'not_regex:/^[a-z0-9]+$/']);
        $this->assertFailsRule(['field' => 'abc123'], ['field' => 'not_regex:/^[a-z0-9]+$/']);
    }

    // ------------------------------------------------------------------
    // required
    // ------------------------------------------------------------------

    public function testValidateRequired() {
        $this->assertPasses(['field' => 'value'], ['field' => 'required']);
        $this->assertPasses(['field' => 0], ['field' => 'required']);
        $this->assertPasses(['field' => false], ['field' => 'required']);
        $this->assertFailsRule(['field' => null], ['field' => 'required']);
        $this->assertFailsRule(['field' => ''], ['field' => 'required']);
        $this->assertFailsRule(['field' => '   '], ['field' => 'required']);
        $this->assertFailsRule(['field' => []], ['field' => 'required']);
        $this->assertFailsRule([], ['field' => 'required']);
    }

    // ------------------------------------------------------------------
    // required_if / required_if_accepted / required_unless
    // ------------------------------------------------------------------

    public function testValidateRequiredIf() {
        $this->assertFailsRule(['other' => 'foo'], ['field' => 'required_if:other,foo']);
        $this->assertPasses(['field' => 'value', 'other' => 'foo'], ['field' => 'required_if:other,foo']);
        $this->assertPasses(['other' => 'bar'], ['field' => 'required_if:other,foo']);
    }

    public function testValidateRequiredIfAccepted() {
        $this->assertFailsRule(['other' => 'yes'], ['field' => 'required_if_accepted:other']);
        $this->assertPasses(['field' => 'value', 'other' => 'yes'], ['field' => 'required_if_accepted:other']);
        $this->assertPasses(['other' => 'no'], ['field' => 'required_if_accepted:other']);
    }

    public function testValidateRequiredUnless() {
        $this->assertFailsRule(['other' => 'bar'], ['field' => 'required_unless:other,foo']);
        $this->assertPasses(['other' => 'foo'], ['field' => 'required_unless:other,foo']);
        $this->assertPasses(['field' => 'value', 'other' => 'bar'], ['field' => 'required_unless:other,foo']);
    }

    // ------------------------------------------------------------------
    // required_with / required_with_all / required_without / required_without_all
    // ------------------------------------------------------------------

    public function testValidateRequiredWith() {
        $this->assertFailsRule(['other' => 'x'], ['field' => 'required_with:other']);
        $this->assertPasses(['field' => 'value', 'other' => 'x'], ['field' => 'required_with:other']);
        $this->assertPasses([], ['field' => 'required_with:other']);
    }

    public function testValidateRequiredWithAll() {
        $this->assertFailsRule(['a' => 1, 'b' => 2], ['field' => 'required_with_all:a,b']);
        $this->assertPasses(['a' => 1], ['field' => 'required_with_all:a,b']);
        $this->assertPasses(['field' => 'value', 'a' => 1, 'b' => 2], ['field' => 'required_with_all:a,b']);
    }

    public function testValidateRequiredWithout() {
        $this->assertFailsRule([], ['field' => 'required_without:other']);
        $this->assertPasses(['other' => 'x'], ['field' => 'required_without:other']);
        $this->assertPasses(['field' => 'value'], ['field' => 'required_without:other']);
    }

    public function testValidateRequiredWithoutAll() {
        $this->assertFailsRule([], ['field' => 'required_without_all:a,b']);
        $this->assertPasses(['a' => 1], ['field' => 'required_without_all:a,b']);
        $this->assertPasses(['field' => 'value'], ['field' => 'required_without_all:a,b']);
    }

    // ------------------------------------------------------------------
    // prohibited / prohibited_if / prohibited_unless / prohibits
    // ------------------------------------------------------------------

    public function testValidateProhibited() {
        $this->assertPasses([], ['field' => 'prohibited']);
        $this->assertPasses(['field' => ''], ['field' => 'prohibited']);
        $this->assertFailsRule(['field' => 'value'], ['field' => 'prohibited']);
    }

    public function testValidateProhibitedIf() {
        $this->assertFailsRule(['field' => 'value', 'other' => 'foo'], ['field' => 'prohibited_if:other,foo']);
        $this->assertPasses(['other' => 'foo'], ['field' => 'prohibited_if:other,foo']);
        $this->assertPasses(['field' => 'value', 'other' => 'bar'], ['field' => 'prohibited_if:other,foo']);
    }

    public function testValidateProhibitedUnless() {
        $this->assertFailsRule(['field' => 'value', 'other' => 'bar'], ['field' => 'prohibited_unless:other,foo']);
        $this->assertPasses(['field' => 'value', 'other' => 'foo'], ['field' => 'prohibited_unless:other,foo']);
        $this->assertPasses(['other' => 'bar'], ['field' => 'prohibited_unless:other,foo']);
    }

    public function testValidateProhibits() {
        $this->assertFailsRule(['field' => 'value', 'other' => 'x'], ['field' => 'prohibits:other']);
        $this->assertPasses(['field' => 'value'], ['field' => 'prohibits:other']);
        $this->assertPasses(['other' => 'x'], ['field' => 'prohibits:other']);
    }

    // ------------------------------------------------------------------
    // same
    // ------------------------------------------------------------------

    public function testValidateSame() {
        $this->assertPasses(['field' => 'foo', 'other' => 'foo'], ['field' => 'same:other']);
        $this->assertFailsRule(['field' => 'foo', 'other' => 'bar'], ['field' => 'same:other']);
        // strict comparison: '1' !== 1
        $this->assertFailsRule(['field' => '1', 'other' => 1], ['field' => 'same:other']);
    }

    // ------------------------------------------------------------------
    // starts_with / doesnt_start_with / ends_with / doesnt_end_with
    // ------------------------------------------------------------------

    public function testValidateStartsWith() {
        $this->assertPasses(['field' => 'hello world'], ['field' => 'starts_with:hello']);
        $this->assertFailsRule(['field' => 'world hello'], ['field' => 'starts_with:hello']);
    }

    public function testValidateDoesntStartWith() {
        $this->assertPasses(['field' => 'world hello'], ['field' => 'doesnt_start_with:hello']);
        $this->assertFailsRule(['field' => 'hello world'], ['field' => 'doesnt_start_with:hello']);
    }

    public function testValidateEndsWith() {
        $this->assertPasses(['field' => 'hello world'], ['field' => 'ends_with:world']);
        $this->assertFailsRule(['field' => 'world hello'], ['field' => 'ends_with:world']);
    }

    public function testValidateDoesntEndWith() {
        $this->assertPasses(['field' => 'world hello'], ['field' => 'doesnt_end_with:world']);
        $this->assertFailsRule(['field' => 'hello world'], ['field' => 'doesnt_end_with:world']);
    }

    // ------------------------------------------------------------------
    // string
    // ------------------------------------------------------------------

    public function testValidateString() {
        $this->assertPasses(['field' => 'hello'], ['field' => 'string']);
        $this->assertFailsRule(['field' => 123], ['field' => 'string']);
        $this->assertFailsRule(['field' => ['a']], ['field' => 'string']);
    }

    // ------------------------------------------------------------------
    // timezone
    // ------------------------------------------------------------------

    public function testValidateTimezone() {
        $this->assertPasses(['field' => 'Asia/Jakarta'], ['field' => 'timezone']);
        $this->assertFailsRule(['field' => 'Not/AZone'], ['field' => 'timezone']);
    }

    // ------------------------------------------------------------------
    // url
    // ------------------------------------------------------------------

    public function testValidateUrl() {
        $this->assertPasses(['field' => 'https://example.com'], ['field' => 'url']);
        $this->assertPasses(['field' => 'http://example.com/path?query=1'], ['field' => 'url']);
        $this->assertFailsRule(['field' => 'not a url'], ['field' => 'url']);
        $this->assertFailsRule(['field' => 'example.com'], ['field' => 'url']);
    }

    // ------------------------------------------------------------------
    // ulid / uuid
    // ------------------------------------------------------------------

    public function testValidateUlid() {
        $this->assertPasses(['field' => '01ARZ3NDEKTSV4RRFFQ69G5FAV'], ['field' => 'ulid']);
        $this->assertFailsRule(['field' => 'not-a-ulid'], ['field' => 'ulid']);
    }

    public function testValidateUuid() {
        $this->assertPasses(['field' => '550e8400-e29b-41d4-a716-446655440000'], ['field' => 'uuid']);
        $this->assertFailsRule(['field' => 'not-a-uuid'], ['field' => 'uuid']);
    }

    // ------------------------------------------------------------------
    // nullable / sometimes / bail / exclude / exclude_if / exclude_unless /
    // exclude_with / exclude_without - these are pseudo-rules that always
    // pass by themselves (they steer orchestration, exercised in
    // ValidatorTest.php); we only sanity-check the trait methods used to
    // guard other rules where relevant, e.g. nullable skips other rules.
    // ------------------------------------------------------------------

    public function testValidateNullableAllowsNullToSkipOtherRules() {
        $this->assertPasses(['field' => null], ['field' => 'nullable|email']);
        $this->assertFailsRule(['field' => 'not-an-email'], ['field' => 'nullable|email']);
    }

    public function testValidateExcludeIf() {
        // when excluded, the other rules on the attribute are not run - so an
        // otherwise-invalid value still passes overall.
        $this->assertPasses(
            ['field' => 'not-an-email', 'other' => 'foo'],
            ['field' => 'exclude_if:other,foo|email']
        );
        $this->assertFailsRule(
            ['field' => 'not-an-email', 'other' => 'bar'],
            ['field' => 'exclude_if:other,foo|email']
        );
    }

    public function testValidateExcludeUnless() {
        $this->assertPasses(
            ['field' => 'not-an-email', 'other' => 'bar'],
            ['field' => 'exclude_unless:other,foo|email']
        );
        $this->assertFailsRule(
            ['field' => 'not-an-email', 'other' => 'foo'],
            ['field' => 'exclude_unless:other,foo|email']
        );
    }

    public function testValidateExcludeWith() {
        $this->assertPasses(
            ['field' => 'not-an-email', 'other' => 'x'],
            ['field' => 'exclude_with:other|email']
        );
        $this->assertFailsRule(
            ['field' => 'not-an-email'],
            ['field' => 'exclude_with:other|email']
        );
    }

    public function testValidateExcludeWithout() {
        $this->assertPasses(
            ['field' => 'not-an-email'],
            ['field' => 'exclude_without:other|email']
        );
        $this->assertFailsRule(
            ['field' => 'not-an-email', 'other' => 'x'],
            ['field' => 'exclude_without:other|email']
        );
    }

    /**
     * `integer:strict` dan `numeric:strict` menolak string numerik.
     *
     * @return void
     */
    public function testStrictIntegerAndNumericRejectNumericStrings() {
        $this->assertPasses(['x' => '5'], ['x' => 'integer']);
        $this->assertFailsRule(['x' => '5'], ['x' => 'integer:strict']);
        $this->assertPasses(['x' => 5], ['x' => 'integer:strict']);
        $this->assertFailsRule(['x' => 5.5], ['x' => 'integer:strict']);

        $this->assertPasses(['x' => '5.5'], ['x' => 'numeric']);
        $this->assertFailsRule(['x' => '5.5'], ['x' => 'numeric:strict']);
        $this->assertPasses(['x' => 5.5], ['x' => 'numeric:strict']);
    }

    /*
    |--------------------------------------------------------------------------
    | Pesan: kunci i18n ada dan placeholder terganti
    |--------------------------------------------------------------------------
    */

    /**
     * @return array
     */
    public function rulesWithMessagesProvider() {
        return [
            'ascii' => ['ascii', ['x' => 'ä']],
            'decimal' => ['decimal:2', ['x' => '1.234']],
            'lowercase' => ['lowercase', ['x' => 'ABC']],
            'uppercase' => ['uppercase', ['x' => 'abc']],
            'max_digits' => ['max_digits:2', ['x' => '123']],
            'min_digits' => ['min_digits:3', ['x' => '12']],
            'missing' => ['missing', ['x' => 'a']],
            'missing_if' => ['missing_if:y,1', ['x' => 'a', 'y' => '1']],
            'missing_unless' => ['missing_unless:y,1', ['x' => 'a', 'y' => '2']],
            'missing_with' => ['missing_with:y', ['x' => 'a', 'y' => '1']],
            'missing_with_all' => ['missing_with_all:y,z', ['x' => 'a', 'y' => '1', 'z' => '1']],
            'required_if_accepted' => ['required_if_accepted:y', ['y' => 'yes']],
            'doesnt_start_with' => ['doesnt_start_with:foo', ['x' => 'foobar']],
            'doesnt_end_with' => ['doesnt_end_with:bar', ['x' => 'foobar']],
            'ulid' => ['ulid', ['x' => 'abc']],
            'starts_with' => ['starts_with:foo,bar', ['x' => 'baz']],
            'ends_with' => ['ends_with:foo', ['x' => 'bar']],
            'multiple_of' => ['multiple_of:3', ['x' => '4']],
            'date_equals' => ['date_equals:2026-01-01', ['x' => '2026-01-02']],
            'prohibits' => ['prohibits:y', ['x' => 'a', 'y' => 'b']],
            'accepted_if' => ['accepted_if:y,1', ['x' => 'no', 'y' => '1']],
            'declined_if' => ['declined_if:y,1', ['x' => 'yes', 'y' => '1']],
            'prohibited_if' => ['prohibited_if:y,1', ['x' => 'a', 'y' => '1']],
            'prohibited_unless' => ['prohibited_unless:y,1', ['x' => 'a', 'y' => '2']],
            'required_array_keys' => ['required_array_keys:a,b', ['x' => ['a' => 1]]],
        ];
    }

    /**
     * Rule yang ada tetapi lapisan pesannya tertinggal: sebelum ini `decimal:2`
     * mengeluarkan "validation.decimal" mentah dan `starts_with` menyisakan ":values".
     *
     * @dataProvider rulesWithMessagesProvider
     *
     * @param string $rule
     * @param array  $data
     *
     * @return void
     */
    public function testFailureMessageIsTranslatedWithPlaceholdersReplaced($rule, array $data) {
        $v = $this->validator($data, ['x' => $rule]);

        $this->assertTrue($v->fails(), $rule . ' harus gagal');
        $message = $v->errors()->first('x');
        $this->assertStringStartsNotWith('validation.', $message, $rule . ': kunci i18n hilang');
        foreach ([':values', ':value', ':other', ':date', ':min', ':max', ':decimal'] as $placeholder) {
            $this->assertStringNotContainsString($placeholder, $message, $rule . ': placeholder ' . $placeholder . ' tidak diganti');
        }
    }

    /**
     * @return void
     */
    public function testDecimalMessageShowsTheRange() {
        $message = $this->validator(['x' => '1.2'], ['x' => 'decimal:2,4'])->errors()->first('x');
        $this->assertStringContainsString('2-4', $message);
    }

    /**
     * Rule dependen mengganti `*` pada parameternya dengan indeks atribut yang
     * sedang divalidasi; tanpa itu `missing_if:items.*.type,x` tidak pernah jalan.
     *
     * @return void
     */
    public function testMissingRulesReplaceWildcardsInTheirParameters() {
        $data = ['items' => [['type' => 'x', 'qty' => 5], ['type' => 'y']]];

        $this->assertFailsRule($data, ['items.*.qty' => 'missing_if:items.*.type,x']);
        $this->assertPasses($data, ['items.*.qty' => 'missing_if:items.*.type,y']);

        $this->assertFailsRule($data, ['items.*.qty' => 'missing_unless:items.*.type,y']);
        $this->assertFailsRule($data, ['items.*.qty' => 'missing_with:items.*.type']);
        $this->assertFailsRule($data, ['items.*.qty' => 'missing_with_all:items.*.type']);
    }

    /*
    |--------------------------------------------------------------------------
    | Rule baru (paper §3)
    |--------------------------------------------------------------------------
    */

    public function testHexColor() {
        foreach (['#fff', '#FFF', '#ffff', '#a1b2c3', '#a1b2c3d4'] as $ok) {
            $this->assertPasses(['x' => $ok], ['x' => 'hex_color'], $ok);
        }
        foreach (['fff', '#ggg', '#12345', 123, '#ff'] as $bad) {
            $this->assertFailsRule(['x' => $bad], ['x' => 'hex_color'], json_encode($bad));
        }
    }

    public function testList() {
        $this->assertPasses(['x' => []], ['x' => 'list']);
        $this->assertPasses(['x' => ['a', 'b']], ['x' => 'list']);
        $this->assertFailsRule(['x' => [1 => 'a', 2 => 'b']], ['x' => 'list']);
        $this->assertFailsRule(['x' => ['k' => 'a']], ['x' => 'list']);
        $this->assertFailsRule(['x' => 'abc'], ['x' => 'list']);
    }

    public function testBase64() {
        $this->assertPasses(['x' => base64_encode('halo')], ['x' => 'base64']);
        $this->assertFailsRule(['x' => 'bukan base64!'], ['x' => 'base64']);
        // string kosong = tidak ada isian, rule non-implicit dilewati seperti rule lain
        $this->assertPasses(['x' => ''], ['x' => 'base64']);
        $this->assertFailsRule(['x' => ''], ['x' => 'required|base64']);
        $this->assertFailsRule(['x' => 'aGFsbw'], ['x' => 'base64'], 'tanpa padding ditolak');
    }

    public function testContainsAndDoesntContain() {
        $this->assertPasses(['x' => ['a', 'b', 'c']], ['x' => 'contains:a,c']);
        $this->assertFailsRule(['x' => ['a', 'b']], ['x' => 'contains:a,c']);
        $this->assertFailsRule(['x' => 'abc'], ['x' => 'contains:a']);

        $this->assertPasses(['x' => ['a', 'b']], ['x' => 'doesnt_contain:c,d']);
        $this->assertFailsRule(['x' => ['a', 'b']], ['x' => 'doesnt_contain:b']);
        $this->assertStringNotContainsString(':values', $this->validator(['x' => ['a', 'b']], ['x' => 'doesnt_contain:b'])->errors()->first('x'));

        $this->assertSame('contains:"a","b"', (string) CValidation_Rule::contains(['a', 'b']));
        $this->assertSame('doesnt_contain:"a"', (string) CValidation_Rule::doesntContain('a'));
        $this->assertPasses(['x' => ['a', 'b']], ['x' => [CValidation_Rule::contains('a', 'b')]]);
    }

    public function testInArrayKeysAndArrayKeys() {
        $this->assertPasses(['x' => ['a' => 1, 'b' => 2]], ['x' => 'in_array_keys:a,z']);
        $this->assertFailsRule(['x' => ['a' => 1]], ['x' => 'in_array_keys:y,z']);
        $this->assertFailsRule(['x' => 'abc'], ['x' => 'in_array_keys:a']);

        $this->assertPasses(['x' => ['a' => 1, 'b' => 2]], ['x' => 'array_keys:a,b,c']);
        $this->assertFailsRule(['x' => ['a' => 1, 'd' => 2]], ['x' => 'array_keys:a,b']);
        $this->assertSame('array_keys:"a","b"', (string) CValidation_Rule::arrayKeys(['a', 'b']));
        $this->assertPasses(['x' => ['a' => 1]], ['x' => [CValidation_Rule::arrayKeys('a', 'b')]]);
    }

    public function testPresentIfAndUnlessAreImplicitAndReplaceWildcards() {
        // implicit: atribut yang tidak ada pun dievaluasi
        $this->assertFailsRule(['y' => '1'], ['x' => 'present_if:y,1']);
        $this->assertPasses(['y' => '2'], ['x' => 'present_if:y,1']);
        $this->assertPasses(['x' => null, 'y' => '1'], ['x' => 'present_if:y,1'], 'ada walau null = present');

        $this->assertFailsRule(['y' => '2'], ['x' => 'present_unless:y,1']);
        $this->assertPasses(['y' => '1'], ['x' => 'present_unless:y,1']);

        $this->assertFailsRule(['y' => 'a'], ['x' => 'present_with:y,z']);
        $this->assertPasses([], ['x' => 'present_with:y,z']);
        $this->assertFailsRule(['y' => 'a', 'z' => 'b'], ['x' => 'present_with_all:y,z']);
        $this->assertPasses(['y' => 'a'], ['x' => 'present_with_all:y,z']);

        $data = ['items' => [['type' => 'x'], ['type' => 'y', 'qty' => 1]]];
        $this->assertFailsRule($data, ['items.*.qty' => 'present_if:items.*.type,x']);
        $this->assertPasses($data, ['items.*.qty' => 'present_if:items.*.type,y']);

        $message = $this->validator(['y' => '1'], ['x' => 'present_if:y,1'])->errors()->first('x');
        $this->assertStringNotContainsString(':other', $message);
        $this->assertStringNotContainsString(':value', $message);
    }

    public function testAcceptedDeclinedCounterparts() {
        $this->assertFailsRule(['terms' => 'no'], ['x' => 'required_if_declined:terms']);
        $this->assertPasses(['terms' => 'yes'], ['x' => 'required_if_declined:terms']);
        $this->assertPasses(['terms' => 'no', 'x' => 'ada'], ['x' => 'required_if_declined:terms']);

        $this->assertFailsRule(['terms' => 'yes', 'x' => 'ada'], ['x' => 'prohibited_if_accepted:terms']);
        $this->assertPasses(['terms' => 'no', 'x' => 'ada'], ['x' => 'prohibited_if_accepted:terms']);

        $this->assertFailsRule(['terms' => 'no', 'x' => 'ada'], ['x' => 'prohibited_if_declined:terms']);
        $this->assertPasses(['terms' => 'yes', 'x' => 'ada'], ['x' => 'prohibited_if_declined:terms']);

        foreach (['required_if_declined:terms' => ['terms' => 'no'], 'prohibited_if_accepted:terms' => ['terms' => 'yes', 'x' => 'a'], 'prohibited_if_declined:terms' => ['terms' => 'no', 'x' => 'a']] as $rule => $data) {
            $message = $this->validator($data, ['x' => $rule])->errors()->first('x');
            $this->assertStringStartsNotWith('validation.', $message, $rule);
            $this->assertStringNotContainsString(':other', $message, $rule);
        }
    }

    public function testUnlessCounterpartsOfTheConditionalRuleObjects() {
        $this->assertSame('required', (string) CValidation_Rule::requiredUnless(false));
        $this->assertSame('', (string) CValidation_Rule::requiredUnless(function () { return true; }));
        $this->assertSame('exclude', (string) CValidation_Rule::excludeUnless(false));
        $this->assertSame('prohibited', (string) CValidation_Rule::prohibitedUnless(false));

        $this->assertFailsRule([], ['x' => [CValidation_Rule::requiredUnless(false)]]);
        $this->assertPasses([], ['x' => [CValidation_Rule::requiredUnless(true)]]);
    }

    public function testRuleUnlessIsTheMirrorOfWhen() {
        $this->assertFailsRule(['x' => ''], ['x' => [CValidation_Rule::unless(false, 'required')]]);
        $this->assertPasses(['x' => ''], ['x' => [CValidation_Rule::unless(true, 'required')]]);
        $this->assertFailsRule(['x' => ''], ['x' => [CValidation_Rule::unless(true, 'nullable', 'required')]]);
    }

    public function testRuleArrayCanAndEnumStatics() {
        $this->assertSame('array', (string) CValidation_Rule::array());
        $this->assertSame('array:a,b', (string) CValidation_Rule::array(['a', 'b']));
        $this->assertSame('array:a,b', (string) CValidation_Rule::array('a', 'b'));
        $this->assertInstanceOf(CValidation_Rule_Can::class, CValidation_Rule::can('update', 'post'));
        if (PHP_VERSION_ID >= 80100) {
            $this->assertInstanceOf(CValidation_Rule_Enum::class, CValidation_Rule::enum('SomeEnum'));
        }
    }
}
