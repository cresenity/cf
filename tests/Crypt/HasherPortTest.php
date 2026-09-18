<?php
use PHPUnit\Framework\TestCase;

/**
 * Port HasherTest hulu: nilai hash kosong/null, verifikasi algoritma, needsRehash, konfigurasi
 * rounds/memory/time, HashManager sebagai fasad driver, Md5Hasher warisan.
 */
class HasherPortTest extends TestCase {
    public function testEmptyHashedValueReturnsFalse() {
        $this->assertFalse((new CCrypt_Hasher_BcryptHasher())->check('password', ''));
        $this->assertFalse((new CCrypt_Hasher_Md5Hasher())->check('password', ''));
        if (defined('PASSWORD_ARGON2I')) {
            $this->assertFalse((new CCrypt_Hasher_ArgonHasher())->check('password', ''));
        }
    }

    public function testNullHashedValueReturnsFalse() {
        $this->assertFalse((new CCrypt_Hasher_BcryptHasher())->check('password', null));
        $this->assertFalse((new CCrypt_Hasher_Md5Hasher())->check('password', null));
    }

    public function testBcryptHashingHonoursRounds() {
        $hasher = new CCrypt_Hasher_BcryptHasher(['rounds' => 4]);
        $value = $hasher->make('password');
        $this->assertNotSame('password', $value);
        $this->assertTrue($hasher->check('password', $value));
        $this->assertFalse($hasher->check('wrong', $value));
        $this->assertFalse($hasher->needsRehash($value));
        $this->assertTrue($hasher->needsRehash($value, ['rounds' => 5]), 'cost berbeda → perlu rehash');
        $this->assertSame('bcrypt', $hasher->info($value)['algoName']);
        $this->assertSame(4, $hasher->info($value)['options']['cost']);
    }

    public function testBcryptSetRoundsChangesCost() {
        $hasher = new CCrypt_Hasher_BcryptHasher(['rounds' => 4]);
        $this->assertSame($hasher, $hasher->setRounds(5));
        $this->assertSame(5, $hasher->info($hasher->make('password'))['options']['cost']);
    }

    public function testBcryptVerifyConfiguration() {
        $hasher = new CCrypt_Hasher_BcryptHasher(['rounds' => 5]);
        $this->assertTrue($hasher->verifyConfiguration($hasher->make('password')));
        $this->assertTrue($hasher->verifyConfiguration((new CCrypt_Hasher_BcryptHasher(['rounds' => 4]))->make('password')), 'cost lebih rendah masih sah');
        $this->assertFalse($hasher->verifyConfiguration((new CCrypt_Hasher_BcryptHasher(['rounds' => 6]))->make('password')), 'cost lebih tinggi dari konfigurasi ditolak');
        $this->assertFalse($hasher->verifyConfiguration(md5('password')));
        if (defined('PASSWORD_ARGON2I')) {
            $this->assertFalse($hasher->verifyConfiguration((new CCrypt_Hasher_ArgonHasher())->make('password')));
        }
    }

    public function testBcryptVerificationRejectsOtherAlgorithmsWhenVerifyIsOn() {
        if (!defined('PASSWORD_ARGON2I')) {
            $this->markTestSkipped('butuh argon2');
        }
        $argonHashed = (new CCrypt_Hasher_ArgonHasher())->make('password');
        $this->assertTrue((new CCrypt_Hasher_BcryptHasher(['verify' => false]))->check('password', $argonHashed), 'verify=false: password_verify menerima algoritma apa pun');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This password does not use the Bcrypt algorithm.');
        (new CCrypt_Hasher_BcryptHasher(['verify' => true]))->check('password', $argonHashed);
    }

    public function testArgonVerificationRejectsBcryptWhenVerifyIsOn() {
        if (!defined('PASSWORD_ARGON2I')) {
            $this->markTestSkipped('butuh argon2');
        }
        $bcryptHashed = (new CCrypt_Hasher_BcryptHasher(['rounds' => 4]))->make('password');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This password does not use the Argon2i algorithm.');
        (new CCrypt_Hasher_ArgonHasher(['verify' => true]))->check('password', $bcryptHashed);
    }

