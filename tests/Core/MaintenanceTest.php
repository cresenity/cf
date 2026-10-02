<?php

use PHPUnit\Framework\TestCase;

/**
 * Keputusan mode maintenance.
 *
 * Yang diuji `CF::maintenanceDecision()` — bagian murni dari
 * `CF::isDownForMaintenance()`. Pemisahannya disengaja: keputusannya bergantung
 * pada konfigurasi, jalur, dan cookie saja, sehingga seluruh cabangnya dapat
 * diperiksa tanpa server, berkas, maupun request.
 */
class Core_MaintenanceTest extends TestCase {
    const SECRET = 'a7f3c9e21b4d5680';

    /**
     * @return array
     */
    protected function config(array $override = []) {
        return array_merge([
            'down' => true,
            'view' => 'system.maintenance',
            'secret' => self::SECRET,
            'cookie' => '',
        ], $override);
    }

    // ------------------------------------------------------------------
    // aplikasi hidup
    // ------------------------------------------------------------------

    public function testApplicationIsUpWhenDownIsFalse() {
        $this->assertSame(
            CF::MAINTENANCE_UP,
            CF::maintenanceDecision($this->config(['down' => false]), '/')
        );
    }

    /**
     * Kunci `down` yang hilang berarti **hidup**.
     *
     * Sebelumnya bawaannya `true`, sehingga berkas yang tidak lengkap atau
     * salah ketik menjatuhkan aplikasi. Kesalahan menulis konfigurasi tidak
     * seharusnya berbiaya downtime, dan test ini yang menjaga arah itu.
     */
    public function testApplicationIsUpWhenDownKeyIsMissing() {
        $this->assertSame(
            CF::MAINTENANCE_UP,
            CF::maintenanceDecision(['view' => 'system.maintenance'], '/')
        );
    }

    public function testApplicationIsUpWhenConfigIsNotAnArray() {
        $this->assertSame(CF::MAINTENANCE_UP, CF::maintenanceDecision(null, '/'));
        $this->assertSame(CF::MAINTENANCE_UP, CF::maintenanceDecision('down', '/'));
        $this->assertSame(CF::MAINTENANCE_UP, CF::maintenanceDecision(1, '/'));
    }

    // ------------------------------------------------------------------
    // aplikasi tutup
    // ------------------------------------------------------------------

    public function testApplicationIsDownForAnOrdinaryVisitor() {
        $this->assertSame(CF::MAINTENANCE_DOWN, CF::maintenanceDecision($this->config(), '/'));
        $this->assertSame(CF::MAINTENANCE_DOWN, CF::maintenanceDecision($this->config(), 'product/list'));
    }

    // ------------------------------------------------------------------
    // tautan rahasia
    // ------------------------------------------------------------------

    public function testOpeningTheSecretLinkGrantsAccess() {
        $this->assertSame(
            CF::MAINTENANCE_GRANT,
            CF::maintenanceDecision($this->config(), self::SECRET)
        );
    }

    public function testTheSecretLinkIsAcceptedWithSurroundingSlashes() {
        $this->assertSame(CF::MAINTENANCE_GRANT, CF::maintenanceDecision($this->config(), '/' . self::SECRET));
        $this->assertSame(CF::MAINTENANCE_GRANT, CF::maintenanceDecision($this->config(), self::SECRET . '/'));
    }

    /**
     * Jalur yang sekadar diawali rahasianya tidak cukup — bila cocok sebagian
     * saja diterima, seluruh sub-jalur di bawahnya ikut membuka pintu.
     */
    public function testAPathThatMerelyStartsWithTheSecretIsNotEnough() {
        $this->assertSame(
            CF::MAINTENANCE_DOWN,
            CF::maintenanceDecision($this->config(), self::SECRET . '/product')
        );
    }

    public function testAnEmptySecretDoesNotTurnEveryRequestIntoTheSecretLink() {
        $config = $this->config(['secret' => '']);

        $this->assertSame(CF::MAINTENANCE_DOWN, CF::maintenanceDecision($config, ''));
        $this->assertSame(CF::MAINTENANCE_DOWN, CF::maintenanceDecision($config, '/'));
    }

    // ------------------------------------------------------------------
    // cookie hasil tautan rahasia
    // ------------------------------------------------------------------

    public function testTheGrantedCookieBypassesMaintenance() {
        $this->assertSame(
            CF::MAINTENANCE_BYPASS,
            CF::maintenanceDecision($this->config(), 'product/list', [CF::MAINTENANCE_COOKIE => self::SECRET])
        );
    }

