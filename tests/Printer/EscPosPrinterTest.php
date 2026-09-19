<?php
use PHPUnit\Framework\TestCase;

/**
 * CPrinter_EscPos: byte ESC/POS yang dihasilkan Printer/Builder lewat DummyPrintConnector,
 * validasi argumen, profil kemampuan, dan HtmlRenderer untuk pratinjau struk.
 */
class EscPosPrinterTest extends TestCase {
    /** @var CPrinter_EscPos_PrintConnector_DummyPrintConnector */
    protected $connector;

    /** @var CPrinter_EscPos_Printer */
    protected $printer;

    protected function setUp(): void {
        $this->connector = new CPrinter_EscPos_PrintConnector_DummyPrintConnector();
        $this->printer = new CPrinter_EscPos_Printer($this->connector);
    }

    /**
     * Data setelah ESC @ dari konstruktor.
     *
     * @return string
     */
    protected function output() {
        $data = $this->connector->getData();
        $this->assertStringStartsWith(CPrinter_EscPos::ESC . '@', $data, 'konstruktor selalu menginisialisasi printer');

        return substr($data, 2);
    }

    public function testConstructorInitializesAndLoadsDefaultProfile() {
        $this->assertSame(CPrinter_EscPos::ESC . '@', $this->connector->getData());
        $this->assertSame('default', $this->printer->getPrinterCapabilityProfile()->getId());
        $this->assertSame($this->connector, $this->printer->getPrintConnector());
    }

    public function testTextIsWrittenThroughTheBuffer() {
        $this->printer->text("Halo dunia\n");
        $this->assertSame("Halo dunia\n", $this->output());
    }

    public function testTextSwitchesCodePageForNonAsciiAndRejectsBadUtf8() {
        $this->printer->text("caf\xc3\xa9");
        $out = $this->output();
        $this->assertStringStartsWith('caf', $out);
        $this->assertMatchesRegularExpression('/^caf\x1bt.{2}$/s', $out, 'ESC t n: pindah tabel karakter, lalu é jadi satu byte di tabel itu');
        $this->expectException(Exception::class);
        $this->printer->text("\xff\xfe");
    }

    public function testFeedUsesLfForOneLineAndEscDForMore() {
        $this->printer->feed();
        $this->assertSame(CPrinter_EscPos::LF, $this->output());
        $this->connector->clear();
        $this->printer->feed(3);
        $this->assertSame(CPrinter_EscPos::ESC . 'd' . chr(3), $this->connector->getData());
    }

    public function testFeedRejectsOutOfRange() {
        $this->expectException(InvalidArgumentException::class);
        $this->printer->feed(256);
    }

    public function testCutFullAndPartial() {
        $this->printer->cut();
        $this->assertSame(CPrinter_EscPos::GS . 'V' . chr(CPrinter_EscPos::CUT_FULL) . chr(3), $this->output());
        $this->connector->clear();
        $this->printer->cut(CPrinter_EscPos::CUT_PARTIAL, 5);
        $this->assertSame(CPrinter_EscPos::GS . 'V' . chr(CPrinter_EscPos::CUT_PARTIAL) . chr(5), $this->connector->getData());
    }

    public function testEmphasisUnderlineJustificationAndTextSize() {
        $this->printer->setEmphasis(true);
        $this->printer->setEmphasis(false);
        $this->assertSame(CPrinter_EscPos::ESC . 'E' . chr(1) . CPrinter_EscPos::ESC . 'E' . chr(0), $this->output());
        $this->connector->clear();

        $this->printer->setUnderline(CPrinter_EscPos::UNDERLINE_DOUBLE);
        $this->assertSame(CPrinter_EscPos::ESC . '-' . chr(2), $this->connector->getData());
        $this->connector->clear();

        $this->printer->setJustification(CPrinter_EscPos::JUSTIFY_CENTER);
        $this->assertSame(CPrinter_EscPos::ESC . 'a' . chr(CPrinter_EscPos::JUSTIFY_CENTER), $this->connector->getData());
        $this->connector->clear();

        $this->printer->setTextSize(2, 3);
        $this->assertSame(CPrinter_EscPos::GS . '!' . chr((2 << 3) * 1 + 2), $this->connector->getData(), 'lebar 2x → nibble atas 1, tinggi 3x → nibble bawah 2');
    }

