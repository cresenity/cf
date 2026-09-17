<?php

use PHPUnit\Framework\TestCase;

/**
 * Penurunan path berkas controller, dan rujukan kelas yang salah kapital.
 *
 * Yang diuji `CF::controllerFileCandidates()` — bagian murni dari
 * `CF::autoLoad()`. Kandidat pertama adalah bentuk lama, sisanya cadangan
 * untuk folder yang kapitalisasinya berbeda dari nama kelas.
 *
 * Bagian kedua: nama kelas PHP tidak memedulikan huruf, tetapi `is_file()` di Linux iya.
 * `TBWEB::foo()` atau `TBModel_ManualSubscriptionAddOn::find()` (berkasnya
 * `ManualSubscriptionAddon.php`) fatal hanya bila kelasnya belum termuat — jadi lolos di
 * dev dan meledak di produksi (#-13889). Autoloader kini mencari padanan tanpa memedulikan
 * huruf setelah pencarian persis gagal.
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

    public function testLoadsAClassReferencedWithTheWrongCase() {
        $this->assertFalse(class_exists('CVendor_Namecheap_Command_Domains_Ns', false), 'fixture harus belum termuat agar autoloader yang diuji');

        $this->assertTrue(class_exists('cvendor_namecheap_command_domains_NS'));
        $this->assertTrue(class_exists('CVendor_Namecheap_Command_Domains_Ns', false));
    }

    public function testLoadsAClassWhoseDirectorySegmentHasTheWrongCase() {
        $this->assertFalse(class_exists('CVendor_Namecheap_Command_Domains_Transfer', false));

        $this->assertTrue(class_exists('CVendor_Namecheap_Command_DOMAINS_Transfer'));
    }

    public function testStillReportsAGenuinelyMissingClass() {
        $this->assertFalse(class_exists('CVendor_Namecheap_Command_Domains_TidakAda'));
        $this->assertFalse(class_exists('CVendor_TidakAda_Sama_Sekali'));
    }
}
