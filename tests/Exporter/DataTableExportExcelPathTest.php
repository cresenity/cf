<?php
use PHPUnit\Framework\TestCase;

/**
 * DataTable::exportExcel() (lama, deprecated) tidak lagi menulis ke DOCROOT/export (docroot web, tanpa pembersihan):
 * berkas sementara ada di temp/export/<appCode>/excel/ dengan awalan acak dan dihapus saat request selesai.
 */
class DataTableExportExcelPathTest extends TestCase {
    /**
     * @return CElement_Component_DataTable
     */
    protected function table() {
        return new CElement_Component_DataTable('uji-export-excel-' . uniqid());
    }

    /**
     * @param CElement_Component_DataTable $table
     * @param string                       $name
     * @param array                        $args
     *
     * @return mixed
     */
    protected function call($table, $name, array $args = []) {
        $method = new ReflectionMethod($table, $name);
        $method->setAccessible(true);

        return $method->invokeArgs(is_object($table) && $method->isStatic() ? null : $table, $args);
    }

    public function testDownloadPathIsUnderTempExportAppExcelAndNotUnderDocrootExport() {
        $path = $this->call($this->table(), 'excelDownloadPath', ['laporan.xls']);

        $this->assertSame(CTemporary::getDirectory('export', 'excel'), dirname($path) . DS);
        $this->assertStringContainsString(DS . 'temp' . DS . CTemporary::appFolder('export') . DS . 'excel' . DS, $path);
        $this->assertStringStartsNotWith(DOCROOT . 'export' . DS, $path, 'bukan DOCROOT/export lagi');
        $this->assertDirectoryExists(dirname($path));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{16}-laporan\.xls$/', basename($path), 'awalan acak + nama aman');
    }

    public function testEveryCallGetsAUniquePathSoConcurrentExportsDoNotCollide() {
        $table = $this->table();
        $a = $this->call($table, 'excelDownloadPath', ['sama.xls']);
        $b = $this->call($table, 'excelDownloadPath', ['sama.xls']);

        $this->assertNotSame($a, $b);
        $this->assertSame('sama.xls', substr(basename($a), 17));
        $this->assertSame('sama.xls', substr(basename($b), 17));
    }

    public function testDeleteRemovesTheFileAndToleratesAMissingOne() {
        $path = $this->call($this->table(), 'excelDownloadPath', ['hapus.xls']);
        file_put_contents($path, 'isi');
        $this->assertFileExists($path);

        $this->call($this->table(), 'deleteExcelDownload', [$path]);

        $this->assertFileDoesNotExist($path);
        $this->call($this->table(), 'deleteExcelDownload', [$path]);
        $this->call($this->table(), 'deleteExcelDownload', [DOCROOT . 'temp' . DS . 'tidak-ada-' . uniqid()]);
        $this->assertFileDoesNotExist($path, 'memanggil lagi untuk berkas yang tidak ada tidak melempar');
    }

    public function testLegacyExporterPathHelpersAreMarkedDeprecated() {
        foreach (['makePath', 'getDirectory', 'makefolder'] as $method) {
            $doc = (string) (new ReflectionMethod(CExporter::class, $method))->getDocComment();

            $this->assertStringContainsString('@deprecated', $doc, 'CExporter::' . $method . '() menulis ke docroot web');
        }
    }

    public function testExportExcelWorkbookIsBuiltWithDataAndColumnTransformsAndReadsBack() {
        $table = $this->table();
        $table->addColumn('nama')->setLabel('Nama')->addTransform(function ($value) {
            return strtoupper($value);
        });
        $table->addColumn('kota')->setLabel('Kota');
        $table->setDataFromArray([['nama' => 'Budi', 'kota' => 'Jakarta'], ['nama' => 'Sari', 'kota' => 'Bandung']]);
        $requery = new ReflectionMethod($table, 'requery');
        $requery->setAccessible(true);
        $requery->invoke($table);

        $excel = $this->call($table, 'buildExcelExport', ['data']);
        $path = $this->call($table, 'excelDownloadPath', ['hasil.xls']);

        try {
            $excel->save($path);
            $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
            $rows = $sheet->toArray(null, true, false);

            $this->assertSame('data', $sheet->getTitle());
            $this->assertSame(['Nama', 'Kota'], array_slice($rows[0], 0, 2));
            $this->assertSame(['BUDI', 'Jakarta'], array_slice($rows[1], 0, 2), 'transform kolom dijalankan (sebelumnya fatal: properti transforms protected, lalu execute() pada closure)');
            $this->assertSame(['SARI', 'Bandung'], array_slice($rows[2], 0, 2));
        } finally {
            $this->call($table, 'deleteExcelDownload', [$path]);
        }

        $this->assertFileDoesNotExist($path);
    }
}
