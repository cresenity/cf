<?php
use PHPUnit\Framework\TestCase;

/**
 * cmsg (pesan flash di session), clang (terjemahan + daftar bahasa), ctemp (pembungkus
 * CTemporary) - pembungkus tipis yang kontraknya dipakai controller app.
 */
class cmsgClangCtempTest extends TestCase {
    protected function tearDown(): void {
        cmsg::clear_all();
    }

    public function testMessagesAccumulatePerTypeAndClearIndependently() {
        if (CSession::store() === null) {
            $this->markTestSkipped('tidak ada session store di lingkungan ini');
        }
        cmsg::add('success', 'Tersimpan');
        cmsg::add('success', 'Terkirim');
        cmsg::add('error', 'Gagal');
        $this->assertSame(['Tersimpan', 'Terkirim'], cmsg::get('success'));
        $this->assertSame(['Gagal'], cmsg::get('error'));
        cmsg::clear('success');
        $this->assertNull(cmsg::get('success'));
        $this->assertSame(['Gagal'], cmsg::get('error'), 'tipe lain tidak tersentuh');
        cmsg::clear_all();
        $this->assertNull(cmsg::get('error'));
    }

    public function testFlashRendersAnAlertAndEmptiesTheQueue() {
        if (CSession::store() === null) {
            $this->markTestSkipped('tidak ada session store di lingkungan ini');
        }
        cmsg::add('warning', 'Hati-hati');
        cmsg::add('warning', 'Sekali lagi');
        $html = cmsg::flash('warning');
        $this->assertStringContainsString('capp-message', $html);
        $this->assertStringContainsString('<p>Hati-hati</p><p>Sekali lagi</p>', $html);
        $this->assertStringContainsString('Warning!', $html, 'judul = tipe dikapitalkan');
        $this->assertNull(cmsg::get('warning'), 'flash mengosongkan antrean');
        $this->assertSame('', cmsg::flash('warning'), 'kosong = string kosong, bukan alert kosong');
    }

    public function testFlashAllConcatenatesInFixedOrder() {
        if (CSession::store() === null) {
            $this->markTestSkipped('tidak ada session store di lingkungan ini');
        }
        cmsg::add('success', 'S');
        cmsg::add('error', 'E');
        $html = cmsg::flash_all();
        $this->assertLessThan(strpos($html, '<p>S</p>'), strpos($html, '<p>E</p>'), 'error dulu, success terakhir');
        $this->assertSame('', cmsg::flash_all());
    }

    public function testClangTranslatesPlainWordsAndDottedKeys() {
        $this->assertSame('Save', clang::__('Save'), 'kata tanpa terjemahan kembali apa adanya');
        $this->assertSame(c::__('Save'), clang::__('Save'));
        $this->assertSame('Halo Hery', clang::__('Halo :name', [':name' => 'Hery']), 'parameter diganti');
        $this->assertSame('kunci.tak.ada', clang::__('kunci.tak.ada'), 'kunci bertitik tanpa berkas bahasa kembali kuncinya');
    }

    public function testClangLanguageList() {
        $this->assertSame(['default' => 'Default', 'en' => 'English', 'id' => 'Indonesia'], clang::get_lang_list());
        $this->assertSame('Indonesia', clang::get_lang_name_by_code('id'));
        $this->assertNull(clang::get_lang_name_by_code('xx'));
        $this->assertSame(ccfg::get('lang'), clang::defaultlang());
        $this->assertContains(clang::current_lang_name(), array_merge(array_values(clang::get_lang_list()), [null]));
    }

    public function testClangGetDirReturnsALangDirectoryOrNull() {
        $dir = clang::get_dir('en');
        $this->assertTrue($dir === null || is_dir($dir));
    }

    public function testCtempWrapsCTemporary() {
        $this->assertSame(CTemporary::getDirectory(), ctemp::get_directory());
        $this->assertStringEndsWith('temp' . DIRECTORY_SEPARATOR, ctemp::get_directory());
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uji-ctemp-' . bin2hex(random_bytes(3)) . DIRECTORY_SEPARATOR;
        $this->assertSame($base, ctemp::makedir($base));
        $this->assertDirectoryExists($base);
        $this->assertSame($base . 'sub' . DIRECTORY_SEPARATOR, ctemp::makefolder($base, 'sub'));
        $this->assertDirectoryExists($base . 'sub');
        rmdir($base . 'sub');
        rmdir($base);
        $path = ctemp::makepath('uji', 'x.txt');
        $this->assertSame(CTemporary::makePath('uji', 'x.txt'), $path);
        $this->assertStringContainsString('uji' . DIRECTORY_SEPARATOR . CF::appCode(), $path, 'per app (lihat CTemporary)');
    }
}
