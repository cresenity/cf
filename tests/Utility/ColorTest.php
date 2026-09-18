<?php

use PHPUnit\Framework\TestCase;

/**
 * CColor: parsing hex/rgb/rgba/hsl/hsla/hsv/nama warna, konversi antar format, manipulasi
 * (lighten/darken/saturate/grayscale/spin/mix), isLight/isDark, dan galat untuk string tak dikenal.
 */
class ColorTest extends TestCase {
    public function testCreateDetectsTheFormatFromTheString() {
        $this->assertInstanceOf(CColor_Format_Hex::class, CColor::create('#ff0000'));
        $this->assertInstanceOf(CColor_Format_Hex::class, CColor::create('f00'));
        $this->assertInstanceOf(CColor_Format_Hexa::class, CColor::create('#ff000080'));
        $this->assertInstanceOf(CColor_Format_Rgb::class, CColor::create('rgb(255, 0, 0)'));
        $this->assertInstanceOf(CColor_Format_Rgb::class, CColor::create('255,0,0'));
        $this->assertInstanceOf(CColor_Format_Rgba::class, CColor::create('rgba(255,0,0,0.5)'));
        $this->assertInstanceOf(CColor_Format_Rgba::class, CColor::create('255,0,0,0.5'));
        $this->assertInstanceOf(CColor_Format_Hsl::class, CColor::create('hsl(0,100%,50%)'));
        $this->assertInstanceOf(CColor_Format_Hsla::class, CColor::create('hsla(0,100%,50%,1)'));
        $this->assertInstanceOf(CColor_Format_Hsv::class, CColor::create('hsv(0,100%,100%)'));
        $this->assertInstanceOf(CColor_Format_Hex::class, CColor::create('red'), 'nama warna CSS');
    }

    public function testAmbiguousOrInvalidStringsThrow() {
        try {
            CColor::create('0,100%,50%');
            $this->fail('harus melempar');
        } catch (CColor_Exception_AmbiguousColorStringException $e) {
            $this->assertStringContainsString('0,100%,50%', $e->getMessage());
        }
        $this->expectException(CColor_Exception_AmbiguousColorStringException::class);
        CColor::create('#gggggg');
    }

    public function testAKnownFormatWithAnInvalidCodeThrowsInvalidColor() {
        $this->expectException(CColor_Exception_InvalidColorException::class);
        new CColor_Format_Hex('gggggg');
    }

    public function testHexNormalizationAndToString() {
        $this->assertSame('#FF0000', strtoupper((string) CColor::create('f00')));
        $this->assertSame('#FF0000', strtoupper((string) CColor::create('red')));
        $this->assertSame(['ff', '00', '00'], array_map('strtolower', CColor::create('#FF0000')->values()));
    }

    public function testConversionsBetweenFormatsAreConsistent() {
        $red = CColor::create('#ff0000');

        $this->assertSame('rgb(255,0,0)', (string) $red->toRgb());
        $this->assertSame('rgba(255,0,0,1)', (string) $red->toRgba());
        $this->assertSame('hsl(0,100%,50%)', (string) $red->toHsl());
        $this->assertSame('hsla(0,100%,50%,1)', (string) $red->toHsla());
        $this->assertSame('hsv(0,100%,100%)', (string) $red->toHsv());
        $this->assertSame('#ff0000', strtolower((string) CColor::create('rgb(255,0,0)')->toHex()));
        $this->assertSame('#ff0000', strtolower((string) CColor::create('hsl(0,100%,50%)')->toHex()));
        $this->assertSame('#00ff00', strtolower((string) CColor::create('hsv(120,100%,100%)')->toHex()));
        //rgba → rgb dikomposit ke latar putih (alpha 0.5: 255,127,127), lalu alpha dipasang lagi (0.5 → 7f)
        $this->assertSame('rgb(255,127,127)', (string) CColor::create('rgba(255,0,0,0.5)')->toRgb());
        $this->assertSame('#ff7f7f7f', strtolower((string) CColor::create('rgba(255,0,0,0.5)')->toHexa()));
        $this->assertSame('#ff00007f', strtolower((string) CColor::create('#ff0000')->toHexa()->alpha(0.5)), 'alpha langsung pada hexa tidak mengomposit');
    }

