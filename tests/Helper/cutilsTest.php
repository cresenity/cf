<?php
use PHPUnit\Framework\TestCase;

/**
 * Kontrak helper cutils yang dipakai app: penomoran/terbilang rupiah, tanggal, daftar
 * bulan/tahun, sanitasi msisdn/nama berkas, romawi, selisih tanggal.
 */
class cutilsTest extends TestCase {
    protected function tearDown(): void {
        CCarbon::setTestNow(null);
    }

    public function testIndentAndBr() {
        $this->assertSame("\t\t\t", cutils::indent(3));
        $this->assertSame('    ', cutils::indent(2, '  '));
        $this->assertSame('', cutils::indent(0));
        $this->assertSame("\r\n", cutils::br());
    }

    public function testGetUnder1000Terbilang() {
        $this->assertSame('', cutils::get_under_1000(0));
        $this->assertSame('Satu', cutils::get_under_1000(1));
        $this->assertSame('Sepuluh', cutils::get_under_1000(10));
        $this->assertSame('Sebelas', cutils::get_under_1000(11));
        $this->assertSame('Dua Belas', cutils::get_under_1000(12));
        $this->assertSame('Dua Puluh', cutils::get_under_1000(20));
        $this->assertSame('Dua Puluh Satu', cutils::get_under_1000(21));
        $this->assertSame('Seratus', cutils::get_under_1000(100));
        $this->assertSame('Seratus Satu', cutils::get_under_1000(101));
        $this->assertSame('Dua Ratus Sebelas', cutils::get_under_1000(211));
        $this->assertSame('Sembilan Ratus Sembilan Puluh Sembilan', cutils::get_under_1000(999));
        $this->assertSame('', cutils::get_under_1000(1000), '>= 1000 = kosong, bukan exception');
    }

    public function testIndonesianCurrencyString() {
        $this->assertSame('Seribu', cutils::indonesian_currency_string(1000));
        $this->assertSame('Dua Ribu Lima Ratus', cutils::indonesian_currency_string(2500));
        $this->assertSame('Satu Juta', cutils::indonesian_currency_string(1000000));
        $this->assertSame('Satu Juta Dua Ratus Tiga Puluh Empat Ribu Lima Ratus Enam Puluh Tujuh', cutils::indonesian_currency_string(1234567));
        $this->assertSame('Satu Miliar', cutils::indonesian_currency_string(1000000000), 'kelipatan miliar/triliun bulat dulu menyisipkan " Juta" kosong');
        $this->assertSame('Dua Triliun', cutils::indonesian_currency_string(2000000000000));
        $this->assertSame('Satu Miliar Lima Ratus Juta', cutils::indonesian_currency_string(1500000000));
        $this->assertSame('Satu Triliun Dua Miliar Tiga Juta Empat Ribu Lima', cutils::indonesian_currency_string(1002003004005));
        $this->assertSame('Seratus Ribu', cutils::indonesian_currency_string(100000));
        $this->assertSame('', cutils::indonesian_currency_string(0));
    }

    public function testFormatFilesize() {
        $this->assertSame('1 kb', cutils::format_filesize(1024));
        $this->assertSame('1.5 mb', cutils::format_filesize(1.5 * 1024 * 1024));
        $this->assertSame('500 b', cutils::format_filesize(500));
        $this->assertSame('1 gb', cutils::format_filesize(1024 ** 3));
    }

    public function testDateParts() {
        $this->assertSame('09', cutils::get_month('2026-09-18'));
        $this->assertSame('2026', cutils::get_year('2026-09-18'));
        $this->assertSame('18', cutils::get_day('2026-09-18'));
        $this->assertSame(date('m'), cutils::get_month());
        $this->assertSame(date('Y'), cutils::get_year());
        $this->assertSame('Fri', cutils::get_short_day_name(18, 9, 2026));
        $this->assertSame('29', cutils::day_count(2, 2024), 'Februari tahun kabisat');
        $this->assertSame('28', cutils::day_count(2, 2026));
        $this->assertSame('31', cutils::day_count(12, 2026));
    }

    public function testMonthListAndName() {
        $list = cutils::month_list(false);
        $this->assertCount(12, $list);
        $this->assertSame('January', $list[1]);
        $this->assertSame('December', $list[12]);
        $this->assertSame('March', cutils::month_name(3, false));
        $this->assertSame('Unknown', cutils::month_name(13, false));
        $this->assertSame('Unknown', cutils::month_name(0, false));
    }

