<?php
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;

/**
 * CSocialLogin_DriverManager: extend() untuk provider kustom, driver twitter-oauth-2 dan x,
 * serta CSocialLogin::fake() untuk test aplikasi.
 */
class SocialLoginDriverManagerTest extends TestCase {
    /** @var array */
    protected $config = ['client_id' => 'id-1', 'client_secret' => 'rahasia', 'redirect' => 'https://app.uji.test/cb'];

    protected function tearDown(): void {
        CSocialLogin_DriverManager::forgetCustomCreators();
        CSocialLogin::forgetFakes();
    }

    public function testExtendRegistersACustomDriverForEveryManagerInstance() {
        $seen = [];
        CSocialLogin::extend('kantor', function ($config, $request, $manager) use (&$seen) {
            $seen[] = [$config, get_class($request), get_class($manager)];

            return $manager->buildProvider(CSocialLogin_OAuth2_Provider_GithubProvider::class, array_merge($config, ['redirect' => 'https://sso.kantor.test/cb']));
        });

        $provider = CSocialLogin::driver('kantor', $this->config);
        $this->assertInstanceOf(CSocialLogin_OAuth2_Provider_GithubProvider::class, $provider);
        $this->assertSame($this->config, $seen[0][0]);
        $this->assertSame(CHTTP_Request::class, $seen[0][1]);
        $this->assertSame(CSocialLogin_DriverManager::class, $seen[0][2]);
        parse_str(parse_url($provider->stateless()->redirect()->getTargetUrl(), PHP_URL_QUERY), $query);
        $this->assertSame('https://sso.kantor.test/cb', $query['redirect_uri']);

        $this->assertInstanceOf(CSocialLogin_OAuth2_Provider_GithubProvider::class, (new CSocialLogin_DriverManager())->setConfig($this->config)->driver('kantor'), 'instance manager baru tetap kenal driver kustom');

        CSocialLogin_DriverManager::forgetCustomCreators('kantor');
        $this->expectException(InvalidArgumentException::class);
        CSocialLogin::driver('kantor', $this->config);
    }

    public function testCustomDriverTakesPrecedenceOverBuiltIn() {
        CSocialLogin::extend('github', function ($config, $request, $manager) {
            return $manager->buildProvider(CSocialLogin_OAuth2_Provider_GitlabProvider::class, $config);
        });
        $this->assertInstanceOf(CSocialLogin_OAuth2_Provider_GitlabProvider::class, CSocialLogin::driver('github', $this->config));
        CSocialLogin_DriverManager::forgetCustomCreators();
        $this->assertInstanceOf(CSocialLogin_OAuth2_Provider_GithubProvider::class, CSocialLogin::driver('github', $this->config));
    }

    public function testUnknownDriverStillThrows() {
        $this->expectException(InvalidArgumentException::class);
        CSocialLogin::driver('tidak-ada', $this->config);
    }

    public function testTwitterOauth2AndXDrivers() {
        $this->assertInstanceOf(CSocialLogin_OAuth1_Provider_TwitterProvider::class, CSocialLogin::driver('twitter', $this->config), "'twitter' tetap OAuth1");

        $twitter = CSocialLogin::driver('twitter-oauth-2', $this->config)->stateless();
        $this->assertInstanceOf(CSocialLogin_OAuth2_Provider_TwitterProvider::class, $twitter);
        $url = $twitter->redirect()->getTargetUrl();
        $this->assertStringStartsWith('https://twitter.com/i/oauth2/authorize?', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('S256', $query['code_challenge_method'], 'PKCE aktif');
        $this->assertNotEmpty($query['code_challenge']);
        $this->assertSame('state', $query['state'], 'stateless: state literal seperti upstream');
        $this->assertSame('users.read tweet.read', $query['scope']);

        $x = CSocialLogin::driver('x', $this->config)->stateless();
        $this->assertInstanceOf(CSocialLogin_OAuth2_Provider_XProvider::class, $x);
        $xUrl = $x->redirect()->getTargetUrl();
        $this->assertStringStartsWith('https://x.com/i/oauth2/authorize?', $xUrl);
        parse_str(parse_url($xUrl, PHP_URL_QUERY), $xQuery);
        $this->assertSame('users.read users.email tweet.read', $xQuery['scope']);

        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode(['data' => ['id' => '9', 'username' => 'hery', 'name' => 'Hery', 'confirmed_email' => 'x@uji.test', 'profile_image_url' => 'https://img/x.png']])),
        ]));
        $stack->push(GuzzleHttp\Middleware::history($history));
        $user = $x->setHttpClient(new Client(['handler' => $stack]))->userFromToken('tok-x');
        $this->assertStringStartsWith('https://api.x.com/2/users/me?', (string) $history[0]['request']->getUri());
        $this->assertSame('x@uji.test', $user->getEmail());
        $this->assertSame('hery', $user->getNickname());
    }

    public function testFakeReplacesTheDriverUntilForgotten() {
        $fake = CSocialLogin::fake('google');
        $this->assertInstanceOf(CSocialLogin_Testing_FakeProvider::class, $fake);
        $provider = CSocialLogin::driver('google', $this->config);
        $this->assertSame($fake, $provider);
        $this->assertSame('https://sociallogin.fake/google/authorize', $provider->redirect()->getTargetUrl());
        $user = $provider->user();
        $this->assertInstanceOf(CSocialLogin_OAuth2_User::class, $user);
        $this->assertSame('test@example.com', $user->getEmail(), 'tanpa user: CSocialLogin_OAuth2_User::fake()');
        $this->assertSame('fake-token', $user->token);

        $this->assertSame($provider, $provider->scopes(['x']), 'panggilan fluent diteruskan ke provider asli dan kembali ke fake');
        $this->assertSame(['openid', 'profile', 'email', 'x'], $provider->getScopes());

        $this->assertTrue(CSocialLogin::hasFake('google'), 'fake("google") sebelumnya masih terdaftar');
        CSocialLogin::fake('google', CSocialLogin_OAuth2_User::fake(['id' => '42', 'email' => 'saya@uji.test']));
        $this->assertTrue(CSocialLogin::hasFake('google'));
        $this->assertSame('42', CSocialLogin::driver('google')->user()->getId());
        CSocialLogin::fake('github', function () {
            return CSocialLogin_OAuth2_User::fake(['email' => 'closure@uji.test']);
        });
        $this->assertSame('closure@uji.test', CSocialLogin::driver('github')->user()->getEmail());

        CSocialLogin::forgetFakes();
        $this->assertFalse(CSocialLogin::hasFake('google'));
        $this->assertInstanceOf(CSocialLogin_OAuth2_Provider_GoogleProvider::class, CSocialLogin::driver('google', $this->config));
    }

    public function testUserFakeDefaultsAndOverrides() {
        $user = CSocialLogin_OAuth2_User::fake(['name' => 'Uji']);
        $this->assertSame('123456789', $user->getId());
        $this->assertSame('Uji', $user->getName());
        $this->assertSame('https://example.com/avatar.jpg', $user->getAvatar());
        $this->assertSame(3600, $user->expiresIn);
        $this->assertSame('fake-refresh-token', $user->refreshToken);
        $this->assertSame('Uji', $user->getRaw()['name']);
    }
}
