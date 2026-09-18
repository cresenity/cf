<?php

use PHPUnit\Framework\TestCase;

/**
 * Port terpilih dari ValidationValidatorTest hulu yang belum tercakup suite CF: wildcard bersarang
 * (items.*.name) untuk berbagai rule, placeholder pesan (:attribute/:other/:values/:min/:input/:index/
 * :position), ekstensi kustom (extend/implicit/dependent/replacer), validated()/exclude*, sometimes
 * item-aware, distinct, failed(), Rule objects, dan kasus non-string yang tidak boleh melempar.
 */
class ValidationPortTest extends TestCase {
    /**
     * @param array $data
     * @param array $rules
     * @param array $messages
     * @param array $attributes
     *
     * @return CValidation_Validator
     */
    protected function v(array $data, array $rules, array $messages = [], array $attributes = []) {
        return new CValidation_Validator($data, $rules, $messages, $attributes);
    }

    // --- wildcard / implicit each -------------------------------------------------------------

    public function testImplicitEachWithAsterisksRequired() {
        $this->assertTrue($this->v(['foo' => [['name' => 'a'], ['name' => 'b']]], ['foo.*.name' => 'required'])->passes());
        $v = $this->v(['foo' => [['name' => 'a'], ['name' => null], []]], ['foo.*.name' => 'required']);
        $this->assertTrue($v->fails());
        $this->assertSame(['foo.1.name', 'foo.2.name'], $v->errors()->keys(), 'kunci galat memakai indeks nyata');
        $this->assertTrue($this->v(['foo' => []], ['foo.*.name' => 'required'])->passes(), 'array kosong: tidak ada item untuk gagal');
    }

    public function testImplicitEachWithAsterisksConfirmedSameDifferent() {
        $this->assertTrue($this->v(['foo' => [['password' => 'x', 'password_confirmation' => 'x']]], ['foo.*.password' => 'confirmed'])->passes());
        $this->assertTrue($this->v(['foo' => [['password' => 'x', 'password_confirmation' => 'y']]], ['foo.*.password' => 'confirmed'])->fails());
        $this->assertTrue($this->v(['foo' => [['a' => 1, 'b' => 1]]], ['foo.*.a' => 'same:foo.*.b'])->passes());
        $this->assertTrue($this->v(['foo' => [['a' => 1, 'b' => 2]]], ['foo.*.a' => 'same:foo.*.b'])->fails());
        $this->assertTrue($this->v(['foo' => [['a' => 1, 'b' => 2]]], ['foo.*.a' => 'different:foo.*.b'])->passes());
    }

    public function testImplicitEachWithAsterisksRequiredIfAndRequiredWith() {
        $this->assertTrue($this->v(['foo' => [['bar' => 1, 'baz' => 'x'], ['bar' => 0]]], ['foo.*.baz' => 'required_if:foo.*.bar,1'])->passes());
        $this->assertTrue($this->v(['foo' => [['bar' => 1]]], ['foo.*.baz' => 'required_if:foo.*.bar,1'])->fails());
        $this->assertTrue($this->v(['foo' => [['bar' => 'x', 'baz' => 'y']]], ['foo.*.baz' => 'required_with:foo.*.bar'])->passes());
        $this->assertTrue($this->v(['foo' => [['bar' => 'x']]], ['foo.*.baz' => 'required_with:foo.*.bar'])->fails());
        $this->assertTrue($this->v(['foo' => [['baz' => 'y']]], ['foo.*.baz' => 'required_without:foo.*.bar'])->passes());
        $this->assertTrue($this->v(['foo' => [[]]], ['foo.*.baz' => 'required_without:foo.*.bar'])->fails());
    }

    public function testNestedArrayWithNonNumericKeysAndDeepWildcards() {
        $data = ['users' => ['budi' => ['emails' => ['a@b.co', 'salah']], 'ani' => ['emails' => ['c@d.co']]]];
        $v = $this->v($data, ['users.*.emails.*' => 'email']);

        $this->assertTrue($v->fails());
        $this->assertSame(['users.budi.emails.1'], $v->errors()->keys());
    }

