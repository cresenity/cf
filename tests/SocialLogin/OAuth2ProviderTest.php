<?php
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Middleware;
use GuzzleHttp\Handler\MockHandler;

/**
 * CSocialLogin OAuth2: URL otorisasi, field token, pemetaan user dari JSON provider — semua lewat
 * Guzzle MockHandler, tanpa jaringan dan tanpa sesi (stateless).
 */
class OAuth2ProviderTest extends TestCase {
    /** @var array */
    protected $history = [];

    /** @var mixed */
    protected $originalSession;

    protected function setUp(): void {
        $property = new ReflectionProperty(CBase::class, 'session');
        $property->setAccessible(true);
        $this->originalSession = $property->getValue();
    }

    protected function tearDown(): void {
        $property = new ReflectionProperty(CBase::class, 'session');
        $property->setAccessible(true);
        $property->setValue(null, $this->originalSession);
    }

    /**
     * Sesi array sebagai CBase::session() (yang dibaca CHTTP_Request::session()).
     *
     * @return CSession_Store
     */
    protected function arraySession() {
        $session = new CSession_Store('uji', new CSession_Handler_ArraySessionHandler(120));
        $session->start();
        $property = new ReflectionProperty(CBase::class, 'session');
        $property->setAccessible(true);
        $property->setValue(null, $session);

        return $session;
    }

    /**
     * @param Response[] $responses
     *
     * @return Client
     */
    protected function mockClient(array $responses) {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new Client(['handler' => $stack]);
    }

    /**
     * @param string $driver
     * @param array  $query
     *
     * @return CSocialLogin_OAuth2_AbstractProvider
     */
    protected function provider($driver, array $query = []) {
        $request = CHTTP_Request::create('/callback', 'GET', $query);
        $config = ['client_id' => 'id-123', 'client_secret' => 'secret-xyz', 'redirect' => 'https://app.uji.test/callback'];
        $manager = (new CSocialLogin_DriverManager())->setConfig($config);
        $provider = $manager->driver($driver);
        $provider->setRequest($request)->stateless();

        return $provider;
    }

    public function testDriverManagerBuildsKnownProviders() {
        $this->assertInstanceOf(CSocialLogin_OAuth2_Provider_GoogleProvider::class, $this->provider('google'));
        $this->assertInstanceOf(CSocialLogin_OAuth2_Provider_GithubProvider::class, $this->provider('github'));
        $this->assertInstanceOf(CSocialLogin_OAuth2_Provider_FacebookProvider::class, $this->provider('facebook'));
        $this->assertInstanceOf(CSocialLogin_OAuth2_Provider_GitlabProvider::class, CSocialLogin::driver('gitlab', ['client_id' => 'a', 'client_secret' => 'b', 'redirect' => '/cb']));
        $this->expectException(InvalidArgumentException::class);
        (new CSocialLogin_DriverManager())->setConfig([])->driver('tidak-ada');
    }

    public function testGoogleRedirectUrlCarriesClientScopesAndRedirect() {
        $response = $this->provider('google')->redirect();
        $this->assertInstanceOf(CHTTP_RedirectResponse::class, $response);
        $url = $response->getTargetUrl();
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/auth?', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('id-123', $query['client_id']);
        $this->assertSame('https://app.uji.test/callback', $query['redirect_uri']);
        $this->assertSame('openid profile email', $query['scope'], 'pemisah scope Google = spasi');
        $this->assertSame('code', $query['response_type']);
        $this->assertArrayNotHasKey('state', $query, 'stateless → tanpa state');
    }

    public function testScopesAndExtraParametersAreAppended() {
        $provider = $this->provider('github')->scopes(['user:email', 'repo'])->with(['allow_signup' => 'false']);
        $this->assertSame(['user:email', 'repo'], array_values($provider->getScopes()), 'scope default github (user:email) + tambahan, unik');
        parse_str(parse_url($provider->redirect()->getTargetUrl(), PHP_URL_QUERY), $query);
        $this->assertSame('user:email,repo', $query['scope'], 'pemisah scope GitHub = koma');
        $this->assertSame('false', $query['allow_signup']);
        $provider->setScopes(['repo']);
        $this->assertSame(['repo'], $provider->getScopes());
        $provider->redirectUrl('https://lain.uji.test/cb');
        parse_str(parse_url($provider->redirect()->getTargetUrl(), PHP_URL_QUERY), $query);
        $this->assertSame('https://lain.uji.test/cb', $query['redirect_uri']);
    }

