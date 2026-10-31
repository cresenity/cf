<?php

use PHPUnit\Framework\TestCase;

/**
 * unformat* CApp_Formatter: desimal tetap utuh untuk setting ribuan titik (Indonesia)
 * maupun ribuan koma (bawaan), dan pemisah bisa diberikan per panggilan.
 */
class AppFormatterUnformatTest extends TestCase {
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

    /**
     * @param string $decimal
     * @param string $thousand
     *
     * @return CApp_Formatter
     */
    protected function formatter($decimal, $thousand) {
        return CApp_Formatter::instance()->setDecimalSeparator($decimal)->setThousandSeparator($thousand);
    }

    public function testDesimalUtuhDenganRibuanTitik() {
        $formatter = $this->formatter(',', '.');
        $this->assertSame(12.5, $formatter->unformatDecimal('12,5'));
        $this->assertSame(1234.56, $formatter->unformatCurrency('1.234,56'));
        $this->assertSame(1500000, $formatter->unformatNumber('1.500.000'));
    }

    public function testSettingBawaanTidakBerubah() {
        $formatter = $this->formatter('.', ',');
        $this->assertSame(12.5, $formatter->unformatDecimal('12.5'));
        $this->assertSame(1234.56, $formatter->unformatCurrency('1,234.56'));
        $this->assertSame(1500000, $formatter->unformatNumber('1,500,000'));
    }

    public function testPrefixMataUangDibuang() {
        $this->assertSame(1500000, $this->formatter(',', '.')->unformatCurrency('Rp 1.500.000'));
    }

    public function testPemisahBisaDiberikanPerPanggilan() {
        $formatter = $this->formatter('.', ',');
        $this->assertSame(12.5, $formatter->unformatDecimal('12,5', ',', '.'));
        $this->assertSame(1234.56, $formatter->unformatCurrency('1.234,56', ',', '.'));
    }

    public function testPemisahRibuanKosong() {
        $this->assertSame(1234.5, $this->formatter(',', '')->unformatDecimal('1234,5'));
    }
}
