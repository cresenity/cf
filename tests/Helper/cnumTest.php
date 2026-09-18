<?php
use PHPUnit\Framework\TestCase;

/**
 * cnum - pemformat angka berbasis intl (format/spell/ordinal/percentage/currency) dan yang
 * murni (fileSize/forHumans/abbreviate/clamp), termasuk locale sementara.
 */
class cnumTest extends TestCase {
    protected function setUp(): void {
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('butuh ekstensi intl');
        }
        cnum::useLocale('en');
    }

    protected function tearDown(): void {
        cnum::useLocale('en');
    }

    public function testFormat() {
        $this->assertSame('0', cnum::format(0));
        $this->assertSame('1', cnum::format(1));
        $this->assertSame('10', cnum::format(10));
        $this->assertSame('25', cnum::format(25));
        $this->assertSame('100', cnum::format(100));
        $this->assertSame('100,000', cnum::format(100000));
        $this->assertSame('100,000.00', cnum::format(100000, 2));
        $this->assertSame('100,000.12', cnum::format(100000.123, 2));
        $this->assertSame('100,000.123', cnum::format(100000.1234, null, 3));
        $this->assertSame('100,000.124', cnum::format(100000.1236, 3));
        $this->assertSame('123,456,789', cnum::format(123456789));
        $this->assertSame('-1', cnum::format(-1));
        $this->assertSame('-10', cnum::format(-10));
        $this->assertSame('-25', cnum::format(-25));
        $this->assertSame('0.2', cnum::format(0.2));
        $this->assertSame('0.20', cnum::format(0.2, 2));
        $this->assertSame('0.123', cnum::format(0.1234, null, 3));
        $this->assertSame('1.23', cnum::format(1.23));
        $this->assertSame('-1.23', cnum::format(-1.23));
        $this->assertSame('123.456', cnum::format(123.456));
        $this->assertSame('∞', cnum::format(INF));
        $this->assertSame('NaN', cnum::format(NAN));
    }

    public function testFormatWithDifferentLocale() {
        $this->assertSame('123.456,789', cnum::format(123456.789, null, null, 'de'));
        $this->assertSame('123 456,789', str_replace("\u{202F}", ' ', str_replace("\u{A0}", ' ', cnum::format(123456.789, null, null, 'fr'))));
        $this->assertSame('123.456,789', cnum::format(123456.789, null, null, 'id'));
    }

    public function testFormatWithAppLocale() {
        $this->assertSame('123,456.789', cnum::format(123456.789));
        cnum::useLocale('de');
        $this->assertSame('123.456,789', cnum::format(123456.789));
    }

    public function testSpellout() {
        $this->assertSame('ten', cnum::spell(10));
        $this->assertSame('one point two', cnum::spell(1.2));
        $this->assertSame('sepuluh', cnum::spell(10, 'id'));
    }

    public function testSpelloutWithThreshold() {
        $this->assertSame('9', cnum::spell(9, null, 10));
        $this->assertSame('10', cnum::spell(10, null, 10));
        $this->assertSame('eleven', cnum::spell(11, null, 10));
        $this->assertSame('nine', cnum::spell(9, null, null, 10));
        $this->assertSame('10', cnum::spell(10, null, null, 10));
        $this->assertSame('11', cnum::spell(11, null, null, 10));
        $this->assertSame('ten thousand', cnum::spell(10000, null, null, 50000));
        $this->assertSame('100,000', cnum::spell(100000, null, null, 50000));
    }

    public function testOrdinal() {
        $this->assertSame('1st', cnum::ordinal(1));
        $this->assertSame('2nd', cnum::ordinal(2));
        $this->assertSame('3rd', cnum::ordinal(3));
        $this->assertSame('11th', cnum::ordinal(11));
        $this->assertSame('22nd', cnum::ordinal(22));
    }

    public function testToPercent() {
        $this->assertSame('0%', cnum::percentage(0, 0));
        $this->assertSame('0%', cnum::percentage(0));
        $this->assertSame('1%', cnum::percentage(1));
        $this->assertSame('10.00%', cnum::percentage(10, 2));
        $this->assertSame('100%', cnum::percentage(100));
        $this->assertSame('100.00%', cnum::percentage(100, 2));
        $this->assertSame('100.123%', cnum::percentage(100.1234, 0, 3));
        $this->assertSame('300%', cnum::percentage(300));
        $this->assertSame('1,000%', cnum::percentage(1000));
        $this->assertSame('2%', cnum::percentage(1.75));
        $this->assertSame('1.75%', cnum::percentage(1.75, 2));
        $this->assertSame('1.750%', cnum::percentage(1.75, 3));
        $this->assertSame('0%', cnum::percentage(0.12345));
        $this->assertSame('0.00%', cnum::percentage(0, 2));
        $this->assertSame('0.12%', cnum::percentage(0.12345, 2));
        $this->assertSame('0.1235%', cnum::percentage(0.12345, 4));
    }

    public function testToCurrency() {
        $this->assertSame('$0.00', cnum::currency(0));
        $this->assertSame('$1.00', cnum::currency(1));
        $this->assertSame('$10.00', cnum::currency(10));
        $this->assertSame('€0.00', cnum::currency(0, 'EUR'));
        $this->assertSame('€1.00', cnum::currency(1, 'EUR'));
        $this->assertSame('€10.00', cnum::currency(10, 'EUR'));
        $this->assertSame('-$5.00', cnum::currency(-5));
        $this->assertSame('$5.00', cnum::currency(5.00));
        $this->assertSame('$5.32', cnum::currency(5.325));
        $this->assertSame('Rp 1.000', str_replace("\u{A0}", ' ', cnum::currency(1000, 'IDR', 'id')), 'rupiah tanpa desimal di locale id');
    }

    public function testBytesToHuman() {
        $this->assertSame('0 B', cnum::fileSize(0));
        $this->assertSame('0.00 B', cnum::fileSize(0, 2));
        $this->assertSame('1 B', cnum::fileSize(1));
        $this->assertSame('1 KB', cnum::fileSize(1024));
        $this->assertSame('2 KB', cnum::fileSize(2048));
        $this->assertSame('2.00 KB', cnum::fileSize(2048, 2));
        $this->assertSame('1.23 KB', cnum::fileSize(1264, 2));
        $this->assertSame('1.234 KB', cnum::fileSize(1264.12345, 0, 3));
        $this->assertSame('1.234 KB', cnum::fileSize(1264, 3));
        $this->assertSame('5 GB', cnum::fileSize(1024 * 1024 * 1024 * 5));
        $this->assertSame('10 TB', cnum::fileSize((1024 ** 4) * 10));
        $this->assertSame('10 PB', cnum::fileSize((1024 ** 5) * 10));
        $this->assertSame('1 ZB', cnum::fileSize(1024 ** 7));
        $this->assertSame('1 YB', cnum::fileSize(1024 ** 8));
        $this->assertSame('1,024 YB', cnum::fileSize(1024 ** 9));
    }

    public function testToHuman() {
        $this->assertSame('1', cnum::forHumans(1));
        $this->assertSame('1.00', cnum::forHumans(1, 2));
        $this->assertSame('10', cnum::forHumans(10));
        $this->assertSame('100', cnum::forHumans(100));
        $this->assertSame('1 thousand', cnum::forHumans(1000));
        $this->assertSame('1.00 thousand', cnum::forHumans(1000, 2));
        $this->assertSame('1 thousand', cnum::forHumans(1000, 0, 2));
        $this->assertSame('1 thousand', cnum::forHumans(1230));
        $this->assertSame('1.2 thousand', cnum::forHumans(1230, 0, 1));
        $this->assertSame('1 million', cnum::forHumans(1000000));
        $this->assertSame('1 billion', cnum::forHumans(1000000000));
        $this->assertSame('1 trillion', cnum::forHumans(1000000000000));
        $this->assertSame('1 quadrillion', cnum::forHumans(1000000000000000));
        $this->assertSame('1 thousand quadrillion', cnum::forHumans(1000000000000000000));
        $this->assertSame('123', cnum::forHumans(123));
        $this->assertSame('1 thousand', cnum::forHumans(1234));
        $this->assertSame('1.23 thousand', cnum::forHumans(1234, 2));
        $this->assertSame('12 thousand', cnum::forHumans(12345));
        $this->assertSame('1 million', cnum::forHumans(1234567));
        $this->assertSame('1 billion', cnum::forHumans(1234567890));
        $this->assertSame('1 trillion', cnum::forHumans(1234567890123));
        $this->assertSame('1.23 trillion', cnum::forHumans(1234567890123, 2));
        $this->assertSame('1 quadrillion', cnum::forHumans(1234567890123456));
        $this->assertSame('1.23 thousand quadrillion', cnum::forHumans(1234567890123456789, 2));
        $this->assertSame('490 thousand', cnum::forHumans(489939));
        $this->assertSame('489.939 thousand', cnum::forHumans(489939, 4));
        $this->assertSame('500.00000 million', cnum::forHumans(500000000, 5));
        $this->assertSame('1 million quadrillion', cnum::forHumans(1000000000000000000000));
        $this->assertSame('1 billion quadrillion', cnum::forHumans(1000000000000000000000000));
        $this->assertSame('1 trillion quadrillion', cnum::forHumans(1000000000000000000000000000));
        $this->assertSame('1 quadrillion quadrillion', cnum::forHumans(1000000000000000000000000000000));
        $this->assertSame('1 thousand quadrillion quadrillion', cnum::forHumans(1000000000000000000000000000000000));
        $this->assertSame('0', cnum::forHumans(0));
        $this->assertSame('0', cnum::forHumans(0.0));
        $this->assertSame('0.00', cnum::forHumans(0, 2));
        $this->assertSame('0.00', cnum::forHumans(0.0, 2));
        $this->assertSame('-1', cnum::forHumans(-1));
        $this->assertSame('-1.00', cnum::forHumans(-1, 2));
        $this->assertSame('-10', cnum::forHumans(-10));
        $this->assertSame('-100', cnum::forHumans(-100));
        $this->assertSame('-1 thousand', cnum::forHumans(-1000));
        $this->assertSame('-1.23 thousand', cnum::forHumans(-1234, 2));
        $this->assertSame('-1.2 thousand', cnum::forHumans(-1234, 0, 1));
        $this->assertSame('-1 million', cnum::forHumans(-1000000));
        $this->assertSame('-1 billion', cnum::forHumans(-1000000000));
        $this->assertSame('-1 trillion', cnum::forHumans(-1000000000000));
        $this->assertSame('-1.1 trillion', cnum::forHumans(-1100000000000, 0, 1));
        $this->assertSame('-1 quadrillion', cnum::forHumans(-1000000000000000));
        $this->assertSame('-1 thousand quadrillion', cnum::forHumans(-1000000000000000000));
    }

    public function testSummarize() {
        $this->assertSame('1', cnum::abbreviate(1));
        $this->assertSame('1.00', cnum::abbreviate(1, 2));
        $this->assertSame('10', cnum::abbreviate(10));
        $this->assertSame('100', cnum::abbreviate(100));
        $this->assertSame('1K', cnum::abbreviate(1000));
        $this->assertSame('1.00K', cnum::abbreviate(1000, 2));
        $this->assertSame('1K', cnum::abbreviate(1000, 0, 2));
        $this->assertSame('1K', cnum::abbreviate(1230));
        $this->assertSame('1.2K', cnum::abbreviate(1230, 0, 1));
        $this->assertSame('1M', cnum::abbreviate(1000000));
        $this->assertSame('1B', cnum::abbreviate(1000000000));
        $this->assertSame('1T', cnum::abbreviate(1000000000000));
        $this->assertSame('1Q', cnum::abbreviate(1000000000000000));
        $this->assertSame('1KQ', cnum::abbreviate(1000000000000000000));
        $this->assertSame('123', cnum::abbreviate(123));
        $this->assertSame('1K', cnum::abbreviate(1234));
        $this->assertSame('1.23K', cnum::abbreviate(1234, 2));
        $this->assertSame('12K', cnum::abbreviate(12345));
        $this->assertSame('1M', cnum::abbreviate(1234567));
        $this->assertSame('1B', cnum::abbreviate(1234567890));
        $this->assertSame('1T', cnum::abbreviate(1234567890123));
        $this->assertSame('1.23T', cnum::abbreviate(1234567890123, 2));
        $this->assertSame('1Q', cnum::abbreviate(1234567890123456));
        $this->assertSame('1.23KQ', cnum::abbreviate(1234567890123456789, 2));
        $this->assertSame('490K', cnum::abbreviate(489939));
        $this->assertSame('489.939K', cnum::abbreviate(489939, 4));
        $this->assertSame('500.00000M', cnum::abbreviate(500000000, 5));
        $this->assertSame('1MQ', cnum::abbreviate(1000000000000000000000));
        $this->assertSame('1BQ', cnum::abbreviate(1000000000000000000000000));
        $this->assertSame('1TQ', cnum::abbreviate(1000000000000000000000000000));
        $this->assertSame('1QQ', cnum::abbreviate(1000000000000000000000000000000));
        $this->assertSame('1KQQ', cnum::abbreviate(1000000000000000000000000000000000));
        $this->assertSame('0', cnum::abbreviate(0));
        $this->assertSame('0', cnum::abbreviate(0.0));
        $this->assertSame('0.00', cnum::abbreviate(0, 2));
        $this->assertSame('0.00', cnum::abbreviate(0.0, 2));
        $this->assertSame('-1', cnum::abbreviate(-1));
        $this->assertSame('-1.00', cnum::abbreviate(-1, 2));
        $this->assertSame('-10', cnum::abbreviate(-10));
        $this->assertSame('-100', cnum::abbreviate(-100));
        $this->assertSame('-1K', cnum::abbreviate(-1000));
        $this->assertSame('-1.23K', cnum::abbreviate(-1234, 2));
        $this->assertSame('-1.2K', cnum::abbreviate(-1234, 0, 1));
        $this->assertSame('-1M', cnum::abbreviate(-1000000));
        $this->assertSame('-1B', cnum::abbreviate(-1000000000));
        $this->assertSame('-1T', cnum::abbreviate(-1000000000000));
        $this->assertSame('-1.1T', cnum::abbreviate(-1100000000000, 0, 1));
        $this->assertSame('-1Q', cnum::abbreviate(-1000000000000000));
        $this->assertSame('-1KQ', cnum::abbreviate(-1000000000000000000));
    }

    public function testClamp() {
        $this->assertSame(2, cnum::clamp(1, 2, 3));
        $this->assertSame(3, cnum::clamp(5, 2, 3));
        $this->assertSame(5, cnum::clamp(5, 1, 10));
        $this->assertSame(4.5, cnum::clamp(4.5, 1, 10));
        $this->assertSame(1, cnum::clamp(-10, 1, 5));
    }

    public function testWithLocaleRestoresThePreviousOne() {
        $result = cnum::withLocale('de', function () {
            return cnum::format(1234.5);
        });
        $this->assertSame('1.234,5', $result);
        $this->assertSame('1,234.5', cnum::format(1234.5), 'locale semula dipulihkan');
    }
}
