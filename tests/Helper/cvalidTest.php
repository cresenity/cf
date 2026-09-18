<?php
use PHPUnit\Framework\TestCase;

/**
 * cvalid - validator murni (email/url/ip/kartu kredit/telepon/alpha/digit/decimal).
 */
class cvalidTest extends TestCase {
    public function testEmail() {
        $this->assertTrue(cvalid::email('hery@cresenity.com'));
        $this->assertTrue(cvalid::email('a.b+c@sub.example.co.id'));
        $this->assertFalse(cvalid::email('bukan-email'));
        $this->assertFalse(cvalid::email('a@b'));
        $this->assertFalse(cvalid::email(''));
        $this->assertFalse(cvalid::email(null));
    }

    public function testEmailRfc() {
        $this->assertTrue(cvalid::email_rfc('hery@cresenity.com'));
        $this->assertTrue(cvalid::email_rfc('"tanda kutip"@example.com'), 'local-part berkutip sah menurut RFC');
        $this->assertFalse(cvalid::email_rfc('tanpa-at.example.com'));
        $this->assertFalse(cvalid::email_rfc('spasi di@example.com'));
    }

    public function testUrl() {
        $this->assertTrue(cvalid::url('https://dev.cresenity.com/docs/page'));
        $this->assertTrue(cvalid::url('ftp://host/file'));
        $this->assertFalse(cvalid::url('dev.cresenity.com'), 'tanpa skema');
        $this->assertFalse(cvalid::url('http://'));
        $this->assertFalse(cvalid::url(''));
    }

    public function testIp() {
        $this->assertTrue(cvalid::ip('103.158.162.131'));
        $this->assertTrue(cvalid::ip('192.168.1.1'), 'privat diizinkan secara default');
        $this->assertFalse(cvalid::ip('192.168.1.1', false, false), 'privat ditolak bila allow_private=false');
        $this->assertFalse(cvalid::ip('127.0.0.1'), 'rentang reserved selalu ditolak');
        $this->assertFalse(cvalid::ip('256.1.1.1'));
        $this->assertFalse(cvalid::ip('2001:db8::1'), 'IPv6 ditolak tanpa flag');
        $this->assertTrue(cvalid::ip('2606:4700:4700::1111', true));
        $this->assertFalse(cvalid::ip('bukan-ip'));
    }

    public function testCreditCard() {
        $this->assertTrue(cvalid::credit_card('4111111111111111', 'visa'));
        $this->assertTrue(cvalid::credit_card('4111 1111 1111 1111'), 'spasi dibuang; default = pola umum');
        $this->assertFalse(cvalid::credit_card('4111111111111112', 'visa'), 'Luhn gagal');
        $this->assertTrue(cvalid::credit_card('5555555555554444', 'mastercard'));
        $this->assertFalse(cvalid::credit_card('5555555555554444', 'visa'), 'prefiks bukan visa');
        $this->assertTrue(cvalid::credit_card('5555555555554444', ['visa', 'mastercard']), 'daftar tipe = salah satu');
        $this->assertFalse(cvalid::credit_card('5555555555554444', 'kartu-tak-dikenal'));
        $this->assertFalse(cvalid::credit_card('abc'));
        $this->assertFalse(cvalid::credit_card(''));
    }

    public function testPhone() {
        $this->assertTrue(cvalid::phone('0812-3456-789'), '10 digit');
        $this->assertTrue(cvalid::phone('(031) 5551234'), '10 digit dengan tanda baca');
        $this->assertTrue(cvalid::phone('08123456789'), '11 digit');
        $this->assertTrue(cvalid::phone('5551234'), '7 digit');
        $this->assertFalse(cvalid::phone('12345'));
        $this->assertTrue(cvalid::phone('12345', [5]), 'panjang kustom');
    }

    public function testDate() {
        $this->assertTrue(cvalid::date('2026-09-18'));
        $this->assertTrue(cvalid::date('next monday'), 'strtotime relatif ikut sah - divergensi dari validator ketat');
        $this->assertFalse(cvalid::date('bukan tanggal'));
        $this->assertFalse(cvalid::date(''));
    }

    public function testAlphaFamily() {
        $this->assertTrue(cvalid::alpha('abcXYZ'));
        $this->assertFalse(cvalid::alpha('abc1'));
        $this->assertFalse(cvalid::alpha('café'));
        $this->assertTrue(cvalid::alpha('café', true), 'utf8');
        $this->assertTrue(cvalid::alpha_numeric('abc123'));
        $this->assertFalse(cvalid::alpha_numeric('abc-123'));
        $this->assertTrue(cvalid::alpha_dash('abc-123_x'));
        $this->assertFalse(cvalid::alpha_dash('abc 123'));
        $this->assertTrue(cvalid::alpha_dash('café-1', true));
        $this->assertTrue(cvalid::digit('0123'));
        $this->assertFalse(cvalid::digit('12.3'));
        $this->assertFalse(cvalid::digit('-1'));
        $this->assertTrue(cvalid::digit('١٢٣', true), 'angka Arab-Indic sah di mode utf8');
    }

    public function testNumericAndDecimal() {
        $this->assertTrue(cvalid::numeric('123'));
        $this->assertTrue(cvalid::numeric('-12.5'));
        $this->assertFalse(cvalid::numeric('12a'));
        $this->assertFalse(cvalid::numeric('1,000'), 'koma bukan pemisah desimal di locale C');
        $this->assertTrue(cvalid::decimal('12.50'));
        $this->assertFalse(cvalid::decimal('12'));
        $this->assertFalse(cvalid::decimal('.5'));
        $this->assertTrue(cvalid::decimal('12.50', [2]), 'format [desimal]');
        $this->assertFalse(cvalid::decimal('12.5', [2]));
        $this->assertTrue(cvalid::decimal('12.50', [2, 2]), 'format [digit, desimal]');
        $this->assertFalse(cvalid::decimal('123.50', [2, 2]));
    }

    public function testStandardTextAndPassport() {
        $this->assertTrue(cvalid::standard_text('Halo dunia, ini-teks_biasa. Nomor 1!'));
        $this->assertFalse(cvalid::standard_text('tag <b>'));
        $this->assertFalse(cvalid::standard_text(''));
        $this->assertTrue(cvalid::passport('1234567890'));
        $this->assertTrue(cvalid::passport('123456789012'));
        $this->assertFalse(cvalid::passport('12345678901'), '11 digit tidak sah');
        $this->assertFalse(cvalid::passport('A1234567'));
    }

    public function testMysqlDateIsAnInstanceMethod() {
        $valid = new cvalid();
        $this->assertTrue($valid->mysql_date('2026-09-18'));
        $this->assertTrue($valid->mysql_date('2026-9-8'));
        $this->assertFalse($valid->mysql_date('18-09-2026'));
        $this->assertFalse($valid->mysql_date('2026-09-18 10:00:00'));
    }
}
