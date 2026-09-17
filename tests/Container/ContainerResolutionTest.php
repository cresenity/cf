<?php

use PHPUnit\Framework\TestCase;

/**
 * CContainer_Container - lanjutan suite hulu yang belum tercakup ContainerTest: alias berantai,
 * rebinding, parameter override bertingkat, pesan exception, get() PSR, dan flush.
 */
class ContainerResolutionTest extends TestCase {
    protected function tearDown(): void {
        CContainer_Container::setInstance(null);
    }

    public function testSetInstanceAndGetInstance() {
        $container = CContainer_Container::setInstance(new CContainer_Container());
        $this->assertSame($container, CContainer_Container::getInstance());

        CContainer_Container::setInstance(null);
        $fresh = CContainer_Container::getInstance();
        $this->assertInstanceOf(CContainer_Container::class, $fresh);
        $this->assertNotSame($container, $fresh);
    }

    public function testSingletonIfDoesNotRegisterWhenAlreadyBound() {
        $container = new CContainer_Container();
        $container->singleton('class', function () {
            return new stdClass();
        });
        $first = $container->make('class');
        $container->singletonIf('class', function () {
            return new ContainerResolutionTestConcrete();
        });

        $this->assertSame($first, $container->make('class'));
    }

    public function testSingletonIfRegistersWhenNotBoundYet() {
        $container = new CContainer_Container();
        $container->singletonIf('other', function () {
            return new stdClass();
        });

        $this->assertSame($container->make('other'), $container->make('other'));
    }

    public function testSharedConcreteResolution() {
        $container = new CContainer_Container();
        $container->singleton(ContainerResolutionTestConcrete::class);

        $this->assertSame($container->make(ContainerResolutionTestConcrete::class), $container->make(ContainerResolutionTestConcrete::class));
    }

    public function testContainerIsPassedToResolvers() {
        $container = new CContainer_Container();
        $container->bind('something', function ($c) {
            return $c;
        });

        $this->assertSame($container, $container->make('something'));
    }

    public function testArrayAccessWithANonClosureValue() {
        $container = new CContainer_Container();
        $container['something'] = 'teks';

        $this->assertTrue(isset($container['something']));
        $this->assertSame('teks', $container['something']);
        unset($container['something']);
        $this->assertFalse(isset($container['something']));
    }

    public function testAliasesChain() {
        $container = new CContainer_Container();
        $container['foo'] = 'bar';
        $container->alias('foo', 'baz');
        $container->alias('baz', 'bat');

        $this->assertSame('bar', $container->make('foo'));
        $this->assertSame('bar', $container->make('baz'));
        $this->assertSame('bar', $container->make('bat'));
    }

    public function testAliasesWithArrayOfParameters() {
        $container = new CContainer_Container();
        $container->bind('foo', function ($app, $config) {
            return $config;
        });
        $container->alias('foo', 'baz');

        $this->assertEquals([1, 2, 3], $container->make('baz', [1, 2, 3]));
    }

    public function testBindingsCanBeOverridden() {
        $container = new CContainer_Container();
        $container['foo'] = 'bar';
        $container['foo'] = 'baz';

        $this->assertSame('baz', $container['foo']);
    }

    public function testInstanceReturnsTheInstance() {
        $container = new CContainer_Container();
        $bound = new stdClass();

        $this->assertSame($bound, $container->instance('foo', $bound));
        $this->assertSame($bound, $container->make('foo'));
    }

    public function testOptionalClassDependencyStaysNullWhenNotBoundAndNotBuildable() {
        $container = new CContainer_Container();
        $instance = $container->make(ContainerResolutionTestOptionalContract::class);

        $this->assertNull($instance->impl, 'interface tanpa binding pada parameter opsional → nilai default');

        $container->bind(ContainerResolutionTestContract::class, ContainerResolutionTestImplementation::class);
        $this->assertInstanceOf(ContainerResolutionTestImplementation::class, $container->make(ContainerResolutionTestOptionalContract::class)->impl);
    }

