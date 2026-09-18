<?php

use PHPUnit\Framework\TestCase;

/**
 * Engine unggah (FileUpload/ImgUpload) lewat jalur base64 di POST — tanpa $_FILES — dan
 * CAjax_FileAjax: penentuan tipe dari akhiran id, identifier `@fileId|resourceId|url`, create().
 */
class AjaxUploadTest extends TestCase {
    /**
     * @var string[]
     */
    protected $created = [];

    protected function setUp(): void {
        $_GET = [];
        $_POST = [];
    }

    protected function tearDown(): void {
        $disk = CTemporary::publicDisk();
        foreach ($this->created as $file) {
            if ($disk->exists($file)) {
                $disk->delete($file);
            }
        }
        $_GET = [];
        $_POST = [];
    }

    /**
     * @param string $type
     * @param array  $data
     * @param array  $post
     *
     * @return array
     */
    protected function upload($type, array $data, array $post) {
        $method = CAjax::createMethod()->setType($type)->setMethod('post');
        foreach ($data as $key => $value) {
            $method->setData($key, $value);
        }
        $_POST = $post;
        $response = CAjax_Method::createEngine($method)->execute();
        $this->assertInstanceOf(CHTTP_JsonResponse::class, $response);
        $payload = json_decode($response->getContent(), true);
        if (!empty($payload['data']['fileId'])) {
            $folder = $type === CAjax::TYPE_IMG_UPLOAD ? CAjax_Engine_ImgUpload::FOLDER : CAjax_Engine_FileUpload::FOLDER;
            $this->created[] = CTemporary::getPath($folder, $payload['data']['fileId']);
            $this->created[] = CTemporary::getPath($folder . 'info', $payload['data']['fileId']);
        }

        return $payload;
    }

    public function testFileUploadFromBase64StoresTheFileAndReturnsItsId() {
        $payload = $this->upload(CAjax::TYPE_FILE_UPLOAD, ['inputName' => 'berkas'], [
            'berkas' => 'data:text/plain;base64,' . base64_encode('isi berkas'),
            'berkas_filename' => 'catatan.txt',
        ]);

        $this->assertSame(0, $payload['errCode']);
        $fileId = $payload['data']['fileId'];
        $this->assertSame('catatan.txt', $payload['data']['fileName']);
        $this->assertStringEndsWith('f.txt', $fileId, 'akhiran f = berkas biasa');
        $this->assertSame(date('Ymd'), substr($fileId, 0, 8));
        $path = CTemporary::getPath(CAjax_Engine_FileUpload::FOLDER, $fileId);
        $this->assertStringStartsWith('fileupload/' . CF::appCode() . '/', $path, 'disimpan per app');
        $this->assertSame('isi berkas', CTemporary::publicDisk()->get($path));
        $this->assertStringContainsString($fileId, $payload['data']['url']);
        $this->assertFalse((new CAjax_FileAjax($fileId))->haveInfo(), 'tanpa withInfo tidak ada berkas info');
    }

    public function testFileUploadWithInfoWritesAnInfoFileReadableByFileAjaxAndInfo() {
        $payload = $this->upload(CAjax::TYPE_FILE_UPLOAD, ['inputName' => 'berkas', 'withInfo' => true], [
            'berkas' => 'data:text/plain;base64,' . base64_encode('x'),
            'berkas_filename' => 'asli.txt',
        ]);
        $fileId = $payload['data']['fileId'];

        $file = new CAjax_FileAjax($fileId);
        $this->assertTrue($file->haveInfo());
        $info = $file->getInfo();
        $this->assertSame('asli.txt', $info['filename']);
        $this->assertSame($fileId, $info['fileId']);
        $this->assertSame(CAjax_FileAjax::TYPE_FILE, $file->getType());
        $this->assertSame('asli.txt', CAjax_Info::getFileInfo($fileId)['filename']);
        $this->assertSame($info['url'], $file->getUrl());
        $this->assertSame(basename($fileId), $file->getFileName(), 'tanpa setFilename: nama berkas = id');
    }

    public function testFileUploadRejectsBlacklistedAndDisallowedExtensions() {
        $payload = $this->upload(CAjax::TYPE_FILE_UPLOAD, ['inputName' => 'berkas'], [
            'berkas' => 'data:text/plain;base64,' . base64_encode('<?php'),
            'berkas_filename' => 'jahat.php',
        ]);
        $this->assertSame(1, $payload['errCode']);
        $this->assertNotSame('', $payload['errMessage']);
        $this->assertSame('', $payload['data']['fileId'], 'tidak ada berkas yang ditulis');

        $payload = $this->upload(CAjax::TYPE_FILE_UPLOAD, ['inputName' => 'berkas', 'allowedExtension' => ['pdf']], [
            'berkas' => 'data:text/plain;base64,' . base64_encode('x'),
            'berkas_filename' => 'data.csv',
        ]);
        $this->assertSame(1, $payload['errCode']);
        $this->assertStringContainsString('pdf', $payload['errMessage']);

        $payload = $this->upload(CAjax::TYPE_FILE_UPLOAD, ['inputName' => 'berkas', 'allowedExtension' => ['csv']], [
            'berkas' => 'data:text/plain;base64,' . base64_encode('a,b'),
            'berkas_filename' => 'data.CSV',
        ]);
        $this->assertSame(0, $payload['errCode'], 'perbandingan ekstensi tidak peka huruf besar');
    }

