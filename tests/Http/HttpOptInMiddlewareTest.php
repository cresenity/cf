<?php

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Middleware baru yang opt-in (FrameGuard, PrefersJsonResponses, ValidatePathEncoding, SetCacheHeaders),
 * CookieValuePrefix::validate, TrimStrings::except()/flushState(), dan opsi opt-in VerifyCsrfToken.
 */
class HttpOptInCsrfForTest extends CHTTP_Middleware_VerifyCsrfToken {
    protected function runningUnitTests() {
        return false;
    }
}

class HttpOptInMiddlewareTest extends TestCase {
    protected function tearDown(): void {
        CHTTP_Middleware_VerifyCsrfToken::flushState();
        CHTTP_Middleware_TrimStrings::flushState();
        CHTTP_Middleware_ConvertEmptyStringsToNull::flushState();
        CBase::session()->forget('_token');
        parent::tearDown();
    }

    protected function pass($middleware, CHTTP_Request $request, array $arguments = [], $response = null) {
        return $middleware->handle($request, function () use ($response) {
            return $response ?: new CHTTP_Response('ok');
        }, ...$arguments);
    }

    protected function get($uri = 'https://contoh.test/x', array $server = []) {
        return CHTTP_Request::create($uri, 'GET', [], [], [], $server);
    }

    public function testFrameGuardAddsSameOriginWithoutReplacingAnExistingHeader() {
        $response = $this->pass(new CHTTP_Middleware_FrameGuard(), $this->get());

        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
    }

    public function testPrefersJsonTurnsBroadAcceptHeadersIntoJson() {
        foreach ([null, '', '*/*', 'application/*', '*/*;q=0.8, application/*'] as $accept) {
            $request = $this->get('https://contoh.test/x', $accept === null ? [] : ['HTTP_ACCEPT' => $accept]);
            if ($accept === null) {
                $request->headers->remove('Accept');
            }

            $this->pass(new CHTTP_Middleware_PrefersJsonResponses(), $request);

            $this->assertSame('application/json', $request->headers->get('Accept'), var_export($accept, true));
        }
    }

    public function testPrefersJsonKeepsSpecificAcceptHeadersAndRemembersTheOriginal() {
        $html = $this->get('https://contoh.test/x', ['HTTP_ACCEPT' => 'text/html, */*;q=0.1']);
        $this->pass(new CHTTP_Middleware_PrefersJsonResponses(), $html);
        $this->assertSame('text/html, */*;q=0.1', $html->headers->get('Accept'));

        $broad = $this->get('https://contoh.test/x', ['HTTP_ACCEPT' => '*/*']);
        $this->pass(new CHTTP_Middleware_PrefersJsonResponses(), $broad);
        $this->assertSame('*/*', $broad->headers->get('X-Original-Accept'));
    }

    public function testValidatePathEncodingRejectsInvalidUtf8Paths() {
        $this->assertSame('ok', $this->pass(new CHTTP_Middleware_ValidatePathEncoding(), $this->get('https://contoh.test/halaman%20baik'))->getContent());
        $this->assertSame('ok', $this->pass(new CHTTP_Middleware_ValidatePathEncoding(), $this->get('https://contoh.test/caf%C3%A9'))->getContent());

        try {
            $this->pass(new CHTTP_Middleware_ValidatePathEncoding(), $this->get('https://contoh.test/rusak%FF%FE'));
            $this->fail('seharusnya ditolak');
        } catch (CHTTP_Exception_MalformedUrlException $e) {
            $this->assertSame(400, $e->getStatusCode());
            $this->assertSame('Malformed URL.', $e->getMessage());
        }
    }

