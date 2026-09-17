<?php

use PHPUnit\Framework\TestCase;

/**
 * CContainer_Container - berapa kali callback resolving dipanggil untuk tiap bentuk binding
 * (interface → kelas, closure, string abstrak, rebinding), padanan bagian hitungan suite hulu.
 */
class ResolvingCallbackCountTest extends TestCase {
    /**
     * @param CContainer_Container $container
     * @param string               $abstract
     * @param int                  $counter
     */
    private function countResolving(CContainer_Container $container, $abstract, &$counter) {
        $container->resolving($abstract, function () use (&$counter) {
            $counter++;
        });
    }

    public function testInterfaceCallbackFiresOncePerResolutionOfTheImplementation() {
        $container = new CContainer_Container();
        $counter = 0;
        $this->countResolving($container, ResolvingCountContract::class, $counter);
        $container->bind(ResolvingCountContract::class, ResolvingCountImplementation::class);

        $container->make(ResolvingCountImplementation::class);
        $this->assertSame(1, $counter);
        $container->make(ResolvingCountImplementation::class);
        $this->assertSame(2, $counter);
    }

    public function testInterfaceCallbackFiresOnceWhenResolvedThroughTheInterface() {
        $container = new CContainer_Container();
        $counter = 0;
        $this->countResolving($container, ResolvingCountContract::class, $counter);
        $container->bind(ResolvingCountContract::class, ResolvingCountImplementation::class);

        $container->make(ResolvingCountContract::class);
        $this->assertSame(1, $counter);
    }

    public function testGlobalCallbackFiresOncePerResolution() {
        $container = new CContainer_Container();
        $counter = 0;
        $container->resolving(function () use (&$counter) {
            $counter++;
        });
        $container->bind(ResolvingCountContract::class, ResolvingCountImplementation::class);

        $container->make(ResolvingCountImplementation::class);
        $this->assertSame(1, $counter);
        $container->make(ResolvingCountContract::class);
        $this->assertSame(2, $counter);
    }

    public function testCallbacksFireOncePerResolutionOfBoundConcretes() {
        $container = new CContainer_Container();
        $counter = 0;
        $this->countResolving($container, ResolvingCountContract::class, $counter);
        $container->bind(ResolvingCountContract::class, ResolvingCountImplementation::class);
        $container->bind(ResolvingCountImplementation::class);

        $container->make(ResolvingCountImplementation::class);
        $this->assertSame(1, $counter);
        $container->make(ResolvingCountImplementation::class);
        $this->assertSame(2, $counter);
        $container->make(ResolvingCountContract::class);
        $this->assertSame(3, $counter);
    }

    public function testCallbacksCanStillBeAddedAfterTheFirstResolution() {
        $container = new CContainer_Container();
        $container->bind(ResolvingCountContract::class, ResolvingCountImplementation::class);
        $container->make(ResolvingCountImplementation::class);

        $counter = 0;
        $this->countResolving($container, ResolvingCountContract::class, $counter);
        $container->make(ResolvingCountImplementation::class);
        $this->assertSame(1, $counter);
    }

    public function testConcreteCallbackStopsFiringWhenTheInterfaceIsReboundElsewhere() {
        $container = new CContainer_Container();
        $container->bind(ResolvingCountContract::class, ResolvingCountImplementation::class);
        $counter = 0;
        $this->countResolving($container, ResolvingCountImplementation::class, $counter);

        $container->make(ResolvingCountContract::class);
        $this->assertSame(1, $counter);

        $container->bind(ResolvingCountContract::class, ResolvingCountImplementationTwo::class);
        $container->make(ResolvingCountContract::class);
        $this->assertSame(1, $counter);
    }

    public function testStringAbstractionCallbackFiresOncePerResolution() {
        $container = new CContainer_Container();
        $counter = 0;
        $this->countResolving($container, 'foo', $counter);
        $container->bind('foo', ResolvingCountImplementation::class);

        $container->make('foo');
        $this->assertSame(1, $counter);
        $container->make('foo');
        $this->assertSame(2, $counter);
    }

    public function testConcreteCallbackFiresOnceForEveryStringAbstractionOfIt() {
        $container = new CContainer_Container();
        $counter = 0;
        $this->countResolving($container, ResolvingCountImplementation::class, $counter);
        $container->bind('foo', ResolvingCountImplementation::class);
        $container->bind('bar', ResolvingCountImplementation::class);
        $container->bind(ResolvingCountContract::class, ResolvingCountImplementation::class);

        $container->make(ResolvingCountImplementation::class);
        $this->assertSame(1, $counter);
        $container->make('foo');
        $this->assertSame(2, $counter);
        $container->make('bar');
        $this->assertSame(3, $counter);
        $container->make(ResolvingCountContract::class);
        $this->assertSame(4, $counter);
    }

    public function testInterfaceCallbackFiresOnceWithAClosureBinding() {
        $container = new CContainer_Container();
        $counter = 0;
        $this->countResolving($container, ResolvingCountContract::class, $counter);
        $container->bind(ResolvingCountContract::class, function () {
            return new ResolvingCountImplementation();
        });

        $container->make(ResolvingCountContract::class);
        $this->assertSame(1, $counter);
        $container->make(ResolvingCountImplementation::class);
        $this->assertSame(2, $counter);
        $container->make(ResolvingCountImplementation::class);
        $this->assertSame(3, $counter);
        $container->make(ResolvingCountContract::class);
        $this->assertSame(4, $counter);
    }

