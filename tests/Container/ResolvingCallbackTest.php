<?php

use PHPUnit\Framework\TestCase;

interface ResolvingCallbackContract {
}

class ResolvingCallbackStub implements ResolvingCallbackContract {
    /**
     * @var string
     */
    public $tanda = '';
}

class ResolvingCallbackOtherStub {
}

class ResolvingCallbackTest extends TestCase {
    /**
     * @return CContainer_Container
     */
    protected function makeContainer() {
        return new CContainer_Container();
    }

    public function testResolvingCallbackFiresForItsOwnAbstract() {
        $container = $this->makeContainer();
        $fired = 0;
        $container->resolving(ResolvingCallbackStub::class, function () use (&$fired) {
            $fired++;
        });
        $container->make(ResolvingCallbackStub::class);

        $this->assertSame(1, $fired);
    }

    public function testResolvingCallbackDoesNotFireForAnotherAbstract() {
        $container = $this->makeContainer();
        $fired = 0;
        $container->resolving(ResolvingCallbackStub::class, function () use (&$fired) {
            $fired++;
        });
        $container->make(ResolvingCallbackOtherStub::class);

        $this->assertSame(0, $fired);
    }

    public function testAGlobalResolvingCallbackFiresForEveryAbstract() {
        $container = $this->makeContainer();
        $fired = 0;
        $container->resolving(function () use (&$fired) {
            $fired++;
        });
        $container->make(ResolvingCallbackStub::class);
        $container->make(ResolvingCallbackOtherStub::class);

        $this->assertSame(2, $fired);
    }

    public function testTheCallbackReceivesTheObjectAndTheContainer() {
        $container = $this->makeContainer();
        $seenObject = null;
        $seenContainer = null;
        $container->resolving(ResolvingCallbackStub::class, function ($object, $c) use (&$seenObject, &$seenContainer) {
            $seenObject = $object;
            $seenContainer = $c;
        });
        $resolved = $container->make(ResolvingCallbackStub::class);

        $this->assertSame($resolved, $seenObject);
        $this->assertSame($container, $seenContainer);
    }

    public function testTheCallbackCanMutateTheResolvedObject() {
        $container = $this->makeContainer();
        $container->resolving(ResolvingCallbackStub::class, function ($object) {
            $object->tanda = 'disentuh';
        });

        $this->assertSame('disentuh', $container->make(ResolvingCallbackStub::class)->tanda);
    }

    public function testResolvingFiresOnEveryBuildOfAPlainBinding() {
        $container = $this->makeContainer();
        $fired = 0;
        $container->bind(ResolvingCallbackStub::class);
        $container->resolving(ResolvingCallbackStub::class, function () use (&$fired) {
            $fired++;
        });
        $container->make(ResolvingCallbackStub::class);
        $container->make(ResolvingCallbackStub::class);

        $this->assertSame(2, $fired);
    }

    /**
     * A singleton is built once, so its callbacks run once no matter how often
     * it is asked for.
     */
    public function testResolvingFiresOnlyOnceForASingleton() {
        $container = $this->makeContainer();
        $fired = 0;
        $container->singleton(ResolvingCallbackStub::class);
        $container->resolving(ResolvingCallbackStub::class, function () use (&$fired) {
            $fired++;
        });
        $container->make(ResolvingCallbackStub::class);
        $container->make(ResolvingCallbackStub::class);

        $this->assertSame(1, $fired);
    }

    public function testSeveralCallbacksAllFireInOrder() {
        $container = $this->makeContainer();
        $urutan = [];
        $container->resolving(ResolvingCallbackStub::class, function () use (&$urutan) {
            $urutan[] = 'a';
        });
        $container->resolving(ResolvingCallbackStub::class, function () use (&$urutan) {
            $urutan[] = 'b';
        });
        $container->make(ResolvingCallbackStub::class);

        $this->assertSame(['a', 'b'], $urutan);
    }

    public function testAfterResolvingRunsAfterResolving() {
        $container = $this->makeContainer();
        $urutan = [];
        $container->afterResolving(ResolvingCallbackStub::class, function () use (&$urutan) {
            $urutan[] = 'sesudah';
        });
        $container->resolving(ResolvingCallbackStub::class, function () use (&$urutan) {
            $urutan[] = 'saat';
        });
        $container->make(ResolvingCallbackStub::class);

        $this->assertSame(['saat', 'sesudah'], $urutan);
    }

    public function testAGlobalAfterResolvingCallbackFiresForEveryAbstract() {
        $container = $this->makeContainer();
        $fired = 0;
        $container->afterResolving(function () use (&$fired) {
            $fired++;
        });
        $container->make(ResolvingCallbackStub::class);
        $container->make(ResolvingCallbackOtherStub::class);

        $this->assertSame(2, $fired);
    }

    /**
     * Callback pada interface ikut menyala untuk kelas konkret yang dibangun
     * untuknya — tepat sekali: bingkai resolusi dalam (kelas konkret) tidak
     * memancarkan event lagi, hanya bingkai luar (interface yang diminta).
     */
    public function testACallbackOnAnInterfaceFiresForItsImplementation() {
        $container = $this->makeContainer();
        $fired = 0;
        $container->bind(ResolvingCallbackContract::class, ResolvingCallbackStub::class);
        $container->resolving(ResolvingCallbackContract::class, function () use (&$fired) {
            $fired++;
        });
        $container->make(ResolvingCallbackContract::class);

        $this->assertSame(1, $fired);
    }

    public function testACallbackFiresForAnAlias() {
        $container = $this->makeContainer();
        $fired = 0;
        $container->bind(ResolvingCallbackStub::class);
        $container->alias(ResolvingCallbackStub::class, 'stub');
        $container->resolving(ResolvingCallbackStub::class, function () use (&$fired) {
            $fired++;
        });
        $container->make('stub');

        $this->assertSame(1, $fired);
    }

    public function testACallbackRegisteredAfterAnInstanceIsAlreadySetDoesNotFireRetroactively() {
        $container = $this->makeContainer();
        $fired = 0;
        $container->instance(ResolvingCallbackStub::class, new ResolvingCallbackStub());
        $container->resolving(ResolvingCallbackStub::class, function () use (&$fired) {
            $fired++;
        });
        $container->make(ResolvingCallbackStub::class);

        $this->assertSame(0, $fired);
    }

    public function testCallbacksFireForADependencyBuiltAlongTheWay() {
        $container = $this->makeContainer();
        $fired = 0;
        $container->resolving(ResolvingCallbackStub::class, function () use (&$fired) {
            $fired++;
        });
        $container->make(ResolvingCallbackDependent::class);

        $this->assertSame(1, $fired);
    }
}

class ResolvingCallbackDependent {
    /**
     * @var ResolvingCallbackStub
     */
    public $stub;

    public function __construct(ResolvingCallbackStub $stub) {
        $this->stub = $stub;
    }
}