    public function testLightenDarkenAndGrayscale() {
        $this->assertSame('#ffffff', strtolower((string) CColor::create('#808080')->lighten(100)->toHex()));
        $this->assertSame('#000000', strtolower((string) CColor::create('#808080')->darken(100)->toHex()));
        $this->assertSame('#ff8080', strtolower((string) CColor::create('#ff0000')->lighten(25)->toHex()), 'lighten 25% dari merah murni');
        $this->assertSame('#800000', strtolower((string) CColor::create('#ff0000')->darken(25)->toHex()));
        $gray = CColor::create('#ff0000')->grayscale()->toRgb()->values();
        $this->assertSame($gray[0], $gray[1]);
        $this->assertSame($gray[1], $gray[2]);
    }

    public function testSaturateDesaturateAndSpin() {
        $this->assertSame('#ff0000', strtolower((string) CColor::create('#bf4040')->saturate(100)->toHex()));
        $this->assertSame('#808080', strtolower((string) CColor::create('#ff0000')->desaturate(100)->toHex()));
        $this->assertSame('#00ff00', strtolower((string) CColor::create('#ff0000')->spin(120)->toHex()), 'putar hue 120°');
        $this->assertSame('#0000ff', strtolower((string) CColor::create('#ff0000')->spin(-120)->toHex()));
    }

    public function testMixBlendsTwoColors() {
        $mixed = CColor::create('#ff0000')->mix(CColor::create('#0000ff'));
        $this->assertSame('#800080', strtolower((string) $mixed->toHex()), '50/50 merah-biru = ungu');
        $this->assertSame('#ff0000', strtolower((string) CColor::create('#ff0000')->mix(CColor::create('#0000ff'), 0)->toHex()), '0% = warna asal');
        $this->assertSame('#0000ff', strtolower((string) CColor::create('#ff0000')->mix(CColor::create('#0000ff'), 100)->toHex()));
    }

    public function testIsLightAndIsDark() {
        $this->assertTrue(CColor::create('#ffffff')->isLight());
        $this->assertFalse(CColor::create('#ffffff')->isDark());
        $this->assertTrue(CColor::create('#000000')->isDark());
        $this->assertTrue(CColor::create('#ffff00')->isLight(), 'kuning terang');
        $this->assertTrue(CColor::create('#000080')->isDark(), 'navy gelap');
        $this->assertTrue(CColor::create('#808080')->isLight(), 'abu-abu tengah: darkness 0.498 < 0.5');
        $this->assertFalse(CColor::create('#808080')->isLight(0.3), 'ambang bisa diatur');
    }

    public function testRandomAndFromStringAreDeterministicOnlyForFromString() {
        $a = CColor::fromString('budi');
        $b = CColor::fromString('budi');
        $c = CColor::fromString('ani');

        $this->assertInstanceOf(CColor_String::class, $a);
        $this->assertSame((string) $a->toHex(), (string) $b->toHex(), 'string yang sama → warna yang sama');
        $this->assertNotSame((string) $a->toHex(), (string) $c->toHex());
        $this->assertInstanceOf(CColor_Format_Hsv::class, $a->toHsv());
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', (string) CColor::random()->toHex());
    }

    public function testCssHelperBuildsBackgroundAndForeground() {
        $css = CColor::css('#ff0000', '#ffffff');

        $this->assertInstanceOf(CColor_Css::class, $css);
        $this->assertTrue($css->isHex('#ff0000'));
        $this->assertFalse($css->isHex('merah'));
        $this->assertSame([255, 0, 0], $css->hex2RGB('ff0000'), 'hex2RGB tanpa #');
        $this->assertSame([255, 0, 0], $css->hex2RGB('f00'));
        $this->assertSame('ff0000', strtolower($css->RGB2Hex([255, 0, 0])));
        $this->assertGreaterThan($css->brightness('000000'), $css->brightness('ffffff'));
        $this->assertSame('ffffff', strtolower($css->calcFG('000000', 'ffffff')), 'kontras sudah cukup → warna depan tidak diubah');
        $this->assertNotSame('111111', strtolower($css->calcFG('000000', '111111')), 'kontras kurang → warna depan digeser');
    }
}
