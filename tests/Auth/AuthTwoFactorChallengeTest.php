<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/AuthSessionGuardTest.php';
require_once __DIR__ . '/AuthTwoFactorTest.php';

/**
 * Pengguna dengan kemampuan Manager (forceFill/save/getAttributes) di atas GenericUser.
 */
class AuthTwoFactorChallengeUser extends CAuth_GenericUser {
    public $saves = 0;

    public function __get($key) {
        return array_key_exists($key, $this->attributes) ? $this->attributes[$key] : null;
    }

    public function getAttributes() {
        return $this->attributes;
    }

    public function forceFill(array $attributes) {
        $this->attributes = array_merge($this->attributes, $attributes);

        return $this;
    }

    public function save() {
        $this->saves++;

        return true;
    }
}

class AuthTwoFactorChallengeProvider extends AuthSessionGuardTestProvider {
    public function retrieveByCredentials(array $credentials) {
        $user = $this->retrieveById(7);

        return $user && $user->username === carr::get($credentials, 'username') ? $user : null;
    }
}

class AuthTwoFactorChallengeTest extends TestCase {
    /**
     * @var AuthTwoFactorChallengeUser
     */
    private $user;

    /**
     * @var CSession_Store
     */
    private $session;

    /**
     * @var CAuth_Guard_SessionGuard
     */
    private $guard;

    /**
     * @var CAuth_TwoFactor_Manager
     */
    private $manager;

    /**
     * @var CApp_Auth_TwoFactorChallenge
     */
    private $challenge;

