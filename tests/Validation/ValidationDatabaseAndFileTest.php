<?php

require_once __DIR__ . '/../Model/Integration/UjiModelSupport.php';

/**
 * Rule yang butuh basis data (exists/unique lewat CValidation_PresenceVerifier_Database di koneksi
 * SQLite in-memory `uji_model`) dan rule berkas (file/image/mimes/mimetypes/dimensions/size) memakai
 * berkas unggahan palsu CHTTP_UploadedFile::fake().
 */
class ValidationDatabaseAndFileTest extends UjiModel_IntegrationTestCase {
    /**
     * @param array $data
     * @param array $rules
     *
     * @return CValidation_Validator
     */
    protected function v(array $data, array $rules) {
        return new CValidation_Validator($data, $rules);
    }

    public function testExistsWithConnectionTableAndColumn() {
        $this->createUser(['name' => 'budi', 'email' => 'b@x.co']);

        $this->assertTrue($this->v(['n' => 'budi'], ['n' => 'exists:uji_model.uji_user,name'])->passes());
        $this->assertTrue($this->v(['n' => 'ani'], ['n' => 'exists:uji_model.uji_user,name'])->fails());
        $this->assertTrue($this->v(['e' => 'b@x.co'], ['e' => 'exists:uji_model.uji_user,email'])->passes());
        $this->assertTrue($this->v(['n' => 'budi'], ['n' => 'exists:uji_model.uji_user'])->fails(), 'tanpa kolom → kolom = nama atribut (n) yang tidak ada... query gagal → false');
    }

    public function testExistsHonoursExtraWhereClausesAndArrays() {
        $this->createUser(['name' => 'aktif', 'is_active' => true]);
        $this->createUser(['name' => 'mati', 'is_active' => false]);

        $this->assertTrue($this->v(['n' => 'aktif'], ['n' => 'exists:uji_model.uji_user,name,is_active,1'])->passes());
        $this->assertTrue($this->v(['n' => 'mati'], ['n' => 'exists:uji_model.uji_user,name,is_active,1'])->fails());
        $this->assertTrue($this->v(['n' => ['aktif', 'mati']], ['n' => 'array', 'n.*' => 'exists:uji_model.uji_user,name'])->passes());
        $this->assertTrue($this->v(['n' => ['aktif', 'tidak']], ['n' => 'array', 'n.*' => 'exists:uji_model.uji_user,name'])->fails());
    }

    public function testExistsIgnoresSoftDeletedRowsOnlyWhenTold() {
        $user = $this->createUser(['name' => 'hapus']);
        $user->delete();

        $this->assertTrue($this->v(['n' => 'hapus'], ['n' => 'exists:uji_model.uji_user,name'])->passes(), 'presence verifier memakai query builder mentah: baris status 0 tetap terhitung');
        $this->assertTrue($this->v(['n' => 'hapus'], ['n' => 'exists:uji_model.uji_user,name,status,1'])->fails(), 'saring status lewat parameter tambahan');
    }

    public function testUniqueWithIgnoreAndRuleObject() {
        $budi = $this->createUser(['name' => 'budi', 'email' => 'b@x.co']);
        $this->createUser(['name' => 'ani', 'email' => 'a@x.co']);

        $this->assertTrue($this->v(['email' => 'baru@x.co'], ['email' => 'unique:uji_model.uji_user,email'])->passes());
        $this->assertTrue($this->v(['email' => 'b@x.co'], ['email' => 'unique:uji_model.uji_user,email'])->fails());
        $this->assertTrue($this->v(['email' => 'b@x.co'], ['email' => 'unique:uji_model.uji_user,email,' . $budi->getKey() . ',uji_user_id'])->passes(), 'ignore id sendiri saat edit');
        $this->assertTrue($this->v(['email' => 'a@x.co'], ['email' => 'unique:uji_model.uji_user,email,' . $budi->getKey() . ',uji_user_id'])->fails());

        $rule = CValidation_Rule::unique('uji_model.uji_user', 'email')->ignore($budi->getKey(), 'uji_user_id');
        $this->assertTrue($this->v(['email' => 'b@x.co'], ['email' => [$rule]])->passes());
        $this->assertTrue($this->v(['email' => 'a@x.co'], ['email' => [$rule]])->fails());
        $this->assertStringStartsWith('unique:uji_model.uji_user,email,"' . $budi->getKey() . '",uji_user_id', (string) $rule);

        $where = CValidation_Rule::unique('uji_model.uji_user', 'name')->where('is_active', 0);
        $this->assertTrue($this->v(['name' => 'budi'], ['name' => [$where]])->passes(), 'where tambahan menyempitkan pemeriksaan');
    }