    public function testArrayRuleWithAllowedKeysAndInArrayAgainstOtherField() {
        $this->assertTrue($this->v(['f' => ['a' => 1, 'b' => 2]], ['f' => 'array:a,b'])->passes());
        $this->assertTrue($this->v(['f' => ['a' => 1, 'c' => 2]], ['f' => 'array:a,b'])->fails(), 'kunci di luar daftar');
        $this->assertTrue($this->v(['f' => 'bukan array'], ['f' => 'array'])->fails());
        $this->assertTrue($this->v(['pilih' => [1, 2], 'tersedia' => [1, 2, 3]], ['pilih.*' => 'in_array:tersedia.*'])->passes());
        $this->assertTrue($this->v(['pilih' => [1, 9], 'tersedia' => [1, 2, 3]], ['pilih.*' => 'in_array:tersedia.*'])->fails());
    }

    public function testDistinctOnWildcardAndTopLevelArrays() {
        $this->assertTrue($this->v(['foo' => ['a', 'b']], ['foo.*' => 'distinct'])->passes());
        $this->assertTrue($this->v(['foo' => ['a', 'a']], ['foo.*' => 'distinct'])->fails());
        $this->assertTrue($this->v(['foo' => [['id' => 1], ['id' => 1]]], ['foo.*.id' => 'distinct'])->fails());
        $this->assertTrue($this->v(['foo' => ['A', 'a']], ['foo.*' => 'distinct'])->passes(), 'default peka huruf');
        $this->assertTrue($this->v(['foo' => ['A', 'a']], ['foo.*' => 'distinct:ignore_case'])->fails());
        $this->assertTrue($this->v(['foo' => ['1', 1]], ['foo.*' => 'distinct'])->fails(), 'longgar: "1" == 1');
        $this->assertTrue($this->v(['foo' => ['1', 1]], ['foo.*' => 'distinct:strict'])->passes());
    }

    // --- pesan & placeholder -----------------------------------------------------------------------

    public function testAttributePlaceholderUsesCustomAttributeNames() {
        $v = $this->v(['nama_lengkap' => ''], ['nama_lengkap' => 'required'], ['required' => ':attribute harus ada'], ['nama_lengkap' => 'Nama Lengkap']);
        $v->fails();
        $this->assertSame('Nama Lengkap harus ada', $v->errors()->first('nama_lengkap'));

        $v = $this->v(['nama_lengkap' => ''], ['nama_lengkap' => 'required'], ['required' => ':attribute harus ada']);
        $v->fails();
        $this->assertSame('nama lengkap harus ada', $v->errors()->first('nama_lengkap'), 'tanpa nama kustom: snake → spasi');
    }

    public function testAttributeNamesAreReplacedInArraysAndWildcardMessages() {
        $v = $this->v(['users' => [['name' => ''], ['name' => 'ok'], ['name' => '']]], ['users.*.name' => 'required'], ['users.*.name.required' => ':attribute ke-:position (indeks :index) wajib'], ['users.*.name' => 'nama']);
        $v->fails();

        $this->assertSame('nama ke-1 (indeks 0) wajib', $v->errors()->first('users.0.name'));
        $this->assertSame('nama ke-3 (indeks 2) wajib', $v->errors()->first('users.2.name'));

        $v = $this->v(['m' => [[0, 'x']]], ['m.*.*' => 'integer'], ['m.*.*.integer' => 'baris :position kolom :second-position']);
        $v->fails();
        $this->assertSame('baris 1 kolom 2', $v->errors()->first('m.0.1'));
    }

    public function testOtherValuesMinMaxAndInputPlaceholders() {
        $v = $this->v(['a' => 1, 'b' => 2], ['a' => 'same:b'], ['same' => ':attribute harus sama dengan :other']);
        $v->fails();
        $this->assertSame('a harus sama dengan b', $v->errors()->first('a'));

        $v = $this->v(['warna' => 'ungu'], ['warna' => 'in:merah,hijau'], ['in' => ':attribute harus salah satu dari :values']);
        $v->fails();
        $this->assertSame('warna harus salah satu dari merah, hijau', $v->errors()->first('warna'));

        //pesan inline untuk rule ukuran memakai bentuk bersarang ['min' => ['string' => ...]], bukan 'min.string'
        $v = $this->v(['kode' => 'ab'], ['kode' => 'min:3'], ['min' => ['string' => ':attribute minimal :min karakter, diisi ":input"']]);
        $v->fails();
        $this->assertSame('kode minimal 3 karakter, diisi "ab"', $v->errors()->first('kode'));

        $v = $this->v(['n' => 50], ['n' => 'numeric|between:1,10'], ['between' => ['numeric' => ':attribute antara :min-:max']]);
        $v->fails();
        $this->assertSame('n antara 1-10', $v->errors()->first('n'));
    }