    protected function setUp(): void {
        parent::setUp();
        $this->user = new AuthTwoFactorChallengeUser([
            'id' => 7,
            'username' => 'rina@example.com',
            'password' => 'rahasia',
            'remember_token' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
        $provider = new AuthTwoFactorChallengeProvider([$this->user]);
        $this->session = new CSession_Store('cf-test', new CSession_Handler_ArraySessionHandler(120));
        $this->session->start();
        $this->guard = new CAuth_Guard_SessionGuard('default', $provider, $this->session, CHTTP_Request::create('/login', 'POST'));
        $this->guard->setDispatcher(new CEvent_Dispatcher());
        $this->guard->setCookieJar(new CHTTP_Cookie());

        $this->manager = new CAuth_TwoFactor_Manager(new CAuth_TwoFactor_TotpProvider(1, new AuthTwoFactorArrayCache()));
        $limiter = new CCache_RateLimiter(new CCache_Repository(new CCache_Driver_ArrayDriver()));
        $this->challenge = new CApp_Auth_TwoFactorChallenge($this->guard, $this->session, $limiter, $this->manager);
    }

    /**
     * @return string
     */
    private function enableTwoFactor() {
        $this->manager->enable($this->user);
        $secret = c::decrypt($this->user->two_factor_secret);
        $this->manager->confirm($this->user, CAuth_OTP_TOTP::createFromSecret($secret)->now());

        return $secret;
    }

    /**
     * @return CHTTP_Request
     */
    private function request(array $input) {
        return CHTTP_Request::create('/login/twofactor', 'POST', $input);
    }

    /**
     * @param string $secret
     * @param int    $offset
     *
     * @return string
     */
    private function code($secret, $offset = 30) {
        // kode periode berikutnya: kode yang dipakai saat konfirmasi sudah hangus
        return CAuth_OTP_TOTP::createFromSecret($secret)->at(time() + $offset);
    }

    public function testDefaultCollaboratorsAreWiredFromTheAppCache() {
        // tanpa limiter/provider yang disuntik: memakai c::cache() milik app, bukan repository buatan tes
        $challenge = new CApp_Auth_TwoFactorChallenge($this->guard, $this->session);
        $this->assertNull($challenge->challengedUser());

        $provider = new CAuth_TwoFactor_TotpProvider();
        $secret = $provider->generateSecretKey();
        $code = CAuth_OTP_TOTP::createFromSecret($secret)->now();
        $this->assertTrue($provider->verify($secret, $code));
        $this->assertFalse($provider->verify($secret, $code), 'kode sekali pakai juga di cache bawaan');
    }

    public function testNoPendingLoginMeansNoChallengedUser() {
        $this->assertNull($this->challenge->challengedUser());
        $this->assertFalse($this->challenge->hasChallengedUser());
    }

    public function testBeginThenChallengedUserReturnsTheUser() {
        $this->enableTwoFactor();

        CApp_Auth_TwoFactorChallenge::begin($this->session, $this->user, true);

        $this->assertSame($this->user, $this->challenge->challengedUser());
        $this->assertSame(7, $this->session->get('login.id'));
        $this->assertTrue($this->session->get('login.remember'));
    }

    public function testPendingLoginExpires() {
        $this->enableTwoFactor();
        CApp_Auth_TwoFactorChallenge::begin($this->session, $this->user);
        $this->session->put('login.at', time() - CApp_Auth_TwoFactorChallenge::TTL - 5);

        $this->assertNull($this->challenge->challengedUser());
        $this->assertNull($this->session->get('login.id'), 'login tertunda yang kedaluwarsa dibersihkan');
    }

    public function testPendingLoginIsDroppedWhenTwoFactorIsNotEnabled() {
        CApp_Auth_TwoFactorChallenge::begin($this->session, $this->user);

        $this->assertNull($this->challenge->challengedUser());
        $this->assertNull($this->session->get('login.id'));
    }

    public function testValidCodeLogsTheUserInAndClearsPendingState() {
        $secret = $this->enableTwoFactor();
        CApp_Auth_TwoFactorChallenge::begin($this->session, $this->user);
        $before = $this->session->getId();

        $user = $this->challenge->complete($this->request(['code' => $this->code($secret)]));

        $this->assertSame($this->user, $user);
        $this->assertTrue($this->guard->check());
        $this->assertSame(7, $this->guard->id());
        $this->assertNull($this->session->get('login.id'));
        $this->assertNull($this->session->get('login.remember'));
        $this->assertNull($this->session->get('login.at'));
        $this->assertNotSame($before, $this->session->getId(), 'id sesi diganti setelah login');
    }

    public function testInvalidCodeIsRejectedAndKeepsThePendingLogin() {
        $this->enableTwoFactor();
        CApp_Auth_TwoFactorChallenge::begin($this->session, $this->user);

        try {
            $this->challenge->complete($this->request(['code' => '000000']));
            $this->fail('kode salah harus ditolak');
        } catch (CValidation_Exception $e) {
            $this->assertArrayHasKey('code', $e->errors());
        }

        $this->assertFalse($this->guard->check());
        $this->assertSame(7, $this->session->get('login.id'));
    }

    public function testRecoveryCodeLogsInOnceOnly() {
        $this->enableTwoFactor();
        $recovery = json_decode(c::decrypt($this->user->two_factor_recovery_codes), true)[0];
        CApp_Auth_TwoFactorChallenge::begin($this->session, $this->user);

        $this->challenge->complete($this->request(['recovery_code' => $recovery]));
        $this->assertTrue($this->guard->check());

        $this->guard->logout();
        CApp_Auth_TwoFactorChallenge::begin($this->session, $this->user);

        try {
            $this->challenge->complete($this->request(['recovery_code' => $recovery]));
            $this->fail('kode pemulihan yang sama tidak boleh dipakai lagi');
        } catch (CValidation_Exception $e) {
            $this->assertArrayHasKey('recovery_code', $e->errors());
        }
    }

    public function testMissingCodeIsAValidationError() {
        $this->enableTwoFactor();
        CApp_Auth_TwoFactorChallenge::begin($this->session, $this->user);

        $this->expectException(CValidation_Exception::class);

        $this->challenge->complete($this->request([]));
    }

    public function testCompleteWithoutPendingLoginIsAnExpiredChallenge() {
        try {
            $this->challenge->complete($this->request(['code' => '123456']));
            $this->fail('tanpa login tertunda harus ditolak');
        } catch (CValidation_Exception $e) {
            $this->assertStringContainsString('expired', $e->errors()['code'][0]);
        }
    }

    public function testTooManyFailuresLockTheChallengeEvenForAValidCode() {
        $secret = $this->enableTwoFactor();
        CApp_Auth_TwoFactorChallenge::begin($this->session, $this->user);

        for ($i = 0; $i < CApp_Auth_TwoFactorChallenge::MAX_ATTEMPTS; $i++) {
            try {
                $this->challenge->complete($this->request(['code' => '000000']));
            } catch (CValidation_Exception $e) {
            }
        }

        try {
            $this->challenge->complete($this->request(['code' => $this->code($secret)]));
            $this->fail('harus terkunci');
        } catch (CValidation_Exception $e) {
            $this->assertStringContainsString('Too many attempts', $e->errors()['code'][0]);
        }
        $this->assertFalse($this->guard->check());
    }

    public function testCancelDropsThePendingLogin() {
        $this->enableTwoFactor();
        CApp_Auth_TwoFactorChallenge::begin($this->session, $this->user);

        $this->challenge->cancel();

        $this->assertFalse($this->challenge->hasChallengedUser());
    }

    public function testPasswordConfirmationRemembersACorrectPassword() {
        $confirmation = new CApp_Auth_PasswordConfirmation($this->guard, $this->session);

        $this->assertFalse($confirmation->isConfirmed());
        $this->assertFalse($confirmation->confirm($this->user, 'salah'));
        $this->assertFalse($confirmation->isConfirmed());

        $this->assertTrue($confirmation->confirm($this->user, 'rahasia'));
        $this->assertTrue($confirmation->isConfirmed());
        $this->assertFalse($confirmation->isConfirmed(-1), 'jendela habis');

        $confirmation->forget();
        $this->assertFalse($confirmation->isConfirmed());
    }

    public function testFeatureOptionsAreRememberedAndOnlyCountWhenTheFeatureIsEnabled() {
        CApp_Auth_Features::setFeatures([CApp_Auth_Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => false])]);

        $this->assertTrue(CApp_Auth_Features::optionEnabled(CApp_Auth_Features::twoFactorAuthentication(), 'confirm'));
        $this->assertFalse(CApp_Auth_Features::optionEnabled(CApp_Auth_Features::twoFactorAuthentication(), 'confirmPassword'));
        $this->assertFalse(CApp_Auth_Features::optionEnabled(CApp_Auth_Features::twoFactorAuthentication(), 'unknown'));

        CApp_Auth_Features::setFeatures([]);
        $this->assertFalse(CApp_Auth_Features::optionEnabled(CApp_Auth_Features::twoFactorAuthentication(), 'confirm'));
    }
}
