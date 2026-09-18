<?php
use PHPUnit\Framework\TestCase;

/**
 * Port AuthDatabaseUserProviderTest, AuthEloquentUserProviderTest, AuthPasswordBrokerTest dan
 * DatabaseTokenRepository hulu ke CAuth di atas SQLite in-memory dengan bcrypt sungguhan.
 */
class AuthUserProviderAndPasswordBrokerTest extends TestCase {
    const CONNECTION = 'uji_auth';

    /**
     * @var CDatabase_Connection
     */
    protected $connection;

    /**
     * @var CCrypt_Hasher_BcryptHasher
     */
    protected $hasher;

    protected function setUp(): void {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('butuh ekstensi pdo_sqlite');
        }
        $manager = CDatabase_Manager::instance();
        $manager->purge(static::CONNECTION);
        $manager->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], static::CONNECTION);
        $this->connection = $manager->connection(static::CONNECTION);
        $this->connection->statement('create table uji_auth_user (uji_auth_user_id integer primary key autoincrement, email varchar(100) null, name varchar(100) null, password varchar(255) null, remember_token varchar(100) null, created datetime null, updated datetime null, status integer not null default 1)');
        // DatabaseUserProvider mencari lewat Builder::find() (kolom <tabel>_id) tetapi GenericUser
        // melaporkan identifier 'id' - lihat testDatabaseProviderIdentifierColumnsDisagree; tabel
        // fixture memuat keduanya supaya jalur lain tetap bisa diuji
        $this->connection->statement('create table users (users_id integer primary key autoincrement, id integer null, email varchar(100) null, password varchar(255) null, remember_token varchar(100) null)');
        $this->connection->statement('create table password_reset (email varchar(100), token varchar(255), created_at datetime null)');
        $this->hasher = new CCrypt_Hasher_BcryptHasher(['rounds' => 4]);
        CCarbon::setTestNow(null);
        UjiAuth_User::$sentTokens = [];
    }

    protected function tearDown(): void {
        CCarbon::setTestNow(null);
        CDatabase_Manager::instance()->purge(static::CONNECTION);
    }

    /**
     * @return CAuth_UserProvider_DatabaseUserProvider
     */
    protected function databaseProvider() {
        return new CAuth_UserProvider_DatabaseUserProvider($this->connection, $this->hasher, 'users');
    }

    /**
     * @return CAuth_UserProvider_ModelUserProvider
     */
    protected function modelProvider() {
        return new CAuth_UserProvider_ModelUserProvider($this->hasher, UjiAuth_User::class);
    }

    /**
     * @param string $email
     * @param string $password
     *
     * @return int
     */
    protected function insertGenericUser($email = 'taylor@example.com', $password = 'secret') {
        $id = $this->connection->table('users')->insertGetId(['email' => $email, 'password' => $this->hasher->make($password), 'remember_token' => 'tok-1']);
        $this->connection->table('users')->where('users_id', $id)->update(['id' => $id]);

        return $id;
    }

    /**
     * @param string $email
     * @param string $password
     *
     * @return UjiAuth_User
     */
    protected function createModelUser($email = 'taylor@example.com', $password = 'secret') {
        $user = new UjiAuth_User();
        $user->email = $email;
        $user->name = 'Taylor';
        $user->password = $this->hasher->make($password);
        $user->remember_token = 'tok-1';
        $user->save();

        return $user;
    }

    // ---- DatabaseUserProvider ----

    public function testDatabaseRetrieveByIdReturnsGenericUserWhenUserIsFound() {
        $id = $this->insertGenericUser();
        $user = $this->databaseProvider()->retrieveById($id);
        $this->assertInstanceOf(CAuth_GenericUser::class, $user);
        $this->assertEquals($id, $user->getAuthIdentifier());
        $this->assertSame('taylor@example.com', $user->email);
        $this->assertSame('tok-1', $user->getRememberToken());
        $this->assertSame('id', $user->getAuthIdentifierName(), 'GenericUser memakai kolom id');
    }

    /**
     * Bukan perbaikan, hanya dokumentasi: retrieveById()/retrieveByToken() memakai Builder::find()
     * yang mencari kolom <tabel>_id, sedangkan CAuth_GenericUser::getAuthIdentifierName() = 'id'
     * dipakai updateRememberToken(). Tabel yang hanya punya salah satu kolom gagal di satu sisi.
     * Tidak ada app yang memakai provider 'database' (semua config auth mengomentarinya).
     */
    public function testDatabaseProviderIdentifierColumnsDisagree() {
        $this->connection->statement('create table members (id integer primary key autoincrement, email varchar(100) null, password varchar(255) null, remember_token varchar(100) null)');
        $id = $this->connection->table('members')->insertGetId(['email' => 'm@example.com', 'password' => 'x', 'remember_token' => 't']);
        $provider = new CAuth_UserProvider_DatabaseUserProvider($this->connection, $this->hasher, 'members');

        $this->assertNull($provider->retrieveById($id), 'tabel berkolom id tidak ditemukan karena find() mencari members_id');
        $user = $provider->retrieveByCredentials(['email' => 'm@example.com']);
        $this->assertSame('id', $user->getAuthIdentifierName());
        $this->assertEquals($id, $user->getAuthIdentifier());
    }

    public function testDatabaseRetrieveByIdReturnsNullWhenUserIsNotFound() {
        $this->assertNull($this->databaseProvider()->retrieveById(999));
    }

    public function testDatabaseRetrieveByTokenChecksTheRememberToken() {
        $id = $this->insertGenericUser();
        $provider = $this->databaseProvider();
        $this->assertInstanceOf(CAuth_GenericUser::class, $provider->retrieveByToken($id, 'tok-1'));
        $this->assertNull($provider->retrieveByToken($id, 'wrong'));
        $this->assertNull($provider->retrieveByToken(999, 'tok-1'));

        $this->connection->table('users')->where('users_id', $id)->update(['remember_token' => null]);
        $this->assertNull($provider->retrieveByToken($id, ''), 'token kosong di storage tidak pernah cocok');
    }

    public function testDatabaseUpdateRememberToken() {
        $id = $this->insertGenericUser();
        $provider = $this->databaseProvider();
        $user = $provider->retrieveById($id);
        $provider->updateRememberToken($user, 'tok-2');
        $this->assertSame('tok-2', $provider->retrieveById($id)->getRememberToken());
    }

    public function testDatabaseRetrieveByCredentials() {
        $this->insertGenericUser();
        $provider = $this->databaseProvider();
        $user = $provider->retrieveByCredentials(['email' => 'taylor@example.com', 'password' => 'ignored']);
        $this->assertInstanceOf(CAuth_GenericUser::class, $user);
        $this->assertNull($provider->retrieveByCredentials(['email' => 'nobody@example.com']));
    }

    public function testDatabaseRetrieveByCredentialsSkipsPasswordOnlyAndEmpty() {
        $this->insertGenericUser();
        $provider = $this->databaseProvider();
        $this->assertNull($provider->retrieveByCredentials([]));
        $this->assertNull($provider->retrieveByCredentials(['password' => 'secret']), 'hanya password → tidak menebak user pertama');
        $this->assertNull($provider->retrieveByCredentials(['password_confirmation' => 'secret', 'email' => 'nobody@example.com']));
    }

    public function testDatabaseRetrieveByCredentialsAcceptsArraysAndClosures() {
        $this->insertGenericUser('a@example.com');
        $this->insertGenericUser('b@example.com');
        $provider = $this->databaseProvider();
        $this->assertSame('a@example.com', $provider->retrieveByCredentials(['email' => ['a@example.com', 'zzz@example.com']])->email);
        $this->assertSame('b@example.com', $provider->retrieveByCredentials([function ($query) {
            $query->where('email', 'like', 'b@%');
        }])->email);
    }

    public function testDatabaseValidateCredentials() {
        $id = $this->insertGenericUser('taylor@example.com', 'secret');
        $provider = $this->databaseProvider();
        $user = $provider->retrieveById($id);
        $this->assertTrue($provider->validateCredentials($user, ['password' => 'secret']));
        $this->assertFalse($provider->validateCredentials($user, ['password' => 'wrong']));
        $this->assertSame($this->hasher, $provider->hasher());
    }

    // ---- ModelUserProvider ----

    public function testModelRetrieveByIdAndObject() {
        $created = $this->createModelUser();
        $provider = $this->modelProvider();
        $user = $provider->retrieveById($created->getKey());
        $this->assertInstanceOf(UjiAuth_User::class, $user);
        $this->assertSame('uji_auth_user_id', $user->getAuthIdentifierName(), 'identifier = primary key model');
        $this->assertEquals($created->getKey(), $user->getAuthIdentifier());
        $this->assertNull($provider->retrieveById(999));

        $this->assertInstanceOf(UjiAuth_User::class, $provider->retrieveByObject((object) ['uji_auth_user_id' => $created->getKey()]));
        $this->assertNull($provider->retrieveByObject((object) ['other' => 1]));
    }

    public function testModelRetrieveByTokenAndUpdateRememberToken() {
        $created = $this->createModelUser();
        $provider = $this->modelProvider();
        $this->assertInstanceOf(UjiAuth_User::class, $provider->retrieveByToken($created->getKey(), 'tok-1'));
        $this->assertNull($provider->retrieveByToken($created->getKey(), 'wrong'));

        $provider->updateRememberToken($created, 'tok-2');
        $this->assertSame('tok-2', $created->fresh()->remember_token);
        $this->assertSame('tok-2', $created->getRememberToken(), 'instance yang diberikan ikut diperbarui');
    }

    public function testModelRetrieveByCredentialsAndValidate() {
        $this->createModelUser('taylor@example.com', 'secret');
        $provider = $this->modelProvider();
        $user = $provider->retrieveByCredentials(['email' => 'taylor@example.com', 'password' => 'secret']);
        $this->assertInstanceOf(UjiAuth_User::class, $user);
        $this->assertTrue($provider->validateCredentials($user, ['password' => 'secret']));
        $this->assertFalse($provider->validateCredentials($user, ['password' => 'wrong']));
        $this->assertNull($provider->retrieveByCredentials(['password' => 'secret']));
        $this->assertNull($provider->retrieveByCredentials([]));
    }

    public function testModelProviderAccessors() {
        $provider = $this->modelProvider();
        $this->assertSame(UjiAuth_User::class, $provider->getModel());
        $this->assertInstanceOf(UjiAuth_User::class, $provider->createModel());
        $this->assertSame($this->hasher, $provider->getHasher());
        $other = new CCrypt_Hasher_Md5Hasher();
        $this->assertSame($provider, $provider->setHasher($other));
        $this->assertSame($other, $provider->getHasher());
        $this->assertSame($provider, $provider->setModel(UjiAuth_User::class));
    }

    // ---- DatabaseTokenRepository ----

    /**
     * @param int $expires
     * @param int $throttle
     *
     * @return CAuth_Password_DatabaseTokenRepository
     */
    protected function tokens($expires = 60, $throttle = 60) {
        return new CAuth_Password_DatabaseTokenRepository($this->connection, $this->hasher, 'password_reset', 'uji-hash-key', $expires, $throttle);
    }

    public function testTokenRepositoryCreateInsertsHashedTokenAndReplacesExisting() {
        $user = $this->createModelUser();
        $repo = $this->tokens();
        $first = $repo->create($user);
        $this->assertSame(64, strlen($first), 'token = hmac sha256 hex');
        $second = $repo->create($user);
        $this->assertNotSame($first, $second);
        $rows = $this->connection->table('password_reset')->where('email', 'taylor@example.com')->get();
        $this->assertCount(1, $rows, 'token lama dihapus saat membuat yang baru');
        $row = (array) $rows->first();
        $this->assertNotSame($second, $row['token'], 'token disimpan sebagai hash');
        $this->assertTrue($this->hasher->check($second, $row['token']));
        $this->assertTrue($repo->exists($user, $second));
        $this->assertFalse($repo->exists($user, $first));
    }

    public function testTokenRepositoryExistsRespectsExpiry() {
        $user = $this->createModelUser();
        $repo = $this->tokens(60);
        $token = $repo->create($user);
        $this->assertTrue($repo->exists($user, $token));
        $this->connection->table('password_reset')->update(['created_at' => CCarbon::now()->subMinutes(61)->toDateTimeString()]);
        $this->assertFalse($repo->exists($user, $token), 'lebih tua dari expires menit → tidak sah');
        $this->assertFalse($repo->exists($user, 'wrong'));
    }

    public function testTokenRepositoryRecentlyCreatedTokenAndDelete() {
        $user = $this->createModelUser();
        $repo = $this->tokens(60, 60);
        $this->assertFalse($repo->recentlyCreatedToken($user));
        $repo->create($user);
        $this->assertTrue($repo->recentlyCreatedToken($user));
        $this->connection->table('password_reset')->update(['created_at' => CCarbon::now()->subSeconds(61)->toDateTimeString()]);
        $this->assertFalse($repo->recentlyCreatedToken($user));
        $repo->delete($user);
        $this->assertSame(0, $this->connection->table('password_reset')->count());
        $this->assertFalse($this->tokens(60, 0)->recentlyCreatedToken($user), 'throttle 0 = tidak pernah dianggap baru');
    }

    public function testTokenRepositoryDeleteExpired() {
        $user = $this->createModelUser();
        $repo = $this->tokens(60);
        $repo->create($user);
        $this->connection->table('password_reset')->insert(['email' => 'old@example.com', 'token' => 'x', 'created_at' => CCarbon::now()->subHours(2)->toDateTimeString()]);
        $repo->deleteExpired();
        $this->assertSame(['taylor@example.com'], $this->connection->table('password_reset')->pluck('email')->all());
        $this->assertSame($this->connection, $repo->getConnection());
        $this->assertSame($this->hasher, $repo->getHasher());
    }

    // ---- Password Broker ----

    /**
     * @return CAuth_Password_Broker
     */
    protected function broker() {
        return new CAuth_Password_Broker($this->tokens(), $this->modelProvider());
    }

    public function testBrokerSendResetLinkReturnsInvalidUserWhenNotFound() {
        $this->assertSame(CAuth_Password_Broker::INVALID_USER, $this->broker()->sendResetLink(['email' => 'nobody@example.com']));
        $this->assertSame([], UjiAuth_User::$sentTokens);
    }

    public function testBrokerSendResetLinkSendsNotificationWithTheToken() {
        $user = $this->createModelUser();
        $broker = $this->broker();
        $this->assertSame(CAuth_Password_Broker::RESET_LINK_SENT, $broker->sendResetLink(['email' => 'taylor@example.com']));
        $this->assertCount(1, UjiAuth_User::$sentTokens);
        $token = UjiAuth_User::$sentTokens[0];
        $this->assertTrue($broker->tokenExists($user, $token));
    }

    public function testBrokerSendResetLinkUsesTheCallbackInsteadOfNotification() {
        $this->createModelUser();
        $seen = null;
        $result = $this->broker()->sendResetLink(['email' => 'taylor@example.com'], function ($user, $token) use (&$seen) {
            $seen = [$user->email, $token];
        });
        $this->assertSame(CAuth_Password_Broker::RESET_LINK_SENT, $result);
        $this->assertSame('taylor@example.com', $seen[0]);
        $this->assertSame(64, strlen($seen[1]));
        $this->assertSame([], UjiAuth_User::$sentTokens, 'notifikasi bawaan tidak dikirim');
    }

    public function testBrokerSendResetLinkIsThrottled() {
        $this->createModelUser();
        $broker = $this->broker();
        $this->assertSame(CAuth_Password_Broker::RESET_LINK_SENT, $broker->sendResetLink(['email' => 'taylor@example.com']));
        $this->assertSame(CAuth_Password_Broker::RESET_THROTTLED, $broker->sendResetLink(['email' => 'taylor@example.com']), 'token yang baru dibuat menahan permintaan berikutnya');
        $this->assertCount(1, UjiAuth_User::$sentTokens);
    }

    public function testBrokerGetUserThrowsWhenUserCannotResetPassword() {
        $this->insertGenericUser();
        $broker = new CAuth_Password_Broker($this->tokens(), $this->databaseProvider());
        $this->expectException(UnexpectedValueException::class);
        $broker->getUser(['email' => 'taylor@example.com']);
    }

    public function testBrokerGetUserStripsTheToken() {
        $this->createModelUser();
        $user = $this->broker()->getUser(['email' => 'taylor@example.com', 'token' => 'anything']);
        $this->assertInstanceOf(UjiAuth_User::class, $user);
    }

    public function testBrokerResetReturnsInvalidUserOrInvalidToken() {
        $this->createModelUser();
        $broker = $this->broker();
        $called = false;
        $callback = function () use (&$called) {
            $called = true;
        };
        $this->assertSame(CAuth_Password_Broker::INVALID_USER, $broker->reset(['email' => 'nobody@example.com', 'token' => 'x', 'password' => 'new'], $callback));
        $this->assertSame(CAuth_Password_Broker::INVALID_TOKEN, $broker->reset(['email' => 'taylor@example.com', 'token' => 'x', 'password' => 'new'], $callback));
        $this->assertFalse($called);
    }

    public function testBrokerResetCallsCallbackAndDeletesToken() {
        $user = $this->createModelUser();
        $broker = $this->broker();
        $token = $broker->createToken($user);
        $seen = null;
        $result = $broker->reset(['email' => 'taylor@example.com', 'token' => $token, 'password' => 'new-secret', 'password_confirmation' => 'new-secret'], function ($user, $password) use (&$seen) {
            $seen = [$user->email, $password];
        });
        $this->assertSame(CAuth_Password_Broker::PASSWORD_RESET, $result);
        $this->assertSame(['taylor@example.com', 'new-secret'], $seen);
        $this->assertFalse($broker->tokenExists($user, $token), 'token dihapus setelah reset');
        $this->assertSame(0, $this->connection->table('password_reset')->count());
    }

    public function testBrokerCreateAndDeleteToken() {
        $user = $this->createModelUser();
        $broker = $this->broker();
        $token = $broker->createToken($user);
        $this->assertTrue($broker->tokenExists($user, $token));
        $broker->deleteToken($user);
        $this->assertFalse($broker->tokenExists($user, $token));
        $this->assertInstanceOf(CAuth_Password_DatabaseTokenRepository::class, $broker->getRepository());
    }
}

class UjiAuth_User extends CModel implements CAuth_AuthenticatableInterface, CAuth_Contract_CanResetPasswordInterface {
    use CAuth_Concern_AuthenticatableTrait;

    /**
     * @var array
     */
    public static $sentTokens = [];

    protected $table = 'uji_auth_user';

    protected $connection = 'uji_auth';

    protected $guarded = [];

    public function getEmailForPasswordReset() {
        return $this->email;
    }

    public function sendPasswordResetNotification($token) {
        static::$sentTokens[] = $token;
    }
}
