<?php

use PHPUnit\Framework\TestCase;

/**
 * CRouting_Concern_RouteDependencyResolverTrait::resolveMethodDependencies() (#-13852) -
 * method_exists($reflector, 'getType') is always false (that's a ReflectionParameter method,
 * not ReflectionFunctionAbstract's), so only the "php 5.6" branch below ever actually runs in
 * every CRouting_Route/CController_ControllerDispatcher call. Characterizes that one real path.
 */
class RouteDependencyResolverTraitTest extends TestCase {
    /**
     * @return object
     */
    protected function resolver() {
        return new class() {
            use CRouting_Concern_RouteDependencyResolverTrait;

            /**
             * @var CContainer_Container
             */
            public $container;

            public function __construct() {
                $this->container = CContainer::getInstance();
            }
        };
    }

    public function testAClassTypedParameterIsResolvedFromTheContainerAndSplicedAtItsPosition() {
        $result = $this->resolver()->resolveMethodDependencies(
            ['nama'],
            new ReflectionFunction(function (RouteDependencyResolverTraitTestFixtureDep $dep, $nama) {
            })
        );

        $this->assertInstanceOf(RouteDependencyResolverTraitTestFixtureDep::class, $result[0]);
        $this->assertSame('nama', $result[1]);
    }

    public function testADependencyAlreadyPresentAsAnInstanceIsNotResolvedAgain() {
        $existing = new RouteDependencyResolverTraitTestFixtureDep();

        $result = $this->resolver()->resolveMethodDependencies(
            [$existing],
            new ReflectionFunction(function (RouteDependencyResolverTraitTestFixtureDep $dep) {
            })
        );

        $this->assertSame([$existing], $result, 'instance yang sudah ada tidak digandakan/diganti');
    }

    public function testADependencyInTheMiddleIsSplicedBetweenTheSurroundingRouteParameters() {
        $result = $this->resolver()->resolveMethodDependencies(
            ['a-val', 'b-val'],
            new ReflectionFunction(function ($a, RouteDependencyResolverTraitTestFixtureDep $dep, $b) {
            })
        );

        $this->assertSame('a-val', $result[0]);
        $this->assertInstanceOf(RouteDependencyResolverTraitTestFixtureDep::class, $result[1]);
        $this->assertSame('b-val', $result[2]);
    }

    public function testAMissingTrailingParameterIsNotFilledWithItsDefaultValue() {
        // Satu-satunya jalur yang hidup tidak pernah mengisi default - itu hanya ada di
        // cabang "modern" yang tidak pernah berjalan (guard-nya selalu salah). Mengunci ini
        // supaya menghapus cabang mati tidak diam-diam "memperbaikinya" jadi berperilaku beda.
        $result = $this->resolver()->resolveMethodDependencies(
            ['hanya-a'],
            new ReflectionFunction(function ($a, $b = 'default-b') {
            })
        );

        $this->assertSame(['hanya-a'], $result);
    }
}

class RouteDependencyResolverTraitTestFixtureDep {
}