    /**
     * Nilainya yang diperiksa, bukan keberadaannya — inilah bedanya dengan
     * bentuk lama, dan alasan bentuk ini yang dianjurkan.
     */
    public function testACookieWithTheWrongValueDoesNotBypass() {
        $config = $this->config();

        $this->assertSame(
            CF::MAINTENANCE_DOWN,
            CF::maintenanceDecision($config, '/', [CF::MAINTENANCE_COOKIE => 'tebakan'])
        );
        $this->assertSame(
            CF::MAINTENANCE_DOWN,
            CF::maintenanceDecision($config, '/', [CF::MAINTENANCE_COOKIE => ''])
        );
        $this->assertSame(
            CF::MAINTENANCE_DOWN,
            CF::maintenanceDecision($config, '/', [CF::MAINTENANCE_COOKIE => substr(self::SECRET, 0, 8)])
        );
    }

    // ------------------------------------------------------------------
    // bentuk lama
    // ------------------------------------------------------------------

    /**
     * Dipertahankan supaya konfigurasi yang sudah terpasang tidak patah.
     * Kelemahannya melekat: yang diperiksa hanya keberadaan cookie, sehingga
     * **nama** cookie itulah rahasianya.
     */
    public function testTheLegacyCookieNameStillBypasses() {
        $config = $this->config(['secret' => '', 'cookie' => 'bypass-maintenance']);

        $this->assertSame(
            CF::MAINTENANCE_BYPASS,
            CF::maintenanceDecision($config, '/', ['bypass-maintenance' => '1'])
        );
    }

    public function testTheLegacyCookieBypassesRegardlessOfItsValue() {
        $config = $this->config(['secret' => '', 'cookie' => 'bypass-maintenance']);

        $this->assertSame(
            CF::MAINTENANCE_BYPASS,
            CF::maintenanceDecision($config, '/', ['bypass-maintenance' => ''])
        );
    }

    public function testAnEmptyLegacyCookieNameNeverBypasses() {
        $config = $this->config(['secret' => '', 'cookie' => '']);

        $this->assertSame(CF::MAINTENANCE_DOWN, CF::maintenanceDecision($config, '/', ['' => '1']));
        $this->assertSame(CF::MAINTENANCE_DOWN, CF::maintenanceDecision($config, '/', []));
    }

    public function testBothMechanismsMayBeConfiguredTogether() {
        $config = $this->config(['cookie' => 'bypass-maintenance']);

        $this->assertSame(
            CF::MAINTENANCE_BYPASS,
            CF::maintenanceDecision($config, '/', [CF::MAINTENANCE_COOKIE => self::SECRET])
        );
        $this->assertSame(
            CF::MAINTENANCE_BYPASS,
            CF::maintenanceDecision($config, '/', ['bypass-maintenance' => '1'])
        );
        $this->assertSame(CF::MAINTENANCE_DOWN, CF::maintenanceDecision($config, '/'));
    }

    // ------------------------------------------------------------------
    // penjagaan yang berlaku saat aplikasi hidup
    // ------------------------------------------------------------------

    /**
     * Saat aplikasi hidup, tautan rahasianya tidak boleh membajak jalur apa
     * pun — bila ia tetap diperiksa, sebuah rute yang kebetulan bernama sama
     * dengan rahasianya akan dialihkan alih-alih dilayani.
     */
    public function testTheSecretLinkIsInertWhileTheApplicationIsUp() {
        $this->assertSame(
            CF::MAINTENANCE_UP,
            CF::maintenanceDecision($this->config(['down' => false]), self::SECRET)
        );
    }

    // ------------------------------------------------------------------
    // kunci opsional: except, cookie bertanda tangan, respons
    // ------------------------------------------------------------------

    public function testExceptPathsAreLetThroughWhileEverythingElseStaysDown() {
        $config = $this->config(['except' => ['health', 'webhook/*', '/']]);

        $this->assertSame(CF::MAINTENANCE_BYPASS, CF::maintenanceDecision($config, 'health'));
        $this->assertSame(CF::MAINTENANCE_BYPASS, CF::maintenanceDecision($config, '/health/'));
        $this->assertSame(CF::MAINTENANCE_BYPASS, CF::maintenanceDecision($config, 'webhook/xendit/paid'));
        $this->assertSame(CF::MAINTENANCE_BYPASS, CF::maintenanceDecision($config, ''), "'/' berarti halaman utama");
        $this->assertSame(CF::MAINTENANCE_DOWN, CF::maintenanceDecision($config, 'product/list'));
        $this->assertSame(CF::MAINTENANCE_DOWN, CF::maintenanceDecision($config, 'healthcheck'), 'tanpa wildcard harus persis sama');
    }

