<?php

use PHPUnit\Framework\TestCase;

/**
 * Versi aset harus identik di setiap node yang menyajikan berkas yang sama. mtime tidak
 * memenuhi itu (git pull yang tidak serempak memberi mtime berbeda per node — deploy tribelio
 * 18 Agu 2026, selisih 92 detik, ?v= berbeda antar anggota ALB), jadi tanpa identitas rilis
 * versinya diturunkan dari isi berkas (#-13856).
 */
class AssetHelperVersionTest extends TestCase {
    /**
     * @var string
     */
    protected $dir;

    protected function setUp(): void {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cf-asset-version-' . uniqid();
        mkdir($this->dir, 0777, true);
        CConfig::repository()->set('assets.release', null);
        CManager_Asset_Helper::flushReleaseVersion();
    }

    protected function tearDown(): void {
        CConfig::repository()->set('assets.release', null);
        CManager_Asset_Helper::flushReleaseVersion();
        foreach (glob($this->dir . DIRECTORY_SEPARATOR . '*') as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    /**
     * @param string $name
     * @param string $content
     * @param int    $mtime
     *
     * @return string
     */
    protected function assetFile($name, $content, $mtime) {
        $path = $this->dir . DIRECTORY_SEPARATOR . $name;
        file_put_contents($path, $content);
        touch($path, $mtime);
        clearstatcache(true, $path);

        return $path;
    }

    public function testSameContentGivesTheSameVersionRegardlessOfMtime() {
        // dua "node": berkas identik, ditulis 92 detik terpisah — persis kasus ALB tribelio
        $node1 = $this->assetFile('node1.js', 'console.log("cres");', 1787064544);
        $node2 = $this->assetFile('node2.js', 'console.log("cres");', 1787064544 + 92);

        $this->assertSame(
            CManager_Asset_Helper::getFileVersion($node1, 5),
            CManager_Asset_Helper::getFileVersion($node2, 5)
        );
        $this->assertSame(
            CManager_Asset_Helper::getVersionForFile($node1),
            CManager_Asset_Helper::getVersionForFile($node2)
        );
        $this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', CManager_Asset_Helper::getFileVersion($node1));
    }

    public function testVersionChangesWhenTheContentChanges() {
        $before = $this->assetFile('a.js', 'console.log(1);', 1787064544);
        $after = $this->assetFile('b.js', 'console.log(1); console.log(2);', 1787064544);

        $this->assertNotSame(
            CManager_Asset_Helper::getFileVersion($before),
            CManager_Asset_Helper::getFileVersion($after)
        );
    }

    public function testReleaseIdentityStillWinsOverContent() {
        CConfig::repository()->set('assets.release', 'rilis-2026-09-17');
        CManager_Asset_Helper::flushReleaseVersion();
        $file = $this->assetFile('c.js', 'console.log(3);', 1787064544);

        $this->assertSame('rilis-2026-09-17', CManager_Asset_Helper::getFileVersion($file, 5));
        $this->assertSame('rilis-2026-09-17', CManager_Asset_Helper::getVersionForFile($file));
    }

    public function testMissingFileDoesNotThrow() {
        $this->assertSame('0', CManager_Asset_Helper::getFileVersion($this->dir . DIRECTORY_SEPARATOR . 'tidak-ada.js'));
    }
}