    public function testRebindingBeforeAnyResolutionDoesNotFireCallbacks() {
        $container = new CContainer_Container();
        $counter = 0;
        $this->countResolving($container, ResolvingCountContract::class, $counter);
        $container->bind(ResolvingCountContract::class, ResolvingCountImplementation::class);
        $container->bind(ResolvingCountContract::class, function () {
            return new ResolvingCountImplementation();
        });
        $this->assertSame(0, $counter);

        $container->make(ResolvingCountContract::class);
        $this->assertSame(1, $counter);
        $container->make(ResolvingCountImplementation::class);
        $this->assertSame(2, $counter);
    }

    public function testCallbacksReceiveTheObjectAndTheContainer() {
        $container = new CContainer_Container();
        $seen = [];
        $record = function ($obj, $app) use (&$seen, $container) {
            $seen[] = [get_class($obj), $app === $container];
        };
        $container->resolving(ResolvingCountContract::class, $record);
        $container->afterResolving(ResolvingCountContract::class, $record);
        $container->afterResolving($record);
        $container->bind(ResolvingCountContract::class, ResolvingCountImplementationTwo::class);

        $container->make(ResolvingCountContract::class);

        $this->assertSame([
            [ResolvingCountImplementationTwo::class, true],
            [ResolvingCountImplementationTwo::class, true],
            [ResolvingCountImplementationTwo::class, true],
        ], $seen);
    }

    public function testReboundOfAResolvedBindingResolvesAgainAndFiresCallbacks() {
        $container = new CContainer_Container();
        $resolving = 0;
        $rebound = 0;
        $this->countResolving($container, ResolvingCountContract::class, $resolving);
        $container->rebinding(ResolvingCountContract::class, function () use (&$rebound) {
            $rebound++;
        });
        $container->bind(ResolvingCountContract::class, ResolvingCountImplementation::class);

        $container->make(ResolvingCountContract::class);
        $this->assertSame([1, 0], [$resolving, $rebound]);

        //bind ulang sesudah pernah di-resolve → rebound() me-resolve lagi untuk listener
        $container->bind(ResolvingCountContract::class, ResolvingCountImplementationTwo::class);
        $this->assertSame([2, 1], [$resolving, $rebound]);

        $container->make(ResolvingCountImplementationTwo::class);
        $this->assertSame([3, 1], [$resolving, $rebound]);

        $container->bind(ResolvingCountContract::class, function () {
            return new ResolvingCountImplementationTwo();
        });
        $this->assertSame([4, 2], [$resolving, $rebound]);

        $container->make(ResolvingCountContract::class);
        $this->assertSame([5, 2], [$resolving, $rebound]);
    }

    public function testReboundWithoutListenersDoesNotResolveAgain() {
        $container = new CContainer_Container();
        $counter = 0;
        $this->countResolving($container, ResolvingCountContract::class, $counter);
        $container->bind(ResolvingCountContract::class, ResolvingCountImplementation::class);

        $container->make(ResolvingCountContract::class);
        $this->assertSame(1, $counter);

        //tanpa listener rebinding, bind ulang tidak memicu resolusi apa pun
        $container->bind(ResolvingCountContract::class, ResolvingCountImplementationTwo::class);
        $this->assertSame(1, $counter);

        $container->make(ResolvingCountImplementationTwo::class);
        $this->assertSame(2, $counter);
    }

    public function testCallbacksOnTheInterfaceAndTheConcreteBothFire() {
        $container = new CContainer_Container();
        $counter = 0;
        $this->countResolving($container, ResolvingCountContract::class, $counter);
        $this->countResolving($container, ResolvingCountImplementationTwo::class, $counter);
        $container->bind(ResolvingCountContract::class, ResolvingCountImplementation::class);

        $container->make(ResolvingCountContract::class);
        $this->assertSame(1, $counter);
        $container->make(ResolvingCountImplementation::class);
        $this->assertSame(2, $counter);
        $container->make(ResolvingCountImplementationTwo::class);
        $this->assertSame(4, $counter, 'interface yang diimplementasikan + kelasnya sendiri');
    }

    public function testConcreteCallbackFiresWhenResolvedThroughTheInterface() {
        $container = new CContainer_Container();
        $counter = 0;
        $this->countResolving($container, ResolvingCountImplementation::class, $counter);
        $container->bind(ResolvingCountContract::class, ResolvingCountImplementation::class);

        $container->make(ResolvingCountContract::class);
        $this->assertSame(1, $counter);
        $container->make(ResolvingCountImplementation::class);
        $this->assertSame(2, $counter);
    }

    public function testCallbacksFireForClassesWithNoBindingAtAll() {
        $container = new CContainer_Container();
        $concrete = 0;
        $interface = 0;
        $this->countResolving($container, ResolvingCountImplementation::class, $concrete);
        $this->countResolving($container, ResolvingCountContract::class, $interface);

        $container->make(ResolvingCountImplementation::class);
        $container->make(ResolvingCountImplementation::class);

        $this->assertSame(2, $concrete);
        $this->assertSame(2, $interface);
    }

    public function testAfterResolvingCallbacksFireOncePerResolution() {
        $container = new CContainer_Container();
        $counter = 0;
        $container->afterResolving(ResolvingCountContract::class, function () use (&$counter) {
            $counter++;
        });
        $container->bind(ResolvingCountContract::class, ResolvingCountImplementation::class);

        $container->make(ResolvingCountImplementation::class);
        $this->assertSame(1, $counter);
        $container->make(ResolvingCountContract::class);
        $this->assertSame(2, $counter);
    }
}

interface ResolvingCountContract {
}

class ResolvingCountImplementation implements ResolvingCountContract {
}

class ResolvingCountImplementationTwo implements ResolvingCountContract {
}