    public function testExceptDoesNothingWhileTheApplicationIsUp() {
        $this->assertSame(CF::MAINTENANCE_UP, CF::maintenanceDecision($this->config(['down' => false, 'except' => ['health']]), 'health'));
    }

    public function testAConfigWithoutTheOptionalKeysBehavesAsBefore() {
        $this->assertSame(CF::MAINTENANCE_DOWN, CF::maintenanceDecision($this->config(), 'health'));
    }

    public function testASignedBypassCookieIsAcceptedUntilItExpires() {
        $now = 1000000;
        $value = CF::maintenanceBypassCookieValue(self::SECRET, $now + 600);

        $this->assertSame(CF::MAINTENANCE_BYPASS, CF::maintenanceDecision($this->config(), 'x', [CF::MAINTENANCE_COOKIE => $value], $now));
        $this->assertSame(CF::MAINTENANCE_DOWN, CF::maintenanceDecision($this->config(), 'x', [CF::MAINTENANCE_COOKIE => $value], $now + 601), 'kedaluwarsa');
    }

    public function testASignedBypassCookieCannotBeForgedOrReusedWithAnotherSecret() {
        $now = 1000000;
        $payload = json_decode(base64_decode(CF::maintenanceBypassCookieValue(self::SECRET, $now + 600)), true);

        $extended = base64_encode(json_encode(['expires_at' => $now + 999999, 'mac' => $payload['mac']]));
        $otherSecret = CF::maintenanceBypassCookieValue('rahasia-lain', $now + 600);

        $this->assertSame(CF::MAINTENANCE_DOWN, CF::maintenanceDecision($this->config(), 'x', [CF::MAINTENANCE_COOKIE => $extended], $now), 'masa berlaku diperpanjang tanpa tanda tangan baru');
        $this->assertSame(CF::MAINTENANCE_DOWN, CF::maintenanceDecision($this->config(), 'x', [CF::MAINTENANCE_COOKIE => $otherSecret], $now));
        $this->assertSame(CF::MAINTENANCE_DOWN, CF::maintenanceDecision($this->config(), 'x', [CF::MAINTENANCE_COOKIE => 'bukan-base64-json'], $now));
    }

    public function testThePlainSecretCookieStillWorksNextToTheSignedFormat() {
        $this->assertSame(CF::MAINTENANCE_BYPASS, CF::maintenanceDecision($this->config(), 'x', [CF::MAINTENANCE_COOKIE => self::SECRET]));
    }

    public function testTheMaintenanceResponseDefaultsToA503View() {
        $response = CF::maintenanceResponse($this->config(), CHTTP_Request::create('/x', 'GET'));

        $this->assertSame(503, $response->getStatusCode());
        $this->assertFalse($response->headers->has('Retry-After'));
        $this->assertFalse($response->headers->has('Refresh'));
    }

    public function testTheMaintenanceResponseCarriesStatusRetryAndRefresh() {
        $response = CF::maintenanceResponse($this->config(['status' => 502, 'retry' => 120, 'refresh' => 30]), CHTTP_Request::create('/x', 'GET'));

        $this->assertSame(502, $response->getStatusCode());
        $this->assertSame('120', $response->headers->get('Retry-After'));
        $this->assertSame('30', $response->headers->get('Refresh'));
    }

    public function testJsonIsOnlyUsedWhenEnabledAndTheRequestExpectsIt() {
        $request = CHTTP_Request::create('/api/x', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

        $off = CF::maintenanceResponse($this->config(), $request);
        $this->assertStringNotContainsString('application/json', (string) $off->headers->get('Content-Type'), 'tanpa kunci json perilaku lama dipertahankan');

        $on = CF::maintenanceResponse($this->config(['json' => true, 'message' => 'Sedang pemeliharaan', 'retry' => 60]), $request);
        $this->assertSame(503, $on->getStatusCode());
        $this->assertSame('{"message":"Sedang pemeliharaan"}', $on->getContent());
        $this->assertSame('60', $on->headers->get('Retry-After'));

        $html = CF::maintenanceResponse($this->config(['json' => true]), CHTTP_Request::create('/x', 'GET', [], [], [], ['HTTP_ACCEPT' => 'text/html']));
        $this->assertStringNotContainsString('application/json', (string) $html->headers->get('Content-Type'));
    }
}
