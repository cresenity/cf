<?php

use Carbon\CarbonImmutable;

use Mockery\Exception\InvalidCountException;
use PHPUnit\Framework\TestCase as BaseTestCase;

class CTesting_TestCase extends BaseTestCase {
    use CTesting_Concern_MakesHttpRequests;
    use CTesting_Concern_InteractsWithAuthentication;
    use CTesting_Concern_InteractsWithDatabase;
    use CTesting_Concern_InteractsWithConsole;

    /**
     * The service container instance, used by traits (MakesHttpRequests) that
     * override or fake individual middleware/service bindings during a test.
     *
     * @var CContainer_Container
     */
    protected $app;

    /**
     * The callbacks that should be run after the application is created.
     *
     * @var array
     */
    protected $afterApplicationCreatedCallbacks = [];

    /**
     * The callbacks that should be run before the application is destroyed.
     *
     * @var array
     */
    protected $beforeApplicationDestroyedCallbacks = [];

    /**
     * The exception thrown while running an application destruction callback.
     *
     * @var \Throwable
     */
    protected $callbackException;

    /**
     * Indicates if we have made it through the base setUp function.
     *
     * @var bool
     */
    protected $setUpHasRun = false;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp() {
        if (!$this->app) {
            $this->app = $this->createApplication();
        }

        $this->setUpTraits();

        foreach ($this->afterApplicationCreatedCallbacks as $callback) {
            $callback();
        }

        $this->setUpHasRun = true;
    }

    /**
     * Resolve the service container instance used by this test.
     *
     * CF bootstraps a single global container (unlike Laravel, there is no
     * separate "boot an application" step needed here since Bootstrap.php
     * already ran via the PHPUnit `bootstrap` config before tests execute).
     *
     * @return CContainer_Container
     */
    protected function createApplication() {
        return CContainer::getInstance();
    }

    /**
     * Register a callback to be run before the test's application/container
     * state is torn down (e.g. rolling back a database transaction).
     *
     * @param callable $callback
     *
     * @return void
     */
    public function beforeApplicationDestroyed(callable $callback) {
        $this->beforeApplicationDestroyedCallbacks[] = $callback;
    }

    /**
     * Boot the testing helper traits.
     *
     * @return array
     */
    protected function setUpTraits() {
        $uses = array_flip(c::classUsesRecursive(static::class));

        if (isset($uses[CTesting_Trait_RefreshDatabaseTrait::class])) {
            /** @var CTesting_Trait_RefreshDatabaseTrait $this */
            $this->refreshDatabase();
        }
        foreach ($uses as $trait) {
            if (method_exists($this, $method = 'setUp' . c::classBasename($trait))) {
                $this->{$method}();
            }

            if (method_exists($this, $method = 'tearDown' . c::classBasename($trait))) {
                $this->beforeApplicationDestroyed(fn () => $this->{$method}());
            }
        }

        return $uses;
    }

    /**
     * Clean up the testing environment before the next test.
     *
     * @throws \Mockery\Exception\InvalidCountException
     *
     * @return void
     */
    protected function tearDown() {
        $this->callBeforeApplicationDestroyedCallbacks();

        $this->setUpHasRun = false;

        if (property_exists($this, 'serverVariables')) {
            $this->serverVariables = [];
        }

        if (property_exists($this, 'defaultHeaders')) {
            $this->defaultHeaders = [];
        }

        // Middleware toggled off via withoutMiddleware() is a process-global static
        // flag (CHTTP / CApi_Manager), so it must be reset or it leaks into the next test.
        CHTTP::withMiddleware();
        CApi_Manager::withMiddlewareForAllGroups();

        // CAuth_Manager caches resolved guards (with whatever user actingAs()/be()
        // set on them) in a process-wide singleton, so an authenticated user from
        // one test otherwise leaks into every later test in the same run.
        CAuth::manager()->forgetGuards();

        // CApp_Auth (c::app()->user(), and anything built on it like OH::user())
        // caches its own resolved guard per guard name on first access, on top of
        // CAuth_Manager's cache above - forgetGuards() orphans that cached guard
        // object without CApp_Auth knowing, so later actingAs() calls stop being
        // visible through this path even though CAuth_Manager itself is fresh.
        if (class_exists(CApp_Auth::class)) {
            CApp_Auth::forgetInstances();
        }

        // The session store is resolved once from the container and reused for
        // every simulated request (like everything else here), so a test that
        // performs a real login (writing the auth id into session, not just
        // actingAs()'s in-memory setUser()) leaves that session data readable
        // by every later test's fresh guard, even after forgetGuards() above -
        // a new guard instance still reads from the same still-populated store.
        c::session()->flush();

        if (class_exists('Mockery')) {
            if ($container = Mockery::getContainer()) {
                $this->addToAssertionCount($container->mockery_getExpectationCount());
            }

            try {
                Mockery::close();
            } catch (InvalidCountException $e) {
                if (!cstr::contains($e->getMethodName(), ['doWrite', 'askQuestion'])) {
                    throw $e;
                }
            }
        }

        if (class_exists(CCarbon::class)) {
            CCarbon::setTestNow();
        }

        if (class_exists(CarbonImmutable::class)) {
            CarbonImmutable::setTestNow();
        }

        $this->afterApplicationCreatedCallbacks = [];
        $this->beforeApplicationDestroyedCallbacks = [];

        if ($this->callbackException) {
            throw $this->callbackException;
        }
    }

    /**
     * Execute the application's pre-destruction callbacks.
     *
     * @return void
     */
    protected function callBeforeApplicationDestroyedCallbacks() {
        foreach ($this->beforeApplicationDestroyedCallbacks as $callback) {
            try {
                $callback();
            } catch (Throwable $e) {
                if (!$this->callbackException) {
                    $this->callbackException = $e;
                }
            }
        }
    }
}
