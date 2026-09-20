<?php
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;

/**
 * CEmail_Address: satu normalizer untuk semua bentuk alamat yang beredar di app.
 */
class EmailAddressTest extends TestCase {
    public function testEveryLegacyShapeNormalizesToEmailAndName() {
        $normalized = CEmail_Address::normalize([
            'a@x.test',
            'Budi <b@x.test>',
            '"Cici, S.Kom" <c@x.test>',
            ['email' => 'd@x.test', 'name' => 'Dedi'],
            ['toEmail' => 'e@x.test', 'toName' => 'Eka'],
            ['address' => 'f@x.test', 'name' => 'Fani'],
            (object) ['email' => 'g@x.test', 'name' => 'Gani'],
            new Address('h@x.test', 'Hadi'),
            ['email' => 'i@x.test'],
            ['j@x.test', 'k@x.test'],
            '',
            null,
            ['email' => ''],
        ]);

        $this->assertSame([
            ['email' => 'a@x.test', 'name' => null],
            ['email' => 'b@x.test', 'name' => 'Budi'],
            ['email' => 'c@x.test', 'name' => 'Cici, S.Kom'],
            ['email' => 'd@x.test', 'name' => 'Dedi'],
            ['email' => 'e@x.test', 'name' => 'Eka'],
            ['email' => 'f@x.test', 'name' => 'Fani'],
            ['email' => 'g@x.test', 'name' => 'Gani'],
            ['email' => 'h@x.test', 'name' => 'Hadi'],
            ['email' => 'i@x.test', 'name' => null],
            ['email' => 'j@x.test', 'name' => null],
            ['email' => 'k@x.test', 'name' => null],
        ], $normalized, 'alamat kosong dibuang, array bersarang diratakan');
    }

    public function testCommaAndSemicolonSeparatedStrings() {
        $this->assertSame([
            ['email' => 'a@x.test', 'name' => null],
            ['email' => 'b@x.test', 'name' => 'Budi'],
            ['email' => 'c@x.test', 'name' => 'Cici, S.Kom'],
        ], CEmail_Address::normalize('a@x.test, Budi <b@x.test>; "Cici, S.Kom" <c@x.test>'), 'koma di dalam nama berkutip tidak memecah');
        $this->assertSame([['email' => 'a@x.test', 'name' => 'Nama']], CEmail_Address::normalize(['a@x.test' => 'Nama']), 'bentuk email => nama');
        $this->assertSame([], CEmail_Address::normalize(null));
        $this->assertSame([], CEmail_Address::normalize([]));
        $this->assertSame([], CEmail_Address::normalize(false));
    }

    public function testEmailsAndSymfonyConversions() {
        $value = ['Budi <b@x.test>', ['toEmail' => 'e@x.test', 'toName' => 'Eka'], 'z@x.test'];

        $this->assertSame(['b@x.test', 'e@x.test', 'z@x.test'], CEmail_Address::emails($value));
        $symfony = CEmail_Address::toSymfony($value);
        $this->assertCount(3, $symfony);
        $this->assertInstanceOf(Address::class, $symfony[0]);
        $this->assertSame('b@x.test', $symfony[0]->getAddress());
        $this->assertSame('Budi', $symfony[0]->getName());
        $this->assertSame('', $symfony[2]->getName(), 'tanpa nama → string kosong seperti kontrak Symfony');
    }

    public function testInvalidEmailIsRejectedBySymfonyNotSilentlySent() {
        $this->expectException(Symfony\Component\Mime\Exception\RfcComplianceException::class);
        CEmail_Address::toSymfony('bukan email');
    }
}
