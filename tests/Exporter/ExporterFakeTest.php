<?php
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * CExporter::fake(): pencatatan download/store/queue/raw/import, assertion, dan tidak menyentuh disk atau antrean.
 */
class ExporterFakeSampleExport {
    /** @var array */
    public $rows;

    public function __construct(array $rows = [['a', 'b']]) {
        $this->rows = $rows;
    }
}

class ExporterFakeSampleImport {
}

class ExporterFakeQueuedImport implements CQueue_ShouldQueueInterface {
}

class ExporterFakeTest extends TestCase {
    protected function tearDown(): void {
        CExporter::forgetFake();
    }

    /**
     * @param callable $assertion
     *
     * @return null|string
     */
    protected function failureOf($assertion) {
        try {
            $assertion();
        } catch (Throwable $e) {
            return $e->getMessage();
        }

        return null;
    }

    public function testFakeLifecycle() {
        $this->assertFalse(CExporter::hasFake());
        $this->assertNull(CExporter::getFake());

        $fake = CExporter::fake();

        $this->assertInstanceOf(CExporter_Fake::class, $fake);
        $this->assertTrue(CExporter::hasFake());
        $this->assertSame($fake, CExporter::getFake());

        CExporter::forgetFake();

        $this->assertFalse(CExporter::hasFake());
    }

    public function testDownloadIsRecordedAndReturnsAResponseWithoutDeletingAnything() {
        $fake = CExporter::fake();
        $export = new ExporterFakeSampleExport();

        $response = CExporter::download($export, 'laporan.xlsx');

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertFileExists($response->getFile()->getPathname(), 'berkas palsu tidak dihapus setelah dikirim');
        $fake->assertDownloaded('laporan.xlsx');
        $fake->assertDownloaded('laporan.xlsx', function ($recorded) use ($export) {
            return $recorded === $export;
        });
        $fake->assertNotDownloaded('lain.xlsx');
    }

    public function testForceDownloadIsRecordedAsADownload() {
        $fake = CExporter::fake();

        CExporter::forceDownload(new ExporterFakeSampleExport(), 'paksa.csv');

        $fake->assertDownloaded('paksa.csv');
    }

    public function testStoreOnDefaultAndNamedDisk() {
        $fake = CExporter::fake();
        $export = new ExporterFakeSampleExport();

        $this->assertTrue(CExporter::store($export, 'a/laporan.xlsx'));
        $this->assertTrue(CExporter::store($export, 'b/laporan.xlsx', ['diskName' => 's3']));

        $fake->assertStored('a/laporan.xlsx');
        $fake->assertStored('b/laporan.xlsx', 's3');
        $fake->assertStored('a/laporan.xlsx', function ($recorded) use ($export) {
            return $recorded === $export;
        });
        $fake->assertNotStored('a/laporan.xlsx', 's3');
        $fake->assertNotStored('c/lain.xlsx');
    }

    public function testQueueRecordsStoredAndQueuedAndNeverDispatches() {
        $fake = CExporter::fake();
        $export = new ExporterFakeSampleExport();

        $pending = CExporter::queue($export, 'antre.xlsx', 's3');

        $this->assertInstanceOf(CExporter_Fake_PendingDispatch::class, $pending);
        $this->assertSame($pending, $pending->onQueue('exports')->delay(10)->onConnection('redis'), 'method fluent diterima');
        $fake->assertQueued('antre.xlsx', 's3');
        $fake->assertStored('antre.xlsx', 's3');
        $this->assertSame($export, CExporter::store($export, 'x.xlsx', ['queued' => true, 'diskName' => 's3']) ? $export : null);
        $fake->assertQueued('x.xlsx', 's3');
    }

    public function testQueueAjaxIsRecordedAsQueued() {
        $fake = CExporter::fake();

        CExporter::queueAjax('abc123', 'ajax.xlsx');

        $fake->assertQueued('ajax.xlsx');
    }

    public function testQueuedWithChain() {
        $fake = CExporter::fake();
        CExporter::queue(new ExporterFakeSampleExport(), 'rantai.xlsx')->chain([ExporterFakeSampleImport::class, new ExporterFakeQueuedImport()]);

        $fake->assertQueuedWithChain([ExporterFakeSampleImport::class, ExporterFakeQueuedImport::class]);
        $fake->assertQueuedWithChain([ExporterFakeSampleImport::class, ExporterFakeQueuedImport::class], 'rantai.xlsx');
        $this->assertNotNull($this->failureOf(function () use ($fake) {
            $fake->assertQueuedWithChain([ExporterFakeQueuedImport::class]);
        }));
    }

