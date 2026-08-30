<?php
use PHPUnit\Framework\TestCase;

/**
 * Covers `CFunction_SerializableClosure` - the framework's own closure
 * serialization wrapper used by `CManager_DataProvider_ModelDataProvider`
 * and the `CElement_FormInput_SelectSearch` ajax pipeline to survive a
 * real serialize() -> temp file -> unserialize() round trip.
 */
class SerializableClosureTest extends TestCase {
    public function testClosureWithoutUseVariablesRoundTrips() {
        $wrapped = new CFunction_SerializableClosure(function ($x) {
            return $x * 2;
        });

        /** @var CFunction_SerializableClosure $restored */
        $restored = unserialize(serialize($wrapped));

        $this->assertSame(8, $restored(4));
    }

    public function testUseVariablesByValueRoundTrip() {
        $factor = 3;
        $wrapped = new CFunction_SerializableClosure(function ($x) use ($factor) {
            return $x * $factor;
        });

        $restored = unserialize(serialize($wrapped));

        $this->assertSame(9, $restored(3));
    }

    public function testGetClosureReturnsAnInvokableClosure() {
        $wrapped = new CFunction_SerializableClosure(function () {
            return 'ok';
        });

        $restored = unserialize(serialize($wrapped));

        $this->assertInstanceOf(Closure::class, $restored->getClosure());
        $this->assertSame('ok', ($restored->getClosure())());
    }

    /**
     * An instance-method closure that reads a private property needs its
     * declaring class rebound as "scope" on unserialize (see
     * NativeSerializer::__serialize()'s `$scope` handling) - this is the
     * normal, successful path the try/catch fallback must not break.
     */
    public function testInstanceMethodClosurePreservesPrivatePropertyAccessAfterRoundTrip() {
        $subject = new SerializableClosureTest_WithSecret('sekali lagi');
        $wrapped = new CFunction_SerializableClosure($subject->makeClosure());

        $restored = unserialize(serialize($wrapped));

        $this->assertSame('sekali lagi', $restored());
    }

    /**
     * Regression test for the 2026-07 production crash: PHP's reflection
     * engine can throw "Failed to retrieve the reflection object" out of
     * ReflectionFunctionAbstract::getClosureScopeClass() for some closures
     * (observed on PHP 8.4/LiteSpeed, not reliably reproducible on every
     * PHP build - which is why this test forces the failure directly via a
     * fake reflector rather than relying on hitting the real engine bug).
     * NativeSerializer::__serialize() must catch it and fall back to no
     * scope instead of crashing serialize().
     */
    public function testNativeSerializerFallsBackToNullScopeWhenReflectionCannotDetermineClosureScope() {
        $closure = function ($x) {
            return $x + 1;
        };
        $serializer = new SerializableClosureTest_FailingScopeReflectorSerializer($closure);

        $data = $serializer->__serialize();

        $this->assertNull($data['scope']);

        $restored = unserialize(serialize($serializer));

        $this->assertSame(5, $restored(4));
    }

    /**
     * Control test: a closure that genuinely needs its scope (private
     * property access) must still get a real scope class name when
     * reflection succeeds - the try/catch must only swallow the failure
     * case, not mask normal successful scope resolution.
     */
    public function testNativeSerializerCapturesScopeClassWhenReflectionSucceeds() {
        $subject = new SerializableClosureTest_WithSecret('rahasia');
        $serializer = new CFunction_SerializableClosure_Serializer_NativeSerializer($subject->makeClosure());

        $data = $serializer->__serialize();

        $this->assertSame(SerializableClosureTest_WithSecret::class, $data['scope']);
    }

    /**
     * A closure declared inside a class carries that class name as its scope.
     * When the payload is restored in a context that cannot load the class -
     * a queue worker running under another app - `bindTo()` only raises a
     * warning and returns null, which silently emptied the closure and blew
     * up later on the next `serialize()`. The scope must be dropped instead.
     */
    public function testUnserializeFallsBackToNullScopeWhenScopeClassIsNotLoadable() {
        $serializer = new CFunction_SerializableClosure_Serializer_NativeSerializer(function ($value) {
            return '[' . $value . ']';
        });

        $data = $serializer->__serialize();
        $data['scope'] = 'SerializableClosureTest_MissingScopeClass';

        $restored = (new ReflectionClass(CFunction_SerializableClosure_Serializer_NativeSerializer::class))
            ->newInstanceWithoutConstructor();
        $restored->__unserialize($data);

        $closure = $restored->getClosure();

        $this->assertInstanceOf(Closure::class, $closure);
        $this->assertSame('[abc]', $closure('abc'));
        $this->assertNotSame('', serialize($restored));
    }

    /**
     * Regression test for TB-13063: an instance-method closure implicitly
     * binds $this, and NativeSerializer::wrapClosures() used to walk that
     * bound object's properties via ReflectionObject::newInstanceWithoutConstructor()
     * + manual property copy. That approach silently drops CCarbon/DateTime's
     * internal engine state (it isn't a reflectable PHP property), producing
     * a "zombie" object that throws "DateTime object has not been correctly
     * initialized by its constructor" on the next method call - reproduced in
     * production via CElement_Component_DataTable::js() -> serialize($table)
     * when a column callback's bound object held a CCarbon property.
     */
    public function testInstanceMethodClosureSurvivesRoundTripWhenBoundObjectHoldsACarbonProperty() {
        $subject = new SerializableClosureTest_WithCarbon();
        $wrapped = new CFunction_SerializableClosure($subject->makeClosure());

        $restored = unserialize(serialize($wrapped));

        $this->assertSame('2026-01-01', $restored());
    }
}

class SerializableClosureTest_WithSecret {
    private $secret;

    public function __construct($secret) {
        $this->secret = $secret;
    }

    public function makeClosure() {
        return function () {
            return $this->secret;
        };
    }
}

class SerializableClosureTest_WithCarbon {
    public $when;

    public function __construct() {
        $this->when = new CCarbon('2026-01-01 00:00:00', new DateTimeZone('Asia/Jakarta'));
    }

    public function makeClosure() {
        return function () {
            return $this->when->format('Y-m-d');
        };
    }
}

/**
 * Forces `getReflector()->getClosureScopeClass()` to throw, simulating the
 * PHP engine edge case without depending on a specific PHP build to
 * reproduce it. `getCode()` (used by `isBindingRequired()`) legitimately
 * calls `getClosureScopeClass()` once on its own and must keep succeeding,
 * so only the *next* call - the explicit one `NativeSerializer::__serialize()`
 * makes to decide what to store as "scope" - is made to fail, mirroring the
 * production symptom of the same closure's reflection intermittently
 * breaking on repeated introspection rather than failing outright.
 */
class SerializableClosureTest_ThrowingScopeReflectionClosure extends CFunction_SerializableClosure_Support_ReflectionClosure {
    protected $callCount = 0;

    #[\ReturnTypeWillChange]
    public function getClosureScopeClass() {
        $this->callCount++;
        if ($this->callCount > 1) {
            throw new Error('Failed to retrieve the reflection object');
        }

        return parent::getClosureScopeClass();
    }
}

class SerializableClosureTest_FailingScopeReflectorSerializer extends CFunction_SerializableClosure_Serializer_NativeSerializer {
    public function getReflector() {
        if ($this->reflector === null) {
            $this->reflector = new SerializableClosureTest_ThrowingScopeReflectionClosure($this->getClosure());
        }

        return $this->reflector;
    }
}
