<?php

use PHPUnit\Framework\TestCase;

/**
 * CAuth_Access_Gate - padanan suite hulu untuk Gate. Pengguna diberikan lewat forUser() supaya
 * tidak bergantung pada guard/sesi yang aktif.
 */
class AuthAccessGateTest extends TestCase {
    /**
     * @param null|CAuth_AuthenticatableInterface $user
     *
     * @return CAuth_Access_Gate
     */
    private function gate($user = null) {
        if ($user === null) {
            $user = new CAuth_GenericUser(['id' => 1, 'name' => 'Hery']);
        }

        return (new CAuth_Access_Gate())->forUser($user);
    }

    public function testBasicClosuresCanBeDefined() {
        $gate = $this->gate();
        $gate->define('foo', function ($user) {
            return true;
        });
        $gate->define('bar', function ($user) {
            return false;
        });

        $this->assertTrue($gate->check('foo'));
        $this->assertTrue($gate->allows('foo'));
        $this->assertFalse($gate->denies('foo'));
        $this->assertFalse($gate->check('bar'));
        $this->assertTrue($gate->denies('bar'));
    }

    public function testHasAbilities() {
        $gate = $this->gate();
        $gate->define('foo', function () {
            return true;
        });

        $this->assertTrue($gate->has('foo'));
        $this->assertFalse($gate->has('bar'));
        $this->assertFalse($gate->has(['foo', 'bar']));
        $this->assertSame(['foo'], array_keys($gate->abilities()));
    }

    public function testUndefinedAbilityIsDenied() {
        $this->assertFalse($this->gate()->check('tidak-ada'));
    }

    public function testArgumentsArePassedToTheCallback() {
        $gate = $this->gate();
        $gate->define('foo', function ($user, $arg1, $arg2) {
            return $user->name === 'Hery' && $arg1 === 'a' && $arg2 === 'b';
        });

        $this->assertTrue($gate->check('foo', ['a', 'b']));
        $this->assertFalse($gate->check('foo', ['a', 'c']));
    }

    public function testAnyAndNone() {
        $gate = $this->gate();
        $gate->define('edit', function ($user) {
            return true;
        });
        $gate->define('delete', function ($user) {
            return false;
        });

        $this->assertTrue($gate->any(['edit', 'delete']));
        $this->assertFalse($gate->any(['delete']));
        $this->assertTrue($gate->none(['delete']));
        $this->assertFalse($gate->none(['edit', 'delete']));
        $this->assertFalse($gate->check(['edit', 'delete']), 'check() dengan larik menuntut semuanya lolos');
    }

    public function testBeforeCallbacksCanOverrideResult() {
        $gate = $this->gate();
        $gate->define('foo', function ($user) {
            return false;
        });
        $gate->before(function ($user, $ability) {
            $this->assertSame('foo', $ability);

            return true;
        });

        $this->assertTrue($gate->check('foo'));
    }

    public function testBeforeCallbacksDontInterruptGateCheckIfNoValueIsReturned() {
        $gate = $this->gate();
        $gate->define('foo', function ($user) {
            return true;
        });
        $gate->before(function () {
        });

        $this->assertTrue($gate->check('foo'));
    }

    public function testAfterCallbacksAreCalledWithResult() {
        $gate = $this->gate();
        $gate->define('foo', function ($user) {
            return true;
        });
        $gate->define('bar', function ($user) {
            return false;
        });
        $gate->after(function ($user, $ability, $result) {
            if ($ability === 'foo') {
                $this->assertTrue($result);
            } elseif ($ability === 'bar') {
                $this->assertFalse($result);
            }
        });

        $this->assertTrue($gate->check('foo'));
        $this->assertFalse($gate->check('bar'));
    }

    /**
     * Beda dari hulu: hasil false dari callback ability masih bisa dibalik oleh callback after
     * (`$result ?: $afterResult`), hulu hanya mengisi yang null. Lihat docs/NOTES.md.
     */
    public function testAfterCallbacksOverrideAFalseResult() {
        $gate = $this->gate();
        $gate->define('deny', function ($user) {
            return false;
        });
        $gate->define('allow', function ($user) {
            return true;
        });
        $gate->after(function ($user, $ability, $result) {
            return !$result;
        });

        $this->assertTrue($gate->allows('allow'));
        $this->assertTrue($gate->allows('deny'));
    }