    public function testTextSizeRejectsMultiplierAboveEight() {
        $this->expectException(InvalidArgumentException::class);
        $this->printer->setTextSize(9, 1);
    }

    public function testSelectPrintModeCombinesFlags() {
        $this->printer->selectPrintMode(CPrinter_EscPos::MODE_EMPHASIZED | CPrinter_EscPos::MODE_DOUBLE_HEIGHT);
        $this->assertSame(CPrinter_EscPos::ESC . '!' . chr(CPrinter_EscPos::MODE_EMPHASIZED | CPrinter_EscPos::MODE_DOUBLE_HEIGHT), $this->output());
    }

    public function testBarcodeFunctionBOnDefaultProfile() {
        $this->assertTrue($this->printer->getPrinterCapabilityProfile()->getSupportsBarcodeB());
        $result = $this->printer->barcode('ABC123', CPrinter_EscPos::BARCODE_CODE39);
        $this->assertSame($this->printer, $result, 'barcode() bisa dirangkai');
        $this->assertSame(CPrinter_EscPos::GS . 'k' . chr(CPrinter_EscPos::BARCODE_CODE39) . chr(6) . 'ABC123', $this->output());
    }

    public function testBarcodeFunctionAOnSimpleProfileIsChainable() {
        $profile = CPrinter_EscPos_CapabilityProfile::load('simple');
        $this->assertFalse($profile->getSupportsBarcodeB());
        $connector = new CPrinter_EscPos_PrintConnector_DummyPrintConnector();
        $printer = new CPrinter_EscPos_Printer($connector, $profile);
        $this->assertSame($printer, $printer->barcode('ABC123', CPrinter_EscPos::BARCODE_CODE39));
        $this->assertSame(CPrinter_EscPos::ESC . '@' . CPrinter_EscPos::GS . 'k' . chr(CPrinter_EscPos::BARCODE_CODE39 - 65) . 'ABC123' . CPrinter_EscPos::NUL, $connector->getData());
    }

    public function testBarcodeValidatesContentPerType() {
        $cases = [
            ['12345678901', CPrinter_EscPos::BARCODE_UPCA, true],
            ['1234567890', CPrinter_EscPos::BARCODE_UPCA, false],
            ['1234567890128', CPrinter_EscPos::BARCODE_JAN13, true],
            ['abc', CPrinter_EscPos::BARCODE_JAN13, false],
            ['1234', CPrinter_EscPos::BARCODE_ITF, true],
            ['123', CPrinter_EscPos::BARCODE_ITF, false],
            ['A123-45B', CPrinter_EscPos::BARCODE_CODABAR, true],
            ['123-45', CPrinter_EscPos::BARCODE_CODABAR, false],
            ['{BHello', CPrinter_EscPos::BARCODE_CODE128, true],
            ['Hello', CPrinter_EscPos::BARCODE_CODE128, false],
            ['abc', CPrinter_EscPos::BARCODE_CODE39, false],
        ];
        foreach ($cases as list($content, $type, $valid)) {
            $connector = new CPrinter_EscPos_PrintConnector_DummyPrintConnector();
            $printer = new CPrinter_EscPos_Printer($connector);
            try {
                $printer->barcode($content, $type);
                $this->assertTrue($valid, $content . ' seharusnya ditolak');
            } catch (InvalidArgumentException $e) {
                $this->assertFalse($valid, $content . ' seharusnya diterima: ' . $e->getMessage());
            }
        }
    }

    public function testQrCodeSendsModelSizeEcAndData() {
        $this->assertSame($this->printer, $this->printer->qrCode('', CPrinter_EscPos::QR_ECLEVEL_L), 'konten kosong tidak mengirim apa pun tapi tetap chainable');
        $this->assertSame('', $this->output());
        $this->connector->clear();

        $this->printer->qrCode('CF', CPrinter_EscPos::QR_ECLEVEL_M, 4, CPrinter_EscPos::QR_MODEL_2);
        $gs = CPrinter_EscPos::GS . '(k';
        $expected = $gs . chr(4) . chr(0) . '1' . chr(65) . chr(48 + 2) . chr(0)
            . $gs . chr(3) . chr(0) . '1' . chr(67) . chr(4)
            . $gs . chr(3) . chr(0) . '1' . chr(69) . chr(48 + 1)
            . $gs . chr(5) . chr(0) . '1' . chr(80) . '0' . 'CF'
            . $gs . chr(3) . chr(0) . '1' . chr(81) . '0';
        $this->assertSame($expected, $this->connector->getData());
    }

