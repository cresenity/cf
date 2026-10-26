<?php

use PHPUnit\Framework\TestCase;

/**
 * Penjagaan atas tipe parameter callback collector.
 *
 * `CException_ReportableHandler::handles()` memilih callback dengan
 * `is_a($e, <tipe parameter pertama>)`. Di PHP 7 ke atas `Error` bukan turunan
 * `Exception` - keduanya hanya berbagi `Throwable`. Callback collector di
 * `system/bootstrap.php` dulu bertipe `Exception`, sehingga justru galat yang
 * paling perlu terlihat (`TypeError`, `ValueError`, dan kerabatnya) tidak
 * pernah terkumpul, tanpa gejala apa pun.
 */
class CollectorReportableTypeTest extends TestCase {
    /**
     * Dasar dari seluruh perkara ini: hierarki PHP-nya sendiri.
     *
     * @return void
     */
    public function testErrorIsNotAnException() {
        $this->assertFalse(is_a(new TypeError('x'), 'Exception'), 'TypeError ternyata turunan Exception - dasar test ini gugur');
        $this->assertTrue(is_a(new TypeError('x'), 'Throwable'));
        $this->assertTrue(is_a(new Exception('x'), 'Throwable'));
    }

    /**
     * Bentuk pemilihan yang dipakai ReportableHandler, diuji langsung supaya
     * perbedaannya terlihat sebagai perilaku, bukan sebagai teori.
     *
     * @param callable $callback
     *
     * @return string
     */
    protected function firstParameterType($callback) {
        $parameter = carr::first((new ReflectionFunction($callback))->getParameters());
        $type = $parameter->getType();

        return $type ? $type->getName() : 'mixed';
    }

    /**
     * @return void
     */
    public function testExceptionTypedCallbackMissesError() {
        $type = $this->firstParameterType(function (Exception $e) {
        });

        $this->assertSame('Exception', $type);
        $this->assertFalse(is_a(new TypeError('x'), $type), 'callback bertipe Exception seharusnya melewatkan Error');
    }

    /**
     * @return void
     */
    public function testThrowableTypedCallbackCatchesBoth() {
        $type = $this->firstParameterType(function (Throwable $e) {
        });

        $this->assertSame('Throwable', $type);
        $this->assertTrue(is_a(new TypeError('x'), $type));
        $this->assertTrue(is_a(new Exception('x'), $type));
    }

    /**
     * Penjagaan atas berkas sumbernya: callback collector harus tetap bertipe
     * Throwable. Dikembalikan ke Exception, galat fatal berhenti terkumpul dan
     * tidak ada yang menandainya.
     *
     * @return void
     */
    public function testBootstrapRegistersCollectorForThrowable() {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/system/bootstrap.php');

        $position = strpos($source, 'collectException');
        $this->assertNotFalse($position, 'pendaftaran collector tidak ditemukan di system/bootstrap.php');

        $snippet = substr($source, max(0, $position - 200), 260);
        $this->assertTrue(
            strpos($snippet, 'function (Throwable $e)') !== false,
            'callback collector tidak lagi bertipe Throwable - Error tidak akan pernah terkumpul'
        );
    }

    /**
     * `Psy\Exception\ParseErrorException` cuma terjadi saat kode yang diketik di
     * REPL `phpcf tinker` gagal di-parse - kodenya belum sempat jalan sama sekali,
     * jadi tidak pernah merepresentasikan bug produksi (lihat #AD-21649/#TB-21637/
     * #MC-21585, satu root cause lintas app).
     *
     * @return void
     */
    public function testBootstrapSkipsPsyParseErrorExceptionFromCollector() {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/system/bootstrap.php');

        $position = strpos($source, 'collectException');
        $snippet = substr($source, max(0, $position - 200), 260);

        $this->assertTrue(
            strpos($snippet, 'Psy\Exception\ParseErrorException') !== false,
            'guard ParseErrorException hilang - typo di tinker akan ter-collect lagi sebagai bug produksi'
        );
    }
}
