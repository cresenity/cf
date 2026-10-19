<?php

use PHPUnit\Framework\TestCase;

/**
 * CHTTP_Response dengan konten array yang gagal di-json_encode (#-21064).
 */
class HttpResponseJsonEncodeFailureTest extends TestCase {
    public function testAFailedEncodingAnswersWithStatus500() {
        $response = new CHTTP_Response(['value' => INF]);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Failed to encode response content', json_decode($response->getContent(), true)['error']);
    }

    public function testSetContentOnAnExistingResponseAlsoSets500() {
        $response = new CHTTP_Response(['ok' => true], 201);
        $this->assertSame(201, $response->getStatusCode());

        $response->setContent(['value' => NAN]);
        $this->assertSame(500, $response->getStatusCode());
    }

    public function testEncodableContentKeepsItsStatus() {
        $response = new CHTTP_Response(['ok' => true, 'text' => "bad \xB1 utf8"], 202);

        $this->assertSame(202, $response->getStatusCode());
        $this->assertTrue(json_decode($response->getContent(), true)['ok']);
    }
}
