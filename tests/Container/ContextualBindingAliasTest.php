<?php

use PHPUnit\Framework\TestCase;

/**
 * CContainer_Container - binding kontekstual berhadapan dengan instance(), alias, dan
 * pemanggilan method; lanjutan suite hulu yang belum tercakup ContextualBindingTest.
 */
class ContextualBindingAliasTest extends TestCase {
    public function testContextualBindingBeatsAnExistingInstance() {
        $container = new CContainer_Container();
        $container->instance(ContextualAliasContract::class, new ContextualAliasImplementation());
        $container->when(ContextualAliasConsumerOne::class)->needs(ContextualAliasContract::class)->give(ContextualAliasImplementationTwo::class);

        $this->assertInstanceOf(ContextualAliasImplementationTwo::class, $container->make(ContextualAliasConsumerOne::class)->impl);
    }

    public function testContextualBindingBeatsAnInstanceRegisteredLater() {
        $container = new CContainer_Container();
        $container->when(ContextualAliasConsumerOne::class)->needs(ContextualAliasContract::class)->give(ContextualAliasImplementationTwo::class);
        $container->instance(ContextualAliasContract::class, new ContextualAliasImplementation());

        $this->assertInstanceOf(ContextualAliasImplementationTwo::class, $container->make(ContextualAliasConsumerOne::class)->impl);
    }

    public function testContextualBindingWorksOnExistingAliasedInstances() {
        $container = new CContainer_Container();
        $container->instance('stub', new ContextualAliasImplementation());
        $container->alias('stub', ContextualAliasContract::class);
        $container->when(ContextualAliasConsumerOne::class)->needs(ContextualAliasContract::class)->give(ContextualAliasImplementationTwo::class);

        $this->assertInstanceOf(ContextualAliasImplementationTwo::class, $container->make(ContextualAliasConsumerOne::class)->impl);
    }

    public function testContextualBindingWorksOnNewAliasedInstances() {
        $container = new CContainer_Container();
        $container->when(ContextualAliasConsumerOne::class)->needs(ContextualAliasContract::class)->give(ContextualAliasImplementationTwo::class);
        $container->instance('stub', new ContextualAliasImplementation());
        $container->alias('stub', ContextualAliasContract::class);

        $this->assertInstanceOf(ContextualAliasImplementationTwo::class, $container->make(ContextualAliasConsumerOne::class)->impl);
    }

    public function testContextualBindingWorksOnNewAliasedBindings() {
        $container = new CContainer_Container();
        $container->when(ContextualAliasConsumerOne::class)->needs(ContextualAliasContract::class)->give(ContextualAliasImplementationTwo::class);
        $container->bind('stub', ContextualAliasImplementation::class);
        $container->alias('stub', ContextualAliasContract::class);

        $this->assertInstanceOf(ContextualAliasImplementationTwo::class, $container->make(ContextualAliasConsumerOne::class)->impl);
    }

    public function testContextualBindingDoesNotFollowStaleAliases() {
        $container = new CContainer_Container();
        $container->when(ContextualAliasConsumerOne::class)->needs('stale')->give(ContextualAliasImplementation::class);
        $container->when(ContextualAliasConsumerOne::class)->needs('live')->give(ContextualAliasImplementationTwo::class);

        $container->alias(ContextualAliasContract::class, 'stale');
        //alias 'stale' dipindahkan ke abstrak lain; yang tersisa untuk kontrak hanya 'live'
        $container->alias('unrelated', 'stale');
        $container->alias(ContextualAliasContract::class, 'live');

        $this->assertInstanceOf(ContextualAliasImplementationTwo::class, $container->make(ContextualAliasConsumerOne::class)->impl);
    }

    public function testContextualBindingDoesNotOverrideNonContextualResolution() {
        $container = new CContainer_Container();
        $container->instance('stub', new ContextualAliasImplementation());
        $container->alias('stub', ContextualAliasContract::class);
        $container->when(ContextualAliasConsumerTwo::class)->needs(ContextualAliasContract::class)->give(ContextualAliasImplementationTwo::class);

        $this->assertInstanceOf(ContextualAliasImplementationTwo::class, $container->make(ContextualAliasConsumerTwo::class)->impl);
        $this->assertInstanceOf(ContextualAliasImplementation::class, $container->make(ContextualAliasConsumerOne::class)->impl);
    }