    public function testBoundOnlyReportsTheAbstractSide() {
        $container = new CContainer_Container();
        $container->bind(ContainerResolutionTestContract::class, ContainerResolutionTestConcrete::class);

        $this->assertTrue($container->bound(ContainerResolutionTestContract::class));
        $this->assertFalse($container->bound(ContainerResolutionTestConcrete::class));
    }

    public function testUnsetRemovesBoundInstances() {
        $container = new CContainer_Container();
        $container->instance('object', new stdClass());
        unset($container['object']);

        $this->assertFalse($container->bound('object'));
    }

    public function testBoundInstanceAndAliasCheckViaArrayAccess() {
        $container = new CContainer_Container();
        $container->instance('object', new stdClass());
        $container->alias('object', 'alias');

        $this->assertTrue(isset($container['object']));
        $this->assertTrue(isset($container['alias']));
    }

    public function testReboundListenersFireWhenABindingIsReplaced() {
        $container = new CContainer_Container();
        $container->bind('foo', function () {
            return 'pertama';
        });
        $seen = [];
        $container->rebinding('foo', function ($app, $instance) use (&$seen) {
            $seen[] = $instance;
        });
        $container->bind('foo', function () {
            return 'kedua';
        });

        $this->assertSame(['kedua'], $seen);
    }

    public function testReboundListenersFireOnInstances() {
        $container = new CContainer_Container();
        $container->instance('foo', 'pertama');
        $fired = false;
        $container->rebinding('foo', function () use (&$fired) {
            $fired = true;
        });
        $container->instance('foo', 'kedua');

        $this->assertTrue($fired);
    }

    public function testReboundListenersOnInstancesOnlyFireIfAlreadyBound() {
        $container = new CContainer_Container();
        $fired = false;
        $container->rebinding('foo', function () use (&$fired) {
            $fired = true;
        });
        $container->instance('foo', 'pertama');

        $this->assertFalse($fired);
    }

    public function testRefreshCallsTheTargetMethodOnRebind() {
        $container = new CContainer_Container();
        $container->bind('foo', function () {
            return 'pertama';
        });
        $target = new ContainerResolutionTestRefreshTarget();
        $container->refresh('foo', $target, 'setValue');
        $container->bind('foo', function () {
            return 'kedua';
        });

        $this->assertSame('kedua', $target->value);
    }

    public function testUnresolvablePrimitiveMessage() {
        $container = new CContainer_Container();

        $this->expectException(CContainer_Exception_BindingResolutionException::class);
        $this->expectExceptionMessage('Unresolvable dependency resolving [Parameter #0 [ <required> $first ]] in class ContainerResolutionTestMixedPrimitive');
        $container->make(ContainerResolutionTestMixedPrimitive::class);
    }

    public function testNotInstantiableMessage() {
        $container = new CContainer_Container();

        $this->expectException(CContainer_Exception_BindingResolutionException::class);
        $this->expectExceptionMessage('Target [ContainerResolutionTestContract] is not instantiable.');
        $container->make(ContainerResolutionTestContract::class);
    }

    public function testNotInstantiableMessageIncludesTheBuildStack() {
        $container = new CContainer_Container();

        $this->expectException(CContainer_Exception_BindingResolutionException::class);
        $this->expectExceptionMessage('Target [ContainerResolutionTestContract] is not instantiable while building [ContainerResolutionTestDependent].');
        $container->make(ContainerResolutionTestDependent::class);
    }

    public function testMissingClassMessage() {
        $container = new CContainer_Container();

        $this->expectException(CContainer_Exception_BindingResolutionException::class);
        $this->expectExceptionMessage('Target class [Foo\Bar\Baz\DummyClass] does not exist.');
        $container->build('Foo\Bar\Baz\DummyClass');
    }

