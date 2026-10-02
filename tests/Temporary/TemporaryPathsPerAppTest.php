<?php
use PHPUnit\Framework\TestCase;

class TemporaryPathsPerAppArrayModel {
    use CModel_ArrayDriver_ArrayDriverTrait;
}

/**
 * Temp path yang tadinya dipakai bersama antar app (temp/runner/ffmpeg, temp/devsuite/db/db-dumps,
 * temp/model/array/cache) kini per app lewat CTemporary: temp/<folder>/<appCode>/.
 */
class TemporaryPathsPerAppTest extends TestCase {
    /**
     * @return string folder app saat ini ("<appCode>" atau "common")
     */
    protected function appSegment() {
        $appCode = (string) CF::appCode();

        return strlen($appCode) > 0 ? $appCode : 'common';
    }

    public function testFfmpegTemporaryDirectoriesLiveUnderThePerAppRoot() {
        $directories = new CRunner_FFMpeg_Storage_TemporaryDirectories();
        $created = $directories->create();

        try {
            $this->assertDirectoryExists($created);
            $this->assertSame(rtrim(CTemporary::getDirectory('runner', 'ffmpeg'), '/\\'), dirname($created));
            $this->assertStringContainsString('runner' . DS . $this->appSegment() . DS . 'ffmpeg', $created, 'appCode tepat setelah folder tipe');
        } finally {
            $directories->deleteAll();
        }

        $this->assertDirectoryDoesNotExist($created, 'deleteAll() menghapus direktori sementara');
    }

    public function testFfmpegRootIsCreatedWhenMissing() {
        // sebelumnya create() memakai mkdir non-rekursif sehingga gagal bila temp/runner/ffmpeg belum ada
        $root = rtrim(CTemporary::getDirectory('runner', 'ffmpeg'), '/\\');

        $this->assertDirectoryExists($root);
    }

    public function testDevSuiteDumpPathIsPerApp() {
        $class = new ReflectionClass(CDevSuite_Linux_Db_MariaDB::class);
        $instance = $class->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($instance, 'temporaryDumpPath');
        $method->setAccessible(true);

        $path = $method->invoke($instance, 'mysql-contoh.sql');

        $this->assertSame(CTemporary::getDirectory('devsuite', 'db/db-dumps') . 'mysql-contoh.sql', $path);
        $this->assertStringContainsString('devsuite' . DS . $this->appSegment() . DS . 'db' . DS . 'db-dumps' . DS . 'mysql-contoh.sql', $path, 'appCode tepat setelah folder tipe');
        $this->assertDirectoryExists(dirname($path));
    }

    public function testArrayDriverCacheDirectoryIsPerAppAndStaysOptIn() {
        $expected = DOCROOT . 'temp' . DS . 'model' . DS . $this->appSegment() . DS . 'array' . DS . 'cache';
        $legacy = DOCROOT . 'temp' . DS . 'model' . DS . 'array' . DS . 'cache';
        $existedBefore = is_dir($expected);
        $legacyUsable = is_dir($legacy) && is_writable($legacy);

        $method = new ReflectionMethod(TemporaryPathsPerAppArrayModel::class, 'arrayDriverCacheDirectory');
        $method->setAccessible(true);
        $directory = $method->invoke(null);

        try {
            $this->assertSame($expected, $directory);
            $this->assertStringContainsString('cache', basename(dirname($directory)) . basename($directory));
            if (!$existedBefore) {
                $this->assertSame($legacyUsable, is_dir($directory), 'folder per app hanya dibuat bila folder lama sudah ada (caching tetap opt-in)');
            }
        } finally {
            if (!$existedBefore && is_dir($directory)) {
                @rmdir($directory);
            }
        }
    }

    public function testGetDirectoryPutsTheAppCodeRightAfterTheTypeFolder() {
        $plain = CTemporary::getDirectory('uji-tipe');
        $nested = CTemporary::getDirectory('uji-tipe', 'a/b');
        $slashes = CTemporary::getDirectory('uji-tipe', '/a/b/');

        try {
            $this->assertSame(DOCROOT . 'temp' . DS . 'uji-tipe' . DS . $this->appSegment() . DS, $plain, 'tanpa subPath: perilaku lama');
            $this->assertSame($plain . 'a' . DS . 'b' . DS, $nested);
            $this->assertSame($nested, $slashes, 'garis miring di tepi subPath diabaikan');
            $this->assertDirectoryExists($nested);
            $this->assertSame(DOCROOT . 'temp' . DS, CTemporary::getDirectory(), 'tanpa folder: root temp');
            $this->assertSame(DOCROOT . 'temp' . DS, CTemporary::getDirectory(null, 'a/b'), 'subPath tanpa folder diabaikan');
        } finally {
            @rmdir($nested);
            @rmdir(dirname(rtrim($nested, DS)));
            @rmdir($plain);
            @rmdir(DOCROOT . 'temp' . DS . 'uji-tipe');
        }
    }
}