    public function testContextuallyBoundInstancesAreNotRecreated() {
        ContextualAliasCounted::$instantiations = 0;
        $container = new CContainer_Container();
        $container->instance(ContextualAliasContract::class, new ContextualAliasImplementation());
        $container->instance(ContextualAliasCounted::class, new ContextualAliasCounted());
        $this->assertSame(1, ContextualAliasCounted::$instantiations);

        $container->when(ContextualAliasConsumerOne::class)->needs(ContextualAliasContract::class)->give(ContextualAliasCounted::class);
        $container->make(ContextualAliasConsumerOne::class);
        $container->make(ContextualAliasConsumerOne::class);
        $container->make(ContextualAliasConsumerOne::class);

        $this->assertSame(1, ContextualAliasCounted::$instantiations, 'instance() yang diberikan kontekstual dipakai ulang, bukan dibangun lagi');
    }

    public function testContextualBindingWorksWithAliasedTargets() {
        $container = new CContainer_Container();
        $container->bind(ContextualAliasContract::class, ContextualAliasImplementation::class);
        $container->alias(ContextualAliasContract::class, 'interface-stub');
        $container->alias(ContextualAliasImplementation::class, 'stub-1');

        $container->when(ContextualAliasConsumerOne::class)->needs('interface-stub')->give('stub-1');
        $container->when(ContextualAliasConsumerTwo::class)->needs('interface-stub')->give(ContextualAliasImplementationTwo::class);

        $this->assertInstanceOf(ContextualAliasImplementation::class, $container->make(ContextualAliasConsumerOne::class)->impl);
        $this->assertInstanceOf(ContextualAliasImplementationTwo::class, $container->make(ContextualAliasConsumerTwo::class)->impl);
    }

    public function testContextualBindingWorksForNestedOptionalDependencies() {
        $container = new CContainer_Container();
        $container->when(ContextualAliasTwoInstances::class)->needs(ContextualAliasConsumerTwo::class)->give(function () {
            return new ContextualAliasConsumerTwo(new ContextualAliasImplementationTwo());
        });

        $resolved = $container->make(ContextualAliasTwoInstances::class);

        $this->assertInstanceOf(ContextualAliasOptionalInner::class, $resolved->implOne);
        $this->assertNull($resolved->implOne->inner, 'interface tanpa binding pada parameter opsional → null');
        $this->assertInstanceOf(ContextualAliasConsumerTwo::class, $resolved->implTwo);
        $this->assertInstanceOf(ContextualAliasImplementationTwo::class, $resolved->implTwo->impl);
    }

    public function testContextualBindingIsNotYetAppliedToMethodInvocation() {
        //hulu memakai binding kontekstual kelas target juga untuk parameter method lewat call();
        //CF belum: tanpa binding global, interface-nya tidak bisa dibangun
        $container = new CContainer_Container();
        $container->when(ContextualAliasMethodArgument::class)->needs(ContextualAliasContract::class)->give(ContextualAliasImplementation::class);

        $this->expectException(CContainer_Exception_BindingResolutionException::class);
        $container->call([new ContextualAliasMethodArgument(), 'method']);
    }
}

interface ContextualAliasContract {
}

class ContextualAliasImplementation implements ContextualAliasContract {
}

class ContextualAliasImplementationTwo implements ContextualAliasContract {
}

class ContextualAliasCounted implements ContextualAliasContract {
    /**
     * @var int
     */
    public static $instantiations = 0;

    public function __construct() {
        static::$instantiations++;
    }
}

class ContextualAliasConsumerOne {
    /**
     * @var ContextualAliasContract
     */
    public $impl;

    public function __construct(ContextualAliasContract $impl) {
        $this->impl = $impl;
    }
}

class ContextualAliasConsumerTwo {
    /**
     * @var ContextualAliasContract
     */
    public $impl;

    public function __construct(ContextualAliasContract $impl) {
        $this->impl = $impl;
    }
}

class ContextualAliasOptionalInner {
    /**
     * @var null|ContextualAliasContract
     */
    public $inner;

    public function __construct(?ContextualAliasContract $inner = null) {
        $this->inner = $inner;
    }
}

class ContextualAliasTwoInstances {
    /**
     * @var ContextualAliasOptionalInner
     */
    public $implOne;

    /**
     * @var ContextualAliasConsumerTwo
     */
    public $implTwo;

    public function __construct(ContextualAliasOptionalInner $implOne, ContextualAliasConsumerTwo $implTwo) {
        $this->implOne = $implOne;
        $this->implTwo = $implTwo;
    }
}

class ContextualAliasMethodArgument {
    public function method(ContextualAliasContract $dependency) {
        return $dependency;
    }
}