    public function testPkceAddsChallengeToTheAuthUrl() {
        $provider = $this->provider('google')->enablePKCE();
        $session = $this->arraySession();
        parse_str(parse_url($provider->redirect()->getTargetUrl(), PHP_URL_QUERY), $query);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertNotEmpty($query['code_challenge']);
        $verifier = $session->get('code_verifier');
        $this->assertNotEmpty($verifier, 'verifier disimpan di sesi');
        $this->assertSame(rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='), $query['code_challenge'], 'challenge = base64url(sha256(verifier))');
    }

    public function testUserExchangesTheCodeAndMapsGoogleProfile() {
        $client = $this->mockClient([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['access_token' => 'tok-1', 'refresh_token' => 'ref-1', 'expires_in' => 3600])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['sub' => '10', 'name' => 'Hery', 'email' => 'hery@uji.test', 'picture' => 'https://img/h.png', 'email_verified' => true])),
        ]);
        $provider = $this->provider('google', ['code' => 'kode-abc'])->setHttpClient($client);
        $user = $provider->user();

        $this->assertCount(2, $this->history);
        $tokenRequest = $this->history[0]['request'];
        $this->assertSame('POST', $tokenRequest->getMethod());
        $this->assertSame('https://www.googleapis.com/oauth2/v4/token', (string) $tokenRequest->getUri());
        parse_str((string) $tokenRequest->getBody(), $fields);
        $this->assertSame(['grant_type' => 'authorization_code', 'client_id' => 'id-123', 'client_secret' => 'secret-xyz', 'code' => 'kode-abc', 'redirect_uri' => 'https://app.uji.test/callback'], $fields);
        $userRequest = $this->history[1]['request'];
        $this->assertSame('Bearer tok-1', $userRequest->getHeaderLine('Authorization'));
        $this->assertStringStartsWith('https://www.googleapis.com/oauth2/v3/userinfo', (string) $userRequest->getUri());

        $this->assertInstanceOf(CSocialLogin_OAuth2_User::class, $user);
        $this->assertSame('10', $user->getId());
        $this->assertSame('Hery', $user->getName());
        $this->assertSame('hery@uji.test', $user->getEmail());
        $this->assertSame('https://img/h.png', $user->getAvatar());
        $this->assertSame('tok-1', $user->token);
        $this->assertSame('ref-1', $user->refreshToken);
        $this->assertSame(3600, $user->expiresIn);
        $this->assertTrue($user->getRaw()['verified_email'], 'alias kompatibilitas diisi dari email_verified');
        $this->assertSame('hery@uji.test', $user['email'], 'ArrayAccess ke atribut');
        $this->assertSame($user, $provider->user(), 'dipanggil dua kali tidak menembak ulang');
        $this->assertCount(2, $this->history);
    }

    public function testGithubUserFromTokenFetchesPrimaryVerifiedEmail() {
        $client = $this->mockClient([
            new Response(200, [], json_encode(['id' => 77, 'login' => 'hery', 'name' => 'Hery S', 'avatar_url' => 'https://gh/a.png', 'email' => null])),
            new Response(200, [], json_encode([
                ['email' => 'lama@uji.test', 'primary' => false, 'verified' => true],
                ['email' => 'utama@uji.test', 'primary' => true, 'verified' => true],
            ])),
        ]);
        $user = $this->provider('github')->scopes('user:email')->setHttpClient($client)->userFromToken('gh-token');
        $this->assertSame(77, $user->getId());
        $this->assertSame('hery', $user->getNickname());
        $this->assertSame('utama@uji.test', $user->getEmail());
        $this->assertSame('gh-token', $user->token);
        $this->assertSame('token gh-token', $this->history[0]['request']->getHeaderLine('Authorization'));
        $this->assertSame('https://api.github.com/user/emails', (string) $this->history[1]['request']->getUri());
    }

    public function testGithubWithoutEmailScopeSkipsTheEmailCall() {
        $client = $this->mockClient([
            new Response(200, [], json_encode(['id' => 1, 'login' => 'x', 'avatar_url' => 'a', 'email' => 'publik@uji.test'])),
        ]);
        $user = $this->provider('github')->setScopes(['read:user'])->setHttpClient($client)->userFromToken('t');
        $this->assertSame('publik@uji.test', $user->getEmail());
        $this->assertCount(1, $this->history);
    }

    public function testStatefulProviderRejectsMismatchedState() {
        $session = $this->arraySession();
        $session->put('state', 'asli');
        $request = CHTTP_Request::create('/callback', 'GET', ['code' => 'x', 'state' => 'palsu']);
        $provider = (new CSocialLogin_DriverManager())->setConfig(['client_id' => 'a', 'client_secret' => 'b', 'redirect' => '/cb'])->driver('google');
        $provider->setRequest($request);
        $this->expectException(CSocialLogin_Exception_InvalidStateException::class);
        $provider->user();
    }

    public function testStatefulRedirectStoresAFortyCharState() {
        $session = $this->arraySession();
        $request = CHTTP_Request::create('/login', 'GET');
        $provider = (new CSocialLogin_DriverManager())->setConfig(['client_id' => 'a', 'client_secret' => 'b', 'redirect' => '/cb'])->driver('facebook');
        $provider->setRequest($request);
        parse_str(parse_url($provider->redirect()->getTargetUrl(), PHP_URL_QUERY), $query);
        $this->assertSame(40, strlen($query['state']));
        $this->assertSame($query['state'], $session->get('state'));
        $this->assertStringStartsWith('https://www.facebook.com/', $provider->redirect()->getTargetUrl());
    }

    public function testUserMapAndRaw() {
        $user = (new CSocialLogin_OAuth2_User())->setRaw(['a' => 1])->map(['id' => 5, 'name' => 'N', 'nickname' => 'n', 'email' => 'e', 'avatar' => 'v']);
        $this->assertSame(['a' => 1], $user->getRaw());
        $this->assertSame(5, $user->getId());
        $this->assertSame('n', $user->getNickname());
        $this->assertTrue(isset($user['a']));
        $user['b'] = 2;
        $this->assertSame(2, $user->getRaw()['b']);
        unset($user['a']);
        $this->assertFalse(isset($user['a']));
        $this->assertSame('x', (new CSocialLogin_OAuth2_User())->setToken('x')->token);
    }

    public function testConfigObject() {
        $config = new CSocialLogin_Config('k', 's', 'https://cb', ['tenant' => 'x']);
        $this->assertSame(['client_id' => 'k', 'client_secret' => 's', 'redirect' => 'https://cb', 'tenant' => 'x'], $config->get());
        $spoofed = (new CSocialLogin_ConfigRetriever())->fromServices('layanan-tidak-ada-' . uniqid());
        $this->assertStringEndsWith('_KEY', $spoofed->get()['client_id'], 'di CLI, layanan yang tidak dikonfigurasi dipalsukan (bukan exception) — perilaku CF');
        $this->assertSame([], $spoofed->get()['guzzle']);
    }
}