    public function testRawExport() {
        $fake = CExporter::fake();
        $export = new ExporterFakeSampleExport([['x']]);

        $this->assertSame('RAW-CONTENTS', CExporter::raw($export, CExporter::CSV));

        $fake->assertExportedInRaw(ExporterFakeSampleExport::class);
        $fake->assertExportedInRaw(ExporterFakeSampleExport::class, function ($recorded) {
            return $recorded->rows === [['x']];
        });
    }

    public function testImportsAreRecordedWithoutReadingAnyFile() {
        $fake = CExporter::fake();
        $import = new ExporterFakeSampleImport();

        $this->assertSame($fake, CImporter::import($import, 'tidak-ada.xlsx'));
        $this->assertSame([], CImporter::toArray(new ExporterFakeSampleImport(), 'array.xlsx', 's3'));
        $this->assertCount(0, CImporter::toCollection(new ExporterFakeSampleImport(), 'koleksi.xlsx'));
        CImporter::import(new ExporterFakeQueuedImport(), 'antre.xlsx');

        $fake->assertImported('tidak-ada.xlsx');
        $fake->assertImported('tidak-ada.xlsx', function ($recorded) use ($import) {
            return $recorded === $import;
        });
        $fake->assertImported('array.xlsx', 's3');
        $fake->assertImported('koleksi.xlsx');
        $fake->assertImported('antre.xlsx');
    }

    public function testMatchByRegex() {
        $fake = CExporter::fake();
        CExporter::download(new ExporterFakeSampleExport(), 'laporan-2026-10.xlsx');
        CExporter::store(new ExporterFakeSampleExport(), 'dir/laporan-2026-10.xlsx');

        $this->assertNotNull($this->failureOf(function () use ($fake) {
            $fake->assertDownloaded('/laporan-\d+-\d+\.xlsx/');
        }), 'tanpa matchByRegex nama dicocokkan persis');

        $fake->matchByRegex();
        $fake->assertDownloaded('/^laporan-\d+-\d+\.xlsx$/');
        $fake->assertStored('/laporan-.*\.xlsx$/');

        $fake->doesntMatchByRegex();
        $this->assertNotNull($this->failureOf(function () use ($fake) {
            $fake->assertDownloaded('/laporan/');
        }));
    }

    public function testAssertionsFailWithClearMessages() {
        $fake = CExporter::fake();
        CExporter::download(new ExporterFakeSampleExport(), 'ada.xlsx');
        CExporter::store(new ExporterFakeSampleExport(), 'tersimpan.xlsx', ['diskName' => 's3']);

        $this->assertStringContainsString('lain.xlsx is not downloaded', $this->failureOf(function () use ($fake) {
            $fake->assertDownloaded('lain.xlsx');
        }));
        $this->assertStringContainsString('was not downloaded with the expected data', $this->failureOf(function () use ($fake) {
            $fake->assertDownloaded('ada.xlsx', function () {
                return false;
            });
        }));
        $this->assertStringContainsString('tersimpan.xlsx is not stored on disk default', $this->failureOf(function () use ($fake) {
            $fake->assertStored('tersimpan.xlsx');
        }));
        $this->assertStringContainsString('is not queued for export', $this->failureOf(function () use ($fake) {
            $fake->assertQueued('tersimpan.xlsx', 's3');
        }));
        $this->assertStringContainsString('is not exported in raw', $this->failureOf(function () use ($fake) {
            $fake->assertExportedInRaw(ExporterFakeSampleExport::class);
        }));
        $this->assertStringContainsString('is not imported', $this->failureOf(function () use ($fake) {
            $fake->assertImported('x.xlsx');
        }));
        $this->assertStringContainsString('was downloaded unexpectedly', $this->failureOf(function () use ($fake) {
            $fake->assertNotDownloaded('ada.xlsx');
        }));
        $this->assertStringContainsString('was stored on disk s3 unexpectedly', $this->failureOf(function () use ($fake) {
            $fake->assertNotStored('tersimpan.xlsx', 's3');
        }));
    }

    public function testWithoutFakeTheRealPathIsUsed() {
        $this->assertFalse(CExporter::hasFake());

        $raw = CExporter::raw(new ExporterRoundTripFakeExport(), CExporter::CSV);

        $this->assertStringContainsString('halo', $raw);
    }
}

class ExporterRoundTripFakeExport implements CExporter_Concern_FromArray {
    public function getArray() {
        return [['halo', 'dunia']];
    }
}
