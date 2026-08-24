<?php

use PHPUnit\Framework\TestCase;

/**
 * Penurunan path berkas controller.
 *
 * Yang diuji `CF::controllerFileCandidates()` — bagian murni dari
 * `CF::autoLoad()`. Kandidat pertama adalah bentuk lama, sisanya cadangan
 * untuk folder yang kapitalisasinya berbeda dari nama kelas.
 */
class Core_AutoLoadTest extends TestCase {
    /**
     * @param string $class
     *
     * @return array
     */
    protected function candidates($class) {
        return CF::controllerFileCandidates(explode('_', substr($class, 11)));
    }

    public function testKeepsLegacyDerivationAsFirstCandidate() {
        $candidates = $this->candidates('Controller_UserDataTracking_Affiliate');

        $this->assertSame('userDataTracking' . DS . 'affiliate', $candidates[0]);
    }

    public function testOffersLowercasedDirectoryAsFallback() {
        $candidates = $this->candidates('Controller_UserDataTracking_Affiliate');

        $this->assertContains('userdatatracking' . DS . 'affiliate', $candidates);
    }

    public function testOffersFullyLowercasedPathAsFallback() {
        $candidates = $this->candidates('Controller_Admin_Setting_Web_CmsHome');

        $this->assertContains('admin' . DS . 'setting' . DS . 'web' . DS . 'cmshome', $candidates);
    }

    public function testOffersUntouchedFilenameAsFallback() {
        $candidates = $this->candidates('Controller_Laporan_SisaSaldo');

        $this->assertContains('laporan' . DS . 'SisaSaldo', $candidates);
    }

    public function testSingleSegmentClassStillResolves() {
        $candidates = $this->candidates('Controller_ZaraPanel');

        $this->assertSame('zaraPanel', $candidates[0]);
        $this->assertContains('zarapanel', $candidates);
    }

    public function testCandidatesAreUnique() {
        $candidates = $this->candidates('Controller_Admin_Setting_Web_CmsHome');

        $this->assertSame($candidates, array_values(array_unique($candidates)));
    }
}
