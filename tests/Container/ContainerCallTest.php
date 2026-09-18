<?php

use PHPUnit\Framework\TestCase;

class ContainerCallStub {
}

class ContainerCallConcreteStub {
}

class ContainerCallTestClass {
    /**
     * @return string
     */
    public function work() {
        return 'bekerja';
    }

    /**
     * @param ContainerCallStub $stub
     * @param string            $default
     *
     * @return array
     */
    public function inject(ContainerCallStub $stub, $default = 'bawaan') {
        return [$stub, $default];
    }

    /**
     * @param string $satu
     * @param string $dua
     *
     * @return array
     */
    public function untyped($satu, $dua = 'dua') {
        return [$satu, $dua];
    }

    /**
     * @return string
     */
    public static function statik() {
        return 'statik';
    }

    /**
     * @return string
     */
    public function __invoke() {
        return 'terpanggil';
    }
}

class ContainerCallVariadicStub {
    /**
     * @var array
     */
    public $stubs;

    public function __construct(ContainerCallStub ...$stubs) {
        $this->stubs = $stubs;
    }
}

class ContainerCallTest extends TestCase {
    /**
     * @return CContainer_Container
     */
    protected function makeContainer() {
        return new CContainer_Container();
    }

    public function testCallWithAnArrayCallable() {
        $container = $this->makeContainer();

        $this->assertSame('bekerja', $container->call([new ContainerCallTestClass(), 'work']));
    }

    public function testCallWithAtSignSyntax() {
        $container = $this->makeContainer();

        $this->assertSame('bekerja', $container->call(ContainerCallTestClass::class . '@work'));
    }

    /**
     * The default method is used when the string names a class with no method.
     */
    public function testCallWithADefaultMethod() {
        $container = $this->makeContainer();

        $this->assertSame('bekerja', $container->call(ContainerCallTestClass::class, [], 'work'));
    }

    public function testCallOnAnInvokableObject() {
        $container = $this->makeContainer();

        $this->assertSame('terpanggil', $container->call(new ContainerCallTestClass()));
    }

    public function testCallAStaticMethod() {
        $container = $this->makeContainer();

        $this->assertSame('statik', $container->call(ContainerCallTestClass::class . '::statik'));
    }

    public function testCallResolvesTypeHintedDependencies() {
        $container = $this->makeContainer();
        list($stub, $default) = $container->call([new ContainerCallTestClass(), 'inject']);

        $this->assertInstanceOf(ContainerCallStub::class, $stub);
        $this->assertSame('bawaan', $default);
    }

    public function testCallParametersOverrideADefault() {
        $container = $this->makeContainer();
        list(, $default) = $container->call([new ContainerCallTestClass(), 'inject'], ['default' => 'ditimpa']);

        $this->assertSame('ditimpa', $default);
    }

    public function testCallSuppliesUntypedParametersByName() {
        $container = $this->makeContainer();
        $result = $container->call([new ContainerCallTestClass(), 'untyped'], ['satu' => 'a']);

        $this->assertSame(['a', 'dua'], $result);
    }

    /**
     * Parameter bernomor tidak dipetakan menurut urutan: tiap parameter diisi
     * menurut nama, tipe, atau nilai bawaannya. `$satu` yang tanpa nama dan
     * tanpa bawaan karena itu tidak bisa dipenuhi, dan ini ditolak terang-terangan
     * (dulu argumennya diam-diam bergeser: bawaan `$dua` maju ke depan).
     */
    public function testUnnamedParametersCannotFillARequiredParameter() {
        $container = $this->makeContainer();

        $this->expectException(CContainer_Exception_BindingResolutionException::class);
        $this->expectExceptionMessage('Unable to resolve dependency [Parameter #0 [ <required> $satu ]] in class ContainerCallTestClass');
        $container->call([new ContainerCallTestClass(), 'untyped'], ['a', 'b']);
    }

    public function testCallAClosureWithoutDependencies() {
        $container = $this->makeContainer();

        $this->assertSame('halo', $container->call(function () {
            return 'halo';
        }));
    }

    public function testCallAClosureResolvesTypeHintedDependencies() {
        $container = $this->makeContainer();
        $resolved = $container->call(function (ContainerCallStub $stub) {
            return $stub;
        });

        $this->assertInstanceOf(ContainerCallStub::class, $resolved);
    }

    public function testCallAClosureWithParametersByName() {
        $container = $this->makeContainer();
        $resolved = $container->call(function ($nama) {
            return $nama;
        }, ['nama' => 'Cresenity']);

        $this->assertSame('Cresenity', $resolved);
    }

    public function testCallAClosureMixingDependenciesAndParameters() {
        $container = $this->makeContainer();
        $resolved = $container->call(function (ContainerCallStub $stub, $nama) {
            return [$stub, $nama];
        }, ['nama' => 'Cresenity']);

        $this->assertInstanceOf(ContainerCallStub::class, $resolved[0]);
        $this->assertSame('Cresenity', $resolved[1]);
    }

    public function testCallHonoursABoundImplementationForATypeHint() {
        $container = $this->makeContainer();
        $shared = new ContainerCallStub();
        $container->instance(ContainerCallStub::class, $shared);
        $resolved = $container->call(function (ContainerCallStub $stub) {
            return $stub;
        });

        $this->assertSame($shared, $resolved);
    }

    public function testBindMethodReplacesTheCall() {
        $container = $this->makeContainer();
        $container->bindMethod(ContainerCallTestClass::class . '@work', function () {
            return 'diganti';
        });

        $this->assertTrue($container->hasMethodBinding(ContainerCallTestClass::class . '@work'));
        $this->assertSame('diganti', $container->call([new ContainerCallTestClass(), 'work']));
    }

    public function testAMethodWithoutABindingIsUnaffected() {
        $container = $this->makeContainer();
        $container->bindMethod(ContainerCallTestClass::class . '@work', function () {
            return 'diganti';
        });

        $this->assertFalse($container->hasMethodBinding(ContainerCallTestClass::class . '@untyped'));
        $this->assertSame(['a', 'dua'], $container->call([new ContainerCallTestClass(), 'untyped'], ['satu' => 'a']));
    }

    public function testWrapReturnsAClosureThatCallsLater() {
        $container = $this->makeContainer();
        $wrapped = $container->wrap(function (ContainerCallStub $stub) {
            return $stub;
        });

        $this->assertInstanceOf(Closure::class, $wrapped);
        $this->assertInstanceOf(ContainerCallStub::class, $wrapped());
    }

    /**
     * Sebuah tipe variadic diisi satu instance, bukan dibiarkan kosong dan
     * bukan pula menggagalkan pembangunan.
     */
    public function testAVariadicDependencyResolvesToASingleInstance() {
        $container = $this->makeContainer();
        $resolved = $container->make(ContainerCallVariadicStub::class);

        $this->assertCount(1, $resolved->stubs);
        $this->assertInstanceOf(ContainerCallStub::class, $resolved->stubs[0]);
    }
}
