<?php

use PHPUnit\Framework\TestCase;

/**
 * Model pengguna palsu di memori: cukup forceFill/save/getAttributes dan akses properti.
 */
class AuthTwoFactorFakeUser {
    public $attributes;

    public $saves = 0;

    public function __construct(array $attributes = []) {
        $this->attributes = $attributes;
    }

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

class AuthTwoFactorModel extends AuthTwoFactorFakeUser {
    use CAuth_TwoFactor_AuthenticatableTrait;
}

class AuthTwoFactorLegacyTraitModel extends AuthTwoFactorFakeUser {
    use CApp_Auth_TwoFactor_TwoFactorAuthenticatableTrait;
}

class AuthTwoFactorArrayCache {
    public $items = [];

    public function add($key, $value, $ttl = null) {
        if (isset($this->items[$key])) {
            return false;
        }
        $this->items[$key] = $value;

        return true;
    }
}

class AuthTwoFactorTest extends TestCase {
    const SECRET = 'JBSWY3DPEHPK3PXP';

    protected function tearDown(): void {
        CAuth_TwoFactor_Manager::setInstance(null);
        parent::tearDown();
    }

    /**
     * @return AuthTwoFactorFakeUser
     */
    private function user(array $attributes = ['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null]) {
        return new AuthTwoFactorFakeUser($attributes);
    }

    /**
     * @return CAuth_TwoFactor_Manager
     */
    private function manager() {
        return new CAuth_TwoFactor_Manager(new CAuth_TwoFactor_TotpProvider(1, new AuthTwoFactorArrayCache()));
    }

    /**
     * @param string $secret
     * @param int    $offset
     *
     * @return string
     */
    private function code($secret, $offset = 0) {
        return CAuth_OTP_TOTP::createFromSecret($secret)->at(time() + $offset);
    }

    public function testProviderGeneratesBase32SecretAndProvisioningUri() {
        $provider = new CAuth_TwoFactor_TotpProvider();
        $secret = $provider->generateSecretKey();

        $this->assertMatchesRegularExpression('/^[A-Z2-7]{16,}$/', $secret);

        $uri = $provider->qrCodeUrl('Acme CRM', 'rina@example.com', $secret);
        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret=' . $secret, $uri);
        $this->assertStringContainsString('issuer=Acme', $uri);
        $this->assertStringContainsString('rina%40example.com', $uri);
    }

    public function testProviderAcceptsCurrentCodeOnceAndRejectsReplay() {
        $provider = new CAuth_TwoFactor_TotpProvider(1, new AuthTwoFactorArrayCache());
        $code = $this->code(self::SECRET);

        $this->assertTrue($provider->verify(self::SECRET, $code));
        $this->assertFalse($provider->verify(self::SECRET, $code), 'kode yang sama tidak boleh dipakai dua kali');
    }

    public function testProviderToleratesOnePeriodOfDriftButNotTwo() {
        $provider = new CAuth_TwoFactor_TotpProvider(1, new AuthTwoFactorArrayCache());

        $this->assertTrue($provider->verify(self::SECRET, $this->code(self::SECRET, -30)));
        $this->assertFalse($provider->verify(self::SECRET, $this->code(self::SECRET, -60)));
    }

    public function testProviderRejectsMalformedCodes() {
        $provider = new CAuth_TwoFactor_TotpProvider(1, new AuthTwoFactorArrayCache());

        $this->assertFalse($provider->verify(self::SECRET, ''));
        $this->assertFalse($provider->verify(self::SECRET, 'abcdef'));
        $this->assertFalse($provider->verify(self::SECRET, '000000'));
    }

    public function testProviderIgnoresSpacesInCode() {
        $provider = new CAuth_TwoFactor_TotpProvider(1, new AuthTwoFactorArrayCache());
        $code = $this->code(self::SECRET);

        $this->assertTrue($provider->verify(self::SECRET, substr($code, 0, 3) . ' ' . substr($code, 3)));
    }

    public function testEnableStoresEncryptedSecretAndEightRecoveryCodesPendingConfirmation() {
        $manager = $this->manager();
        $user = $this->user();

        $manager->enable($user);

        $this->assertNotNull($user->two_factor_secret);
        $this->assertNotSame(c::decrypt($user->two_factor_secret), $user->two_factor_secret, 'secret harus terenkripsi');
        $codes = json_decode(c::decrypt($user->two_factor_recovery_codes), true);
        $this->assertCount(8, $codes);
        $this->assertCount(8, array_unique($codes));
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertFalse($manager->isEnabled($user), 'setup yang belum dikonfirmasi tidak boleh memicu tantangan login');
    }

    public function testConfirmRejectsInvalidCodeAndActivatesOnValidCode() {
        $manager = $this->manager();
        $user = $this->user();
        $manager->enable($user);

        try {
            $manager->confirm($user, '000000');
            $this->fail('kode salah harus ditolak');
        } catch (CValidation_Exception $e) {
            $this->assertArrayHasKey('code', $e->errors());
        }
        $this->assertFalse($manager->isEnabled($user));

        $manager->confirm($user, $this->code(c::decrypt($user->two_factor_secret)));

        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertTrue($manager->isEnabled($user));
    }

    public function testConfirmWithoutEnableIsAProgrammerError() {
        $this->expectException(LogicException::class);

        $this->manager()->confirm($this->user(), '123456');
    }

    public function testModelWithoutConfirmationColumnIsActiveRightAfterEnable() {
        $manager = $this->manager();
        $user = $this->user(['two_factor_secret' => null, 'two_factor_recovery_codes' => null]);

        $manager->enable($user);

        $this->assertTrue($manager->isEnabled($user));
        $this->assertArrayNotHasKey('two_factor_confirmed_at', $user->getAttributes());
    }

    public function testVerifyCodeAndReplay() {
        $manager = $this->manager();
        $user = $this->user();
        $manager->enable($user);
        $code = $this->code(c::decrypt($user->two_factor_secret));

        $this->assertTrue($manager->verifyCode($user, $code));
        $this->assertFalse($manager->verifyCode($user, $code));
        $this->assertFalse($manager->verifyCode($this->user(), $code), 'tanpa secret selalu gagal');
    }

    public function testDisableClearsEverything() {
        $manager = $this->manager();
        $user = $this->user();
        $manager->enable($user);
        $manager->confirm($user, $this->code(c::decrypt($user->two_factor_secret)));

        $manager->disable($user);

        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertFalse($manager->isEnabled($user));
    }

    public function testDisableOnUserWithoutTwoFactorDoesNotSave() {
        $user = $this->user();

        $this->manager()->disable($user);

        $this->assertSame(0, $user->saves);
    }

    public function testRegenerateRecoveryCodesReplacesAllCodes() {
        $manager = $this->manager();
        $user = $this->user();
        $manager->enable($user);
        $before = json_decode(c::decrypt($user->two_factor_recovery_codes), true);

        $manager->regenerateRecoveryCodes($user);

        $after = json_decode(c::decrypt($user->two_factor_recovery_codes), true);
        $this->assertCount(8, $after);
        $this->assertSame([], array_intersect($before, $after));
    }

    public function testRecoveryCodeWorksOnceAndIsReplaced() {
        $manager = $this->manager();
        $user = $this->user();
        $manager->enable($user);
        $codes = json_decode(c::decrypt($user->two_factor_recovery_codes), true);

        $this->assertTrue($manager->useRecoveryCode($user, $codes[3]));
        $this->assertFalse($manager->useRecoveryCode($user, $codes[3]), 'kode pemulihan hangus sekali pakai');

        $after = json_decode(c::decrypt($user->two_factor_recovery_codes), true);
        $this->assertCount(8, $after);
        $this->assertNotContains($codes[3], $after);
        $this->assertSame($codes[0], $after[0], 'kode lain tidak berubah');
    }

    public function testRecoveryCodeRejectsUnknownAndEmpty() {
        $manager = $this->manager();
        $user = $this->user();
        $manager->enable($user);

        $this->assertFalse($manager->useRecoveryCode($user, 'nope-nope'));
        $this->assertFalse($manager->useRecoveryCode($user, ''));
        $this->assertFalse($manager->useRecoveryCode($this->user(), 'anything'));
    }

    public function testTraitExposesCodesQrAndEnabledFlag() {
        $manager = $this->manager();
        CAuth_TwoFactor_Manager::setInstance($manager);
        $user = new AuthTwoFactorModel(['email' => 'rina@example.com', 'two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null]);
        $manager->enable($user);

        $this->assertCount(8, $user->recoveryCodes());
        $this->assertStringStartsWith('otpauth://totp/', $user->twoFactorQrCodeUrl());
        $this->assertStringContainsString('<svg', $user->twoFactorQrCodeSvg());
        $this->assertFalse($user->hasEnabledTwoFactorAuthentication());

        $manager->confirm($user, $this->code(c::decrypt($user->two_factor_secret)));
        $this->assertTrue($user->hasEnabledTwoFactorAuthentication());
    }

    public function testTraitReplaceRecoveryCodeDelegatesToManager() {
        $manager = $this->manager();
        CAuth_TwoFactor_Manager::setInstance($manager);
        $user = new AuthTwoFactorModel(['email' => 'a@b.c', 'two_factor_secret' => null, 'two_factor_recovery_codes' => null]);
        $manager->enable($user);
        $codes = $user->recoveryCodes();

        $user->replaceRecoveryCode($codes[0]);

        $this->assertNotContains($codes[0], $user->recoveryCodes());
    }

    public function testOldClassNamesStillWork() {
        $this->assertContains(CAuth_TwoFactor_AuthenticatableTrait::class, c::classUsesRecursive(new AuthTwoFactorLegacyTraitModel()));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{10}-[A-Za-z0-9]{10}$/', CApp_Auth_RecoveryCode::generate());
        $this->assertTrue(is_subclass_of(CAuth_TwoFactor_TotpProvider::class, CApp_Auth_TwoFactor_TwoFactorAuthenticationProviderInterface::class)
            || (new ReflectionClass(CApp_Auth_TwoFactor_TwoFactorAuthenticationProviderInterface::class))->implementsInterface(CAuth_TwoFactor_ProviderInterface::class));
    }
}
