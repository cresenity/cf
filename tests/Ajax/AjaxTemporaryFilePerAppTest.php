<?php
use PHPUnit\Framework\TestCase;

/**
 * Berkas temp method ajax dipisah per app (#-13859). Insiden aslinya (collector 12633): tombol
 * ekspor di admin.tribelio.com membaca berkas temp milik tribelio karena keduanya berbagi
 * temp/ajax/ di satu docroot. Berkas baru ditulis ke ajax/<appCode>/..., sementara id yang dibuat
 * sebelum pemisahan masih ditemukan di lokasi lama supaya url yang sudah beredar tetap jalan.
 */
class AjaxTemporaryFilePerAppTest extends TestCase {
    /**
     * @var string[]
     */
    protected $created = [];

    protected function tearDown(): void {
        $disk = CTemporary::disk();
        foreach ($this->created as $file) {
            if ($disk->exists($file)) {
                $disk->delete($file);
            }
        }
    }

    public function testNewMethodsAreWrittenUnderTheAppFolder() {
        $method = CAjax::createMethod();
        $method->setType('DataTableExporter')->setData('smoke', true);
        $url = $method->makeUrl();
        $id = carr::last(explode('/', parse_url($url, PHP_URL_PATH)));

        $file = CAjax::temporaryFile($id);
        $this->created[] = $file;

        $this->assertStringStartsWith('ajax' . DIRECTORY_SEPARATOR . CF::appCode() . DIRECTORY_SEPARATOR, $file);
        $this->assertTrue(CTemporary::disk()->exists($file));
        $this->assertSame('DataTableExporter', CAjax::createMethod(CTemporary::disk()->get($file))->getType());
    }

    public function testLegacySharedLocationIsStillReadForOldIds() {
        $id = date('Ymd') . cutils::randmd5();
        $legacy = CTemporary::getPath('ajax', $id . '.tmp');
        CTemporary::disk()->put($legacy, json_encode(['type' => 'Legacy', 'data' => ['x' => 1]]));
        $this->created[] = $legacy;

        $this->assertSame($legacy, CAjax::temporaryFile($id));
        $this->assertSame(['x' => 1], carr::get(CAjax::getData($id), 'data'));

        // menulis ulang id lama tetap ke lokasi lama - tidak membuat salinan per app yang bisa
        // menyimpang dari yang dibaca pembaca lain
        CAjax::setData($id, ['type' => 'Legacy', 'data' => ['x' => 2]]);
        $this->assertSame(['x' => 2], carr::get(json_decode(CTemporary::disk()->get($legacy), true), 'data'));
        $this->assertFalse(CTemporary::disk()->exists(CTemporary::getPath(CAjax::temporaryFolder(), $id . '.tmp')));
    }

    public function testAnIdThatExistsNowhereResolvesToTheAppFolder() {
        $id = date('Ymd') . cutils::randmd5();

        $this->assertStringStartsWith(CAjax::temporaryFolder() . DIRECTORY_SEPARATOR, CAjax::temporaryFile($id));
    }
}
