<?php

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * RobotsTxt, Sitemap, dan exception HTTP: tes karakterisasi perilaku saat ini (sebelumnya 0 tes).
 */
class HttpRobotsSitemapExceptionTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        CHTTP_RobotsTxt::instance()->reset();
    }

    protected function tearDown(): void {
        CHTTP_RobotsTxt::instance()->reset();
        $property = new ReflectionProperty(CHTTP_RobotsTxt::class, 'shouldIndex');
        $property->setAccessible(true);
        $property->setValue(null, true);
        parent::tearDown();
    }

    public function testRobotsTxtGeneratesTheLinesInTheOrderTheyWereAdded() {
        $robots = CHTTP_RobotsTxt::instance();

        $robots->addComment('uji')->addUserAgent('*')->addDisallow(['/admin', '/rahasia'])->addAllow('/publik')->addSpacer()->addHost('contoh.test')->addSitemap('https://contoh.test/sitemap.xml');

        $this->assertSame(
            implode(PHP_EOL, ['# uji', 'User-agent: *', 'Disallow: /admin', 'Disallow: /rahasia', 'Allow: /publik', '', 'Host: contoh.test', 'Sitemap: https://contoh.test/sitemap.xml']),
            $robots->generate()
        );
    }

    public function testRobotsTxtResetEmptiesTheLinesAndTheInstanceIsShared() {
        CHTTP_RobotsTxt::instance()->addDisallow('/x');
        $this->assertNotSame('', CHTTP_RobotsTxt::instance()->generate());

        CHTTP_RobotsTxt::instance()->reset();

        $this->assertSame('', CHTTP_RobotsTxt::instance()->generate());
        $this->assertSame(CHTTP_RobotsTxt::instance(), CHTTP_RobotsTxt::instance());
    }

    public function testRobotsTxtResponseIsPlainText() {
        $response = CHTTP_RobotsTxt::instance()->addUserAgent('*')->addDisallow('/')->toResponse();

        $this->assertSame("User-agent: *\nDisallow: /", str_replace(PHP_EOL, "\n", $response->getContent()));
        $this->assertStringContainsString('text/plain', $response->headers->get('Content-Type'));
    }

    public function testRobotsMetaTagFollowsTheShouldIndexCallback() {
        $robots = CHTTP_RobotsTxt::instance();

        $this->assertSame('<meta name="robots" content="index, follow">', $robots->metaTag());

        $robots->setShouldIndexCallback(function () {
            return false;
        });
        $this->assertFalse($robots->shouldIndex());
        $this->assertSame('<meta name="robots" content="noindex, nofollow">', $robots->metaTag());
    }

    public function testSitemapRendersUrlsAndEscapesXmlSpecialCharacters() {
        $sitemap = CHTTP_Sitemap::create();
        $sitemap->addUrl('https://contoh.test/a');
        $sitemap->addUrl('https://contoh.test/cari?x=1&y=<2>');

        $xml = $sitemap->render();

        $this->assertStringContainsString('<loc>https://contoh.test/a</loc>', $xml);
        $this->assertStringNotContainsString('&y=<2>', $xml, 'karakter khusus XML tidak boleh mentah');
        $this->assertNotFalse(simplexml_load_string($xml), 'hasil render adalah XML yang valid');
    }

    public function testSitemapFindsAndDeduplicatesUrls() {
        $sitemap = CHTTP_Sitemap::create();
        $sitemap->addUrl('https://contoh.test/a');
        $sitemap->addUrl('https://contoh.test/a');
        $sitemap->addUrl('https://contoh.test/b');

        $this->assertTrue($sitemap->hasUrl('https://contoh.test/b'));
        $this->assertFalse($sitemap->hasUrl('https://contoh.test/c'));
        $this->assertSame(1, substr_count($sitemap->render(), '<loc>https://contoh.test/a</loc>'), 'URL kembar hanya dirender sekali');
    }

    public function testSitemapResponseIsXml() {
        $response = CHTTP_Sitemap::create()->toResponse();

        $this->assertStringContainsString('xml', $response->headers->get('Content-Type'));
    }

    public function testHttpExceptionsCarryTheirStatusCodes() {
        $this->assertSame(413, (new CHTTP_Exception_PostTooLargeException())->getStatusCode());
        $this->assertSame(404, (new CHTTP_Exception_NotFoundHttpException())->getStatusCode());
        $this->assertSame(410, (new CHTTP_Exception_GoneHttpException())->getStatusCode());
        $this->assertSame(307, (new CHTTP_Exception_RedirectHttpException())->getStatusCode());
        $this->assertSame(500, (new CHTTP_Exception_HttpException(500))->getStatusCode());
    }

    public function testThrottleRequestExceptionIsA429WithItsHeaders() {
        $e = new CHTTP_Exception_ThrottleRequestException('Terlalu sering', null, ['Retry-After' => 30]);

        $this->assertSame(429, $e->getStatusCode());
        $this->assertSame('Terlalu sering', $e->getMessage());
        $this->assertSame(30, $e->getHeaders()['Retry-After']);
    }

    public function testRedirectHttpExceptionKeepsItsUri() {
        $e = new CHTTP_Exception_RedirectHttpException();
        $e->setUri('https://contoh.test/baru');

        $this->assertSame('https://contoh.test/baru', $e->getUri());
    }

    public function testResponseExceptionWrapsAResponseButHasNoMessage() {
        $response = new CHTTP_Response('isi', 418);
        $e = new CHTTP_Exception_ResponseException($response);

        $this->assertSame($response, $e->getResponse());
        $this->assertSame('', $e->getMessage(), 'pesan kosong sehingga sulit dibaca di log');
    }

    public function test411ExceptionIsAnHttpException() {
        $this->assertInstanceOf(CHTTP_Exception_HttpException::class, new CHTTP_Exception_411(411));
    }
}