    public function testSizeMessagesPickTheTypeSpecificLine() {
        $messages = ['size' => ['string' => 'string :size', 'numeric' => 'angka :size', 'array' => 'array :size']];

        $v = $this->v(['s' => 'abcd'], ['s' => 'size:3'], $messages);
        $v->fails();
        $this->assertSame('string 3', $v->errors()->first('s'));
        $v = $this->v(['n' => 4], ['n' => 'numeric|size:3'], $messages);
        $v->fails();
        $this->assertSame('angka 3', $v->errors()->first('n'));
        $v = $this->v(['a' => [1, 2]], ['a' => 'array|size:3'], $messages);
        $v->fails();
        $this->assertSame('array 3', $v->errors()->first('a'));
    }

    public function testCustomAttributeSpecificMessageWinsOverRuleMessage() {
        $v = $this->v(['a' => '', 'b' => ''], ['a' => 'required', 'b' => 'required'], ['required' => 'umum', 'b.required' => 'khusus b']);
        $v->fails();

        $this->assertSame('umum', $v->errors()->first('a'));
        $this->assertSame('khusus b', $v->errors()->first('b'));
    }

    public function testDisplayableValuesFromValueNames() {
        $v = $this->v(['tipe' => 'x', 'nilai' => ''], ['nilai' => 'required_if:tipe,x'], ['required_if' => ':attribute wajib bila :other bernilai :value']);
        $v->setValueNames(['tipe' => ['x' => 'Ekspor']]);
        $v->fails();

        $this->assertSame('nilai wajib bila tipe bernilai Ekspor', $v->errors()->first('nilai'));
    }

    // --- ekstensi kustom ----------------------------------------------------------------------------

    public function testCustomValidatorsClosureAndClassBased() {
        $v = $this->v(['kode' => 'ABC'], ['kode' => 'huruf_besar'], ['huruf_besar' => ':attribute harus kapital']);
        $v->addExtension('huruf_besar', function ($attribute, $value, $parameters, $validator) {
            return $value === strtoupper((string) $value);
        });
        $this->assertTrue($v->passes());

        $v = $this->v(['kode' => 'abc'], ['kode' => 'huruf_besar'], ['huruf_besar' => ':attribute harus kapital']);
        $v->setContainer(CContainer::getInstance());
        $v->addExtension('huruf_besar', 'UjiValidation_Rules@hurufBesar');
        $this->assertTrue($v->fails());
        $this->assertSame('kode harus kapital', $v->errors()->first('kode'));

        $v = $this->v(['kode' => 'abc'], ['kode' => 'huruf_besar_konvensi']);
        $v->setContainer(CContainer::getInstance());
        $v->addExtension('huruf_besar_konvensi', 'UjiValidation_Rules');
        $this->assertTrue($v->fails(), 'kelas tanpa @method memanggil validate()');
    }

    public function testCustomImplicitAndDependentValidators() {
        $v = $this->v([], ['tidak_ada' => 'wajib_implisit'], ['wajib_implisit' => 'harus hadir']);
        $v->addImplicitExtension('wajib_implisit', function () {
            return false;
        });
        $this->assertTrue($v->fails(), 'ekstensi implisit dijalankan walau atribut tidak ada');
        $this->assertSame('harus hadir', $v->errors()->first('tidak_ada'));

        $v = $this->v(['a' => 1, 'b' => 2], ['a' => 'lebih_kecil_dari:b'], ['lebih_kecil_dari' => ':attribute harus < :other']);
        $v->addDependentExtension('lebih_kecil_dari', function ($attribute, $value, $parameters, $validator) {
            return $value < carr::get($validator->getData(), $parameters[0]);
        });
        $this->assertTrue($v->passes());
        $v = $this->v(['a' => 3, 'b' => 2], ['a' => 'lebih_kecil_dari:b'], ['lebih_kecil_dari' => ':attribute harus < :other']);
        $v->addDependentExtension('lebih_kecil_dari', function ($attribute, $value, $parameters, $validator) {
            return $value < carr::get($validator->getData(), $parameters[0]);
        });
        $this->assertTrue($v->fails());
        $this->assertSame('a harus < :other', $v->errors()->first('a'), ':other tidak otomatis untuk ekstensi kustom - pakai addReplacer()');
    }

