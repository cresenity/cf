<?php

use PHPUnit\Framework\TestCase;

/**
 * CSocialLogin_AbstractUser::__get() (#-21033) - properti terdeklarasi
 * (id/nickname/name/email/avatar/user) selalu menang; __get hanya jalan untuk
 * kunci data mentah yang tidak punya properti/getter sendiri.
 */
class AbstractUserMagicGetTest extends TestCase {
    public function testDeclaredPropertiesAreUnaffectedByMagicGet() {
        $user = (new CSocialLogin_OAuth2_User())->setRaw(['id' => 'mentah-999'])->map(['id' => '123', 'name' => 'Budi']);

        $this->assertSame('123', $user->id, 'properti terdeklarasi menang, bukan nilai dari array $user mentah');
        $this->assertSame('Budi', $user->name);
    }

    public function testUndeclaredRawAttributeIsReadableThroughMagicGet() {
        $user = (new CSocialLogin_OAuth2_User())->setRaw(['id' => '1', 'locale' => 'id-ID']);

        $this->assertSame('id-ID', $user->locale, 'kunci mentah tanpa properti/getter sendiri terbaca lewat __get');
    }

    public function testMissingRawAttributeReturnsNullInsteadOfAWarning() {
        $user = (new CSocialLogin_OAuth2_User())->setRaw(['id' => '1']);

        $this->assertNull($user->tidak_ada);
    }
}