    public function testForgetInstanceAffectsIsShared() {
        $container = new CContainer_Container();
        $container->instance(ContainerResolutionTestConcrete::class, new ContainerResolutionTestConcrete());

        $this->assertTrue($container->isShared(ContainerResolutionTestConcrete::class));
        $container->forgetInstance(ContainerResolutionTestConcrete::class);
        $this->assertFalse($container->isShared(ContainerResolutionTestConcrete::class));
    }

    public function testForgetInstancesForgetsAllInstances() {
        $container = new CContainer_Container();
        $container->instance('Instance1', new stdClass());
        $container->instance('Instance2', new stdClass());
        $this->assertTrue($container->isShared('Instance1'));
        $this->assertTrue($container->isShared('Instance2'));

        $container->forgetInstances();
        $this->assertFalse($container->isShared('Instance1'));
        $this->assertFalse($container->isShared('Instance2'));
    }

    public function testFlushClearsBindingsAliasesAndResolvedFlags() {
        $container = new CContainer_Container();
        $container->bind('ConcreteStub', function () {
            return new ContainerResolutionTestConcrete();
        }, true);
        $container->alias('ConcreteStub', 'AliasStub');
        $container->make('ConcreteStub');

        $this->assertTrue($container->resolved('ConcreteStub'));
        $this->assertTrue($container->isAlias('AliasStub'));
        $this->assertArrayHasKey('ConcreteStub', $container->getBindings());
        $this->assertTrue($container->isShared('ConcreteStub'));

        $container->flush();
        $this->assertFalse($container->resolved('ConcreteStub'));
        $this->assertFalse($container->isAlias('AliasStub'));
        $this->assertEmpty($container->getBindings());
        $this->assertFalse($container->isShared('ConcreteStub'));
    }

    public function testResolvedResolvesAliasToBindingNameBeforeChecking() {
        $container = new CContainer_Container();
        $container->bind('ConcreteStub', function () {
            return new ContainerResolutionTestConcrete();
        }, true);
        $container->alias('ConcreteStub', 'foo');

        $this->assertFalse($container->resolved('ConcreteStub'));
        $this->assertFalse($container->resolved('foo'));
        $container->make('ConcreteStub');
        $this->assertTrue($container->resolved('ConcreteStub'));
        $this->assertTrue($container->resolved('foo'));
    }

    public function testGetAliasIsRecursive() {
        $container = new CContainer_Container();
        $container->alias('ConcreteStub', 'foo');
        $container->alias('foo', 'bar');
        $container->alias('bar', 'baz');

        $this->assertSame('ConcreteStub', $container->getAlias('foo'));
        $this->assertSame('ConcreteStub', $container->getAlias('baz'));
        $this->assertTrue($container->isAlias('baz'));
        $this->assertTrue($container->isAlias('bar'));
        $this->assertTrue($container->isAlias('foo'));
        $this->assertFalse($container->isAlias('ConcreteStub'));
    }

    public function testAliasingToItselfIsRejectedWhenResolved() {
        //hulu menolak sudah di alias(); CF baru menolak saat getAlias() dipanggil
        $container = new CContainer_Container();
        $container->alias('name', 'name');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('[name] is aliased to itself.');
        $container->getAlias('name');
    }

    public function testFactoryMatchesMake() {
        $container = new CContainer_Container();
        $container->bind('name', function () {
            return new stdClass();
        });
        $factory = $container->factory('name');

        $this->assertEquals($container->make('name'), $factory());
        $this->assertNotSame($container->make('name'), $factory(), 'binding biasa → objek baru tiap panggilan');
    }

    public function testMakeWithIsAnAliasForMake() {
        $container = new CContainer_Container();
        $instance = $container->makeWith(ContainerResolutionTestDefaultValue::class, ['default' => 'ubah']);

        $this->assertSame('ubah', $instance->default);
    }

    public function testResolvingWithArrayOfMixedParameters() {
        $container = new CContainer_Container();
        $instance = $container->make(ContainerResolutionTestMixedPrimitive::class, ['first' => 1, 'last' => 2, 'third' => 3]);

        $this->assertSame(1, $instance->first);
        $this->assertInstanceOf(ContainerResolutionTestConcrete::class, $instance->stub);
        $this->assertSame(2, $instance->last);
        $this->assertFalse(isset($instance->third));
    }

