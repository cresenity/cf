<?php
use PHPUnit\Framework\TestCase;

/**
 * curl (pembangun URL) dan crequest (pembaca request) - bagian yang murni dan yang bisa
 * dikendalikan lewat $_SERVER di CLI.
 */
class curlAndCrequestTest extends TestCase {
    /** @var array */
    protected $server;

    protected function setUp(): void {
        $this->server = $_SERVER;
    }

    protected function tearDown(): void {
        $_SERVER = $this->server;
    }

    public function testBaseAlwaysEndsWithASlashAndSiteBuildsOnIt() {
        $base = curl::base(false);
        $this->assertStringEndsWith('/', $base);
        $this->assertSame($base, curl::base(false), 'deterministik');
        $this->assertSame(curl::base(true) . 'user/edit/1', curl::site('user/edit/1'));
        $this->assertSame(curl::base(true) . 'user/edit/1?x=1&y=2#frag', curl::site('/user/edit/1/?x=1&y=2#frag'), 'slash di ujung path dibuang, query & fragment ikut');
        $this->assertSame(curl::base(true), curl::site(''));
        $this->assertSame(curl::base(true), curl::site('/'));
    }

    public function testBaseWithExplicitProtocolUsesTheDomain() {
        $siteDomain = (string) CF::config('core.site_domain', '');
        $https = curl::base(false, 'https');
        $this->assertStringStartsWith('https://', $https);
        if ($siteDomain === '' || $siteDomain[0] === '/') {
            $_SERVER['HTTP_HOST'] = 'uji.example.test';
            $this->assertSame('https://uji.example.test' . rtrim($siteDomain, '/') . '/', curl::base(false, 'https'), 'HTTP_HOST dipakai bila site_domain kosong/relatif');
        }
    }

    public function testFileKeepsAbsoluteUrlsAndPrefixesRelativeOnes() {
        $this->assertSame('https://cdn.example/a.js', curl::file('https://cdn.example/a.js'));
        $this->assertSame(curl::base(false) . 'media/a.js', curl::file('media/a.js'));
    }

    public function testTitleMakesASlug() {
        $this->assertSame('hello-world', curl::title('Hello World!'));
        $this->assertSame('hello_world', curl::title('Hello World!', '_'));
        $this->assertSame('cafe-au-lait', curl::title('Café au lait'));
        $this->assertSame('a-b-c', curl::title('  a -- b   c  '));
    }

    public function testTitleSeparatorRule() {
        $this->assertSame('a_b', curl::title('a b', '*'), 'apa pun selain "-" diperlakukan sebagai "_"');
    }

    public function testAsPostString() {
        $this->assertSame('a=1&b=x+y', curl::asPostString(['a' => 1, 'b' => 'x y']));
        $this->assertSame('a%5Bb%5D=1&a%5Bc%5D%5B0%5D=2', curl::asPostString(['a' => ['b' => 1, 'c' => [2]]]), 'kunci bersarang a[b], a[c][0]');
        $this->assertSame('file=@/tmp/x.png', curl::asPostString(['file' => '@/tmp/x.png']), 'nilai berawalan @ (unggah curl lama) tidak di-encode');
        $this->assertSame('k=v', curl::asPostString('v', 'k'));
        $this->assertSame(curl::asPostString(['a' => 1]), curl::as_post_string(['a' => 1]));
    }

    public function testRemoveScheme() {
        $this->assertSame('example.com/path?x=1', curl::removeScheme('https://example.com/path?x=1'));
        $this->assertSame('example.com', curl::removeScheme('http://example.com'));
        $this->assertSame('//example.com', curl::removeScheme('//example.com'), 'tanpa skema, // dibiarkan');
        $this->assertSame('example.com/a', curl::removeScheme('example.com/a'));
        $this->assertSame(curl::removeScheme('ftp://x'), curl::remove_scheme('ftp://x'));
    }

    public function testProtocolIsNullInCli() {
        $this->assertNull(curl::protocol());
        $this->assertNull(crequest::protocol());
        $this->assertFalse(crequest::isHttps());
        $this->assertFalse(crequest::is_https());
    }

    public function testRequestMethodDefaultsToGet() {
        unset($_SERVER['REQUEST_METHOD']);
        $this->assertSame('get', crequest::method());
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->assertSame('post', crequest::method());
    }

    public function testIsAjaxReadsTheHeader() {
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        $this->assertFalse(crequest::is_ajax());
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $this->assertTrue(crequest::is_ajax());
    }

    public function testReferrerStripsTheBaseUrl() {
        unset($_SERVER['HTTP_REFERER']);
        $this->assertFalse(crequest::referrer());
        $this->assertSame('dflt', crequest::referrer('dflt'));
        $_SERVER['HTTP_REFERER'] = curl::base(false) . 'user/list';
        $this->assertSame('user/list', crequest::referrer());
        $_SERVER['HTTP_REFERER'] = 'https://lain.example/x';
        $this->assertSame('https://lain.example/x', crequest::referrer(), 'referrer luar dibiarkan utuh');
    }

    public function testCurrentContainerIdComesFromQueryString() {
        $get = $_GET;
        $_GET['capp_current_container_id'] = 'cont-1';
        $this->assertSame('cont-1', crequest::current_container_id());
        unset($_GET['capp_current_container_id']);
        $this->assertNull(crequest::current_container_id());
        $_GET = $get;
    }

    public function testPlatformVersionIsAlwaysEmpty() {
        $this->assertSame('', crequest::platform_version());
    }
}
