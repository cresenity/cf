<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/AuthTwoFactorChallengeTest.php';

class AuthTwoFactorLoginCredentialsUser extends AuthTwoFactorChallengeUser {
    use CAuth_TwoFactor_AuthenticatableTrait;
}

/**
 * Langkah pertama login (CApp_Auth_Action_RedirectIfTwoFactorAuthenticatable): password harus divalidasi sebelum
 * tantangan 2FA dibuka, kegagalan menaikkan rate limiter, dan alihan non-JSON tidak melempar.
 */
class AuthTwoFactorLoginCredentialsTest extends TestCase {
    /** @var AuthTwoFactorLoginCredentialsUser */
    private $user;

    /** @var CAuth_Guard_SessionGuard */
    private $guard;

    /** @var CApp_Auth_LoginRateLimiter */
    private $limiter;

    /** @var CApp_Auth_Action_RedirectIfTwoFactorAuthenticatable */
    private $action;

    /** @var CAuth_TwoFactor_Manager */
    private $manager;

    /** @var array */
    private $failedEvents = [];

    protected function setUp(): void {
        parent::setUp();
        $this->user = new AuthTwoFactorLoginCredentialsUser([
            'id' => 7,
            'username' => 'rina@example.com',
            'password' => 'rahasia',
            'remember_token' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
        $session = new CSession_Store('cf-test', new CSession_Handler_ArraySessionHandler(120));
        $session->start();
        $this->guard = new CAuth_Guard_SessionGuard('default', new AuthTwoFactorChallengeProvider([$this->user]), $session, CHTTP_Request::create('/login', 'POST'));
        $this->guard->setDispatcher(new CEvent_Dispatcher());
        $this->guard->setCookieJar(new CHTTP_Cookie());
        $this->manager = new CAuth_TwoFactor_Manager(new CAuth_TwoFactor_TotpProvider(1, new AuthTwoFactorArrayCache()));
        CAuth_TwoFactor_Manager::setInstance($this->manager);
        $this->limiter = new CApp_Auth_LoginRateLimiter(new CCache_RateLimiter(new CCache_Repository(new CCache_Driver_ArrayDriver())));
        $this->action = new CApp_Auth_Action_RedirectIfTwoFactorAuthenticatable($this->guard, $this->limiter);

        c::session()->flush();
        $this->failedEvents = [];
        CEvent::dispatcher()->listen(CAuth_Event_Failed::class, function ($event) {
            $this->failedEvents[] = $event;
        });
    }

    protected function tearDown(): void {
        CEvent::dispatcher()->forget(CAuth_Event_Failed::class);
        CAuth_TwoFactor_Manager::setInstance(null);
        c::session()->flush();
        parent::tearDown();
    }

    /**
     * @return void
     */
    private function enableTwoFactor() {
        $this->manager->enable($this->user);
        $this->manager->confirm($this->user, CAuth_OTP_TOTP::createFromSecret(c::decrypt($this->user->two_factor_secret))->now());
    }

    /**
     * @param array $input
     * @param bool  $json
     *
     * @return CHTTP_Request
     */
    private function request(array $input, $json = false) {
        $request = CHTTP_Request::create('/login', 'POST', $input);
        if ($json) {
            $request->headers->set('Accept', 'application/json');
        }

        return $request;
    }

    /**
     * @param CHTTP_Request $request
     *
     * @return array [mixed response, bool nextReached, null|Throwable thrown]
     */
    private function attempt(CHTTP_Request $request) {
        $reached = false;
        $thrown = null;
        $response = null;
        try {
            $response = $this->action->handle($request, function () use (&$reached) {
                $reached = true;
            });
        } catch (Throwable $e) {
            $thrown = $e;
        }

        return [$response, $reached, $thrown];
    }

    /**
     * @param array $result
     *
     * @return void
     */
    private function assertRejected(array $result) {
        $this->assertInstanceOf(CValidation_Exception::class, $result[2], 'kredensial salah harus ditolak di langkah pertama');
        $this->assertFalse($result[1], 'tidak boleh lanjut ke langkah berikutnya');
        $this->assertNull(c::session()->get(CApp_Auth_TwoFactorChallenge::SESSION_ID), 'tantangan 2FA tidak boleh dibuka');
    }

    public function testWrongPasswordOnTwoFactorAccountIsRejectedForJsonAndWebRequests() {
        $this->enableTwoFactor();

        foreach ([true, false] as $json) {
            $this->assertRejected($this->attempt($this->request(['username' => 'rina@example.com', 'password' => 'SALAH-TOTAL'], $json)));
        }
    }

    public function testWrongPasswordRaisesTheLoginRateLimiterAndFiresFailedEvent() {
        $this->enableTwoFactor();
        $request = $this->request(['username' => 'rina@example.com', 'password' => 'salah'], true);

        $this->assertSame(0, $this->limiter->attempts($request));
        $this->attempt($request);
        $this->attempt($request);

        $this->assertSame(2, $this->limiter->attempts($request), 'percobaan password salah dihitung walau akun ber-2FA');
        $this->assertCount(2, $this->failedEvents);
        $this->assertSame($this->user, $this->failedEvents[0]->user, 'event Failed membawa pengguna yang ditemukan');
    }

    public function testUnknownUsernameIsRejected() {
        $this->enableTwoFactor();

        $this->assertRejected($this->attempt($this->request(['username' => 'tidak@ada.test', 'password' => 'rahasia'], true)));
        $this->assertCount(1, $this->failedEvents);
    }

    public function testCorrectPasswordOnTwoFactorAccountOpensTheChallengeAsJson() {
        $this->enableTwoFactor();

        list($response, $reached, $thrown) = $this->attempt($this->request(['username' => 'rina@example.com', 'password' => 'rahasia'], true));

        $this->assertNull($thrown);
        $this->assertFalse($reached);
        $this->assertInstanceOf(CHTTP_JsonResponse::class, $response);
        $this->assertTrue($response->getData(true)['two_factor']);
        $this->assertSame(7, c::session()->get(CApp_Auth_TwoFactorChallenge::SESSION_ID));
        $this->assertCount(0, $this->failedEvents);
    }

    public function testCorrectPasswordOnTwoFactorAccountRedirectsWebRequestsToTheChallenge() {
        $this->enableTwoFactor();

        list($response, $reached, $thrown) = $this->attempt($this->request(['username' => 'rina@example.com', 'password' => 'rahasia']));

        $this->assertNull($thrown, 'alihan non-JSON tidak boleh melempar (setTargetUrl tidak ada di CHTTP_Redirector)');
        $this->assertFalse($reached);
        $this->assertInstanceOf(CHTTP_RedirectResponse::class, $response);
        $this->assertStringContainsString('login/twofactor', $response->getTargetUrl());
        $this->assertSame(7, c::session()->get(CApp_Auth_TwoFactorChallenge::SESSION_ID));
    }

    public function testAccountWithoutTwoFactorContinuesToTheNextStep() {
        list($response, $reached, $thrown) = $this->attempt($this->request(['username' => 'rina@example.com', 'password' => 'rahasia'], true));

        $this->assertNull($thrown);
        $this->assertTrue($reached);
        $this->assertNull(c::session()->get(CApp_Auth_TwoFactorChallenge::SESSION_ID));
    }

    public function testWrongPasswordOnAccountWithoutTwoFactorIsAlsoRejectedHere() {
        $this->assertRejected($this->attempt($this->request(['username' => 'rina@example.com', 'password' => 'salah'], true)));
    }

    public function testRecoveryCodesAreDefensiveForMissingOrCorruptColumn() {
        $this->assertSame([], $this->user->recoveryCodes(), 'kolom null');

        $this->user->forceFill(['two_factor_recovery_codes' => c::encrypt('bukan json')]);
        $this->assertSame([], $this->user->recoveryCodes(), 'isi bukan array');

        $this->user->forceFill(['two_factor_recovery_codes' => c::encrypt(json_encode(['aaaaa-bbbbb']))]);
        $this->assertSame(['aaaaa-bbbbb'], $this->user->recoveryCodes());
    }
}