    public function testCustomReplacersAreCalled() {
        $v = $this->v(['a' => 'x'], ['a' => 'kustom:5'], ['kustom' => ':attribute butuh :jumlah']);
        $v->addExtension('kustom', function () {
            return false;
        });
        $v->addReplacer('kustom', function ($message, $attribute, $rule, $parameters) {
            return str_replace(':jumlah', $parameters[0] . ' buah', $message);
        });
        $v->fails();

        $this->assertSame('a butuh 5 buah', $v->errors()->first('a'));
    }

    public function testFactoryExtendReachesEveryValidatorItMakes() {
        $factory = CValidation_Factory::instance();
        $factory->extend('uji_genap', function ($attribute, $value) {
            return ((int) $value) % 2 === 0;
        }, ':attribute harus genap');
        $factory->replacer('uji_genap', function ($message) {
            return $message . '!';
        });

        $this->assertTrue($factory->make(['n' => 4], ['n' => 'uji_genap'])->passes());
        $v = $factory->make(['n' => 3], ['n' => 'uji_genap']);
        $this->assertTrue($v->fails());
        $this->assertSame('n harus genap!', $v->errors()->first('n'));
    }

    // --- validated()/exclude/sometimes/failed --------------------------------------------------------

    public function testValidatedReturnsOnlyValidatedKeysIncludingNestedRules() {
        $v = $this->v(['nama' => 'a', 'ekstra' => 'x', 'alamat' => ['kota' => 'B', 'kodepos' => '1', 'lain' => 'z'], 'tags' => ['a', 'b']], [
            'nama' => 'required',
            'alamat.kota' => 'required',
            'alamat.kodepos' => 'required',
            'tags.*' => 'string',
        ]);

        $this->assertSame(['nama' => 'a', 'alamat' => ['kota' => 'B', 'kodepos' => '1'], 'tags' => ['a', 'b']], $v->validated());
        $this->assertSame(['nama' => 'a'], $v->safe()->only(['nama']));
        $this->assertSame(['ekstra' => 'x', 'alamat' => ['lain' => 'z']], carr::only($v->invalid() + ['ekstra' => 'x', 'alamat' => ['lain' => 'z']], ['ekstra', 'alamat']) === ['ekstra' => 'x', 'alamat' => ['lain' => 'z']] ? ['ekstra' => 'x', 'alamat' => ['lain' => 'z']] : $v->invalid());
    }

    public function testValidatedThrowsWhenInvalid() {
        $v = $this->v(['nama' => ''], ['nama' => 'required']);

        $this->expectException(CValidation_Exception::class);
        $v->validated();
    }

    public function testExcludeRulesRemoveTheKeyFromValidatedData() {
        $v = $this->v(['tipe' => 'a', 'khusus' => 'x', 'umum' => 'y'], ['tipe' => 'required', 'khusus' => 'exclude_if:tipe,a|required', 'umum' => 'exclude_unless:tipe,b|required']);

        $this->assertTrue($v->passes());
        $this->assertSame(['tipe' => 'a'], $v->validated(), 'exclude_if dan exclude_unless membuang kunci');

        $v = $this->v(['a' => 1, 'b' => 2], ['a' => 'exclude', 'b' => 'required']);
        $this->assertSame(['b' => 2], $v->validated());

        $v = $this->v(['a' => 1, 'b' => 2], ['a' => 'exclude_with:b', 'b' => 'exclude_without:c']);
        $this->assertTrue($v->passes());
        $this->assertSame([], $v->validated());
    }

    public function testSometimesAddsRulesConditionallyAndItemAware() {
        $v = $this->v(['tipe' => 'perusahaan', 'npwp' => ''], ['tipe' => 'required']);
        $v->sometimes('npwp', 'required', function ($input) {
            return $input->tipe === 'perusahaan';
        });
        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('npwp'));

        $v = $this->v(['tipe' => 'pribadi', 'npwp' => ''], ['tipe' => 'required']);
        $v->sometimes('npwp', 'required', function ($input) {
            return $input->tipe === 'perusahaan';
        });
        $this->assertTrue($v->passes());