    public function testExistsRuleObjectWithWhereNull() {
        $this->createUser(['name' => 'tanpa negara']);

        $rule = CValidation_Rule::exists('uji_model.uji_user', 'name')->whereNull('uji_country_id');
        $this->assertTrue($this->v(['n' => 'tanpa negara'], ['n' => [$rule]])->passes());
        $rule = CValidation_Rule::exists('uji_model.uji_user', 'name')->whereNotNull('uji_country_id');
        $this->assertTrue($this->v(['n' => 'tanpa negara'], ['n' => [$rule]])->fails());
    }

    public function testFileRulesOnAFakeImage() {
        $image = CHTTP_UploadedFile::fake()->image('foto.png', 200, 100);

        $this->assertTrue($this->v(['f' => $image], ['f' => 'file'])->passes());
        $this->assertTrue($this->v(['f' => $image], ['f' => 'image'])->passes());
        $this->assertTrue($this->v(['f' => $image], ['f' => 'mimes:png,jpg'])->passes());
        $this->assertTrue($this->v(['f' => $image], ['f' => 'mimes:pdf'])->fails());
        $this->assertTrue($this->v(['f' => $image], ['f' => 'mimetypes:image/png'])->passes());
        $this->assertTrue($this->v(['f' => $image], ['f' => 'dimensions:min_width=100,min_height=50'])->passes());
        $this->assertTrue($this->v(['f' => $image], ['f' => 'dimensions:max_width=150'])->fails());
        $this->assertTrue($this->v(['f' => $image], ['f' => 'dimensions:ratio=2/1'])->passes());
        $this->assertTrue($this->v(['f' => $image], ['f' => 'dimensions:width=200,height=100'])->passes());
        $this->assertTrue($this->v(['f' => $image], ['f' => 'dimensions:ratio=1'])->fails());
    }

    public function testFileSizeRulesUseKilobytes() {
        $file = CHTTP_UploadedFile::fake()->create('dok.pdf', 30, 'application/pdf');

        $this->assertTrue($this->v(['f' => $file], ['f' => 'file|max:40'])->passes());
        $this->assertTrue($this->v(['f' => $file], ['f' => 'file|max:20'])->fails());
        $this->assertTrue($this->v(['f' => $file], ['f' => 'file|min:20'])->passes());
        $this->assertTrue($this->v(['f' => $file], ['f' => 'file|between:25,35'])->passes());
        $this->assertTrue($this->v(['f' => $file], ['f' => 'image'])->fails(), 'pdf bukan gambar');
    }

    public function testNonFileValuesFailFileRulesWithoutThrowing() {
        $this->assertTrue($this->v(['f' => 'string biasa'], ['f' => 'file'])->fails());
        $this->assertTrue($this->v(['f' => 'string biasa'], ['f' => 'image'])->fails());
        $this->assertTrue($this->v(['f' => ['x']], ['f' => 'mimes:png'])->fails());
        $this->assertTrue($this->v(['f' => 'x'], ['f' => 'dimensions:min_width=1'])->fails());
    }

    public function testRuleFileObjectsBuildTheSameRules() {
        $image = CHTTP_UploadedFile::fake()->image('foto.jpg', 50, 50);

        $this->assertTrue($this->v(['f' => $image], ['f' => [CValidation_Rule::file()->types(['jpg', 'png'])->max(1024)]])->passes());
        $this->assertTrue($this->v(['f' => $image], ['f' => [CValidation_Rule::file()->types(['pdf'])]])->fails());
        $this->assertTrue($this->v(['f' => $image], ['f' => [CValidation_Rule::imageFile()->dimensions(CValidation_Rule::dimensions()->maxWidth(60))]])->passes());
        $this->assertTrue($this->v(['f' => $image], ['f' => [CValidation_Rule::imageFile()->dimensions(CValidation_Rule::dimensions()->maxWidth(40))]])->fails());
    }
}