    public function testQrCodeRejectsInvalidSize() {
        $this->expectException(InvalidArgumentException::class);
        $this->printer->qrCode('CF', CPrinter_EscPos::QR_ECLEVEL_L, 17);
    }

    public function testCapabilityProfilesLoadAndSuggestNearest() {
        $names = CPrinter_EscPos::instance()->getProfileNames();
        $this->assertContains('default', $names);
        $this->assertContains('simple', $names);
        $this->assertSame('simple', CPrinter_EscPos_CapabilityProfile::load('simple')->getId());
        try {
            CPrinter_EscPos_CapabilityProfile::load('defualt');
            $this->fail('profil tak dikenal harus melempar');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('default', $e->getMessage(), 'saran nama profil terdekat ikut di pesan');
        }
    }

    public function testSelectCharacterTableRespectsProfile() {
        $this->printer->selectCharacterTable(0);
        $this->assertSame(0, $this->printer->getCharacterTable());
        $this->expectException(InvalidArgumentException::class);
        $this->printer->selectCharacterTable(200);
    }

    public function testBuilderPadsTextByWidth() {
        $builder = CPrinter_EscPos::instance()->createBuilder();
        $this->assertInstanceOf(CPrinter_EscPos_Builder::class, $builder);
        $builder->textLeft('abc', 6)->textRight('abc', 6)->textCenter('abc', 6)->textCenter('abc', 7, '.')->textLeft('abcdefgh', 4)->br();
        $out = substr($builder->render(), 2);
        $this->assertSame('abc   ' . '   abc' . ' abc  ' . '..abc..' . 'abcd' . CPrinter_EscPos::LF, $out);
    }

    public function testBuilderTextAlignDispatch() {
        $builder = CPrinter_EscPos_Builder::factory();
        $builder->text('x', 3, 'L')->text('x', 3, 'R')->text('x', 3, 'C');
        $this->assertSame('x  ' . '  x' . ' x ', substr($builder->render(), 2));
    }

    public function testHtmlRendererTurnsEmphasisJustificationAndFeedIntoHtml() {
        $builder = CPrinter_EscPos_Builder::factory();
        $builder->setEmphasis(true)->text('TOKO')->setEmphasis(false)->feed(2)->setJustifyCenter()->text('tengah')->setJustifyLeft()->text('kiri')->setUnderline()->text('u')->feed();
        $html = CPrinter_EscPos::instance()->renderToHtml($builder->render());
        $this->assertStringContainsString('<span style="font-weight:bold">TOKO</span>', $html);
        $this->assertStringContainsString("\n\n", $html, 'ESC d 2 jadi dua baris baru, bukan mati di debugger');
        $this->assertMatchesRegularExpression('/text-align:center;[^"]*">tengah/', $html);
        $this->assertMatchesRegularExpression('/<span style="display:inline-block;text-align:left;">kiri/', $html);
        $this->assertStringNotContainsString(chr(1), $html, 'parameter ESC - tidak bocor ke HTML');
        $this->assertStringContainsString('kiriu', $html);
    }

    public function testHtmlRendererPrintModeAndBarcode() {
        $builder = CPrinter_EscPos_Builder::factory();
        $builder->selectPrintMode(CPrinter_EscPos::MODE_EMPHASIZED | CPrinter_EscPos::MODE_UNDERLINE)->text('judul')->selectPrintMode()->text('biasa')->barcode('ABC123');
        $html = CPrinter_EscPos::instance()->renderToHtml($builder->render());
        $this->assertStringContainsString('font-weight:bold;text-decoration:underline;">judul', $html);
        $this->assertStringContainsString('biasa', $html);
        $this->assertStringContainsString('<div', $html, 'barcode dirender jadi HTML oleh picqer');
        $this->assertStringNotContainsString('ABC123' . CPrinter_EscPos::NUL, $html);
    }
}
