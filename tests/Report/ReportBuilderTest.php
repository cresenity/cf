<?php
use PHPUnit\Framework\TestCase;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * CReport: builder JRXML → PDF (TCPDF) dan Excel (lewat CExporter) dari koleksi data;
 * ekspresi $F{}, $P{}, $V{PAGE_NUMBER}, penjumlahan variabel, printWhenExpression,
 * grup, orientasi/ukuran kertas.
 */
class ReportBuilderTest extends TestCase {
    /** @var string[] */
    protected $files = [];

    protected function tearDown(): void {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    /**
     * @param string $detail   isi band detail (elemen jrxml)
     * @param string $extra    elemen tambahan di dalam jasperReport (variable/parameter/group)
     * @param string $attrs    atribut tambahan pada jasperReport
     * @param string $summary  band summary
     *
     * @return string
     */
    protected function jrxml($detail, $extra = '', $attrs = '', $summary = '') {
        if ($attrs === '') {
            $attrs = 'pageWidth="595" pageHeight="842" columnWidth="555"';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>
<jasperReport xmlns="http://jasperreports.sourceforge.net/jasperreports" name="uji" leftMargin="20" rightMargin="20" topMargin="20" bottomMargin="20" ' . $attrs . '>
    ' . $extra . '
    <title>
        <band height="30">
            <staticText>
                <reportElement x="0" y="0" width="555" height="30"/>
                <textElement textAlignment="Center"><font size="14" isBold="true"/></textElement>
                <text><![CDATA[Laporan Uji]]></text>
            </staticText>
        </band>
    </title>
    <columnHeader>
        <band height="20">
            <textField>
                <reportElement x="0" y="0" width="200" height="20"/>
                <textFieldExpression><![CDATA["Nama"]]></textFieldExpression>
            </textField>
            <textField>
                <reportElement x="200" y="0" width="100" height="20"/>
                <textFieldExpression><![CDATA["Jumlah"]]></textFieldExpression>
            </textField>
        </band>
    </columnHeader>
    <detail>
        <band height="20">
            ' . $detail . '
        </band>
    </detail>
    ' . $summary . '
    <pageFooter>
        <band height="20">
            <textField>
                <reportElement x="455" y="0" width="100" height="20"/>
                <textElement textAlignment="Right"/>
                <textFieldExpression><![CDATA["Halaman " + $V{PAGE_NUMBER}]]></textFieldExpression>
            </textField>
        </band>
    </pageFooter>
</jasperReport>';
    }

    /**
     * @return string
     */
    protected function detailFields() {
        return '<textField>
                <reportElement x="0" y="0" width="200" height="20"/>
                <textFieldExpression><![CDATA[$F{name}]]></textFieldExpression>
            </textField>
            <textField>
                <reportElement x="200" y="0" width="100" height="20"/>
                <textElement textAlignment="Right"/>
                <textFieldExpression><![CDATA[$F{qty}]]></textFieldExpression>
            </textField>';
    }

    /**
     * @return CCollection
     */
    protected function rows() {
        return c::collect([
            ['name' => 'Apel', 'qty' => 3, 'kelompok' => 'Buah'],
            ['name' => 'Beras', 'qty' => 5, 'kelompok' => 'Pokok'],
            ['name' => 'Cabai', 'qty' => 2, 'kelompok' => 'Buah'],
        ]);
    }

    /**
     * @param TCPDF $pdf
     *
     * @return string
     */
    protected function pdfString($pdf) {
        $path = tempnam(sys_get_temp_dir(), 'uji-report') . '.pdf';
        $this->files[] = $path;
        $pdf->Output($path, 'F');

        return file_get_contents($path);
    }

    /**
     * Teks halaman PDF (TCPDF menulis teks tanpa kompresi bila compression dimatikan, jadi
     * kita ekstrak lewat tcpdf sendiri: jumlah halaman + string yang ditulis).
     *
     * @param CReport_Builder $builder
     *
     * @return TCPDF
     */
    protected function pdf(CReport_Builder $builder) {
        $pdf = $builder->getPdf();
        $this->assertInstanceOf(TCPDF::class, $pdf);

        return $pdf;
    }

    /**
     * @param CReport_Builder $builder
     *
     * @return array[]
     */
    protected function excelRows(CReport_Builder $builder) {
        $export = $builder->getExcelExport();
        $this->assertInstanceOf(CReport_Excel_Export::class, $export);
        $contents = CExporter::raw($export, CExporter::XLSX);
        $path = tempnam(sys_get_temp_dir(), 'uji-report') . '.xlsx';
        $this->files[] = $path;
        file_put_contents($path, $contents);

        return IOFactory::load($path)->getActiveSheet()->toArray();
    }

    public function testBuilderParsesJrxmlAndRendersPdfWithOnePagePerSmallDataset() {
        $builder = CReport::builder()->fromXml($this->jrxml($this->detailFields()))->setDataFromCollection($this->rows());
        $this->assertInstanceOf(CReport_Builder::class, $builder);
        $pdf = $this->pdf($builder);
        $this->assertSame(1, $pdf->getNumPages());
        $raw = $this->pdfString($pdf);
        $this->assertStringStartsWith('%PDF-', $raw);
        $this->assertGreaterThan(1000, strlen($raw));
    }

    public function testPdfPagesFollowTheDetailBandHeight() {
        $many = c::collect(array_map(function ($i) {
            return ['name' => 'Baris ' . $i, 'qty' => $i, 'kelompok' => 'x'];
        }, range(1, 120)));
        $pdf = $this->pdf(CReport::builder()->fromXml($this->jrxml($this->detailFields()))->setDataFromCollection($many));
        $this->assertGreaterThan(2, $pdf->getNumPages(), '120 baris × 20pt tidak muat di satu halaman A4');
        $this->assertLessThan(6, $pdf->getNumPages());
    }

    public function testExcelExportMirrorsTheBands() {
        $rows = $this->excelRows(CReport::builder()->fromXml($this->jrxml($this->detailFields()))->setDataFromCollection($this->rows()));
        $flat = array_values(array_filter(array_map(function ($r) {
            return implode('|', array_filter(array_map('strval', $r), 'strlen'));
        }, $rows), 'strlen'));
        $this->assertContains('Laporan Uji', $flat, 'judul');
        $this->assertContains('Nama|Jumlah', $flat, 'header kolom');
        $this->assertContains('Apel|3', $flat);
        $this->assertContains('Beras|5', $flat);
        $this->assertContains('Cabai|2', $flat);
        $this->assertContains('Halaman 1', $flat, 'footer dengan $V{PAGE_NUMBER}');
    }

    /**
     * Divergensi dari JasperReports: $P{} disubstitusi mentah (tanpa tanda kutip untuk string),
     * jadi parameter string dipakai di dalam literal: "$P{prefix} " + $F{name}.
     */
    public function testParametersAreAvailableAsPExpressions() {
        $detail = '<textField>
                <reportElement x="0" y="0" width="300" height="20"/>
                <textFieldExpression><![CDATA["$P{prefix} " + $F{name}]]></textFieldExpression>
            </textField>';
        $extra = '<parameter name="prefix" class="java.lang.String"/>';
        $builder = CReport::builder()->fromXml($this->jrxml($detail, $extra))->setDataFromCollection($this->rows());
        $builder->setParameter('prefix', 'Item:');
        $rows = $this->excelRows($builder);
        $flat = array_map(function ($r) {
            return implode('', array_map('strval', $r));
        }, $rows);
        $this->assertContains('Item: Apel', $flat);
        $this->assertContains('Item: Cabai', $flat);
    }

    public function testSumVariableIsCalculatedIntoTheSummaryBand() {
        $extra = '<variable name="TOTAL_QTY" class="java.lang.Integer" calculation="Sum">
            <variableExpression><![CDATA[$F{qty}]]></variableExpression>
        </variable>';
        $summary = '<summary>
        <band height="20">
            <textField>
                <reportElement x="0" y="0" width="300" height="20"/>
                <textFieldExpression><![CDATA["Total: " + $V{TOTAL_QTY}]]></textFieldExpression>
            </textField>
        </band>
    </summary>';
        $rows = $this->excelRows(CReport::builder()->fromXml($this->jrxml($this->detailFields(), $extra, '', $summary))->setDataFromCollection($this->rows()));
        $flat = array_map(function ($r) {
            return implode('', array_map('strval', $r));
        }, $rows);
        $this->assertContains('Total: 10', $flat, '3 + 5 + 2');
    }

    public function testPrintWhenExpressionHidesRows() {
        $detail = '<textField>
                <reportElement x="0" y="0" width="300" height="20">
                    <printWhenExpression><![CDATA[$F{qty} > 2]]></printWhenExpression>
                </reportElement>
                <textFieldExpression><![CDATA[$F{name}]]></textFieldExpression>
            </textField>';
        $rows = $this->excelRows(CReport::builder()->fromXml($this->jrxml($detail))->setDataFromCollection($this->rows()));
        $flat = array_map(function ($r) {
            return implode('', array_map('strval', $r));
        }, $rows);
        $this->assertContains('Apel', $flat);
        $this->assertContains('Beras', $flat);
        $this->assertNotContains('Cabai', $flat, 'qty 2 tidak lolos printWhenExpression');
    }

    public function testComparisonOperatorsInExpressions() {
        $cases = [
            ['3 > 2', true],
            ['2 > 3', false],
            ['2 < 3', true],
            ['3 <= 3', true],
            ['4 >= 5', false],
            ['"a" == "a"', true],
            ['1 != 1', false],
            ['1 <> 2', true],
        ];
        foreach ($cases as list($expression, $expected)) {
            $this->assertSame($expected, (new CReport_Generator_Expression($expression))->evaluate(), $expression);
        }
        $this->assertSame(7, (new CReport_Generator_Expression('1 + 2 * 3'))->evaluate(), 'prioritas operator');
        $this->assertSame(9, (new CReport_Generator_Expression('(1 + 2) * 3'))->evaluate());
        $this->assertSame('ab', (new CReport_Generator_Expression('"a" + "b"'))->evaluate(), '+ pada string = concat');
    }

    public function testGroupHeaderIsPrintedPerGroupValue() {
        $extra = '<group name="kelompok">
            <groupExpression><![CDATA[$F{kelompok}]]></groupExpression>
            <groupHeader>
                <band height="20">
                    <textField>
                        <reportElement x="0" y="0" width="300" height="20"/>
                        <textFieldExpression><![CDATA["Kelompok " + $F{kelompok}]]></textFieldExpression>
                    </textField>
                </band>
            </groupHeader>
        </group>';
        $sorted = $this->rows()->sortBy('kelompok')->values();
        $rows = $this->excelRows(CReport::builder()->fromXml($this->jrxml($this->detailFields(), $extra))->setDataFromCollection($sorted));
        $flat = array_map(function ($r) {
            return implode('', array_map('strval', $r));
        }, $rows);
        $this->assertSame(2, count(array_keys($flat, 'Kelompok Buah', true)) + count(array_keys($flat, 'Kelompok Pokok', true)), 'satu header per nilai grup');
        $this->assertContains('Kelompok Buah', $flat);
        $this->assertContains('Kelompok Pokok', $flat);
    }

    public function testOrientationAndPaperSizeReachTcpdf() {
        $builder = CReport::builder()->fromXml($this->jrxml($this->detailFields()))->setDataFromCollection($this->rows());
        $portrait = $this->pdf($builder);
        $w = $portrait->getPageWidth();
        $h = $portrait->getPageHeight();
        $this->assertLessThan($h, $w, 'A4 potret dari pageWidth/pageHeight jrxml');

        $landscape = CReport::builder()->fromXml($this->jrxml($this->detailFields(), '', 'orientation="Landscape" pageWidth="842" pageHeight="595" columnWidth="802"'))->setDataFromCollection($this->rows());
        $pdf = $this->pdf($landscape);
        $this->assertGreaterThan($pdf->getPageHeight(), $pdf->getPageWidth(), 'lanskap dari atribut jrxml');
    }

    public function testEmptyDatasetStillRendersTitleAndFooter() {
        $builder = CReport::builder()->fromXml($this->jrxml($this->detailFields()))->setDataFromCollection(c::collect([]));
        $this->assertSame(1, $this->pdf($builder)->getNumPages());
        $flat = array_map(function ($r) {
            return implode('', array_map('strval', $r));
        }, $this->excelRows($builder));
        $this->assertContains('Laporan Uji', $flat);
        $this->assertNotContains('Apel', $flat);
    }
}
