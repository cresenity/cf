<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for CValidation_Validator mechanics: how the validator orchestrates
 * rules (parsing, pass/fail, error bag, messages, sometimes/after hooks,
 * bail/stopOnFirstFailure, wildcard attributes, Rule:: objects, custom
 * extensions, validated()/safe()).
 *
 * This file intentionally does NOT test individual rule-checking logic
 * (required/email/numeric/date/etc. implementations) - that lives in
 * ValidateAttributeTrait.php and is covered elsewhere.
 */
class ValidatorTest extends TestCase {
    protected function makeValidator(array $data, array $rules, array $messages = [], array $customAttributes = []) {
        return new CValidation_Validator($data, $rules, $messages, $customAttributes);
    }

    /*
    |--------------------------------------------------------------------------
    | Basic pass / fail
    |--------------------------------------------------------------------------
    */

    public function testPassesReturnsTrueWhenAllRulesPass() {
        $v = $this->makeValidator(['name' => 'John'], ['name' => 'required|string']);

        $this->assertTrue($v->passes());
        $this->assertFalse($v->fails());
    }

    public function testFailsReturnsTrueWhenARuleFails() {
        $v = $this->makeValidator(['name' => ''], ['name' => 'required']);

        $this->assertTrue($v->fails());
        $this->assertFalse($v->passes());
    }

    public function testCheckIsAliasForPasses() {
        $v = $this->makeValidator(['name' => 'John'], ['name' => 'required']);

        $this->assertTrue($v->check());
    }

    /*
    |--------------------------------------------------------------------------
    | Error bag: errors()/messages()/first()/get()/has()
    |--------------------------------------------------------------------------
    */

    public function testErrorsReturnsMessageBagWithFailureMessages() {
        $v = $this->makeValidator(['name' => ''], ['name' => 'required']);
        $v->fails();

        $errors = $v->errors();

        $this->assertInstanceOf(CBase_MessageBag::class, $errors);
        $this->assertTrue($errors->has('name'));
    }

    public function testMessagesIsAliasForErrors() {
        $v = $this->makeValidator(['name' => ''], ['name' => 'required']);
        $v->fails();

        $this->assertSame($v->errors()->toArray(), $v->messages()->toArray());
    }

    public function testGetMessageBagIsAliasForErrors() {
        $v = $this->makeValidator(['name' => ''], ['name' => 'required']);
        $v->fails();

        $this->assertSame($v->errors()->toArray(), $v->getMessageBag()->toArray());
    }

    public function testErrorsCanBeAccessedWithoutCallingPassesFirst() {
        $v = $this->makeValidator(['name' => ''], ['name' => 'required']);

        // errors()/messages() should lazily run validation if it hasn't run yet.
        $this->assertTrue($v->errors()->has('name'));
    }

    public function testErrorBagFirstReturnsFirstMessageForKey() {
        $v = $this->makeValidator(['name' => ''], ['name' => 'required']);
        $v->fails();

        $this->assertIsString($v->errors()->first('name'));
        $this->assertNotSame('', $v->errors()->first('name'));
    }

    public function testErrorBagFirstReturnsEmptyStringForMissingKey() {
        $v = $this->makeValidator(['name' => 'John'], ['name' => 'required']);
        $v->passes();

        $this->assertSame('', $v->errors()->first('name'));
    }

    public function testErrorBagGetReturnsAllMessagesForKey() {
        $v = $this->makeValidator(['name' => ''], ['name' => 'required|min:5']);
        $v->fails();

        $messages = $v->errors()->get('name');

        $this->assertIsArray($messages);
        $this->assertNotEmpty($messages);
    }

    public function testErrorBagHasChecksKeyPresence() {
        $v = $this->makeValidator(
            ['name' => '', 'age' => 20],
            ['name' => 'required', 'age' => 'integer']
        );
        $v->fails();

        $this->assertTrue($v->errors()->has('name'));
        $this->assertFalse($v->errors()->has('age'));
    }

