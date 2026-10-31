<?php

use PHPUnit\Framework\TestCase;

class UjiBaseUtilsUnformat {
    use CBase_Trait_UtilsTrait;
}

/**
 * CBase_Trait_UtilsTrait::unformatCurrency()/unformatNumber(): pemisah kosong mengikuti
 * c::formatter(), pemisah yang diberikan tetap dipakai, dan desimal tidak hilang.
 */
class BaseUtilsTraitUnformatTest extends TestCase {
    /**
     * @var array [decimal, thousand] setting singleton sebelum test
     */
    protected $original;

    protected function setUp(): void {
        $formatter = CApp_Formatter::instance();
        $this->original = [$formatter->getDecimalSeparator(), $formatter->getThousandSeparator()];
    }

    protected function tearDown(): void {
        CApp_Formatter::instance()->setDecimalSeparator($this->original[0])->setThousandSeparator($this->original[1]);
    }

    public function testSettingBawaanSamaDenganPerilakuLama() {
        CApp_Formatter::instance()->setDecimalSeparator('.')->setThousandSeparator(',');
        $this->assertSame(1500000, UjiBaseUtilsUnformat::unformatCurrency('1,500,000'));
        $this->assertSame(1234.56, UjiBaseUtilsUnformat::unformatCurrency('Rp 1,234.56'));
        $this->assertSame(12.5, UjiBaseUtilsUnformat::unformatNumber('12.5'));
        $this->assertSame(1500, UjiBaseUtilsUnformat::unformatNumber(1500));
    }

    public function testTanpaPemisahMengikutiFormatter() {
        CApp_Formatter::instance()->setDecimalSeparator(',')->setThousandSeparator('.');
        $this->assertSame(12.5, UjiBaseUtilsUnformat::unformatCurrency('12,5'));
        $this->assertSame(1500000, UjiBaseUtilsUnformat::unformatNumber('1.500.000'));
    }

    public function testPemisahEksplisitTetapDipakai() {
        CApp_Formatter::instance()->setDecimalSeparator('.')->setThousandSeparator(',');
        $this->assertSame(1500000, UjiBaseUtilsUnformat::unformatCurrency('1.500.000', true, ',', '.'));
        $this->assertSame(12.5, UjiBaseUtilsUnformat::unformatCurrency('12,5', true, ',', '.'));
    }

    public function testForceNumberFalseMenolakAwalanBukanAngka() {
        $this->assertFalse(UjiBaseUtilsUnformat::unformatCurrency('Rp 1.000', false));
        $this->assertFalse(UjiBaseUtilsUnformat::unformatNumber('abc', false));
    }
}