    public function testFileUploadWithoutInputReturnsAnEmptySuccess() {
        $payload = $this->upload(CAjax::TYPE_FILE_UPLOAD, ['inputName' => 'berkas'], []);

        $this->assertSame(0, $payload['errCode']);
        $this->assertSame('', $payload['data']['fileId']);
    }

    public function testValidationCallbackCanRejectAnUpload() {
        $payload = $this->upload(CAjax::TYPE_FILE_UPLOAD, [
            'inputName' => 'berkas',
            'validationCallback' => function ($fileName, $data) {
                throw new CAjax_Exception_UploadFailedException('ditolak: ' . $fileName);
            },
        ], [
            'berkas' => 'data:text/plain;base64,' . base64_encode('x'),
            'berkas_filename' => 'apa.txt',
        ]);

        $this->assertSame(1, $payload['errCode']);
        $this->assertSame('ditolak: apa.txt', $payload['errMessage']);
    }

    public function testImgUploadFromBase64StoresAnImageWithTheImageSuffix() {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('butuh ekstensi gd');
        }
        $image = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        $payload = $this->upload(CAjax::TYPE_IMG_UPLOAD, ['inputName' => 'gambar'], [
            'gambar' => 'data:image/png;base64,' . base64_encode($png),
            'gambar_filename' => 'kotak.png',
        ]);

        $this->assertSame(0, $payload['errCode'], $payload['errMessage']);
        $fileId = $payload['data']['fileId'];
        $this->assertStringEndsWith('i.png', $fileId, 'akhiran i = gambar');
        $this->assertSame(CAjax_FileAjax::TYPE_IMAGE, (new CAjax_FileAjax($fileId))->getType());
        $this->assertTrue(CTemporary::publicDisk()->exists(CTemporary::getPath(CAjax_Engine_ImgUpload::FOLDER, $fileId)));
    }

    public function testFileAjaxIdentifierRoundTrip() {
        $file = new CAjax_FileAjax('20260101abcf.txt');

        $this->assertSame('@20260101abcf.txt||', $file->getIdentifier());
        $this->assertSame(['fileId' => '20260101abcf.txt', 'resourceId' => '', 'url' => ''], CAjax_FileAjax::parseIdentifier('@20260101abcf.txt||'));
        $this->assertSame(['fileId' => 'x', 'resourceId' => null, 'url' => null], CAjax_FileAjax::parseIdentifier('@x'), 'identifier pendek tidak lagi memicu undefined offset');

        $parsed = CAjax_FileAjax::create('@20260101abcf.txt||');
        $this->assertInstanceOf(CAjax_FileAjax::class, $parsed);
        $this->assertSame(CAjax_FileAjax::TYPE_FILE, $parsed->getType());
        $this->assertSame('20260101abcf.txt', $parsed->getFileName());
    }

    public function testFileAjaxCreateDispatchesOnTheInputShape() {
        $fromUrl = CAjax_FileAjax::create('https://contoh.test/a/b.pdf');
        $this->assertSame('https://contoh.test/a/b.pdf', $fromUrl->getUrl());
        $this->assertNull($fromUrl->getFileName(), 'dari url saja tidak ada nama berkas');

        $fromJson = CAjax_FileAjax::create(['fileId' => '20260101zzi.png', 'url' => 'https://contoh.test/z.png', 'fileName' => 'z.png']);
        $this->assertSame(CAjax_FileAjax::TYPE_IMAGE, $fromJson->getType());
        $this->assertSame('z.png', $fromJson->getFileName());
        $this->assertSame('https://contoh.test/z.png', $fromJson->getUrl(), 'url eksplisit menang atas url temp');
        $this->assertSame($fromJson->getType(), CAjax_FileAjax::create(json_encode(['fileId' => '20260101zzi.png']))->getType());

        $plain = CAjax_FileAjax::create('20260101zzf.txt');
        $this->assertSame(CAjax_FileAjax::TYPE_FILE, $plain->getType());
        $this->assertStringContainsString('20260101zzf.txt', $plain->getUrl());
    }
}