    public function testResolvingWithParametersThroughAnInterface() {
        $container = new CContainer_Container();
        $container->bind(ContainerResolutionTestContract::class, ContainerResolutionTestInjectVariable::class);
        $instance = $container->make(ContainerResolutionTestContract::class, ['something' => 'nilai']);

        $this->assertSame('nilai', $instance->something);
    }

    public function testNestedParameterOverride() {
        $container = new CContainer_Container();
        $container->bind('foo', function ($app, $config) {
            return $app->make('bar', ['name' => 'Cresenity']);
        });
        $container->bind('bar', function ($app, $config) {
            return $config;
        });

        $this->assertEquals(['name' => 'Cresenity'], $container->make('foo', ['something']));
    }

    public function testNestedParametersAreResetForAFreshMake() {
        $container = new CContainer_Container();
        $container->bind('foo', function ($app, $config) {
            return $app->make('bar');
        });
        $container->bind('bar', function ($app, $config) {
            return $config;
        });

        $this->assertSame([], $container->make('foo', ['something']));
    }

    public function testSingletonBindingsAreNotRespectedWithMakeParameters() {
        $container = new CContainer_Container();
        $container->singleton('foo', function ($app, $config) {
            return $config;
        });

        $this->assertEquals(['name' => 'a'], $container->make('foo', ['name' => 'a']));
        $this->assertEquals(['name' => 'b'], $container->make('foo', ['name' => 'b']));
    }

    public function testBuildWorksWithoutAParameterStack() {
        $container = new CContainer_Container();
        $this->assertInstanceOf(ContainerResolutionTestConcrete::class, $container->build(ContainerResolutionTestConcrete::class));

        $container->bind(ContainerResolutionTestContract::class, ContainerResolutionTestImplementation::class);
        $this->assertInstanceOf(ContainerResolutionTestDependent::class, $container->build(ContainerResolutionTestDependent::class));
    }

    public function testHasKnowsBoundEntries() {
        $container = new CContainer_Container();
        $container->bind(ContainerResolutionTestContract::class, ContainerResolutionTestImplementation::class);

        $this->assertTrue($container->has(ContainerResolutionTestContract::class));
        $this->assertFalse($container->has(ContainerResolutionTestConcrete::class));
    }

    public function testGetResolvesAnyBoundWordAndConcreteClasses() {
        $container = new CContainer_Container();
        $container->bind('Cresenity', stdClass::class);

        $this->assertInstanceOf(stdClass::class, $container->get('Cresenity'));
        $this->assertInstanceOf(ContainerResolutionTestConcrete::class, $container->get(ContainerResolutionTestConcrete::class));
    }

    public function testGetOnUnknownEntryThrowsEntryNotFound() {
        $container = new CContainer_Container();

        $this->expectException(CContainer_Exception_EntryNotFoundException::class);
        $container->get('Cresenity');
    }

    public function testGetOnABoundButUnresolvableEntryThrowsBindingResolution() {
        $container = new CContainer_Container();
        $container->bind('Cresenity', ContainerResolutionTestContract::class);

        $this->expectException(CContainer_Exception_BindingResolutionException::class);
        $container->get('Cresenity');
    }

    public function testMethodLevelContextualBindingIsNotAppliedByCall() {
        //hulu mendorong kelas target ke buildStack di call() sehingga binding kontekstual ikut
        //berlaku untuk parameter method; CF belum, jadi binding global yang dipakai
        $container = new CContainer_Container();
        $container->bind(ContainerResolutionTestContract::class, ContainerResolutionTestImplementationTwo::class);
        $container->when(ContainerResolutionTestCallTarget::class)
            ->needs(ContainerResolutionTestContract::class)
            ->give(ContainerResolutionTestImplementation::class);

        $result = $container->call([new ContainerResolutionTestCallTarget(), 'work']);

        $this->assertInstanceOf(ContainerResolutionTestImplementationTwo::class, $result);
    }

