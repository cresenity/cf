<?php

use PHPUnit\Framework\TestCase;

/**
 * CHouseKeeping_FileTemp_ResourceFileTemp memangkas sisa konversi di temp/resource: direktori scratch
 * `temp/<acak>` dan pohon `<Ymd>/...` lama, baik langsung di bawah temp/resource maupun per app.
 */
class ResourceTempHousekeepingTest extends TestCase {
    /**
     * @var string
     */
    protected $base;

    protected function setUp(): void {
        $this->base = sys_get_temp_dir() . '/cf-resource-temp-' . cstr::random(8);
        mkdir($this->base, 0777, true);
    }

    protected function tearDown(): void {
        if (is_dir($this->base)) {
            exec('rm -rf ' . escapeshellarg($this->base));
        }
    }

    /**
     * @param string $relative
     * @param int    $ageHours
     *
     * @return string
     */
    protected function dir($relative, $ageHours = 0) {
        $path = $this->base . '/' . $relative;
        mkdir($path, 0777, true);
        file_put_contents($path . '/f.jpg', 'x');
        touch($path, time() - $ageHours * 3600);

        return $path;
    }

    public function testOldScratchAndDateTreesAreRemovedFreshOnesKept() {
        $oldScratch = $this->dir('temp/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 30);
        $freshScratch = $this->dir('temp/bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', 1);
        $oldDate = $this->dir('20250101/a/b/c', 0);
        $today = $this->dir(date('Ymd') . '/a/b/c', 0);
        $appOldScratch = $this->dir('ohayomart/temp/cccccccccccccccccccccccccccccccc', 30);
        $appFreshScratch = $this->dir('ohayomart/temp/dddddddddddddddddddddddddddddddd', 1);
        $appOldDate = $this->dir('ohayomart/20250102/a/b', 0);
        $appToday = $this->dir('ohayomart/' . date('Ymd') . '/a', 0);

        $this->assertTrue(CHouseKeeping_FileTemp_ResourceFileTemp::execute(24, $this->base));

        $this->assertDirectoryDoesNotExist($oldScratch);
        $this->assertDirectoryExists($freshScratch);
        $this->assertDirectoryDoesNotExist(dirname($oldDate, 3));
        $this->assertDirectoryExists($today);
        $this->assertDirectoryDoesNotExist($appOldScratch);
        $this->assertDirectoryExists($appFreshScratch);
        $this->assertDirectoryDoesNotExist(dirname($appOldDate, 2));
        $this->assertDirectoryExists($appToday);
        $this->assertDirectoryExists($this->base . '/ohayomart/temp', 'folder induk app tidak ikut dihapus');
    }

    public function testNothingToDoReturnsFalse() {
        $this->dir('temp/eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee', 1);

        $this->assertFalse(CHouseKeeping_FileTemp_ResourceFileTemp::execute(24, $this->base));
        $this->assertFalse(CHouseKeeping_FileTemp_ResourceFileTemp::execute(24, $this->base . '/tidak-ada'));
    }

    public function testScratchDirectoriesAreCreatedPerApp() {
        $directory = CResources_Helpers_TemporaryDirectory::create();
        $path = $directory->path();
        $directory->delete();

        $this->assertStringStartsWith(DOCROOT . 'temp' . DS . 'resource' . DS . CF::appCode() . DS . 'temp' . DS, $path);
    }
}
