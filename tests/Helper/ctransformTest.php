<?php
use PHPUnit\Framework\TestCase;

/**
 * ctransform - pemformat nilai untuk tampilan (pemisah ribuan, tanggal, huruf); dikunci apa
 * adanya termasuk perilaku lama: thousand_separator membuang desimal (sprintf %d).
 */
class ctransformTest extends TestCase {
    public function testThousandSeparator() {
        $this->assertSame('1,234,567', ctransform::thousand_separator(1234567));
        $this->assertSame('999', ctransform::thousand_separator(999));
        $this->assertSame('1,000', ctransform::thousand_separator(1000));
        $this->assertSame('0', ctransform::thousand_separator(0));
        $this->assertSame('-1,234', ctransform::thousand_separator(-1234));
        $this->assertSame('1,234', ctransform::thousand_separator('1234.56'), 'desimal dibuang (sprintf %d) - perilaku lama yang dipertahankan');
        $this->assertSame('1,234', ctransform::thousand_separator(1234.99, 2), 'argumen decimal tidak menghidupkan desimal kembali');
        $this->assertSame('12', ctransform::thousand_separator('12abc'), 'floatval memotong di huruf pertama');
        $this->assertSame('0', ctransform::thousand_separator('abc'));
    }

    public function testFormatAndUnformatCurrency() {
        $this->assertSame('1,234,567', ctransform::format_currency(1234567));
        $this->assertSame('1234567', ctransform::unformat_currency('1,234,567'));
        $this->assertSame('1234567', ctransform::format_currency('1,234,567', true), 'unformat=true membalik');
        $this->assertSame('12.5', ctransform::unformat_currency('12.5'));
    }

    public function testCaseAndDateHelpers() {
        $this->assertSame('ABC', ctransform::uppercase('abc'));
        $this->assertSame('abc', ctransform::lowercase('ABC'));
        $this->assertSame('2026-09-18', ctransform::short_date_format('2026-09-18 10:11:12'));
        $this->assertSame('2026-09-18', ctransform::short_date_format('2026-09-18'));
        $this->assertSame('2026-09-18', ctransform::unformat_date('18 September 2026'));
        $this->assertSame('2026-09-18 10:11:12', ctransform::unformat_datetime('18 Sep 2026 10:11:12'));
        $this->assertSame(ctransform::unformat_datetime('2026-09-18 01:02:03'), ctransform::unformat_long_date('2026-09-18 01:02:03'));
        $this->assertSame('March', ctransform::month_name(3));
    }

    public function testDateFormattedFollowsConfigOrPassesThrough() {
        $this->assertSame('', ctransform::date_formatted(''));
        $this->assertSame('', ctransform::long_date_formatted(''));
        $format = ccfg::get('date_formatted');
        if (strlen((string) $format) === 0) {
            $this->assertSame('2026-09-18', ctransform::date_formatted('2026-09-18'), 'tanpa config = apa adanya');
        } else {
            $this->assertSame(date($format, strtotime('2026-09-18')), ctransform::date_formatted('2026-09-18'));
        }
        $this->assertSame(ctransform::date_formatted('2026-09-18'), ctransform::format_date('2026-09-18'));
        $this->assertSame(ctransform::long_date_formatted('2026-09-18 08:00:00'), ctransform::format_datetime('2026-09-18 08:00:00'));
        if (strlen((string) ccfg::get('long_date_formatted')) > 0) {
            $this->assertSame('2026-09-18 08:00:00', ctransform::long_date_formatted('18 Sep 2026 08:00', true), 'unformat=true = Y-m-d H:i:s');
        } else {
            $this->assertSame('18 Sep 2026 08:00', ctransform::long_date_formatted('18 Sep 2026 08:00', true), 'tanpa config long_date_formatted nilai kembali apa adanya, unformat diabaikan');
        }
    }

    public function testHtmlSpecialcharsAndLangDelegate() {
        $this->assertSame('&lt;b&gt;&amp;&quot;', ctransform::html_specialchars('<b>&"'));
        $this->assertSame(clang::__('Save'), ctransform::lang('Save'));
    }
}
