<?php
use PHPUnit\Framework\TestCase;

/**
 * Driver s3 CStorage terhadap bucket dev MinIO (prefix cf/) — skip bila kredensial tidak ada.
 * Kredensial: S3_KEY/S3_SECRET/S3_ENDPOINT/S3_BUCKET(/S3_REGION) dari env, atau disk `s3` di config.
 */
class StorageS3Test extends TestCase {
    /** @var CStorage_Adapter_AwsS3V3Adapter */
    protected $disk;

    /** @var string */
    protected $prefix;

    protected function setUp(): void {
        $config = CF::config('storage.disks.s3', []);
        $key = getenv('S3_KEY') ?: carr::get($config, 'key');
        $secret = getenv('S3_SECRET') ?: carr::get($config, 'secret');
        $endpoint = getenv('S3_ENDPOINT') ?: carr::get($config, 'endpoint');
        $bucket = getenv('S3_BUCKET') ?: carr::get($config, 'bucket');
        if (!$key || !$secret || !$bucket || !$endpoint) {
            $this->markTestSkipped('kredensial S3/MinIO tidak tersedia (S3_KEY/S3_SECRET/S3_ENDPOINT/S3_BUCKET)');
        }
        if (!class_exists(Aws\S3\S3Client::class)) {
            $this->markTestSkipped('AWS SDK tidak tersedia');
        }
        $this->disk = CStorage::instance()->build([
            'driver' => 's3',
            'key' => $key,
            'secret' => $secret,
            'region' => getenv('S3_REGION') ?: carr::get($config, 'region', 'us-east-1'),
            'bucket' => $bucket,
            'endpoint' => $endpoint,
            'use_path_style_endpoint' => true,
            'throw' => true,
        ]);
        $this->prefix = 'cf/uji-' . uniqid() . '/';
    }

    protected function tearDown(): void {
        if ($this->disk) {
            $this->disk->deleteDirectory(rtrim($this->prefix, '/'));
        }
    }

    public function testPutGetExistsDelete() {
        $this->assertTrue($this->disk->put($this->prefix . 'a.txt', 'halo minio'));
        $this->assertTrue($this->disk->exists($this->prefix . 'a.txt'));
        $this->assertSame('halo minio', $this->disk->get($this->prefix . 'a.txt'));
        $this->assertSame(10, $this->disk->size($this->prefix . 'a.txt'));
        $this->assertTrue($this->disk->delete($this->prefix . 'a.txt'));
        $this->assertFalse($this->disk->exists($this->prefix . 'a.txt'));
    }

    public function testListingCopyMoveAndStream() {
        $this->disk->put($this->prefix . 'd/1.txt', '1');
        $this->disk->put($this->prefix . 'd/2.txt', '2');
        $files = array_map('basename', $this->disk->files($this->prefix . 'd'));
        sort($files);
        $this->assertSame(['1.txt', '2.txt'], $files);
        $this->assertTrue($this->disk->copy($this->prefix . 'd/1.txt', $this->prefix . 'e/1.txt'));
        $this->assertTrue($this->disk->move($this->prefix . 'd/2.txt', $this->prefix . 'e/2.txt'));
        $this->assertFalse($this->disk->exists($this->prefix . 'd/2.txt'));
        $this->assertContains($this->prefix . 'e', $this->disk->directories($this->prefix));
        $stream = $this->disk->readStream($this->prefix . 'e/1.txt');
        $this->assertSame('1', stream_get_contents($stream));
        fclose($stream);
    }

    public function testUrlAndTemporaryUrl() {
        $this->disk->put($this->prefix . 'u.txt', 'u');
        $url = $this->disk->url($this->prefix . 'u.txt');
        $this->assertStringContainsString($this->prefix . 'u.txt', $url);
        $temporary = $this->disk->temporaryUrl($this->prefix . 'u.txt', CCarbon::now()->addMinutes(5));
        $this->assertStringContainsString('X-Amz-Signature', $temporary);
        $this->assertSame('u', file_get_contents($temporary), 'URL sementara bisa diunduh tanpa kredensial');
    }

    public function testVisibilityRoundTrip() {
        $this->disk->put($this->prefix . 'v.txt', 'v', 'public');
        $this->assertSame('public', $this->disk->getVisibility($this->prefix . 'v.txt'));
        $this->disk->setVisibility($this->prefix . 'v.txt', 'private');
        $this->assertSame('private', $this->disk->getVisibility($this->prefix . 'v.txt'));
    }
}
