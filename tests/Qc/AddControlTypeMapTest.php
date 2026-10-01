<?php

use PHPUnit\Framework\TestCase;

/**
 * Ekstensi PHPStan AddControlExtension hanya menyempitkan tipe kembalian addControl() bila kelas kontrolnya
 * adalah CElement_FormInput. Test ini menjaga agar kontrol bawaan yang tidak memenuhi syarat itu hanya yang sudah
 * diketahui; kontrol lain yang menyimpang akan diam-diam kembali ke tipe `@return` yang lebar.
 *
 * Kelas ekstensinya dibaca sebagai teks: CQC_Phpstan bergantung pada PHPStan yang tidak dimuat di suite ini.
 */
class AddControlTypeMapTest extends TestCase {
    /**
     * @return array
     */
    protected function defaultControls() {
        CApp::registerControl();

        return CManager::instance()->getRegisteredControls();
    }

    public function testDefaultControlsAreRegistered() {
        $controls = $this->defaultControls();

        $this->assertGreaterThan(30, count($controls));
        $this->assertSame(CElement_FormInput_Text::class, $controls['text']);
        $this->assertSame(CElement_FormInput_DateRange::class, $controls['daterange-picker']);
    }

    public function testEveryDefaultControlClassExistsAndOnlyKnownOnesAreNotFormInputs() {
        $notFormInputs = [];
        foreach ($this->defaultControls() as $type => $class) {
            $this->assertTrue(class_exists($class), "kelas kontrol '" . $type . "' tidak ada: " . $class);
            if (!is_a($class, CElement_FormInput::class, true)) {
                $notFormInputs[$type] = $class;
            }
        }

        // quill dibuat lewat factory() miliknya, bukan CElement_Factory::createFormInput(); ekstensi PHPStan
        // sengaja memakai tipe `@return` untuk kontrol seperti ini. Kontrol baru di daftar ini perlu disadari.
        $this->assertSame(['quill' => CElement_FormInput_Textarea_Quill::class], $notFormInputs);
    }

    public function testExtensionReadsTheSameRegistryAsTheFactory() {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/system/libraries/CQC/Phpstan/Service/ReturnType/AddControlExtension.php');

        $this->assertStringContainsString('CManager::instance()', $source);
        $this->assertStringContainsString('getRegisteredControls()', $source);
        $this->assertStringContainsString("'addControl'", $source);
    }

    public function testAddControlIsDeclaredOnTheClassTheExtensionTargets() {
        $method = new ReflectionMethod(CObservable::class, 'addControl');

        $this->assertSame(CObservable::class, $method->getDeclaringClass()->getName());
        $this->assertTrue(is_a(CElement_Component_Form_Field::class, CObservable::class, true), 'Field memakai addControl() dari CObservable');
    }
}
