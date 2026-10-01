<?php

use PHPUnit\Framework\TestCase;

/**
 * URL resource untuk disk S3: `root` disk cukup muncul sekali (adapter sudah memprefiks).
 * Disk sekali pakai dengan endpoint palsu; URL dibentuk tanpa koneksi jaringan.
 */
class ResourceS3UrlGeneratorTest extends TestCase {
    /**
     * @var string
     */
    private $disk;

    protected function tearDown(): void {
        CConfig::repository()->set('storage.disks.' . $this->disk, null);
    }

    /**
     * @param null|string $root
     *
     * @return CApp_Model_Resource
     */
    private function makeResource($root) {
        $config = [
            'driver' => 's3',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'region' => 'us-east-1',
            'bucket' => 'test-bucket',
            'endpoint' => 'https://s3.example.test',
            'use_path_style_endpoint' => true,
        ];
        if ($root !== null) {
            $config['root'] = $root;
        }
        // nama unik: CStorage menyimpan disk yang sudah dibangun per nama
        $this->disk = 'cf-test-s3-' . uniqid();
        CConfig::repository()->set('storage.disks.' . $this->disk, $config);

        $resource = new CApp_Model_Resource();
        $resource->resource_id = 77;
        $resource->model_type = 'TestModel';
        $resource->file_name = 'foto.png';
        $resource->disk = $this->disk;
        $resource->version = 2;
        $resource->created = '2026-10-01 10:00:00';

        return $resource;
    }

    /**
     * @param CApp_Model_Resource $resource
     *
     * @return CResources_UrlGenerator_S3UrlGenerator
     */
    private function generator($resource) {
        $generator = new CResources_UrlGenerator_S3UrlGenerator();
        $generator->setResource($resource);
        $generator->setPathGenerator(new CResources_PathGenerator());

        return $generator;
    }

    public function testRootAppearsOnlyOnceInUrl() {
        $generator = $this->generator($this->makeResource('myroot/sub'));
        $url = $generator->getUrl();

        $this->assertSame(1, substr_count($url, 'myroot/sub'), $url);
        $this->assertSame('https://s3.example.test/test-bucket/myroot/sub/' . $generator->getPath(), $url);
    }

    public function testUrlWithoutRootHasNoRootSegment() {
        $generator = $this->generator($this->makeResource(null));

        $this->assertSame('https://s3.example.test/test-bucket/' . $generator->getPath(), $generator->getUrl());
    }

    public function testTemporaryUrlAndPlainUrlAgreeOnTheObjectKey() {
        $generator = $this->generator($this->makeResource('myroot'));
        $plain = $generator->getUrl();
        $temporary = $generator->getTemporaryUrl(new DateTimeImmutable('+5 minutes'));

        $this->assertStringStartsWith($plain . '?', $temporary);
    }
}