    public function testAfterCallbackCanDecideWhenAbilityIsUndefined() {
        $gate = $this->gate();
        $gate->after(function ($user, $ability, $result) {
            return $ability === 'undefined';
        });

        $this->assertTrue($gate->check('undefined'));
        $this->assertFalse($gate->check('other'));
    }

    public function testResponseObjectsCarryMessageAndCode() {
        $gate = $this->gate();
        $gate->define('foo', function ($user) {
            return CAuth_Access_Response::allow('boleh');
        });
        $gate->define('bar', function ($user) {
            return CAuth_Access_Response::deny('tidak boleh', 'kode-x');
        });

        $this->assertTrue($gate->check('foo'));
        $this->assertFalse($gate->check('bar'));

        $response = $gate->inspect('bar');
        $this->assertInstanceOf(CAuth_Access_Response::class, $response);
        $this->assertTrue($response->denied());
        $this->assertFalse($response->allowed());
        $this->assertSame('tidak boleh', $response->message());
        $this->assertSame('kode-x', $response->code());
        $this->assertSame('tidak boleh', (string) $response);

        $this->assertSame('boleh', $gate->inspect('foo')->message());
    }

    public function testAuthorizeReturnsAllowedResponseOrThrows() {
        $gate = $this->gate();
        $gate->define('foo', function ($user) {
            return true;
        });
        $gate->define('bar', function ($user) {
            return CAuth_Access_Response::deny('dilarang', 3);
        });

        $this->assertTrue($gate->authorize('foo')->allowed());

        try {
            $gate->authorize('bar');
            $this->fail('seharusnya melempar AuthorizationException');
        } catch (CAuth_Exception_AuthorizationException $e) {
            $this->assertSame('dilarang', $e->getMessage());
            $this->assertSame(3, $e->getCode());
            $this->assertSame('dilarang', $e->response()->message());
        }
    }

    public function testAuthorizeOnUndefinedAbilityThrowsWithDefaultMessage() {
        $this->expectException(CAuth_Exception_AuthorizationException::class);
        $this->expectExceptionMessage('This action is unauthorized.');
        $this->gate()->authorize('tidak-ada');
    }

    public function testRawReturnsTheUnderlyingResult() {
        $gate = $this->gate();
        $gate->define('foo', function ($user) {
            return true;
        });
        $gate->define('bar', function ($user) {
            return 'tidak-boolean';
        });

        $this->assertTrue($gate->raw('foo'));
        $this->assertSame('tidak-boolean', $gate->raw('bar'));
        $this->assertNull($gate->raw('undefined'));
    }

    public function testStringCallbacksAreResolved() {
        $gate = $this->gate();
        $gate->define('foo', AuthAccessGateTestClass::class . '@foo');
        $gate->define('bar', [AuthAccessGateTestClass::class, 'bar']);

        $this->assertTrue($gate->check('foo'));
        $this->assertFalse($gate->check('bar'));
    }

    public function testPoliciesAreResolvedForObjectsAndClassNames() {
        $gate = $this->gate();
        $gate->policy(AuthAccessGateTestDummy::class, AuthAccessGateTestPolicy::class);

        $this->assertTrue($gate->check('update', new AuthAccessGateTestDummy()));
        $this->assertFalse($gate->check('delete', new AuthAccessGateTestDummy()));
        $this->assertTrue($gate->check('create', AuthAccessGateTestDummy::class));
        $this->assertSame(['dummy' => true], $gate->raw('extras', [new AuthAccessGateTestDummy(), 'dummy']));

        $this->assertInstanceOf(AuthAccessGateTestPolicy::class, $gate->getPolicyFor(new AuthAccessGateTestDummy()));
        $this->assertInstanceOf(AuthAccessGateTestPolicy::class, $gate->getPolicyFor(AuthAccessGateTestDummy::class));
        $this->assertNull($gate->getPolicyFor(stdClass::class));
        $this->assertSame([AuthAccessGateTestDummy::class => AuthAccessGateTestPolicy::class], $gate->policies());
    }

