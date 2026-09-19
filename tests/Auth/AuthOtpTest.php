<?php

use PHPUnit\Framework\TestCase;

/**
 * TOTP/HOTP harus bisa dipakai di PHP 7.4: `setParameter()` dulu bertipe
 * `mixed` dan peta parameternya digabung dengan spread berkunci string -
 * dua-duanya baru ada di PHP 8.x dan meledak saat dipanggil, bukan saat dimuat.
 */
class AuthOtpTest extends TestCase {
    const SECRET = 'JBSWY3DPEHPK3PXP';

    /**
     * @return void
     */
    public function testTotpParameterMapMergesParentAndOwnEntries() {
        $totp = CAuth_OTP_TOTP::createFromSecret(self::SECRET);
        $totp->setParameter('period', '45');
        $totp->setParameter('digits', 8);
        $totp->setParameter('algorithm', 'SHA256');

        $this->assertSame(45, $totp->getPeriod());
        $this->assertSame(8, $totp->getDigits());
        $this->assertSame('sha256', $totp->getDigest());
    }

    /**
     * @return void
     */
    public function testHotpParameterMapMergesParentAndOwnEntries() {
        $hotp = CAuth_OTP_HOTP::createFromSecret(self::SECRET);
        $hotp->setParameter('counter', '7');
        $hotp->setParameter('digits', 6);

        $this->assertSame(7, $hotp->getCounter());
        $this->assertSame(6, $hotp->getDigits());
    }

    /**
     * @return void
     */
    public function testInvalidParameterValueIsRejectedByTheMap() {
        $totp = CAuth_OTP_TOTP::createFromSecret(self::SECRET);

        $this->expectException(InvalidArgumentException::class);
        $totp->setParameter('period', 0);
    }

    /**
     * @return void
     */
    public function testProvisioningUriMergesOptionsWithParameters() {
        $totp = CAuth_OTP_TOTP::createFromSecret(self::SECRET);
        $totp->setLabel('user@example.com');
        $totp->setIssuer('Cresenity');
        $totp->setPeriod(60);

        $uri = $totp->getProvisioningUri();

        $this->assertStringStartsWith('otpauth://totp/Cresenity%3Auser%40example.com?', $uri);
        $this->assertStringContainsString('period=60', $uri);
        $this->assertStringContainsString('secret=' . self::SECRET, $uri);
        $this->assertStringContainsString('issuer=Cresenity', $uri);
    }
}