    public function testArgonOptionsAndNeedsRehash() {
        if (!defined('PASSWORD_ARGON2I')) {
            $this->markTestSkipped('butuh argon2');
        }
        $hasher = new CCrypt_Hasher_ArgonHasher(['memory' => 1024, 'time' => 2, 'threads' => 2]);
        $value = $hasher->make('password');
        $info = $hasher->info($value);
        $this->assertSame('argon2i', $info['algoName']);
        $this->assertSame(['memory_cost' => 1024, 'time_cost' => 2, 'threads' => 2], $info['options']);
        $this->assertFalse($hasher->needsRehash($value));
        $this->assertTrue($hasher->needsRehash($value, ['memory' => 2048]));
        $hasher->setMemory(2048)->setTime(3)->setThreads(2);
        $this->assertSame(['memory_cost' => 2048, 'time_cost' => 3, 'threads' => 2], $hasher->info($hasher->make('password'))['options']);
        $this->assertTrue($hasher->verifyConfiguration($value), 'opsi lebih rendah dari konfigurasi sah');
        $hasher->setThreads(1);
        $this->assertFalse($hasher->verifyConfiguration($value), 'threads di hash melebihi konfigurasi');
    }

    public function testArgon2idHashingAndVerification() {
        if (!defined('PASSWORD_ARGON2ID')) {
            $this->markTestSkipped('butuh argon2id');
        }
        $hasher = new CCrypt_Hasher_Argon2IdHasher();
        $value = $hasher->make('password');
        $this->assertSame('argon2id', $hasher->info($value)['algoName']);
        $this->assertTrue($hasher->check('password', $value));
        $this->assertFalse($hasher->check('wrong', $value));
        $this->assertFalse($hasher->needsRehash($value));
        $this->expectException(RuntimeException::class);
        (new CCrypt_Hasher_Argon2IdHasher(['verify' => true]))->check('password', (new CCrypt_Hasher_ArgonHasher())->make('password'));
    }

    public function testMd5HasherIsPlainMd5WithoutRehash() {
        $hasher = new CCrypt_Hasher_Md5Hasher();
        $this->assertSame(md5('password'), $hasher->make('password'));
        $this->assertTrue($hasher->check('password', md5('password')));
        $this->assertFalse($hasher->check('wrong', md5('password')));
        $this->assertFalse($hasher->needsRehash(md5('password')), 'md5 warisan tidak pernah minta rehash');
    }

    public function testHashManagerResolvesDriversAndDelegates() {
        $manager = new CCrypt_HashManager();
        $this->assertSame('bcrypt', $manager->getDefaultDriver());
        $this->assertInstanceOf(CCrypt_Hasher_BcryptHasher::class, $manager->driver());
        $this->assertInstanceOf(CCrypt_Hasher_BcryptHasher::class, $manager->driver('bcrypt'));
        $this->assertInstanceOf(CCrypt_Hasher_Md5Hasher::class, $manager->driver('md5'));
        if (defined('PASSWORD_ARGON2I')) {
            $this->assertInstanceOf(CCrypt_Hasher_ArgonHasher::class, $manager->driver('argon'));
            $this->assertInstanceOf(CCrypt_Hasher_Argon2IdHasher::class, $manager->driver('argon2id'));
        }

        $value = $manager->make('password');
        $this->assertSame('bcrypt', $manager->info($value)['algoName']);
        $this->assertSame(10, $manager->info($value)['options']['cost'], 'rounds dari config hashing.bcrypt');
        $this->assertTrue($manager->check('password', $value));
        $this->assertFalse($manager->needsRehash($value));
    }

    public function testHashManagerInstancePerDriverAndHelper() {
        $md5 = CCrypt_HashManager::instance('md5');
        $this->assertSame($md5, CCrypt_HashManager::instance('md5'));
        $this->assertSame(md5('x'), $md5->make('x'), 'manager bernama md5 memakai driver itu sebagai default');
        $this->assertSame(CCrypt_HashManager::instance(), c::hash());
        $this->assertSame($md5, c::hash('md5'));
        $this->assertNotSame(c::hash(), c::hash('md5'));
    }

    public function testIsHashed() {
        $manager = new CCrypt_HashManager();
        $this->assertTrue($manager->isHashed((new CCrypt_Hasher_BcryptHasher(['rounds' => 4]))->make('password')));
        $this->assertFalse($manager->isHashed('plain-text'));
        $this->assertFalse($manager->isHashed(md5('password')), 'md5 bukan hash password_*');
    }
}