    public function testMonthRomawi() {
        $this->assertSame('I', cutils::month_romawi(1));
        $this->assertSame('IV', cutils::month_romawi('4'));
        $this->assertSame('IX', cutils::month_romawi(9));
        $this->assertSame('XII', cutils::month_romawi(12));
        $this->assertSame('', cutils::month_romawi(13));
        $this->assertSame('', cutils::month_romawi(0));
    }

    public function testDayAndYearList() {
        $days = cutils::day_list();
        $this->assertCount(31, $days);
        $this->assertSame(1, $days['1']);
        $this->assertSame(31, $days['31']);
        $this->assertSame([2020 => 2020, 2021 => 2021, 2022 => 2022], cutils::year_list(2020, 2022));
        $this->assertSame([], cutils::year_list(2022, 2020), 'awal > akhir = kosong');
        $years = cutils::year_list();
        $this->assertSame(1900, array_key_first($years));
        $this->assertSame((int) date('Y'), array_key_last($years));
    }

    public function testDayNameListStartsOnSunday() {
        $names = cutils::day_name_list();
        $this->assertCount(7, $names);
        $this->assertSame('Sunday', $names[0]);
        $this->assertSame('Saturday', $names[6]);
    }

    public function testSanitizeMsisdn() {
        $this->assertSame('628123456789', cutils::sanitize_msisdn('08123456789'));
        $this->assertSame('628123456789', cutils::sanitize_msisdn('+628123456789'));
        $this->assertSame('628123456789', cutils::sanitize_msisdn('628123456789'));
        $this->assertSame('608123456789', cutils::sanitize_msisdn('08123456789', '60'), 'prefiks negara lain');
        $this->assertSame('', cutils::sanitize_msisdn(''));
        $this->assertSame('8123', cutils::sanitize_msisdn('+8123'), 'tanpa 0 di depan tidak diberi prefiks');
    }

    public function testSanitize() {
        $this->assertSame('hello-world-', cutils::sanitize('Hello World!'), 'tanda baca jadi tanda hubung, termasuk di ujung (tidak di-trim)');
        $this->assertSame('hello-world', cutils::sanitize('Hello World'));
        $this->assertSame('a-b-c', cutils::sanitize('a   b---c'));
        $this->assertSame('laporan-2026.pdf', cutils::sanitize('Laporan 2026.pdf', true), 'mode nama berkas mempertahankan titik');
        $this->assertSame('laporan-2026-pdf', cutils::sanitize('Laporan 2026.pdf'));
        $this->assertSame('café', cutils::sanitize('Café'), 'huruf unicode (\\w dengan /u) dipertahankan');
    }

    public function testTrimCsv() {
        $this->assertSame('a,b', cutils::trim_csv("a,\r\nb"));
        $this->assertSame('ab', cutils::trim_csv("a\rb\n"));
        $this->assertSame('', cutils::trim_csv(null));
    }

    public function testDateDiffs() {
        $this->assertEqualsWithDelta(10.0, cutils::day_diff('2026-01-01', '2026-01-11'), 0.001);
        $this->assertEqualsWithDelta(2.0, cutils::month_diff('2026-01-01', '2026-03-02'), 0.001, 'bulan = 30 hari (aproksimasi legacy)');
        $this->assertEqualsWithDelta(1.0, cutils::year_diff('2025-01-01', '2025-12-27'), 0.001, 'tahun = 360 hari (aproksimasi legacy)');
        $this->assertEqualsWithDelta(-5.0, cutils::day_diff('2026-01-11', '2026-01-06'), 0.001, 'urutan terbalik = negatif');
        $this->assertEqualsWithDelta(1.0, cutils::day_diff(strtotime('2026-01-01'), strtotime('2026-01-02')), 0.001, 'timestamp juga diterima');
        $this->assertGreaterThan(0, cutils::day_diff('2020-01-01'), 'tanpa akhir = sampai sekarang');
    }

    public function testRandmd5IsHexOf32() {
        $a = cutils::randmd5();
        $b = cutils::randmd5();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $a);
        $this->assertNotSame($a, $b);
    }

    public function testThousandSeparatorDelegatesToCtransform() {
        $this->assertSame(ctransform::thousand_separator(1234567), cutils::thousand_separator(1234567));
    }

    public function testBeginAndLastDateOfMonth() {
        $this->assertSame(date('Y') . '-' . date('m') . '-1', cutils::begin_date_month());
        $this->assertSame(date('Y-m-t'), cutils::last_date_month());
    }
}
