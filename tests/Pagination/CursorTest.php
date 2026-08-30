<?php

use PHPUnit\Framework\TestCase;

/**
 * `CPagination_Cursor` membungkus penanda posisi paginasi berbasis kursor.
 *
 * Nilainya sampai lewat query string, jadi `fromEncoded()` adalah pintu masuk
 * yang menerima langsung apa pun dari luar - dan itu yang paling perlu dijaga
 * di sini.
 */
class CursorTest extends TestCase {
    /**
     * @param mixed $value
     *
     * @return string
     */
    private function encodeRaw($value) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($value)));
    }

    /**
     * @return void
     */
    public function testParameterReturnsTheStoredValue() {
        $cursor = new CPagination_Cursor(['id' => 7, 'name' => 'budi']);

        $this->assertSame(7, $cursor->parameter('id'));
        $this->assertSame('budi', $cursor->parameter('name'));
    }

    /**
     * @return void
     */
    public function testParameterThrowsForAnUnknownName() {
        $cursor = new CPagination_Cursor(['id' => 7]);

        $this->expectException(UnexpectedValueException::class);
        $cursor->parameter('tidak_ada');
    }

    /**
     * @return void
     */
    public function testParametersReturnsSeveralAtOnce() {
        $cursor = new CPagination_Cursor(['id' => 7, 'name' => 'budi', 'extra' => 1]);

        $this->assertSame([7, 'budi'], $cursor->parameters(['id', 'name']));
    }

    /**
     * @return void
     */
    public function testDirectionDefaultsToNextItems() {
        $cursor = new CPagination_Cursor(['id' => 1]);

        $this->assertTrue($cursor->pointsToNextItems());
        $this->assertFalse($cursor->pointsToPreviousItems());
    }

    /**
     * @return void
     */
    public function testDirectionCanPointBackwards() {
        $cursor = new CPagination_Cursor(['id' => 1], false);

        $this->assertFalse($cursor->pointsToNextItems());
        $this->assertTrue($cursor->pointsToPreviousItems());
    }

    /**
     * @return void
     */
    public function testToArrayCarriesTheDirectionAlongsideParameters() {
        $cursor = new CPagination_Cursor(['id' => 7], false);

        $this->assertSame(['id' => 7, '_pointsToNextItems' => false], $cursor->toArray());
    }

    /**
     * Hasil encode dipakai di dalam URL, jadi tiga karakter base64 yang tidak
     * aman untuk URL harus sudah tergantikan.
     *
     * @return void
     */
    public function testEncodeIsUrlSafe() {
        $encoded = (new CPagination_Cursor(['id' => str_repeat('a', 40)]))->encode();

        $this->assertStringNotContainsString('+', $encoded);
        $this->assertStringNotContainsString('/', $encoded);
        $this->assertStringNotContainsString('=', $encoded);
    }

    /**
     * @return void
     */
    public function testEncodeAndDecodeRoundTrip() {
        $cursor = new CPagination_Cursor(['id' => 7, 'name' => 'budi'], false);

        $decoded = CPagination_Cursor::fromEncoded($cursor->encode());

        $this->assertSame($cursor->toArray(), $decoded->toArray());
        $this->assertFalse($decoded->pointsToNextItems());
    }

    /**
     * @return void
     */
    public function testFromEncodedRejectsNullAndNonStrings() {
        $this->assertNull(CPagination_Cursor::fromEncoded(null));
        $this->assertNull(CPagination_Cursor::fromEncoded([]));
        $this->assertNull(CPagination_Cursor::fromEncoded(123));
    }

    /**
     * @return void
     */
    public function testFromEncodedRejectsMalformedInput() {
        $this->assertNull(CPagination_Cursor::fromEncoded('inibukanbase64!!!'));
    }

    /**
     * JSON yang sah tetapi bukan objek (skalar, atau null) mengembalikan
     * `null`, selaras dengan perbaikan upstream Laravel
     * (laravel/framework#59699, sudah dirilis di 13.x).
     *
     * @return void
     */
    public function testScalarJsonMakesFromEncodedReturnNull() {
        foreach ([123, 'halo', null] as $value) {
            $encoded = $this->encodeRaw($value);

            $this->assertNull(
                CPagination_Cursor::fromEncoded($encoded),
                'JSON ' . var_export($value, true) . ' seharusnya menghasilkan null'
            );
        }
    }

    /**
     * Objek JSON yang sah tetapi tanpa penanda arah `_pointsToNextItems`
     * juga mengembalikan `null`, bukan Cursor dengan arah `null`.
     *
     * @return void
     */
    public function testObjectWithoutDirectionKeyReturnsNull() {
        $this->assertNull(CPagination_Cursor::fromEncoded($this->encodeRaw(['id' => 5])));
    }
}
