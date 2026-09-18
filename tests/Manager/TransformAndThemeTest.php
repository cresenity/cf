<?php

use PHPUnit\Framework\TestCase;

/**
 * CManager_Transform (rantai transformasi `metode:arg|metode2` yang dipakai kolom tabel/exporter):
 * metode bawaan, argumen, rantai `|`, closure, callback terdaftar, dan CManager_Theme (tema aktif,
 * callback, data tema).
 */
class TransformAndThemeTest extends TestCase {
    /**
     * @var mixed
     */
    protected $originalTheme;

    protected function setUp(): void {
        $this->originalTheme = CManager_Theme::getDefaultTheme();
    }

    protected function tearDown(): void {
        CManager_Theme::setTheme($this->originalTheme);
        CManager_Theme::setThemeCallback(function ($theme) {
            return $theme;
        });
    }

    public function testBuiltinMethodsTransformValues() {
        $transform = CManager_Transform::instance();

        $this->assertSame('HALO', $transform->call('uppercase', 'halo'));
        $this->assertSame('halo', $transform->call('lowercase', 'HALO'));
        $this->assertSame('Halo', $transform->call('ucfirst', 'halo'));
        $this->assertSame('YES', $transform->call('yesNo', 1));
        $this->assertSame('NO', $transform->call('yesNo', 0));
        $this->assertSame('&lt;b&gt;', $transform->call('escape', '<b>'));
        $this->assertSame('<span class="badge merah">x</span>', $transform->call('span:badge,merah', 'x'));
        $this->assertSame('<div class="kotak">x</div>', $transform->call('div:kotak', 'x'));
        $this->assertSame('pendek', $transform->call('showMore:10', 'pendek'));
        $this->assertInstanceOf(CElement_Component_ShowMore::class, $transform->call('showMore:3', 'panjang sekali'));
    }

    public function testMethodsCanBeChainedWithPipe() {
        $transform = CManager_Transform::instance();

        $this->assertSame('<span class="b">HALO</span>', $transform->call('uppercase|span:b', 'halo'));
        $this->assertSame('HALO', $transform->call(['lowercase', 'uppercase'], 'HaLo'), 'array = rantai');
    }

    public function testMethodNamesAreNormalizedAndAliasesResolve() {
        $this->assertSame('Uppercase', CManager_Transform_Parser::normalizeMethod('uppercase'));
        $this->assertSame('FormatDate', CManager_Transform_Parser::normalizeMethod('format_date'));
        $this->assertSame('FormatDate', CManager_Transform_Parser::normalizeMethod('formatDate'));
        $this->assertSame(['uppercase', 'span:b'], CManager_Transform_Parser::explodeMethod('uppercase|span:b'));
        list($method, $arguments) = CManager_Transform_Parser::parse('span:badge,merah');
        $this->assertSame('Span', $method, 'parse() sudah menormalkan nama metode');
        $this->assertSame(['badge', 'merah'], $arguments);
    }

    public function testClosureAndRegisteredCallbackMethods() {
        $transform = CManager_Transform::instance();

        $this->assertSame('x!', $transform->call(function ($value) {
            return $value . '!';
        }, 'x'));

        //callback terdaftar dan closure menerima ($value, array $arguments) — argumen tidak di-spread
        $transform->addCallback('ujiKurung', function ($value, array $arguments = []) {
            return carr::get($arguments, 0, '[') . $value . carr::get($arguments, 1, ']');
        });
        $this->assertSame('[x]', $transform->call('ujiKurung', 'x'));
        $this->assertSame('[X]', $transform->call('uppercase|ujiKurung', 'x'));
        //callback terdaftar dicari dengan string metode utuh, jadi bentuk `nama:arg` tidak dikenali dan nilai lewat apa adanya
        $this->assertSame('x', $transform->call('ujiKurung:(,)', 'x'));
        $this->assertTrue($transform->isTransformable('ujiKurung'));
    }

    public function testArgumentsMayReferenceRowFieldsWithBraces() {
        $arguments = CManager_Transform_Parser::getArguments(['{nama}', 'tetap', '{a}-{b}'], ['nama' => 'Budi', 'a' => 1, 'b' => 2]);

        $this->assertSame(['Budi', 'tetap', '1-2'], $arguments);
    }

    public function testCFunctionDelegatesUnknownStringsToTheTransformer() {
        $this->assertSame('HALO', CFunction::factory('uppercase')->execute(['halo']));
        $this->assertSame('<span class="b">x</span>', CFunction::factory('span:b')->execute(['x']));
    }

    public function testThemeSelectionAndCallback() {
        CManager_Theme::setTheme('uji-tema');
        $this->assertSame('uji-tema', CManager_Theme::getDefaultTheme());
        $this->assertSame('uji-tema', CManager_Theme::getCurrentTheme());

        CManager_Theme::setThemeCallback(function ($theme) {
            return $theme . '-mobile';
        });
        $this->assertSame('uji-tema-mobile', CManager_Theme::getCurrentTheme(), 'callback boleh mengganti tema per request');
        $this->assertSame('uji-tema', CManager_Theme::getDefaultTheme(), 'default tidak tersentuh callback');
    }

    public function testThemeDataCanBeInjectedAndRead() {
        CManager_Theme::setTheme('uji-tema-data');
        CManager_Theme::setThemeData(['theme_path' => 'uji', 'data' => ['warna' => 'merah']]);

        $this->assertSame('merah', CManager_Theme::getData('warna'));
        $this->assertSame('bawaan', CManager_Theme::getData('tidak_ada', 'bawaan'));
        $this->assertSame(['theme_path' => 'uji', 'data' => ['warna' => 'merah']], CManager_Theme::getThemeData());
        $this->assertNull(CManager_Theme::getThemeData('tema-yang-tidak-ada-' . uniqid()), 'tema tanpa berkas themes/<nama>.php → null');
    }
}
