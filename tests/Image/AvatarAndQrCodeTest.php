<?php
use PHPUnit\Framework\TestCase;

/**
 * CImage::avatar() (inisial → PNG/base64/SVG) dan CImage_QRCode (render GD).
 */
class AvatarAndQrCodeTest extends TestCase {
    protected function setUp(): void {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD tidak tersedia');
        }
    }

    public function testAvatarFacadeReturnsTheInitialsApi() {
        $this->assertInstanceOf(CImage_Avatar::class, CImage::avatar());
        $this->assertInstanceOf(CImage_Avatar_Api_Initials::class, CImage::avatar()->api());
        $this->assertInstanceOf(CImage_Avatar_Api_Initials::class, CImage::avatar()->createInitials());
    }

    public function testInitialsAreDerivedFromTheName() {
        $engine = new CImage_Avatar_Engine_Initials();
        $this->assertSame('HS', $engine->name('Hery Setiawan')->getInitials());
        $this->assertSame('H', $engine->name('Hery')->length(1)->getInitials());
        $this->assertSame('hs', $engine->name('hery setiawan')->length(2)->keepCase()->getInitials(), 'keepCase mempertahankan huruf kecil');
        $this->assertSame('AC', $engine->name('Ali Bin Cahya')->length(2)->keepCase(false)->getInitials(), 'lebih dari 2 kata: kata pertama + kata terakhir');
        $this->assertSame('ABC', $engine->name('Ali Bin Cahya')->length(3)->getInitials());
    }

    public function testAvatarRendersAPngOfTheRequestedSize() {
        $api = CImage::avatar()->api()->setName('Hery Setiawan')->setSize(64)->setBackground('#336699');
        $image = $api->getImageObject();
        $this->assertSame(64, $image->width());
        $this->assertSame(64, $image->height());
        $png = (string) $api->render();
        $this->assertStringStartsWith("\x89PNG", $png);
        $size = getimagesizefromstring($png);
        $this->assertSame([64, 64], [$size[0], $size[1]]);
        $this->assertSame('image/png', $size['mime']);
    }

    public function testAvatarBase64AndSvgOutputs() {
        $api = CImage::avatar()->api()->setName('Budi Santoso')->setSize(48)->setRounded();
        $dataUrl = $api->toBase64();
        $this->assertStringStartsWith('data:image/png;base64,', $dataUrl);
        $decoded = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true);
        $this->assertNotFalse($decoded);
        $this->assertSame(48, getimagesizefromstring($decoded)[0]);

        $svg = $api->toSvg();
        $this->assertStringStartsWith('<svg', trim($svg));
        $this->assertStringContainsString('BS', $svg, 'inisial ditulis di SVG');
        $this->assertStringContainsString('<circle', $svg, 'rounded → lingkaran');
        $square = CImage::avatar()->api()->setName('Budi Santoso')->setSize(48)->setRounded(false)->toSvg();
        $this->assertStringContainsString('<rect', $square);
    }

    public function testBackgroundColorIsDeterministicPerName() {
        $first = (new CImage_Avatar_Engine_Initials())->name('Hery Setiawan')->generate()->pickColor(2, 2, 'hex');
        $second = (new CImage_Avatar_Engine_Initials())->name('Hery Setiawan')->generate()->pickColor(2, 2, 'hex');
        $this->assertSame($first, $second, 'nama yang sama selalu memberi warna latar yang sama');
    }

    public function testQrCodeRendersAGdImage() {
        $qr = new CImage_QRCode('https://cresenity.com', []);
        $image = $qr->renderImage();
        $this->assertTrue(is_resource($image) || $image instanceof GdImage);
        $this->assertGreaterThan(20, imagesx($image));
        $this->assertSame(imagesx($image), imagesy($image), 'QR persegi');
        // Ada modul hitam dan latar putih.
        $colors = [];
        for ($x = 0; $x < imagesx($image); $x += 3) {
            for ($y = 0; $y < imagesy($image); $y += 3) {
                $colors[imagecolorat($image, $x, $y) & 0xFFFFFF] = true;
            }
        }
        $this->assertArrayHasKey(0x000000, $colors);
        $this->assertArrayHasKey(0xFFFFFF, $colors);
        imagedestroy($image);
    }

    public function testQrCodeHonoursColorOptions() {
        $qr = new CImage_QRCode('CF', ['fc' => 'FF0000', 'bc' => '00FF00']);
        $image = $qr->renderImage();
        $colors = [];
        for ($x = 0; $x < imagesx($image); $x += 2) {
            for ($y = 0; $y < imagesy($image); $y += 2) {
                $colors[imagecolorat($image, $x, $y) & 0xFFFFFF] = true;
            }
        }
        $this->assertArrayHasKey(0xFF0000, $colors, 'fc = warna modul');
        $this->assertArrayHasKey(0x00FF00, $colors, 'bc = warna latar');
        imagedestroy($image);
    }
}