    public function testSetCacheHeadersAppliesTheOptionsToACacheableSuccessfulResponse() {
        $response = $this->pass(new CHTTP_Middleware_SetCacheHeaders(), $this->get(), [['max_age' => 300, 'public' => true]]);

        $this->assertStringContainsString('max-age=300', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
    }

    public function testSetCacheHeadersParsesTheStringFormAndGeneratesAnEtag() {
        $response = $this->pass(new CHTTP_Middleware_SetCacheHeaders(), $this->get(), ['max_age=60;s_maxage=120;etag;public']);

        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('max-age=60', $cacheControl);
        $this->assertStringContainsString('s-maxage=120', $cacheControl);
        $this->assertSame('"' . md5('ok') . '"', $response->getEtag());
    }

    public function testSetCacheHeadersAnswers304WhenTheEtagMatches() {
        $request = $this->get('https://contoh.test/x', ['HTTP_IF_NONE_MATCH' => '"' . md5('ok') . '"']);

        $response = $this->pass(new CHTTP_Middleware_SetCacheHeaders(), $request, [['etag' => true]]);

        $this->assertSame(304, $response->getStatusCode());
    }

    public function testSetCacheHeadersLeavesNonCacheableRequestsAndFailedResponsesAlone() {
        $post = CHTTP_Request::create('/x', 'POST');
        $this->assertStringNotContainsString('max-age=300', (string) $this->pass(new CHTTP_Middleware_SetCacheHeaders(), $post, [['max_age' => 300]])->headers->get('Cache-Control'));

        $failed = $this->pass(new CHTTP_Middleware_SetCacheHeaders(), $this->get(), [['max_age' => 300]], new CHTTP_Response('gagal', 500));
        $this->assertStringNotContainsString('max-age=300', (string) $failed->headers->get('Cache-Control'));
    }

    public function testSetCacheHeadersUsingBuildsTheMiddlewareString() {
        $this->assertSame(
            CHTTP_Middleware_SetCacheHeaders::class . ':max_age=300;etag;public',
            CHTTP_Middleware_SetCacheHeaders::using(['max_age' => 300, 'etag' => true, 'public' => true, 'private' => false])
        );
        $this->assertSame(CHTTP_Middleware_SetCacheHeaders::class . ':max_age=60', CHTTP_Middleware_SetCacheHeaders::using('max_age=60'));
    }

    public function testCookieValuePrefixValidateAcceptsAnyOfTheKeys() {
        $value = CHTTP_Cookie_CookieValuePrefix::create('sesi', 'kunci-lama') . 'isi-cookie';

        $this->assertSame('isi-cookie', CHTTP_Cookie_CookieValuePrefix::validate('sesi', $value, ['kunci-baru', 'kunci-lama']));
        $this->assertNull(CHTTP_Cookie_CookieValuePrefix::validate('sesi', $value, ['kunci-baru']));
        $this->assertNull(CHTTP_Cookie_CookieValuePrefix::validate('lain', $value, ['kunci-lama']), 'terikat nama cookie');
        $this->assertNull(CHTTP_Cookie_CookieValuePrefix::validate('sesi', $value, []));
    }

    public function testTrimStringsExceptAndWildcardAndFlushState() {
        CHTTP_Middleware_TrimStrings::except(['password', 'rahasia.*']);
        CHTTP_Middleware_TrimStrings::except('token');
        $request = CHTTP_Request::create('/x', 'POST', ['password' => ' a ', 'token' => ' b ', 'rahasia' => ['satu' => ' c '], 'nama' => ' d ']);

        $this->pass(new CHTTP_Middleware_TrimStrings(), $request);

        $this->assertSame(' a ', $request->request->get('password'));
        $this->assertSame(' b ', $request->request->get('token'));
        $this->assertSame(' c ', $request->request->get('rahasia')['satu'], 'wildcard pada kunci bertitik');
        $this->assertSame('d', $request->request->get('nama'));

        CHTTP_Middleware_TrimStrings::flushState();
        $again = CHTTP_Request::create('/x', 'POST', ['password' => ' a ']);
        $this->pass(new CHTTP_Middleware_TrimStrings(), $again);
        $this->assertSame('a', $again->request->get('password'), 'flushState() mengosongkan daftar global');
    }

    public function testFlushStateClearsSkipCallbacks() {
        CHTTP_Middleware_ConvertEmptyStringsToNull::skipWhen(function () {
            return true;
        });
        CHTTP_Middleware_ConvertEmptyStringsToNull::flushState();

        $request = CHTTP_Request::create('/x', 'POST', ['a' => '']);
        $this->pass(new CHTTP_Middleware_ConvertEmptyStringsToNull(), $request);

        $this->assertNull($request->request->get('a'));
    }

    protected function post(array $server = [], $uri = '/form', array $data = []) {
        return CHTTP_Request::create($uri, 'POST', $data, [], [], $server);
    }

    public function testCsrfDefaultsIgnoreSecFetchSite() {
        CBase::session()->put('_token', 'token-uji');

        $this->expectException(CSession_Exception_TokenMismatchException::class);

        $this->pass(new HttpOptInCsrfForTest(), $this->post(['HTTP_SEC_FETCH_SITE' => 'same-origin']));
    }

    public function testCsrfTrustSecFetchSiteLetsSameOriginRequestsThroughOnly() {
        CBase::session()->put('_token', 'token-uji');
        CHTTP_Middleware_VerifyCsrfToken::trustSecFetchSite();

        $this->assertSame('ok', $this->pass(new HttpOptInCsrfForTest(), $this->post(['HTTP_SEC_FETCH_SITE' => 'same-origin']))->getContent());

        $this->expectException(CSession_Exception_TokenMismatchException::class);
        $this->pass(new HttpOptInCsrfForTest(), $this->post(['HTTP_SEC_FETCH_SITE' => 'cross-site']));
    }

    public function testCsrfAllowSameSiteIsAnExtraOptIn() {
        CBase::session()->put('_token', 'token-uji');
        CHTTP_Middleware_VerifyCsrfToken::trustSecFetchSite();
        CHTTP_Middleware_VerifyCsrfToken::allowSameSite();

        $this->assertSame('ok', $this->pass(new HttpOptInCsrfForTest(), $this->post(['HTTP_SEC_FETCH_SITE' => 'same-site']))->getContent());
    }

    public function testCsrfOriginOnlyRejectsCrossOriginWithoutATokenAndSendsNoXsrfCookie() {
        CBase::session()->put('_token', 'token-uji');
        CHTTP_Middleware_VerifyCsrfToken::useOriginOnly();

        $ok = $this->pass(new HttpOptInCsrfForTest(), $this->post(['HTTP_SEC_FETCH_SITE' => 'same-origin']));
        $this->assertSame('ok', $ok->getContent());
        $this->assertSame([], $ok->headers->getCookies(), 'mode origin-only tidak mengirim cookie XSRF-TOKEN');

        $this->expectException(CHTTP_Exception_OriginMismatchException::class);
        $this->pass(new HttpOptInCsrfForTest(), $this->post(['HTTP_SEC_FETCH_SITE' => 'cross-site', 'HTTP_X_CSRF_TOKEN' => 'token-uji']));
    }

    public function testOriginMismatchIsRenderedLikeATokenMismatch() {
        $this->assertInstanceOf(CSession_Exception_TokenMismatchException::class, new CHTTP_Exception_OriginMismatchException());
    }

    public function testCsrfStaticExceptSkipsTheCheckForEveryInstance() {
        CBase::session()->put('_token', 'token-uji');
        CHTTP_Middleware_VerifyCsrfToken::except(['webhook/*']);

        $this->assertSame('ok', $this->pass(new HttpOptInCsrfForTest(), $this->post([], '/webhook/xendit'))->getContent());
    }

    public function testCsrfFlushStateRestoresTheDefaults() {
        CBase::session()->put('_token', 'token-uji');
        CHTTP_Middleware_VerifyCsrfToken::trustSecFetchSite();
        CHTTP_Middleware_VerifyCsrfToken::except('webhook/*');

        CHTTP_Middleware_VerifyCsrfToken::flushState();

        $this->expectException(CSession_Exception_TokenMismatchException::class);
        $this->pass(new HttpOptInCsrfForTest(), $this->post(['HTTP_SEC_FETCH_SITE' => 'same-origin'], '/webhook/x'));
    }
}