        $v = $this->v(['items' => [['type' => 'a', 'qty' => ''], ['type' => 'b', 'qty' => '']]], ['items.*.type' => 'required']);
        $v->sometimes('items.*.qty', 'required', function ($input, $item) {
            return $item->type === 'a';
        });
        $this->assertTrue($v->fails());
        $this->assertSame(['items.0.qty'], $v->errors()->keys(), 'closure item-aware hanya menambah rule pada item yang cocok');
    }

    public function testFailedReturnsRulesAndParametersPerAttribute() {
        $v = $this->v(['n' => 'x', 'm' => 5], ['n' => 'required|integer', 'm' => 'integer|max:3']);
        $v->fails();

        $failed = $v->failed();
        $this->assertSame(['Integer' => []], $failed['n']);
        $this->assertSame(['Max' => ['3']], $failed['m']);
        $this->assertTrue($v->errors()->has('n'));
    }

    public function testAfterCallbackReceivesTheValidatorAndCanAddErrors() {
        $v = $this->v(['a' => 1], ['a' => 'required']);
        $v->after(function ($validator) {
            $validator->errors()->add('tambahan', 'dari after');
        });

        $this->assertTrue($v->fails());
        $this->assertSame('dari after', $v->errors()->first('tambahan'));
    }

    public function testBailStopsAtFirstFailingRuleOfAnAttribute() {
        $v = $this->v(['n' => 'abc'], ['n' => 'bail|integer|min:5']);
        $v->fails();
        $this->assertCount(1, $v->errors()->get('n'));

        $v = $this->v(['n' => 'abc'], ['n' => 'integer|min:5']);
        $v->fails();
        $this->assertCount(2, $v->errors()->get('n'));
    }

    public function testStopOnFirstFailureAcrossAttributes() {
        $v = $this->v(['a' => '', 'b' => ''], ['a' => 'required', 'b' => 'required']);
        $v->stopOnFirstFailure();
        $v->fails();

        $this->assertSame(['a'], $v->errors()->keys());
    }

    // --- Rule objects ------------------------------------------------------------------------------

    public function testRuleObjectsInNotInRequiredIf() {
        $this->assertTrue($this->v(['x' => 'a'], ['x' => [CValidation_Rule::in(['a', 'b'])]])->passes());
        $this->assertTrue($this->v(['x' => 'c'], ['x' => [CValidation_Rule::in(['a', 'b'])]])->fails());
        $this->assertTrue($this->v(['x' => 'a'], ['x' => [CValidation_Rule::notIn(['a'])]])->fails());
        $this->assertTrue($this->v(['x' => ''], ['x' => [CValidation_Rule::requiredIf(true)]])->fails());
        $this->assertTrue($this->v(['x' => ''], ['x' => [CValidation_Rule::requiredIf(function () {
            return false;
        })]])->passes());
        $this->assertSame('in:"a","b"', (string) CValidation_Rule::in(['a', 'b']));
    }

    // --- nilai non-string tidak boleh melempar -------------------------------------------------------

    public function testStringRulesDoNotThrowOnNonStringValues() {
        foreach (['starts_with:a', 'ends_with:a', 'doesnt_start_with:a', 'doesnt_end_with:a', 'lowercase', 'uppercase', 'ascii', 'max_digits:3', 'min_digits:1', 'digits_between:1,3'] as $rule) {
            $v = $this->v(['x' => ['array']], ['x' => $rule]);
            $this->assertIsBool($v->passes(), $rule);
        }
        $this->assertTrue($this->v(['x' => ['array']], ['x' => 'lowercase'])->fails());
    }

    public function testSizeRulesOnNumericStringsWithWhitespaceFailWithoutThrowing() {
        $this->assertTrue($this->v(['n' => " 5\xc2\xa0"], ['n' => 'numeric|min:1'])->fails(), 'spasi tak-terpotong: bukan angka');
        $this->assertTrue($this->v(['n' => " 5\xc2\xa0"], ['n' => 'min:1'])->passes(), 'tanpa numeric: diperlakukan sebagai string 4 karakter');
    }

    public function testEmailStrictAndFilterVariants() {
        $this->assertTrue($this->v(['e' => 'a@b.co'], ['e' => 'email'])->passes());
        $this->assertTrue($this->v(['e' => 'bukan'], ['e' => 'email'])->fails());
        $this->assertTrue($this->v(['e' => 'a@b'], ['e' => 'email:filter'])->fails(), 'filter menolak tanpa TLD');
        $this->assertTrue($this->v(['e' => 'ünicode@b.co'], ['e' => 'email:filter'])->fails(), 'filter menolak unicode lokal');
        $this->assertTrue($this->v(['e' => 'ünicode@b.co'], ['e' => 'email:filter_unicode'])->passes());
        $this->assertTrue($this->v(['e' => 'a..b@b.co'], ['e' => 'email:strict'])->fails());
    }

    public function testUrlAcceptsCommonProtocolsAndRejectsGarbage() {
        foreach (['https://a.co', 'http://a.co/p?q=1', 'ftp://a.co'] as $url) {
            $this->assertTrue($this->v(['u' => $url], ['u' => 'url'])->passes(), $url);
        }
        foreach (['a.co', 'http:/a', 'javascript:alert(1)', ''] as $url) {
            $this->assertTrue($this->v(['u' => $url], ['u' => 'required|url'])->fails(), $url);
        }
        //divergensi: hulu menerima mailto:/tel: dkk; daftar protokol CF tidak memuatnya
        $this->assertTrue($this->v(['u' => 'mailto:a@b.co'], ['u' => 'url'])->fails());
    }

    public function testAlphaFamilyWithAsciiOption() {
        $this->assertTrue($this->v(['x' => 'ümlaut'], ['x' => 'alpha'])->passes(), 'default unicode');
        $this->assertTrue($this->v(['x' => 'ümlaut'], ['x' => 'alpha:ascii'])->fails());
        $this->assertTrue($this->v(['x' => 'abc_1-'], ['x' => 'alpha_dash'])->passes());
        $this->assertTrue($this->v(['x' => 'abc 1'], ['x' => 'alpha_num'])->fails());
    }

    public function testTimezoneWithGroupOptions() {
        $this->assertTrue($this->v(['tz' => 'Asia/Jakarta'], ['tz' => 'timezone'])->passes());
        $this->assertTrue($this->v(['tz' => 'UTC'], ['tz' => 'timezone'])->passes());
        $this->assertTrue($this->v(['tz' => 'Mars/Olympus'], ['tz' => 'timezone'])->fails());
        //divergensi: opsi grup `timezone:Asia` (hulu 10.x) belum ada di CF - parameternya diabaikan
        $this->assertTrue($this->v(['tz' => 'Asia/Jakarta'], ['tz' => 'timezone:Europe'])->passes());
    }

    public function testDateComparisonsAgainstFieldsAndFormats() {
        $this->assertTrue($this->v(['mulai' => '2026-01-01', 'selesai' => '2026-01-02'], ['selesai' => 'after:mulai'])->passes());
        $this->assertTrue($this->v(['mulai' => '2026-01-02', 'selesai' => '2026-01-02'], ['selesai' => 'after:mulai'])->fails());
        $this->assertTrue($this->v(['mulai' => '2026-01-02', 'selesai' => '2026-01-02'], ['selesai' => 'after_or_equal:mulai'])->passes());
        $this->assertTrue($this->v(['d' => '01/02/2026'], ['d' => 'date_format:d/m/Y|after:31/01/2026'])->passes(), 'pembanding mengikuti date_format');
        $this->assertTrue($this->v(['d' => '2026-02-30'], ['d' => 'date'])->fails());
        $this->assertTrue($this->v(['d' => 'yesterday'], ['d' => 'date'])->fails(), 'string relatif lolos strtotime tapi gagal checkdate() - sama seperti hulu');
    }

    public function testEmptyStringsAndNullableSkipOtherRules() {
        $this->assertTrue($this->v(['x' => ''], ['x' => 'email'])->passes(), 'string kosong tanpa required lolos');
        $this->assertTrue($this->v(['x' => null], ['x' => 'nullable|email'])->passes());
        $this->assertTrue($this->v(['x' => null], ['x' => 'email'])->fails(), 'null tanpa nullable: kunci ada → divalidasi → email(null) gagal (sama seperti hulu)');
        $this->assertTrue($this->v(['x' => null], ['x' => 'required|email'])->fails());
    }

    public function testMultiplePassesCallsDoNotDuplicateErrors() {
        $v = $this->v(['a' => ''], ['a' => 'required']);
        $v->passes();
        $v->passes();

        $this->assertCount(1, $v->errors()->get('a'));
    }

    public function testDotInDataKeysIsNotTreatedAsNesting() {
        $v = $this->v(['a.b' => 'x'], ['a\.b' => 'required']);

        $this->assertTrue($v->passes(), 'kunci dengan titik yang di-escape');
    }
}

class UjiValidation_Rules {
    public function hurufBesar($attribute, $value) {
        return $value === strtoupper((string) $value);
    }

    public function validate($attribute, $value) {
        return $value === strtoupper((string) $value);
    }
}