    /*
    |--------------------------------------------------------------------------
    | Multiple rules per field: pipe-separated vs array syntax
    |--------------------------------------------------------------------------
    */

    public function testPipeSeparatedRuleStringIsSupported() {
        $v = $this->makeValidator(['name' => 'Jo'], ['name' => 'required|string|min:5']);

        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('name'));
    }

    public function testArrayOfRuleStringsIsSupported() {
        $v = $this->makeValidator(['name' => 'Jo'], ['name' => ['required', 'string', 'min:5']]);

        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('name'));
    }

    public function testArrayAndPipeSyntaxProduceEquivalentResults() {
        $pipe = $this->makeValidator(['name' => 'John'], ['name' => 'required|string|min:2']);
        $array = $this->makeValidator(['name' => 'John'], ['name' => ['required', 'string', 'min:2']]);

        $this->assertTrue($pipe->passes());
        $this->assertTrue($array->passes());
    }

    /*
    |--------------------------------------------------------------------------
    | Custom error messages and placeholder replacement
    |--------------------------------------------------------------------------
    */

    public function testCustomMessageForAttributeAndRuleOverridesDefault() {
        $v = $this->makeValidator(
            ['name' => ''],
            ['name' => 'required'],
            ['name.required' => 'Please fill in the name field.']
        );
        $v->fails();

        $this->assertSame('Please fill in the name field.', $v->errors()->first('name'));
    }

    public function testCustomMessageForRuleOnlyAppliesToAllAttributes() {
        $v = $this->makeValidator(
            ['name' => '', 'email' => ''],
            ['name' => 'required', 'email' => 'required'],
            ['required' => 'This field cannot be empty.']
        );
        $v->fails();

        $this->assertSame('This field cannot be empty.', $v->errors()->first('name'));
        $this->assertSame('This field cannot be empty.', $v->errors()->first('email'));
    }

    public function testAttributePlaceholderIsReplacedInDefaultMessage() {
        $v = $this->makeValidator(['name' => ''], ['name' => 'required']);
        $v->fails();

        $this->assertStringNotContainsString(':attribute', $v->errors()->first('name'));
        $this->assertStringContainsStringIgnoringCase('name', $v->errors()->first('name'));
    }

    public function testMinMaxPlaceholdersAreReplacedInDefaultMessage() {
        $v = $this->makeValidator(['name' => 'ab'], ['name' => 'min:5']);
        $v->fails();
        $message = $v->errors()->first('name');
        $this->assertStringContainsString('5', $message);

        $v2 = $this->makeValidator(['name' => 'abcdefg'], ['name' => 'max:3']);
        $v2->fails();
        $message2 = $v2->errors()->first('name');
        $this->assertStringContainsString('3', $message2);
    }

    /*
    |--------------------------------------------------------------------------
    | Custom attribute names
    |--------------------------------------------------------------------------
    */

    public function testCustomAttributeNameReplacesAttributePlaceholder() {
        $v = $this->makeValidator(
            ['e' => ''],
            ['e' => 'required'],
            [],
            ['e' => 'email address']
        );
        $v->fails();

        $this->assertStringContainsStringIgnoringCase('email address', $v->errors()->first('e'));
    }

    public function testSetAttributeNamesAppliesCustomAttributeNames() {
        $v = $this->makeValidator(['e' => ''], ['e' => 'required']);
        $v->setAttributeNames(['e' => 'email address']);
        $v->fails();

        $this->assertStringContainsStringIgnoringCase('email address', $v->errors()->first('e'));
    }

    public function testAddCustomAttributesMergesIntoExisting() {
        $v = $this->makeValidator(
            ['a' => '', 'b' => ''],
            ['a' => 'required', 'b' => 'required'],
            [],
            ['a' => 'Field A']
        );
        $v->addCustomAttributes(['b' => 'Field B']);
        $v->fails();

        $this->assertStringContainsString('Field A', $v->errors()->first('a'));
        $this->assertStringContainsString('Field B', $v->errors()->first('b'));
    }

    /*
    |--------------------------------------------------------------------------
    | sometimes()
    |--------------------------------------------------------------------------
    */

    public function testSometimesAppliesRuleWhenCallbackReturnsTrue() {
        $v = $this->makeValidator(['has_appointment' => true, 'appointment_date' => ''], []);

        $v->sometimes('appointment_date', 'required', function ($input) {
            return $input->has_appointment === true;
        });

        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('appointment_date'));
    }

    public function testSometimesDoesNotApplyRuleWhenCallbackReturnsFalse() {
        $v = $this->makeValidator(['has_appointment' => false, 'appointment_date' => ''], []);

        $v->sometimes('appointment_date', 'required', function ($input) {
            return $input->has_appointment === true;
        });

        $this->assertTrue($v->passes());
        $this->assertFalse($v->errors()->has('appointment_date'));
    }

    public function testSometimesCallbackReceivesFluentInputPayload() {
        $received = null;
        $v = $this->makeValidator(['type' => 'email', 'email' => 'not-an-email'], []);

        $v->sometimes('email', 'required', function ($input) use (&$received) {
            $received = $input;

            return true;
        });
        $v->passes();

        $this->assertInstanceOf(CBase_Fluent::class, $received);
        $this->assertSame('email', $received->type);
    }

    /*
    |--------------------------------------------------------------------------
    | after()
    |--------------------------------------------------------------------------
    */

    public function testAfterHookRunsAfterRuleValidation() {
        $v = $this->makeValidator(['password' => 'secret', 'password_confirmation' => 'secret'], []);

        $v->after(function ($validator) {
            $validator->errors()->add('password', 'Custom after-hook failure.');
        });

        $this->assertTrue($v->fails());
        $this->assertSame('Custom after-hook failure.', $v->errors()->first('password'));
    }

    public function testAfterHookDoesNotRunIfNoCallbackRegistered() {
        $v = $this->makeValidator(['name' => 'John'], ['name' => 'required']);

        $this->assertTrue($v->passes());
    }

    public function testAfterReturnsValidatorInstanceForChaining() {
        $v = $this->makeValidator(['name' => 'John'], ['name' => 'required']);

        $result = $v->after(function () {
        });

        $this->assertSame($v, $result);
    }

    public function testMultipleAfterHooksAllRun() {
        $calls = [];
        $v = $this->makeValidator(['name' => 'John'], ['name' => 'required']);

        $v->after(function () use (&$calls) {
            $calls[] = 'first';
        });
        $v->after(function () use (&$calls) {
            $calls[] = 'second';
        });
        $v->passes();

        $this->assertSame(['first', 'second'], $calls);
    }

    /*
    |--------------------------------------------------------------------------
    | stopOnFirstFailure()
    |--------------------------------------------------------------------------
    */

    public function testStopOnFirstFailureStopsValidatingSubsequentAttributes() {
        $v = $this->makeValidator(
            ['first' => '', 'second' => ''],
            ['first' => 'required', 'second' => 'required']
        );
        $v->stopOnFirstFailure();

        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('first'));
        $this->assertFalse($v->errors()->has('second'));
    }

    public function testWithoutStopOnFirstFailureBothAttributesAreValidated() {
        $v = $this->makeValidator(
            ['first' => '', 'second' => ''],
            ['first' => 'required', 'second' => 'required']
        );

        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('first'));
        $this->assertTrue($v->errors()->has('second'));
    }

    /*
    |--------------------------------------------------------------------------
    | bail
    |--------------------------------------------------------------------------
    */

    public function testBailStopsValidatingRemainingRulesOnFieldAfterFirstFailure() {
        $v = $this->makeValidator(['age' => 'not-a-number'], ['age' => 'bail|integer|min:10']);
        $v->fails();

        $messages = $v->errors()->get('age');

        // Only the "integer" failure should be recorded; "min" should never run.
        $this->assertCount(1, $messages);
    }

    public function testWithoutBailAllFailingRulesOnFieldAreRecorded() {
        $v = $this->makeValidator(['age' => 'not-a-number'], ['age' => 'integer|max:3']);
        $v->fails();

        $messages = $v->errors()->get('age');

        // "integer" fails because the value isn't numeric, and "max:3" fails
        // because the string is longer than 3 characters - both should be
        // recorded since bail was not used.
        $this->assertCount(2, $messages);
    }

    /*
    |--------------------------------------------------------------------------
    | Wildcard / nested array validation
    |--------------------------------------------------------------------------
    */

    public function testWildcardRuleValidatesEachArrayElement() {
        $v = $this->makeValidator(
            ['items' => [['name' => 'Widget'], ['name' => '']]],
            ['items.*.name' => 'required']
        );

        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('items.1.name'));
        $this->assertFalse($v->errors()->has('items.0.name'));
    }

    public function testWildcardRuleExpandsRulesForEachExplicitKey() {
        $v = $this->makeValidator(
            ['items' => [['name' => 'Widget'], ['name' => 'Gadget']]],
            ['items.*.name' => 'required|string']
        );

        $this->assertTrue($v->passes());
        $rules = $v->getRules();

        $this->assertArrayHasKey('items.0.name', $rules);
        $this->assertArrayHasKey('items.1.name', $rules);
    }

    /*
    |--------------------------------------------------------------------------
    | Rule:: fluent rule objects
    |--------------------------------------------------------------------------
    */

    public function testRuleInObjectIsAcceptedInRulesArray() {
        $v = $this->makeValidator(
            ['status' => 'archived'],
            ['status' => ['required', CValidation_Rule::in(['active', 'inactive'])]]
        );

        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('status'));
    }

    public function testRuleInObjectPassesForAllowedValue() {
        $v = $this->makeValidator(
            ['status' => 'active'],
            ['status' => ['required', CValidation_Rule::in(['active', 'inactive'])]]
        );

        $this->assertTrue($v->passes());
    }

    public function testRuleNotInObjectRejectsDisallowedValue() {
        $v = $this->makeValidator(
            ['status' => 'active'],
            ['status' => [CValidation_Rule::notIn(['active', 'inactive'])]]
        );

        $this->assertTrue($v->fails());
    }

    public function testClosureRuleInRulesArrayIsInvoked() {
        $v = $this->makeValidator(
            ['username' => 'admin'],
            ['username' => [function ($attribute, $value, $fail) {
                if ($value === 'admin') {
                    $fail('The :attribute is reserved.');
                }
            }]]
        );

        $this->assertTrue($v->fails());
        $this->assertStringContainsString('reserved', $v->errors()->first('username'));
    }

    public function testClosureRuleInRulesArrayPassesWhenFailNotCalled() {
        $v = $this->makeValidator(
            ['username' => 'john'],
            ['username' => [function ($attribute, $value, $fail) {
                if ($value === 'admin') {
                    $fail('The :attribute is reserved.');
                }
            }]]
        );

        $this->assertTrue($v->passes());
    }

    /*
    |--------------------------------------------------------------------------
    | Custom rule registration via addExtension
    |--------------------------------------------------------------------------
    */

    public function testAddExtensionRegistersCustomRule() {
        $v = $this->makeValidator(['code' => 'AB12'], ['code' => 'uppercase_alpha_num']);

        $v->addExtension('uppercase_alpha_num', function ($attribute, $value) {
            return (bool) preg_match('/^[A-Z0-9]+$/', $value);
        });

        $this->assertTrue($v->passes());
    }

    public function testAddExtensionCustomRuleFailsWhenClosureReturnsFalse() {
        $v = $this->makeValidator(['code' => 'ab12'], ['code' => 'uppercase_alpha_num']);

        $v->addExtension('uppercase_alpha_num', function ($attribute, $value) {
            return (bool) preg_match('/^[A-Z0-9]+$/', $value);
        });

        $this->assertTrue($v->fails());
    }

    public function testAddExtensionWithFallbackMessageIsUsedOnFailure() {
        $v = $this->makeValidator(['code' => 'ab12'], ['code' => 'uppercase_alpha_num']);

        $v->addExtension('uppercase_alpha_num', function ($attribute, $value) {
            return (bool) preg_match('/^[A-Z0-9]+$/', $value);
        });
        $v->setFallbackMessages(['uppercase_alpha_num' => 'The :attribute must be uppercase alphanumeric.']);
        $v->fails();

        $this->assertSame('The code must be uppercase alphanumeric.', $v->errors()->first('code'));
    }

    /*
    |--------------------------------------------------------------------------
    | validated() / safe()
    |--------------------------------------------------------------------------
    */

    public function testValidatedReturnsOnlyAttributesCoveredByRules() {
        $v = $this->makeValidator(
            ['name' => 'John', 'age' => 30, 'not_validated' => 'secret'],
            ['name' => 'required', 'age' => 'integer']
        );

        $this->assertTrue($v->passes());
        $this->assertSame(['name' => 'John', 'age' => 30], $v->validated());
    }

    public function testValidatedThrowsExceptionWhenValidationFails() {
        $v = $this->makeValidator(['name' => ''], ['name' => 'required']);

        $this->expectException(CValidation_Exception::class);
        $v->validated();
    }

    public function testSafeReturnsValidatedInputInstance() {
        $v = $this->makeValidator(
            ['name' => 'John', 'age' => 30],
            ['name' => 'required', 'age' => 'integer']
        );
        $v->passes();

        $safe = $v->safe();

        $this->assertInstanceOf(CBase_ValidatedInput::class, $safe);
    }

    public function testSafeWithKeysReturnsOnlyRequestedKeys() {
        $v = $this->makeValidator(
            ['name' => 'John', 'age' => 30],
            ['name' => 'required', 'age' => 'integer']
        );
        $v->passes();

        $safe = $v->safe(['name']);
        $result = $safe instanceof CBase_ValidatedInput ? $safe->toArray() : (array) $safe;

        $this->assertSame(['name' => 'John'], $result);
    }

    /*
    |--------------------------------------------------------------------------
    | validate() / validateWithBag()
    |--------------------------------------------------------------------------
    */

    public function testValidateReturnsValidatedDataOnSuccess() {
        $v = $this->makeValidator(['name' => 'John'], ['name' => 'required']);

        $this->assertSame(['name' => 'John'], $v->validate());
    }

    public function testValidateThrowsCValidationExceptionOnFailure() {
        $v = $this->makeValidator(['name' => ''], ['name' => 'required']);

        $this->expectException(CValidation_Exception::class);
        $v->validate();
    }

    public function testValidateWithBagAttachesErrorBagNameToException() {
        $v = $this->makeValidator(['name' => ''], ['name' => 'required']);

        try {
            $v->validateWithBag('customBag');
            $this->fail('Expected CValidation_Exception was not thrown.');
        } catch (CValidation_Exception $e) {
            $this->assertSame('customBag', $e->errorBag);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | valid() / invalid() / failed()
    |--------------------------------------------------------------------------
    */

    public function testValidReturnsOnlyDataThatPassedValidation() {
        $v = $this->makeValidator(
            ['name' => 'John', 'email' => ''],
            ['name' => 'required', 'email' => 'required']
        );
        $v->fails();

        $valid = $v->valid();

        $this->assertArrayHasKey('name', $valid);
        $this->assertArrayNotHasKey('email', $valid);
    }

    public function testInvalidReturnsOnlyDataThatFailedValidation() {
        $v = $this->makeValidator(
            ['name' => 'John', 'email' => ''],
            ['name' => 'required', 'email' => 'required']
        );
        $v->fails();

        $invalid = $v->invalid();

        $this->assertArrayHasKey('email', $invalid);
        $this->assertArrayNotHasKey('name', $invalid);
    }

    public function testFailedReturnsFailedRulesKeyedByAttributeAndRule() {
        $v = $this->makeValidator(['age' => 'abc'], ['age' => 'integer|min:5']);
        $v->fails();

        $failed = $v->failed();

        $this->assertArrayHasKey('age', $failed);
        $this->assertArrayHasKey('Integer', $failed['age']);
    }

    /*
    |--------------------------------------------------------------------------
    | Rule inspection: hasRule() / getRules()
    |--------------------------------------------------------------------------
    */

    public function testHasRuleReturnsTrueWhenRulePresentForAttribute() {
        $v = $this->makeValidator(['name' => 'John'], ['name' => 'required|string']);

        // hasRule() internally normalizes rule names to StudlyCase before comparing.
        $this->assertTrue($v->hasRule('name', 'Required'));
        $this->assertTrue($v->hasRule('name', ['String']));
        $this->assertFalse($v->hasRule('name', 'Numeric'));
    }

    public function testGetRulesReturnsExplodedRulesArray() {
        $v = $this->makeValidator(['name' => 'John'], ['name' => 'required|string']);

        $rules = $v->getRules();

        // getRules() returns the raw, un-normalized (not StudlyCase) rule
        // strings as exploded from the pipe-separated rule string.
        $this->assertSame(['required', 'string'], $rules['name']);
    }

    public function testSetRulesReplacesExistingRules() {
        $v = $this->makeValidator(['name' => ''], ['name' => 'required']);
        $v->setRules(['name' => 'nullable']);

        $this->assertTrue($v->passes());
    }

    /*
    |--------------------------------------------------------------------------
    | Data access: getData() / setData()
    |--------------------------------------------------------------------------
    */

    public function testGetDataReturnsUnderlyingData() {
        $v = $this->makeValidator(['name' => 'John'], ['name' => 'required']);

        $this->assertSame(['name' => 'John'], $v->getData());
    }

    public function testSetDataReplacesDataAndReEvaluatesRules() {
        $v = $this->makeValidator(['name' => ''], ['name' => 'required']);
        $this->assertTrue($v->fails());

        $v->setData(['name' => 'John']);
        $this->assertTrue($v->passes());
    }

    /*
    |--------------------------------------------------------------------------
    | Factory / facade construction
    |--------------------------------------------------------------------------
    */

    public function testFactoryMakeCreatesAWorkingValidator() {
        $v = CValidation_Factory::instance()->make(['name' => 'John'], ['name' => 'required']);

        $this->assertInstanceOf(CValidation_Validator::class, $v);
        $this->assertTrue($v->passes());
    }

    public function testFactoryValidateThrowsOnFailureAndReturnsDataOnSuccess() {
        $factory = CValidation_Factory::instance();

        $this->assertSame(['name' => 'John'], $factory->validate(['name' => 'John'], ['name' => 'required']));

        $this->expectException(CValidation_Exception::class);
        $factory->validate(['name' => ''], ['name' => 'required']);
    }

    public function testCValidationFactoryFacadeCreatesValidator() {
        $v = CValidation::factory(['name' => 'John'], ['name' => 'required']);

        $this->assertInstanceOf(CValidation_Validator::class, $v);
        $this->assertTrue($v->passes());
    }

    public function testCValidationFactoryFacadeWithoutDataReturnsFactoryInstance() {
        $factory = CValidation::factory();

        $this->assertInstanceOf(CValidation_Factory::class, $factory);
    }

    public function testCValidationCreateValidatorBuildsValidatorDirectly() {
        $v = CValidation::createValidator(['name' => 'John'], ['name' => 'required']);

        $this->assertInstanceOf(CValidation_Validator::class, $v);
        $this->assertTrue($v->passes());
    }

    /*
    |--------------------------------------------------------------------------
    | whenPasses / whenFails / setValue / appendRules / getException
    |--------------------------------------------------------------------------
    */

    public function testWhenPassesRunsTheCallbackOnlyWhenValid() {
        $v = $this->makeValidator(['name' => 'John'], ['name' => 'required']);
        $seen = null;
        $result = $v->whenPasses(function ($validator) use (&$seen) {
            $seen = $validator;
        }, function () {
            $this->fail('default tidak boleh dipanggil saat lolos');
        });

        $this->assertSame($v, $seen);
        // callback tanpa nilai balik -> validator itu sendiri, supaya bisa dirantai
        $this->assertSame($v, $result);
    }

    public function testWhenPassesFallsBackToDefaultAndReturnsItsValue() {
        $v = $this->makeValidator(['name' => ''], ['name' => 'required']);
        $result = $v->whenPasses(function () {
            $this->fail('callback tidak boleh dipanggil saat gagal');
        }, function ($validator) {
            return 'gagal: ' . $validator->errors()->first('name');
        });

        $this->assertStringStartsWith('gagal: ', $result);
    }

    public function testWhenFailsIsTheMirrorImage() {
        $v = $this->makeValidator(['name' => ''], ['name' => 'required']);
        $this->assertSame('kena', $v->whenFails(function () {
            return 'kena';
        }));

        $v = $this->makeValidator(['name' => 'John'], ['name' => 'required']);
        $this->assertSame($v, $v->whenFails(function () {
            $this->fail('callback tidak boleh dipanggil saat lolos');
        }));
    }

    public function testSetValueWritesIntoNestedData() {
        $v = $this->makeValidator(['user' => ['name' => '']], ['user.name' => 'required']);
        $v->setValue('user.name', 'John');

        $this->assertTrue($v->passes());
        $this->assertSame('John', $v->getData()['user']['name']);
    }

    public function testAppendRulesKeepsExistingRulesAndAddsNew() {
        $v = $this->makeValidator(['name' => 'Jo', 'age' => 'x'], ['name' => 'required']);
        $this->assertTrue($v->passes());

        $v->appendRules(['name' => 'min:3', 'age' => ['integer']]);

        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('name'));
        $this->assertTrue($v->errors()->has('age'));
        $this->assertSame(['required', 'min:3'], $v->getRules()['name']);
    }

    public function testGetExceptionReturnsTheConfiguredClass() {
        $v = $this->makeValidator([], []);
        $this->assertSame(CValidation_Exception::class, $v->getException());

        $custom = new class($v) extends CValidation_Exception {
        };
        $v->setException(get_class($custom));
        $this->assertSame(get_class($custom), $v->getException());
    }

    /*
    |--------------------------------------------------------------------------
    | fakeDnsLookups
    |--------------------------------------------------------------------------
    */

    public function testFakeDnsLookupsMakesDnsRulesPassOffline() {
        CValidation_Validator::fakeDnsLookups();

        try {
            $v = $this->makeValidator(
                ['email' => 'user@surely-not-a-registered-domain-cf19.test', 'url' => 'https://surely-not-a-registered-domain-cf19.test'],
                ['email' => 'email:dns', 'url' => 'active_url']
            );
            $this->assertTrue($v->passes(), json_encode($v->errors()->all()));

            // nama host yang tidak sah tetap ditolak walau DNS dipalsukan
            $v = $this->makeValidator(['url' => 'https://not a host'], ['url' => 'active_url']);
            $this->assertTrue($v->fails());
        } finally {
            CValidation_Validator::fakeDnsLookups(false);
        }
    }
}
