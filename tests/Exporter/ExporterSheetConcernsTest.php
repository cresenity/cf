<?php
use PHPUnit\Framework\TestCase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Color;

class ExporterSheetConcernsPlainExport implements CExporter_Concern_FromArray {
    public function getArray() {
        return [['a', 'b', 'c'], [1, 2, 3], [4, 5, 6], [7, 8, 9]];
    }
}

class ExporterSheetConcernsFullExport extends ExporterSheetConcernsPlainExport implements CExporter_Concern_WithFreezePane, CExporter_Concern_WithTabColor, CExporter_Concern_WithPrintArea, CExporter_Concern_WithPageBreaks, CExporter_Concern_WithSheetProtection {
    public function freezePane() {
        return 'A2';
    }

    public function tabColor() {
        return 'FF1A347B';
    }

    public function printArea() {
        return 'A1:C4';
    }

    public function pageBreaks() {
        return ['A3', 'A4'];
    }

    public function sheetProtection() {
        return ['password' => 'rahasia', 'sort' => true, 'formatCells' => true];
    }
}

class ExporterSheetConcernsColorObjectExport extends ExporterSheetConcernsPlainExport implements CExporter_Concern_WithTabColor {
    public function tabColor() {
        return new Color('FFFF0000');
    }
}

class ExporterSheetConcernsEmptyProtectionExport extends ExporterSheetConcernsPlainExport implements CExporter_Concern_WithSheetProtection {
    public function sheetProtection() {
        return [];
    }
}

class ExporterSheetConcernsBadProtectionExport extends ExporterSheetConcernsPlainExport implements CExporter_Concern_WithSheetProtection {
    public function sheetProtection() {
        return ['bukanOpsi' => true];
    }
}

class ExporterSheetConcernsWithEventsExport extends ExporterSheetConcernsPlainExport implements CExporter_Concern_WithFreezePane, CExporter_Concern_WithEvents {
    public function freezePane() {
        return 'A2';
    }

    public function registerEvents() {
        return [
            CExporter_Event_AfterSheet::class => function (CExporter_Event_AfterSheet $event) {
                $event->sheet->getDelegate()->setTitle('Dari Event');
            },
        ];
    }
}

/**
 * Concern lembar kerja: WithFreezePane, WithTabColor, WithPrintArea, WithPageBreaks, WithSheetProtection.
 */
class ExporterSheetConcernsTest extends TestCase {
    /**
     * @param mixed  $value
     * @param string $message
     *
     * @return void
     */
    public static function assertNotTrue($value, $message = '') {
        static::assertThat($value !== true, static::isTrue(), $message);
    }

    /**
     * @param object $export
     *
     * @return \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
     */
    protected function exportedSheet($export) {
        $path = tempnam(sys_get_temp_dir(), 'uji-concern') . '.xlsx';
        file_put_contents($path, CExporter::raw($export, CExporter::XLSX));

        try {
            return IOFactory::load($path)->getActiveSheet();
        } finally {
            @unlink($path);
        }
    }

    public function testAllFiveConcernsAreWrittenToTheWorkbook() {
        $sheet = $this->exportedSheet(new ExporterSheetConcernsFullExport());

        $this->assertSame('A2', $sheet->getFreezePane());
        $this->assertSame('FF1A347B', $sheet->getTabColor()->getARGB());
        $this->assertSame('A1:C4', str_replace('$', '', $sheet->getPageSetup()->getPrintArea()));
        $this->assertSame(['A3', 'A4'], array_keys($sheet->getBreaks()));
        $this->assertTrue($sheet->getProtection()->getSheet());
        $this->assertNotSame('', $sheet->getProtection()->getPassword(), 'kata sandi disimpan sebagai hash');
        $this->assertTrue($sheet->getProtection()->getSort());
        $this->assertTrue($sheet->getProtection()->getFormatCells());
        $this->assertNotTrue($sheet->getProtection()->getInsertRows(), 'opsi yang tidak disebut tidak diubah');
    }

    public function testTabColorAcceptsAColorObject() {
        $sheet = $this->exportedSheet(new ExporterSheetConcernsColorObjectExport());

        $this->assertSame('FFFF0000', $sheet->getTabColor()->getARGB());
    }

    public function testEmptyProtectionOptionsStillProtectTheSheetWithoutAPassword() {
        $sheet = $this->exportedSheet(new ExporterSheetConcernsEmptyProtectionExport());

        $this->assertTrue($sheet->getProtection()->getSheet());
        $this->assertSame('', $sheet->getProtection()->getPassword());
    }

    public function testUnknownProtectionOptionIsRejected() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('bukanOpsi');

        CExporter::raw(new ExporterSheetConcernsBadProtectionExport(), CExporter::XLSX);
    }

    public function testExportWithoutTheConcernsIsUntouched() {
        $sheet = $this->exportedSheet(new ExporterSheetConcernsPlainExport());

        $this->assertNull($sheet->getFreezePane());
        $this->assertNotTrue($sheet->getProtection()->getSheet());
        $this->assertSame([], $sheet->getBreaks());
        $this->assertSame([['a', 'b', 'c'], [1, 2, 3], [4, 5, 6], [7, 8, 9]], $sheet->toArray(null, true, false));
    }

    public function testConcernsAndAfterSheetEventWorkTogether() {
        $sheet = $this->exportedSheet(new ExporterSheetConcernsWithEventsExport());

        $this->assertSame('A2', $sheet->getFreezePane());
        $this->assertSame('Dari Event', $sheet->getTitle());
    }
}