    public function testPolicyMethodMissingIsDenied() {
        $gate = $this->gate();
        $gate->policy(AuthAccessGateTestDummy::class, AuthAccessGateTestPolicy::class);

        $this->assertFalse($gate->check('tidakAdaMethod', new AuthAccessGateTestDummy()));
    }

    public function testPolicyBeforeMethodCanOverride() {
        $gate = $this->gate();
        $gate->policy(AuthAccessGateTestDummy::class, AuthAccessGateTestPolicyWithBefore::class);

        //before() mengembalikan true untuk pengguna id 1, jadi delete yang aslinya ditolak ikut lolos
        $this->assertTrue($gate->check('delete', new AuthAccessGateTestDummy()));

        $other = $this->gate(new CAuth_GenericUser(['id' => 2, 'name' => 'Budi']));
        $other->policy(AuthAccessGateTestDummy::class, AuthAccessGateTestPolicyWithBefore::class);
        $this->assertFalse($other->check('delete', new AuthAccessGateTestDummy()));
    }

    public function testResourceRegistersTheStandardAbilities() {
        $gate = $this->gate();
        $gate->resource('post', AuthAccessGateTestResource::class);

        $this->assertTrue($gate->has(['post.viewAny', 'post.view', 'post.create', 'post.update', 'post.delete']));
        $this->assertTrue($gate->check('post.view', new AuthAccessGateTestDummy()));
        $this->assertFalse($gate->check('post.delete', new AuthAccessGateTestDummy()));

        $gate->resource('photo', AuthAccessGateTestResource::class, ['view' => 'view']);
        $this->assertTrue($gate->has('photo.view'));
        $this->assertFalse($gate->has('photo.delete'));
    }

    public function testGuestUserIsDeniedUnlessTheCallbackAcceptsNull() {
        $gate = (new CAuth_Access_Gate())->forUser(null);
        $gate->define('strict', function ($user) {
            return true;
        });
        $gate->define('loose', function (?CAuth_AuthenticatableInterface $user = null) {
            return $user === null;
        });

        $this->assertFalse($gate->check('strict'), 'callback tanpa parameter nullable tidak dipanggil untuk tamu');
        $this->assertTrue($gate->check('loose'));
    }

    public function testForUserReturnsANewGateWithTheSameDefinitions() {
        $gate = $this->gate();
        $gate->define('owner', function ($user) {
            return $user->id === 1;
        });

        $budi = $gate->forUser(new CAuth_GenericUser(['id' => 2]));
        $this->assertNotSame($gate, $budi);
        $this->assertTrue($gate->check('owner'));
        $this->assertFalse($budi->check('owner'));
    }

    public function testResponseFactoriesAndArrayForm() {
        $allow = CAuth_Access_Response::allow();
        $deny = CAuth_Access_Response::deny('no', 7);

        $this->assertTrue($allow->allowed());
        $this->assertNull($allow->message());
        $this->assertSame(['allowed' => true, 'message' => null, 'code' => null], $allow->toArray());
        $this->assertSame(['allowed' => false, 'message' => 'no', 'code' => 7], $deny->toArray());
        $this->assertSame($allow, $allow->authorize());
    }
}

class AuthAccessGateTestClass {
    public function foo($user) {
        return true;
    }

    public function bar($user) {
        return false;
    }
}

class AuthAccessGateTestDummy {
}

class AuthAccessGateTestPolicy {
    public function create($user) {
        return true;
    }

    public function update($user, AuthAccessGateTestDummy $dummy) {
        return true;
    }

    public function delete($user, AuthAccessGateTestDummy $dummy) {
        return false;
    }

    public function extras($user, AuthAccessGateTestDummy $dummy, $extra) {
        return [$extra => true];
    }
}

class AuthAccessGateTestPolicyWithBefore extends AuthAccessGateTestPolicy {
    public function before($user, $ability) {
        if ($user->id === 1) {
            return true;
        }
    }
}

class AuthAccessGateTestResource {
    public function viewAny($user) {
        return true;
    }

    public function view($user, $dummy) {
        return true;
    }

    public function create($user) {
        return true;
    }

    public function update($user, $dummy) {
        return true;
    }

    public function delete($user, $dummy) {
        return false;
    }
}
