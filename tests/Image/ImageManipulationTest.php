<?php
use PHPUnit\Framework\TestCase;

/**
 * CImage_Image + CImage_Manipulations (port spatie/image v1 di atas Glide/Intervention, driver GD)
 * dengan fixture PNG yang dibuat GD di setUp.
 */
class ImageManipulationTest extends TestCase {
    /** @var string */
    protected $tmp;

    /** @var string */
    protected $source;

    protected function setUp(): void {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD tidak tersedia');
        }
        $this->tmp = rtrim(sys_get_temp_dir(), '/') . '/uji-image-' . uniqid() . '/';
        mkdir($this->tmp, 0777, true);
        $this->source = $this->tmp . 'sumber.png';
        // 400×300: kiri merah, kanan biru — supaya crop bisa dibedakan lewat warna piksel.
        $image = imagecreatetruecolor(400, 300);
        imagefilledrectangle($image, 0, 0, 199, 299, imagecolorallocate($image, 255, 0, 0));
        imagefilledrectangle($image, 200, 0, 399, 299, imagecolorallocate($image, 0, 0, 255));
        imagepng($image, $this->source);
        imagedestroy($image);
    }

    protected function tearDown(): void {
        CFile::deleteDirectory($this->tmp);
    }

    /**
     * @param string $path
     *
     * @return array [width, height, mime]
     */
    protected function info($path) {
        $this->assertFileExists($path);
        $size = getimagesize($path);

        return [$size[0], $size[1], $size['mime']];
    }

    /**
     * @param string $path
     * @param int    $x
     * @param int    $y
     *
     * @return array [r, g, b]
     */
    protected function pixel($path, $x, $y) {
        $image = imagecreatefromstring(file_get_contents($path));
        $rgb = imagecolorat($image, $x, $y);
        imagedestroy($image);

        return [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF];
    }

    public function testLoadReportsDimensionsMimeAndExtension() {
        $image = CImage::image($this->source);
        $this->assertInstanceOf(CImage_Image::class, $image);
        $this->assertSame(400, $image->getWidth());
        $this->assertSame(300, $image->getHeight());
        $this->assertSame('image/png', $image->mime());
        $this->assertSame('png', $image->extension());
        $this->assertSame($this->source, $image->path());
    }

    public function testLoadMissingFileThrows() {
        $this->expectException(InvalidArgumentException::class);
        CImage::image($this->tmp . 'tidak-ada.png');
    }

    public function testWidthKeepsAspectRatio() {
        $out = $this->tmp . 'w.png';
        CImage::image($this->source)->useImageDriver('gd')->width(200)->save($out);
        $this->assertSame([200, 150, 'image/png'], $this->info($out));
    }

    public function testHeightKeepsAspectRatio() {
        $out = $this->tmp . 'h.png';
        CImage::image($this->source)->useImageDriver('gd')->height(100)->save($out);
        $this->assertSame([133, 100, 'image/png'], $this->info($out));
    }

    public function testFitMethods() {
        $cases = [
            [CImage_Manipulations::FIT_CONTAIN, 100, 100, [100, 75]],
            [CImage_Manipulations::FIT_MAX, 800, 800, [400, 300]],
            [CImage_Manipulations::FIT_FILL, 100, 100, [100, 100]],
            [CImage_Manipulations::FIT_STRETCH, 100, 50, [100, 50]],
            [CImage_Manipulations::FIT_CROP, 100, 100, [100, 100]],
        ];
        foreach ($cases as $i => list($method, $w, $h, $expected)) {
            $out = $this->tmp . 'fit' . $i . '.png';
            CImage::image($this->source)->useImageDriver('gd')->fit($method, $w, $h)->save($out);
            list($rw, $rh) = $this->info($out);
            $this->assertSame($expected, [$rw, $rh], $method);
        }
    }

    public function testCropPositionsPickTheRightSide() {
        $left = $this->tmp . 'kiri.png';
        CImage::image($this->source)->useImageDriver('gd')->crop(CImage_Manipulations::CROP_LEFT, 100, 100)->save($left);
        $this->assertSame([100, 100, 'image/png'], $this->info($left));
        $this->assertSame([255, 0, 0], $this->pixel($left, 50, 50), 'crop kiri = merah');

        $right = $this->tmp . 'kanan.png';
        CImage::image($this->source)->useImageDriver('gd')->crop(CImage_Manipulations::CROP_RIGHT, 100, 100)->save($right);
        $this->assertSame([0, 0, 255], $this->pixel($right, 50, 50), 'crop kanan = biru');

        $manual = $this->tmp . 'manual.png';
        CImage::image($this->source)->useImageDriver('gd')->manualCrop(50, 40, 210, 10)->save($manual);
        $this->assertSame([50, 40, 'image/png'], $this->info($manual));
        $this->assertSame([0, 0, 255], $this->pixel($manual, 10, 10));

        $focal = $this->tmp . 'focal.png';
        CImage::image($this->source)->useImageDriver('gd')->focalCrop(100, 100, 0, 50)->save($focal);
        $this->assertSame([255, 0, 0], $this->pixel($focal, 10, 50), 'titik fokus 0% x = sisi kiri');
    }

    public function testFormatConversionByExtensionAndExplicit() {
        $jpg = $this->tmp . 'keluar.jpg';
        CImage::image($this->source)->useImageDriver('gd')->save($jpg);
        $this->assertSame('image/jpeg', $this->info($jpg)[2], 'ekstensi tujuan menentukan format');

        $gif = $this->tmp . 'keluar.gif';
        CImage::image($this->source)->useImageDriver('gd')->save($gif);
        $this->assertSame('image/gif', $this->info($gif)[2]);

        $explicit = $this->tmp . 'apa-saja.bin';
        CImage::image($this->source)->useImageDriver('gd')->format(CImage_Manipulations::FORMAT_JPG)->save($explicit);
        $this->assertSame('image/jpeg', $this->info($explicit)[2], 'format() eksplisit menang atas ekstensi');

        if (function_exists('imagewebp')) {
            $webp = $this->tmp . 'keluar.webp';
            CImage::image($this->source)->useImageDriver('gd')->save($webp);
            $this->assertSame('image/webp', $this->info($webp)[2]);
        }
    }

    public function testQualityChangesJpegSize() {
        $high = $this->tmp . 'q90.jpg';
        $low = $this->tmp . 'q10.jpg';
        // Gradien supaya kualitas berpengaruh nyata pada ukuran berkas.
        $gradient = $this->tmp . 'gradien.png';
        $image = imagecreatetruecolor(300, 300);
        for ($x = 0; $x < 300; $x++) {
            imageline($image, $x, 0, $x, 299, imagecolorallocate($image, (int) ($x * 255 / 300), 128, 255 - (int) ($x * 255 / 300)));
        }
        imagepng($image, $gradient);
        imagedestroy($image);
        CImage::image($gradient)->useImageDriver('gd')->quality(90)->save($high);
        CImage::image($gradient)->useImageDriver('gd')->quality(10)->save($low);
        $this->assertGreaterThan(filesize($low), filesize($high));
    }

    public function testFiltersAndOrientation() {
        $grey = $this->tmp . 'abu.png';
        CImage::image($this->source)->useImageDriver('gd')->greyscale()->save($grey);
        list($r, $g, $b) = $this->pixel($grey, 50, 50);
        $this->assertSame($r, $g);
        $this->assertSame($g, $b, 'greyscale: r=g=b');

        $rotated = $this->tmp . 'putar.png';
        CImage::image($this->source)->useImageDriver('gd')->orientation(CImage_Manipulations::ORIENTATION_90)->save($rotated);
        $this->assertSame([300, 400, 'image/png'], $this->info($rotated), 'putar 90° menukar dimensi');

        $flipped = $this->tmp . 'balik.png';
        CImage::image($this->source)->useImageDriver('gd')->flip(CImage_Manipulations::FLIP_HORIZONTALLY)->save($flipped);
        $this->assertSame([0, 0, 255], $this->pixel($flipped, 50, 50), 'setelah dibalik horizontal, kiri = biru');
    }

    public function testWatermarkIsDrawn() {
        $mark = $this->tmp . 'mark.png';
        $image = imagecreatetruecolor(40, 40);
        imagefilledrectangle($image, 0, 0, 39, 39, imagecolorallocate($image, 0, 255, 0));
        imagepng($image, $mark);
        imagedestroy($image);

        $out = $this->tmp . 'wm.png';
        CImage::image($this->source)->useImageDriver('gd')
            ->watermark($mark)
            ->watermarkPosition(CImage_Manipulations::POSITION_TOP_LEFT)
            ->watermarkPadding(0)
            ->save($out);
        $this->assertSame([0, 255, 0], $this->pixel($out, 10, 10), 'pojok kiri atas hijau dari watermark');
        $this->assertSame([255, 0, 0], $this->pixel($out, 100, 100), 'di luar watermark tetap merah');
    }

    public function testInvalidManipulationsThrow() {
        $manipulations = new CImage_Manipulations();
        $cases = [
            function () use ($manipulations) {
                $manipulations->width(-1);
            },
            function () use ($manipulations) {
                $manipulations->height(-5);
            },
            function () use ($manipulations) {
                $manipulations->fit('bukan-metode', 10, 10);
            },
            function () use ($manipulations) {
                $manipulations->crop('crop-sembarang', 10, 10);
            },
            function () use ($manipulations) {
                $manipulations->quality(101);
            },
            function () use ($manipulations) {
                $manipulations->brightness(150);
            },
            function () use ($manipulations) {
                $manipulations->format('bmp');
            },
            function () use ($manipulations) {
                $manipulations->orientation(45);
            },
            function () use ($manipulations) {
                $manipulations->watermarkOpacity(200);
            },
        ];
        foreach ($cases as $i => $case) {
            try {
                $case();
                $this->fail('kasus ' . $i . ' harus melempar InvalidManipulationException');
            } catch (CImage_Exception_InvalidManipulationException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    public function testUnknownManipulationOnImageThrows() {
        $this->expectException(BadMethodCallException::class);
        CImage::image($this->source)->tidakAda();
    }

    public function testInvalidDriverThrows() {
        $this->expectException(CImage_Exception_InvalidImageDriverException::class);
        CImage::image($this->source)->useImageDriver('vips');
    }

    public function testManipulationsAreRecordedAsASequence() {
        $manipulations = (new CImage_Manipulations())->width(100)->height(50)->greyscale()->quality(80);
        $this->assertTrue($manipulations->hasManipulation('width'));
        $this->assertFalse($manipulations->hasManipulation('blur'));
        $this->assertSame(100, (int) $manipulations->getManipulationArgument('width'));
        $this->assertSame([['width' => 100, 'height' => 50, 'filter' => 'greyscale', 'quality' => 80]], $manipulations->toArray(), 'greyscale disimpan sebagai filter');
        $manipulations->removeManipulation('height');
        $this->assertFalse($manipulations->hasManipulation('height'));
        $this->assertFalse($manipulations->isEmpty());
        $this->assertTrue((new CImage_Manipulations())->isEmpty());
    }

    public function testApplyStartsANewGroupAndMergeCombines() {
        $first = (new CImage_Manipulations())->width(100)->apply()->height(50);
        $this->assertCount(2, $first->toArray(), 'apply() memisahkan grup konversi');
        $second = (new CImage_Manipulations())->blur(5);
        $first->mergeManipulations($second);
        $this->assertSame([['width' => 100], ['height' => 50, 'blur' => 5]], $first->toArray());
        $this->assertSame(5, $first->getFirstManipulationArgument('blur'));
        $this->assertNull($first->getFirstManipulationArgument('sharpen'));
    }

    public function testManipulateAcceptsClosureOrManipulations() {
        $out = $this->tmp . 'closure.png';
        CImage::image($this->source)->useImageDriver('gd')->manipulate(function (CImage_Manipulations $m) {
            $m->width(40);
        })->save($out);
        $this->assertSame([40, 30, 'image/png'], $this->info($out));

        $out2 = $this->tmp . 'obj.png';
        CImage::image($this->source)->useImageDriver('gd')->manipulate((new CImage_Manipulations())->height(30))->save($out2);
        $this->assertSame([40, 30, 'image/png'], $this->info($out2));
    }

    public function testMultipleGroupsAreAppliedInOrder() {
        $out = $this->tmp . 'grup.png';
        CImage::image($this->source)->useImageDriver('gd')->width(200)->apply()->crop(CImage_Manipulations::CROP_RIGHT, 50, 50)->save($out);
        $this->assertSame([50, 50, 'image/png'], $this->info($out));
        $this->assertSame([0, 0, 255], $this->pixel($out, 25, 25));
    }

    public function testSaveWithoutPathOverwritesTheSource() {
        CImage::image($this->source)->useImageDriver('gd')->width(80)->save();
        $this->assertSame([80, 60, 'image/png'], $this->info($this->source));
    }

    public function testConstructorArrayAndDevicePixelRatio() {
        $manipulations = new CImage_Manipulations([['width' => 10, 'height' => 20], ['blur' => 1]]);
        $this->assertSame([['width' => 10, 'height' => 20], ['blur' => 1]], $manipulations->toArray());
        $manipulations = new CImage_Manipulations([['width' => 10]]);
        $this->assertSame([['width' => 10]], $manipulations->toArray(), 'konstruktor selalu menerima array grup (satu grup = satu array di dalam array)');
        $out = $this->tmp . 'dpr.png';
        CImage::image($this->source)->useImageDriver('gd')->width(100)->devicePixelRatio(2)->save($out);
        $this->assertSame([200, 150, 'image/png'], $this->info($out));
    }
}
