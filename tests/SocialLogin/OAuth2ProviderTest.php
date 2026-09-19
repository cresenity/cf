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
        $this->assertSame(['user:email', 'repo'], $provider->getScopes(), 'scope default github (user:email) + tambahan, unik, indeks dirapikan');
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
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['access_token' => 'tok-1', 'refresh_token' => 'ref-1', 'expires_in' => 3600, 'scope' => 'openid email'])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['sub' => '10', 'name' => 'Hery', 'email' => 'hery@uji.test', 'picture' => 'https://img/h.png', 'email_verified' => true])),
        ]);
        $provider = $this->provider('google', ['code' => 'kode-abc'])->with(['audience' => 'api-x'])->setHttpClient($client);
        $user = $provider->user();

        $this->assertCount(2, $this->history);
        $tokenRequest = $this->history[0]['request'];
        $this->assertSame('POST', $tokenRequest->getMethod());
        $this->assertSame('https://www.googleapis.com/oauth2/v4/token', (string) $tokenRequest->getUri());
        parse_str((string) $tokenRequest->getBody(), $fields);
        $this->assertSame(['grant_type' => 'authorization_code', 'client_id' => 'id-123', 'client_secret' => 'secret-xyz', 'code' => 'kode-abc', 'redirect_uri' => 'https://app.uji.test/callback', 'audience' => 'api-x'], $fields, 'parameter with() ikut ke endpoint token');
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
        $this->assertSame(['openid', 'email'], $user->approvedScopes, 'scope yang disetujui dipecah dengan pemisah provider');
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

    /**
     * Pasangan kunci RS256 uji + JWKS publiknya.
     *
     * @return array [privateKeyPem, jwks]
     */
    protected function rsaKeyPair($kid) {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($resource, $privateKey);
        $details = openssl_pkey_get_details($resource);
        $b64 = function ($bin) {
            return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
        };
        $jwks = ['keys' => [['kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'kid' => $kid, 'n' => $b64($details['rsa']['n']), 'e' => $b64($details['rsa']['e'])]]];

        return [$privateKey, $jwks];
    }

    public function testRefreshTokenPostsTheRefreshGrant() {
        $client = $this->mockClient([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['access_token' => 'tok-2', 'expires_in' => 1800, 'scope' => 'openid profile'])),
        ]);
        $token = $this->provider('google')->setHttpClient($client)->refreshToken('ref-1');

        parse_str((string) $this->history[0]['request']->getBody(), $fields);
        $this->assertSame(['grant_type' => 'refresh_token', 'refresh_token' => 'ref-1', 'client_id' => 'id-123', 'client_secret' => 'secret-xyz'], $fields);
        $this->assertInstanceOf(CSocialLogin_OAuth2_Token::class, $token);
        $this->assertSame('tok-2', $token->token);
        $this->assertSame('ref-1', $token->refreshToken, 'Google tidak mengirim refresh_token baru: yang lama dipertahankan');
        $this->assertSame(1800, $token->expiresIn);
        $this->assertSame(['openid', 'profile'], $token->approvedScopes);

        $client = $this->mockClient([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['access_token' => 'tok-3', 'refresh_token' => 'ref-3'])),
        ]);
        $token = $this->provider('github')->setHttpClient($client)->refreshToken('ref-1');
        $this->assertSame('ref-3', $token->refreshToken);
        $this->assertNull($token->expiresIn);
        $this->assertSame([''], $token->approvedScopes, 'tanpa scope: explode string kosong, seperti upstream');
    }

    public function testGoogleIdTokenIsVerifiedAgainstJwks() {
        list($privateKey, $jwks) = $this->rsaKeyPair('kid-uji');
        $claims = ['iss' => 'https://accounts.google.com', 'aud' => 'id-123', 'sub' => '77', 'email' => 'jwt@uji.test', 'email_verified' => true, 'name' => 'Pengguna JWT', 'picture' => 'https://img/j.png', 'iat' => time(), 'exp' => time() + 300];
        $idToken = \Firebase\JWT\JWT::encode($claims, $privateKey, 'RS256', 'kid-uji');
        $this->assertGreaterThan(100, strlen($idToken));

        $client = $this->mockClient([new Response(200, ['Content-Type' => 'application/json'], json_encode($jwks))]);
        $user = $this->provider('google')->setHttpClient($client)->userFromToken($idToken);

        $this->assertCount(1, $this->history, 'hanya JWKS yang diambil, tidak ada panggilan userinfo');
        $this->assertSame('https://www.googleapis.com/oauth2/v3/certs', (string) $this->history[0]['request']->getUri());
        $this->assertSame('77', $user->getId());
        $this->assertSame('jwt@uji.test', $user->getEmail());
        $this->assertSame('Pengguna JWT', $user->getName());
        $this->assertSame($idToken, $user->token);

        $wrongAudience = \Firebase\JWT\JWT::encode(array_merge($claims, ['aud' => 'lain']), $privateKey, 'RS256', 'kid-uji');
        $client = $this->mockClient([new Response(200, ['Content-Type' => 'application/json'], json_encode($jwks))]);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid ID token audience');
        $this->provider('google')->setHttpClient($client)->userFromToken($wrongAudience);
    }

    public function testFacebookUsesGraphV23AndCanBePinnedToAnotherVersion() {
        $provider = $this->provider('facebook');
        $this->assertStringStartsWith('https://www.facebook.com/v23.0/dialog/oauth?', $provider->redirect()->getTargetUrl());

        $client = $this->mockClient([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['access_token' => 'fb-tok', 'expires' => 5000])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => '5', 'name' => 'FB', 'email' => 'fb@uji.test'])),
        ]);
        $user = $this->provider('facebook', ['code' => 'k'])->setHttpClient($client)->user();
        $this->assertSame('https://graph.facebook.com/v23.0/oauth/access_token', (string) $this->history[0]['request']->getUri());
        $this->assertStringStartsWith('https://graph.facebook.com/v23.0/me?', (string) $this->history[1]['request']->getUri());
        parse_str(parse_url((string) $this->history[1]['request']->getUri(), PHP_URL_QUERY), $query);
        $this->assertSame(hash_hmac('sha256', 'fb-tok', 'secret-xyz'), $query['appsecret_proof']);
        $this->assertSame(5000, $user->expiresIn, "'expires' dinormalkan ke expires_in");
        $this->assertSame('https://graph.facebook.com/v23.0/5/picture?type=normal', $user->getAvatar());

        $client = $this->mockClient([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['access_token' => 'fb-tok'])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => '5'])),
        ]);
        $this->provider('facebook', ['code' => 'k'])->graphVersion('v3.3')->setHttpClient($client)->user();
        $this->assertSame('https://graph.facebook.com/v3.3/oauth/access_token', (string) $this->history[0]['request']->getUri());
    }

    public function testFacebookLimitedLoginOidcTokenSkipsTheGraphCall() {
        list($privateKey, $jwks) = $this->rsaKeyPair('fb-kid');
        $claims = ['iss' => 'https://www.facebook.com', 'aud' => 'id-123', 'sub' => '900', 'email' => 'terbatas@uji.test', 'name' => 'Limited', 'given_name' => 'Lim', 'family_name' => 'Ited', 'nonce' => 'n-1', 'iat' => time(), 'exp' => time() + 300];
        $oidc = \Firebase\JWT\JWT::encode($claims, $privateKey, 'RS256', 'fb-kid');

        $client = $this->mockClient([new Response(200, ['Content-Type' => 'application/json'], json_encode($jwks))]);
        $user = $this->provider('facebook')->setHttpClient($client)->userFromToken($oidc, 'n-1');

        $this->assertCount(1, $this->history);
        $this->assertSame('https://limited.facebook.com/.well-known/oauth/openid/jwks/', (string) $this->history[0]['request']->getUri());
        $this->assertSame('900', $user->getId());
        $this->assertSame('terbatas@uji.test', $user->getEmail());
        $this->assertSame('Lim', $user->getRaw()['first_name']);
        $this->assertSame('Ited', $user->getRaw()['last_name']);

        $client = $this->mockClient([new Response(200, ['Content-Type' => 'application/json'], json_encode($jwks))]);
        try {
            $this->provider('facebook')->setHttpClient($client)->userFromToken($oidc, 'nonce-lain');
            $this->fail('nonce yang tidak cocok harus ditolak');
        } catch (Exception $e) {
            $this->assertSame('Token has incorrect nonce.', $e->getMessage());
        }

        $client = $this->mockClient([new Response(200, ['Content-Type' => 'application/json'], json_encode($jwks))]);
        $this->expectExceptionMessage('Token has incorrect nonce.');
        $this->provider('facebook')->setHttpClient($client)->userFromToken($oidc);
    }

    public function testStateComparisonUsesHashEquals() {
        $session = $this->arraySession();
        $session->put('state', 'abc');
        $request = CHTTP_Request::create('/callback', 'GET', ['state' => ['abc']]);
        $provider = (new CSocialLogin_DriverManager())->setConfig(['client_id' => 'a', 'client_secret' => 'b', 'redirect' => 'https://cb'])->driver('github')->setRequest($request);
        try {
            $provider->user();
            $this->fail('state berupa array harus ditolak tanpa warning');
        } catch (CSocialLogin_Exception_InvalidStateException $e) {
            $this->assertNull($session->get('state'), 'state sesi dihabiskan (pull)');
        }
        $session->put('state', 'abc');
        $request = CHTTP_Request::create('/callback', 'GET', ['state' => 'abd']);
        $this->expectException(CSocialLogin_Exception_InvalidStateException::class);
        (new CSocialLogin_DriverManager())->setConfig(['client_id' => 'a', 'client_secret' => 'b', 'redirect' => 'https://cb'])->driver('github')->setRequest($request)->user();
    }

    public function testConfigObject() {
        $config = new CSocialLogin_Config('k', 's', 'https://cb', ['tenant' => 'x']);
        $this->assertSame(['client_id' => 'k', 'client_secret' => 's', 'redirect' => 'https://cb', 'tenant' => 'x'], $config->get());
        $this->expectException(CSocialLogin_Exception_MissingConfigException::class);
        (new CSocialLogin_ConfigRetriever())->fromServices('layanan-tidak-ada-' . uniqid());
    }

    public function testRelativeRedirectIsResolvedAgainstTheAppUrl() {
        $manager = new CSocialLogin_DriverManager();
        $method = new ReflectionMethod($manager, 'formatRedirectUrl');
        $method->setAccessible(true);
        $this->assertSame('https://cb/x', $method->invoke($manager, ['redirect' => 'https://cb/x']), 'redirect absolut dibiarkan');
        $resolved = $method->invoke($manager, ['redirect' => '/auth/google/callback']);
        $this->assertStringEndsWith('/auth/google/callback', $resolved);
        $this->assertMatchesRegularExpression('#^https?://#', $resolved, 'redirect relatif di-resolve ke URL absolut');
        $this->assertSame('/lazy', cstr::after($method->invoke($manager, ['redirect' => function () {
            return '/lazy';
        }]), '://' . parse_url($resolved, PHP_URL_HOST)), 'redirect boleh closure');
    }
}
