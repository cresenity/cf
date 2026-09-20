<?php
use PHPUnit\Framework\TestCase;

/**
 * Kalimat polos yang dipakai framework lewat c::__() diterjemahkan dari system/i18n/<locale>.json
 * (dimuat lewat CF::paths(), app bisa menimpa), dan system/ tidak lagi memanggil helper deprecated
 * clang::__ / ccfg::get / crouter::* untuk itu.
 */
class SystemJsonI18nTest extends TestCase {
    public function testSystemIdJsonIsValidAndTranslatesFrameworkStrings() {
        $file = SYSPATH . 'i18n' . DS . 'id_ID.json';
        $this->assertFileExists($file);
        $lines = json_decode(file_get_contents($file), true);
        $this->assertIsArray($lines);
        $this->assertNotEmpty($lines);

        $translator = CTranslation::translator('id_ID');
        $this->assertSame('Masuk', $translator->get('Sign In'));
        $this->assertSame('Kata Sandi', $translator->get('Password'));
        $this->assertSame('Ke halaman 3', $translator->get('Go to page :page', ['page' => 3]), 'placeholder :page terganti');
        $this->assertSame('Ke halaman 3', $translator->get('Go to page :page', [':page' => 3]), 'kunci pengganti berawalan titik dua (gaya lama) tetap bekerja');
        foreach ($lines as $key => $translation) {
            $this->assertSame($translation, $translator->get($key), 'kunci "' . $key . '" harus terbaca dari JSON');
        }
    }

    public function testEnglishFallsBackToTheKeyItself() {
        $translator = CTranslation::translator('en_US');
        $this->assertSame('Sign In', $translator->get('Sign In'));
        $this->assertSame('Go to page 3', $translator->get('Go to page :page', ['page' => 3]));
    }

    public function testEveryJsonKeyIsStillUsedSomewhereInSystem() {
        $lines = json_decode(file_get_contents(SYSPATH . 'i18n' . DS . 'id_ID.json'), true);
        $sources = '';
        foreach (['libraries', 'views', 'core'] as $dir) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(SYSPATH . $dir)) as $file) {
                if ($file->getExtension() === 'php' && strpos($file->getPathname(), 'graphify') === false) {
                    $sources .= file_get_contents($file->getPathname());
                }
            }
        }
        foreach (array_keys($lines) as $key) {
            $this->assertTrue(strpos($sources, $key) !== false, 'kunci "' . $key . '" tidak dipakai lagi di system/ — hapus dari id_ID.json');
        }
    }

    public function testSystemNoLongerCallsTheReplacedDeprecatedHelpers() {
        $pattern = '/\b(clang::__|ccfg::get|crouter::(complete_uri|routed_uri|controller|method|query_string)|crequest::platform_version|cphp::save_value|clog::request)\s*\(/';
        $offenders = [];
        foreach (['libraries', 'views', 'core', 'config', 'data'] as $dir) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(SYSPATH . $dir)) as $file) {
                if ($file->getExtension() !== 'php' || strpos($file->getPathname(), 'graphify') !== false) {
                    continue;
                }
                foreach (file($file->getPathname()) as $i => $line) {
                    if (preg_match($pattern, $line) && strpos(ltrim($line), '//') !== 0) {
                        $offenders[] = str_replace(SYSPATH, '', $file->getPathname()) . ':' . ($i + 1);
                    }
                }
            }
        }
        $this->assertSame([], $offenders, 'pemakaian helper deprecated di dalam system/ (pakai c::__, CApp_Config::get, c::router()->current(), CFile::putPhpValue, CApp_Log_Request::populate)');
    }
}