    public function testExtendersAreForgotten() {
        $container = new CContainer_Container();
        $container->bind('foo', function () {
            return 'asli';
        });
        $container->extend('foo', function ($value) {
            return $value . '+ext';
        });
        $this->assertSame('asli+ext', $container->make('foo'));

        $container->forgetExtenders('foo');
        $this->assertSame('asli', $container->make('foo'));
    }

    public function testBindMethodOverridesTheMethodCalledThroughCall() {
        $container = new CContainer_Container();
        $container->bindMethod(ContainerResolutionTestCallTarget::class . '@work', function ($instance, $app) {
            return 'diganti';
        });

        $this->assertTrue($container->hasMethodBinding(ContainerResolutionTestCallTarget::class . '@work'));
        $this->assertSame('diganti', $container->call([new ContainerResolutionTestCallTarget(), 'work']));
    }

    public function testWrapInjectsDependenciesWhenTheClosureIsExecuted() {
        $container = new CContainer_Container();
        $container->bind(ContainerResolutionTestContract::class, ContainerResolutionTestImplementation::class);
        $wrapped = $container->wrap(function (ContainerResolutionTestContract $impl, $extra) {
            return [$impl, $extra];
        }, ['extra' => 'x']);

        $result = $wrapped();
        $this->assertInstanceOf(ContainerResolutionTestImplementation::class, $result[0]);
        $this->assertSame('x', $result[1]);
    }

    public function testMagicPropertyAccessMirrorsArrayAccess() {
        $container = new CContainer_Container();
        $container->nama = function () {
            return 'Cresenity';
        };

        $this->assertSame('Cresenity', $container->nama);
        $this->assertSame('Cresenity', $container['nama']);
    }
}

class ContainerResolutionTestConcrete {
}

interface ContainerResolutionTestContract {
}

class ContainerResolutionTestImplementation implements ContainerResolutionTestContract {
}

class ContainerResolutionTestImplementationTwo implements ContainerResolutionTestContract {
}

class ContainerResolutionTestDependent {
    /**
     * @var ContainerResolutionTestContract
     */
    public $impl;

    public function __construct(ContainerResolutionTestContract $impl) {
        $this->impl = $impl;
    }
}

class ContainerResolutionTestOptionalContract {
    /**
     * @var null|ContainerResolutionTestContract
     */
    public $impl;

    public function __construct(?ContainerResolutionTestContract $impl = null) {
        $this->impl = $impl;
    }
}

class ContainerResolutionTestDefaultValue {
    /**
     * @var ContainerResolutionTestConcrete
     */
    public $stub;

    /**
     * @var string
     */
    public $default;

    public function __construct(ContainerResolutionTestConcrete $stub, $default = 'bawaan') {
        $this->stub = $stub;
        $this->default = $default;
    }
}

class ContainerResolutionTestMixedPrimitive {
    /**
     * @var mixed
     */
    public $first;

    /**
     * @var mixed
     */
    public $last;

    /**
     * @var ContainerResolutionTestConcrete
     */
    public $stub;

    public function __construct($first, ContainerResolutionTestConcrete $stub, $last) {
        $this->stub = $stub;
        $this->last = $last;
        $this->first = $first;
    }
}

class ContainerResolutionTestInjectVariable implements ContainerResolutionTestContract {
    /**
     * @var mixed
     */
    public $something;

    public function __construct(ContainerResolutionTestConcrete $concrete, $something) {
        $this->something = $something;
    }
}

class ContainerResolutionTestCallTarget {
    public function work(ContainerResolutionTestContract $stub) {
        return $stub;
    }
}

class ContainerResolutionTestRefreshTarget {
    /**
     * @var mixed
     */
    public $value;

    public function setValue($value) {
        $this->value = $value;
    }
}
