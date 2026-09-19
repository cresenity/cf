<?php
use PHPUnit\Framework\TestCase;

/**
 * CDocument_Pdf: geometri Rectangle, PageSize baku, RectangleReadOnly, dan Document default.
 */
class PdfRectangleTest extends TestCase {
    public function testPageSizesAreReadOnlyPoints() {
        $a4 = CDocument_Pdf_PageSize::a4();
        $this->assertInstanceOf(CDocument_Pdf_Element_RectangleReadOnly::class, $a4);
        $this->assertSame(595.0, $a4->getWidth());
        $this->assertSame(842.0, $a4->getHeight());
        $letter = CDocument_Pdf_PageSize::letter();
        $this->assertSame(612.0, $letter->getWidth());
        $this->assertSame(792.0, $letter->getHeight());
        $this->assertSame('RectangleReadOnly: 595x842 (rot: 0 degrees)', (string) $a4);
    }

    public function testReadOnlyRectangleRejectsMutation() {
        $a4 = CDocument_Pdf_PageSize::a4();
        $this->expectException(BadMethodCallException::class);
        $a4->setLeft(10);
    }

    public function testRotateSwapsAxesAndAccumulatesRotation() {
        $a4 = CDocument_Pdf_PageSize::a4();
        $landscape = $a4->rotate();
        $this->assertInstanceOf(CDocument_Pdf_Element_Rectangle::class, $landscape);
        $this->assertNotInstanceOf(CDocument_Pdf_Element_RectangleReadOnly::class, $landscape, 'hasil rotate bisa diubah');
        $this->assertSame(842.0, $landscape->getWidth());
        $this->assertSame(595.0, $landscape->getHeight());
        $this->assertSame(90, $landscape->getRotation());
        $this->assertSame('Rectangle: 842x595 (rot: 90 degrees)', (string) $landscape);
        $this->assertSame(0, $landscape->rotate()->rotate()->rotate()->getRotation(), '4 × 90° kembali ke 0');
    }

    public function testSetRotationOnlyAcceptsRightAngles() {
        $rect = new CDocument_Pdf_Element_Rectangle(0, 0, 100, 50);
        $rect->setRotation(90);
        $this->assertSame(90, $rect->getRotation());
        $rect->setRotation(450);
        $this->assertSame(90, $rect->getRotation(), 'dinormalkan modulo 360');
        $rect->setRotation(45);
        $this->assertSame(0, $rect->getRotation(), 'bukan kelipatan 90 → 0');
        $this->assertSame(180, (new CDocument_Pdf_Element_Rectangle(0, 0, 10, 10, 180))->getRotation(), 'rotasi lewat konstruktor');
    }

    public function testNormalizeAndMarginsAndCopyConstructor() {
        $rect = new CDocument_Pdf_Element_Rectangle(100, 50, 0, 0);
        $this->assertSame(-100.0, $rect->getWidth());
        $rect->normalize();
        $this->assertSame(0.0, $rect->getLeft());
        $this->assertSame(100.0, $rect->getRight());
        $this->assertSame(50.0, $rect->getTop());
        $this->assertSame(10.0, $rect->getLeft(10));
        $this->assertSame(90.0, $rect->getRight(-10));

        $rect->setBorder(CDocument_Pdf_Element_Rectangle::BOX);
        $rect->setBorderWidth(2);
        $copy = new CDocument_Pdf_Element_Rectangle($rect);
        $this->assertSame(100.0, $copy->getWidth());
        $this->assertSame(CDocument_Pdf_Element_Rectangle::BOX, $copy->getBorder(), 'parameter non-posisi ikut disalin');
        $this->assertSame(2.0, $copy->getBorderWidth());
    }

    public function testBorderSidesAreBitFlags() {
        $rect = new CDocument_Pdf_Element_Rectangle(0, 0, 10, 10);
        $this->assertSame(CDocument_Pdf_Element_Rectangle::UNDEFINED, $rect->getBorder());
        $this->assertFalse($rect->hasBorders());
        $this->assertFalse($rect->hasBorder(CDocument_Pdf_Element_Rectangle::TOP));

        $rect->enableBorderSide(CDocument_Pdf_Element_Rectangle::TOP);
        $rect->enableBorderSide(CDocument_Pdf_Element_Rectangle::LEFT);
        $this->assertTrue($rect->hasBorder(CDocument_Pdf_Element_Rectangle::TOP));
        $this->assertTrue($rect->hasBorder(CDocument_Pdf_Element_Rectangle::LEFT));
        $this->assertFalse($rect->hasBorder(CDocument_Pdf_Element_Rectangle::RIGHT));
        $this->assertFalse($rect->hasBorders(), 'sisi aktif tapi lebar garis 0 → tidak ada border tergambar');
        $rect->setBorderWidth(1);
        $this->assertTrue($rect->hasBorders());

        $rect->disableBorderSide(CDocument_Pdf_Element_Rectangle::TOP);
        $this->assertFalse($rect->hasBorder(CDocument_Pdf_Element_Rectangle::TOP));
        $this->assertSame(CDocument_Pdf_Element_Rectangle::LEFT, $rect->getBorder());
        $this->assertSame(15, CDocument_Pdf_Element_Rectangle::BOX);
    }

    public function testDocumentDefaultsToA4WithHalfInchMargins() {
        $document = CDocument_Pdf::createDocument();
        $this->assertInstanceOf(CDocument_Pdf_Document::class, $document);
        $reflection = new ReflectionClass($document);
        foreach (['marginLeft', 'marginRight', 'marginTop', 'marginBottom'] as $margin) {
            $property = $reflection->getProperty($margin);
            $property->setAccessible(true);
            $this->assertSame(36.0, $property->getValue($document), $margin);
        }
    }
}
